<?php

declare(strict_types=1);

namespace EmailValidation;

/** Values of `ValidationResult::$action`: what to do with the address. */
final class Action
{
    public const SEND = 'send';
    public const SEND_WITH_CAUTION = 'send_with_caution';
    public const REMOVE = 'remove';
    public const RETRY_LATER = 'retry_later';
}
