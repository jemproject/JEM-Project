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
}
