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
}
