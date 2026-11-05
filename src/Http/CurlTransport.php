<?php

declare(strict_types=1);

namespace EmailValidation\Http;

/**
 * The built-in transport, on ext-curl. Redirects are never followed; each attempt is bounded by the request's
 * timeout; bodies are spooled to `php://temp` (memory up to 2 MB, then a temporary file).
 *
 * Extra cURL options (a proxy, a CA bundle …) can be passed to the constructor. The options the SDK relies on
 * (method, URL, headers, body, ranges, redirects, timeouts, callbacks, output handling) are refused there with an
 * `\InvalidArgumentException` instead of being silently ignored, and so is `CURLOPT_VERBOSE`, which would print the
 * `Authorization` header (the API key) to stderr.
 */
final class CurlTransport implements TransportInterface
{
    private const SPOOL = 'php://temp/maxmemory:2097152';

    private ?\CurlHandle $handle = null;

    /** cURL options the transport sets itself, or that would change the request, its answer or leak the key. */
    private const RESERVED = [
        CURLOPT_URL, CURLOPT_CUSTOMREQUEST, CURLOPT_HTTPHEADER, CURLOPT_FOLLOWLOCATION, CURLOPT_TIMEOUT,
        CURLOPT_TIMEOUT_MS, CURLOPT_CONNECTTIMEOUT, CURLOPT_CONNECTTIMEOUT_MS, CURLOPT_NOSIGNAL, CURLOPT_HEADERFUNCTION,
        CURLOPT_WRITEFUNCTION, CURLOPT_HTTPGET, CURLOPT_POST, CURLOPT_POSTFIELDS, CURLOPT_PUT, CURLOPT_UPLOAD,
        CURLOPT_NOBODY, CURLOPT_HEADER, CURLOPT_RANGE, CURLOPT_RESUME_FROM, CURLOPT_FILE, CURLOPT_INFILE,
        CURLOPT_READFUNCTION, CURLOPT_RETURNTRANSFER, CURLOPT_WRITEHEADER, CURLOPT_VERBOSE,
    ];

    /**
     * @param array<int, mixed> $curlOptions extra `CURLOPT_*` options, e.g. `[CURLOPT_PROXY => 'http://proxy:3128']`
     *
     * @throws \InvalidArgumentException when an option the transport relies on (or `CURLOPT_VERBOSE`) is passed
     */
    public function __construct(private readonly array $curlOptions = [])
    {
        foreach (array_keys($curlOptions) as $option) {
            if (in_array($option, self::RESERVED, true)) {
                throw new \InvalidArgumentException(sprintf('cURL option %d is managed by the SDK and cannot be passed.', $option));
            }
        }
    }

    public function send(Request $request): Response
    {
        if ($this->handle === null) {
            $handle = curl_init();
            if ($handle === false) {
                throw new TransportException('The API could not be reached.');
            }
            $this->handle = $handle;
        }
        $handle = $this->handle;
        curl_reset($handle);

        $headerLines = ['Expect:'];
        foreach ($request->headers() as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        $body = $request->body();
        if ($body === null && $request->method !== 'GET') {
            $headerLines[] = 'Content-Length: 0';
        }

        $sink = fopen(self::SPOOL, 'w+b');
        if ($sink === false) {
            throw new TransportException('Could not open a temporary stream for the answer.');
        }
        /** @var array<string, string> $headers */
        $headers = [];
        $timeoutMs = max(1, (int) ceil($request->timeout * 1000));

        $options = [
            CURLOPT_URL => $request->url,
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
                $trimmed = trim($line);
                if (str_starts_with($trimmed, 'HTTP/')) {
                    $headers = []; // a new answer (after 100 Continue or a proxy CONNECT)
                } elseif (($pos = strpos($trimmed, ':')) !== false) {
                    $name = strtolower(trim(substr($trimmed, 0, $pos)));
                    $value = trim(substr($trimmed, $pos + 1));
                    $headers[$name] = isset($headers[$name]) ? $headers[$name] . ', ' . $value : $value;
                }

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($ch, string $data) use ($sink): int {
                $written = fwrite($sink, $data);

                return $written === false ? 0 : $written;
            },
        ];
        if ($request->method === 'GET') {
            $options[CURLOPT_HTTPGET] = true;
        }
        if ($body !== null) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($handle, $options + $this->curlOptions);
        $ok = curl_exec($handle);
        if ($ok === false) {
            $errno = curl_errno($handle);
            $error = curl_error($handle);
            fclose($sink);
            $timedOut = $errno === CURLE_OPERATION_TIMEDOUT;
            throw new TransportException(
                $timedOut ? 'The request timed out.' : 'The API could not be reached.',
                $timedOut,
                new \RuntimeException(sprintf('cURL error %d: %s', $errno, $error)),
            );
        }
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        rewind($sink);

        return new Response($status, $headers, $sink);
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['curlOptions' => count($this->curlOptions) . ' extra option(s)'];
    }
}
