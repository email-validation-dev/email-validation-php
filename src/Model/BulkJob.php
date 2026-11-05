<?php

declare(strict_types=1);

namespace EmailValidation\Model;

use EmailValidation\Internal\Decode;
use EmailValidation\Vocabulary;

/**
 * A bulk job. `status` and `errorCode` are plain strings (see {@see \EmailValidation\BulkJobStatus} and
 * {@see Vocabulary::BULK_JOB_ERROR_CODES}); a value added to the API later is kept as is.
 */
final class BulkJob implements \JsonSerializable
{
    public function __construct(
        /** `job_` followed by 22 letters and digits. */
        public readonly string $id,
        public readonly string $status,
        public readonly ?string $filename,
        /** Null while `analyzing` (like the four counts below). */
        public readonly ?int $totalRows,
        public readonly ?int $uniqueEmails,
        public readonly ?int $duplicates,
        public readonly ?int $syntaxInvalid,
        public readonly ?int $localAnswered,
        public readonly int $processed,
        public readonly int $creditsReserved,
        public readonly int $creditsCharged,
        /** Only the statuses seen so far. */
        public readonly StatusCounts $counts,
        /** Why a job `failed`, or why it waits in `ready` (its start was refused). */
        public readonly ?string $errorCode,
        public readonly ?string $webhookEndpointId,
        public readonly string $createdAt,
        public readonly ?string $startedAt,
        public readonly ?string $finishedAt,
        public readonly string $expiresAt,
    ) {
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Decode::string($data, 'id'),
            Decode::string($data, 'status'),
            Decode::nullableString($data, 'filename'),
            Decode::nullableInt($data, 'total_rows'),
            Decode::nullableInt($data, 'unique_emails'),
            Decode::nullableInt($data, 'duplicates'),
            Decode::nullableInt($data, 'syntax_invalid'),
            Decode::nullableInt($data, 'local_answered'),
            Decode::int($data, 'processed'),
            Decode::int($data, 'credits_reserved'),
            Decode::int($data, 'credits_charged'),
            StatusCounts::fromArray(Decode::object($data, 'counts')),
            Decode::nullableString($data, 'error_code'),
            Decode::nullableString($data, 'webhook_endpoint_id'),
            Decode::string($data, 'created_at'),
            Decode::nullableString($data, 'started_at'),
            Decode::nullableString($data, 'finished_at'),
            Decode::string($data, 'expires_at'),
        );
    }

    /** Completed, failed, cancelled or expired: the job never changes again. */
    public function isTerminal(): bool
    {
        return Vocabulary::isTerminalBulkJobStatus($this->status);
    }

    /** `ready` with an `errorCode`: its start was refused (e.g. `insufficient_credits`) and it never moves on by itself. */
    public function isStuckReady(): bool
    {
        return $this->status === 'ready' && $this->errorCode !== null && $this->errorCode !== '';
    }

    /** @return array<string, mixed> the API's field names and order */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'filename' => $this->filename,
            'total_rows' => $this->totalRows,
            'unique_emails' => $this->uniqueEmails,
            'duplicates' => $this->duplicates,
            'syntax_invalid' => $this->syntaxInvalid,
            'local_answered' => $this->localAnswered,
            'processed' => $this->processed,
            'credits_reserved' => $this->creditsReserved,
            'credits_charged' => $this->creditsCharged,
            'counts' => $this->counts->toArray(),
            'error_code' => $this->errorCode,
            'webhook_endpoint_id' => $this->webhookEndpointId,
            'created_at' => $this->createdAt,
            'started_at' => $this->startedAt,
            'finished_at' => $this->finishedAt,
            'expires_at' => $this->expiresAt,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return array_merge($this->toArray(), ['counts' => $this->counts]); // `counts` encodes as `{}` when empty
    }
}
