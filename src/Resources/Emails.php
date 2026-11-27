<?php

declare(strict_types=1);

namespace Anser\Resources;

use Anser\Http;
use Anser\Page;

final class Emails extends Resource
{
    /**
     * Queue an email; it leaves within seconds, or at `scheduled_at`. An address is `'amy@example.com'`,
     * `'Amy <amy@example.com>'` or `['email' => 'amy@example.com', 'name' => 'Amy']`; tags are `['category' => 'welcome']`;
     * attachments take base64 `content` (see `Anser\Attachment`); `scheduled_at` may be a `DateTimeInterface`.
     *
     * @param array<string, mixed> $params from, to, subject, html, text, cc, bcc, reply_to, headers, attachments, tags, scheduled_at
     * @param array{idempotency_key?: string, timeout?: float, max_retries?: int} $options
     * @return array<string, mixed> the email: id, status, recipients, tags…
     */
    public function send(array $params, array $options = []): array
    {
        return $this->sending('/emails', self::wire($params), $options);
    }

    /**
     * Up to 100 emails: all or none (an exception says which one, `emails[3].from`), or with `$atomic = false` each
     * taken or refused on its own.
     *
     * @param list<array<string, mixed>> $emails each as for send()
     * @param array{idempotency_key?: string, timeout?: float, max_retries?: int} $options
     * @return array<string, mixed> `data`: one email per email, null for a refused one; `errors`: `[['index' => …, 'code' => …, 'message' => …, 'param' => …]]`
     */
    public function sendBatch(array $emails, ?bool $atomic = null, array $options = []): array
    {
        $body = ['emails' => array_map(self::wire(...), $emails)];
        if ($atomic !== null) {
            $body['atomic'] = $atomic;
        }
        return $this->sending('/emails/batch', $body, $options);
    }

    /**
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function get(string $id, array $options = []): array
    {
        return self::object($this->http->request('GET', '/emails/' . Http::path($id), options: $options));
    }

    /**
     * One page, newest first.
     *
     * @param array{limit?: int, after?: string, before?: string, status?: string, q?: string} $params
     * @param array{timeout?: float, max_retries?: int} $options
     */
    public function list(array $params = [], array $options = []): Page
    {
        return Page::from($this->http->request('GET', '/emails', $params, options: $options));
    }

    /**
     * Every email matching, page after page.
     *
     * @param array{limit?: int, status?: string, q?: string} $params
     * @param array{timeout?: float, max_retries?: int} $options
     * @return \Generator<int, array<string, mixed>>
     */
    public function listAll(array $params = [], array $options = []): \Generator
    {
        return $this->http->iterate('/emails', $params, $options);
    }

    /**
     * What happened to it, oldest first.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return list<array<string, mixed>>
     */
    public function events(string $id, array $options = []): array
    {
        return self::data($this->http->request('GET', '/emails/' . Http::path($id) . '/events', options: $options));
    }

    /**
     * An attachment's bytes; only while the email has not left yet.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     */
    public function attachment(string $id, string $attachmentId, array $options = []): string
    {
        $path = '/emails/' . Http::path($id) . '/attachments/' . Http::path($attachmentId);
        $bytes = $this->http->request('GET', $path, options: $options, raw: true);
        return is_string($bytes) ? $bytes : '';
    }

    /**
     * Move a scheduled email.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed> the email
     */
    public function update(string $id, string|\DateTimeInterface $scheduledAt, array $options = []): array
    {
        $body = ['scheduled_at' => self::time($scheduledAt)];
        return self::object($this->http->request('PATCH', '/emails/' . Http::path($id), [], $body, $options));
    }

    /**
     * Cancel a scheduled email.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed> the email
     */
    public function cancel(string $id, array $options = []): array
    {
        return self::object($this->http->request('POST', '/emails/' . Http::path($id) . '/cancel', options: $options));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private static function wire(array $params): array
    {
        if (isset($params['scheduled_at']) && $params['scheduled_at'] instanceof \DateTimeInterface) {
            $params['scheduled_at'] = self::time($params['scheduled_at']);
        }
        return $params;
    }
}
