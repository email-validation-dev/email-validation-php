<?php

declare(strict_types=1);

namespace EmailValidation\Model;

use EmailValidation\Internal\Decode;

/** Totals of a usage period. */
final class UsageTotals implements \JsonSerializable
{
    public function __construct(public readonly int $creditsUsed, public readonly StatusCounts $byStatus)
    {
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(Decode::int($data, 'credits_used'), StatusCounts::fromArray(Decode::object($data, 'by_status')));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['credits_used' => $this->creditsUsed, 'by_status' => $this->byStatus->toArray()];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['credits_used' => $this->creditsUsed, 'by_status' => $this->byStatus];
    }
}
