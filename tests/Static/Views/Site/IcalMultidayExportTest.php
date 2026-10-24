<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class IcalMultidayExportTest extends TestCase
{
    public function testRawCalendarViewsInitialiseStateBeforeExportOverrides(): void
    {
        foreach (array('category', 'venue', 'calendar', 'weekcal', 'annualcalendar') as $view) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/view.raw.php');
            $initialise = strpos($source, '$model->getState();');
            $override = strpos($source, "\$model->setState('filter.calendar_multiday', false);");

            self::assertNotFalse($initialise, $view);
            self::assertNotFalse($override, $view);
            self::assertLessThan($override, $initialise, $view);
        }
    }

    public function testWeekModelDoesNotExpandRowsForIcal(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/weekcal.php');

        self::assertStringContainsString(
            "\$this->getState('filter.calendar_multiday', true) !== false",
            $source
        );
    }
}
