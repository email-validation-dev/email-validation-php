<?php

declare(strict_types=1);

namespace EmailValidation\Http;

/**
 * The client's only view of the network: one request in, one answer (any status) out.
 *
 * Implementations must not follow redirects (the `Authorization` header must never travel to another host), must
 * honour {@see Request::$timeout} for the whole attempt, and must throw {@see TransportException} when no HTTP answer
 * arrived (flagged as a timeout when the attempt timed out). Non-2xx answers are returned, not thrown.
 */
interface TransportInterface
{
    /** @throws TransportException when no HTTP answer arrived */
    public function send(Request $request): Response;
}
