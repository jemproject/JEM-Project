<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Production ICS parser, expander and preview; Joomla libraries only, no installed database. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class IcsRecurrenceImportTest extends TestCase
{
    private object $controller;

    /** @return void Load real checkout methods without booting the installed application. */
    protected function setUp(): void
    {
        if (!getenv('JEM_TEST_JOOMLA_ROOT')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to a local Joomla library installation.');
        }
        require JEM_TEST_ROOT . '/tests/Support/ics_import_environment.php';
        $this->controller = new class extends JemControllerImport {
            public function __construct() {}
            /** @return void Logging is outside this no-database parser fixture. */
            protected function addImportLogEntry($key, $message, $priority = \Joomla\CMS\Log\Log::INFO, $normaliseHeader = true) {}
        };
    }

    /** @param string $properties Event properties. @return string A complete VEVENT. */
    private function event(string $properties): string
    {
        return "BEGIN:VEVENT\r\nUID:jem-2516@example.test\r\nSUMMARY:Rehearsal\r\n" . str_replace("\n", "\r\n", $properties) . "\r\nEND:VEVENT\r\n";
    }

    /** @param string $events VEVENTs. @param array $range Optional window. @return array Real preview payload. */
    private function preview(string $events, array $range = []): array
    {
        $file = tempnam(sys_get_temp_dir(), 'ics2516');
        file_put_contents($file, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\n" . $events . "END:VCALENDAR\r\n");
        try {
            return (new ReflectionMethod($this->controller, 'buildExternalIcsPreview'))->invoke($this->controller,
                ['tmp_name' => $file], $range + ['mode' => 'standard', 'published' => 1, 'publish_up' => '',
                    'locid' => 0, 'type_id' => 0, 'language' => '*', 'catid' => 1,
                    'category_label' => 'Tests', 'type_label' => '', 'venue_label' => '', 'language_label' => 'All']);
        } finally {
            unlink($file);
        }
    }

    /** @return void RRULE/RDATE union, exceptions and duplicate additional dates. */
    public function testWeeklyRuleAndAdditionalDatesRespectExclusions(): void
    {
        $p = $this->preview($this->event("DTSTART;TZID=Europe/Amsterdam:20261203T200000\nDTEND;TZID=Europe/Amsterdam:20261203T220000\nRRULE:FREQ=WEEKLY;COUNT=4;BYDAY=TH\nEXDATE;TZID=Europe/Amsterdam:20261224T200000\nRDATE;TZID=Europe/Amsterdam:20261210T200000,20261230T200000"));
        self::assertSame(['2026-12-03', '2026-12-10', '2026-12-17', '2026-12-30'], array_column($p['records'], 1));
        self::assertSame(array_fill(0, 4, '19:00:00'), array_column($p['records'], 3));
        self::assertSame(array_fill(0, 4, '21:00:00'), array_column($p['records'], 4));
        self::assertSame(4, $p['valid_count']);
        self::assertSame(0, $p['error_count']);
    }

    /** @return iterable Recurrence calendar semantics, including real DST and leap-year boundaries. */
    public static function calendars(): iterable
    {
        // RFC 5545 section 3.8.5.3 examples, with finite counts for the test window.
        yield 'RFC alternate weeks Tuesday Thursday' => ["DTSTART:19970902T090000\nRRULE:FREQ=WEEKLY;INTERVAL=2;COUNT=8;WKST=SU;BYDAY=TU,TH", ['1997-09-02','1997-09-04','1997-09-16','1997-09-18','1997-09-30','1997-10-02','1997-10-14','1997-10-16'], array_fill(0, 8, '09:00:00')];
        yield 'RFC week starts Monday' => ["DTSTART:19970805T090000\nRRULE:FREQ=WEEKLY;INTERVAL=2;COUNT=4;BYDAY=TU,SU;WKST=MO", ['1997-08-05','1997-08-10','1997-08-19','1997-08-24'], array_fill(0, 4, '09:00:00')];
        yield 'RFC week starts Sunday' => ["DTSTART:19970805T090000\nRRULE:FREQ=WEEKLY;INTERVAL=2;COUNT=4;BYDAY=TU,SU;WKST=SU", ['1997-08-05','1997-08-17','1997-08-19','1997-08-31'], array_fill(0, 4, '09:00:00')];
        yield 'RFC first Friday' => ["DTSTART:19970905T090000\nRRULE:FREQ=MONTHLY;COUNT=10;BYDAY=1FR", ['1997-09-05','1997-10-03','1997-11-07','1997-12-05','1998-01-02','1998-02-06','1998-03-06','1998-04-03','1998-05-01','1998-06-05'], array_fill(0, 10, '09:00:00')];
        yield 'RFC twentieth Monday' => ["DTSTART:19970519T090000\nRRULE:FREQ=YEARLY;BYDAY=20MO;COUNT=3", ['1997-05-19','1998-05-18','1999-05-17'], array_fill(0, 3, '09:00:00')];
        yield 'RFC invalid February day skipped' => ["DTSTART:20070115T090000\nRRULE:FREQ=MONTHLY;BYMONTHDAY=15,30;COUNT=5", ['2007-01-15','2007-01-30','2007-02-15','2007-03-15','2007-03-30'], array_fill(0, 5, '09:00:00')];
        yield 'alternate days across month' => ["DTSTART:20260130T100000\nRRULE:FREQ=DAILY;INTERVAL=2;COUNT=3", ['2026-01-30','2026-02-01','2026-02-03'], array_fill(0, 3, '10:00:00')];
        yield 'negative month days' => ["DTSTART:20260131T100000\nRRULE:FREQ=MONTHLY;BYMONTHDAY=-1;COUNT=3", ['2026-01-31','2026-02-28','2026-03-31'], array_fill(0, 3, '10:00:00')];
        yield 'last Sunday alternate months' => ["DTSTART:20260125T100000\nRRULE:FREQ=MONTHLY;INTERVAL=2;BYDAY=-1SU;COUNT=3", ['2026-01-25','2026-03-29','2026-05-31'], array_fill(0, 3, '10:00:00')];
        yield 'year month weekday expansion' => ["DTSTART:20260105T100000\nRRULE:FREQ=YEARLY;BYMONTH=1,3;BYDAY=1MO;COUNT=4", ['2026-01-05','2026-03-02','2027-01-04','2027-03-01'], array_fill(0, 4, '10:00:00')];
        yield 'year days with negative index' => ["DTSTART:20260101T100000\nRRULE:FREQ=YEARLY;BYYEARDAY=1,100,-1;COUNT=4", ['2026-01-01','2026-04-10','2026-12-31','2027-01-01'], array_fill(0, 4, '10:00:00')];
        yield 'year day leap only' => ["DTSTART:20241231T100000\nRRULE:FREQ=YEARLY;BYYEARDAY=366;COUNT=2", ['2024-12-31','2028-12-31'], array_fill(0, 2, '10:00:00')];
        yield 'week one crossing calendar years' => ["DTSTART:20241230T100000\nRRULE:FREQ=YEARLY;BYWEEKNO=1;BYDAY=MO;COUNT=3", ['2024-12-30','2025-12-29','2027-01-04'], array_fill(0, 3, '10:00:00')];
        yield 'last week of year' => ["DTSTART:20261228T100000\nRRULE:FREQ=YEARLY;BYWEEKNO=-1;BYDAY=MO;COUNT=3", ['2026-12-28','2027-12-27','2028-12-25'], array_fill(0, 3, '10:00:00')];
        yield 'week one Sunday start' => ["DTSTART:20250101T100000\nRRULE:FREQ=YEARLY;BYWEEKNO=1;BYDAY=WE;WKST=SU;COUNT=3", ['2025-01-01','2026-01-07','2027-01-06'], array_fill(0, 3, '10:00:00')];
        yield 'daily time expansion sorted unique' => ["DTSTART:20260101T091500\nRRULE:FREQ=DAILY;BYHOUR=17,9,9;BYMINUTE=45,15;BYSECOND=0;COUNT=5", ['2026-01-01','2026-01-01','2026-01-01','2026-01-01','2026-01-02'], ['09:15:00','09:45:00','17:15:00','17:45:00','09:15:00']];
        yield 'setpos across hours not just days' => ["DTSTART:20260101T170000\nRRULE:FREQ=DAILY;BYHOUR=9,17;BYSETPOS=-1;COUNT=2", ['2026-01-01','2026-01-02'], ['17:00:00','17:00:00']];
        yield 'setpos duplicates removed' => ["DTSTART:20260101T100000\nRRULE:FREQ=DAILY;BYHOUR=10;BYSETPOS=1,-1;COUNT=2", ['2026-01-01','2026-01-02'], ['10:00:00','10:00:00']];
        yield 'all day ignores time selectors per RFC' => ["DTSTART;VALUE=DATE:20260101\nRRULE:FREQ=DAILY;BYHOUR=10;COUNT=2", ['2026-01-01','2026-01-02'], [null,null]];
        yield 'autumn ambiguous time uses first occurrence' => ["DTSTART;TZID=Europe/Madrid:20261018T023000\nRRULE:FREQ=WEEKLY;COUNT=3", ['2026-10-18','2026-10-25','2026-11-01'], ['00:30:00','00:30:00','01:30:00']];
        yield 'setpos follows invalid DST time removal' => ["DTSTART;TZID=Europe/Madrid:20260328T023000\nRRULE:FREQ=DAILY;BYHOUR=2,3;BYSETPOS=1;COUNT=3", ['2026-03-28','2026-03-29','2026-03-30'], ['01:30:00','01:30:00','00:30:00']];
        yield 'month selector only uses start day' => ["DTSTART:20260110T100000\nRRULE:FREQ=YEARLY;BYMONTH=1,3;COUNT=3", ['2026-01-10','2026-03-10','2027-01-10'], array_fill(0, 3, '10:00:00')];
        yield 'yearly month day expands all months' => ["DTSTART:20260110T100000\nRRULE:FREQ=YEARLY;BYMONTHDAY=10;COUNT=3", ['2026-01-10','2026-02-10','2026-03-10'], array_fill(0, 3, '10:00:00')];
        yield 'monthly day and weekday intersect' => ["DTSTART:20260105T100000\nRRULE:FREQ=MONTHLY;BYMONTHDAY=1,2,3,4,5,6,7;BYDAY=MO;COUNT=3", ['2026-01-05','2026-02-02','2026-03-02'], array_fill(0, 3, '10:00:00')];
        yield 'UNTIL cuts after full setpos period' => ["DTSTART:20260130T100000\nRRULE:FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1;UNTIL=20260215T100000", ['2026-01-30'], ['10:00:00']];

        yield 'epoch all day' => ["DTSTART;VALUE=DATE:19700101\nDURATION:P2D", ['1970-01-01'], [null]];
        yield 'ordinary' => ["DTSTART:20261203T200000\nDTEND:20261203T220000", ['2026-12-03'], ['20:00:00']];
        yield 'rdate only' => ["DTSTART:20261203T200000\nRDATE:20261205T200000,20261206T200000", ['2026-12-03','2026-12-05','2026-12-06'], array_fill(0, 3, '20:00:00')];
        yield 'remove initial' => ["DTSTART:20261203T200000\nEXDATE:20261203T200000", [], []];
        yield 'folded rule' => ["DTSTART:20261203T200000\nRRULE:FREQ=WEEKLY;\n COUNT=2", ['2026-12-03','2026-12-10'], ['20:00:00','20:00:00']];
        yield 'last weekday' => ["DTSTART:20260130T100000\nRRULE:FREQ=MONTHLY;COUNT=3;BYDAY=MO,TU,WE,TH,FR;BYSETPOS=-1", ['2026-01-30','2026-02-27','2026-03-31'], array_fill(0, 3, '10:00:00')];
        yield 'missing month day' => ["DTSTART:20260131T100000\nRRULE:FREQ=MONTHLY;COUNT=3", ['2026-01-31','2026-03-31','2026-05-31'], array_fill(0, 3, '10:00:00')];
        yield 'leap day' => ["DTSTART;VALUE=DATE:20240229\nRRULE:FREQ=YEARLY;COUNT=3", ['2024-02-29','2028-02-29','2032-02-29'], [null,null,null]];
        yield 'spring clock' => ["DTSTART;TZID=Europe/Madrid:20260322T100000\nRRULE:FREQ=WEEKLY;COUNT=3", ['2026-03-22','2026-03-29','2026-04-05'], ['09:00:00','08:00:00','08:00:00']];
        yield 'skip missing hour without consuming count' => ["DTSTART;TZID=Europe/Madrid:20260322T023000\nRRULE:FREQ=WEEKLY;COUNT=3", ['2026-03-22','2026-04-05','2026-04-12'], ['01:30:00','00:30:00','00:30:00']];
        yield 'until inclusive UTC' => ["DTSTART;TZID=Europe/Madrid:20260322T100000\nRRULE:FREQ=WEEKLY;UNTIL=20260405T080000Z", ['2026-03-22','2026-03-29','2026-04-05'], ['09:00:00','08:00:00','08:00:00']];
        yield 'cancelled master' => ["DTSTART:20261203T200000\nRRULE:FREQ=WEEKLY;COUNT=3\nSTATUS:CANCELLED", [], []];
    }

    /** @param string $source Source properties. @param array $dates Expected dates. @param array $times Expected UTC times. @return void */
    #[DataProvider('calendars')]
    public function testCalendarExpansion(string $source, array $dates, array $times): void
    {
        $p = $this->preview($this->event($source));
        self::assertSame($dates, array_column($p['records'], 1));
        self::assertSame($times, array_column($p['records'], 3));
    }

    /** @return void Moved occurrences inherit master data and replace their original date; cancelled ones disappear. */
    public function testMovedAndCancelledExceptions(): void
    {
        $master = $this->event("DTSTART:20261203T200000\nDTEND:20261203T220000\nRRULE:FREQ=WEEKLY;COUNT=4");
        $moved = str_replace("SUMMARY:Rehearsal\r\n", '', $this->event("RECURRENCE-ID:20261210T200000\nDTSTART:20261211T190000"));
        $cancelled = $this->event("RECURRENCE-ID:20261217T200000\nSTATUS:CANCELLED");
        $p = $this->preview($moved . $master . $cancelled);
        self::assertCount(3, $p['records']);
        $byDate = array_column($p['records'], null, 1);
        self::assertArrayNotHasKey('2026-12-10', $byDate);
        self::assertArrayNotHasKey('2026-12-17', $byDate);
        self::assertSame('Rehearsal', $byDate['2026-12-11'][0]);
        self::assertSame('21:00:00', $byDate['2026-12-11'][4]);
        $identity = json_decode($byDate['2026-12-11'][7], true)['jem_ics_uid_hash'];
        self::assertSame(hash('sha256', "jem-2516@example.test\n20261210T200000Z"), $identity);
    }

    /** @return void Range selection uses resulting dates, including moved instances. */
    public function testExplicitRangeForInfiniteRuleAndMovedInstance(): void
    {
        $master = $this->event("DTSTART:20261203T200000\nRRULE:FREQ=WEEKLY");
        $moved = $this->event("RECURRENCE-ID:20261217T200000\nDTSTART:20261210T190000");
        $p = $this->preview($master . $moved, ['ics_from'=>'2026-12-10','ics_to'=>'2026-12-10']);
        self::assertCount(2, $p['records']);
        self::assertSame(['2026-12-10','2026-12-10'], array_column($p['records'], 1));
        self::assertSame('2026-12-10', $p['ics_from']);
    }

    /** @return void All-day DTEND remains exclusive in ICS and inclusive in JEM; DURATION and PERIOD are honoured. */
    public function testDurationAndAllDayEnd(): void
    {
        $p = $this->preview($this->event("DTSTART;VALUE=DATE:20261203\nDURATION:P2D\nRRULE:FREQ=WEEKLY;COUNT=2"));
        self::assertSame(['2026-12-04','2026-12-11'], array_column($p['records'], 2));
        self::assertSame([null,null], array_column($p['records'], 3));
        $epoch = $this->preview($this->event("DTSTART;VALUE=DATE:19700101\nDURATION:P2D"));
        self::assertSame('1970-01-02', $epoch['records'][0][2]);
        $p = $this->preview($this->event("DTSTART:20261203T200000\nDURATION:PT2H\nRDATE;VALUE=PERIOD:20261205T100000/PT3H"));
        self::assertSame(['22:00:00','13:00:00'], array_column($p['records'], 4));
    }

    /** @return void Duplicate protection sees one distinct stable identity per date, including on reimport. */
    public function testStableIdentityAndExistingDeduplication(): void
    {
        $source = $this->event("DTSTART:20261203T200000\nRRULE:FREQ=WEEKLY;COUNT=3");
        $p = $this->preview($source);
        $known = []; $skipped = 0;
        $method = new ReflectionMethod($this->controller, 'filterImportedIcsRecords');
        $args = [$p['records'], &$known, &$skipped];
        self::assertCount(3, $method->invokeArgs($this->controller, $args));
        self::assertCount(3, $known);
        self::assertArrayHasKey(hash('sha256', "jem-2516@example.test\n"), $known);
        self::assertSame([], $method->invokeArgs($this->controller, $args));
        self::assertSame(3, $skipped);
    }

    /** @return void An alarm's properties never replace the event description. */
    public function testAlarmIsolationAndDetachedInstances(): void
    {
        $p = $this->preview($this->event("DTSTART:20261203T200000\nDESCRIPTION:Event text\nBEGIN:VALARM\nDESCRIPTION:Alarm text\nEND:VALARM"));
        self::assertSame('Event text', $p['records'][0][5]);
        $p = $this->preview($this->event("DTSTART:20261203T200000\nRECURRENCE-ID:20261203T190000") .
            $this->event("DTSTART:20261210T200000\nRECURRENCE-ID:20261210T190000"));
        self::assertCount(2, $p['records']);
    }

    /** @return void The published usage example imports exactly the documented five events. */
    public function testDocumentationExample(): void
    {
        $ics = file_get_contents(JEM_TEST_ROOT . '/tests/Fixtures/ics/third-party-calendar.ics');
        $ics = preg_replace('/^BEGIN:VCALENDAR\\r?\\n|^VERSION:2.0\\r?\\n|^PRODID:.*\\r?\\n|^CALSCALE:.*\\r?\\n|^END:VCALENDAR\\r?\\n/m', '', $ics);
        $p = $this->preview($ics);
        self::assertSame(5, $p['valid_count']);
        $rows = array_column($p['records'], null, 1);
        $dates = array_keys($rows); sort($dates);
        self::assertSame(['2026-12-03','2026-12-05','2026-12-11','2026-12-17','2026-12-30'], $dates);
        self::assertSame('Rehearsal moved to Friday', $rows['2026-12-11'][0]);
        self::assertSame('18:00:00', $rows['2026-12-11'][3]);
        self::assertNull($rows['2026-12-05'][3]);
    }

    /** @return iterable Inputs that must fail before producing a partial import. */
    public static function rejectedCalendars(): iterable
    {
        foreach (['INTERVAL=0', 'INTERVAL=-1', 'INTERVAL=1x', 'BYMONTH=13', 'BYMONTHDAY=0',
            'BYMONTHDAY=32', 'BYHOUR=24', 'BYMINUTE=60', 'BYSECOND=60', 'BYDAY=0MO', 'BYDAY=54MO',
            'BYDAY=1MO', 'BYWEEKNO=1', 'BYYEARDAY=1', 'BYSETPOS=1', 'WKST=XX', 'BYDAY='] as $part) {
            yield 'invalid daily ' . $part => ["DTSTART:20260101T100000\nRRULE:FREQ=DAILY;COUNT=2;" . $part, [], 'UNSUPPORTED'];
        }
        yield 'weekly monthday forbidden' => ["DTSTART:20260101T100000\nRRULE:FREQ=WEEKLY;BYMONTHDAY=1;COUNT=2", [], 'UNSUPPORTED'];
        yield 'year weekno ordinal weekday forbidden' => ["DTSTART:20260101T100000\nRRULE:FREQ=YEARLY;BYWEEKNO=1;BYDAY=1TH;COUNT=2", [], 'UNSUPPORTED'];
        yield 'UTC start needs UTC until' => ["DTSTART:20260101T100000Z\nRRULE:FREQ=DAILY;UNTIL=20260103T100000", [], 'INVALID_DATE'];
        yield 'TZID start needs UTC until' => ["DTSTART;TZID=Europe/Madrid:20260101T100000\nRRULE:FREQ=DAILY;UNTIL=20260103T100000", [], 'INVALID_DATE'];
        yield 'duplicate rule part' => ["DTSTART:20260101T100000\nRRULE:FREQ=DAILY;COUNT=2;COUNT=3", [], 'UNSUPPORTED'];
        yield 'bounded unreachable count' => ["DTSTART:20260101T100000\nRRULE:FREQ=YEARLY;INTERVAL=200;COUNT=2", [], 'LIMIT'];
        yield 'infinite' => ["DTSTART:20261203T200000\nRRULE:FREQ=WEEKLY", [], 'RANGE_REQUIRED'];
        yield 'subdaily' => ["DTSTART:20261203T200000\nRRULE:FREQ=HOURLY;COUNT=4", [], 'UNSUPPORTED'];
        yield 'both count until' => ["DTSTART:20261203T200000\nRRULE:FREQ=DAILY;COUNT=2;UNTIL=20261205T200000", [], 'UNSUPPORTED'];
        yield 'exrule' => ["DTSTART:20261203T200000\nEXRULE:FREQ=DAILY", [], 'UNSUPPORTED'];
        yield 'range exception' => ["DTSTART:20261203T200000\nRECURRENCE-ID;RANGE=THISANDFUTURE:20261203T200000", [], 'UNSUPPORTED'];
        yield 'invalid date' => ["DTSTART:20260230T200000", [], 'INVALID_DATE'];
        yield 'reversed range' => ["DTSTART:20261203T200000", ['ics_from'=>'2026-12-12','ics_to'=>'2026-12-01'], 'RANGE_INVALID'];
        yield 'range too broad' => ["DTSTART:20261203T200000", ['ics_from'=>'2020-01-01','ics_to'=>'2040-01-01'], 'RANGE_INVALID'];
        yield 'count cap' => ["DTSTART:20261203T200000\nRRULE:FREQ=DAILY;COUNT=50001", [], 'LIMIT'];
        yield 'no matching instance' => ["DTSTART:20261203T200000\nRRULE:FREQ=WEEKLY;UNTIL=20261204T200000;BYDAY=MO", [], 'START_MISMATCH'];
        yield 'wrong weekday' => ["DTSTART:20261203T200000\nRRULE:FREQ=WEEKLY;COUNT=2;BYDAY=MO", [], 'START_MISMATCH'];
        yield 'duration and end' => ["DTSTART:20261203T200000\nDTEND:20261203T220000\nDURATION:PT2H", [], 'INVALID_DATE'];
        yield 'unknown rule' => ["DTSTART:20261203T200000\nRRULE:FREQ=WEEKLY;COUNT=2;WRONG=1", [], 'UNSUPPORTED'];
    }

    /** @return void Candidate limits apply before BYSETPOS can hide excessive expansion. */
    public function testBoundsLargeCartesianExpansion(): void
    {
        $rule = 'FREQ=YEARLY;COUNT=2;BYDAY=MO,TU,WE,TH,FR,SA,SU;BYHOUR='
            . implode(',', range(0, 23)) . ';BYMINUTE=' . implode(',', range(0, 59))
            . ';BYSECOND=' . implode(',', range(0, 59)) . ';BYSETPOS=1';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('COM_JEM_IMPORT_ICS_LIMIT');
        $this->preview($this->event("DTSTART:20260101T000000\nRRULE:" . $rule));
    }

    /** @return void Administrator-facing errors come from Joomla INI overrides, not engine dictionaries. */
    public function testJoomlaLanguageOverrideIsUsedForErrors(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'jem-ics-language');
        try {
            file_put_contents($file, 'COM_JEM_IMPORT_ICS_UNSUPPORTED="Regla no compatible"');
            $language = \Joomla\CMS\Factory::$language;
            self::assertTrue((new ReflectionMethod($language, 'loadLanguage'))->invoke($language, $file));
        } finally {
            unlink($file);
        }
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Regla no compatible');
        $this->preview($this->event("DTSTART:20260101T100000\nRRULE:FREQ=DAILY;COUNT=2;BYMONTH=13"));
    }

    /** @param string $source Source properties. @param array $range Window. @param string $error Expected key suffix. @return void */
    #[DataProvider('rejectedCalendars')]
    public function testRejectsUnsupportedOrUnsafeInput(string $source, array $range, string $error): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('COM_JEM_IMPORT_ICS_' . $error);
        $this->preview($this->event($source), $range);
    }
}
