<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/admin/helpers/csvmetadata.php';

final class CsvMetadataTest extends TestCase
{
    public function testAddsVersionAsABackwardsCompatibleExtraColumn(): void
    {
        self::assertSame(
            array('id' => 791, 'title' => 'Test event', 'jem_export_version' => '5.0.1beta1'),
            JemCsvMetadataHelper::addVersion(
                array('id' => 791, 'title' => 'Test event'),
                '5.0.1beta1'
            )
        );
    }

    public function testFindsAndExtractsVersionMetadata(): void
    {
        $header = array('id', 'title', 'JEM_EXPORT_VERSION');
        $row = array('791', 'Test event', '4.4.2');
        $column = JemCsvMetadataHelper::findVersionColumn($header);

        self::assertSame(2, $column);
        self::assertSame('4.4.2', JemCsvMetadataHelper::extractVersion($row, $column));
    }

    public function testRejectsUnsafeVersionMetadata(): void
    {
        self::assertSame('', JemCsvMetadataHelper::normaliseVersion('<script>alert(1)</script>'));
    }
}
