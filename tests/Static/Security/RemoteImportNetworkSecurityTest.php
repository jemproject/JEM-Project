<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RemoteImportNetworkSecurityTest extends TestCase
{
    public function testRemoteImportsUseTheReviewedNetworkPolicy(): void
    {
        $controller = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/import.php');
        $catalog = (string) file_get_contents(JEM_TEST_ROOT . '/admin/helpers/importcatalog.php');
        $policy = (string) file_get_contents(JEM_TEST_ROOT . '/admin/helpers/remotesource.php');

        self::assertStringContainsString("require_once JPATH_COMPONENT_ADMINISTRATOR . '/helpers/remotesource.php';", $controller);
        self::assertStringContainsString('JemRemoteSourceHelper::download(', $controller);
        self::assertStringContainsString('catch (\\Throwable $exception)', $controller);
        self::assertStringContainsString("require_once __DIR__ . '/remotesource.php';", $catalog);
        self::assertStringContainsString('JemRemoteSourceHelper::download(', $catalog);
        self::assertStringNotContainsString("'follow_location' => 3", $controller);
        self::assertStringNotContainsString('@file_get_contents($url', $controller);
    }

    public function testRemotePolicyPinsValidatedAddressesAndOwnsRedirects(): void
    {
        $policy = (string) file_get_contents(JEM_TEST_ROOT . '/admin/helpers/remotesource.php');

        self::assertStringContainsString('FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE', $policy);
        self::assertStringContainsString('FILTER_FLAG_GLOBAL_RANGE', $policy);
        self::assertStringContainsString('CURLOPT_RESOLVE', $policy);
        self::assertStringContainsString('CURLOPT_FOLLOWLOCATION => false', $policy);
        self::assertStringContainsString("'follow_location' => 0", $policy);
        self::assertStringContainsString('resolveRedirectUrl(', $policy);
        self::assertStringContainsString('MAX_REDIRECTS = 3', $policy);
        self::assertStringContainsString('MAX_ADDRESS_ATTEMPTS = 4', $policy);
        self::assertStringContainsString('REQUEST_TIMEOUT = 20', $policy);
        self::assertStringContainsString('MAX_HEADER_BYTES = 65536', $policy);
        self::assertStringContainsString('stream_set_timeout(', $policy);
        self::assertStringContainsString('Accept-Encoding: identity', $policy);
        self::assertStringContainsString('CURLOPT_SSL_VERIFYPEER => true', $policy);
        self::assertStringContainsString("'verify_peer_name' => true", $policy);
    }
}
