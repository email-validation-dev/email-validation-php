<?php

declare(strict_types=1);

namespace EmailValidation;

use EmailValidation\Exception\EmailValidationException;
use EmailValidation\Http\CurlTransport;
use EmailValidation\Http\TransportInterface;
use EmailValidation\Internal\Engine;
use EmailValidation\Internal\Secret;
use EmailValidation\Model\Credits;
use EmailValidation\Model\ServiceStatus;
use EmailValidation\Model\UsageResponse;
use EmailValidation\Model\ValidationResult;

/**
 * Client for the email-validation.org REST API.
 *
 *     $client = new Client(apiKey: getenv('EV_API_KEY'));
 *     $result = $client->validate('accepted@sandbox.email-validation.org');
 *
 * Retries: answers 429 (rate limited) and 503 (temporarily unavailable) are never charged, so they are retried with
 * backoff (honouring `Retry-After`) up to `maxRetries` times, unless the requested wait is longer than
 * `maxRetryWait` seconds. Network failures and timeouts are retried only for reads (GET), because a check that reached
 * the service may already have been charged.
 *
 * Construct it once and reuse it. The API key never appears in `var_dump()`, `print_r()`, `var_export()`,
 * `json_encode()` output or in an exception.
 */
final class Client
{
    public const SDK_VERSION = '1.0.0';

    /** Bulk jobs: `create`, `get`, `start`, `cancel`, `results`, `resultsStream`, `wait`. */
    public readonly BulkJobs $bulk;

    private readonly Engine $engine;

    /**
     * @param string                     $apiKey       your API key (`ev_live_…`); keep it on the server
     * @param string|null                $baseUrl      default `https://api.email-validation.org`
     * @param float                      $timeout      seconds per attempt, connect and full answer (default 30; a check
     *                                                 may take up to 15 s on our side)
     * @param int                        $maxRetries   retries after the first attempt (default 2; negative = 0)
     * @param float                      $maxRetryWait longest single wait before a retry the client accepts, in seconds
     *                                                 (default 10; negative = 0)
     * @param TransportInterface|null    $transport    replaces the built-in cURL transport (tests, proxies, PSR-18 adapters)
     * @param (callable(float): void)|null $sleep      replaces the real wait between retries and polls (tests)
     * @param (callable(): float)|null   $clock        replaces the current Unix time, used for `Retry-After` dates (tests)
     *
     * @throws \InvalidArgumentException when the key is empty or contains whitespace, the base URL is not http(s) or
     *                                   the timeout is not positive
     */
    public function __construct(
        #[\SensitiveParameter] string $apiKey,
        ?string $baseUrl = null,
        float $timeout = 30.0,
        int $maxRetries = 2,
        float $maxRetryWait = 10.0,
        ?TransportInterface $transport = null,
        ?callable $sleep = null,
        ?callable $clock = null,
    ) {
        if (preg_match('/^\S+\z/u', $apiKey) !== 1) {
            throw new \InvalidArgumentException('apiKey is required (a non-empty key without whitespace).');
        }
        $url = rtrim($baseUrl ?? Vocabulary::DEFAULT_BASE_URL, '/');
        if (preg_match('#^https?://#', $url) !== 1) {
            throw new \InvalidArgumentException('baseUrl must be an http:// or https:// URL.');
        }
        if (!is_finite($timeout) || $timeout <= 0) {
            throw new \InvalidArgumentException('timeout must be a positive number of seconds.');
        }
        $this->engine = new Engine(
            new Secret($apiKey),
            $url,
            $timeout,
            max(0, $maxRetries),
            is_nan($maxRetryWait) ? 0.0 : max(0.0, $maxRetryWait),
            $transport ?? new CurlTransport(),
            $sleep ?? static function (float $seconds): void {
                if ($seconds > 0) {
                    usleep((int) round($seconds * 1_000_000));
                }
            },
            $clock ?? static fn (): float => microtime(true),
        );
        $this->bulk = new BulkJobs($this->engine);
    }

    /**
     * Checks one address. Costs 1 credit unless the result is `unknown` or the answer is local (malformed and
     * sandbox addresses are free).
     *
     * @param string      $email     sent as is
     * @param string|null $ipAddress the sign-up IP address, for fraud signals
     * @param int|null    $timeout   seconds the service may spend (5–15, server default 15); keep the client
     *                               `timeout` a few seconds above it
     * @param bool|null   $fresh     true checks again even when the address was checked in the last 12 months
     *
     * @throws EmailValidationException
     */
    public function validate(
        #[\SensitiveParameter] string $email,
        ?string $ipAddress = null,
        ?int $timeout = null,
        ?bool $fresh = null,
    ): ValidationResult {
        $body = ['email' => $email];
        if ($ipAddress !== null) {
            $body['ip_address'] = $ipAddress;
        }
        if ($timeout !== null) {
            $body['timeout'] = $timeout;
        }
        if ($fresh !== null) {
            $body['fresh'] = $fresh;
        }

        return $this->engine->decode(
            $this->engine->send('POST', '/v1/validate', json: $body),
            ValidationResult::fromArray(...),
        );
    }

    /**
     * Balance, debt, pending credits and credit lots (soonest-expiring first).
     *
     * @throws EmailValidationException
     */
    public function credits(): Credits
    {
        return $this->engine->decode($this->engine->send('GET', '/v1/credits'), Credits::fromArray(...));
    }

    /**
     * Credit usage by day (default) or by API key.
     *
     * @param string|null $from    `YYYY-MM-DD` (UTC)
     * @param string|null $to      `YYYY-MM-DD` (UTC)
     * @param string|null $groupBy `day` (default) or `key`
     *
     * @throws EmailValidationException
     */
    public function usage(?string $from = null, ?string $to = null, ?string $groupBy = null): UsageResponse
    {
        $response = $this->engine->send('GET', '/v1/usage', ['from' => $from, 'to' => $to, 'group_by' => $groupBy]);

        return $this->engine->decode($response, UsageResponse::fromArray(...));
    }

    /**
     * Public service status (no credits).
     *
     * @throws EmailValidationException
     */
    public function status(): ServiceStatus
    {
        return $this->engine->decode($this->engine->send('GET', '/v1/status'), ServiceStatus::fromArray(...));
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['config' => $this->engine->__debugInfo()];
    }

    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        throw new \LogicException('The client cannot be serialized.');
    }

    /** @param array<mixed> $data */
    public function __unserialize(array $data): void
    {
        throw new \LogicException('The client cannot be unserialized.');
    }
}
