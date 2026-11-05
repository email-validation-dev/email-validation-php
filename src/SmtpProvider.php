<?php

declare(strict_types=1);

namespace EmailValidation;

/** Values of `ValidationResult::$smtpProvider`. An unknown future value is kept as a string. */
final class SmtpProvider
{
    public const GOOGLE = 'google';
    public const MICROSOFT = 'microsoft';
    public const YAHOO = 'yahoo';
    public const APPLE = 'apple';
    public const ZOHO = 'zoho';
    public const PROTON = 'proton';
    public const FASTMAIL = 'fastmail';
    public const GMX = 'gmx';
    public const YANDEX = 'yandex';
    public const MAILRU = 'mailru';
    public const AMAZON = 'amazon';
    public const MIMECAST = 'mimecast';
    public const PROOFPOINT = 'proofpoint';
    public const BARRACUDA = 'barracuda';
    public const OTHER = 'other';
}
