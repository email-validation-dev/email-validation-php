<?php

declare(strict_types=1);

namespace EmailValidation\Http;

/**
 * The built-in transport, on ext-curl. Redirects are never followed and request headers never go to a proxy (only
 * to the API). JSON calls are bounded by the request's timeout as a whole; transfers (file uploads, results
 * downloads) by an idle timeout instead (no byte for that long), so a slow but progressing 100 MB transfer is never
 * cut. An upload's two waits that libcurl cannot see as progress (bytes draining from the kernel's send buffer, then
 * the wait for the answer after the last byte) get a drain allowance, `timeout + min(size, 4 MiB) ÷ 128 KiB/s`.
 * Answers are spooled to `php://temp` (memory up to 2 MB, then a temporary file), or written straight to the request's
 * sink. Uploads over 1 MiB are streamed from the request body (sent with `Content-Length`, never copied). A range
 * request answered with a 2xx other than 206 is returned at once with an empty body: the refused body (possibly the
 * whole file) is never read.
 *
 * Every request other than a GET goes out on a fresh connection that is closed afterwards: libcurl would otherwise
 * resend a request by itself when a reused keep-alive connection dies before the answer, even after the service
 * read (and charged) it. Only the client's retry rules ever repeat a request.
 *
 * Extra cURL options (a proxy, a CA bundle …) can be passed to the constructor. The options the SDK relies on
 * (method, URL, headers, body, ranges, redirects, timeouts, callbacks, output handling) are refused there with an
 * `\InvalidArgumentException` instead of being silently ignored, and so is `CURLOPT_VERBOSE`, which would print the
 * `Authorization` header (the API key) to stderr. Where the transport sets an option on a request (header routing,
 * connection reuse for every request other than a GET, the idle timers of a transfer), its value wins over yours.
 */
final class CurlTransport implements TransportInterface
{
    private const SPOOL = 'php://temp/maxmemory:2097152';
    /** Bodies above this size are streamed by a read callback instead of copied into cURL (COPYPOSTFIELDS). */
    private const STREAM_UPLOAD_ABOVE = 1_048_576;
    /** The drain allowance: bytes per second, and the most bytes counted. */
    private const UPLOAD_RATE = 131_072;
    private const DRAIN_CAP = 4_194_304;

    private ?\CurlHandle $handle = null;

    /**
     * cURL options the transport sets itself, or that would change the request, its answer or leak the key: refused.
     * The list is 1.0.0's (code that built a transport on 1.0.0 still does). The options 1.1.0 also sets
     * (`CURLOPT_HEADEROPT`, `CURLOPT_FRESH_CONNECT`, `CURLOPT_FORBID_REUSE`, the low-speed and progress options,
     * `CURLOPT_INFILESIZE`) are accepted, and the transport's own value wins on every request where it sets one.
     */
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

        $spool = fopen(self::SPOOL, 'w+b');
        if ($spool === false) {
            throw new TransportException('Could not open a temporary stream for the answer.');
        }
        /** @var array<string, string> $headers */
        $headers = [];
        $timeoutMs = max(1, (int) ceil($request->timeout * 1000));
        $sink = $request->sink;
        $ranged = $request->header('range') !== null;
        $target = null;
        $refused = false;
        $size = $body === null ? 0 : strlen($body);
        $headSeen = false;

        $options = [
            CURLOPT_URL => $request->url,
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_HEADEROPT => CURLHEADER_SEPARATE, // our headers (the key) never go to a proxy
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_HEADERFUNCTION => static function (\CurlHandle $ch, string $line) use (&$headers, &$headSeen, $size): int {
                $trimmed = trim($line);
                if (str_starts_with($trimmed, 'HTTP/')) {
                    $headers = []; // a new answer (after 100 Continue or a proxy CONNECT)
                    $headSeen = false;
                } elseif ($trimmed === '') {
                    // the end of a head; a proxy's CONNECT answer comes before any upload byte, the API's after the last
                    $headSeen = curl_getinfo($ch, CURLINFO_SIZE_UPLOAD_T) >= $size;
                } elseif (($pos = strpos($trimmed, ':')) !== false) {
                    $name = strtolower(trim(substr($trimmed, 0, $pos)));
                    $value = trim(substr($trimmed, $pos + 1));
                    $headers[$name] = isset($headers[$name]) ? $headers[$name] . ', ' . $value : $value;
                }

                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function (\CurlHandle $ch, string $data) use ($spool, $sink, $ranged, &$target, &$refused): int {
                if ($target === null) { // the first body bytes: the final answer's status is known
                    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                    if ($ranged && $status >= 200 && $status < 300 && $status !== 206) {
                        $refused = true; // the whole file instead of the part: stop here, unread

                        return 0;
                    }
                    $target = $sink !== null && ($ranged ? $status === 206 : ($status >= 200 && $status < 300)) ? $sink : $spool;
                }
                $written = fwrite($target, $data);

                return $written === false ? 0 : $written;
            },
        ];
        $idle = false;
        if ($request->transfer) {
            // idle timeout, no total cap: abort once no byte moved for `timeout` (the progress callback runs at least
            // about once a second); cURL's own low-speed check stays as a backstop. libcurl counts an upload byte when
            // the kernel takes it, so until the answer's head arrives an upload gets the drain allowance instead
            $last = [hrtime(true), -1, -1];
            $limit = $request->timeout * 1e9;
            $drain = self::drainAllowance($request->timeout, $size);
            $drainLimit = $drain * 1e9;
            $options[CURLOPT_NOPROGRESS] = false;
            $options[CURLOPT_XFERINFOFUNCTION] = static function ($ch, int $dlTotal, int $dlNow, int $ulTotal, int $ulNow) use (&$last, &$idle, &$headSeen, $limit, $drainLimit): int {
                $now = hrtime(true);
                if ($dlNow !== $last[1] || $ulNow !== $last[2]) {
                    $last = [$now, $dlNow, $ulNow];

                    return 0;
                }
                $idle = $now - $last[0] > ($headSeen ? $limit : $drainLimit);

                return $idle ? 1 : 0;
            };
            $options[CURLOPT_LOW_SPEED_LIMIT] = 1;
            $options[CURLOPT_LOW_SPEED_TIME] = max(1, (int) ceil($drain));
        } else {
            $options[CURLOPT_TIMEOUT_MS] = $timeoutMs;
        }
        if ($request->method === 'GET') {
            $options[CURLOPT_HTTPGET] = true;
        } else {
            // a non-GET never rides a reused connection, so libcurl has no dead connection to "retry" it on
            $options[CURLOPT_FRESH_CONNECT] = true;
            $options[CURLOPT_FORBID_REUSE] = true;
        }
        if ($body !== null) {
            $options += self::bodyOptions($body);
        }

        curl_setopt_array($handle, $options + $this->curlOptions);
        $ok = curl_exec($handle);
        if ($ok === false && $refused) {
            // the head of a refused range answer: returned without its body (the client raises the refusal)
            return new Response(curl_getinfo($handle, CURLINFO_RESPONSE_CODE), $headers, $spool);
        }
        if ($ok === false) {
            $errno = curl_errno($handle);
            $error = curl_error($handle);
            fclose($spool);
            $timedOut = $errno === CURLE_OPERATION_TIMEDOUT || ($idle && $errno === CURLE_ABORTED_BY_CALLBACK);
            throw new TransportException(
                $timedOut ? 'The request timed out.' : ($errno === CURLE_WRITE_ERROR ? 'The answer could not be written.' : 'The API could not be reached.'),
                $timedOut,
                new \RuntimeException(sprintf('cURL error %d: %s', $errno, $error)),
            );
        }
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        rewind($spool);

        return new Response($status, $headers, $spool);
    }

    /**
     * Seconds an upload may wait without progress before its answer's head arrived: `timeout` plus the
     * time `min(size, 4 MiB)` takes to drain at 128 KiB/s. `timeout` itself when there is no body.
     */
    private static function drainAllowance(float $timeout, int $size): float
    {
        return $timeout + min($size, self::DRAIN_CAP) / self::UPLOAD_RATE;
    }

    /**
     * The body as cURL options. Up to 1 MiB: `CURLOPT_POSTFIELDS` (cURL keeps a copy). Above: an upload read from the
     * request's own string by a callback, so a 100 MB file is held once, with `Content-Length` set (never chunked:
     * the API refuses chunked uploads) and the same bytes on every attempt.
     *
     * @return array<int, mixed>
     */
    private static function bodyOptions(#[\SensitiveParameter] string $body): array
    {
        if (strlen($body) <= self::STREAM_UPLOAD_ABOVE) {
            return [CURLOPT_POSTFIELDS => $body];
        }
        $offset = 0;

        return [
            CURLOPT_UPLOAD => true, // with CURLOPT_CUSTOMREQUEST still sending the request's own method
            CURLOPT_INFILESIZE => strlen($body),
            CURLOPT_READFUNCTION => static function ($ch, $fd, int $length) use ($body, &$offset): string {
                $chunk = substr($body, $offset, $length);
                $offset += strlen($chunk);

                return $chunk;
            },
        ];
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return ['curlOptions' => count($this->curlOptions) . ' extra option(s)'];
    }
}
