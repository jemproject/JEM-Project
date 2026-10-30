<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AttendeeStatusRecorder
{
    public static array $calls = [];

    public static function toggleAttendanceStatus($id, $status, $canChange): string
    {
        self::$calls[] = [$status, (bool) $canChange];
        return '';
    }
}

final class AttendeeStatusDisplayTest extends TestCase
{
    public static function registrations(): iterable
    {
        foreach ([-1, 0, 1] as $status) {
            foreach ([0, 1] as $waiting) {
                foreach ([0, 1] as $enabled) {
                    foreach ([false, true] as $canChange) {
                        foreach ([false, true] as $isPriced) {
                            yield [$status, $waiting, $enabled, $canChange, $isPriced];
                        }
                    }
                }
            }
        }
    }

    #[DataProvider('registrations')]
    public function testStoredStatusIsDisplayedWithoutEnablingNewActions(
        int $status,
        int $waiting,
        int $enabled,
        bool $canChange,
        bool $isPriced
    ): void {
        foreach ([
            'admin/views/attendees/tmpl/default.php',
            'site/views/attendees/tmpl/default.php',
            'site/views/attendees/tmpl/responsive/default.php',
        ] as $path) {
            $source = (string) file_get_contents(JEM_TEST_ROOT . '/' . $path);
            $start = strpos($source, '$status = (int)');
            self::assertNotFalse($start, $path);
            $end = strpos($source, '?>', $start);
            self::assertNotFalse($end, $path);
            $fragment = substr($source, $start, $end - $start);
            // Execute the actual layout decision; only the HTML renderer is a recorder.
            $fragment = str_replace('jemhtml::', 'AttendeeStatusRecorder::', $fragment);
            $view = new class {
                public object $event;
            };
            $view->event = (object) ['waitinglist' => $enabled];
            $run = function () use ($fragment, $status, $waiting, $canChange, $isPriced): void {
                $row = (object) ['id' => 10, 'status' => $status, 'waiting' => $waiting];
                $i = 0;
                eval($fragment);
            };
            AttendeeStatusRecorder::$calls = [];
            $run->call($view);
            $logicalStatus = $status === 1 && $waiting === 1 ? 2 : $status;
            $pricedReadOnly = $isPriced && str_contains($fragment, '!$isPriced');
            $expectedChange = (bool) $enabled
                && (str_starts_with($path, 'site/') || ($canChange && !$pricedReadOnly));
            self::assertSame([[$logicalStatus, $expectedChange]], AttendeeStatusRecorder::$calls, $path);
        }
    }
}
