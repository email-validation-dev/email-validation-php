<?php

declare(strict_types=1);

namespace EmailValidation;

/**
 * The public vocabulary of the email-validation.org API: closed lists the API answers with. New values arrive as
 * minor SDK releases; until then a client keeps unknown values as
 * raw strings (decoding never fails on them).
 */
final class Vocabulary
{
    public const DEFAULT_BASE_URL = 'https://api.email-validation.org';
    /** EU data residency: validated and stored in the EU. Pass it as `baseUrl` to use the EU endpoint. */
    public const EU_BASE_URL = 'https://api-eu.email-validation.org';

    /** Free test addresses: `<reason>@sandbox.email-validation.org` answers that reason and costs nothing. */
    public const SANDBOX_DOMAIN = 'sandbox.email-validation.org';

    /** Seconds a webhook timestamp may differ from now. */
    public const DEFAULT_WEBHOOK_TOLERANCE = 300;

    /** @var list<string> */
    public const STATUSES = [
        'valid',
        'invalid',
        'catch_all',
        'unknown',
        'disposable',
    ];

    /** @var array<string, list<string>> */
    public const REASONS_BY_STATUS = [
        'valid' => [
            'accepted',
            'alias',
            'normalized',
            'low_engagement',
            'high_engagement',
            'trusted_catch_all',
            'ai_agent',
            'role_account',
            'abuse_linked',
            'suppressed',
            'trap_pattern',
            'spamtrap',
            'complainer',
        ],
        'invalid' => ['no_mailbox', 'no_inbound_mail', 'invalid_syntax', 'likely_typo', 'no_dns', 'mailbox_full', 'unroutable'],
        'catch_all' => ['catch_all', 'role_catch_all', 'forwarding_domain'],
        'unknown' => ['server_unavailable', 'temporary_error', 'greylisted', 'anti_spam_block', 'timeout', 'internal_error'],
        'disposable' => ['disposable'],
    ];

    /** @var list<string> every reason, grouped by status */
    public const REASONS = [
        'accepted',
        'alias',
        'normalized',
        'low_engagement',
        'high_engagement',
        'trusted_catch_all',
        'ai_agent',
        'role_account',
        'abuse_linked',
        'suppressed',
        'trap_pattern',
        'spamtrap',
        'complainer',
        'no_mailbox',
        'no_inbound_mail',
        'invalid_syntax',
        'likely_typo',
        'no_dns',
        'mailbox_full',
        'unroutable',
        'catch_all',
        'role_catch_all',
        'forwarding_domain',
        'disposable',
        'server_unavailable',
        'temporary_error',
        'greylisted',
        'anti_spam_block',
        'timeout',
        'internal_error',
    ];

    /** @var array<string, array{status: string, action: string}> the status and fixed action of each reason */
    public const REASON_INFO = [
        'accepted' => ['status' => 'valid', 'action' => 'send'],
        'alias' => ['status' => 'valid', 'action' => 'send'],
        'normalized' => ['status' => 'valid', 'action' => 'send'],
        'low_engagement' => ['status' => 'valid', 'action' => 'send_with_caution'],
        'high_engagement' => ['status' => 'valid', 'action' => 'send'],
        'trusted_catch_all' => ['status' => 'valid', 'action' => 'send'],
        'ai_agent' => ['status' => 'valid', 'action' => 'send_with_caution'],
        'role_account' => ['status' => 'valid', 'action' => 'send_with_caution'],
        'abuse_linked' => ['status' => 'valid', 'action' => 'remove'],
        'suppressed' => ['status' => 'valid', 'action' => 'remove'],
        'trap_pattern' => ['status' => 'valid', 'action' => 'remove'],
        'spamtrap' => ['status' => 'valid', 'action' => 'remove'],
        'complainer' => ['status' => 'valid', 'action' => 'remove'],
        'no_mailbox' => ['status' => 'invalid', 'action' => 'remove'],
        'no_inbound_mail' => ['status' => 'invalid', 'action' => 'remove'],
        'invalid_syntax' => ['status' => 'invalid', 'action' => 'remove'],
        'likely_typo' => ['status' => 'invalid', 'action' => 'remove'],
        'no_dns' => ['status' => 'invalid', 'action' => 'remove'],
        'mailbox_full' => ['status' => 'invalid', 'action' => 'remove'],
        'unroutable' => ['status' => 'invalid', 'action' => 'remove'],
        'catch_all' => ['status' => 'catch_all', 'action' => 'send_with_caution'],
        'role_catch_all' => ['status' => 'catch_all', 'action' => 'send_with_caution'],
        'forwarding_domain' => ['status' => 'catch_all', 'action' => 'send_with_caution'],
        'disposable' => ['status' => 'disposable', 'action' => 'remove'],
        'server_unavailable' => ['status' => 'unknown', 'action' => 'retry_later'],
        'temporary_error' => ['status' => 'unknown', 'action' => 'retry_later'],
        'greylisted' => ['status' => 'unknown', 'action' => 'retry_later'],
        'anti_spam_block' => ['status' => 'unknown', 'action' => 'retry_later'],
        'timeout' => ['status' => 'unknown', 'action' => 'retry_later'],
        'internal_error' => ['status' => 'unknown', 'action' => 'retry_later'],
    ];

    /** @var list<string> */
    public const ACTIONS = [
        'send',
        'send_with_caution',
        'remove',
        'retry_later',
    ];

    /** @var list<string> */
    public const SMTP_PROVIDERS = [
        'google',
        'microsoft',
        'yahoo',
        'apple',
        'zoho',
        'proton',
        'fastmail',
        'gmx',
        'yandex',
        'mailru',
        'amazon',
        'mimecast',
        'proofpoint',
        'barracuda',
        'other',
    ];

    /** @var list<string> */
    public const BULK_JOB_STATUSES = [
        'analyzing',
        'ready',
        'queued',
        'running',
        'cancelling',
        'finalizing',
        'completed',
        'failed',
        'cancelled',
        'expired',
    ];

    /** @var list<string> a job in one of these states never changes again */
    public const TERMINAL_BULK_JOB_STATUSES = [
        'completed',
        'failed',
        'cancelled',
        'expired',
    ];

    /** @var array<string, string> bulk job `error_code` => the job status it comes with (`failed` or `ready`) */
    public const BULK_JOB_ERROR_CODES = [
        'invalid_csv' => 'failed',
        'too_many_rows' => 'failed',
        'empty_file' => 'failed',
        'email_column_not_found' => 'failed',
        'upload_missing' => 'failed',
        'pipeline_mismatch' => 'failed',
        'missing_results' => 'failed',
        'internal_error' => 'failed',
        'insufficient_credits' => 'ready',
        'upstream_capacity' => 'ready',
        'account_suspended' => 'ready',
        'billing_hold' => 'ready',
        'invalid_key' => 'ready',
        'forbidden_scope' => 'ready',
        'key_daily_cap' => 'ready',
        'upload_expired' => 'ready',
    ];

    /** @var list<string> API error codes (`EmailValidationException::$errorCode`), in catalogue order */
    public const ERROR_CODES = [
        'invalid_request',
        'unauthorized',
        'insufficient_credits',
        'forbidden',
        'forbidden_origin',
        'not_found',
        'conflict',
        'length_required',
        'payload_too_large',
        'rate_limited',
        'abuse_throttled',
        'key_daily_cap',
        'internal_error',
        'service_unavailable',
        'upstream_capacity',
    ];

    /** @var list<string> error codes the client retries by itself (with backoff, honouring `Retry-After`) */
    public const AUTO_RETRY_ERROR_CODES = [
        'rate_limited',
        'abuse_throttled',
        'service_unavailable',
        'upstream_capacity',
    ];

    /** @var list<string> codes the client itself uses when there is no API error code */
    public const CLIENT_ERROR_CODES = [
        'connection_error',
        'timeout',
        'unexpected_response',
        'aborted',
    ];

    /** @var list<string> every `type` a webhook delivery can carry */
    public const WEBHOOK_EVENTS = [
        'bulk.completed',
        'bulk.failed',
        'credits.low',
        'credits.expiring',
        'webhook.test',
    ];

    public static function isStatus(mixed $value): bool
    {
        return is_string($value) && in_array($value, self::STATUSES, true);
    }

    public static function isReason(mixed $value): bool
    {
        return is_string($value) && in_array($value, self::REASONS, true);
    }

    public static function isTerminalBulkJobStatus(mixed $value): bool
    {
        return is_string($value) && in_array($value, self::TERMINAL_BULK_JOB_STATUSES, true);
    }

    /**
     * `<reason>@sandbox.email-validation.org`: a free test address that answers that reason.
     *
     * @throws \InvalidArgumentException for an unknown reason
     */
    public static function sandboxAddress(string $reason): string
    {
        if (!self::isReason($reason)) {
            throw new \InvalidArgumentException(sprintf('Unknown reason: "%s".', $reason));
        }

        return $reason . '@' . self::SANDBOX_DOMAIN;
    }
}
