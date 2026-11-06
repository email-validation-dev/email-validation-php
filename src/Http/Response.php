<?php

declare(strict_types=1);

namespace EmailValidation\Http;

/**
 * An HTTP answer, as a {@see TransportInterface} returns it. The body is either a string or a readable stream
 * resource (the built-in transport spools bodies to `php://temp`, so large result files never sit in memory).
 */
final class Response
{
    /** @var array<string, string> */
    private readonly array $headers;

    /** @var string|resource */
    private $body;

    /**
     * @param array<string, string|list<string>> $headers header names in any case; several values are joined with ", "
     * @param string|resource                    $body
     */
    public function __construct(public readonly int $status, array $headers = [], #[\SensitiveParameter] mixed $body = '')
    {
        if (!is_string($body) && !is_resource($body)) {
            throw new \InvalidArgumentException('Response body must be a string or a stream resource.');
        }
        $lower = [];
        foreach ($headers as $name => $value) {
            $lower[strtolower((string) $name)] = is_array($value) ? implode(', ', $value) : $value;
        }
        $this->headers = $lower;
        $this->body = $body;
    }

    /** @return array<string, string> header names in lower case */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** The whole body as a string (reads the stream to its end and closes it). */
    public function body(): string
    {
        if (is_string($this->body)) {
            return $this->body;
        }
        if (!is_resource($this->body)) {
            return '';
        }
        rewind($this->body);
        $text = stream_get_contents($this->body);
        fclose($this->body);
        $this->body = $text === false ? '' : $text;

        return $this->body;
    }

    /**
     * The body as a readable stream positioned at its start. The caller owns it and should `fclose()` it.
     *
     * @return resource
     */
    public function stream()
    {
        if (is_resource($this->body)) {
            rewind($this->body);

            return $this->body;
        }
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Could not open a temporary stream.');
        }
        fwrite($stream, is_string($this->body) ? $this->body : '');
        rewind($stream);

        return $stream;
    }

    /** Closes the body stream, if any (used when a body is discarded). */
    public function close(): void
    {
        if (is_resource($this->body)) {
            fclose($this->body);
        }
        $this->body = '';
    }
}
