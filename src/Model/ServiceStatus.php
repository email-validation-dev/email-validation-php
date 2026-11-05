<?php

declare(strict_types=1);

namespace EmailValidation\Model;

use EmailValidation\Internal\Decode;

/** Public service status. */
final class ServiceStatus implements \JsonSerializable
{
    public function __construct(
        /** `operational`, `degraded` or `outage`. */
        public readonly string $status,
        public readonly ServiceComponents $components,
        /** ISO 8601 UTC. */
        public readonly string $updatedAt,
    ) {
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Decode::string($data, 'status'),
            ServiceComponents::fromArray(Decode::object($data, 'components')),
            Decode::string($data, 'updated_at'),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['status' => $this->status, 'components' => $this->components->toArray(), 'updated_at' => $this->updatedAt];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
