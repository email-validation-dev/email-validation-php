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
     * @param float                $timeout seconds this attempt may take (connect and full response)
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        #[\SensitiveParameter] array $headers,
        #[\SensitiveParameter] ?string $body,
        public readonly float $timeout,
    ) {
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
        return ['method' => $this->method, 'url' => $this->url, 'headers' => '[hidden]', 'body' => '[hidden]', 'timeout' => $this->timeout];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }
}
