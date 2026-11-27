<?php

declare(strict_types=1);

namespace Anser\Tests;

use Anser\AnserException;
use Anser\Attachment;
use Anser\Client;
use PHPUnit\Framework\TestCase;

/**
 * Against a running server; skipped unless ANSER_LIVE_BASE_URL, ANSER_LIVE_API_KEY and ANSER_LIVE_FROM are set.
 * ANSER_LIVE_FROM is an address on a verified domain of that workspace. Mail goes to the SES simulator, which
 * does not count towards the quota.
 */
final class LiveTest extends TestCase
{
    private const TO = 'success@simulator.amazonses.com';

    private Client $anser;
    private string $from;

    protected function setUp(): void
    {
        [$base, $key, $from] = array_map(
            static fn (string $name): string => (string) getenv("ANSER_LIVE_{$name}"),
            ['BASE_URL', 'API_KEY', 'FROM'],
        );
        if ($base === '' || $key === '' || $from === '') {
            self::markTestSkipped('set ANSER_LIVE_BASE_URL, ANSER_LIVE_API_KEY and ANSER_LIVE_FROM');
        }
        $this->anser = new Client($key, ['base_url' => $base]);
        $this->from = $from;
    }

    public function testSchedulesReadsMovesAndCancels(): void
    {
        $at = (new \DateTimeImmutable('+1 day'))->setTime(9, 0);
        $params = [
            'from' => $this->from,
            'to' => self::TO,
            'subject' => 'SDK 联调 👋',
            'text' => 'php',
            'attachments' => [Attachment::fromBytes('hi.txt', '你好')],
            'tags' => ['sdk' => 'php'],
            'scheduled_at' => $at,
        ];
        $key = ['idempotency_key' => 'live-' . bin2hex(random_bytes(8))];
        $sent = $this->anser->emails->send($params, $key);
        // the same key answers with the same email
        self::assertSame($sent, $this->anser->emails->send($params, $key));

        $email = $this->anser->emails->get($sent['id']);
        self::assertSame(['scheduled', [self::TO], 'SDK 联调 👋'], [$email['status'], $email['to'], $email['subject']]);
        self::assertSame(['sdk' => 'php'], $email['tags']);
        self::assertSame('你好', $this->anser->emails->attachment($sent['id'], $email['attachments'][0]['id']));

        self::assertSame('scheduled', $this->anser->emails->update($sent['id'], $at->modify('+1 hour'))['status']);
        self::assertSame('canceled', $this->anser->emails->cancel($sent['id'])['status']);
        self::assertSame(
            ['email.scheduled', 'email.scheduled', 'email.canceled'],
            array_column($this->anser->emails->events($sent['id']), 'type'),
        );
        self::assertContains($sent['id'], array_column($this->anser->emails->list(['limit' => 5])->data, 'id'));
    }

    public function testSendsABatch(): void
    {
        $email = fn (int $n, string $to = self::TO): array => ['from' => $this->from, 'to' => $to, 'subject' => "SDK 联调 {$n}", 'text' => 'php'];
        $batch = $this->anser->emails->sendBatch([$email(1), $email(2)]);
        self::assertSame(['SDK 联调 1', 'SDK 联调 2'], array_column($batch['data'], 'subject'));

        $partial = $this->anser->emails->sendBatch([$email(3), $email(4, 'nope')], atomic: false);
        self::assertNull($partial['data'][1]);
        self::assertSame('emails[1].to[0]', $partial['errors'][0]['param']);
    }

    public function testTheRestOfTheWorkspace(): void
    {
        $domain = rtrim(substr($this->from, strrpos($this->from, '@') + 1), '>');
        self::assertContains($domain, array_column($this->anser->domains->list(), 'name'));
        self::assertSame('usage', $this->anser->usage->get()['object']);
        self::assertCount(7, $this->anser->stats->get(7)['days']);
        $address = 'sdk-live-' . bin2hex(random_bytes(4)) . '@example.com';
        self::assertSame($address, $this->anser->suppressions->add($address)['address']);
        $this->anser->suppressions->remove($address);
        self::assertCount(0, $this->anser->suppressions->list(['q' => $address]));
        try {
            $this->anser->emails->get('01938a1c-0000-7000-8000-000000000000');
            self::fail('no error');
        } catch (AnserException $e) {
            self::assertSame([404, 'not_found'], [$e->statusCode, $e->errorCode]);
        }
    }
}
