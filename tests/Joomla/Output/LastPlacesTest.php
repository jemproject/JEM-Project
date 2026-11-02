<?php

declare(strict_types=1);

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Native form/options plus real output and save-normalization blocks; no site or booking writes. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class LastPlacesTest extends TestCase
{
    /** @return void Load native libraries without bootstrapping an installed application. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT; no installed site is modified.');
        }
        require JEM_TEST_ROOT . '/tests/Support/last_places_environment.php';
    }

    /** @return iterable<string, array{array, array, string, ?string}> Label priority and display toggles. */
    public static function indicators(): iterable
    {
        yield 'manual external' => [['ticket_availability' => 'limited', 'test_registration_open' => false], [], 'limited', 'last_places'];
        yield 'automatic nine' => [['maxplaces' => 100, 'regCount' => 91], [], 'limited', 'last_places'];
        yield 'automatic ten' => [['maxplaces' => 100, 'regCount' => 90], [], 'instock', null];
        yield 'percentage below' => [['maxplaces' => 1000, 'regCount' => 901], ['module_status_last_places_mode' => 'percent'], 'limited', 'last_places'];
        yield 'percentage equal' => [['maxplaces' => 1000, 'regCount' => 900], ['module_status_last_places_mode' => 'percent'], 'instock', null];
        yield 'closed automatic' => [['maxplaces' => 100, 'regCount' => 91, 'test_registration_open' => false], [], 'instock', null];
        yield 'manual full' => [['ticket_availability' => 'limited', 'maxplaces' => 100, 'regCount' => 100], [], 'soldout', 'soldout'];
        yield 'manual full waiting' => [['ticket_availability' => 'limited', 'maxplaces' => 100, 'regCount' => 100, 'waitinglist' => 1], [], 'waitinglist', 'waitinglist'];
        yield 'full status hidden is never last places' => [['maxplaces' => 100, 'regCount' => 100], ['module_status_active_soldout' => 0], 'soldout', null];
        yield 'waiting hidden falls back soldout' => [['maxplaces' => 100, 'regCount' => 100, 'waitinglist' => 1], ['module_status_active_waitinglist' => 0], 'waitinglist', 'soldout'];
        yield 'both full indicators hidden' => [['maxplaces' => 100, 'regCount' => 100, 'waitinglist' => 1], ['module_status_active_waitinglist' => 0, 'module_status_active_soldout' => 0], 'waitinglist', null];
        yield 'indicator disabled manual' => [['ticket_availability' => 'limited'], ['module_status_active_last_places' => 0], 'limited', null];
        yield 'ribbons disabled preserve detail' => [['maxplaces' => 100, 'regCount' => 91], ['module_status_ribbons' => 0], 'limited', null];
        yield 'cancelled priority' => [['ticket_availability' => 'limited', 'event_status' => 'cancelled'], [], 'limited', 'cancelled'];
        yield 'preorder priority' => [['ticket_availability' => 'preorder', 'maxplaces' => 100, 'regCount' => 100], [], 'preorder', 'preorder'];
        yield 'soldout explicit' => [['ticket_availability' => 'soldout'], [], 'soldout', 'soldout'];
        yield 'unknown totals' => [['maxplaces' => 5], [], 'instock', null];
        yield 'unlimited' => [['maxplaces' => 0, 'regCount' => 2], [], 'instock', null];
    }

    /** @param array $data Event. @param array $settings Settings. @param string $state Effective state. @param string|null $indicator Ribbon. @return void */
    #[DataProvider('indicators')]
    public function testSharedOutput(array $data, array $settings, string $state, ?string $indicator): void
    {
        JemHelper::$settings = $settings;
        $event = (object) $data;
        self::assertSame($state, JemOutput::getEffectiveTicketAvailability($event));
        $presentation = JemOutput::getModuleEventStatus($event);
        self::assertSame($indicator, $presentation['status'] ?? null);
        if ($indicator === 'last_places') {
            self::assertSame('Last places', Text::_($presentation['label']));
        }
        $badges = JemOutput::eventStateBadges($event, false, true);
        self::assertSame($state === 'limited', str_contains($badges, 'jem-event-state-badge--last-places'));
        if ($state === 'limited') {
            self::assertStringContainsString('Last places', $badges);
        }
    }

    /** @return iterable<string, array{string}> Both event editors. */
    public static function sides(): iterable
    {
        yield 'backend' => ['admin'];
        yield 'frontend' => ['site'];
    }

    /** @param string $side Form side. @return void Native form options can retain manual limited. */
    #[DataProvider('sides')]
    public function testNativeEventSelector(string $side): void
    {
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/' . $side . '/models/forms/event.xml');
        $element = $xml->xpath('//field[@name="ticket_availability"]')[0];
        $field = new ListField(new Form('test.availability', ['control' => 'jform']));
        self::assertTrue($field->setup($element, 'limited'));
        self::assertSame('limited', $field->value);
        $options = $field->options;
        self::assertSame(['instock', 'limited', 'preorder', 'soldout'], array_column($options, 'value'));
        self::assertSame('Last places', $options[1]->text);
        self::assertSame('instock', (string) $element['default']);
    }

    /** @return void Both event save paths preserve limited and still reject unsupported stored states. */
    public function testSaveNormalization(): void
    {
        foreach (['event', 'jem_events'] as $table) {
            $source = file_get_contents(JEM_TEST_ROOT . '/admin/tables/' . $table . '.php');
            self::assertSame(1, preg_match('/(\$validTicketAvailabilities = .*?\n        \})/s', $source, $match));
            $check = eval('return function () {' . $match[1] . '};');
            foreach (['limited', 'instock', 'preorder', 'soldout', 'invalid', 'waitinglist'] as $value) {
                $record = new class {
                    public string $ticket_availability = '';
                };
                $record->ticket_availability = $value;
                $check->call($record);
                self::assertSame(in_array($value, ['invalid', 'waitinglist'], true) ? 'instock' : $value, $record->ticket_availability);
            }
        }
    }

    /** @return void Shared settings are exposed, normalized and seeded without changing existing choices. */
    public function testConfigurationWiring(): void
    {
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/admin/models/forms/settings.xml');
        $mode = $xml->xpath('//field[@name="module_status_last_places_mode"]')[0];
        self::assertSame('places', (string) $mode['default']);
        self::assertSame(['places', 'percent'], array_map(static fn ($option) => (string) $option['value'], $mode->xpath('option')));
        self::assertSame('', (string) $mode['showon']);
        $threshold = $xml->xpath('//field[@name="module_status_last_places_threshold"]')[0];
        self::assertSame('10', (string) $threshold['default']);
        self::assertSame('', (string) $threshold['showon'], 'Shared rule must remain editable when ribbons are hidden.');
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/models/settings.php');
        self::assertSame(1, preg_match('/(\$lastPlacesRule = .*?)\s*\$data\[\x27module_status_new_days\x27\]/s', $source, $match));
        $save = eval('return function ($data) {' . $match[1] . 'return $data;};');
        $actual = $save(['module_status_last_places_mode' => 'percent', 'module_status_last_places_threshold' => 250]);
        self::assertSame('percent', $actual['module_status_last_places_mode']);
        self::assertSame(100, $actual['module_status_last_places_threshold']);
        foreach (['admin/sql/install.mysql.utf8.sql', 'admin/sql/updates/mysql/5.1.0.sql'] as $file) {
            self::assertStringContainsString("('module_status_last_places_mode', 'places')", file_get_contents(JEM_TEST_ROOT . '/' . $file));
        }
        self::assertStringContainsString("'module_status_last_places_mode' => 'places'", file_get_contents(JEM_TEST_ROOT . '/script.php'));
        self::assertStringContainsString("'module_status_last_places_mode'", file_get_contents(JEM_TEST_ROOT . '/admin/views/settings/tmpl/default_basicmodulestatus.php'));
    }

    /** @return void All output mappings and the three original sample values support manual limited. */
    public function testPresentationAndSampleWiring(): void
    {
        foreach (['admin/views/events/tmpl/default.php', 'site/views/event/tmpl/default.php', 'site/views/event/tmpl/responsive/default.php'] as $file) {
            $source = file_get_contents(JEM_TEST_ROOT . '/' . $file);
            self::assertSame(1, preg_match('/\$ticketAvailabilityOptions = array\(.*?^\);/ms', $source, $match));
            $mapping = eval($match[0] . 'return $ticketAvailabilityOptions;');
            self::assertSame('Last places', Text::_($mapping['limited']['label']));
        }
        foreach (['jem.css', 'jem-responsive.css'] as $file) {
            self::assertStringContainsString('#jem .jem-event-state-badge--last-places,', file_get_contents(JEM_TEST_ROOT . '/media/css/' . $file));
        }
        $sample = file_get_contents(JEM_TEST_ROOT . '/admin/assets/sampledata.sql');
        self::assertSame(3, preg_match_all('/^\((19|21|24),[^\r\n]*\x27limited\x27/m', $sample));
        self::assertSame('limited', JemTicketAvailability::resolve((object) ['ticket_availability' => 'limited'], (object) [], false));
    }
}
