<?php

declare(strict_types=1);

namespace EmailValidation\Exception;

/**
 * A webhook delivery failed its header, timestamp, secret or signature checks (or its signed body is not JSON).
 * Not part of the {@see EmailValidationException} family: answer such a request with 400.
 */
final class WebhookVerificationException extends \RuntimeException
{
}
