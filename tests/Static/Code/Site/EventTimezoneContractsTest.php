<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventTimezoneContractsTest extends TestCase
{
    public function testEventSchemaStoresTimezoneAndCanonicalUtcBoundaries(): void
    {
        $installSql = $this->read('/admin/sql/install.mysql.utf8.sql');
        $updateSql = $this->read('/admin/sql/updates/mysql/5.0.1.sql');

        foreach (array($installSql, $updateSql) as $sql) {
            self::assertStringContainsString('`timezone_mode`', $sql);
            self::assertStringContainsString('`start_utc`', $sql);
            self::assertStringContainsString('`end_utc`', $sql);
        }

        self::assertStringContainsString("`timezone_mode` varchar(10) NOT NULL DEFAULT 'joomla'", $installSql);
        self::assertStringContainsString("('event_timezone_default', 'joomla')", $installSql);
    }

    public function testEventAndVenueFormsExposeTimezoneSources(): void
    {
        foreach (array('/admin/models/forms/event.xml', '/site/models/forms/event.xml') as $path) {
            $xml = $this->read($path);
            self::assertStringContainsString('name="timezone_mode"', $xml);
            self::assertStringContainsString('value="joomla"', $xml);
            self::assertStringContainsString('value="venue"', $xml);
            self::assertStringContainsString('value="custom"', $xml);
            self::assertStringContainsString('name="timezone" type="eventtimezone"', $xml);
            self::assertStringContainsString('default=""', $xml);
            self::assertStringNotContainsString('default="UTC"', $xml);
        }

        foreach (array('/admin/models/fields/eventtimezone.php', '/site/models/fields/eventtimezone.php') as $path) {
            $field = $this->read($path);
            self::assertStringContainsString('class JFormFieldEventTimezone extends TimezoneField', $field);
            self::assertStringContainsString("'select.option'", $field);
            self::assertStringContainsString("COM_JEM_EVENT_TIMEZONE_SELECT", $field);
            self::assertMatchesRegularExpression("/'select\\.option',\\s*''\\s*,/s", $field);
        }

        foreach (array('/admin/models/forms/venue.xml', '/site/models/forms/venue.xml') as $path) {
            self::assertStringContainsString('name="timezone" type="timezone"', $this->read($path));
        }
    }

    public function testSharedEventQueryUsesUtcPublicationHelper(): void
    {
        $model = $this->read('/site/models/eventslist.php');

        self::assertStringContainsString("JemHelper::getEventPublicationWhere('a', false)", $model);
        self::assertStringNotContainsString("new Date('now', \$app->get('offset')))->format(\$db->getDateFormat(), true)", $model);
    }

    public function testPublicAlternativeQueriesUsePublicationWindow(): void
    {
        foreach (array(
            '/site/models/search.php',
            '/site/models/categories.php',
            '/site/models/venues.php',
            '/site/helpers/map.php',
            '/modules/mod_jem_types/helper.php',
            '/plugins/plg_jem_mailer/mailer.php',
        ) as $path) {
            self::assertStringContainsString(
                'getEventPublicationWhere',
                $this->read($path),
                ltrim($path, '/') . ' must enforce the UTC publication window.'
            );
        }
    }

    public function testCurrentEventFiltersDoNotDependOnMysqlNow(): void
    {
        foreach (array(
            '/modules/mod_jem/helper.php',
            '/modules/mod_jem_wide/helper.php',
            '/modules/mod_jem_teaser/helper.php',
            '/modules/mod_jem_banner/helper.php',
            '/plugins/plg_content_jemlistevents/jemlistevents.php',
            '/plugins/plg_content_jemembed/jemembed.php',
        ) as $path) {
            $code = $this->read($path);
            self::assertStringContainsString('getEventDateTimeWhere', $code);
            self::assertStringNotContainsString('TIMESTAMPDIFF(MINUTE, NOW()', $code);
        }
    }

    public function testFinderUsesPublicationAndCanonicalUtcFields(): void
    {
        $finder = $this->read('/plugins/plg_finder_jem/jem.php');

        self::assertStringContainsString('a.publish_up AS publish_start_date', $finder);
        self::assertStringContainsString('a.publish_down AS publish_end_date', $finder);
        self::assertStringContainsString('a.start_utc AS start_date', $finder);
        self::assertStringContainsString('a.end_utc AS end_date', $finder);
    }

    public function testDirectAttachmentDownloadsRespectEventPublicationWindow(): void
    {
        $attachments = $this->read('/site/classes/attachment.class.php');

        self::assertStringContainsString("preg_match('/^event(\\d+)\$/i'", $attachments);
        self::assertMatchesRegularExpression('/JemHelper::isEventPublishedNow\(\$[a-zA-Z_][a-zA-Z0-9_]*\)/', $attachments);
    }

    public function testCalendarDefaultsUseJoomlaTimezone(): void
    {
        foreach (array(
            '/site/models/day.php',
            '/site/models/calendar.php',
            '/site/models/categorycal.php',
            '/site/models/venuecal.php',
            '/site/models/weekcal.php',
            '/site/models/annualcalendar.php',
            '/modules/mod_jem_cal/mod_jem_cal.php',
        ) as $path) {
            $code = $this->read($path);
            self::assertTrue(
                str_contains($code, 'getJoomlaDate') || str_contains($code, 'getJoomlaTimeZoneName'),
                ltrim($path, '/') . ' must derive its current date from Joomla timezone.'
            );
            self::assertStringNotContainsString('date_default_timezone_set', $code);
        }
    }

    public function testCleanupUsesJoomlaTimezoneAndPreservesArchiveDayBoundary(): void
    {
        $helper = $this->read('/site/helpers/helper.php');

        self::assertStringContainsString(
            '$cleanupTimeZone = new \\DateTimeZone(self::getJoomlaTimeZoneName());',
            $helper
        );
        self::assertStringContainsString(
            "->setTimezone(\$cleanupTimeZone)\n            ->format('Y-m-d')",
            $helper
        );
        self::assertStringNotContainsString("\$offset = idate('Z')", $helper);
        self::assertStringContainsString(
            "self::getJoomlaDate(-\$minusDays)) . ' >= (IF (enddates IS NOT NULL, enddates, dates))'",
            $helper
        );
    }

    public function testCalendarViewsPassTheSelectedCivilMonthToTheirModels(): void
    {
        $views = array(
            '/site/views/calendar/view.html.php' => 1,
            '/site/views/calendar/view.raw.php' => 2,
            '/site/views/category/view.html.php' => 1,
            '/site/views/category/view.raw.php' => 1,
            '/site/views/venue/view.html.php' => 1,
            '/site/views/venue/view.raw.php' => 2,
        );

        foreach ($views as $path => $expectedCalls) {
            $code = $this->read($path);

            self::assertSame(
                $expectedCalls,
                substr_count($code, "setDate(sprintf('%04d-%02d-01', \$year, \$month))"),
                ltrim($path, '/') . ' must pass the selected civil month without a PHP-timezone timestamp.'
            );
            self::assertStringNotContainsString('setDate(mktime(', $code);
        }
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
