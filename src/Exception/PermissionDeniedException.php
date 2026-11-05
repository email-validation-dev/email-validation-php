<?php

declare(strict_types=1);

namespace EmailValidation\Exception;

/** 403: missing scope, disallowed origin, suspended account or billing hold. */
class PermissionDeniedException extends EmailValidationException
{
}
