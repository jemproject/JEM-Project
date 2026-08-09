<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MainControlPanelStyleTest extends TestCase
{
    /**
     * The selected backend layout stylesheet replaces the other variant, so
     * both files must independently support the control-panel markup.
     */
    public function testBothBackendStylesSupportGroupedTilesAndAddBadges(): void
    {
        foreach (array('media/css/backend.css', 'media/css/backend-responsive.css') as $relativePath) {
            $path = JEM_TEST_ROOT . '/' . $relativePath;

            self::assertFileExists($path);
            $css = (string) file_get_contents($path);

            self::assertStringContainsString('.jem-wei-group-title', $css, $relativePath);
            self::assertStringContainsString('.jem-wei-group', $css, $relativePath);
            self::assertStringContainsString('.jem-wei-menus .icon', $css, $relativePath);
            self::assertStringContainsString('a.jem-wei-add', $css, $relativePath);
            self::assertMatchesRegularExpression(
                '/\.cpanel div\.icon a,.*?height:\s*115px;.*?width:\s*137px;/s',
                $css,
                $relativePath
            );
            self::assertMatchesRegularExpression(
                '/a\.jem-wei-add[^}]*\{[^}]*position:\s*absolute;/s',
                $css,
                $relativePath
            );
        }
    }
}
