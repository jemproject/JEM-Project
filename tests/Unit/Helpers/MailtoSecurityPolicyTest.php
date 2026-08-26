<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/site/helpers/mailtohelper.php';

final class MailtoSecurityPolicyTest extends TestCase
{
    private string $rateDirectory;

    protected function setUp(): void
    {
        $this->rateDirectory = sys_get_temp_dir() . '/jem-mailto-rate-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->rateDirectory)) {
            return;
        }

        foreach (array_diff(scandir($this->rateDirectory) ?: array(), array('.', '..')) as $entry) {
            $path = $this->rateDirectory . DIRECTORY_SEPARATOR . $entry;

            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($this->rateDirectory);
    }

    #[DataProvider('headerDataProvider')]
    public function testRejectsUnsafeOrOversizedHeaderData(array $data, bool $expected): void
    {
        self::assertSame($expected, JemMailtoHelper::containsForbiddenHeaderData($data));
    }

    public static function headerDataProvider(): array
    {
        $valid = array(
            'emailto' => 'friend@example.test',
            'emailfrom' => 'member@example.test',
            'sender' => 'JEM Member',
            'subject' => 'Invitation to an event',
        );

        return array(
            'valid' => array($valid, false),
            'case insensitive header' => array(array_replace($valid, array('subject' => '  BcC: hidden@example.test')), true),
            'control character' => array(array_replace($valid, array('sender' => "Member\r\nBcc: hidden@example.test")), true),
            'oversized recipient' => array(array_replace($valid, array('emailto' => str_repeat('a', 255))), true),
            'invalid utf8' => array(array_replace($valid, array('subject' => "\xC3\x28")), true),
            'missing field' => array(array_diff_key($valid, array('subject' => true)), true),
        );
    }

    public function testNormalisesOnlyDirectValidIpAddresses(): void
    {
        self::assertSame('192.0.2.10', JemMailtoHelper::normaliseRemoteAddress(' 192.0.2.10 '));
        self::assertSame('2001:db8::10', JemMailtoHelper::normaliseRemoteAddress('2001:db8::10'));
        self::assertSame('', JemMailtoHelper::normaliseRemoteAddress('not-an-address'));
        self::assertSame('', JemMailtoHelper::normaliseRemoteAddress(array('192.0.2.10')));
    }

    public function testNormalisesOnlySupportedJemItemContexts(): void
    {
        self::assertSame(
            array('view' => 'event', 'id' => 42),
            JemMailtoHelper::normaliseLinkContext(array('view' => 'Event', 'id' => '42:summer-film'))
        );
        self::assertSame(
            array('view' => 'venueslist', 'id' => 0),
            JemMailtoHelper::normaliseLinkContext(array('view' => 'venueslist'))
        );
        self::assertSame(array(), JemMailtoHelper::normaliseLinkContext(array('view' => 'event', 'id' => 0)));
        self::assertSame(array(), JemMailtoHelper::normaliseLinkContext(array('view' => 'users', 'id' => 42)));
    }

    public function testAccountLimitIsFiveAndResetsAfterTheWindow(): void
    {
        for ($index = 1; $index <= JemMailtoHelper::ACCOUNT_RATE_LIMIT; ++$index) {
            self::assertTrue($this->consume('session-' . $index, 42, '192.0.2.' . $index, 1000 + $index));
        }

        self::assertFalse($this->consume('session-6', 42, '192.0.2.6', 1006));
        self::assertTrue($this->consume('session-7', 42, '192.0.2.7', 1001 + JemMailtoHelper::RATE_WINDOW_SECONDS));
    }

    public function testRejectedLayerDoesNotConsumeOtherQuotas(): void
    {
        for ($index = 1; $index <= JemMailtoHelper::ACCOUNT_RATE_LIMIT; ++$index) {
            self::assertTrue($this->consume('owner-' . $index, 42, '192.0.2.' . $index, 2000 + $index));
        }

        for ($index = 1; $index <= JemMailtoHelper::SESSION_RATE_LIMIT; ++$index) {
            self::assertFalse($this->consume('shared-session', 42, '198.51.100.' . $index, 2010 + $index));
        }

        self::assertTrue($this->consume('shared-session', 43, '203.0.113.10', 2020));
    }

    public function testAggregateIpLimitIsAppliedAcrossAccounts(): void
    {
        for ($index = 1; $index <= JemMailtoHelper::IP_RATE_LIMIT; ++$index) {
            self::assertTrue($this->consume('session-' . $index, 100 + $index, '192.0.2.50', 3000 + $index));
        }

        self::assertFalse($this->consume('session-21', 121, '192.0.2.50', 3021));
    }

    public function testInvalidRemoteAddressDoesNotCreateOneSharedFallbackQuota(): void
    {
        for ($index = 1; $index <= JemMailtoHelper::ACCOUNT_RATE_LIMIT; ++$index) {
            self::assertTrue($this->consume('first-' . $index, 50, 'invalid', 4000 + $index));
        }

        self::assertTrue($this->consume('second', 51, 'invalid', 4010));
    }

    public function testRateStateContainsOnlyOpaqueIdentitiesAndCounters(): void
    {
        self::assertTrue($this->consume('private-session-id', 42, '192.0.2.60', 5000));

        $contents = file_get_contents($this->rateDirectory . '/.limits.json');
        self::assertIsString($contents);
        self::assertStringNotContainsString('private-session-id', $contents);
        self::assertStringNotContainsString('192.0.2.60', $contents);
        self::assertStringNotContainsString('site-secret', $contents);

        $state = json_decode($contents, true);
        self::assertIsArray($state);
        self::assertCount(3, $state);

        foreach ($state as $identity => $entry) {
            self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $identity);
            self::assertSame(array('window', 'count'), array_keys($entry));
        }
    }

    public function testMalformedStateFailsClosed(): void
    {
        mkdir($this->rateDirectory, 0755, true);
        file_put_contents($this->rateDirectory . '/.limits.json', '{invalid');

        self::assertFalse($this->consume('session', 42, '192.0.2.70', 6000));
    }

    private function consume(string $sessionId, int $userId, string $remote, int $now): bool
    {
        return JemMailtoHelper::consumeSubmissionLimits(
            $this->rateDirectory,
            $remote,
            $sessionId,
            $userId,
            'site-secret',
            $now
        );
    }
}
