# Changelog

All notable changes to the PHP SDK. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
the SDK follows [Semantic Versioning](https://semver.org/): a new vocabulary value, option or operation is a minor
release; a removed or renamed public symbol is a major one.

## [1.1.0] - 2026-11-06

### Fixed

- No double charge when a kept-alive connection dies: the built-in `CurlTransport` sends every request other than a
  GET (`validate()`, a bulk upload, `start()`, `cancel()`) on a fresh connection that is closed afterwards, so cURL
  can no longer resend a request the service already read ("Connection died, retrying a fresh connect"). Only the
  client's retry rules repeat a request.
- Checked addresses and uploaded lists no longer show in exception stack traces: `#[\SensitiveParameter]` on
  `bulk->create()`, the request engine and `Response` (PHP 8.2+).
- Transfers (a file upload, `results()`, `resultsStream()`) use `timeout` as an idle timeout (no byte sent or
  received for that long) instead of a cap on the whole transfer, so a slow but progressing large file is no longer
  cut and retried from the start. JSON calls keep the whole-attempt bound. Until the answer starts, an upload may
  wait `timeout + min(size, 4 MiB) ÷ 128 KiB/s` without visible progress (bytes still draining from the system's
  send buffer), so an upload is not cut at its end after the service received it.
- A `range` request (`results(range:)`, a `download()` resume) answered with the whole file is refused as soon as
  the answer starts, without reading the file first.
- Uploads over 1 MiB are streamed from the request body instead of copied into cURL, so a 100 MB list is held once.
- A bulk job's `counts` keeps statuses this version does not know (`toArray()` gives back every key received).
- `filter: []`, `all` inside a list and the retired names (`spamtrap`, `abuse`, `do_not_mail`, `risky`) throw
  `\InvalidArgumentException` before anything is sent (an empty list used to send `filter=`).
- `Webhook::verify()` returns any signed JSON object as the event (a missing or non-string `type` or `timestamp`
  reads as `''`; both stay `string`); a body that is not a JSON object throws `The webhook body is not JSON.` (the
  extra message "not a webhook event" is gone). Signatures joined with `, ` verify too, and header names that differ
  only in case are joined (not only the first).
- Request headers, the key included, never go to a proxy: the transport sets `CURLOPT_HEADEROPT` itself, and its own
  values of `CURLOPT_HEADEROPT`, `CURLOPT_FRESH_CONNECT`, `CURLOPT_FORBID_REUSE`, the low-speed and progress options
  win over the constructor's wherever it sets them (they are still accepted there, as in 1.0.0).

### Added

- `Client::withTimeout(float $timeout)`: a copy of the client with another per-attempt timeout (the per-call
  timeout), sharing its key, base URL, retry settings and transport.
- `bulk->download(string $jobId, string|SplFileInfo $path, bool $resume = true, …): int` writes the result CSV
  straight into a file and resumes a partial one (`Range: bytes=<size>-`).
- `bulk->create(file: new SplFileInfo('list.csv'))` uploads a file by path.
- `StatusCounts::get(string $status)`; `Request::$transfer` and `Request::$sink` for custom transports.

## [1.0.0] - 2026-11-04

### Added

- `Client` for the email-validation.org REST API v1: `validate()`, `credits()`, `usage()`, `status()`.
- Bulk jobs on `$client->bulk`: `create()` (from a list or a CSV / plain-text file or stream), `get()`, `start()`,
  `cancel()`, `results()`, `resultsStream()` (spooled to `php://temp`) and `wait()` (with `onProgress`).
- Typed readonly models (`ValidationResult`, `Credits`, `UsageResponse`, `BulkJob`, `ServiceStatus`,
  `WebhookEvent` …) with `fromArray()` / `toArray()` and `json_encode()` support; unknown fields are ignored and
  unknown enum values are kept as strings.
- Exceptions under `EmailValidation\Exception\` extending `EmailValidationException`, with `status`, `errorCode`,
  `requestId` and `retryAfter`.
- Automatic retries of 429 and 503 answers (honouring `Retry-After`, capped by `maxRetryWait`) and of failed reads;
  per-attempt `timeout`.
- `Webhook::verify()` and `Webhook::sign()` (Standard Webhooks scheme, constant-time comparison).
- `Vocabulary` lists and predicates, named constants (`Status`, `Reason`, `Action`, `SmtpProvider`,
  `BulkJobStatus`, `ErrorCode`, `WebhookEventType`), `Vocabulary::sandboxAddress()`.
- `TransportInterface` with the built-in `CurlTransport` (redirects never followed).
