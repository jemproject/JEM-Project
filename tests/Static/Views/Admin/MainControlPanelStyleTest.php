<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MainControlPanelStyleTest extends TestCase
{
    public function testCanonicalBackendStyleSupportsGroupedTilesAndAddBadges(): void
    {
        $relativePath = 'media/css/backend.css';
        $path = JEM_TEST_ROOT . '/' . $relativePath;

        self::assertFileExists($path);
        $css = (string) file_get_contents($path);

        self::assertStringContainsString('.jem-wei-group-title', $css, $relativePath);
        self::assertStringContainsString('.jem-wei-group', $css, $relativePath);
        self::assertStringContainsString('.jem-wei-menus .icon', $css, $relativePath);
        self::assertStringContainsString('a.jem-wei-add', $css, $relativePath);
        self::assertMatchesRegularExpression('/\.cpanel div\.icon a,.*?height:\s*115px;.*?width:\s*137px;/s', $css);
        self::assertMatchesRegularExpression('/a\.jem-wei-add[^}]*\{[^}]*position:\s*absolute;/s', $css);
    }
}
