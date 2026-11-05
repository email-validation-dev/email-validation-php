<?php

declare(strict_types=1);

namespace EmailValidation\Model;

use EmailValidation\Internal\Decode;

/** State of each part of the service: `operational`, `degraded` or `outage`. */
final class ServiceComponents implements \JsonSerializable
{
    public function __construct(
        public readonly string $website,
        public readonly string $api,
        public readonly string $bulk,
        public readonly string $signIn,
        public readonly string $payments,
        public readonly string $support,
    ) {
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            Decode::string($data, 'website'),
            Decode::string($data, 'api'),
            Decode::string($data, 'bulk'),
            Decode::string($data, 'sign_in'),
            Decode::string($data, 'payments'),
            Decode::string($data, 'support'),
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'website' => $this->website,
            'api' => $this->api,
            'bulk' => $this->bulk,
            'sign_in' => $this->signIn,
            'payments' => $this->payments,
            'support' => $this->support,
        ];
    }

    /** @return array<string, string> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
