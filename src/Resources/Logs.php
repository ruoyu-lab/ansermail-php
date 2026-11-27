<?php

declare(strict_types=1);

namespace Anser\Resources;

use Anser\Http;
use Anser\Page;

/** Your API requests of the last 7 days */
final class Logs extends Resource
{
    /**
     * One page, newest first, without bodies.
     *
     * @param array{limit?: int, after?: string, before?: string, status?: 'succeeded'|'failed'} $params
     * @param array{timeout?: float, max_retries?: int} $options
     */
    public function list(array $params = [], array $options = []): Page
    {
        return Page::from($this->http->request('GET', '/logs', $params, options: $options));
    }

    /**
     * @param array{limit?: int, status?: 'succeeded'|'failed'} $params
     * @param array{timeout?: float, max_retries?: int} $options
     * @return \Generator<int, array<string, mixed>>
     */
    public function listAll(array $params = [], array $options = []): \Generator
    {
        return $this->http->iterate('/logs', $params, $options);
    }

    /**
     * One request with its bodies.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function get(string $id, array $options = []): array
    {
        return self::object($this->http->request('GET', '/logs/' . Http::path($id), options: $options));
    }
}
