<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/helpers/attachmentdisplay.php';

final class SampleDataSqlTest extends TestCase
{
    public function testSampleDataAttachmentLayoutsAreValid(): void
    {
        $sql = (string) file_get_contents(JEM_TEST_ROOT . '/admin/assets/sampledata.sql');
        preg_match_all('/"attachments_layout"\s*:\s*"([^"]+)"/', $sql, $matches);

        self::assertNotEmpty($matches[1], 'Sample data should include attachment layout examples.');

        $invalid = array_values(array_diff(array_unique($matches[1]), JemAttachmentDisplayHelper::LAYOUTS));

        self::assertSame(
            array(),
            $invalid,
            "Invalid sample attachment layout values:\n" . implode("\n", $invalid)
        );
    }

    public function testSampleDataLinksUseKnownLayoutAndOrderValues(): void
    {
        $sql = (string) file_get_contents(JEM_TEST_ROOT . '/admin/assets/sampledata.sql');
        preg_match_all('/"links_layout"\s*:\s*"([^"]+)"/', $sql, $layoutMatches);
        preg_match_all('/"links_order"\s*:\s*"([^"]+)"/', $sql, $orderMatches);

        $validLayouts = array('row', 'row_full', 'row_uniform', 'column', 'column_full', 'column_uniform');
        $validOrders = array(
            'image_icon_text',
            'image_text_icon',
            'icon_text_image',
            'icon_image_text',
            'text_image_icon',
            'text_icon_image',
        );

        self::assertSame(array(), array_values(array_diff(array_unique($layoutMatches[1]), $validLayouts)));
        self::assertSame(array(), array_values(array_diff(array_unique($orderMatches[1]), $validOrders)));
    }

    public function testSampleDataCategoryInsertsDeclareColumns(): void
    {
        $sql = (string) file_get_contents(JEM_TEST_ROOT . '/admin/assets/sampledata.sql');

        self::assertDoesNotMatchRegularExpression(
            '/INSERT\s+INTO\s+`#__jem_categories`\s+VALUES\s*\(/i',
            $sql,
            'Sample category inserts must declare columns so upgraded databases with a different physical column order still load correctly.'
        );
    }

    public function testSampleDataVenueInsertsDeclareColumns(): void
    {
        $sql = (string) file_get_contents(JEM_TEST_ROOT . '/admin/assets/sampledata.sql');

        self::assertDoesNotMatchRegularExpression(
            '/INSERT\s+INTO\s+`#__jem_venues`\s+VALUES\s*\(/i',
            $sql,
            'Sample venue inserts must declare columns so the timezone column cannot shift legacy values.'
        );
        self::assertMatchesRegularExpression(
            '/INSERT\s+INTO\s+`#__jem_venues`\s*\([^)]*`country`[^)]*`type_id`[^)]*\)\s+VALUES\s*\(/i',
            $sql
        );
    }

    public function testSampleDataDemonstratesAllTimezoneModes(): void
    {
        $sql = (string) file_get_contents(JEM_TEST_ROOT . '/admin/assets/sampledata.sql');

        foreach (array(
            "WHEN 1 THEN 'Europe/Berlin'",
            "WHEN 4 THEN 'Europe/Madrid'",
            "WHEN 5 THEN 'Europe/Paris'",
            "WHEN 6 THEN 'Europe/London'",
            "SET `timezone_mode` = 'venue'",
            "SET `timezone_mode` = 'custom', `timezone` = 'Europe/Berlin'",
        ) as $expected) {
            self::assertStringContainsString($expected, $sql);
        }

        self::assertStringContainsString(
            '`timezone_mode` varchar(10) NOT NULL DEFAULT \'joomla\'',
            (string) file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql')
        );
    }

    public function testSampleDataContainsJem5TypesLinksAttachmentsAndMuseumExamples(): void
    {
        $sql = (string) file_get_contents(JEM_TEST_ROOT . '/admin/assets/sampledata.sql');

        foreach (array(
            'INSERT INTO `#__jem_types`',
            "'Concert', 'concert'",
            "'Exhibition', 'exhibition'",
            "'Museum', 'museum'",
            'Museum Technology Talk at the Prado',
            'Louvre Small Group Tour',
            'Science Museum Discovery Tour',
            'INSERT INTO `#__jem_links`',
            'INSERT INTO `#__jem_attachments`',
            'attachments_layout',
            'links_layout',
            'ticket_availability',
        ) as $expected) {
            self::assertStringContainsString($expected, $sql);
        }
    }

    public function testSampleDataArchiveContainsJem5ImageAndAttachmentAssets(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('PHP zip extension is required to inspect sampledata.zip.');
        }

        $zip = new ZipArchive();
        self::assertTrue($zip->open(JEM_TEST_ROOT . '/admin/assets/sampledata.zip'));

        $missing = array();

        foreach (array(
            'event-prado-evening-tour.webp',
            'event-louvre-masters-preview.webp',
            'event-science-museum-night-lab.webp',
            'venue-museo-del-prado.webp',
            'venue-musee-du-louvre.webp',
            'venue-science-museum.webp',
            'attachment-event1-dj-night-lineup.txt',
            'attachment-event3-balkan-beatz-press-pack.zip',
            'attachment-venue1-douala-house-rules.pdf',
        ) as $entry) {
            if ($zip->locateName($entry) === false) {
                $missing[] = $entry;
            }
        }

        $zip->close();

        self::assertSame(array(), $missing, "sampledata.zip is missing JEM 5 sample assets:\n" . implode("\n", $missing));
    }

    public function testSampleDataModelEnsuresTypeIdColumnsBeforeLoadingSql(): void
    {
        $code = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/sampledata.php');

        self::assertStringContainsString('$this->ensureTypeAssignmentSchema();', $code);
        self::assertStringContainsString("'#__jem_events' => \"`type_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `ticket_availability`\"", $code);
        self::assertStringContainsString("'#__jem_venues' => \"`type_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `language`\"", $code);
        self::assertStringContainsString("'#__jem_categories' => \"`type_id` INT(11) UNSIGNED NULL DEFAULT NULL AFTER `modified_user_id`\"", $code);
        self::assertMatchesRegularExpression(
            '/if\s*\(!empty\(\$columns\)\s*&&\s*!isset\(\$columns\[\'type_id\'\]\)\)\s*\{/',
            $code,
            'The schema guard should add type_id only when the table exists and the column is missing.'
        );
    }

    public function testSampleDataModelPreparesDatesAndRebuildsUtcBoundaries(): void
    {
        $code = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/sampledata.php');

        self::assertStringContainsString('$buffer = $this->prepareDateExpressions($buffer);', $code);
        self::assertStringContainsString('JemHelper::getJoomlaDate()', $code);
        self::assertStringContainsString('Factory::getDate()->toSql()', $code);
        self::assertStringContainsString('$this->rebuildEventUtcDates();', $code);
        self::assertStringContainsString('JemHelper::setEventUtcDates($event, $event->venue_timezone);', $code);
        self::assertStringContainsString("'v.timezone AS venue_timezone'", $code);
        self::assertStringContainsString("'start_utc'", $code);
        self::assertStringContainsString("'end_utc'", $code);
    }

    public function testSampleDataModelEnsuresTimezoneColumnsBeforeLoadingSql(): void
    {
        $code = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/sampledata.php');

        self::assertStringContainsString('$this->ensureTimezoneSchema();', $code);
        self::assertStringContainsString(
            "'timezone_mode' => \"`timezone_mode` VARCHAR(10) NOT NULL DEFAULT 'joomla' AFTER `endtimes`\"",
            $code
        );
        self::assertStringContainsString(
            "'start_utc'     => \"`start_utc` DATETIME NULL DEFAULT NULL AFTER `timezone`\"",
            $code
        );
        self::assertStringContainsString(
            "\"ALTER TABLE `#__jem_venues` ADD COLUMN `timezone` VARCHAR(64) NOT NULL DEFAULT '' AFTER `country`\"",
            $code
        );
    }

    public function testSampleDataImportIsAtomicAndChecksNewContentTables(): void
    {
        $code = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/sampledata.php');

        self::assertStringContainsString('$this->_db->transactionStart();', $code);
        self::assertStringContainsString('$this->_db->transactionCommit();', $code);
        self::assertStringContainsString('$this->_db->transactionRollback();', $code);
        self::assertStringContainsString('private function tableHasRows($table)', $code);

        foreach (array(
            '#__jem_venue_capacity_profiles',
            '#__jem_venue_spaces',
            '#__jem_venue_layouts',
            '#__jem_venue_profile_spaces',
            '#__jem_venue_capacity_areas',
            '#__jem_event_space_layouts',
            '#__jem_capacity_pools',
            '#__jem_event_prices',
            '#__jem_register_items',
            '#__jem_register_history',
        ) as $table) {
            self::assertStringContainsString($table, $code, $table . ' must block unsafe Sample Data loading.');
        }
    }

    public function testFreshInstallDefaultsDoNotBlockSampleData(): void
    {
        $code = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/sampledata.php');
        self::assertMatchesRegularExpression(
            '/A previous reset.*?foreach \(array\((.*?)\) as \$table\)/s',
            $code
        );
        preg_match('/A previous reset.*?foreach \(array\((.*?)\) as \$table\)/s', $code, $matches);
        $blockingTables = (string) ($matches[1] ?? '');

        self::assertStringNotContainsString('#__jem_special_days', $blockingTables);
        self::assertStringNotContainsString('#__jem_types', $blockingTables);
        self::assertStringContainsString('#__jem_events', $blockingTables);
    }
}
