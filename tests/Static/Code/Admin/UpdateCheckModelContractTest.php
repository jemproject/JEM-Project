<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class UpdateCheckModelContractTest extends TestCase
{
    public function testUpdatecheckFallsBackToLatestXmlVersionWhenNoCompatibleTargetMatches(): void
    {
        $code = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/updatecheck.php');

        self::assertStringContainsString('$highestPlatformUpdate = null;', $code);
        self::assertStringContainsString('$this->compareUpdatePlatform($updatexml, $highestPlatformUpdate) > 0', $code);
        self::assertStringContainsString('$selectedUpdate = $selectedUpdate ?: $highestPlatformUpdate;', $code);
        self::assertStringContainsString('$this->assignUpdateData($updatedata, $selectedUpdate, $installedversion);', $code);
    }

    public function testUpdatecheckReportsInstalledNewerVersionInsteadOfFailing(): void
    {
        $code = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/updatecheck.php');

        self::assertStringContainsString('private function assignUpdateData', $code);
        self::assertStringContainsString('$updatedata->failed           = 0;', $code);
        self::assertStringContainsString('$updatedata->installedversion = $installedversion;', $code);
        self::assertStringContainsString('$updatedata->current          = version_compare($installedversion, $version);', $code);
    }

    public function testUpdatecheckSuppressesNetworkWarningsAndUsesTimeout(): void
    {
        $code = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/updatecheck.php');

        self::assertStringContainsString('protected static function fetchUpdateXml($filename)', $code);
        self::assertStringContainsString("'timeout' => 5", $code);
        self::assertStringContainsString('$contents = @file_get_contents($filename, false, $context);', $code);
        self::assertStringContainsString('$updateXml = self::fetchUpdateXml($updateFile);', $code);
        self::assertStringNotContainsString('file_get_contents($updateFile)', $code);
    }
}
