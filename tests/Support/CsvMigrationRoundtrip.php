<?php

declare(strict_types=1);

namespace JemProject\Tests\Support;

/**
 * Exercise production Migration export methods without HTTP headers or downloads.
 */
final class CsvMigrationRoundtrip
{
    /**
     * Export a stored row and decode it with the production CSV import helpers.
     *
     * @param array $record Stored database columns and values.
     *
     * @return array Decoded original columns, excluding transport metadata.
     */
    public static function record(array $record): array
    {
        require_once JEM_TEST_ROOT . '/admin/models/export.php';
        $model = (new \ReflectionClass(\JemModelExport::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($model, 'jemVersion'))->setValue($model, '5.1.0beta5');
        $header = (new \ReflectionMethod($model, 'addVersionHeader'))->invoke($model, array_keys($record));
        $row = (new \ReflectionMethod($model, 'addVersionValue'))->invoke($model, $record);
        $write = new \ReflectionMethod($model, 'putCsv');
        $stream = fopen('php://temp', 'w+');

        try {
            $write->invoke($model, $stream, $header, ';', '"');
            $write->invoke($model, $stream, $row, ';', '"');
            rewind($stream);
            $header = fgetcsv($stream, 0, ';', '"', '');
            $row = fgetcsv($stream, 0, ';', '"', '');
            $row = \JemCsvMetadataHelper::restoreFormulaEscapes(
                $row,
                \JemCsvMetadataHelper::findVersionColumn($header),
                \JemCsvMetadataHelper::findEscapeColumn($header)
            );

            return array_intersect_key(array_combine($header, $row), $record);
        } finally {
            fclose($stream);
        }
    }
}
