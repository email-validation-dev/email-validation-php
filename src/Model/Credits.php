<?php

declare(strict_types=1);

namespace EmailValidation\Model;

use EmailValidation\Internal\Decode;

/** Balance, debt, pending credits and credit lots. */
final class Credits implements \JsonSerializable
{
    /** @param list<CreditLot> $lots soonest-expiring first (the order credits are spent in) */
    public function __construct(
        public readonly int $balance,
        public readonly int $debt,
        public readonly int $pending,
        public readonly array $lots,
    ) {
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Decode::int($data, 'balance'),
            Decode::int($data, 'debt'),
            Decode::int($data, 'pending'),
            array_map(CreditLot::fromArray(...), Decode::listOfObjects($data, 'lots')),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'balance' => $this->balance,
            'debt' => $this->debt,
            'pending' => $this->pending,
            'lots' => array_map(static fn (CreditLot $lot): array => $lot->toArray(), $this->lots),
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
