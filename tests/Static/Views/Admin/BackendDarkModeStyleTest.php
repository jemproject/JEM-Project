<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BackendDarkModeStyleTest extends TestCase
{
    public function testJemSurfacesUseThemeAwareBackgroundTokens(): void
    {
        $relativePath = 'media/css/backend.css';
        $css = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);

        self::assertStringNotContainsString('var(--template-bg-light', $css, $relativePath);

        $contracts = array(
            '.jem-statistics-card .card-header' => '--card-header-bg',
            '.jem-statistics-detail-card .card-header' => '--card-header-bg',
            '.jem-statistics-order-kpis > div' => '--secondary-bg',
            '.jem-statistics-event-filters' => '--secondary-bg',
            '.jem-statistics-detail-card .progress' => '--progress-bg',
            '.jem-operating-profile-card' => '--secondary-bg',
            '.jem-operating-profile-summary' => '--secondary-bg',
        );

        foreach ($contracts as $selector => $variable) {
            self::assertMatchesRegularExpression(
                '/' . preg_quote($selector, '/') . '\s*\{[^}]*var\('
                    . preg_quote($variable, '/') . '[,)]/s',
                $css,
                $selector . ' must use ' . $variable . '.'
            );
        }
    }
}
