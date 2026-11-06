<?php

declare(strict_types=1);

namespace EmailValidation;

use EmailValidation\Exception\WebhookVerificationException;
use EmailValidation\Model\WebhookEvent;

/**
 * Webhook signature verification (Standard Webhooks scheme).
 *
 * Signed content: `{webhook-id}.{webhook-timestamp}.{raw body}`, HMAC-SHA256 with the endpoint secret (the part after
 * `whsec_`, base64-decoded); header `webhook-signature: v1,<base64> [v1,<base64> …]`.
 */
final class Webhook
{
    private const BASE64 = '#^[A-Za-z0-9+/]+={0,2}\z#';

    /**
     * Verifies a delivery and returns the event.
     *
     * Pass the raw request body (before any JSON parsing) and the request headers: `getallheaders()`, a PSR-7
     * `getHeaders()` array, `$_SERVER` or any array; names are matched in any case and a header with several
     * values is joined with spaces. Deduplicate on the `webhook-id` header (retries reuse it) and answer 2xx quickly.
     *
     * @param string                              $payload   the raw body bytes
     * @param array<string, string|list<string>>  $headers
     * @param string                              $secret    the endpoint's signing secret (`whsec_…`)
     * @param int                                 $tolerance seconds the timestamp may differ from now (default 300)
     * @param int|null                            $now       current Unix time (tests)
     *
     * @throws WebhookVerificationException
     */
    public static function verify(
        string $payload,
        array $headers,
        #[\SensitiveParameter] string $secret,
        int $tolerance = Vocabulary::DEFAULT_WEBHOOK_TOLERANCE,
        ?int $now = null,
    ): WebhookEvent {
        $msgId = self::header($headers, 'webhook-id');
        $timestamp = self::header($headers, 'webhook-timestamp');
        $signatures = self::header($headers, 'webhook-signature');
        if ($msgId === null || $msgId === '' || $timestamp === null || $timestamp === '' || $signatures === null || $signatures === '') {
            throw new WebhookVerificationException('Missing webhook headers.');
        }
        if (preg_match('/^[0-9]{1,12}\z/', $timestamp) !== 1) {
            throw new WebhookVerificationException('Invalid webhook timestamp.');
        }
        if (abs(($now ?? time()) - (int) $timestamp) > $tolerance) {
            throw new WebhookVerificationException('Webhook timestamp is outside the allowed tolerance.');
        }

        $expected = hash_hmac('sha256', $msgId . '.' . $timestamp . '.' . $payload, self::key($secret), true);
        // entries are separated by spaces; a stack that joined repeated header lines with ", " leaves a trailing comma
        foreach (preg_split('/ +/', $signatures, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $entry) {
            if (str_ends_with($entry, ',')) {
                $entry = substr($entry, 0, -1);
            }
            $comma = strpos($entry, ',');
            if ($comma === false || substr($entry, 0, $comma) !== 'v1') {
                continue;
            }
            $given = self::base64(substr($entry, $comma + 1));
            if ($given !== null && hash_equals($expected, $given)) {
                return self::event($payload);
            }
        }
        throw new WebhookVerificationException('No webhook signature matched.');
    }

    /**
     * The `webhook-signature` value for a payload (`v1,<base64>`), to test your endpoint locally.
     *
     * @throws WebhookVerificationException when the secret is empty or not valid
     */
    public static function sign(string $msgId, int $timestamp, string $payload, #[\SensitiveParameter] string $secret): string
    {
        $digest = hash_hmac('sha256', $msgId . '.' . $timestamp . '.' . $payload, self::key($secret), true);

        return 'v1,' . base64_encode($digest);
    }

    private static function key(#[\SensitiveParameter] string $secret): string
    {
        if ($secret === '') {
            throw new WebhookVerificationException('Missing webhook secret.');
        }
        $key = self::base64(str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret);
        if ($key === null || $key === '') {
            throw new WebhookVerificationException('The webhook secret is not valid (expected whsec_ followed by base64).');
        }

        return $key;
    }

    /** Strict standard base64 (RFC 4648 §4, padded): null when the text is not valid base64. */
    private static function base64(string $text): ?string
    {
        if ($text === '' || strlen($text) % 4 !== 0 || preg_match(self::BASE64, $text) !== 1) {
            return null;
        }
        $bytes = base64_decode($text, true);

        return $bytes === false ? null : $bytes;
    }

    /** Any JSON object is the event, whatever its fields (forward compatible); anything else is "not JSON". */
    private static function event(string $payload): WebhookEvent
    {
        try {
            $object = json_decode($payload, false, 512, JSON_THROW_ON_ERROR); // objects stay objects: `{}` is not `[]`
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new WebhookVerificationException('The webhook body is not JSON.');
        }
        if (!$object instanceof \stdClass || !is_array($data)) {
            throw new WebhookVerificationException('The webhook body is not JSON.');
        }

        return WebhookEvent::fromArray($data);
    }

    /**
     * The header's value: every value of the name in any case (several list items, names that differ only in case)
     * joined with one space; the `$_SERVER` key (`HTTP_WEBHOOK_ID`) when the name itself is absent. A value of
     * another type reads as absent.
     *
     * @param array<mixed> $headers
     */
    private static function header(array $headers, string $name): ?string
    {
        $server = 'http_' . str_replace('-', '_', $name);
        $values = [$name => [], $server => []];
        foreach ($headers as $key => $value) {
            $key = strtolower((string) $key);
            if ($key !== $name && $key !== $server) {
                continue;
            }
            if (is_array($value)) {
                $values[$key][] = implode(' ', array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $value));
            } elseif (is_scalar($value)) {
                $values[$key][] = (string) $value;
            } else {
                return null;
            }
        }
        $found = $values[$name] !== [] ? $values[$name] : $values[$server];

        return $found === [] ? null : implode(' ', $found);
    }
}
