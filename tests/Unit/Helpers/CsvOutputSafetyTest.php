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

    public function testOnlySafeEscapedExportValuesAreRestored(): void
    {
        $cases = array(
            '-33.8568' => '-33.8568',
            '+31 20 123 4567' => '+31 20 123 4567',
            "\nFirst line" => "\nFirst line",
            '-' => '-',
            '=1+1' => "'=1+1",
            '-30% Early bird' => "'-30% Early bird",
        );

        foreach ($cases as $source => $expected) {
            self::assertSame($expected, JemCsv::unprotectFormulaValue(JemCsv::protectFormulaValue($source)));
        }
    }

    public function testDefaultWriterRoundTripsBackslashBeforeAQuoteWithRfc4180(): void
    {
        $stream = fopen('php://temp', 'w+');
        $source = 'A backslash \\ before "a quote"';

        JemCsv::putRow($stream, array($source));
        rewind($stream);

        self::assertSame(array($source), fgetcsv($stream, 0, ',', '"', ''));
        fclose($stream);
    }
}
