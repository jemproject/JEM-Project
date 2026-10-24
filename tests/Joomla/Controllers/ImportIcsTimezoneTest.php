<?php

declare(strict_types=1);

use Joomla\CMS\Factory;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

final class ImportIcsTimezoneTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        self::bootJoomlaSite();

        if (!defined('JPATH_COMPONENT_ADMINISTRATOR')) {
            define('JPATH_COMPONENT_ADMINISTRATOR', JEM_TEST_ROOT . '/admin');
        }

        require_once JEM_TEST_ROOT . '/admin/controllers/import.php';
    }

    public function testUtcAndForeignTzidAreConvertedToSiteWallTime(): void
    {
        $app = Factory::getApplication();
        $originalOffset = $app->get('offset', 'UTC');
        $app->set('offset', 'Europe/Amsterdam');

        try {
            $controller = (new ReflectionClass(JemControllerImport::class))->newInstanceWithoutConstructor();
            $method = new ReflectionMethod(JemControllerImport::class, 'normaliseExternalIcsDateProperty');

            $summer = $method->invoke($controller, array('raw' => '20260715T180000Z', 'params' => array()));
            self::assertSame('2026-07-15', $summer['date']);
            self::assertSame('20:00:00', $summer['time']);

            $winter = $method->invoke($controller, array(
                'raw' => '20261120T180000',
                'params' => array('TZID' => 'America/New_York'),
            ));
            self::assertSame('2026-11-21', $winter['date']);
            self::assertSame('00:00:00', $winter['time']);

            $mozilla = $method->invoke($controller, array(
                'raw' => '20261120T200000',
                'params' => array('TZID' => '/mozilla.org/20050126_1/America/Argentina/Buenos_Aires'),
            ));
            self::assertSame('2026-11-21', $mozilla['date']);
            self::assertSame('00:00:00', $mozilla['time']);

            if (class_exists(IntlTimeZone::class)) {
                $windows = $method->invoke($controller, array(
                    'raw' => '20261120T190000',
                    'params' => array('TZID' => '"W. Europe Standard Time"'),
                ));
                self::assertSame('2026-11-20', $windows['date']);
                self::assertSame('19:00:00', $windows['time']);
                self::assertFalse($windows['error']);
            }

            $floating = $method->invoke($controller, array('raw' => '20260715T180000', 'params' => array()));
            self::assertSame('18:00:00', $floating['time']);
            self::assertFalse($floating['converted']);

            $invalidZone = $method->invoke($controller, array(
                'raw' => '20260715T180000',
                'params' => array('TZID' => 'Not/A_Timezone'),
            ));
            self::assertTrue($invalidZone['error']);
        } finally {
            $app->set('offset', $originalOffset);
        }
    }

    public function testCatalogXmlEventFieldsAreNotPrefixed(): void
    {
        $controller = (new ReflectionClass(JemControllerImport::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(JemControllerImport::class, 'extractExternalXmlRecords');
        $xml = simplexml_load_string('<jem-events><event><title>Example</title><dates>2026-10-21</dates></event></jem-events>');

        self::assertNotFalse($xml);
        self::assertSame(
            array(array('title' => 'Example', 'dates' => '2026-10-21')),
            $method->invoke($controller, $xml)
        );
    }

    public function testIcsUidIsPersistedAsAStableHashAndDuplicateRowsAreSkipped(): void
    {
        $controller = (new ReflectionClass(JemControllerImport::class))->newInstanceWithoutConstructor();
        $normalise = new ReflectionMethod(JemControllerImport::class, 'normaliseExternalIcsEvent');
        $event = array(
            'SUMMARY' => array(array('value' => 'Test', 'raw' => 'Test', 'params' => array())),
            'DTSTART' => array(array('value' => '20261021T100000', 'raw' => '20261021T100000', 'params' => array())),
            'DTEND' => array(array('value' => '20261021T110000', 'raw' => '20261021T110000', 'params' => array())),
            'UID' => array(array('value' => 'test-123@example.org', 'raw' => 'test-123@example.org', 'params' => array())),
        );
        $options = array('mode' => 'standard', 'published' => 1, 'publish_up' => '', 'type_id' => 0, 'locid' => 0, 'language' => '*', 'catid' => 1);
        $first = $normalise->invoke($controller, $event, $options, 1);
        self::assertTrue($first['valid']);

        $known = array();
        $skipped = 0;
        $filter = new ReflectionMethod(JemControllerImport::class, 'filterImportedIcsRecords');
        $records = array($first['record'], $first['record']);
        $arguments = array($records, &$known, &$skipped);
        self::assertCount(1, $filter->invokeArgs($controller, $arguments));
        self::assertSame(1, $skipped);
        self::assertCount(1, $known);
    }

    public function testIcsPreviewExplainsNonexistentDaylightSavingTime(): void
    {
        $app = Factory::getApplication();
        $originalOffset = $app->get('offset', 'UTC');
        $app->set('offset', 'Europe/Madrid');

        try {
            $controller = (new ReflectionClass(JemControllerImport::class))->newInstanceWithoutConstructor();
            $event = array(
                'SUMMARY' => array(array('value' => 'Test', 'raw' => 'Test', 'params' => array())),
                'DTSTART' => array(array('value' => '20260329T023000', 'raw' => '20260329T023000', 'params' => array())),
                'DTEND' => array(array('value' => '20260329T033000', 'raw' => '20260329T033000', 'params' => array())),
            );
            $options = array('mode' => 'standard', 'published' => 1, 'publish_up' => '', 'type_id' => 0, 'locid' => 0, 'language' => '*', 'catid' => 1);
            $result = (new ReflectionMethod(JemControllerImport::class, 'normaliseExternalIcsEvent'))->invoke($controller, $event, $options, 1);

            self::assertFalse($result['valid']);
            self::assertContains('COM_JEM_IMPORT_EXTERNAL_ICS_INVALID_LOCAL_TIME', $result['preview']['notes']);
        } finally {
            $app->set('offset', $originalOffset);
        }
    }

    public function testRecurringSpecialDayIsShownAsAnErrorInsteadOfImported(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'jem-ics-');
        self::assertNotFalse($path);
        file_put_contents($path, implode("\r\n", array(
            'BEGIN:VCALENDAR',
            'BEGIN:VEVENT',
            'SUMMARY:Recurring day',
            'DTSTART;VALUE=DATE:20261225',
            'RRULE:FREQ=YEARLY;COUNT=3',
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        )));

        try {
            $controller = (new ReflectionClass(JemControllerImport::class))->newInstanceWithoutConstructor();
            $options = array('title' => 'Test', 'day_type' => 'holiday', 'day_type_id' => 1, 'replace' => 0);
            $preview = (new ReflectionMethod(JemControllerImport::class, 'buildSpecialDaysIcsPreview'))
                ->invoke($controller, array('tmp_name' => $path), $options);

            self::assertSame(0, $preview['valid_count']);
            self::assertSame(1, $preview['error_count']);
            self::assertSame(array(), $preview['records']);
            self::assertContains('COM_JEM_IMPORT_SPECIAL_DAYS_RRULE_UNSUPPORTED', $preview['rows'][0]['notes']);
        } finally {
            unlink($path);
        }
    }

    public function testMixedSpecialDaysPreviewKeepsPlainEventAndRejectsRecurrence(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'jem-ics-');
        self::assertNotFalse($path);
        file_put_contents($path, implode("\r\n", array(
            'BEGIN:VCALENDAR',
            'BEGIN:VEVENT',
            'SUMMARY:Plain day',
            'DTSTART;VALUE=DATE:20261224',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'SUMMARY:Recurring day',
            'DTSTART;VALUE=DATE:20261225',
            'RRULE:FREQ=YEARLY;COUNT=3',
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        )));

        try {
            $controller = (new ReflectionClass(JemControllerImport::class))->newInstanceWithoutConstructor();
            $options = array('title' => 'Test', 'day_type' => 'holiday', 'day_type_id' => 1, 'replace' => 0);
            $preview = (new ReflectionMethod(JemControllerImport::class, 'buildSpecialDaysIcsPreview'))
                ->invoke($controller, array('tmp_name' => $path), $options);

            self::assertSame(1, $preview['valid_count']);
            self::assertSame(1, $preview['error_count']);
            self::assertCount(1, $preview['records']);
            self::assertContains('COM_JEM_IMPORT_SPECIAL_DAYS_RRULE_UNSUPPORTED', $preview['rows'][1]['notes']);
        } finally {
            unlink($path);
        }
    }
}
