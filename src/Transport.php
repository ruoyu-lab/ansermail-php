<?php

declare(strict_types=1);

namespace Anser;

/**
 * Sends one HTTP request. The default is curl; pass your own to `new Client(…, ['transport' => …])`.
 */
interface Transport
{
    /**
     * @param non-empty-string $method
     * @param array<string, string> $headers
     * @return array{status: int, headers: array<string, string>, body: string} header names in lower case
     * @throws ConnectionException when there is no answer
     */
    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): array;
}
