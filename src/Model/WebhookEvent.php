<?php

declare(strict_types=1);

namespace EmailValidation\Model;

use EmailValidation\Internal\Decode;

/**
 * A verified webhook delivery. `type` is one of {@see \EmailValidation\WebhookEventType}; `data` is the event's
 * payload as an array with the API's field names:
 *
 * - `bulk.completed`, `bulk.failed`: `job_id`, `status`, `total_rows`, `unique_emails`, `processed`,
 *   `credits_charged`, `counts`, `error_code`
 * - `credits.low`: `balance`, `threshold`
 * - `credits.expiring`: `credits`, `expires_at`
 * - `webhook.test`: `endpoint_id` (sent only by "Send test" in the dashboard)
 */
final class WebhookEvent implements \JsonSerializable
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public readonly string $type,
        /** ISO 8601 UTC. */
        public readonly string $timestamp,
        public readonly array $data,
    ) {
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $payload */
        $payload = Decode::object($data, 'data');

        return new self(Decode::string($data, 'type'), Decode::string($data, 'timestamp'), $payload);
    }

    /** @return array{type: string, timestamp: string, data: array<string, mixed>} */
    public function toArray(): array
    {
        return ['type' => $this->type, 'timestamp' => $this->timestamp, 'data' => $this->data];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['type' => $this->type, 'timestamp' => $this->timestamp, 'data' => (object) $this->data];
    }
}
