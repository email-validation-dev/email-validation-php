<?php

declare(strict_types=1);

namespace EmailValidation\Http;

/**
 * The client's only view of the network: one request in, one answer (any status) out.
 *
 * Implementations must not follow redirects (the `Authorization` header must never travel to another host), must
 * honour {@see Request::$timeout} (a bound on the whole attempt, or an idle bound when {@see Request::$transfer} is
 * true; see there), and must throw {@see TransportException} when no HTTP answer arrived (flagged as a timeout when the
 * attempt timed out). Non-2xx answers are returned, not thrown. They must never resend a request by themselves, and
 * must send every request other than a GET on a fresh connection that is not reused afterwards (with cURL:
 * `CURLOPT_FRESH_CONNECT` and `CURLOPT_FORBID_REUSE`): libcurl resends a request on its own when a reused connection
 * dies before the answer, even after the service read and charged it.
 */
interface TransportInterface
{
    /** @throws TransportException when no HTTP answer arrived */
    public function send(Request $request): Response;
}
