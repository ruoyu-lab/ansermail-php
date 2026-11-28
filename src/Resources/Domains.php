<?php

declare(strict_types=1);

namespace Anser\Resources;

use Anser\Http;

final class Domains extends Resource
{
    /**
     * Creates the SES identity; add the DNS records it returns.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function create(string $name, array $options = []): array
    {
        return self::object($this->http->request('POST', '/domains', [], ['name' => $name], $options));
    }

    /**
     * @param array{timeout?: float, max_retries?: int} $options
     * @return list<array<string, mixed>>
     */
    public function list(array $options = []): array
    {
        return self::data($this->http->request('GET', '/domains', options: $options));
    }

    /**
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function get(string $id, array $options = []): array
    {
        return self::object($this->http->request('GET', '/domains/' . Http::path($id), options: $options));
    }

    /**
     * @param array{open_tracking?: bool, click_tracking?: bool, receiving?: bool} $changes
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function update(string $id, array $changes, array $options = []): array
    {
        return self::object($this->http->request('PATCH', '/domains/' . Http::path($id), [], $changes, $options));
    }

    /**
     * Check SES and the DNS records again now.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function verify(string $id, array $options = []): array
    {
        return self::object($this->http->request('POST', '/domains/' . Http::path($id) . '/verify', options: $options));
    }

    /**
     * Add the records that are not in effect yet at Cloudflare, with an API token that can edit the zone's DNS. The token
     * is used for this call only and never stored.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed> `object: "dns_setup"`, the `zone`, a `results` entry per record, and the `domain`
     */
    public function addToCloudflare(string $id, string $token, array $options = []): array
    {
        $path = '/domains/' . Http::path($id) . '/dns/cloudflare';
        return self::object($this->http->request('POST', $path, [], ['token' => $token], $options));
    }

    /**
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function delete(string $id, array $options = []): array
    {
        return self::object($this->http->request('DELETE', '/domains/' . Http::path($id), options: $options));
    }
}
