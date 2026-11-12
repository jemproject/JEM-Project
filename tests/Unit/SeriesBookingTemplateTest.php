<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Verify first-time participants can see the series option before submitting. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SeriesBookingTemplateTest extends TestCase
{
    /** @return iterable<string,array{string,int,bool,bool}> Layout, optional selection, full event and series enabled. */
    public static function cases(): iterable
    {
        foreach (['default_regform.php', 'responsive/default_regform.php'] as $layout) {
            foreach ([0, 1] as $single) {
                foreach ([false, true] as $full) {
                    yield $layout . '/' . $single . '/' . (int) $full => [$layout, $single, $full, true];
                }
            }
            yield $layout . '/individual only' => [$layout, 1, false, false];
        }
    }

    /**
     * @param string $layout Real registration layout.
     * @param int $single Whether an individual booking is allowed.
     * @param bool $full Whether the origin offers only waiting-list places.
     * @param bool $series Whether series booking is enabled.
     * @return void Check notices and usable positive-response choices, without any previous booking.
     */
    #[DataProvider('cases')]
    public function testSeriesChoiceAndNoticeBeforeFirstBooking(string $layout, int $single, bool $full, bool $series): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/cancellation_lookup_environment.php';
        require_once JEM_TEST_ROOT . '/tests/Support/series_booking_template_environment.php';
        $view = new SeriesBookingTemplateView((object) [
            'id' => 10, 'did' => 10, 'registra' => 1, 'maxbookeduser' => 5, 'minbookeduser' => 1,
            'maxplaces' => 20, 'reservedplaces' => 0, 'booked' => $full ? 20 : 0,
            'waitinglist' => 1, 'seriesbooking' => (int) $series, 'singlebooking' => $single,
            'recurrence_type' => 2, 'recurrence_first_id' => 0, 'series_id' => 0, 'requestanswer' => 0,
        ]);
        $html = $view->render($layout);
        self::assertSame($series, str_contains($html, 'COM_JEM_SERIES_BOOKING_SUSPENDED_NOTICE'));
        self::assertSame($series, str_contains($html, 'COM_JEM_SERIES_BOOKING_AGE_NOTICE'));
        self::assertSame($series && $single === 1, str_contains($html, 'id="jem_registration_series_1"'));
        self::assertSame($series && $single === 0, str_contains($html, 'COM_JEM_I_WILL_GO_SERIES_3'));
        self::assertStringContainsString('value="event.userregister"', $html);
    }
}
