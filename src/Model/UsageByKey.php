<?php

declare(strict_types=1);

namespace EmailValidation\Model;

use EmailValidation\Internal\Decode;

/** Usage of one API key (`group_by` `key`). */
final class UsageByKey implements \JsonSerializable
{
    public function __construct(
        public readonly string $keyId,
        /** The key's name (null for a deleted key). */
        public readonly ?string $name,
        public readonly ?string $last4,
        /** Exact, from the credit ledger. */
        public readonly int $creditsUsed,
    ) {
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Decode::string($data, 'key_id'),
            Decode::nullableString($data, 'name'),
            Decode::nullableString($data, 'last4'),
            Decode::int($data, 'credits_used'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['key_id' => $this->keyId, 'name' => $this->name, 'last4' => $this->last4, 'credits_used' => $this->creditsUsed];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
