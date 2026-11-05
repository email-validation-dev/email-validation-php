<?php

declare(strict_types=1);

namespace EmailValidation\Model;

use EmailValidation\Internal\Decode;

/**
 * Counts per status. In usage every count is present; in a bulk job's `counts` only the statuses seen so far are
 * (the others are null), and {@see toArray()} gives back exactly the keys received.
 */
final class StatusCounts implements \JsonSerializable
{
    public function __construct(
        public readonly ?int $valid = null,
        public readonly ?int $invalid = null,
        public readonly ?int $catchAll = null,
        public readonly ?int $unknown = null,
        public readonly ?int $disposable = null,
    ) {
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Decode::nullableInt($data, 'valid'),
            Decode::nullableInt($data, 'invalid'),
            Decode::nullableInt($data, 'catch_all'),
            Decode::nullableInt($data, 'unknown'),
            Decode::nullableInt($data, 'disposable'),
        );
    }

    /** @return array<string, int> only the statuses present */
    public function toArray(): array
    {
        return array_filter(
            [
                'valid' => $this->valid,
                'invalid' => $this->invalid,
                'catch_all' => $this->catchAll,
                'unknown' => $this->unknown,
                'disposable' => $this->disposable,
            ],
            static fn (?int $count): bool => $count !== null,
        );
    }

    /** Encodes as a JSON object, `{}` when empty. */
    public function jsonSerialize(): object
    {
        return (object) $this->toArray();
    }
}
