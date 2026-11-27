<?php

declare(strict_types=1);

namespace Anser\Resources;

use Anser\Http;
use Anser\Page;

/** Emails sent to your domains (with receiving on) or to the workspace's test address */
final class Inbox extends Resource
{
    /**
     * @param array{limit?: int, after?: string, before?: string, to?: string|list<string>, from?: string, start_date?: string, end_date?: string, q?: string, kind?: string, status?: string, in_reply_to_email_id?: string} $params
     * @param array{timeout?: float, max_retries?: int} $options
     */
    public function list(array $params = [], array $options = []): Page
    {
        return Page::from($this->http->request('GET', '/inbox', $params, options: $options));
    }

    /**
     * @param array<string, mixed> $params the filters of list()
     * @param array{timeout?: float, max_retries?: int} $options
     * @return \Generator<int, array<string, mixed>>
     */
    public function listAll(array $params = [], array $options = []): \Generator
    {
        return $this->http->iterate('/inbox', $params, $options);
    }

    /**
     * @param string|null $htmlFormat 'cid' leaves inline images as `cid:` references
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function get(string $id, ?string $htmlFormat = null, array $options = []): array
    {
        $query = ['html_format' => $htmlFormat];
        return self::object($this->http->request('GET', '/inbox/' . Http::path($id), $query, options: $options));
    }

    /**
     * Deletes it with its attachments and raw message.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function delete(string $id, array $options = []): array
    {
        return self::object($this->http->request('DELETE', '/inbox/' . Http::path($id), options: $options));
    }

    /**
     * Attachments with download links (valid for an hour), in the order of the email.
     *
     * @param array{limit?: int, after?: string, before?: string} $params
     * @param array{timeout?: float, max_retries?: int} $options
     */
    public function attachments(string $id, array $params = [], array $options = []): Page
    {
        $path = '/inbox/' . Http::path($id) . '/attachments';
        return Page::from($this->http->request('GET', $path, $params, options: $options));
    }

    /**
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function attachment(string $id, string $attachmentId, array $options = []): array
    {
        $path = '/inbox/' . Http::path($id) . '/attachments/' . Http::path($attachmentId);
        return self::object($this->http->request('GET', $path, options: $options));
    }

    /**
     * The workspace's test address (`<name>+anything@…` lands here too); null when receiving is off.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     */
    public function address(array $options = []): ?string
    {
        $answer = self::object($this->http->request('GET', '/inbox/address', options: $options));
        return is_string($answer['address'] ?? null) ? $answer['address'] : null;
    }

    /**
     * Answer it in the same thread (`In-Reply-To`, `References`, `Re:`); returns the email sent.
     *
     * @param array<string, mixed> $params from, to, cc, bcc, reply_all, subject, html, text, tags
     * @param array{idempotency_key?: string, timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function reply(string $id, array $params, array $options = []): array
    {
        return $this->sending('/inbox/' . Http::path($id) . '/reply', $params, $options);
    }

    /**
     * Forward it with its attachments; returns the email sent.
     *
     * @param array<string, mixed> $params from, to, passthrough, html, text, tags
     * @param array{idempotency_key?: string, timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function forward(string $id, array $params, array $options = []): array
    {
        return $this->sending('/inbox/' . Http::path($id) . '/forward', $params, $options);
    }
}
