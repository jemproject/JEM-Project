<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/admin/helpers/importxlsx.php';

final class ImportXlsxTest extends TestCase
{
    private string $filename;

    protected function setUp(): void
    {
        if (!class_exists('ZipArchive')) {
            self::markTestSkipped('ZipArchive is required for XLSX tests.');
        }

        $temporaryName = tempnam(sys_get_temp_dir(), 'jem-xlsx-');
        self::assertNotFalse($temporaryName);
        unlink($temporaryName);
        $this->filename = $temporaryName . '.xlsx';
        $zip = new ZipArchive();
        self::assertTrue($zip->open($this->filename, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Venues" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>Nº</t></si><si><t>FUENTE</t></si><si><t>COORDENADAS</t></si><si><t>Pza. Carlos I</t></si><si><t>40.487657, -3.355224</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c></row><row r="2"><c r="A2"><v>1</v></c><c r="B2" t="s"><v>3</v></c><c r="C2" t="s"><v>4</v></c></row></sheetData></worksheet>');
        $zip->close();
    }

    protected function tearDown(): void
    {
        if (isset($this->filename) && is_file($this->filename)) {
            unlink($this->filename);
        }
    }

    public function testReadsFirstWorksheetAndPreservesCoordinates(): void
    {
        $result = JemImportXlsxHelper::readRecords($this->filename);

        self::assertSame(array('Nº', 'FUENTE', 'COORDENADAS'), $result['fields']);
        self::assertSame('1', $result['records'][0]['Nº']);
        self::assertSame('Pza. Carlos I', $result['records'][0]['FUENTE']);
        self::assertSame('40.487657, -3.355224', $result['records'][0]['COORDENADAS']);
    }
}
