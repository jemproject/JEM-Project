<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/csv.class.php';

final class CsvOutputSafetyTest extends TestCase
{
    public function testSpreadsheetControlPrefixesAreWrittenAsText(): void
    {
        $cases = array(
            array('=1+1', "'=1+1"),
            array('+value', "'+value"),
            array('-value', "'-value"),
            array('@value', "'@value"),
            array('  =value', "'  =value"),
            array("\tvalue", "'\tvalue"),
            array(" \rvalue", "' \rvalue"),
            array("\n@value", "'\n@value"),
        );

        foreach ($cases as $case) {
            self::assertSame($case[1], JemCsv::protectFormulaValue($case[0]));
        }
    }

    public function testPlainAndNumericValuesRemainUnchanged(): void
    {
        foreach (array('', 'plain text', '  plain text', "'already text", '0', 12, -4.5, null) as $value) {
            self::assertSame($value, JemCsv::protectFormulaValue($value));
        }
    }

    public function testRowsKeepTheirKeysWhileEveryValueIsProtected(): void
    {
        self::assertSame(
            array('name' => "'=value", 'comment' => 'plain', 'places' => 2),
            JemCsv::protectFormulaRow(array('name' => '=value', 'comment' => 'plain', 'places' => 2))
        );
    }

    public function testCentralWriterPreservesTheRequestedCsvFormat(): void
    {
        $stream = fopen('php://temp', 'w+');
        self::assertIsResource($stream);

        $written = JemCsv::putRow($stream, array('=value;next', "\tvalue", "line\nbreak", 2), ';', '"', '', "\r\n");
        self::assertIsInt($written);

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        self::assertIsString($csv);
        self::assertStringEndsWith("\r\n", $csv);
        self::assertSame(
            array("'=value;next", "'\tvalue", "line\nbreak", '2'),
            str_getcsv(rtrim($csv, "\r\n"), ';', '"', '')
        );
    }
}
