<?php

declare(strict_types=1);

namespace EmailValidation\Http;

use EmailValidation\Internal\Secret;

/**
 * One HTTP attempt, as the client hands it to a {@see TransportInterface}.
 *
 * The headers carry the API key and the body the checked address: they are kept out of `var_dump()`, `print_r()`,
 * `var_export()` and `json_encode()` output and read only through {@see headers()} and {@see body()}.
 */
final class Request implements \JsonSerializable
{
    private readonly Secret $sensitive;

    /**
     * @param string               $method  GET, POST or DELETE
     * @param string               $url     absolute URL, query string included
     * @param array<string,string> $headers header names in lower case
     * @param string|null          $body    raw body bytes, or null when the request has no body
     * @param float                $timeout  seconds: a hard bound on the whole attempt (connect, send, full answer),
     *                                       or, for a transfer, an idle bound (see $transfer)
     * @param bool                 $transfer true for a file upload or a results download: $timeout is then an idle
     *                                       timeout (connecting, or no byte sent or received for that long) with no
     *                                       total cap, so a slow but progressing 100 MB transfer is never cut; until
     *                                       an upload's answer head arrives, the idle bound is
     *                                       `timeout + min(size, 4 MiB) / 128 KiB/s` (bytes still draining from the
     *                                       send buffer show no progress)
     * @param resource|null        $sink     a writable stream for the body of a 2xx answer (a 206 when the request
     *                                       has a `range` header); the built-in transport writes such a body there
     *                                       directly and returns an empty body. A transport that ignores it returns
     *                                       the body as usual (the client copies it).
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        #[\SensitiveParameter] array $headers,
        #[\SensitiveParameter] ?string $body,
        public readonly float $timeout,
        public readonly bool $transfer = false,
        public readonly mixed $sink = null,
    ) {
        if ($sink !== null && !is_resource($sink)) {
            throw new \InvalidArgumentException('sink must be a stream resource or null.');
        }
        $lower = [];
        foreach ($headers as $name => $value) {
            $lower[strtolower($name)] = $value;
        }
        $this->sensitive = new Secret([$lower, $body]);
    }

    /** @return array<string, string> header names in lower case (includes `authorization`) */
    public function headers(): array
    {
        /** @var array{0: array<string, string>, 1: string|null} $parts */
        $parts = $this->sensitive->reveal();

        return $parts[0];
    }

    public function header(string $name): ?string
    {
        return $this->headers()[strtolower($name)] ?? null;
    }

    public function body(): ?string
    {
        /** @var array{0: array<string, string>, 1: string|null} $parts */
        $parts = $this->sensitive->reveal();

        return $parts[1];
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['method' => $this->method, 'url' => $this->url, 'headers' => '[hidden]', 'body' => '[hidden]', 'timeout' => $this->timeout, 'transfer' => $this->transfer];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }
}
