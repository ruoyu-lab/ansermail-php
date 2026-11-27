<?php

declare(strict_types=1);

namespace Anser\Resources;

use Anser\Http;
use Anser\Page;

/** Check the pushes you receive with `Anser\Webhook::verify()` */
final class Webhooks extends Resource
{
    /**
     * The answer has the `secret` for checking signatures.
     *
     * @param array{endpoint: string, events: list<string>, description?: string} $params
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function create(array $params, array $options = []): array
    {
        return self::object($this->http->request('POST', '/webhooks', [], $params, $options));
    }

    /**
     * @param array{timeout?: float, max_retries?: int} $options
     * @return list<array<string, mixed>>
     */
    public function list(array $options = []): array
    {
        return self::data($this->http->request('GET', '/webhooks', options: $options));
    }

    /**
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function get(string $id, array $options = []): array
    {
        return self::object($this->http->request('GET', '/webhooks/' . Http::path($id), options: $options));
    }

    /**
     * @param array{endpoint?: string, events?: list<string>, description?: string|null, enabled?: bool} $changes
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function update(string $id, array $changes, array $options = []): array
    {
        return self::object($this->http->request('PATCH', '/webhooks/' . Http::path($id), [], $changes, $options));
    }

    /**
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function delete(string $id, array $options = []): array
    {
        return self::object($this->http->request('DELETE', '/webhooks/' . Http::path($id), options: $options));
    }

    /**
     * @param array{limit?: int, after?: string, before?: string} $params
     * @param array{timeout?: float, max_retries?: int} $options
     */
    public function deliveries(string $id, array $params = [], array $options = []): Page
    {
        $path = '/webhooks/' . Http::path($id) . '/deliveries';
        return Page::from($this->http->request('GET', $path, $params, options: $options));
    }

    /**
     * Queue a `webhook.test` push.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function test(string $id, array $options = []): array
    {
        return self::object($this->http->request('POST', '/webhooks/' . Http::path($id) . '/test', options: $options));
    }
}
