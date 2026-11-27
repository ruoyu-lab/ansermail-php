<?php

declare(strict_types=1);

namespace Anser\Resources;

use Anser\Http;
use Anser\Page;

/** Addresses that are never sent to */
final class Suppressions extends Resource
{
    /**
     * @param array{limit?: int, after?: string, before?: string, q?: string} $params
     * @param array{timeout?: float, max_retries?: int} $options
     */
    public function list(array $params = [], array $options = []): Page
    {
        return Page::from($this->http->request('GET', '/suppressions', $params, options: $options));
    }

    /**
     * @param array{limit?: int, q?: string} $params
     * @param array{timeout?: float, max_retries?: int} $options
     * @return \Generator<int, array<string, mixed>>
     */
    public function listAll(array $params = [], array $options = []): \Generator
    {
        return $this->http->iterate('/suppressions', $params, $options);
    }

    /**
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function add(string $address, array $options = []): array
    {
        return self::object($this->http->request('POST', '/suppressions', [], ['address' => $address], $options));
    }

    /**
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function remove(string $address, array $options = []): array
    {
        return self::object($this->http->request('DELETE', '/suppressions/' . Http::path($address), options: $options));
    }
}
