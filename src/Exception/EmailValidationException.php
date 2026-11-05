<?php

declare(strict_types=1);

namespace EmailValidation\Exception;

/**
 * Base of every error the client throws. Branch on the class or on {@see $errorCode}, never on the message.
 *
 * Used directly only when a 2xx answer is not JSON (`unexpected_response`).
 */
class EmailValidationException extends \RuntimeException
{
    /**
     * @param string      $message    the API's message (for people; it may change) or the SDK's fixed text
     * @param int         $status     HTTP status, or 0 when no answer arrived (or the call was refused before sending)
     * @param string      $errorCode  an API error code (`insufficient_credits`, `rate_limited` …) or a client code
     *                                (`connection_error`, `timeout`, `unexpected_response`, `aborted`)
     * @param string|null $requestId  our id for the request (quote it to support)
     * @param int|null    $retryAfter whole seconds from `Retry-After`
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly string $errorCode,
        public readonly ?string $requestId = null,
        public readonly ?int $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /** Kind, status, code, request id and message; never the key, the request body, the address or a stack trace. */
    public function __toString(): string
    {
        return sprintf(
            '%s(status=%d, code=%s, request_id=%s): %s',
            static::class,
            $this->status,
            $this->errorCode,
            $this->requestId ?? 'null',
            $this->getMessage(),
        );
    }
}
