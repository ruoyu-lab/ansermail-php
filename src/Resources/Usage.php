<?php

declare(strict_types=1);

namespace Anser\Resources;

final class Usage extends Resource
{
    /**
     * How much of the workspace's quotas is used.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function get(array $options = []): array
    {
        return self::object($this->http->request('GET', '/usage', options: $options));
    }
}
