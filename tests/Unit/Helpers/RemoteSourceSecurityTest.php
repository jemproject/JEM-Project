<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/admin/helpers/remotesource.php';

final class RemoteSourceSecurityTest extends TestCase
{
    #[DataProvider('blockedAddressProvider')]
    public function testRejectsNonPublicAddresses(string $address): void
    {
        self::assertFalse(JemRemoteSourceHelper::isPublicIp($address), $address);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function blockedAddressProvider(): iterable
    {
        yield 'unspecified IPv4' => array('0.0.0.0');
        yield 'private IPv4' => array('10.0.0.1');
        yield 'carrier-grade NAT IPv4' => array('100.64.0.1');
        yield 'loopback IPv4' => array('127.0.0.1');
        yield 'link-local IPv4' => array('169.254.169.254');
        yield 'private class B' => array('172.16.0.1');
        yield 'private class C' => array('192.168.1.1');
        yield 'documentation IPv4' => array('192.0.2.1');
        yield 'deprecated relay IPv4' => array('192.88.99.1');
        yield 'benchmarking IPv4' => array('198.18.0.1');
        yield 'multicast IPv4' => array('224.0.0.1');
        yield 'reserved IPv4' => array('240.0.0.1');
        yield 'unspecified IPv6' => array('::');
        yield 'loopback IPv6' => array('::1');
        yield 'mapped IPv6' => array('::ffff:8.8.8.8');
        yield 'translation IPv6' => array('64:ff9b::1');
        yield 'dummy IPv6' => array('100:0:0:1::1');
        yield 'protocol assignment IPv6' => array('2001:30::1');
        yield 'documentation IPv6' => array('2001:db8::1');
        yield '6to4 IPv6' => array('2002::1');
        yield 'new documentation IPv6' => array('3fff::1');
        yield 'reserved unicast IPv6' => array('4000::1');
        yield 'unique-local IPv6' => array('fd00::1');
        yield 'link-local IPv6' => array('fe80::1');
        yield 'multicast IPv6' => array('ff02::1');
    }

    public function testAcceptsPublicIpv4AndIpv6Addresses(): void
    {
        self::assertTrue(JemRemoteSourceHelper::isPublicIp('1.1.1.1'));
        self::assertTrue(JemRemoteSourceHelper::isPublicIp('2606:4700:4700::1111'));
    }

    public function testInspectsAValidPublicSource(): void
    {
        $target = JemRemoteSourceHelper::inspectUrl(
            'https://downloads.example/events.csv?year=2026#ignored',
            array('csv'),
            '',
            static fn(string $host): array => array('1.1.1.1')
        );

        self::assertSame('https://downloads.example/events.csv?year=2026', $target['url']);
        self::assertSame('downloads.example', $target['host']);
        self::assertSame(443, $target['port']);
        self::assertSame(array('1.1.1.1'), $target['addresses']);
        self::assertSame('csv', $target['extension']);
    }

    public function testRejectsAHostWhenAnyResolvedAddressIsNotPublic(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(JemRemoteSourceHelper::ERROR_INVALID_URL);

        JemRemoteSourceHelper::inspectUrl(
            'https://mixed.example/events.csv',
            array('csv'),
            '',
            static fn(string $host): array => array('1.1.1.1', '127.0.0.1')
        );
    }

    #[DataProvider('invalidUrlProvider')]
    public function testRejectsUnsafeUrlForms(string $url): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(JemRemoteSourceHelper::ERROR_INVALID_URL);

        JemRemoteSourceHelper::inspectUrl(
            $url,
            array('csv'),
            '',
            static fn(string $host): array => array('1.1.1.1')
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUrlProvider(): iterable
    {
        yield 'embedded credentials' => array('https://user:pass@example.org/events.csv');
        yield 'custom HTTPS port' => array('https://example.org:8443/events.csv');
        yield 'file scheme' => array('file:///tmp/events.csv');
        yield 'control character' => array("https://example.org/events.csv\r\nHost: internal");
        yield 'loopback literal' => array('http://127.0.0.1/events.csv');
        yield 'IPv6 loopback literal' => array('http://[::1]/events.csv');
    }

    public function testRejectsAnUnsupportedSourceExtension(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(JemRemoteSourceHelper::ERROR_UNSUPPORTED);

        JemRemoteSourceHelper::inspectUrl(
            'https://example.org/events.php',
            array('csv'),
            '',
            static fn(string $host): array => array('1.1.1.1')
        );
    }

    public function testResolvesRelativeRedirects(): void
    {
        self::assertSame(
            'https://example.org/files/next/events.csv?year=2026',
            JemRemoteSourceHelper::resolveRedirectUrl(
                'https://example.org/files/current/events.csv',
                '../next/events.csv?year=2026'
            )
        );
    }

    public function testRevalidatesEveryRedirectBeforeRequestingIt(): void
    {
        $requested = array();
        $resolver = static function (string $host): array {
            return $host === 'internal.example' ? array('169.254.169.254') : array('1.1.1.1');
        };
        $requester = static function (array $target) use (&$requested): array {
            $requested[] = $target['host'];

            return array(
                'status' => 302,
                'headers' => array('location' => array('http://internal.example/events.csv')),
                'body' => '',
            );
        };

        try {
            JemRemoteSourceHelper::download(
                'https://public.example/events.csv',
                array('csv'),
                '',
                1024,
                $resolver,
                $requester
            );
            self::fail('The private redirect should have been rejected.');
        } catch (RuntimeException $exception) {
            self::assertSame(JemRemoteSourceHelper::ERROR_INVALID_URL, $exception->getMessage());
        }

        self::assertSame(array('public.example'), $requested);
    }

    public function testRejectsHttpsDowngradeRedirects(): void
    {
        $resolver = static fn(string $host): array => array('1.1.1.1');
        $requester = static fn(array $target): array => array(
            'status' => 302,
            'headers' => array('location' => array('http://cdn.example/events.csv')),
            'body' => '',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(JemRemoteSourceHelper::ERROR_INVALID_URL);

        JemRemoteSourceHelper::download(
            'https://public.example/events.csv',
            array('csv'),
            '',
            1024,
            $resolver,
            $requester
        );
    }

    public function testDownloadsAfterAValidatedRedirect(): void
    {
        $requests = 0;
        $resolver = static fn(string $host): array => array('1.1.1.1');
        $requester = static function (array $target) use (&$requests): array {
            $requests++;
            if ($requests === 1) {
                return array(
                    'status' => 302,
                    'headers' => array('location' => array('/exports/current')),
                    'body' => '',
                );
            }

            return array('status' => 200, 'headers' => array(), 'body' => "title,date\nEvent,2026-08-23\n");
        };

        $download = JemRemoteSourceHelper::download(
            'https://public.example/events.csv',
            array('csv'),
            '',
            1024,
            $resolver,
            $requester
        );

        self::assertSame(2, $requests);
        self::assertSame('https://public.example/exports/current', $download['final_url']);
        self::assertSame('csv', $download['extension']);
        self::assertSame('catalog-source.csv', $download['name']);
    }

    public function testRejectsAResponseOverTheByteBudget(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(JemRemoteSourceHelper::ERROR_TOO_LARGE);

        JemRemoteSourceHelper::download(
            'https://public.example/events.csv',
            array('csv'),
            '',
            8,
            static fn(string $host): array => array('1.1.1.1'),
            static fn(array $target): array => array('status' => 200, 'headers' => array(), 'body' => '123456789')
        );
    }
}
