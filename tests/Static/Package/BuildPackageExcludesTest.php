<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BuildPackageExcludesTest extends TestCase
{
    public function testComponentBuildExcludesDevelopmentFiles(): void
    {
        $buildFile = JEM_TEST_ROOT . '/build.xml';
        self::assertFileExists($buildFile);

        $xml = new DOMDocument();
        $xml->load($buildFile);
        $xpath = new DOMXPath($xml);

        $excluded = array();
        foreach ($xpath->query('//target[@name="build_component"]//exclude') ?: array() as $exclude) {
            if ($exclude instanceof DOMElement) {
                $excluded[] = $exclude->getAttribute('name');
            }
        }

        foreach (array(
            'tests/**',
            'vendor/**',
            '.phpunit.cache/**',
            '.agents/**',
            '.claude/**',
            '.codex/**',
            '.cursor/**',
            '.github/copilot/**',
            '.env',
            '.env.*',
            '*.pem',
            '*.key',
            '*.crt',
            '*.pfx',
            '*.bak',
            '*.orig',
            '*.log',
            'composer.json',
            'composer.lock',
            'phpunit.xml',
            'phpunit.xml.dist',
        ) as $pattern) {
            self::assertContains($pattern, $excluded, $pattern . ' must not be packaged in com_jem.zip.');
        }
    }

    public function testBuildConfigIsLocalOnlyAndNotCommitted(): void
    {
        self::assertFileDoesNotExist(JEM_TEST_ROOT . '/build.config', 'build.config should remain local and ignored.');
        self::assertFileExists(JEM_TEST_ROOT . '/build.config.example', 'build.config.example documents local build values.');
    }
}
