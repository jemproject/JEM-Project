<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AnnualCalendarDurationMarkerContractsTest extends TestCase
{
    public function testScreenAndPdfUseTheDocumentedDurationBoundaries(): void
    {
        $template = (string) file_get_contents(
            JEM_TEST_ROOT . '/site/views/annualcalendar/tmpl/default.php'
        );
        $pdfView = (string) file_get_contents(
            JEM_TEST_ROOT . '/site/views/annualcalendar/view.raw.php'
        );

        self::assertStringContainsString('if ($durationDays === 1) {', $template);
        self::assertStringContainsString('if ($durationDays <= 3) {', $template);
        self::assertStringContainsString('if ($durationDays <= 7) {', $template);
        self::assertStringContainsString("return 'jem-annual-marker-one-day';", $template);
        self::assertStringContainsString("return 'jem-annual-marker-two-three-days';", $template);
        self::assertStringContainsString("return 'jem-annual-marker-four-seven-days';", $template);
        self::assertStringContainsString("return 'jem-annual-marker-eight-plus-days';", $template);
        self::assertStringNotContainsString('if ($durationDays <= 6) {', $template);
        self::assertStringContainsString('if ($durationDays === 1) {', $pdfView);
        self::assertStringContainsString('if ($durationDays <= 3) {', $pdfView);
        self::assertStringContainsString('if ($durationDays <= 7) {', $pdfView);
        self::assertStringNotContainsString('if ($durationDays <= 6) {', $pdfView);
    }

    public function testScreenAndPdfLegendsUseTheSameDurationRanges(): void
    {
        $siteLanguage = (string) file_get_contents(
            JEM_TEST_ROOT . '/site/language/en-GB/com_jem.ini'
        );
        $adminLanguage = (string) file_get_contents(
            JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini'
        );
        $pdfView = (string) file_get_contents(
            JEM_TEST_ROOT . '/site/views/annualcalendar/view.raw.php'
        );

        foreach (array($siteLanguage, $adminLanguage) as $language) {
            self::assertStringContainsString(
                'COM_JEM_ANNUALCALENDAR_EVENT_MARKER_FOUR_SEVEN_DAYS="4 to 7 day events"',
                $language
            );
            self::assertStringContainsString(
                'COM_JEM_ANNUALCALENDAR_EVENT_MARKER_EIGHT_PLUS_DAYS="8 or more day events"',
                $language
            );
        }

        foreach (array('ONE_DAY', 'TWO_THREE_DAYS', 'FOUR_SEVEN_DAYS', 'EIGHT_PLUS_DAYS') as $range) {
            self::assertStringContainsString(
                "Text::_('COM_JEM_ANNUALCALENDAR_EVENT_MARKER_{$range}')",
                $pdfView
            );
        }
        self::assertStringNotContainsString('4 to 6 day events', $pdfView);
        self::assertStringNotContainsString('7 or more day events', $pdfView);
    }
}
