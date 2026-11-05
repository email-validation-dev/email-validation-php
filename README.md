# email-validation.org PHP SDK

Official PHP client for the [email-validation.org](https://email-validation.org) API: validate single addresses,
read credits, usage and service status, run bulk jobs and verify webhook signatures. Typed results (readonly
classes), typed exceptions, automatic retries. PHP 8.1+, no dependencies beyond ext-curl and ext-json.

Documentation: https://email-validation.org/docs/sdks/php/

## Install

```bash
composer require email-validation/sdk
```

Requires PHP 8.1 or later with the `curl` and `json` extensions (both are part of most PHP builds).

## Validate an address

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use EmailValidation\Client;

$client = new Client(apiKey: (string) getenv('EV_API_KEY'));

$result = $client->validate('accepted@sandbox.email-validation.org'); // free test address
echo $result->status, ' ', $result->reason, ' ', $result->action, PHP_EOL; // valid accepted send
```

Keep the key on the server. Construct the client once and reuse it.

`validate(string $email, ?string $ipAddress = null, ?int $timeout = null, ?bool $fresh = null): ValidationResult`

| Argument | Meaning |
|---|---|
| `email` | the address, sent as is |
| `ipAddress` | the sign-up IP address, for fraud signals |
| `timeout` | seconds the service may spend on the check, 5 to 15 (server default 15) |
| `fresh` | `true` checks again even when the address was checked in the last 12 months (by default such a check's definite result may be returned; `processedAt` is when the answer was produced) |

The `ValidationResult` has the API's fields as camelCase readonly properties: `email`, `status`, `reason`,
`action`, `account`, `domain`, `didYouMean`, `freeEmail`, `roleAccount`, `disposable`, `catchAllDomain`, `mxFound`,
`mxRecord`, `smtpProvider`, `domainAgeDays`, `processedAt`, `creditsUsed`, `requestId`. `toArray()` (and
`json_encode()`) give back the API's snake_case JSON.

- `status` is `valid`, `invalid`, `catch_all`, `unknown` or `disposable`; `action` is `send`, `send_with_caution`,
  `remove` or `retry_later`. Spam traps and frequent complainers are `valid` with the action `remove` (reason
  `spamtrap` / `complainer`). Compare them with the constants of `Status`, `Reason`, `Action` and `SmtpProvider`.
- A check costs 1 credit (`creditsUsed`); `unknown` results, malformed addresses and sandbox addresses are free.
- Sandbox addresses `<reason>@sandbox.email-validation.org` answer that reason for free:
  `Vocabulary::sandboxAddress(Reason::CATCH_ALL)`.
- Values the API adds after this SDK was released (a new reason, provider or job status) are kept as plain strings:
  decoding never fails on them.

```php
<?php

use EmailValidation\Action;
use EmailValidation\Client;

$client = new Client(apiKey: (string) getenv('EV_API_KEY'));
$result = $client->validate('accepted@sandbox.email-validation.org', ipAddress: '203.0.113.7', timeout: 10);

if ($result->action === Action::REMOVE) {
    // do not send to this address
} elseif ($result->didYouMean !== null) {
    echo 'Did you mean ', $result->didYouMean, '?', PHP_EOL;
}
```

EU data residency (validated and stored in the EU): pass `baseUrl: Vocabulary::EU_BASE_URL` to call `https://api-eu.email-validation.org`
instead of the default `https://api.email-validation.org`. See https://email-validation.org/docs/eu-data-residency/.

## Async

The PHP client is synchronous. Run several checks concurrently with your framework's tools (queues, workers) or use
a bulk job for lists.

## Handle errors

Every API or network failure throws an exception extending `EmailValidation\Exception\EmailValidationException`.
Branch on the class or on `errorCode`, never on the message (it is for people and may change).

| Property / getter | Meaning |
|---|---|
| `status` / `getStatus()` | HTTP status, or `0` when no answer arrived (or the call was refused before sending) |
| `errorCode` / `getErrorCode()` | an API error code (`insufficient_credits`, `rate_limited` …) or a client code: `connection_error`, `timeout`, `unexpected_response` |
| `getMessage()` | the API's message, or the SDK's text |
| `requestId` / `getRequestId()` | our id for the request: quote it to support |
| `retryAfter` / `getRetryAfter()` | whole seconds from `Retry-After`, or null |
| `getPrevious()` | the underlying transport error, when there is one |

`getCode()` (PHP's integer code) holds the HTTP status too.

| Exception (`EmailValidation\Exception\…`) | When |
|---|---|
| `InvalidRequestException` | 400 `invalid_request`, 411 `length_required`, 413 `payload_too_large`, any other 4xx, a malformed job id (status 0), a range download answered without 206 |
| `AuthenticationException` | 401 `unauthorized` |
| `InsufficientCreditsException` | 402 `insufficient_credits` (nothing was charged) |
| `PermissionDeniedException` | 403 `forbidden`, `forbidden_origin` |
| `NotFoundException` | 404 `not_found` |
| `ConflictException` | 409 `conflict` |
| `RateLimitException` | 429 `rate_limited`, `abuse_throttled`, `key_daily_cap` |
| `ServiceUnavailableException` | 503 `service_unavailable`, `upstream_capacity` |
| `ServerException` | 500 `internal_error`, any other 5xx, and any other unexpected status (such as a redirect, which is never followed) |
| `ConnectionException` | no answer: network failure (`connection_error`), attempt timeout or `bulk->wait()` deadline (`timeout`) |
| `EmailValidationException` itself | a 2xx answer that is not JSON (or not the expected shape): `unexpected_response` |

Mistakes in the arguments (an empty key, a base URL that is not http(s), both `emails` and `file` …) throw PHP's
`\InvalidArgumentException` before anything is sent. Webhook checks throw `WebhookVerificationException`, which is
not part of this family.

```php
<?php

use EmailValidation\Client;
use EmailValidation\Exception\AuthenticationException;
use EmailValidation\Exception\ConnectionException;
use EmailValidation\Exception\EmailValidationException;
use EmailValidation\Exception\InsufficientCreditsException;
use EmailValidation\Exception\RateLimitException;

$client = new Client(apiKey: (string) getenv('EV_API_KEY'));

try {
    $result = $client->validate('accepted@sandbox.email-validation.org');
} catch (InsufficientCreditsException $e) {
    // buy a credit pack; nothing was charged
} catch (RateLimitException $e) {
    $wait = $e->retryAfter; // seconds, or null
} catch (AuthenticationException $e) {
    // check the key
} catch (ConnectionException $e) {
    // no answer: $e->errorCode is "connection_error" or "timeout"
} catch (EmailValidationException $e) {
    error_log(sprintf('email-validation: %s (%d, request %s)', $e->errorCode, $e->status, $e->requestId ?? '-'));
}
```

An exception's string form shows its class, status, code, request id and message. It never contains the API key,
the request body or the checked address (no stack trace either).

## Retries and timeouts

| Option | Default | Meaning |
|---|---|---|
| `timeout` | `30.0` | seconds per attempt (connect and full answer); must be positive |
| `maxRetries` | `2` | retries after the first attempt (negative values count as 0) |
| `maxRetryWait` | `10.0` | longest single wait before a retry the client accepts, in seconds |

```php
<?php

use EmailValidation\Client;

$client = new Client(
    apiKey: (string) getenv('EV_API_KEY'),
    timeout: 20.0,
    maxRetries: 3,
    maxRetryWait: 5.0,
);
```

- Answers `429` and `503` are never charged, so they are retried for every request, POST included, waiting
  `Retry-After` seconds when the answer has one (otherwise exponential backoff with jitter: 0.25 to 0.5 s, then 0.5
  to 1 s, 1 to 2 s, up to 4 to 8 s). If the wait would be longer than `maxRetryWait`, the error is thrown at once.
- `key_daily_cap` (429) is not retried: the key's daily cap resets the next day.
- A network failure or timeout is retried only for reads (GET). A validation, a bulk upload, start or cancel that
  reached the service may already have been charged or applied, so it is not sent again.
- Nothing else is retried (400 to 428, 500, 502, 504 …).
- `timeout` bounds each attempt, not the whole call. The `validate()` `timeout` argument (5 to 15 s) is the time the
  service may spend; keep the client `timeout` a few seconds above it.
- The SDK has no cancellation mechanism (PHP has none for synchronous calls); bound calls with `timeout` and
  `maxRetries`.

## Credits, usage and status

```php
<?php

use EmailValidation\Client;

$client = new Client(apiKey: (string) getenv('EV_API_KEY'));

$credits = $client->credits();
echo $credits->balance, PHP_EOL;
foreach ($credits->lots as $lot) {
    echo $lot->remaining, ' expire at ', $lot->expiresAt, PHP_EOL; // soonest-expiring first
}

$usage = $client->usage(from: '2026-10-01', to: '2026-10-20'); // by day (default)
foreach ($usage->days() as $day) {
    echo $day->date, ': ', $day->creditsUsed, PHP_EOL;
}
$byKey = $client->usage(from: '2026-10-01', to: '2026-10-20', groupBy: 'key');
foreach ($byKey->keys() as $key) {
    echo $key->name ?? '(deleted key)', ': ', $key->creditsUsed, PHP_EOL;
}

$status = $client->status();
echo $status->status, ' ', $status->components->api, PHP_EOL; // operational | degraded | outage
```

- `credits(): Credits`: `balance`, `debt`, `pending`, `lots` (`CreditLot`: `remaining`, `expiresAt`).
- `usage(?string $from = null, ?string $to = null, ?string $groupBy = null): UsageResponse`: dates are `YYYY-MM-DD`
  (UTC); `groupBy` is `day` (default) or `key`. `from`, `to`, `groupBy`, `totals` (`creditsUsed`, `byStatus`) and
  `items`; `days()` returns the `UsageDay` items (`date`, `creditsUsed`, `byStatus`), `keys()` the `UsageByKey` items
  (`keyId`, `name`, `last4`, `creditsUsed`).
- `status(): ServiceStatus`: `status`, `components` (`website`, `api`, `bulk`, `signIn`, `payments`, `support`),
  `updatedAt`.

## Bulk jobs

Bulk jobs need a key with the `bulk` scope.

```php
<?php

use EmailValidation\Client;
use EmailValidation\Model\BulkJob;

$client = new Client(apiKey: (string) getenv('EV_API_KEY'));

// from a list (sent as JSON, up to 100,000 addresses) …
$job = $client->bulk->create(emails: [
    'accepted@sandbox.email-validation.org',
    'no_mailbox@sandbox.email-validation.org',
]);

// … or from a file (CSV, or one address per line; up to 100 MB)
$job = $client->bulk->create(file: fopen('list.csv', 'rb'), column: 'email', hasHeader: true);

$job = $client->bulk->wait($job->id, onProgress: function (BulkJob $job): void {
    echo $job->status, ' ', $job->processed, '/', $job->uniqueEmails ?? '?', PHP_EOL;
});

if ($job->status === 'completed') {
    $csv = $client->bulk->results($job->id, filter: ['valid', 'catch_all']);
    file_put_contents('valid-and-catch-all.csv', $csv);
} elseif ($job->isStuckReady()) {
    echo 'Could not start: ', $job->errorCode, PHP_EOL; // e.g. insufficient_credits: buy credits, then start()
}
```

| Method | Request |
|---|---|
| `create(?array $emails = null, $file = null, string $contentType = 'text/csv', int\|string\|null $column = null, ?bool $hasHeader = null, ?bool $start = null, ?string $webhookEndpointId = null): BulkJob` | `POST /v1/bulk` |
| `get(string $jobId): BulkJob` | `GET /v1/bulk/{id}` |
| `start(string $jobId): BulkJob` | `POST /v1/bulk/{id}/start` (credits are held now) |
| `cancel(string $jobId): BulkJob` | `DELETE /v1/bulk/{id}` (unused credits are returned) |
| `results(string $jobId, string\|array\|null $filter = null, ?string $columns = null, ?bool $escapeFormulas = null, ?string $range = null): string` | `GET /v1/bulk/{id}/results`, the whole CSV |
| `resultsStream(…same arguments…)`: a stream resource | the same download as a readable stream |
| `wait(string $jobId, float $interval = 5.0, ?float $timeout = null, ?callable $onProgress = null): BulkJob` | polls `get()` |

- `create()`: pass exactly one of `emails` (a list of strings) and `file` (the file's contents as a string, or a
  readable stream; a stream is read to its end because the API needs a known length and a retry sends the same
  bytes). `contentType` is `text/csv` or `text/plain`. `column` is the address column (1 to 512, or a header name).
  `start: false` creates the job in `ready` without starting it. `webhookEndpointId` notifies a webhook endpoint
  when the job ends.
- `results()`: `filter` is `all` (default), one status, or a list of statuses; `columns` is `original` (default) or
  `email_only`; `escapeFormulas` (server default true) prefixes cells starting with `= + - @` with `'`. The CSV
  bytes come back unchanged.
- `resultsStream()` returns the CSV as a readable stream positioned at its start (spooled to `php://temp`: memory up
  to 2 MB, then a temporary file). Copy it and close it:

  ```php
  <?php

  $out = fopen('results.csv', 'wb');
  $in = $client->bulk->resultsStream($job->id);
  stream_copy_to_stream($in, $out);
  fclose($in);
  fclose($out);
  ```

- `range` (for example `bytes=0-1048575`) fetches one part of the file, to resume a download. It needs filter `all`
  (or one status with `escapeFormulas: false`) and columns `original`; any other shape throws
  `InvalidRequestException` instead of returning the whole file, so appended parts never duplicate data.
- `wait()` returns once the job is `completed`, `failed`, `cancelled` or `expired`, or once it is `ready` with an
  `errorCode` (its start was refused, for example `insufficient_credits`: buy credits, then call `start()`). Check
  `$job->status` before downloading results. `interval` is at least 1 second. With `timeout`, a
  `ConnectionException` (`timeout`, "Bulk job job_… is still running.") is thrown when the next poll would pass the
  deadline. A job created with `start: false` waits in `ready` until you call `start()`, so call `start()` before
  `wait()`.
- Job ids are checked before anything is sent: a value that is not `job_` followed by 22 letters and digits throws
  `InvalidRequestException` (status 0).
- `BulkJob` has the API's fields as camelCase readonly properties (`id`, `status`, `filename`, `totalRows`,
  `uniqueEmails`, `duplicates`, `syntaxInvalid`, `localAnswered`, `processed`, `creditsReserved`, `creditsCharged`,
  `counts`, `errorCode`, `webhookEndpointId`, `createdAt`, `startedAt`, `finishedAt`, `expiresAt`) plus
  `isTerminal()` and `isStuckReady()`.

## Verify webhooks

Deliveries are signed with the Standard Webhooks scheme. Verify them with the raw request body, before parsing it:

```php
<?php

use EmailValidation\Exception\WebhookVerificationException;
use EmailValidation\Webhook;

$payload = file_get_contents('php://input');

try {
    $event = Webhook::verify($payload, getallheaders(), (string) getenv('EV_WEBHOOK_SECRET'));
} catch (WebhookVerificationException $e) {
    http_response_code(400);
    exit;
}

// deduplicate on the webhook-id header: retries reuse it
switch ($event->type) {
    case 'bulk.completed':
    case 'bulk.failed':
        $jobId = $event->data['job_id'];
        break;
    case 'credits.low':
        $balance = $event->data['balance'];
        break;
    case 'webhook.test': // sent by "Send test" in the dashboard
        break;
}
http_response_code(204); // answer 2xx quickly; do slow work later
```

- `Webhook::verify(string $payload, array $headers, string $secret, int $tolerance = 300, ?int $now = null): WebhookEvent`.
  Headers can be `getallheaders()`, a PSR-7 `getHeaders()` array or `$_SERVER`; names are matched in any case, and
  a header with several values is joined with spaces. The timestamp must be within `tolerance` seconds of now.
- It throws `WebhookVerificationException` with one of these messages: `Missing webhook headers.`,
  `Invalid webhook timestamp.`, `Webhook timestamp is outside the allowed tolerance.`, `Missing webhook secret.`,
  `The webhook secret is not valid (expected whsec_ followed by base64).`, `No webhook signature matched.`,
  `The webhook body is not JSON.`
- `WebhookEvent`: `type` (`bulk.completed`, `bulk.failed`, `credits.low`, `credits.expiring`, `webhook.test`),
  `timestamp` and `data` (an array with the API's field names).
- `Webhook::sign(string $msgId, int $timestamp, string $payload, string $secret): string` returns the
  `webhook-signature` value (`v1,<base64>`), to test your endpoint locally.

## Constants

```php
<?php

use EmailValidation\BulkJobStatus;
use EmailValidation\Reason;
use EmailValidation\Status;
use EmailValidation\Vocabulary;

Vocabulary::STATUSES;                 // ['valid', 'invalid', 'catch_all', 'unknown', 'disposable']
Vocabulary::REASONS_BY_STATUS[Status::CATCH_ALL]; // ['catch_all', 'role_catch_all', 'forwarding_domain']
Vocabulary::REASON_INFO[Reason::CATCH_ALL]; // ['status' => 'catch_all', 'action' => 'send_with_caution']
Vocabulary::isReason('catch_all');    // true
Vocabulary::sandboxAddress(Reason::NO_MAILBOX); // 'no_mailbox@sandbox.email-validation.org'
in_array(BulkJobStatus::COMPLETED, Vocabulary::TERMINAL_BULK_JOB_STATUSES, true); // true
```

`Vocabulary` holds `DEFAULT_BASE_URL`, `EU_BASE_URL`, `SANDBOX_DOMAIN`, `DEFAULT_WEBHOOK_TOLERANCE` (300), `STATUSES`,
`REASONS`, `REASONS_BY_STATUS`, `REASON_INFO`, `ACTIONS`, `SMTP_PROVIDERS`, `BULK_JOB_STATUSES`,
`TERMINAL_BULK_JOB_STATUSES`, `BULK_JOB_ERROR_CODES`, `ERROR_CODES`, `AUTO_RETRY_ERROR_CODES`,
`CLIENT_ERROR_CODES`, `WEBHOOK_EVENTS`, and `isStatus()`, `isReason()`, `isTerminalBulkJobStatus()`,
`sandboxAddress()`. Named constants: `Status`, `Reason`, `Action`, `SmtpProvider`, `BulkJobStatus`, `ErrorCode`,
`WebhookEventType`. `Client::SDK_VERSION` is the SDK version (sent in the `User-Agent`).

## Bring your own HTTP client

The client talks to the network through `EmailValidation\Http\TransportInterface`: one `Request` in, one `Response`
out. The built-in `CurlTransport` never follows redirects and bounds each attempt with the client `timeout`. Pass
cURL options (a proxy, a CA bundle) to it, or replace it entirely (tests, a PSR-18 client). Options the transport
manages itself (method, URL, headers, body, ranges, redirects, timeouts, callbacks) and `CURLOPT_VERBOSE` (it would
print the API key to stderr) are refused with an `InvalidArgumentException`:

```php
<?php

use EmailValidation\Client;
use EmailValidation\Http\CurlTransport;
use EmailValidation\Http\Request;
use EmailValidation\Http\Response;
use EmailValidation\Http\TransportException;
use EmailValidation\Http\TransportInterface;

$client = new Client(
    apiKey: (string) getenv('EV_API_KEY'),
    transport: new CurlTransport([CURLOPT_PROXY => 'http://proxy.internal:3128']),
);

final class MyTransport implements TransportInterface
{
    public function send(Request $request): Response
    {
        // $request->method, $request->url, $request->headers(), $request->body(), $request->timeout
        // Do not follow redirects. Throw new TransportException($message, timeout: true|false) when no answer arrived.
        return new Response(200, ['content-type' => 'application/json'], '{"balance":0,"debt":0,"pending":0,"lots":[]}');
    }
}

$test = new Client(apiKey: 'ev_live_test', transport: new MyTransport());
```

A transport returns every answer (any status) and throws `TransportException` only when no HTTP answer arrived
(flagged as a timeout when the attempt timed out); the client applies the retry rules and error mapping. A
`Request` keeps its headers (they carry the key) and body (it carries the address) out of `var_dump()`,
`print_r()`, `var_export()` and `json_encode()`; read them with `headers()` and `body()`.

## Privacy

The API key travels only in the `Authorization` header. It never appears in a URL, an exception, or the
`var_dump()` / `print_r()` / `var_export()` / `json_encode()` output of the client or a request, and the client
cannot be serialized. The SDK writes nothing to output and has no telemetry.

## Licence

MIT (see `LICENSE`).
