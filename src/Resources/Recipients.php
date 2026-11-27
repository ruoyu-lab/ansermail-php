<?php

declare(strict_types=1);

namespace Anser\Resources;

use Anser\Http;

final class Recipients extends Resource
{
    /**
     * Why someone may not be getting your emails: the suppression list, bounces, the last emails to them.
     *
     * @param array{timeout?: float, max_retries?: int} $options
     * @return array<string, mixed>
     */
    public function get(string $address, array $options = []): array
    {
        return self::object($this->http->request('GET', '/recipients/' . Http::path($address), options: $options));
    }
}
