<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModuleDescriptionTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function moduleProvider(): iterable
    {
        yield 'basic' => array(
            'mod_jem',
            'Displays a compact, configurable list of upcoming, ongoing or archived JEM events with dates, titles, venues and categories.',
        );
        yield 'teaser' => array(
            'mod_jem_teaser',
            'Displays selected JEM events in a visually focused teaser layout with descriptions, event and venue images, dates and links.',
        );
        yield 'wide' => array(
            'mod_jem_wide',
            'Displays JEM events in a wide horizontal layout with event images, dates, titles, categories and venues.',
        );
        yield 'banner' => array(
            'mod_jem_banner',
            'Highlights selected JEM events in configurable banner or card layouts with images, dates, descriptions and links.',
        );
        yield 'jubilee' => array(
            'mod_jem_jubilee',
            'Highlights JEM events whose day and month match a selected date, making it suitable for anniversaries and historical events.',
        );
        yield 'calendar' => array(
            'mod_jem_cal',
            'Displays JEM events in a compact monthly calendar with optional tooltips and AJAX month navigation.',
        );
        yield 'map' => array(
            'mod_jem_map',
            'Displays geocoded JEM venues on an interactive OpenStreetMap or Google map with optional date, category and country filters.',
        );
        yield 'types' => array(
            'mod_jem_types',
            'Lists published JEM event types with upcoming event counts, or groups the next events under each type.',
        );
    }

    #[DataProvider('moduleProvider')]
    public function testDescriptionsAreSpecificAndConsistent(string $module, string $description): void
    {
        $languageKey = strtoupper($module) . '_XML_DESCRIPTION';
        $expected = $languageKey . '="' . $description . '"';
        $languageRoot = 'modules/' . $module . '/language/en-GB/' . $module;

        self::assertStringContainsString($expected, $this->read($languageRoot . '.ini'));
        self::assertStringContainsString($expected, $this->read($languageRoot . '.sys.ini'));
    }

    private function read(string $path): string
    {
        $fullPath = JEM_TEST_ROOT . '/' . $path;
        self::assertFileExists($fullPath);

        return (string) file_get_contents($fullPath);
    }
}
