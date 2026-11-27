<?php

declare(strict_types=1);

namespace Anser;

use Anser\Resources\ApiKeys;
use Anser\Resources\Domains;
use Anser\Resources\Emails;
use Anser\Resources\ForwardingRules;
use Anser\Resources\Inbox;
use Anser\Resources\Logs;
use Anser\Resources\Recipients;
use Anser\Resources\Stats;
use Anser\Resources\Suppressions;
use Anser\Resources\Usage;
use Anser\Resources\Webhooks;

/**
 * The 鸿雁 / Anser SDK: send and receive email through the HTTP API.
 *
 *     $anser = new Anser\Client('ms_…');
 *     $email = $anser->emails->send([
 *         'from' => 'Acme <hello@acme.com>',
 *         'to' => ['email' => 'amy@example.com', 'name' => 'Amy'],
 *         'subject' => 'Welcome',
 *         'html' => '<p>Hi Amy</p>',
 *         'tags' => ['category' => 'welcome'],
 *     ]);
 */
final class Client
{
    public readonly Emails $emails;
    public readonly Inbox $inbox;
    public readonly ForwardingRules $forwardingRules;
    public readonly Domains $domains;
    public readonly ApiKeys $apiKeys;
    public readonly Webhooks $webhooks;
    public readonly Suppressions $suppressions;
    public readonly Stats $stats;
    public readonly Recipients $recipients;
    public readonly Usage $usage;
    public readonly Logs $logs;

    /**
     * @param string|null $apiKey defaults to the ANSER_API_KEY environment variable
     * @param array{base_url?: string, max_retries?: int, timeout?: float, headers?: array<string, string>, transport?: Transport} $options
     *        `max_retries` (default 2) covers network errors, rate limits and server errors; `timeout` is per attempt, in seconds
     */
    public function __construct(?string $apiKey = null, array $options = [])
    {
        $http = new Http(
            $apiKey,
            $options['base_url'] ?? null,
            $options['max_retries'] ?? 2,
            $options['timeout'] ?? 60.0,
            $options['headers'] ?? [],
            $options['transport'] ?? new CurlTransport(),
        );
        $this->emails = new Emails($http);
        $this->inbox = new Inbox($http);
        $this->forwardingRules = new ForwardingRules($http);
        $this->domains = new Domains($http);
        $this->apiKeys = new ApiKeys($http);
        $this->webhooks = new Webhooks($http);
        $this->suppressions = new Suppressions($http);
        $this->stats = new Stats($http);
        $this->recipients = new Recipients($http);
        $this->usage = new Usage($http);
        $this->logs = new Logs($http);
    }
}
