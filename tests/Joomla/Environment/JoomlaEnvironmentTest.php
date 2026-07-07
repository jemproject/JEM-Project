<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class JoomlaEnvironmentTest extends TestCase
{
    public function testJoomlaRootEnvironmentVariablePointsToAJoomlaInstallation(): void
    {
        $root = getenv('JEM_TEST_JOOMLA_ROOT');

        if (!$root) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to run Joomla integration tests.');
        }

        $root = rtrim(str_replace('\\', '/', (string) $root), '/');

        self::assertDirectoryExists($root, 'JEM_TEST_JOOMLA_ROOT should point to an existing Joomla root.');
        self::assertFileExists($root . '/configuration.php', 'Joomla configuration.php is required.');
        self::assertDirectoryExists($root . '/administrator/components/com_jem', 'Backend com_jem must be installed.');
        self::assertDirectoryExists($root . '/components/com_jem', 'Site com_jem must be installed.');
        self::assertFileExists($root . '/libraries/src/Version.php', 'Joomla 6 libraries should be available.');
    }
}
