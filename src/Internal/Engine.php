<?php

declare(strict_types=1);

namespace EmailValidation\Internal;

use EmailValidation\Client;
use EmailValidation\Exception\AuthenticationException;
use EmailValidation\Exception\ConflictException;
use EmailValidation\Exception\ConnectionException;
use EmailValidation\Exception\EmailValidationException;
use EmailValidation\Exception\InsufficientCreditsException;
use EmailValidation\Exception\InvalidRequestException;
use EmailValidation\Exception\NotFoundException;
use EmailValidation\Exception\PermissionDeniedException;
use EmailValidation\Exception\RateLimitException;
use EmailValidation\Exception\ServerException;
use EmailValidation\Exception\ServiceUnavailableException;
use EmailValidation\Http\Request;
use EmailValidation\Http\Response;
use EmailValidation\Http\TransportException;
use EmailValidation\Http\TransportInterface;
use EmailValidation\Vocabulary;

/**
 * Request building, the retry loop and error mapping shared by {@see Client} and {@see \EmailValidation\BulkJobs}.
 *
 * @internal
 */
final class Engine
{
    private const RETRY_STATUSES = [429, 503];
    /** 429 codes where waiting a few seconds cannot help. */
    private const NO_RETRY_CODES = ['key_daily_cap'];
    private const JOB_ID = '/^job_[0-9A-Za-z]{22}\z/';
    private const KINDS = [
        400 => InvalidRequestException::class,
        401 => AuthenticationException::class,
        402 => InsufficientCreditsException::class,
        403 => PermissionDeniedException::class,
        404 => NotFoundException::class,
        409 => ConflictException::class,
        411 => InvalidRequestException::class,
        413 => InvalidRequestException::class,
        429 => RateLimitException::class,
        503 => ServiceUnavailableException::class,
    ];

    private readonly \Closure $sleep;
    private readonly \Closure $clock;

    /**
     * @param callable(float): void $sleep waits that many seconds
     * @param callable(): float     $clock current Unix time in seconds (for `Retry-After` dates)
     */
    public function __construct(
        private readonly Secret $apiKey,
        public readonly string $baseUrl,
        public readonly float $timeout,
        public readonly int $maxRetries,
        public readonly float $maxRetryWait,
        private readonly TransportInterface $transport,
        callable $sleep,
        callable $clock,
    ) {
        $this->sleep = \Closure::fromCallable($sleep);
        $this->clock = \Closure::fromCallable($clock);
    }

    public function sleep(float $seconds): void
    {
        ($this->sleep)($seconds);
    }

    /**
     * Sends a request with the retry rules; returns the 2xx answer or throws a typed error.
     *
     * @param array<string, string|int|bool|null> $query   unset (null) parameters are left out
     * @param array<string, mixed>|null           $json    JSON body (compact; unset options already left out)
     * @param array<string, string>               $headers extra headers
     */
    public function send(
        string $method,
        string $path,
        array $query = [],
        ?array $json = null,
        ?string $body = null,
        ?string $contentType = null,
        string $accept = 'application/json',
        array $headers = [],
    ): Response {
        $request = $this->buildRequest($method, $path, $query, $json, $body, $contentType, $accept, $headers);
        for ($attempt = 0; ; ++$attempt) {
            try {
                $response = $this->transport->send($request);
            } catch (TransportException $e) {
                if ($method === 'GET' && $attempt < $this->maxRetries) {
                    $this->sleep($this->backoff($attempt));
                    continue;
                }
                throw $e->isTimeout()
                    ? new ConnectionException('The request timed out.', 0, 'timeout', null, null, $e)
                    : new ConnectionException('The API could not be reached.', 0, 'connection_error', null, null, $e);
            }
            if ($response->status >= 200 && $response->status < 300) {
                return $response;
            }
            $error = $this->errorFrom($response);
            if (
                in_array($response->status, self::RETRY_STATUSES, true)
                && !in_array($error->errorCode, self::NO_RETRY_CODES, true)
                && $attempt < $this->maxRetries
            ) {
                $wait = $error->retryAfter ?? $this->backoff($attempt);
                if ($wait <= $this->maxRetryWait) {
                    $this->sleep((float) $wait);
                    continue;
                }
            }
            throw $error;
        }
    }

    /**
     * Decodes a 2xx JSON answer into a model.
     *
     * @template T
     *
     * @param callable(array<mixed>): T $factory
     *
     * @return T
     */
    public function decode(Response $response, callable $factory): mixed
    {
        $requestId = $response->header('x-request-id');
        try {
            $data = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new EmailValidationException(
                'The API answered with something other than JSON.',
                $response->status,
                'unexpected_response',
                $requestId,
                null,
                $e,
            );
        }
        try {
            if (!is_array($data)) {
                throw new \UnexpectedValueException('The answer is not a JSON object.');
            }

            return $factory($data);
        } catch (\UnexpectedValueException $e) {
            throw new EmailValidationException(
                'The API answered with JSON in an unexpected shape.',
                $response->status,
                'unexpected_response',
                $requestId,
                null,
                $e,
            );
        }
    }

    public static function checkJobId(string $jobId): string
    {
        if (preg_match(self::JOB_ID, $jobId) !== 1) {
            throw new InvalidRequestException(
                'Not a bulk job id (expected job_ followed by 22 letters and digits).',
                0,
                'invalid_request',
            );
        }

        return $jobId;
    }

    /** Whole seconds from a `Retry-After` value (digits, or an HTTP date), else null. */
    public function parseRetryAfter(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if (preg_match('/^[0-9]+\z/', $value) === 1) {
            return (int) $value;
        }
        $when = self::parseHttpDate($value);
        if ($when === null) {
            return null;
        }

        return max(0, (int) ceil($when - ($this->clock)()));
    }

    /** Exponential backoff with jitter: 0.25–0.5 s, 0.5–1 s, 1–2 s … capped at 4–8 s. */
    public function backoff(int $attempt): float
    {
        $base = min(8.0, 0.5 * 2 ** min($attempt, 16));

        return $base / 2 + (mt_rand() / mt_getrandmax()) * $base / 2;
    }

    /**
     * @param array<string, string|int|bool|null> $query
     * @param array<string, mixed>|null           $json
     * @param array<string, string>               $extra
     */
    private function buildRequest(
        string $method,
        string $path,
        array $query,
        ?array $json,
        ?string $body,
        ?string $contentType,
        string $accept,
        array $extra,
    ): Request {
        $params = [];
        foreach ($query as $name => $value) {
            if ($value === null) {
                continue;
            }
            $params[$name] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }
        $url = $this->baseUrl . $path;
        if ($params !== []) {
            $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }
        $headers = [
            'authorization' => 'Bearer ' . self::string($this->apiKey->reveal()),
            'accept' => $accept,
            'user-agent' => 'email-validation-php/' . Client::SDK_VERSION . ' (PHP ' . PHP_VERSION . ')',
        ];
        if ($json !== null) {
            try {
                $body = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new \InvalidArgumentException('The request cannot be encoded as JSON (strings must be UTF-8).', 0, $e);
            }
            $headers['content-type'] = 'application/json';
        } elseif ($contentType !== null) {
            $headers['content-type'] = $contentType;
        }
        foreach ($extra as $name => $value) {
            $headers[strtolower($name)] = $value;
        }

        return new Request($method, $url, $headers, $body, $this->timeout);
    }

    private function errorFrom(Response $response): EmailValidationException
    {
        $status = $response->status;
        $requestId = $response->header('x-request-id');
        $retryAfter = $this->parseRetryAfter($response->header('retry-after'));
        $text = $response->body();
        $kind = self::KINDS[$status] ?? ($status >= 400 && $status < 500 ? InvalidRequestException::class : ServerException::class);

        try {
            $envelope = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $envelope = null;
        }
        $error = is_array($envelope) && is_array($envelope['error'] ?? null) ? $envelope['error'] : null;
        $code = $error['code'] ?? null;
        if (is_string($code) && in_array($code, Vocabulary::ERROR_CODES, true)) {
            $message = is_string($error['message'] ?? null) ? $error['message'] : $code;
            $rid = is_string($error['request_id'] ?? null) ? $error['request_id'] : $requestId;

            return new $kind($message, $status, $code, $rid, $retryAfter);
        }

        return new $kind(sprintf('Unexpected HTTP %d from the API.', $status), $status, 'unexpected_response', $requestId, $retryAfter);
    }

    /** IMF-fixdate, RFC 850 or asctime (RFC 9110 §5.6.7), always GMT. Returns a Unix time or null. */
    private static function parseHttpDate(string $value): ?float
    {
        $value = (string) preg_replace('/\s+/', ' ', $value);
        $utc = new \DateTimeZone('UTC');
        foreach (['!D, d M Y H:i:s \G\M\T', '!l, d-M-y H:i:s \G\M\T', '!D M j H:i:s Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value, $utc);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return (float) $date->getTimestamp();
            }
        }

        return null;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            'apiKey' => '[redacted]',
            'baseUrl' => $this->baseUrl,
            'timeout' => $this->timeout,
            'maxRetries' => $this->maxRetries,
            'maxRetryWait' => $this->maxRetryWait,
        ];
    }
}
