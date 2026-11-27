<?php

declare(strict_types=1);

namespace Anser\Resources;

use Anser\Http;

final class ApiKeys extends Resource
{
    /**
     * The answer's `token` is shown only this once.
     *
     * @param array{name: string, permission?: 'full'|'send', domain_id?: string} $params
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function create(array $params, array $options = []): array
    {
        return self::object($this->http->request('POST', '/api-keys', [], $params, $options));
    }

    /**
     * @param array{timeout?: float, max_retries?: int} $options
     * @return list<array<string, mixed>>
     */
    public function list(array $options = []): array
    {
        return self::data($this->http->request('GET', '/api-keys', options: $options));
    }

    /**
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function delete(string $id, array $options = []): array
    {
        return self::object($this->http->request('DELETE', '/api-keys/' . Http::path($id), options: $options));
    }
}
