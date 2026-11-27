<?php

declare(strict_types=1);

namespace Anser;

final class Webhook
{
    /**
     * Check a webhook push (Standard Webhooks) and return its event: `['type' => …, 'created_at' => …, 'data' => […]]`.
     *
     *     $event = Anser\Webhook::verify(file_get_contents('php://input'), getallheaders(), $secret);
     *
     * @param string $payload the raw request body
     * @param array<string, string|list<string>> $headers any case
     * @param int $tolerance how old (or how far ahead) a push may be, in seconds
     * @return array<string, mixed>
     * @throws WebhookVerificationException
     */
    public static function verify(string $payload, array $headers, string $secret, int $tolerance = 300, ?int $now = null): array
    {
        $lower = [];
        foreach ($headers as $name => $value) {
            $lower[strtolower($name)] = is_array($value) ? ($value[0] ?? '') : $value;
        }
        $id = $lower['webhook-id'] ?? '';
        $timestamp = $lower['webhook-timestamp'] ?? '';
        $signatures = $lower['webhook-signature'] ?? '';
        if ($id === '' || $timestamp === '' || $signatures === '') {
            throw new WebhookVerificationException('Missing webhook-id, webhook-timestamp or webhook-signature.');
        }
        if (!ctype_digit($timestamp) || abs(($now ?? time()) - (int) $timestamp) > $tolerance) {
            throw new WebhookVerificationException('The webhook timestamp is too old or too far ahead.');
        }
        $key = base64_decode(preg_replace('/^whsec_/', '', $secret) ?? '', true);
        if ($key === false) {
            throw new WebhookVerificationException('The webhook secret is not valid.');
        }
        $expected = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$payload}", $key, true));
        foreach (explode(' ', $signatures) as $signature) {
            if (str_starts_with($signature, 'v1,') && hash_equals($expected, substr($signature, 3))) {
                $event = json_decode($payload, true);
                if (!is_array($event)) {
                    throw new WebhookVerificationException('The webhook body is not JSON.');
                }
                /** @var array<string, mixed> $event */
                return $event;
            }
        }
        throw new WebhookVerificationException('The webhook signature does not match.');
    }
}
