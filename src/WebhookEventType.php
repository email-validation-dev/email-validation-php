<?php

declare(strict_types=1);

namespace EmailValidation;

/** Values of `WebhookEvent::$type`. */
final class WebhookEventType
{
    public const BULK_COMPLETED = 'bulk.completed';
    public const BULK_FAILED = 'bulk.failed';
    public const CREDITS_LOW = 'credits.low';
    public const CREDITS_EXPIRING = 'credits.expiring';
    public const WEBHOOK_TEST = 'webhook.test';
}
