<?php

declare(strict_types=1);

namespace EmailValidation\Model;

use EmailValidation\Internal\Decode;

/**
 * Credit usage for a period. `items` holds {@see UsageDay} objects when `groupBy` is `day` and {@see UsageByKey}
 * objects when it is `key`; {@see days()} and {@see keys()} return them typed.
 */
final class UsageResponse implements \JsonSerializable
{
    /** @param list<UsageDay>|list<UsageByKey> $items */
    public function __construct(
        /** `YYYY-MM-DD` (UTC). */
        public readonly string $from,
        /** `YYYY-MM-DD` (UTC). */
        public readonly string $to,
        /** `day` or `key`. */
        public readonly string $groupBy,
        public readonly UsageTotals $totals,
        public readonly array $items,
    ) {
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $groupBy = Decode::string($data, 'group_by');
        $rows = Decode::listOfObjects($data, 'items');

        return new self(
            Decode::string($data, 'from'),
            Decode::string($data, 'to'),
            $groupBy,
            UsageTotals::fromArray(Decode::object($data, 'totals')),
            $groupBy === 'key' ? array_map(UsageByKey::fromArray(...), $rows) : array_map(UsageDay::fromArray(...), $rows),
        );
    }

    /** @return list<UsageDay> the items when grouped by day, else an empty list */
    public function days(): array
    {
        return array_values(array_filter($this->items, static fn (object $item): bool => $item instanceof UsageDay));
    }

    /** @return list<UsageByKey> the items when grouped by key, else an empty list */
    public function keys(): array
    {
        return array_values(array_filter($this->items, static fn (object $item): bool => $item instanceof UsageByKey));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'group_by' => $this->groupBy,
            'totals' => $this->totals->toArray(),
            'items' => array_map(static fn (UsageDay|UsageByKey $item): array => $item->toArray(), $this->items),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['from' => $this->from, 'to' => $this->to, 'group_by' => $this->groupBy, 'totals' => $this->totals, 'items' => $this->items];
    }
}
