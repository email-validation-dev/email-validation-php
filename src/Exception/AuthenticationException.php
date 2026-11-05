<?php

declare(strict_types=1);

namespace EmailValidation\Exception;

/** 401: missing, malformed, revoked or rolled key. */
class AuthenticationException extends EmailValidationException
{
}
