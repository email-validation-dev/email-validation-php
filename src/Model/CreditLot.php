<?php

declare(strict_types=1);

namespace EmailValidation\Model;

use EmailValidation\Internal\Decode;

/** Credits bought together that expire together. */
final class CreditLot implements \JsonSerializable
{
    public function __construct(
        public readonly int $remaining,
        /** ISO 8601 UTC. */
        public readonly string $expiresAt,
    ) {
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(Decode::int($data, 'remaining'), Decode::string($data, 'expires_at'));
    }

    /** @return array{remaining: int, expires_at: string} */
    public function toArray(): array
    {
        return ['remaining' => $this->remaining, 'expires_at' => $this->expiresAt];
    }

    /** @return array{remaining: int, expires_at: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
