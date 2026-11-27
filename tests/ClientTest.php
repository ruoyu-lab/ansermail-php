<?php

declare(strict_types=1);

namespace Anser\Tests;

use Anser\AnserException;
use Anser\Attachment;
use Anser\Client;
use Anser\ConnectionException;
use Anser\Transport;
use Anser\Webhook;
use Anser\WebhookVerificationException;
use PHPUnit\Framework\TestCase;

/** Records requests; `$answer` decides each reply */
final class ScriptedTransport implements Transport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: mixed}> */
    public array $requests = [];

    /** @var \Closure(array{method: string, url: string, headers: array<string, string>, body: mixed}, int): (array{status: int, headers: array<string, string>, body: string}|ConnectionException) */
    public \Closure $answer;

    public function __construct()
    {
        $this->answer = static fn () => ['status' => 201, 'headers' => [], 'body' => '{"object":"email","id":"e1"}'];
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): array
    {
        $req = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body === null ? null : json_decode($body, true)];
        $this->requests[] = $req;
        $answer = ($this->answer)($req, count($this->requests));
        if ($answer instanceof ConnectionException) {
            throw $answer;
        }
        return $answer;
    }
}

final class ClientTest extends TestCase
{
    private ScriptedTransport $transport;
    private Client $anser;

    protected function setUp(): void
    {
        $this->transport = new ScriptedTransport();
        $this->anser = new Client('ms_test', ['base_url' => 'https://anser.test/', 'transport' => $this->transport]);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    private static function error(int $status, string $code, array $headers = [], ?string $param = null): array
    {
        $body = ['error' => ['code' => $code, 'message' => "{$code} happened", 'param' => $param]];
        return ['status' => $status, 'headers' => $headers, 'body' => json_encode($body) ?: ''];
    }

    public function testSendsWithAKeyAnAttachmentAndATime(): void
    {
        $sent = $this->anser->emails->send([
            'from' => ['email' => 'hello@acme.com', 'name' => 'Acme'],
            'to' => 'amy@example.com',
            'subject' => '你好',
            'text' => 'Hi',
            'attachments' => [Attachment::fromBytes('a.txt', '你好', 'text/plain')],
            'tags' => ['category' => 'welcome'],
            'scheduled_at' => new \DateTimeImmutable('2026-12-01T09:00:00Z'),
        ]);
        self::assertSame(['object' => 'email', 'id' => 'e1'], $sent);
        $req = $this->transport->requests[0];
        self::assertSame(['POST', 'https://anser.test/v1/emails'], [$req['method'], $req['url']]);
        self::assertSame(['email' => 'hello@acme.com', 'name' => 'Acme'], $req['body']['from']);
        self::assertSame(['category' => 'welcome'], $req['body']['tags']);
        self::assertSame('Bearer ms_test', $req['headers']['Authorization']);
        self::assertMatchesRegularExpression('/^anser-[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $req['headers']['Idempotency-Key']);
        self::assertStringStartsWith('ansermail-php/', $req['headers']['User-Agent']);
        self::assertSame(base64_encode('你好'), $req['body']['attachments'][0]['content']);
        self::assertSame('2026-12-01T09:00:00.000+00:00', $req['body']['scheduled_at']);
    }

    public function testRetriesASendWithTheSameKey(): void
    {
        $this->transport->answer = fn ($req, int $n) => $n === 1
            ? new ConnectionException('reset')
            : ['status' => 201, 'headers' => [], 'body' => '{"id":"e1"}'];
        $this->anser->emails->send(['from' => 'a@acme.com', 'to' => 'b@x.com', 'subject' => 's', 'text' => 't']);
        $keys = array_map(static fn ($r) => $r['headers']['Idempotency-Key'], $this->transport->requests);
        self::assertCount(2, $keys);
        self::assertSame($keys[0], $keys[1]);
    }

    public function testNeverRepeatsACreateWithoutAKey(): void
    {
        $this->transport->answer = fn () => self::error(503, 'internal_error');
        try {
            $this->anser->domains->create('acme.com');
            self::fail('no error');
        } catch (AnserException $e) {
            self::assertSame(503, $e->statusCode);
        }
        self::assertCount(1, $this->transport->requests);
    }

    public function testWaitsOutARateLimitButNotAQuota(): void
    {
        $this->transport->answer = fn ($req, int $n) => $n === 1
            ? self::error(429, 'rate_limit_exceeded', ['retry-after' => '1'])
            : ['status' => 200, 'headers' => [], 'body' => '{"object":"usage"}'];
        $started = microtime(true);
        self::assertSame(['object' => 'usage'], $this->anser->usage->get());
        self::assertGreaterThanOrEqual(0.9, microtime(true) - $started);

        $this->transport->requests = [];
        $this->transport->answer = fn () => self::error(429, 'daily_quota_exceeded');
        try {
            $this->anser->emails->send(['from' => 'a@acme.com', 'to' => 'b@x.com', 'subject' => 's', 'text' => 't']);
            self::fail('no error');
        } catch (AnserException $e) {
            self::assertSame('daily_quota_exceeded', $e->errorCode);
        }
        self::assertCount(1, $this->transport->requests);
    }

    public function testErrors(): void
    {
        $this->transport->answer = fn () => self::error(422, 'validation_error', ['x-request-id' => 'r1'], 'to[1]');
        try {
            $this->anser->emails->send(['from' => 'a@acme.com', 'to' => ['b@x.com', 'nope'], 'subject' => 's', 'text' => 't']);
            self::fail('no error');
        } catch (AnserException $e) {
            self::assertSame(
                [422, 'validation_error', 'validation_error happened', 422, 'to[1]', 'r1'],
                [$e->statusCode, $e->errorCode, $e->getMessage(), $e->getCode(), $e->param, $e->requestId],
            );
        }
        $this->transport->answer = fn () => ['status' => 502, 'headers' => [], 'body' => '<h1>Bad Gateway</h1>'];
        $client = new Client('ms_test', ['base_url' => 'https://anser.test', 'transport' => $this->transport, 'max_retries' => 0]);
        try {
            $client->usage->get();
            self::fail('no error');
        } catch (AnserException $e) {
            self::assertSame([502, 'http_error'], [$e->statusCode, $e->errorCode]);
        }
    }

    public function testSendsABatch(): void
    {
        $result = ['object' => 'batch', 'data' => [null], 'errors' => [['index' => 0, 'code' => 'domain_not_verified', 'message' => 'x', 'param' => 'emails[0].from']]];
        $this->transport->answer = fn () => ['status' => 200, 'headers' => [], 'body' => json_encode($result) ?: ''];
        $email = ['from' => 'a@other.com', 'to' => 'b@x.com', 'subject' => 's', 'text' => 't'];
        self::assertSame($result, $this->anser->emails->sendBatch([$email], atomic: false));
        $req = $this->transport->requests[0];
        self::assertSame('https://anser.test/v1/emails/batch', $req['url']);
        self::assertSame(['emails' => [$email], 'atomic' => false], $req['body']);
    }

    public function testWalksEveryPage(): void
    {
        $this->transport->answer = fn ($req) => [
            'status' => 200,
            'headers' => [],
            'body' => str_contains($req['url'], 'after=2')
                ? '{"object":"list","has_more":false,"data":[{"id":"1"}]}'
                : '{"object":"list","has_more":true,"data":[{"id":"3"},{"id":"2"}]}',
        ];
        $ids = [];
        foreach ($this->anser->emails->listAll(['limit' => 2, 'status' => 'sent']) as $email) {
            $ids[] = $email['id'];
        }
        self::assertSame(['3', '2', '1'], $ids);
        self::assertSame('https://anser.test/v1/emails?limit=2&status=sent', $this->transport->requests[0]['url']);
        $page = $this->anser->suppressions->list(['q' => 'x']);
        self::assertTrue($page->hasMore);
        self::assertCount(2, $page);
    }

    public function testInboxFiltersAndBytes(): void
    {
        $this->transport->answer = fn ($req) => str_contains($req['url'], '/attachments/')
            ? ['status' => 200, 'headers' => [], 'body' => '%PDF']
            : ['status' => 200, 'headers' => [], 'body' => '{"object":"list","has_more":false,"data":[]}'];
        $this->anser->inbox->list(['to' => ['a@acme.com', 'b@acme.com'], 'from' => 'amy@x.com']);
        self::assertSame('https://anser.test/v1/inbox?to=a%40acme.com%2Cb%40acme.com&from=amy%40x.com', $this->transport->requests[0]['url']);
        self::assertSame('%PDF', $this->anser->emails->attachment('e1', 'a/1'));
        self::assertSame('https://anser.test/v1/emails/e1/attachments/a%2F1', $this->transport->requests[1]['url']);
    }

    public function testCurlTimesOutAndFailsToConnect(): void
    {
        $client = new Client('ms_test', ['base_url' => 'http://127.0.0.1:9', 'max_retries' => 0]);
        $this->expectException(ConnectionException::class);
        $client->usage->get();
    }

    public function testVerifiesWebhooks(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__ . '/fixtures/webhook.json') ?: '', true);
        $event = Webhook::verify($fixture['body'], $fixture['headers'], $fixture['secret'], now: $fixture['now']);
        self::assertSame('email.delivered', $event['type']);
        self::assertSame('你好 👋', $event['data']['email']['subject']);
        self::assertSame(['category' => 'welcome'], $event['data']['email']['tags']);
        // headers as getallheaders() spells them
        $headers = [];
        foreach ($fixture['headers'] as $name => $value) {
            $headers[ucwords($name, '-')] = $value;
        }
        Webhook::verify($fixture['body'], $headers, $fixture['secret'], now: $fixture['now']);

        $cases = [
            [str_replace('你好', 'hi', $fixture['body']), $fixture['headers'], $fixture['secret'], $fixture['now']],
            [$fixture['body'], $fixture['headers'], 'whsec_' . base64_encode('other'), $fixture['now']],
            [$fixture['body'], $fixture['headers'], $fixture['secret'], $fixture['now'] + 600],
            [$fixture['body'], ['webhook-id' => 'x'], $fixture['secret'], $fixture['now']],
        ];
        foreach ($cases as $i => [$body, $headers, $secret, $now]) {
            try {
                Webhook::verify($body, $headers, $secret, now: $now);
                self::fail("case {$i} passed");
            } catch (WebhookVerificationException) {
                self::assertTrue(true);
            }
        }
    }
}
