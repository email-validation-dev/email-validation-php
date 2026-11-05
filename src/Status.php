<?php

declare(strict_types=1);

namespace EmailValidation;

/** Values of `ValidationResult::$status`. Compare with `===`; an unknown future value is kept as a string. */
final class Status
{
    public const VALID = 'valid';
    public const INVALID = 'invalid';
    public const CATCH_ALL = 'catch_all';
    public const UNKNOWN = 'unknown';
    public const DISPOSABLE = 'disposable';
}
