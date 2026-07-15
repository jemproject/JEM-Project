<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Minimal, read-only XLSX reader for external imports.
 *
 * Only the first worksheet is read. The archive is never extracted and strict
 * limits are applied before XML content is parsed.
 */
class JemImportXlsxHelper
{
    private const MAX_ARCHIVE_ENTRIES = 1000;
    private const MAX_UNCOMPRESSED_BYTES = 52428800;
    private const MAX_XML_BYTES = 10485760;
    private const MAX_ROWS = 10000;
    private const MAX_COLUMNS = 512;

    public static function readRecords($filename)
    {
        if (!class_exists('ZipArchive') || !is_file($filename)) {
            throw new RuntimeException('XLSX support is unavailable or the file cannot be read.');
        }

        $zip = new ZipArchive();

        if ($zip->open($filename, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('The XLSX archive is invalid.');
        }

        try {
            self::validateArchive($zip);
            $sharedStrings = self::readSharedStrings($zip);
            $sheetPath = self::findFirstWorksheet($zip);
            $rows = self::readWorksheet($zip, $sheetPath, $sharedStrings);
        } finally {
            $zip->close();
        }

        return self::buildRecords($rows);
    }

    private static function validateArchive(ZipArchive $zip)
    {
        if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
            throw new RuntimeException('The XLSX archive contains an unsafe number of entries.');
        }

        $totalSize = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = str_replace('\\', '/', (string) ($stat['name'] ?? ''));

            if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || preg_match('#(^|/)\.\.(/|$)#', $name)) {
                throw new RuntimeException('The XLSX archive contains an unsafe entry path.');
            }

            $totalSize += max(0, (int) ($stat['size'] ?? 0));

            if ($totalSize > self::MAX_UNCOMPRESSED_BYTES) {
                throw new RuntimeException('The XLSX archive is too large after decompression.');
            }
        }
    }

    private static function readSharedStrings(ZipArchive $zip)
    {
        $content = self::readOptionalEntry($zip, 'xl/sharedStrings.xml');

        if ($content === null) {
            return array();
        }

        $xml = self::loadXml($content);
        $strings = array();

        foreach ($xml->xpath('//*[local-name()="si"]') ?: array() as $item) {
            $value = '';

            foreach ($item->xpath('.//*[local-name()="t"]') ?: array() as $text) {
                $value .= (string) $text;
            }

            $strings[] = $value;
        }

        return $strings;
    }

    private static function findFirstWorksheet(ZipArchive $zip)
    {
        $workbookContent = self::readRequiredEntry($zip, 'xl/workbook.xml');
        $relationshipsContent = self::readRequiredEntry($zip, 'xl/_rels/workbook.xml.rels');
        $workbook = self::loadXml($workbookContent);
        $relationships = self::loadXml($relationshipsContent);
        $sheet = ($workbook->xpath('//*[local-name()="sheets"]/*[local-name()="sheet"]') ?: array())[0] ?? null;

        if ($sheet) {
            $relationshipAttributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $relationshipId = (string) ($relationshipAttributes['id'] ?? '');

            foreach ($relationships->xpath('//*[local-name()="Relationship"]') ?: array() as $relationship) {
                if ((string) $relationship['Id'] !== $relationshipId || strtolower((string) $relationship['TargetMode']) === 'external') {
                    continue;
                }

                $target = str_replace('\\', '/', (string) $relationship['Target']);
                $path = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
                $path = self::normaliseArchivePath($path);

                if (preg_match('#^xl/worksheets/[^/]+\.xml$#i', $path) && $zip->locateName($path) !== false) {
                    return $path;
                }
            }
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (preg_match('#^xl/worksheets/sheet[^/]*\.xml$#i', $name)) {
                return $name;
            }
        }

        throw new RuntimeException('The XLSX file does not contain a readable worksheet.');
    }

    private static function readWorksheet(ZipArchive $zip, $sheetPath, array $sharedStrings)
    {
        $xml = self::loadXml(self::readRequiredEntry($zip, $sheetPath));
        $rows = array();

        foreach ($xml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: array() as $rowNode) {
            if (count($rows) >= self::MAX_ROWS + 1) {
                throw new RuntimeException('The XLSX worksheet contains too many rows.');
            }

            $row = array();

            foreach ($rowNode->xpath('./*[local-name()="c"]') ?: array() as $cell) {
                $column = self::columnIndex((string) $cell['r']);

                if ($column < 0 || $column >= self::MAX_COLUMNS) {
                    throw new RuntimeException('The XLSX worksheet contains too many columns.');
                }

                $row[$column] = self::readCellValue($cell, $sharedStrings);
            }

            if (array_filter($row, static fn($value) => trim((string) $value) !== '')) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private static function readCellValue(SimpleXMLElement $cell, array $sharedStrings)
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            $value = '';

            foreach ($cell->xpath('.//*[local-name()="is"]//*[local-name()="t"]') ?: array() as $text) {
                $value .= (string) $text;
            }

            return $value;
        }

        $values = $cell->xpath('./*[local-name()="v"]') ?: array();
        $value = isset($values[0]) ? (string) $values[0] : '';

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        if ($type === 'b') {
            return $value === '1' ? '1' : '0';
        }

        return $type === 'e' ? '' : $value;
    }

    private static function buildRecords(array $rows)
    {
        if (!$rows) {
            return array('fields' => array(), 'records' => array());
        }

        $headerRow = array_shift($rows);
        $lastColumn = $headerRow ? max(array_keys($headerRow)) : -1;

        foreach ($rows as $row) {
            if ($row) {
                $lastColumn = max($lastColumn, max(array_keys($row)));
            }
        }

        $fields = array();
        $used = array();

        for ($column = 0; $column <= $lastColumn; $column++) {
            $field = trim((string) ($headerRow[$column] ?? ''));
            $field = $field !== '' ? $field : 'Column ' . self::columnName($column);
            $base = $field;
            $suffix = 2;

            while (isset($used[strtolower($field)])) {
                $field = $base . ' ' . $suffix++;
            }

            $used[strtolower($field)] = true;
            $fields[$column] = $field;
        }

        $records = array();

        foreach ($rows as $row) {
            $record = array();

            foreach ($fields as $column => $field) {
                $record[$field] = (string) ($row[$column] ?? '');
            }

            if (array_filter($record, static fn($value) => trim((string) $value) !== '')) {
                $records[] = $record;
            }
        }

        return array('fields' => array_values($fields), 'records' => $records);
    }

    private static function columnIndex($reference)
    {
        if (!preg_match('/^([A-Z]+)/i', $reference, $match)) {
            return -1;
        }

        $index = 0;

        foreach (str_split(strtoupper($match[1])) as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return $index - 1;
    }

    private static function columnName($index)
    {
        $name = '';

        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $name = chr((($index - 1) % 26) + 65) . $name;
        }

        return $name;
    }

    private static function normaliseArchivePath($path)
    {
        $parts = array();

        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);
                continue;
            }

            $parts[] = $part;
        }

        return implode('/', $parts);
    }

    private static function readRequiredEntry(ZipArchive $zip, $name)
    {
        $content = self::readOptionalEntry($zip, $name);

        if ($content === null) {
            throw new RuntimeException('The XLSX archive is missing required content.');
        }

        return $content;
    }

    private static function readOptionalEntry(ZipArchive $zip, $name)
    {
        $stat = $zip->statName($name);

        if ($stat === false) {
            return null;
        }

        if ((int) ($stat['size'] ?? 0) > self::MAX_XML_BYTES) {
            throw new RuntimeException('An XLSX XML part is too large.');
        }

        $content = $zip->getFromName($name);

        return $content === false ? null : $content;
    }

    private static function loadXml($content)
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();

        if (!$xml) {
            throw new RuntimeException('The XLSX file contains invalid XML.');
        }

        return $xml;
    }
}
