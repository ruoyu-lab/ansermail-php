<?php

declare(strict_types=1);

namespace Anser\Resources;

use Anser\Http;

/**
 * @phpstan-type Options array{idempotency_key?: string, timeout?: float, max_retries?: int}
 */
abstract class Resource
{
    public function __construct(protected readonly Http $http)
    {
    }

    /**
     * Sending calls always carry an idempotency key, so a retry never sends twice.
     *
     * @param array{idempotency_key?: string, timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    protected function sending(string $path, mixed $body, array $options): array
    {
        $options['idempotency_key'] ??= Http::newIdempotencyKey();
        return self::object($this->http->request('POST', $path, [], $body, $options));
    }

    /** @return array<string, mixed> */
    protected static function object(mixed $answer): array
    {
        /** @var array<string, mixed> $answer */
        return $answer;
    }

    /** @return list<array<string, mixed>> */
    protected static function data(mixed $answer): array
    {
        /** @var array{data: list<array<string, mixed>>} $answer */
        return $answer['data'];
    }

    protected static function time(string|\DateTimeInterface $value): string
    {
        return $value instanceof \DateTimeInterface ? $value->format(\DateTimeInterface::RFC3339_EXTENDED) : $value;
    }
}
