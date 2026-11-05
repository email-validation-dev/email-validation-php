# Changelog

All notable changes to the PHP SDK. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
the SDK follows [Semantic Versioning](https://semver.org/): a new vocabulary value, option or operation is a minor
release; a removed or renamed public symbol is a major one.

## [1.0.0] - 2026-11-04

### Added

- `Client` for the email-validation.org REST API v1: `validate()`, `credits()`, `usage()`, `status()`.
- Bulk jobs on `$client->bulk`: `create()` (from a list or a CSV / plain-text file or stream), `get()`, `start()`,
  `cancel()`, `results()`, `resultsStream()` (spooled to `php://temp`) and `wait()` (with `onProgress`).
- Typed readonly models (`ValidationResult`, `Credits`, `UsageResponse`, `BulkJob`, `ServiceStatus`,
  `WebhookEvent` …) with `fromArray()` / `toArray()` and `json_encode()` support; unknown fields are ignored and
  unknown enum values are kept as strings.
- Exceptions under `EmailValidation\Exception\` extending `EmailValidationException`, with `status`, `errorCode`,
  `requestId` and `retryAfter`.
- Automatic retries of 429 and 503 answers (honouring `Retry-After`, capped by `maxRetryWait`) and of failed reads;
  per-attempt `timeout`.
- `Webhook::verify()` and `Webhook::sign()` (Standard Webhooks scheme, constant-time comparison).
- `Vocabulary` lists and predicates, named constants (`Status`, `Reason`, `Action`, `SmtpProvider`,
  `BulkJobStatus`, `ErrorCode`, `WebhookEventType`), `Vocabulary::sandboxAddress()`.
- `TransportInterface` with the built-in `CurlTransport` (redirects never followed).
