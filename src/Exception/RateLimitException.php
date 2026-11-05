<?php

declare(strict_types=1);

namespace EmailValidation\Exception;

/** 429: slow down. Retried automatically when the wait is short (except key_daily_cap). */
class RateLimitException extends EmailValidationException
{
}
