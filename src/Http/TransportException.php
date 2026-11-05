<?php

declare(strict_types=1);

namespace EmailValidation\Http;

/** A transport got no HTTP answer: a network failure, or a timeout when {@see isTimeout()} is true. */
final class TransportException extends \RuntimeException
{
    public function __construct(string $message, private readonly bool $timeout = false, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function isTimeout(): bool
    {
        return $this->timeout;
    }
}
