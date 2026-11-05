<?php

declare(strict_types=1);

namespace EmailValidation;

/** Values of `ValidationResult::$reason` (see `Vocabulary::REASONS_BY_STATUS`). An unknown future value is kept as a string. */
final class Reason
{
    public const ACCEPTED = 'accepted';
    public const ALIAS = 'alias';
    public const NORMALIZED = 'normalized';
    public const LOW_ENGAGEMENT = 'low_engagement';
    public const HIGH_ENGAGEMENT = 'high_engagement';
    public const TRUSTED_CATCH_ALL = 'trusted_catch_all';
    public const AI_AGENT = 'ai_agent';
    public const ROLE_ACCOUNT = 'role_account';
    public const ABUSE_LINKED = 'abuse_linked';
    public const SUPPRESSED = 'suppressed';
    public const TRAP_PATTERN = 'trap_pattern';
    public const SPAMTRAP = 'spamtrap';
    public const COMPLAINER = 'complainer';
    public const NO_MAILBOX = 'no_mailbox';
    public const NO_INBOUND_MAIL = 'no_inbound_mail';
    public const INVALID_SYNTAX = 'invalid_syntax';
    public const LIKELY_TYPO = 'likely_typo';
    public const NO_DNS = 'no_dns';
    public const MAILBOX_FULL = 'mailbox_full';
    public const UNROUTABLE = 'unroutable';
    public const CATCH_ALL = 'catch_all';
    public const ROLE_CATCH_ALL = 'role_catch_all';
    public const FORWARDING_DOMAIN = 'forwarding_domain';
    public const DISPOSABLE = 'disposable';
    public const SERVER_UNAVAILABLE = 'server_unavailable';
    public const TEMPORARY_ERROR = 'temporary_error';
    public const GREYLISTED = 'greylisted';
    public const ANTI_SPAM_BLOCK = 'anti_spam_block';
    public const TIMEOUT = 'timeout';
    public const INTERNAL_ERROR = 'internal_error';
}
