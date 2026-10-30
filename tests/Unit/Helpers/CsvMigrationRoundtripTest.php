<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/csv.class.php';
require_once JEM_TEST_ROOT . '/admin/helpers/csvmetadata.php';

final class CsvMigrationRoundtripTest extends TestCase
{
    public static function csvFormats(): array
    {
        return [
            'semicolon and double quotes' => [';', '"'],
            'comma and double quotes' => [',', '"'],
            'tab and double quotes' => ["\t", '"'],
            'semicolon and single quotes' => [';', "'"],
            'comma and single quotes' => [',', "'"],
            'tab and single quotes' => ["\t", "'"],
        ];
    }

    #[DataProvider('csvFormats')]
    public function testLiteralApostrophesSurviveThreeRealCsvCycles(string $separator, string $enclosure): void
    {
        $sources = [
            '', "'", "''", "'''", 'plain text', "O'Connor", "'Rock Festival", "'Fiesta'",
            "'+31 20 123 4567", "'-", "'---", "'-33.8568", "'123", "' 123 ",
            "'=1+1", "'+value", "'-word", "'@name", "'\ttext", "'\ntext", "'\rtext",
            "''+31 20 123 4567", "'Música ñ", "’+31 20 123 4567",
            'The "quoted" name; with, separators', "path\\\"quote", '+31 20 123 4567', '-33.8568',
        ];

        foreach ($sources as $source) {
            $value = $source;
            for ($cycle = 0; $cycle < 3; ++$cycle) {
                $value = $this->roundTrip($value, $separator, $enclosure);
                self::assertSame($source, $value, 'Original: ' . json_encode($source) . '; cycle=' . $cycle);
            }
        }
    }

    public function testEscapingDoesNotWeakenFormulaProtection(): void
    {
        foreach (['=1+1', '+word', '-SUM(1,2)', '@name', " \ttext", "\n@value"] as $source) {
            self::assertSame("'" . $source, JemCsv::protectFormulaValue($source, true));
        }

        foreach (['=1+1', '+word', '-SUM(1,2)', '@name'] as $source) {
            self::assertSame("'" . $source, $this->roundTrip($source, ';', '"'));
            self::assertSame("'" . $source, $this->roundTrip("'" . $source, ';', '"'));
        }
    }

    public function testOriginalAndAddedApostrophesNoLongerCollide(): void
    {
        foreach (['+31 20 123 4567', '-33.8568', '-', "\ttext"] as $source) {
            self::assertNotSame(
                JemCsv::protectFormulaValue($source, true),
                JemCsv::protectFormulaValue("'" . $source, true)
            );
            self::assertSame(
                "'" . $source,
                JemCsv::unprotectFormulaValue(JemCsv::protectFormulaValue("'" . $source, true), true)
            );
        }
    }

    public function testDefaultWriterAndLegacyRowsKeepTheirPreviousPolicy(): void
    {
        self::assertSame("'+31 20 123 4567", JemCsv::protectFormulaValue("'+31 20 123 4567"));
        self::assertSame(
            ['+31 20 123 4567', '-33.8568', "'=1+1", "'plain", '5.0.1'],
            JemCsvMetadataHelper::restoreFormulaEscapes(
                ["'+31 20 123 4567", "'-33.8568", "'=1+1", "'plain", '5.0.1'],
                4,
                null
            )
        );
        // A literal doubled prefix in an older file must not acquire v2 semantics.
        self::assertSame(
            ["''123", '5.0.1'],
            JemCsvMetadataHelper::restoreFormulaEscapes(["''123", '5.0.1'], 1, null)
        );
    }

    public function testUnversionedCsvIsNotDecodedEvenIfItHasAnEscapeNamedColumn(): void
    {
        $row = ["'+31 20 123 4567", "''123", 'apostrophe-v2'];
        self::assertSame($row, JemCsvMetadataHelper::restoreFormulaEscapes($row, null, 2));
    }

    public static function unsupportedRevisions(): array
    {
        return [
            'empty' => [''],
            'future' => ['apostrophe-v3'],
            'different case' => ['APOSTROPHE-V2'],
            'extra whitespace' => ['apostrophe-v2 '],
            'missing cell' => [null],
        ];
    }

    #[DataProvider('unsupportedRevisions')]
    public function testUnsupportedExplicitRevisionIsRejected(?string $revision): void
    {
        $this->expectException(UnexpectedValueException::class);
        JemCsvMetadataHelper::restoreFormulaEscapes(["''123", '5.1.0', $revision], 1, 2);
    }

    public function testMetadataColumnNormalisationAndPerRowDecoding(): void
    {
        $header = ["\xEF\xBB\xBFJEM_CSV_ESCAPE", 'catname', ' JEM_EXPORT_VERSION '];
        self::assertSame(0, JemCsvMetadataHelper::findEscapeColumn($header));
        self::assertSame(2, JemCsvMetadataHelper::findVersionColumn($header));
        self::assertNull(JemCsvMetadataHelper::findEscapeColumn(['catname', 'jem_export_version']));
        self::assertSame(
            ['apostrophe-v2', "'123", '5.1.0'],
            JemCsvMetadataHelper::restoreFormulaEscapes(['apostrophe-v2', "''123", '5.1.0'], 2, 0)
        );
        self::assertSame(
            ['name' => "''text", 'count' => 2],
            JemCsv::protectFormulaRow(['name' => "'text", 'count' => 2], true)
        );
    }

    public function testMigrationExporterAndImporterUseMatchingRevision(): void
    {
        $export = file_get_contents(JEM_TEST_ROOT . '/admin/models/export.php');
        $import = file_get_contents(JEM_TEST_ROOT . '/admin/controllers/import.php');
        self::assertStringContainsString('JemCsvMetadataHelper::ESCAPE_FIELD', $export);
        self::assertStringContainsString(
            'JemCsv::putRow($handle, $fields, $separator, $delimiter, \'\', "\\n", true)',
            $export
        );
        self::assertStringContainsString('JemCsvMetadataHelper::findEscapeColumn($data)', $import);
        self::assertStringContainsString(
            'JemCsvMetadataHelper::restoreFormulaEscapes($data, $versionColumn, $escapeColumn)',
            $import
        );
        self::assertStringContainsString('catch (UnexpectedValueException $e)', $import);
        self::assertStringContainsString('COM_JEM_IMPORT_CSV_ESCAPE_UNSUPPORTED', $import);
    }

    /**
     * Exercise the real CSV writer, quoting parser, metadata dispatch and decoder.
     *
     * @param string $source Original field value.
     * @param string $separator CSV column separator.
     * @param string $enclosure CSV quote character.
     *
     * @return string The imported text value before field-specific validation.
     */
    private function roundTrip(string $source, string $separator, string $enclosure): string
    {
        $row = JemCsvMetadataHelper::addVersion(['catname' => $source], '5.1.0beta5');
        $stream = fopen('php://temp', 'w+');
        try {
            JemCsv::putRow($stream, array_keys($row), $separator, $enclosure, '', "\n", true);
            JemCsv::putRow($stream, array_values($row), $separator, $enclosure, '', "\n", true);
            rewind($stream);
            $header = fgetcsv($stream, 0, $separator, $enclosure, '');
            $data = fgetcsv($stream, 0, $separator, $enclosure, '');
            $data = JemCsvMetadataHelper::restoreFormulaEscapes(
                $data,
                JemCsvMetadataHelper::findVersionColumn($header),
                JemCsvMetadataHelper::findEscapeColumn($header)
            );
            return $data[0];
        } finally {
            fclose($stream);
        }
    }
}
