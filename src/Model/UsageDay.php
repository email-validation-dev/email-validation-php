<?php

declare(strict_types=1);

namespace EmailValidation\Model;

use EmailValidation\Internal\Decode;

/** Usage of one day (`group_by` `day`). */
final class UsageDay implements \JsonSerializable
{
    public function __construct(
        /** `YYYY-MM-DD` (UTC). */
        public readonly string $date,
        public readonly int $creditsUsed,
        /** Approximate. */
        public readonly StatusCounts $byStatus,
    ) {
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Decode::string($data, 'date'),
            Decode::int($data, 'credits_used'),
            StatusCounts::fromArray(Decode::object($data, 'by_status')),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['date' => $this->date, 'credits_used' => $this->creditsUsed, 'by_status' => $this->byStatus->toArray()];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['date' => $this->date, 'credits_used' => $this->creditsUsed, 'by_status' => $this->byStatus];
    }
}
