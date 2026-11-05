<?php

declare(strict_types=1);

namespace EmailValidation\Model;

use EmailValidation\Internal\Decode;

/**
 * Result of one check. `status`, `reason`, `action` and `smtpProvider` are plain strings (see {@see \EmailValidation\Status},
 * {@see \EmailValidation\Reason}, {@see \EmailValidation\Action}, {@see \EmailValidation\SmtpProvider}); a value added to
 * the API after this SDK was released is kept as is.
 */
final class ValidationResult implements \JsonSerializable
{
    public function __construct(
        /** The normalised address. */
        public readonly string $email,
        /** `valid`, `invalid`, `catch_all`, `unknown` or `disposable`. */
        public readonly string $status,
        public readonly string $reason,
        /** `send`, `send_with_caution`, `remove` or `retry_later`. */
        public readonly string $action,
        public readonly ?string $account,
        public readonly ?string $domain,
        public readonly ?string $didYouMean,
        public readonly ?bool $freeEmail,
        public readonly ?bool $roleAccount,
        public readonly ?bool $disposable,
        public readonly ?bool $catchAllDomain,
        public readonly ?bool $mxFound,
        public readonly ?string $mxRecord,
        public readonly ?string $smtpProvider,
        public readonly ?int $domainAgeDays,
        /** ISO 8601 UTC, when this result was produced. A reused result can come from a check up to 12 months old. */
        public readonly string $processedAt,
        /** 1 when the result cost a credit; `unknown`, malformed and sandbox answers are free. */
        public readonly int $creditsUsed,
        public readonly string $requestId,
    ) {
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Decode::string($data, 'email'),
            Decode::string($data, 'status'),
            Decode::string($data, 'reason'),
            Decode::string($data, 'action'),
            Decode::nullableString($data, 'account'),
            Decode::nullableString($data, 'domain'),
            Decode::nullableString($data, 'did_you_mean'),
            Decode::nullableBool($data, 'free_email'),
            Decode::nullableBool($data, 'role_account'),
            Decode::nullableBool($data, 'disposable'),
            Decode::nullableBool($data, 'catch_all_domain'),
            Decode::nullableBool($data, 'mx_found'),
            Decode::nullableString($data, 'mx_record'),
            Decode::nullableString($data, 'smtp_provider'),
            Decode::nullableInt($data, 'domain_age_days'),
            Decode::string($data, 'processed_at'),
            Decode::int($data, 'credits_used'),
            Decode::string($data, 'request_id'),
        );
    }

    /** @return array<string, mixed> the API's field names and order */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'status' => $this->status,
            'reason' => $this->reason,
            'action' => $this->action,
            'account' => $this->account,
            'domain' => $this->domain,
            'did_you_mean' => $this->didYouMean,
            'free_email' => $this->freeEmail,
            'role_account' => $this->roleAccount,
            'disposable' => $this->disposable,
            'catch_all_domain' => $this->catchAllDomain,
            'mx_found' => $this->mxFound,
            'mx_record' => $this->mxRecord,
            'smtp_provider' => $this->smtpProvider,
            'domain_age_days' => $this->domainAgeDays,
            'processed_at' => $this->processedAt,
            'credits_used' => $this->creditsUsed,
            'request_id' => $this->requestId,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
