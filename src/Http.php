<?php

declare(strict_types=1);

namespace Anser;

/** @internal requests, retries and pagination for the resources */
final class Http
{
    public const VERSION = '0.1.0';
    public const DEFAULT_BASE_URL = 'https://mail.ruoyulab.dev';

    private readonly string $apiKey;
    private readonly string $baseUrl;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        ?string $apiKey,
        ?string $baseUrl,
        private readonly int $maxRetries,
        private readonly float $timeout,
        private readonly array $headers,
        private readonly Transport $transport,
    ) {
        $key = $apiKey ?? (getenv('ANSER_API_KEY') ?: null);
        if ($key === null || $key === '') {
            throw new \InvalidArgumentException('Missing API key: pass one or set ANSER_API_KEY.');
        }
        $this->apiKey = $key;
        $this->baseUrl = rtrim($baseUrl ?? (getenv('ANSER_BASE_URL') ?: self::DEFAULT_BASE_URL), '/');
    }

    /**
     * @param 'GET'|'POST'|'PATCH'|'DELETE' $method
     * @param array<string, mixed> $query
     * @param array{idempotency_key?: string, timeout?: float, max_retries?: int} $options
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        mixed $body = null,
        array $options = [],
        bool $raw = false,
    ): mixed {
        $url = $this->baseUrl . '/v1' . $path;
        $params = [];
        foreach ($query as $name => $value) {
            if ($value !== null) {
                $params[$name] = self::queryValue($value);
            }
        }
        if ($params !== []) {
            $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }
        $headers = $this->headers + [];
        $headers['Authorization'] = "Bearer {$this->apiKey}";
        $headers['User-Agent'] = 'ansermail-php/' . self::VERSION;
        $headers['Accept'] = $raw ? '*/*' : 'application/json';
        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $headers['Content-Type'] = 'application/json';
        }
        $key = $options['idempotency_key'] ?? null;
        if ($key !== null) {
            $headers['Idempotency-Key'] = $key;
        }
        $retries = $options['max_retries'] ?? $this->maxRetries;
        $timeout = $options['timeout'] ?? $this->timeout;
        // A POST runs again only when the server can tell it is the same request
        $safe = $method !== 'POST' || $key !== null;

        for ($attempt = 0; ; $attempt++) {
            try {
                $res = $this->transport->send($method, $url, $headers, $payload, $timeout);
            } catch (ConnectionException $error) {
                if (!$safe || $attempt >= $retries) {
                    throw $error;
                }
                usleep(self::backoff($attempt));
                continue;
            }
            if ($res['status'] >= 200 && $res['status'] < 300) {
                return $raw ? $res['body'] : ($res['body'] === '' ? null : json_decode($res['body'], true, 512, JSON_THROW_ON_ERROR));
            }
            $error = self::toError($res);
            if ($attempt < $retries && self::retryable($error, $safe)) {
                usleep(self::retryAfter($res['headers']) ?? self::backoff($attempt));
                continue;
            }
            throw $error;
        }
    }

    /**
     * Every page of a list, one item at a time.
     *
     * @param array<string, mixed> $query
     * @param array{timeout?: float, max_retries?: int} $options
     * @return \Generator<int, array<string, mixed>>
     */
    public function iterate(string $path, array $query, array $options): \Generator
    {
        while (true) {
            /** @var array{has_more: bool, data: list<array<string, mixed>>} $page */
            $page = $this->request('GET', $path, $query, null, $options);
            yield from $page['data'];
            $last = end($page['data']);
            if (!$page['has_more'] || $last === false) {
                return;
            }
            $query['after'] = $last['id'];
        }
    }

    private static function queryValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return implode(',', array_map(static fn (mixed $v): string => self::queryValue($v), $value));
        }
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @param array{status: int, headers: array<string, string>, body: string} $res
     */
    private static function toError(array $res): AnserException
    {
        $body = json_decode($res['body'], true);
        $error = is_array($body) ? ($body['error'] ?? null) : null;
        if (is_array($error) && is_string($error['code'] ?? null) && is_string($error['message'] ?? null)) {
            $param = is_string($error['param'] ?? null) ? $error['param'] : null;
            return new AnserException($res['status'], $error['code'], $error['message'], $res['headers'], $param);
        }
        // not JSON: a proxy in between answered
        $text = substr($res['body'], 0, 500);
        return new AnserException($res['status'], 'http_error', $text !== '' ? $text : "HTTP {$res['status']}", $res['headers']);
    }

    /** Turned away before it ran (a rate limit, the same key still running), or a server error on a safe call */
    private static function retryable(AnserException $error, bool $safe): bool
    {
        return match ($error->statusCode) {
            429 => $error->errorCode === 'rate_limit_exceeded',
            409 => $error->errorCode === 'idempotency_key_in_use',
            500, 502, 503, 504 => $safe,
            default => false,
        };
    }

    /**
     * @param array<string, string> $headers
     * @return int|null microseconds
     */
    private static function retryAfter(array $headers): ?int
    {
        $seconds = (float) ($headers['retry-after'] ?? 0);
        return $seconds > 0 ? (int) (min($seconds, 60) * 1_000_000) : null;
    }

    /** 0.5 s, 1 s, 2 s … up to 8 s, give or take a quarter (in microseconds) */
    private static function backoff(int $attempt): int
    {
        return (int) (min(0.5 * 2 ** $attempt, 8) * (0.75 + mt_rand() / mt_getrandmax() / 2) * 1_000_000);
    }

    public static function newIdempotencyKey(): string
    {
        $b = random_bytes(16);
        // a version 4 UUID, as the other SDKs make
        $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
        $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
        return 'anser-' . vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    public static function path(string $segment): string
    {
        return rawurlencode($segment);
    }
}
