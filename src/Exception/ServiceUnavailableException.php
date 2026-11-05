<?php

declare(strict_types=1);

namespace EmailValidation\Exception;

/** 503: temporarily unavailable, nothing charged. Retried automatically like 429. */
class ServiceUnavailableException extends EmailValidationException
{
}
