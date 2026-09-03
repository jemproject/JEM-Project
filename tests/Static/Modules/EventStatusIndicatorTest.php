<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventStatusIndicatorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function affectedModuleProvider(): iterable
    {
        yield 'basic' => array('mod_jem', 'MOD_JEM_SHOW_STATUS_INDICATORS');
        yield 'teaser' => array('mod_jem_teaser', 'MOD_JEM_TEASER_SHOW_STATUS_INDICATORS');
        yield 'banner' => array('mod_jem_banner', 'MOD_JEM_BANNER_SHOW_STATUS_INDICATORS');
        yield 'wide' => array('mod_jem_wide', 'MOD_JEM_WIDE_SHOW_STATUS_INDICATORS');
        yield 'jubilee' => array('mod_jem_jubilee', 'MOD_JEM_JUBILEE_SHOW_STATUS_INDICATORS');
    }

    #[DataProvider('affectedModuleProvider')]
    public function testAffectedModulesInheritEnabledStatusIndicators(string $module, string $languageKey): void
    {
        $helper = (string) file_get_contents(JEM_TEST_ROOT . '/modules/' . $module . '/helper.php');
        $entry = (string) file_get_contents(JEM_TEST_ROOT . '/modules/' . $module . '/' . $module . '.php');
        $manifestPath = JEM_TEST_ROOT . '/modules/' . $module . '/' . $module . '.xml';
        $manifest = (string) file_get_contents($manifestPath);
        $language = (string) file_get_contents(
            JEM_TEST_ROOT . '/modules/' . $module . '/language/en-GB/' . $module . '.ini'
        );
        $xml = simplexml_load_file($manifestPath);

        self::assertNotFalse($xml);
        $fields = $xml->xpath('//fieldset[@name="advanced"]/field[@name="show_status_indicators"]');
        self::assertIsArray($fields);
        self::assertCount(1, $fields);
        self::assertSame('1', (string) $fields[0]['default']);

        self::assertStringContainsString("get('show_status_indicators', 1)", $helper);
        self::assertStringContainsString('JemOutput::prepareModuleEventStatuses($events);', $helper);
        self::assertStringContainsString('$row->module_event_status ?? null', $helper);
        self::assertStringContainsString("get('show_status_indicators', 1)", $entry);
        self::assertStringContainsString("JemHelper::loadCss('jem-module-status');", $entry);
        self::assertStringContainsString('name="status_indicator_spacer_before"', $manifest);
        self::assertStringContainsString('name="status_indicator_spacer_after"', $manifest);
        self::assertLessThan(
            strpos($manifest, 'name="moduleclass_sfx"'),
            strpos($manifest, 'name="show_status_indicators"')
        );
        self::assertStringContainsString($languageKey . '="Show status indicators"', $language);
    }

    public function testStatusPriorityIsExclusiveAndDeterministic(): void
    {
        $output = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/output.class.php');
        $eventStatus = strpos($output, 'self::isModuleStatusActive($settings, $eventStatus[\'status\'])');
        $availability = strpos($output, '$availabilityStatuses = array(', (int) $eventStatus);
        $new = strpos($output, "self::isModuleStatusActive(\$settings, 'new')", (int) $availability);
        $open = strpos($output, "self::isModuleStatusActive(\$settings, 'open')", (int) $new);

        self::assertIsInt($eventStatus);
        self::assertIsInt($availability);
        self::assertIsInt($new);
        self::assertIsInt($open);
        self::assertLessThan($availability, $eventStatus);
        self::assertLessThan($new, $availability);
        self::assertLessThan($open, $new);
        self::assertStringContainsString("return self::getModuleStatusPresentation('last_places');", $output);
        self::assertStringContainsString("\$default = \$status === 'open' ? 0 : 1;", $output);
    }

    public function testImageLayoutsUseRibbonAndTitleFallback(): void
    {
        $layouts = array(
            '/modules/mod_jem_teaser/tmpl/default.php',
            '/modules/mod_jem_teaser/tmpl/responsive.php',
            '/modules/mod_jem_banner/tmpl/default.php',
            '/modules/mod_jem_banner/tmpl/responsive.php',
            '/modules/mod_jem_banner/tmpl/cards.php',
            '/modules/mod_jem_banner/tmpl/cards-places.php',
            '/modules/mod_jem_banner/tmpl/table-advanced.php',
            '/modules/mod_jem_wide/tmpl/default.php',
            '/modules/mod_jem_wide/tmpl/default_jem_eventslist.php',
            '/modules/mod_jem_jubilee/tmpl/default.php',
            '/modules/mod_jem_jubilee/tmpl/responsive.php',
        );

        foreach ($layouts as $layout) {
            $template = (string) file_get_contents(JEM_TEST_ROOT . $layout);

            self::assertStringContainsString('moduleEventStatusRibbon', $template, $layout);
            self::assertStringContainsString('moduleEventStatusBadge', $template, $layout);
        }
    }

    public function testTitleOnlyLayoutsUseTheStatusBadge(): void
    {
        $layouts = array(
            '/modules/mod_jem/tmpl/default.php',
            '/modules/mod_jem/tmpl/responsive.php',
            '/modules/mod_jem/tmpl/table.php',
            '/modules/mod_jem/tmpl/table-style.php',
            '/modules/mod_jem/tmpl/table-advanced.php',
            '/modules/mod_jem/tmpl/acymailing.php',
            '/modules/mod_jem_wide/tmpl/default_jem_eventslist_small.php',
        );

        foreach ($layouts as $layout) {
            $template = (string) file_get_contents(JEM_TEST_ROOT . $layout);

            self::assertStringContainsString('moduleEventStatusBadge', $template, $layout);
        }

        $wideDispatcher = (string) file_get_contents(
            JEM_TEST_ROOT . '/modules/mod_jem_wide/tmpl/responsive.php'
        );
        self::assertStringContainsString("default_jem_eventslist_small.php", $wideDispatcher);
        self::assertStringContainsString("default_jem_eventslist.php", $wideDispatcher);
    }

    public function testLegacyBlockImageContainersShrinkToTheRenderedImage(): void
    {
        $layouts = array(
            '/modules/mod_jem_banner/tmpl/default.php',
            '/modules/mod_jem_banner/tmpl/responsive.php',
            '/modules/mod_jem_jubilee/tmpl/default.php',
            '/modules/mod_jem_jubilee/tmpl/responsive.php',
        );

        foreach ($layouts as $layout) {
            $template = (string) file_get_contents(JEM_TEST_ROOT . $layout);

            self::assertStringContainsString(
                'jem-module-event-status-image jem-module-event-status-image--inline',
                $template,
                $layout
            );
        }

        $css = (string) file_get_contents(JEM_TEST_ROOT . '/media/css/jem-module-status.css');

        self::assertStringContainsString('width: fit-content;', $css);
        self::assertStringContainsString('align-self: flex-start;', $css);
    }

    public function testConfigurationAndStylesExposeTheSupportedPolicy(): void
    {
        $form = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/forms/settings.xml');
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/settings.php');
        $layout = (string) file_get_contents(
            JEM_TEST_ROOT . '/admin/views/settings/tmpl/default_basicmodulestatus.php'
        );
        $css = (string) file_get_contents(JEM_TEST_ROOT . '/media/css/jem-module-status.css');
        $installSql = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $updateSql = (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.1.0.sql');
        $installer = (string) file_get_contents(JEM_TEST_ROOT . '/script.php');

        foreach (array(
            'module_status_ribbons',
            'module_status_ribbon_position',
            'module_status_ribbon_side_margin',
            'module_status_last_places_threshold',
            'module_status_new_days',
            'module_status_color_cancelled_bg',
            'module_status_color_open_text',
        ) as $key) {
            self::assertStringContainsString('name="' . $key . '"', $form);
            self::assertStringContainsString("('" . $key . "',", $installSql);
            self::assertStringContainsString("('" . $key . "',", $updateSql);
        }

        $statusDefaults = array(
            'cancelled',
            'postponed',
            'rescheduled',
            'moved_online',
            'preorder',
            'soldout',
            'waitinglist',
            'last_places',
            'new',
        );

        foreach ($statusDefaults as $status) {
            $key = 'module_status_active_' . $status;

            self::assertMatchesRegularExpression(
                '/<field name="' . preg_quote($key, '/') . '"[^>]*default="1"/',
                $form
            );
            self::assertStringContainsString("'" . $key . "'", $model);
            self::assertStringContainsString("('" . $key . "', '1')", $installSql);
            self::assertStringContainsString("('" . $key . "', '1')", $updateSql);
            self::assertStringContainsString("'" . $key . "' => '1'", $installer);
        }

        self::assertMatchesRegularExpression(
            '/<field name="module_status_active_open"[^>]*default="0"/',
            $form
        );
        self::assertStringContainsString("'module_status_active_open'", $model);
        self::assertStringContainsString("('module_status_active_open', '0')", $installSql);
        self::assertStringContainsString("('module_status_active_open', '0')", $updateSql);
        self::assertStringContainsString("'module_status_active_open' => '0'", $installer);
        self::assertStringContainsString('$this->repairModuleStatusSettings();', $installer);

        self::assertStringContainsString("preg_match('/^#[0-9a-f]{8}$/i'", $model);
        self::assertStringNotContainsString('name="module_status_event"', $form);
        self::assertStringNotContainsString('name="module_status_availability"', $form);
        self::assertStringContainsString('COM_JEM_MODULE_STATUS_ACTIVE', $layout);
        self::assertStringContainsString("\$activeField = 'module_status_active_' . \$status;", $layout);
        self::assertStringContainsString('data-jem-module-status-preview', $layout);
        self::assertStringContainsString('font-weight: 700;', $css);
        self::assertStringContainsString('text-transform: uppercase;', $css);
        self::assertStringContainsString('jem-module-event-status-ribbon--diagonal-ascending', $css);
        self::assertStringContainsString('jem-module-event-status-ribbon--horizontal-center', $css);
    }
}
