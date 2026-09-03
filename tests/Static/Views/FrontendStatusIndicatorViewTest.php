<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrontendStatusIndicatorViewTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function menuViewProvider(): iterable
    {
        yield 'events blog' => array('eventsblog', '1');
        yield 'event detail' => array('event', '0');
        yield 'events list' => array('eventslist', '0');
        yield 'category' => array('category', '0');
        yield 'venue' => array('venue', '0');
        yield 'day' => array('day', '0');
        yield 'event type' => array('typeevents', '0');
        yield 'search' => array('search', '0');
    }

    #[DataProvider('menuViewProvider')]
    public function testSupportedMenuViewsExposeTheExpectedDefault(string $view, string $default): void
    {
        $manifestPath = JEM_TEST_ROOT . '/site/views/' . $view . '/tmpl/default.xml';
        $xml = simplexml_load_file($manifestPath);

        self::assertNotFalse($xml, $view);
        $fields = $xml->xpath('//fieldset[@name="basic"]/field[@name="show_status_indicators"]');
        self::assertIsArray($fields, $view);
        self::assertCount(1, $fields, $view);
        self::assertSame($default, (string) $fields[0]['default'], $view);
        self::assertSame('COM_JEM_SHOW_STATUS_INDICATORS', (string) $fields[0]['label'], $view);
    }

    public function testDayTimelineMenuExposesDisabledIndicatorsByDefault(): void
    {
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/site/views/day/tmpl/timeline.xml');

        self::assertNotFalse($xml);
        $fields = $xml->xpath('//fieldset[@name="basic"]/field[@name="show_status_indicators"]');
        self::assertIsArray($fields);
        self::assertCount(1, $fields);
        self::assertSame('0', (string) $fields[0]['default']);
        self::assertSame('COM_JEM_SHOW_STATUS_INDICATORS', (string) $fields[0]['label']);
    }

    public function testBaseViewScopesAndPreparesConfiguredIndicators(): void
    {
        $view = $this->read('site/classes/view.class.php');

        foreach (array('category', 'day', 'event', 'eventsblog', 'eventslist', 'search', 'typeevents', 'venue') as $name) {
            self::assertStringContainsString("'" . $name . "'", $view, $name);
        }

        self::assertStringContainsString('JemHelper::isActiveMenuView($view)', $view);
        self::assertStringContainsString("get('show_status_indicators', \$default)", $view);
        self::assertStringContainsString("\$view === 'eventsblog' ? 1 : 0", $view);
        self::assertStringContainsString('property_exists($candidate, \'event_status\')', $view);
        self::assertStringContainsString('JemOutput::prepareModuleEventStatuses($events, $settings);', $view);
        self::assertStringContainsString('JemHelper::loadModuleStatusAssets();', $view);
    }

    public function testSharedOutputUsesOneImageRibbonOrOneFallbackBadge(): void
    {
        $output = $this->read('site/classes/output.class.php');

        self::assertStringContainsString('$event->event_status_indicators_prepared = true;', $output);
        self::assertStringContainsString('static public function eventStatusImage(', $output);
        self::assertStringContainsString('static public function eventStatusFallbackBadge(', $output);
        self::assertStringContainsString('$event->event_status_indicator_on_image = true;', $output);
        self::assertStringContainsString('$event->event_status_indicator_badge_rendered = true;', $output);
        self::assertStringContainsString("'jem-module-event-status-image--inline jem-event-status-image--' . \$type", $output);
        self::assertStringContainsString('return self::eventStatusFallbackBadge($event);', $output);
        self::assertStringContainsString('jem-event-status-image jem-module-event-status-image', $output);
        self::assertStringContainsString('jem-event-status-ribbon', $output);
        self::assertStringContainsString("preg_replace('/[^A-Za-z0-9 _-]/'", $output);
    }

    public function testEventsBlogUsesEventThenVenueImageAndIgnoresPlaceholder(): void
    {
        $view = $this->read('site/views/eventsblog/view.html.php');
        $template = $this->read('site/views/eventsblog/tmpl/default.php');
        $css = $this->read('media/css/eventsblog.css');

        self::assertStringContainsString('!empty($row->locimage)', $view);
        self::assertStringContainsString("'venue'", $view);
        self::assertStringContainsString('$row->blogHasImage', $view);
        self::assertStringContainsString('if (!empty($row->blogHasImage))', $template);
        self::assertStringContainsString('JemOutput::eventStatusImage(', $template);
        self::assertStringContainsString('jem-eventsblog-status-image', $css);
    }

    public function testEventDetailLayoutsUseTheSharedPolicy(): void
    {
        foreach (array(
            'site/views/event/tmpl/default.php',
            'site/views/event/tmpl/responsive/default.php',
        ) as $path) {
            $template = $this->read($path);

            self::assertStringContainsString('event_status_indicator_image_available', $template, $path);
            self::assertStringContainsString('JemOutput::eventStatusImage(', $template, $path);
            self::assertStringContainsString('JemOutput::eventStatusFallbackBadge(', $template, $path);
            self::assertStringNotContainsString('jem-event-image-ribbon-wrap', $template, $path);
        }
    }

    public function testDayTimelineUsesEventThenVenueAndFallsBackToTheTitle(): void
    {
        foreach (array(
            'site/views/day/tmpl/timeline.php',
            'site/views/day/tmpl/responsive/timeline.php',
        ) as $path) {
            $template = $this->read($path);

            self::assertStringContainsString('event_status_indicator_image_available', $template, $path);
            self::assertStringContainsString('JemOutput::eventStatusImage(', $template, $path);
            self::assertStringContainsString("\$eventImage === ''", $template, $path);
            self::assertStringContainsString('JemOutput::eventStatusFallbackBadge($row)', $template, $path);
        }
    }

    public function testLanguageDescribesModuleAndViewScope(): void
    {
        $adminLanguage = $this->read('admin/language/en-GB/com_jem.ini');
        $siteLanguage = $this->read('site/language/en-GB/com_jem.ini');

        self::assertStringContainsString('COM_JEM_MODULE_STATUS_SETTINGS="Event status indicators"', $adminLanguage);
        self::assertStringContainsString('supported JEM modules and frontend views', $adminLanguage);
        self::assertStringContainsString('COM_JEM_SHOW_STATUS_INDICATORS="Show status indicators"', $adminLanguage);
        self::assertStringContainsString('COM_JEM_SHOW_STATUS_INDICATORS="Show status indicators"', $siteLanguage);
    }

    private function read(string $relativePath): string
    {
        return (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);
    }
}
