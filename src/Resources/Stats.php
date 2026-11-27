<?php

declare(strict_types=1);

namespace Anser\Resources;

final class Stats extends Resource
{
    /**
     * Per UTC day, with totals and rates.
     *
     * @param int|null $days 1–90, default 14
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function get(?int $days = null, array $options = []): array
    {
        return self::object($this->http->request('GET', '/stats', ['days' => $days], options: $options));
    }

    /**
     * The same period per mailbox provider (Gmail, QQ, Outlook…), busiest first.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return list<array<string, mixed>>
     */
    public function providers(?int $days = null, array $options = []): array
    {
        return self::data($this->http->request('GET', '/stats/providers', ['days' => $days], options: $options));
    }
}
