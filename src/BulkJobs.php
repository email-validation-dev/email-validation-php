<?php

declare(strict_types=1);

namespace EmailValidation;

use EmailValidation\Exception\ConnectionException;
use EmailValidation\Exception\EmailValidationException;
use EmailValidation\Exception\InvalidRequestException;
use EmailValidation\Http\Response;
use EmailValidation\Internal\Engine;
use EmailValidation\Model\BulkJob;

/** Bulk jobs, as `$client->bulk`. Needs a key with the `bulk` scope. */
final class BulkJobs
{
    private const CONTENT_TYPES = ['text/csv', 'text/plain'];

    /** @internal created by {@see Client} */
    public function __construct(private readonly Engine $engine)
    {
    }

    /**
     * Creates a job from a list of addresses (sent as JSON, up to 100,000) or from a file (a CSV, or one address per
     * line, up to 100 MB). Pass exactly one of `emails` and `file`.
     *
     * @param list<string>|null    $emails            the addresses
     * @param string|resource|null $file              the file's contents, or a readable stream (read to its end
     *                                                and buffered: the API needs a known length and retries resend it)
     * @param string               $contentType       `text/csv` (default) or `text/plain`, for `file`
     * @param int|string|null      $column            the address column: 1–512, or a header name
     * @param bool|null            $hasHeader         whether the first row is a header
     * @param bool|null            $start             false creates the job in `ready` without starting it (server
     *                                                default true); call {@see start()} later
     * @param string|null          $webhookEndpointId a webhook endpoint to notify when the job ends
     *
     * @throws \InvalidArgumentException when both or neither of `emails` and `file` are given, `emails` is not a
     *                                   list of strings, `file` is not a string or stream, or `contentType` is not allowed
     * @throws EmailValidationException
     */
    public function create(
        ?array $emails = null,
        mixed $file = null,
        string $contentType = 'text/csv',
        int|string|null $column = null,
        ?bool $hasHeader = null,
        ?bool $start = null,
        ?string $webhookEndpointId = null,
    ): BulkJob {
        if (($emails === null) === ($file === null)) {
            throw new \InvalidArgumentException('Pass exactly one of emails or file.');
        }
        if (!in_array($contentType, self::CONTENT_TYPES, true)) {
            throw new \InvalidArgumentException('contentType must be "text/csv" or "text/plain".');
        }
        $query = [
            'column' => $column,
            'has_header' => $hasHeader,
            'start' => $start,
            'webhook_endpoint_id' => $webhookEndpointId,
        ];
        if ($emails !== null) {
            if (!array_is_list($emails)) {
                throw new \InvalidArgumentException('emails must be a list of strings.');
            }
            foreach ($emails as $email) {
                if (!is_string($email)) {
                    throw new \InvalidArgumentException('emails must be a list of strings.');
                }
            }
            $response = $this->engine->send('POST', '/v1/bulk', $query, json: ['emails' => $emails]);
        } else {
            $response = $this->engine->send('POST', '/v1/bulk', $query, body: self::fileBody($file), contentType: $contentType);
        }

        return $this->engine->decode($response, BulkJob::fromArray(...));
    }

    /**
     * @throws InvalidRequestException when the id is not a bulk job id (nothing is sent)
     * @throws EmailValidationException
     */
    public function get(string $jobId): BulkJob
    {
        $response = $this->engine->send('GET', '/v1/bulk/' . Engine::checkJobId($jobId));

        return $this->engine->decode($response, BulkJob::fromArray(...));
    }

    /**
     * Starts a job in `ready`; credits are held now.
     *
     * @throws EmailValidationException
     */
    public function start(string $jobId): BulkJob
    {
        $response = $this->engine->send('POST', '/v1/bulk/' . Engine::checkJobId($jobId) . '/start');

        return $this->engine->decode($response, BulkJob::fromArray(...));
    }

    /**
     * Cancels a job; unused credits are returned.
     *
     * @throws EmailValidationException
     */
    public function cancel(string $jobId): BulkJob
    {
        $response = $this->engine->send('DELETE', '/v1/bulk/' . Engine::checkJobId($jobId));

        return $this->engine->decode($response, BulkJob::fromArray(...));
    }

    /**
     * The result CSV (UTF-8 bytes, unchanged). For large files prefer {@see resultsStream()}.
     *
     * @param string|list<string>|null $filter         `all` (default), one status, or a list of statuses
     * @param string|null              $columns        `original` (default) or `email_only`
     * @param bool|null                $escapeFormulas server default true: cells starting with = + - @ are prefixed with '
     * @param string|null              $range          one part of the file, e.g. `bytes=0-1048575`, to resume a
     *                                                 download; needs filter `all` (or one status with
     *                                                 escapeFormulas false) and columns `original`
     *
     * @throws InvalidRequestException when a range is asked for a shape that cannot be served in parts
     * @throws EmailValidationException
     */
    public function results(
        string $jobId,
        string|array|null $filter = null,
        ?string $columns = null,
        ?bool $escapeFormulas = null,
        ?string $range = null,
    ): string {
        return $this->download($jobId, $filter, $columns, $escapeFormulas, $range)->body();
    }

    /**
     * The result CSV as a readable stream positioned at its start (spooled to `php://temp`: memory up to 2 MB, then
     * a temporary file). Copy it with `stream_copy_to_stream()` and `fclose()` it. Same arguments as {@see results()}.
     *
     * @param string|list<string>|null $filter
     *
     * @return resource
     *
     * @throws EmailValidationException
     */
    public function resultsStream(
        string $jobId,
        string|array|null $filter = null,
        ?string $columns = null,
        ?bool $escapeFormulas = null,
        ?string $range = null,
    ) {
        return $this->download($jobId, $filter, $columns, $escapeFormulas, $range)->stream();
    }

    /**
     * Polls a job and returns it once it is completed, failed, cancelled or expired, or once it is `ready` with an
     * `errorCode` (its start was refused, e.g. `insufficient_credits`: buy credits, then call {@see start()}).
     * Check `$job->status` before downloading results. A job created with `start: false` waits in `ready` until you
     * call {@see start()}, so call `start()` before `wait()`.
     *
     * @param float                         $interval   seconds between polls (default 5, at least 1)
     * @param float|null                    $timeout    give up after this many seconds (default: never)
     * @param (callable(BulkJob): void)|null $onProgress called with each polled job
     *
     * @throws ConnectionException when the deadline passes (`timeout`)
     * @throws EmailValidationException
     */
    public function wait(string $jobId, float $interval = 5.0, ?float $timeout = null, ?callable $onProgress = null): BulkJob
    {
        Engine::checkJobId($jobId);
        $interval = is_nan($interval) ? 5.0 : max(1.0, $interval); // NAN would otherwise poll without pausing
        $deadline = $timeout === null ? null : self::monotonic() + $timeout;
        while (true) {
            $job = $this->get($jobId);
            if ($onProgress !== null) {
                $onProgress($job);
            }
            if ($job->isTerminal() || $job->isStuckReady()) {
                return $job;
            }
            if ($deadline !== null && self::monotonic() + $interval > $deadline) {
                throw new ConnectionException(sprintf('Bulk job %s is still %s.', $job->id, $job->status), 0, 'timeout');
            }
            $this->engine->sleep($interval);
        }
    }

    /** @param string|list<string>|null $filter */
    private function download(string $jobId, string|array|null $filter, ?string $columns, ?bool $escapeFormulas, ?string $range): Response
    {
        $path = '/v1/bulk/' . Engine::checkJobId($jobId) . '/results';
        $query = ['filter' => self::filter($filter), 'columns' => $columns, 'escape_formulas' => $escapeFormulas];
        $ranged = $range !== null && $range !== '';
        $response = $this->engine->send('GET', $path, $query, accept: 'text/csv', headers: $ranged ? ['range' => $range] : []);
        if ($ranged && $response->status !== 206) {
            $response->close();
            throw new InvalidRequestException(
                'This download cannot be fetched in parts: use filter "all" (or one status with escapeFormulas false) and columns "original" with a range.',
                $response->status,
                'invalid_request',
                $response->header('x-request-id'),
            );
        }

        return $response;
    }

    /** @param string|list<string>|null $filter */
    private static function filter(string|array|null $filter): ?string
    {
        if ($filter === null) {
            return null;
        }
        $parts = is_string($filter) ? [$filter] : $filter;
        foreach ($parts as $part) {
            if (!is_string($part) || ($part !== 'all' && !Vocabulary::isStatus($part))) {
                throw new \InvalidArgumentException('filter must be "all", a status, or a list of statuses.');
            }
        }

        return implode(',', $parts);
    }

    private static function fileBody(mixed $file): string
    {
        if (is_string($file)) {
            return $file;
        }
        if (is_resource($file) && get_resource_type($file) === 'stream') {
            $contents = stream_get_contents($file);
            if ($contents === false) {
                throw new \InvalidArgumentException('file could not be read.');
            }

            return $contents;
        }
        throw new \InvalidArgumentException('file must be a string or a readable stream.');
    }

    private static function monotonic(): float
    {
        return hrtime(true) / 1e9;
    }
}
