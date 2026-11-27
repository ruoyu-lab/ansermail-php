<?php

declare(strict_types=1);

namespace Anser;

final class CurlTransport implements Transport
{
    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): array
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }
        $received = [];
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => (int) round($timeout * 1000),
            CURLOPT_HEADERFUNCTION => static function (\CurlHandle $_, string $line) use (&$received): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $received[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }
        $content = curl_exec($handle);
        if (!is_string($content)) {
            $error = curl_error($handle);
            $timedOut = curl_errno($handle) === CURLE_OPERATION_TIMEDOUT;
            $host = parse_url($url, PHP_URL_HOST);
            throw new ConnectionException(
                $timedOut ? "Timed out after {$timeout} s" : 'Could not reach ' . (is_string($host) ? $host : $url) . ": {$error}"
            );
        }
        /** @var array<string, string> $received */
        return ['status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'headers' => $received, 'body' => $content];
    }
}
