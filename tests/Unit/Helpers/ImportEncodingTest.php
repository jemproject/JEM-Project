<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/admin/helpers/importencoding.php';

final class ImportEncodingTest extends TestCase
{
    public function testUtf8TextIsKept(): void
    {
        $value = 'Sesión de video';

        jem_normalise_csv_utf8($value, 1);

        self::assertSame('Sesión de video', $value);
    }

    public function testWindows1252TextIsConvertedToUtf8(): void
    {
        $value = iconv('UTF-8', 'windows-1252', 'Sesión de video');

        jem_normalise_csv_utf8($value, 1);

        self::assertSame('Sesión de video', $value);
    }

    public function testBomIsRemovedFromFirstCsvHeader(): void
    {
        $value = pack('CCC', 0xEF, 0xBB, 0xBF) . 'title';

        jem_normalise_csv_utf8($value, 0);

        self::assertSame('title', $value);
    }

    public function testEmptyValuesAreKept(): void
    {
        $value = '';

        jem_normalise_csv_utf8($value, 1);

        self::assertSame('', $value);
    }
}
