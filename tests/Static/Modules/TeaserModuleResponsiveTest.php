<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TeaserModuleResponsiveTest extends TestCase
{
    public function testEventAndVenueImagesStayInsideTheViewportOnMobile(): void
    {
        $template = $this->read('modules/mod_jem_teaser/tmpl/responsive.php');

        $mediaStart = strpos($template, '@media only all and (max-width: 47.938rem)');
        self::assertNotFalse($mediaStart, 'Expected a narrow-viewport media query in responsive.php');

        $baseRule = $this->ruleBody(substr($template, 0, $mediaStart), '#jemmoduleteaser \.jem-eventimg-teaser img');
        self::assertStringContainsString('max-width: 100%;', $baseRule);

        $mobileRule = $this->ruleBody(substr($template, $mediaStart), '#jemmoduleteaser \.jem-eventimg-teaser img');
        self::assertStringContainsString('max-width: 100%;', $mobileRule);
    }

    public function testEventAndVenueImageContainerCannotOverflowItsParent(): void
    {
        $css = $this->read('modules/mod_jem_teaser/tmpl/responsive.css');
        $containerRule = $this->ruleBody($css, '#jemmoduleteaser \.jem-eventimg-teaser');

        self::assertStringContainsString('max-width: 100%;', $containerRule);
        self::assertStringContainsString('box-sizing: border-box;', $containerRule);
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(JEM_TEST_ROOT . '/' . $path);
    }

    private function ruleBody(string $css, string $selector): string
    {
        $matched = preg_match('/' . $selector . '\s*\{([^}]+)\}/', $css, $matches);

        self::assertSame(1, $matched, $selector);

        return $matches[1];
    }
}
