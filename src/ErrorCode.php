<?php

declare(strict_types=1);

namespace EmailValidation;

/** API error codes (`EmailValidationException::$errorCode`) and the client's own codes. */
final class ErrorCode
{
    public const INVALID_REQUEST = 'invalid_request';
    public const UNAUTHORIZED = 'unauthorized';
    public const INSUFFICIENT_CREDITS = 'insufficient_credits';
    public const FORBIDDEN = 'forbidden';
    public const FORBIDDEN_ORIGIN = 'forbidden_origin';
    public const NOT_FOUND = 'not_found';
    public const CONFLICT = 'conflict';
    public const LENGTH_REQUIRED = 'length_required';
    public const PAYLOAD_TOO_LARGE = 'payload_too_large';
    public const RATE_LIMITED = 'rate_limited';
    public const ABUSE_THROTTLED = 'abuse_throttled';
    public const KEY_DAILY_CAP = 'key_daily_cap';
    public const INTERNAL_ERROR = 'internal_error';
    public const SERVICE_UNAVAILABLE = 'service_unavailable';
    public const UPSTREAM_CAPACITY = 'upstream_capacity';
    public const CONNECTION_ERROR = 'connection_error';
    public const TIMEOUT = 'timeout';
    public const UNEXPECTED_RESPONSE = 'unexpected_response';
    public const ABORTED = 'aborted';
}
