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

    public function testControlPanelUsesDedicatedStatisticsPageInsteadOfAccordions(): void
    {
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/main/tmpl/default.php');
        $view = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/main/view.html.php');

        self::assertStringContainsString('view=statistics', $template);
        self::assertStringContainsString('icon-48-statistics.svg', $template);
        self::assertStringNotContainsString('accordion_jem', $template);
        self::assertStringNotContainsString("get('EventsData')", $view);
        self::assertStringNotContainsString("get('RegistrationData')", $view);
    }

    public function testPostActionTilesReuseLinkLayoutAndIconSize(): void
    {
        $relativePath = 'admin/views/main/tmpl/default.php';
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);

        self::assertMatchesRegularExpression(
            '/\.jem-wei-menus \.icon a,\s*\.jem-wei-menus \.icon button\s*\{'
                . '[^}]*display:\s*flex;[^}]*flex-direction:\s*column;[^}]*align-items:\s*center;/s',
            $template,
            $relativePath
        );
        self::assertMatchesRegularExpression(
            '/\.jem-wei-menus \.icon a img,\s*\.jem-wei-menus \.icon button img\s*\{'
                . '[^}]*width:\s*65px;/s',
            $template,
            $relativePath
        );
    }
}
