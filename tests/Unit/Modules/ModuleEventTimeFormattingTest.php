<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Exercise actual module formatters, with output/service boundaries replaced by recording doubles. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ModuleEventTimeFormattingTest extends TestCase
{
    private string $serverTimezone;

    /** Load isolated dependencies without an installed Joomla site or database. */
    protected function setUp(): void
    {
        $this->serverTimezone = date_default_timezone_get();
        require_once JEM_TEST_ROOT . '/tests/Support/title_rendering_stubs.php';
    }

    /** Restore the process timezone even when an assertion fails. */
    protected function tearDown(): void
    {
        date_default_timezone_set($this->serverTimezone);
    }

    /**
     * Verify equivalent single-day storage and exact forwarding of both times and display options.
     *
     * @param string $module Module suffix.
     * @param string $zone PHP/server timezone.
     * @return void
     */
    #[DataProvider('moduleProvider')]
    public function testSingleDayEndTimeDoesNotDependOnAnExplicitEndDate(string $module, string $zone): void
    {
        date_default_timezone_set($zone);
        $today = new DateTimeImmutable('today');
        foreach ([-7, -1, 0, 1, 7] as $offset) {
            $date = $today->modify(sprintf('%+d days', $offset))->format('Y-m-d');
            foreach ([['10:00:00', '11:00:00'], ['00:00:00', '01:00:00'], ['22:00:00', '00:00:00']] as [$start, $end]) {
                foreach ([['H:i', true], ['g:i a', false], ['', false]] as [$format, $suffix]) {
                    $expected = null;
                    foreach ([null, '', $date] as $endDate) {
                        $row = (object) ['dates' => $date, 'enddates' => $endDate, 'times' => $start, 'endtimes' => $end];
                        $result = $this->render($module, $row, 1, $format, $suffix);
                        self::assertSame($start . '&nbsp;-&nbsp;' . $end, $result[1]);
                        self::assertSame([[$start, $format, $suffix], [$end, $format, $suffix]], JemOutput::$timeCalls);
                        if ($expected !== null) {
                            self::assertSame($expected, $result);
                        }
                        $expected = $result;
                    }
                }
            }
        }
    }

    /**
     * Preserve absent-time, relative, open-date and module-specific multi-day presentation.
     *
     * @param string $module Module suffix.
     * @param string $zone PHP/server timezone.
     * @return void
     */
    #[DataProvider('moduleProvider')]
    public function testOtherDateAndTimeModesKeepTheirExistingPolicy(string $module, string $zone): void
    {
        date_default_timezone_set($zone);
        $today = new DateTimeImmutable('today');
        $date = static fn(int $offset): string => $today->modify(sprintf('%+d days', $offset))->format('Y-m-d');
        $range = '10:00:00&nbsp;-&nbsp;11:00:00';
        foreach ([null, '', $date(0)] as $endDate) {
            foreach ([null, ''] as $endTime) {
                $row = (object) ['dates' => $date(0), 'enddates' => $endDate, 'times' => '10:00:00', 'endtimes' => $endTime];
                self::assertSame('10:00:00', $this->render($module, $row, 1)[1], 'Do not invent an end time');
                self::assertCount(1, JemOutput::$timeCalls);
                $row->times = null;
                self::assertSame('', $this->render($module, $row, 1)[1], 'No times stays empty');
                self::assertSame([], JemOutput::$timeCalls);
            }
        }
        foreach ([
            'future multi-day' => [2, 4, $module === 'teaser' ? '10:00:00' : $range],
            'ongoing multi-day' => [-2, 2, match ($module) {
                'teaser' => '10:00:00',
                'jubilee' => $range,
                default => '&nbsp;-&nbsp;11:00:00',
            }],
            'past multi-day' => [-7, -3, $module === 'jubilee' ? $range : '10:00:00'],
        ] as $label => [$startDay, $endDay, $expected]) {
            $row = (object) ['dates' => $date($startDay), 'enddates' => $date($endDay), 'times' => '10:00:00', 'endtimes' => '11:00:00'];
            self::assertSame($expected, $this->render($module, $row, 1)[1], $label);
        }
        $row = (object) ['dates' => $date(2), 'enddates' => $date(3), 'times' => '23:00:00', 'endtimes' => '01:00:00'];
        self::assertSame($module === 'teaser' ? '23:00:00' : '23:00:00&nbsp;-&nbsp;01:00:00', $this->render($module, $row, 1)[1]);
        foreach ([null, $date(7)] as $endDate) {
            $row = (object) ['dates' => $date(7), 'enddates' => $endDate, 'times' => '10:00:00', 'endtimes' => '11:00:00'];
            self::assertSame('10:00:00', $this->render($module, $row, 2)[1], 'Relative mode is not changed');
        }
        $row = (object) ['dates' => $date(-7), 'enddates' => $date(-7), 'times' => '10:00:00', 'endtimes' => '11:00:00'];
        self::assertSame($module === 'jubilee' ? '10:00:00' : '&nbsp;-&nbsp;11:00:00', $this->render($module, $row, 2)[1]);
        $row = (object) ['dates' => null, 'enddates' => null, 'times' => '10:00:00', 'endtimes' => '11:00:00'];
        foreach ([1, 2] as $method) {
            self::assertSame($range, $this->render($module, $row, $method)[1], 'Open-date policy stays unchanged');
        }
    }

    /**
     * Run the actual protected formatter and ensure that its input record is unchanged.
     *
     * @param string $module Module suffix.
     * @param object $row Stored date/time fields.
     * @param int $method Absolute (1) or relative (2) date mode.
     * @param string $format Time format to forward.
     * @param bool $suffix Whether to request the configured time suffix.
     * @return array Date and time display strings.
     */
    private function render(string $module, object $row, int $method, string $format = 'H:i', bool $suffix = false): array
    {
        require_once JEM_TEST_ROOT . '/modules/mod_jem_' . $module . '/helper.php';
        $before = (array) $row;
        JemOutput::$timeCalls = [];
        $formatter = new ReflectionMethod('ModJem' . ucfirst($module) . 'Helper', '_format_date_time');
        $result = $formatter->invoke(null, $row, $method, 'Y-m-d', $format, $suffix);
        self::assertSame($before, (array) $row, 'Rendering must not change the stored times');
        return $result;
    }

    /** @return iterable<string, array{string, string}> Affected modules and server timezones. */
    public static function moduleProvider(): iterable
    {
        foreach (['wide', 'teaser', 'banner', 'jubilee'] as $module) {
            foreach (['UTC', 'Europe/Madrid', 'Pacific/Honolulu', 'Asia/Kolkata'] as $zone) {
                yield $module . '/' . $zone => [$module, $zone];
            }
        }
    }
}

