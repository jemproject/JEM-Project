<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Production table/helper/form and iCalcreator, with disposable SQLite and application boundaries only. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EventScheduleIntegrationTest extends TestCase
{
    private EventUtcOrderingDatabase $db;

    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php') || !extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT and pdo_sqlite; no installed site is modified.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $file = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/tests/Support/recurrence_source_stream.php';
        stream_wrapper_register('jemrecurrence', RecurrenceSourceStream::class);
        define('JPATH_SITE', 'jemrecurrence://site');
        define('JPATH_ROOT', $root);
        define('JPATH_BASE', $root);
        define('JPATH_ADMINISTRATOR', 'jemrecurrence://site/administrator');
        require_once JEM_TEST_ROOT . '/site/helpers/helper.php';
        require_once JEM_TEST_ROOT . '/admin/tables/event.php';
        require_once JEM_TEST_ROOT . '/site/classes/eventseries.class.php';
        require_once JEM_TEST_ROOT . '/tests/Support/event_utc_ordering_environment.php';
        $this->db = new EventUtcOrderingDatabase(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 't_']);
        $container = new \Joomla\DI\Container();
        $container->set('DatabaseDriver', $this->db, true);
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        Factory::$config = new Registry(['offset' => 'Europe/Madrid', 'sitename' => 'schedule.test']);
        $container->set('config', Factory::$config);
        Factory::$language = new class {
            public function getTag() { return 'en-GB'; }
            public function load(...$args) { return true; }
            public function _($key, ...$args) { return $key; }
        };
        $user = (new ReflectionClass(JemUser::class))->newInstanceWithoutConstructor();
        $user->id = 7;
        (new ReflectionProperty(JemUserAbstract::class, 'instances_jemuser'))->setValue(null, [7 => $user]);
        Factory::$application = new class ($user) {
            public \Joomla\Input\Input $input;
            public function __construct(private $user) { $this->input = new \Joomla\Input\Input([]); }
            public function getSession() { return $this; }
            public function get($key, $default = null) { return $key === 'user' ? $this->user : Factory::$config->get($key, $default); }
            public function isClient($name) { return $name === 'site'; }
            public function getConfig() { return Factory::$config; }
            public function getLanguage() { return Factory::$language; }
            public function getName() { return 'site'; }
            public function getDispatcher() { return new \Joomla\Event\Dispatcher(); }
        };
        $config = (new ReflectionClass(JemConfig::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(JemConfig::class, '_data'))->setValue($config, new Registry([
            'imageenabled' => 0, 'image_filetypes' => 'jpg,png', 'ical_tz' => 1,
            'globalattribs' => '{}', 'formattime' => 'H:i', 'formatdate' => 'Y-m-d', 'timename' => '',
        ]));
        (new ReflectionProperty(JemConfig::class, 'instance'))->setValue(null, $config);
        (new ReflectionProperty(\Joomla\CMS\Component\ComponentHelper::class, 'components'))->setValue(null, [
            'com_jem' => new \Joomla\CMS\Component\ComponentRecord(['params' => '{}']),
        ]);
        $this->db->setQuery('CREATE TABLE t_jem_venues (id INTEGER PRIMARY KEY, timezone TEXT)')->execute();
        $this->db->setQuery("INSERT INTO t_jem_venues VALUES (1, 'America/New_York')")->execute();
        $this->db->setQuery('CREATE TABLE t_jem_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT, dates TEXT, times TEXT, enddates TEXT, endtimes TEXT,
            start_utc TEXT, end_utc TEXT, timezone_mode TEXT, timezone TEXT, locid INTEGER DEFAULT 0,
            created TEXT, created_by INTEGER, modified TEXT, modified_by INTEGER, created_by_alias TEXT, language TEXT,
            datimage TEXT DEFAULT "", fullimage TEXT DEFAULT "", image_path TEXT DEFAULT "", attribs TEXT
        )')->execute();
    }

    private function programme(): array
    {
        return ['version' => 1, 'mode' => 'per_day', 'days' => [
            ['offset' => 0, 'closed' => true, 'all_day' => false, 'slots' => []],
            ['offset' => 1, 'closed' => false, 'all_day' => false, 'slots' => [
                ['start' => '09:00', 'end' => '12:00'], ['start' => '14:00', 'end' => '17:00']]],
            ['offset' => 2, 'closed' => false, 'all_day' => true, 'slots' => []],
        ]];
    }

    private function source(): array
    {
        return ['id' => 0, 'dates' => '2026-10-22', 'enddates' => '2026-10-24',
            'times' => '23:00:00', 'endtimes' => '01:00:00', 'timezone_mode' => 'joomla',
            'attribs' => ['event_schedule' => $this->programme(), 'other' => 'keep']];
    }

    private function store(array $data): object
    {
        $table = new JemTableEvent($this->db);
        self::assertTrue($table->bind($data));
        // SQLite treats id=0 literally; MySQL's AUTO_INCREMENT treats it as a new row.
        $table->id = null;
        $table->_autocreate = true;
        self::assertTrue($table->store(), (string) $table->getError());
        return $this->db->setQuery('SELECT * FROM t_jem_events WHERE id = ' . (int) $table->id)->loadObject();
    }

    public function testTableStoreReloadAndCopyPreserveProgrammeAndUtc(): void
    {
        $stored = $this->store($this->source());
        self::assertSame('2026-10-23 07:00:00', $stored->start_utc);
        self::assertSame('2026-10-24 21:59:59', $stored->end_utc);
        self::assertNull($stored->times, 'Closed first day must not inherit stale general hours.');
        self::assertSame('keep', json_decode($stored->attribs, true)['other']);
        $copy = (array) $stored;
        $copy['id'] = 0;
        JemEventSeriesSchedule::apply($copy, ['date' => '2026-10-29', 'end_date' => '2026-10-31', 'time' => '10:00', 'end_time' => '18:00']);
        $copied = $this->store($copy);
        self::assertSame('2026-10-30 08:00:00', $copied->start_utc);
        self::assertSame('2026-10-31 22:59:59', $copied->end_utc);
        self::assertEquals(JemEventSchedule::definition($stored), JemEventSchedule::definition($copied));
    }

    public function testDirectInvalidCopyFailsBeforeAnyWrite(): void
    {
        $source = $this->source();
        $source['enddates'] = '2026-10-25';
        $table = new JemTableEvent($this->db);
        self::assertTrue($table->bind($source));
        $this->db->queries = [];
        self::assertFalse($table->store());
        self::assertSame('COM_JEM_SCHEDULE_DAYS_MISMATCH', $table->getError());
        self::assertSame([], $this->db->queries);
    }

    public function testVenueAndInstallerUtcRefreshUseActiveSessions(): void
    {
        $source = $this->source();
        $source['timezone_mode'] = 'venue';
        $source['locid'] = 1;
        $stored = $this->store($source);
        JemHelper::refreshVenueEventUtcDates(1, 'Asia/Tokyo');
        self::assertSame('2026-10-23 00:00:00', $this->db->setQuery('SELECT start_utc FROM t_jem_events')->loadResult());
        require_once JEM_TEST_ROOT . '/script.php';
        (new ReflectionMethod(com_jemInstallerScript::class, 'rebuildEventUtcDates'))->invoke(new com_jemInstallerScript());
        self::assertSame($stored->start_utc, $this->db->setQuery('SELECT start_utc FROM t_jem_events')->loadResult());
    }

    public function testWeeklyGeneratorKeepsThreeDayDurationAndTableProgramme(): void
    {
        $source = $this->source();
        $next = JemHelper::calculate_recurrence(array_merge($source, ['recurrence_type' => 2,
            'recurrence_number' => 1, 'recurrence_byday' => 'W:4']));
        $copy = array_replace($source, ['dates' => $next['dates'], 'enddates' => $next['enddates']]);
        $stored = $this->store($copy);
        self::assertSame('2026-10-29', $stored->dates);
        self::assertSame('2026-10-31', $stored->enddates);
        self::assertSame('2026-10-30 08:00:00', $stored->start_utc);
    }

    public function testJoomlaFormsBindAndFilterWithoutDroppingJson(): void
    {
        require_once JEM_TEST_ROOT . '/admin/models/fields/eventschedule.php';
        Form::addFieldPath(JEM_TEST_ROOT . '/admin/models/fields');
        foreach (['admin', 'site'] as $client) {
            $xml = simplexml_load_file(JEM_TEST_ROOT . '/' . $client . '/models/forms/event.xml');
            $element = $xml->xpath('//field[@name="event_schedule"]')[0];
            $form = new Form('schedule.' . $client, ['control' => 'jform']);
            $form->setDatabase($this->db);
            $form->setCurrentUser($this->createMock(\Joomla\CMS\User\User::class));
            self::assertTrue($form->load('<form>' . $element->asXML() . '</form>'));
            $json = json_encode($this->programme());
            self::assertTrue($form->bind(['event_schedule' => $json]));
            self::assertInstanceOf(JFormFieldEventschedule::class, $form->getField('event_schedule'));
            self::assertSame($json, $form->filter(['event_schedule' => $json])['event_schedule']);
            self::assertArrayNotHasKey('event_schedule', $form->filter([]));
        }
    }

    private function export(object $event): string
    {
        require_once JEM_TEST_ROOT . '/tests/Support/event_schedule_export_environment.php';
        $_SERVER['HTTP_HOST'] = 'schedule.test';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_URI'] = '/';
        $event->id = 42;
        $event->slug = '42:programme';
        $event->title = 'Programme';
        $event->categories = [];
        $event->event_links = [(object) ['type' => 'information', 'url' => '']];
        $calendar = JemHelper::getCalendarTool();
        self::assertTrue(JemHelper::icalAddEvent($calendar, $event));
        return preg_replace('/\r?\n[ \t]/', '', $calendar->createCalendar());
    }

    public function testActualIcalendarSplitsSessionsAndKeepsStableUidsAndSource(): void
    {
        $event = (object) $this->source();
        $before = clone $event;
        $ics = $this->export($event);
        self::assertSame(3, substr_count($ics, 'BEGIN:VEVENT'));
        self::assertStringContainsString('UID:event42-session-1-0@schedule.test', $ics);
        self::assertStringContainsString('UID:event42-session-1-1@schedule.test', $ics);
        self::assertStringContainsString('UID:event42-session-2-0@schedule.test', $ics);
        self::assertStringContainsString('DTSTART:20261023T070000Z', $ics);
        self::assertStringContainsString('DTEND:20261023T100000Z', $ics);
        self::assertStringContainsString('DTSTART;VALUE=DATE:20261024', $ics);
        self::assertStringContainsString('DTEND;VALUE=DATE:20261025', $ics);
        self::assertSame($before->enddates, $event->enddates);
        self::assertSame($before->endtimes, $event->endtimes);
        preg_match_all('/UID:(event42[^\r\n]+)/', $this->export($event), $again);
        self::assertSame(['event42-session-1-0@schedule.test', 'event42-session-1-1@schedule.test',
            'event42-session-2-0@schedule.test'], $again[1]);
    }

    public function testContinuousIcalendarKeepsMidnightAndLegacyKeepsSingleExport(): void
    {
        $event = (object) $this->source();
        $event->times = '22:00:00';
        $event->endtimes = '00:00:00';
        $event->attribs = ['event_schedule' => ['version' => 1, 'mode' => 'continuous', 'days' => []]];
        $ics = $this->export($event);
        self::assertSame(1, substr_count($ics, 'BEGIN:VEVENT'));
        self::assertStringContainsString('DTEND:20261023T220000Z', $ics);
        self::assertStringContainsString('UID:event42@schedule.test', $ics);
        $event->attribs = [];
        $legacy = $this->export($event);
        self::assertSame(1, substr_count($legacy, 'BEGIN:VEVENT'));
        self::assertStringContainsString('DTEND:20261024T200000Z', $legacy);
    }

    public function testDailyIcalendarUsesEachSessionsDstOffsetAndSupportsFloat(): void
    {
        $event = (object) $this->source();
        $event->dates = '2026-10-24';
        $event->enddates = '2026-10-26';
        $event->times = '14:00:00';
        $event->endtimes = '16:00:00';
        $event->attribs = ['event_schedule' => ['version' => 1, 'mode' => 'daily', 'days' => []]];
        $ics = $this->export($event);
        self::assertSame(3, substr_count($ics, 'BEGIN:VEVENT'));
        self::assertStringContainsString('DTSTART:20261024T120000Z', $ics);
        self::assertStringContainsString('DTSTART:20261025T130000Z', $ics);
        self::assertStringContainsString('DTSTART:20261026T130000Z', $ics);
        self::assertStringContainsString('DTEND:20261024T140000Z', $ics);
        self::assertStringContainsString('DTEND:20261025T150000Z', $ics);
        self::assertStringNotContainsString('TZID', $ics);
        preg_match_all('/UID:(event42[^\r\n]+)/', $ics, $utcUids);

        JemHelper::config()->ical_tz = 0;
        $floating = $this->export($event);
        foreach (['20261024', '20261025', '20261026'] as $date) {
            self::assertStringContainsString('DTSTART:' . $date . "T140000\r\n", $floating);
            self::assertStringContainsString('DTEND:' . $date . "T160000\r\n", $floating);
        }
        self::assertStringNotContainsString('TZID', $floating);
        preg_match_all('/UID:(event42[^\r\n]+)/', $floating, $floatingUids);
        self::assertSame($utcUids[1], $floatingUids[1]);
    }

    public function testFieldHtmlEscapesJsonAndKeepsLegacyBlank(): void
    {
        require_once JEM_TEST_ROOT . '/admin/models/fields/eventschedule.php';
        \Joomla\CMS\HTML\HTMLHelper::register('script', static fn (...$args) => '');
        \Joomla\CMS\HTML\HTMLHelper::register('stylesheet', static fn (...$args) => '');
        Form::addFieldPath(JEM_TEST_ROOT . '/admin/models/fields');
        $form = new Form('schedule.markup', ['control' => 'jform']);
        $form->setDatabase($this->db);
        $form->setCurrentUser($this->createMock(\Joomla\CMS\User\User::class));
        $form->load('<form><field name="id" type="hidden"/><field name="event_schedule" type="eventschedule"/></form>');
        $form->bind(['id' => 42, 'event_schedule' => '']);
        self::assertStringContainsString('value=""', $form->getInput('event_schedule'));
        self::assertStringContainsString('COM_JEM_SCHEDULE_LEGACY', $form->getInput('event_schedule'));
        $form->bind(['event_schedule' => '{"unsafe":"<script>alert(1)</script>"}']);
        self::assertStringNotContainsString('<script>', $form->getInput('event_schedule'));
        self::assertStringContainsString('&lt;script&gt;', $form->getInput('event_schedule'));
    }

    public function testDailyUnknownEndExportDoesNotInventDuration(): void
    {
        $event = (object) $this->source();
        $event->times = '10:00:00';
        $event->endtimes = null;
        $event->attribs = ['event_schedule' => ['version' => 1, 'mode' => 'daily', 'days' => []]];
        $ics = $this->export($event);
        self::assertSame(3, substr_count($ics, 'BEGIN:VEVENT'));
        self::assertStringNotContainsString('DTEND', $ics);
        self::assertStringNotContainsString('DURATION', $ics);
        self::assertNull($event->endtimes);
    }

    public function testDetailFormattingShowsSessionsAndClosedDay(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/event_schedule_export_environment.php';
        require_once JEM_TEST_ROOT . '/site/classes/output.class.php';
        $event = (object) $this->source();
        JemEventSchedule::synchroniseEnvelope($event);
        self::assertNull($event->times);
        self::assertTrue(JemEventSchedule::hasTimes($event), 'Timezone remains visible when the first day is closed.');
        $html = JemOutput::formatEventSchedule($event);
        self::assertStringContainsString('data-schedule-day="2026-10-22"', $html);
        self::assertStringContainsString('COM_JEM_SCHEDULE_CLOSED', $html);
        self::assertStringContainsString('09:00', $html);
        self::assertStringContainsString('12:00', $html);
        self::assertStringContainsString('14:00', $html);
        self::assertStringContainsString('17:00', $html);
        self::assertStringContainsString('COM_JEM_SCHEDULE_ALL_DAY', $html);
        $event->attribs = [];
        self::assertSame(JemOutput::formatLongDateTime($event->dates, $event->times, $event->enddates, $event->endtimes),
            JemOutput::formatEventSchedule($event));
    }

    public function testPointProgrammeDoesNotExportInvalidEqualDtend(): void
    {
        $event = (object) $this->source();
        $event->times = '10:00:00';
        $event->endtimes = '10:00:00';
        $event->attribs = ['event_schedule' => ['version' => 1, 'mode' => 'daily', 'days' => []]];
        $ics = $this->export($event);
        self::assertSame(3, substr_count($ics, 'BEGIN:VEVENT'));
        self::assertStringNotContainsString('DTEND', $ics);
        self::assertStringNotContainsString('DURATION', $ics);
    }

    public function testSeriesPreflightRejectsInvalidLaterOccurrenceWithoutWrites(): void
    {
        require_once JEM_TEST_ROOT . '/admin/models/event.php';
        $model = (new ReflectionClass(JemModelEvent::class))->newInstanceWithoutConstructor();
        $validate = new ReflectionMethod(JemModelEvent::class, 'validateEventScheduleBatch');
        $first = $this->source();
        $later = array_replace($first, ['id' => 99, 'dates' => '2026-10-29', 'enddates' => '2026-10-31']);
        $this->db->queries = [];
        self::assertTrue($validate->invoke($model, [$first, $later]));
        $later['enddates'] = '2026-11-01';
        self::assertFalse($validate->invoke($model, [$first, $later]));
        self::assertSame('COM_JEM_SCHEDULE_DAYS_MISMATCH [ID:99]', $model->getError());
        self::assertSame([], $this->db->queries);

        $first['attribs'] = ['event_schedule' => ['version' => 1, 'mode' => 'daily', 'days' => []]];
        $first['times'] = '02:30:00';
        $first['endtimes'] = '03:30:00';
        $later = array_replace($first, ['id' => 100, 'dates' => '2026-03-29', 'enddates' => '2026-03-29']);
        self::assertFalse($validate->invoke($model, [$first, $later]));
        self::assertSame('COM_JEM_SCHEDULE_INVALID_LOCAL_TIME [ID:100]', $model->getError());
        self::assertSame([], $this->db->queries);
    }

    public function testAllRecurrenceModesPreserveCalendarDurationAcrossDst(): void
    {
        date_default_timezone_set('Europe/Madrid');
        foreach ([1, 2, 3, 4, 5, 6] as $type) {
            $row = ['dates' => '2026-03-28', 'enddates' => '2026-03-30',
                'recurrence_type' => $type, 'recurrence_number' => 1,
                'recurrence_byday' => $type === 4 ? 'MO' : '', 'recurrence_bylastday' => 'L1'];
            $next = JemHelper::calculate_recurrence($row);
            self::assertIsArray($next);
            self::assertSame(2, (int) JemEventSchedule::date($next['dates'])->diff(JemEventSchedule::date($next['enddates']))->days);
        }
    }

    public function testInstallerWidensParametersOnlyWhenRequired(): void
    {
        require_once JEM_TEST_ROOT . '/script.php';
        foreach (['varchar(5120)' => 1, 'text' => 1, 'mediumtext' => 0, 'longtext' => 0] as $type => $expected) {
            $db = new class ($type) {
                public array $queries = [];
                public function __construct(private string $type) {}
                public function getTableColumns($table) { return ['attribs' => $this->type]; }
                public function quoteName($value) { return '`' . $value . '`'; }
                public function setQuery($query) { $this->queries[] = $query; return $this; }
                public function execute() { return true; }
            };
            $container = new \Joomla\DI\Container();
            $container->set('DatabaseDriver', $db, true);
            (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
            (new ReflectionMethod(com_jemInstallerScript::class, 'ensureEventScheduleStorage'))->invoke(new com_jemInstallerScript());
            self::assertCount($expected, $db->queries);
            if ($expected) {
                self::assertSame('ALTER TABLE `#__jem_events` MODIFY `attribs` MEDIUMTEXT NULL DEFAULT NULL', $db->queries[0]);
            }
        }
        self::assertStringContainsString('`attribs` MEDIUMTEXT NULL DEFAULT NULL', file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql'));
        self::assertStringContainsString('MODIFY `attribs` MEDIUMTEXT NULL DEFAULT NULL', file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.1.0.sql'));
        $check = new \Joomla\CMS\Schema\ChangeItem\MysqlChangeItem($this->db, '5.1.0.sql',
            'ALTER TABLE `#__jem_events` MODIFY `attribs` MEDIUMTEXT NULL DEFAULT NULL;');
        self::assertSame('CHANGE_COLUMN_TYPE', $check->queryType);
        self::assertStringContainsString("field = 'attribs'", $check->checkQuery);
        self::assertStringContainsString('mediumtext', strtolower($check->checkQuery));
    }
}
