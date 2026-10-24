<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Export checkout source using real Joomla libraries, without booting or changing a site. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class IcalendarTimezoneExportTest extends TestCase
{
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT; no installed site is modified.');
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
        require_once JEM_TEST_ROOT . '/tests/Support/ical_export_environment.php';

        $container = new \Joomla\DI\Container();
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        Factory::$config = new Registry(['offset' => 'Europe/Madrid', 'sitename' => 'ics.test']);
        $container->set('config', Factory::$config);
        Factory::$language = new class {
            public function getTag()
            {
                return 'en-GB';
            }
            public function load(...$args)
            {
                return true;
            }
            public function _($key, ...$args)
            {
                return $key;
            }
        };
        Factory::$application = new class {
            public function getConfig()
            {
                return Factory::$config;
            }
            public function isClient($name)
            {
                return $name === 'site';
            }
            public function getLanguage()
            {
                return Factory::$language;
            }
            public function getName()
            {
                return 'site';
            }
        };
        $config = (new ReflectionClass(JemConfig::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(JemConfig::class, '_data'))->setValue($config, new Registry([
            'ical_tz' => 1, 'globalattribs' => '{}',
        ]));
        (new ReflectionProperty(JemConfig::class, 'instance'))->setValue(null, $config);
        (new ReflectionProperty(\Joomla\CMS\Component\ComponentHelper::class, 'components'))->setValue(null, [
            'com_jem' => new \Joomla\CMS\Component\ComponentRecord(['params' => '{}']),
        ]);
        $_SERVER['HTTP_HOST'] = 'ics.test';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_URI'] = '/';
        // Ensure the machine's timezone cannot accidentally make the tests pass.
        date_default_timezone_set('Pacific/Honolulu');
    }

    private function event(array $overrides = []): object
    {
        return (object) array_replace([
            'id' => 130, 'slug' => '130:dst-test', 'title' => 'DST test',
            'categories' => [(object) ['catname' => 'Talks']],
            'event_links' => [(object) ['type' => 'information', 'url' => '']],
            'attribs' => '{}', 'dates' => '2026-11-07', 'enddates' => '2026-11-07',
            'times' => '14:00:00', 'endtimes' => '16:00:00',
            'timezone_mode' => 'custom', 'timezone' => 'Europe/Berlin',
        ], $overrides);
    }

    private function export(array $events, int $mode): string
    {
        JemHelper::config()->ical_tz = $mode;
        $calendar = JemHelper::getCalendarTool();
        foreach ($events as $event) {
            $before = clone $event;
            self::assertTrue(JemHelper::icalAddEvent($calendar, $event));
            self::assertEquals($before, $event, 'Export must not alter the source record.');
        }
        $ics = preg_replace('/\r\n[ \t]/', '', $calendar->createCalendar());
        self::assertStringNotContainsString('TZID', $ics);
        self::assertStringNotContainsString('VTIMEZONE', $ics);
        self::assertStringNotContainsString('X-WR-TIMEZONE', $ics);
        self::assertStringNotContainsString('ISLOCALTIME', $ics);
        self::assertStringNotContainsString('RRULE', $ics);
        self::assertStringContainsString("END:VCALENDAR\r\n", $ics);
        self::assertDoesNotMatchRegularExpression('/(?<!\r)\n/', $ics);
        return $ics;
    }

    public static function timedCases(): iterable
    {
        $cases = [
            'reported Berlin winter' => ['Europe/Berlin', '2026-11-07', '14:00:00', '2026-11-07', '16:00:00', '20261107T130000Z', '20261107T150000Z'],
            'Berlin summer' => ['Europe/Berlin', '2026-07-07', '14:00:00', '2026-07-07', '16:00:00', '20260707T120000Z', '20260707T140000Z'],
            'Berlin spring transition' => ['Europe/Berlin', '2026-03-29', '01:30:00', '2026-03-29', '03:30:00', '20260329T003000Z', '20260329T013000Z'],
            'Berlin autumn transition' => ['Europe/Berlin', '2026-10-25', '01:30:00', '2026-10-25', '03:30:00', '20261024T233000Z', '20261025T023000Z'],
            'multiday DST boundary' => ['Europe/Berlin', '2026-10-24', '10:00:00', '2026-10-26', '18:00:00', '20261024T080000Z', '20261026T170000Z'],
            'Lord Howe half-hour DST' => ['Australia/Lord_Howe', '2026-10-04', '01:30:00', '2026-10-04', '03:30:00', '20261003T150000Z', '20261003T163000Z'],
            'Kathmandu quarter-hour zone' => ['Asia/Kathmandu', '2026-11-07', '14:00:00', '2026-11-07', '16:00:00', '20261107T081500Z', '20261107T101500Z'],
            'New York autumn transition' => ['America/New_York', '2026-11-01', '00:30:00', '2026-11-01', '02:30:00', '20261101T043000Z', '20261101T073000Z'],
            'UTC source' => ['UTC', '2026-11-07', '14:00:00', '2026-11-07', '16:00:00', '20261107T140000Z', '20261107T160000Z'],
            'site timezone' => ['Europe/Madrid', '2026-11-07', '14:00:00', '2026-11-07', '16:00:00', '20261107T130000Z', '20261107T150000Z'],
        ];
        foreach ($cases as $name => $case) {
            yield $name . ' UTC' => [1, ...$case];
            yield $name . ' Float' => [0, ...$case];
        }
    }

    #[DataProvider('timedCases')]
    public function testTimedBoundaries(
        int $mode,
        string $zone,
        string $date,
        string $time,
        string $endDate,
        string $endTime,
        string $utcStart,
        string $utcEnd
    ): void {
        $ics = $this->export([$this->event([
            'timezone' => $zone, 'dates' => $date, 'times' => $time,
            'enddates' => $endDate, 'endtimes' => $endTime,
        ])], $mode);
        $useUtc = $mode === 1 || $zone !== 'Europe/Madrid';
        $start = $useUtc ? $utcStart : str_replace('-', '', $date) . 'T' . str_replace(':', '', $time);
        $end = $useUtc ? $utcEnd : str_replace('-', '', $endDate) . 'T' . str_replace(':', '', $endTime);
        self::assertStringContainsString("DTSTART:" . $start . "\r\n", $ics);
        self::assertStringContainsString("DTEND:" . $end . "\r\n", $ics);
        self::assertStringContainsString('UID:event130@ics.test', $ics);
        self::assertStringContainsString('SUMMARY:DST test', $ics);
        self::assertStringContainsString('CATEGORIES:Talks', $ics);
        if ($useUtc) {
            // Simulate the receiving calendar displaying UTC in the event's zone.
            foreach ([[$start, $date . ' ' . $time], [$end, $endDate . ' ' . $endTime]] as [$value, $expected]) {
                $parsed = DateTimeImmutable::createFromFormat('!Ymd\THis\Z', $value, new DateTimeZone('UTC'));
                self::assertInstanceOf(DateTimeImmutable::class, $parsed);
                self::assertSame($expected, $parsed->setTimezone(new DateTimeZone($zone))->format('Y-m-d H:i:s'));
            }
        }
    }

    public function testAllDayDatesAndExclusiveEndSurviveBothModesAndRepeatExport(): void
    {
        foreach ([0, 1] as $mode) {
            foreach ([null => '20261026', '2026-10-27' => '20261028'] as $endDate => $expected) {
                $event = $this->event(['dates' => '2026-10-25', 'enddates' => $endDate ?: null, 'times' => null, 'endtimes' => null]);
                foreach ([1, 2] as $attempt) {
                    $ics = $this->export([$event], $mode);
                    self::assertStringContainsString("DTSTART;VALUE=DATE:20261025\r\n", $ics);
                    self::assertStringContainsString("DTEND;VALUE=DATE:" . $expected . "\r\n", $ics);
                }
            }
        }
    }

    public function testMixedEventVenueAndJoomlaZonesAndFallbacks(): void
    {
        $events = [
            $this->event(),
            $this->event(['id' => 131, 'timezone_mode' => 'venue', 'venue_timezone' => 'America/New_York']),
            $this->event(['id' => 132, 'timezone_mode' => 'joomla']),
            $this->event(['id' => 133, 'timezone' => 'invalid/timezone']),
            $this->event(['id' => 134, 'timezone_mode' => 'venue', 'venue_timezone' => '']),
        ];
        $ics = $this->export($events, 1);
        self::assertSame(5, substr_count($ics, 'BEGIN:VEVENT'));
        self::assertSame(4, substr_count($ics, 'DTSTART:20261107T130000Z'));
        self::assertSame(1, substr_count($ics, 'DTSTART:20261107T190000Z'));
        self::assertSame(1, substr_count($ics, 'DTEND:20261107T210000Z'));
        $floating = $this->export($events, 0);
        self::assertSame(3, substr_count($floating, "DTSTART:20261107T140000\r\n"));
        self::assertSame(1, substr_count($floating, "DTSTART:20261107T130000Z\r\n"));
        self::assertSame(1, substr_count($floating, "DTSTART:20261107T190000Z\r\n"));
        Factory::$config->set('offset', 'invalid/timezone');
        self::assertStringContainsString('DTSTART:20261107T140000Z', $this->export([$events[2]], 1));
    }

    public function testOvernightAndMissingEndRemainValid(): void
    {
        $overnight = $this->event(['dates' => '2026-10-24', 'enddates' => null, 'times' => '23:30:00', 'endtimes' => '03:30:00']);
        $ics = $this->export([$overnight], 1);
        self::assertStringContainsString('DTSTART:20261024T213000Z', $ics);
        self::assertStringContainsString('DTEND:20261025T023000Z', $ics);
        $floating = $this->export([$overnight], 0);
        self::assertStringContainsString('DTEND:20261025T023000Z', $floating);
        $unknown = $this->export([$this->event(['enddates' => null, 'endtimes' => null])], 1);
        self::assertStringContainsString('DTSTART:20261107T130000Z', $unknown);
        self::assertStringNotContainsString('DTEND:', $unknown);
        $multiday = $this->export([$this->event(['enddates' => '2026-11-09', 'endtimes' => null])], 1);
        self::assertStringContainsString('DTEND:20261109T130000Z', $multiday);
    }

    public function testEventStatusIsExportedForCancelledAndPostponedEvents(): void
    {
        foreach ([0, 1] as $mode) {
            foreach ([
                'cancelled' => 'STATUS:CANCELLED',
                'postponed' => 'STATUS:TENTATIVE',
            ] as $status => $expected) {
                $ics = $this->export([$this->event(['event_status' => $status])], $mode);
                self::assertStringContainsString($expected . "\r\n", $ics);
            }

            foreach (['scheduled', 'rescheduled', 'moved_online'] as $status) {
                $ics = $this->export([$this->event(['event_status' => $status])], $mode);
                self::assertStringNotContainsString('STATUS:', $ics);
            }
        }
    }

    public function testOpenDateDoesNotCreateAnInvalidCalendarEvent(): void
    {
        $calendar = JemHelper::getCalendarTool();
        self::assertFalse(JemHelper::icalAddEvent($calendar, $this->event(['dates' => null])));
        self::assertStringNotContainsString('BEGIN:VEVENT', $calendar->createCalendar());
    }

    public function testDefaultOnlineMeetingExportUsesPortableDescription(): void
    {
        $ics = $this->export([$this->event([
            'online_meeting_url' => 'https://meet.example.org/room',
            'online_meeting_label' => 'Meeting room',
        ])], 1);

        self::assertStringContainsString('https://meet.example.org/room', $ics);
        self::assertStringContainsString('DESCRIPTION:', $ics);
        self::assertStringNotContainsString('CONFERENCE;', $ics);
        self::assertStringNotContainsString('X-ALT-DESC;', $ics);
        self::assertStringContainsString('DTSTART:20261107T130000Z', $ics);
    }

    public function testStructuredOnlineMeetingIsRetainedWhenTextFallbackIsDisabled(): void
    {
        JemHelper::globalattribs()->set('event_online_meeting_ics_description', 0);

        $ics = $this->export([$this->event([
            'online_meeting_url' => 'https://meet.example.org/room',
        ])], 1);

        self::assertStringContainsString('CONFERENCE;VALUE=URI;', $ics);
        self::assertStringContainsString('https://meet.example.org/room', $ics);
        self::assertStringNotContainsString('X-ALT-DESC;', $ics);
    }

    public function testOnlineMeetingOptOutOmitsTheConnectionLink(): void
    {
        JemHelper::globalattribs()->set('event_online_meeting_ics', 0);

        $ics = $this->export([$this->event([
            'online_meeting_url' => 'https://meet.example.org/private-room',
        ])], 1);

        self::assertStringNotContainsString('https://meet.example.org/private-room', $ics);
        self::assertStringNotContainsString('CONFERENCE;', $ics);
        self::assertStringNotContainsString('X-ALT-DESC;', $ics);
    }

    public function testTeamsLinkRetainsOutlookHintsAndPlainTextFallback(): void
    {
        $ics = $this->export([$this->event([
            'online_meeting_url' => 'https://teams.microsoft.com/l/meetup-join/example',
        ])], 1);

        self::assertStringContainsString('X-MICROSOFT-SKYPETEAMSMEETINGURL:', $ics);
        self::assertStringContainsString('https://teams.microsoft.com/l/meetup-join/example', $ics);
        self::assertStringNotContainsString('CONFERENCE;', $ics);
        self::assertStringNotContainsString('X-ALT-DESC;', $ics);
    }
}

