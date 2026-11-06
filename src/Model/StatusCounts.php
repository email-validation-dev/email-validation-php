<?php

declare(strict_types=1);

namespace EmailValidation\Model;

use EmailValidation\Internal\Decode;

/**
 * Counts per status. In usage every count is present; in a bulk job's `counts` only the statuses seen so far are
 * (the others are null), and {@see toArray()} gives back exactly the keys received: a status this SDK version does
 * not know yet is kept too (read it with {@see get()}).
 */
final class StatusCounts implements \JsonSerializable
{
    private const KNOWN = ['valid', 'invalid', 'catch_all', 'unknown', 'disposable'];

    /** @var array<string, int> */
    private readonly array $extra;

    /** @param array<string, int> $extra counts of statuses added to the API after this SDK version */
    public function __construct(
        public readonly ?int $valid = null,
        public readonly ?int $invalid = null,
        public readonly ?int $catchAll = null,
        public readonly ?int $unknown = null,
        public readonly ?int $disposable = null,
        array $extra = [],
    ) {
        $kept = [];
        foreach ($extra as $status => $count) {
            if (is_string($status) && !in_array($status, self::KNOWN, true) && is_int($count)) {
                $kept[$status] = $count;
            }
        }
        $this->extra = $kept;
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $extra = [];
        foreach ($data as $status => $count) {
            if (is_string($status) && !in_array($status, self::KNOWN, true) && is_int($count)) {
                $extra[$status] = $count;
            }
        }

        return new self(
            Decode::nullableInt($data, 'valid'),
            Decode::nullableInt($data, 'invalid'),
            Decode::nullableInt($data, 'catch_all'),
            Decode::nullableInt($data, 'unknown'),
            Decode::nullableInt($data, 'disposable'),
            $extra,
        );
    }

    /** The count of a status by its API name (`catch_all`, or one this SDK version does not know), or null. */
    public function get(string $status): ?int
    {
        return $this->toArray()[$status] ?? null;
    }

    /** @return array<string, int> only the statuses present, the five first, then any newer ones as received */
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
        ) + $this->extra;
    }

    /** Encodes as a JSON object, `{}` when empty. */
    public function jsonSerialize(): object
    {
        return (object) $this->toArray();
    }
}
