<?php

declare(strict_types=1);

namespace Anser\Resources;

use Anser\Http;

/** Forward received emails to other mailboxes; a new destination confirms by a link first */
final class ForwardingRules extends Resource
{
    /**
     * @param array{timeout?: float, max_retries?: int} $options
     * @return list<array<string, mixed>>
     */
    public function list(array $options = []): array
    {
        return self::data($this->http->request('GET', '/forwarding-rules', options: $options));
    }

    /**
     * @param string $address `support@acme.com`, or `*@acme.com` for the whole domain
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function create(string $address, string $forwardTo, array $options = []): array
    {
        $body = ['address' => $address, 'forward_to' => $forwardTo];
        return self::object($this->http->request('POST', '/forwarding-rules', [], $body, $options));
    }

    /**
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function setEnabled(string $id, bool $enabled, array $options = []): array
    {
        $path = '/forwarding-rules/' . Http::path($id);
        return self::object($this->http->request('PATCH', $path, [], ['enabled' => $enabled], $options));
    }

    /**
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function delete(string $id, array $options = []): array
    {
        return self::object($this->http->request('DELETE', '/forwarding-rules/' . Http::path($id), options: $options));
    }

    /**
     * Send the confirmation email again (the old link stops working).
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function resendConfirmation(string $id, array $options = []): array
    {
        $path = '/forwarding-rules/' . Http::path($id) . '/confirmation';
        return self::object($this->http->request('POST', $path, options: $options));
    }
}
