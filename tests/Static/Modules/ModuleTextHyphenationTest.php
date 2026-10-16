<?php
/**
 * @package    JEM
 * @copyright  (C) 2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModuleTextHyphenationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function textTargets(): iterable
    {
        yield 'text target 1' => ['modules/mod_jem/tmpl/default.css', '#jemmodulebasic .event-title'];
        yield 'text target 2' => ['modules/mod_jem/tmpl/table.css', '#jemmodulebasic .event-title'];
        yield 'text target 3' => ['modules/mod_jem/tmpl/table-style.css', '#jemmodulebasic-tablestyle .event-title'];
        yield 'text target 4' => ['modules/mod_jem/tmpl/table-advanced.css', '#jemmodulebasic-tableadvanced .event-title'];
        yield 'text target 5' => ['modules/mod_jem/tmpl/acymailing.css', '.jem_acymailing .event-title'];
        yield 'text target 6' => ['modules/mod_jem_banner/tmpl/default.css', '#jemmodulebanner .event-title'];
        yield 'text target 7' => ['modules/mod_jem_banner/tmpl/default.css', '#jemmodulebanner .desc'];
        yield 'text target 8' => ['modules/mod_jem_banner/tmpl/responsive.css', '#jemmodulebanner .desc'];
        yield 'text target 9' => ['modules/mod_jem_banner/tmpl/cards.css', '#jemmodulebanner_cards .event-title'];
        yield 'text target 10' => ['modules/mod_jem_banner/tmpl/cards.css', '#jemmodulebanner_cards .event-description'];
        yield 'text target 11' => ['modules/mod_jem_banner/tmpl/table-advanced.css', '#jemmodulebanner .event-title'];
        yield 'text target 12' => ['modules/mod_jem_banner/tmpl/table-advanced.css', '#jemmodulebanner .event-description'];
        yield 'text target 13' => ['modules/mod_jem_banner/tmpl/cards-places.css', '#jemmodulebanner_cards_places .jem-banner-card-title'];
        yield 'text target 14' => ['modules/mod_jem_teaser/tmpl/default.css', '#jemmoduleteaser .event-title'];
        yield 'text target 15' => ['modules/mod_jem_teaser/tmpl/default.css', '#jemmoduleteaser .jem-description-teaser'];
        yield 'text target 16' => ['modules/mod_jem_teaser/tmpl/responsive.css', '#jemmoduleteaser .jem-description-teaser'];
        yield 'text target 17' => ['modules/mod_jem_jubilee/tmpl/default.css', '#jemmodulejubilee .event-title'];
        yield 'text target 18' => ['modules/mod_jem_jubilee/tmpl/default.css', '#jemmodulejubilee .desc'];
        yield 'text target 19' => ['modules/mod_jem_jubilee/tmpl/responsive.css', '#jemmodulejubilee .event-title'];
        yield 'text target 20' => ['modules/mod_jem_jubilee/tmpl/responsive.css', '#jemmodulejubilee .desc'];
        yield 'text target 21' => ['modules/mod_jem_wide/tmpl/default.css', '#jemmodulewide .event-title'];
        yield 'text target 22' => ['media/css/jem.css', '.mod-jem-types .mod-jem-types__link'];
        yield 'text target 23' => ['media/css/jem.css', '.mod-jem-types .mod-jem-types__group-title'];
        yield 'text target 24' => ['media/css/jem.css', '.mod-jem-types .mod-jem-types__event > a:first-child'];
        yield 'text target 25' => ['media/css/jem-responsive.css', '.mod-jem-types .mod-jem-types__link'];
        yield 'text target 26' => ['media/css/jem-responsive.css', '.mod-jem-types .mod-jem-types__group-title'];
        yield 'text target 27' => ['media/css/jem-responsive.css', '.mod-jem-types .mod-jem-types__event > a:first-child'];
        yield 'text target 28' => ['modules/mod_jem/tmpl/responsive.css', '#jemmodulebasic .event-title'];
        yield 'text target 29' => ['modules/mod_jem_wide/tmpl/responsive.css', '#jemmodulewide .eventlist'];
    }

    #[DataProvider('textTargets')]
    public function testTextTargetsEnableNativeHyphenation(string $path, string $selector): void
    {
        $css = (string) file_get_contents(JEM_TEST_ROOT . '/' . $path);
        $pattern = '~(?:^|[},])\s*' . preg_quote($selector, '~')
            . '\s*(?:,[^{}]*)?\{([^{}]*)\}~m';
        self::assertSame(1, preg_match($pattern, $css, $matches), $path . ': ' . $selector);
        self::assertMatchesRegularExpression('/\bhyphens\s*:\s*auto\s*;/', $matches[1]);
        self::assertDoesNotMatchRegularExpression('/text-align\s*:\s*justify|word-break\s*:\s*break-all/', $matches[1]);
    }

    public function testMailLayoutTargetsTheTitleWithoutChangingItsText(): void
    {
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/modules/mod_jem/tmpl/acymailing.php');
        self::assertStringContainsString('<p class="event-title" style="', $template);
        self::assertStringNotContainsString('&shy;', $template);
    }
}
