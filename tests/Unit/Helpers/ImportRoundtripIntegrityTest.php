<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/admin/helpers/importsecurity.php';
require_once JEM_TEST_ROOT . '/admin/helpers/csvmetadata.php';
require_once JEM_TEST_ROOT . '/site/classes/csv.class.php';

final class ImportRoundtripIntegrityTest extends TestCase
{
    /** @return iterable<string, array{string, string}> Supported status strings. */
    public static function statuses(): iterable
    {
        foreach (['scheduled', 'cancelled', 'postponed', 'rescheduled', 'moved_online'] as $value) {
            yield $value => ['event_status', $value];
        }
        foreach (['instock', 'soldout', 'preorder', 'limited'] as $value) {
            yield $value => ['ticket_availability', $value];
        }
    }

    #[DataProvider('statuses')]
    public function testStatusStringsSurviveCsvImport(string $field, string $value): void
    {
        // The table, not the sanitizer, decides which availability values its branch supports.
        self::assertSame($value, $this->roundTrip($field, $value));
    }

    /** @return iterable<string, array{string}> JSON storage columns. */
    public static function jsonFields(): iterable
    {
        foreach (['attribs', 'metadata', 'translations', 'image_metadata', 'venue_snapshot'] as $field) {
            yield $field => [$field];
        }
    }

    #[DataProvider('jsonFields')]
    public function testJsonTypesAndContainersSurviveRepeatedCsvCycles(string $field): void
    {
        $value = '{"count":2,"ratio":1.5,"whole":1.0,"yes":true,"no":false,"nothing":null,'
            . '"object":{},"list":[],"id":"001","published":"draft",'
            . '"numericKeys":{"0":"zero","1":"one"},"nested":[1,false,null,{},[]]}';
        foreach ([';', ',', "\t"] as $separator) {
            foreach (['"', "'"] as $enclosure) {
                $actual = $value;
                for ($cycle = 0; $cycle < 3; ++$cycle) {
                    $actual = $this->roundTrip($field, $actual, $separator, $enclosure);
                    self::assertSame(serialize(json_decode($value)), serialize(json_decode($actual)));
                }
            }
        }
    }

    #[DataProvider('jsonFields')]
    public function testJsonStringCleanupDoesNotBreakEncoding(string $field): void
    {
        $value = json_encode(['name' => 'Hall &quot;A&quot;', 'quote' => 'O\'Connor "Room"', 'path' => 'C:\\room']);
        $decoded = json_decode($this->roundTrip($field, $value), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Hall "A"', $decoded['name']);
        self::assertSame('O\'Connor "Room"', $decoded['quote']);
        self::assertSame('C:\\room', $decoded['path']);
    }

    /** @return iterable<string, array{string}> Import source contexts sharing the sanitizer. */
    public static function importContexts(): iterable
    {
        foreach (['events', 'external', 'source_json', 'source_xml', 'source_csv', 'specialdays'] as $context) {
            yield $context => [$context];
        }
    }

    #[DataProvider('importContexts')]
    public function testStructuredFieldsKeepTypesAcrossSourceFormats(string $context): void
    {
        $original = ['version' => 1, 'closed' => false, 'empty' => new stdClass(), 'slots' => []];
        $record = JemImportSecurityHelper::sanitiseRecord(['attribs' => $original], $context, 2);
        self::assertSame(serialize((object) $original), serialize(json_decode($record['attribs'])));
        $warnings = [];
        $source = JemImportSecurityHelper::sanitiseSourceRecord(['metadata' => json_encode($original)], $context, 2, $warnings);
        self::assertSame(serialize((object) $original), serialize(json_decode($source['metadata'])));
        self::assertSame([], $warnings);
    }

    public function testValidProgrammeSurvivesImportInVersionsWithEventSchedule(): void
    {
        $source = ['event_schedule' => ['version' => 1, 'mode' => 'per_day', 'days' => [
            ['offset' => 0, 'closed' => false, 'all_day' => false,
                'slots' => [['start' => '10:00:00', 'end' => '11:00:00']]],
        ]]];
        $value = $this->roundTrip('attribs', json_encode($source));
        self::assertSame($source, json_decode($value, true));
        if (is_file(JEM_TEST_ROOT . '/site/classes/eventschedule.class.php')) {
            require_once JEM_TEST_ROOT . '/site/classes/eventschedule.class.php';
            JemEventSchedule::validate((object) ['dates' => '2030-11-07', 'enddates' => '2030-11-07', 'attribs' => $value]);
        }
    }

    public function testInvalidJsonIsReportedWithSourceRowInsteadOfSilentlyReset(): void
    {
        try {
            JemImportSecurityHelper::sanitiseRecord(['metadata' => '{broken'], 'events', 12);
            self::fail('Malformed JSON must be rejected.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('line=12', $error->getMessage());
            self::assertStringContainsString('metadata contains invalid JSON', $error->getMessage());
            self::assertStringNotContainsString('{broken', $error->getMessage());
        }
    }

    /** @return iterable<string, array{string}> Unsafe JSON leaves, including encoded payloads. */
    public static function unsafeJson(): iterable
    {
        yield 'unicode escaped script' => ['{"text":"\\u003cscript\\u003ealert(1)\\u003c/script\\u003e"}'];
        yield 'encoded script' => [json_encode(['nested' => ['value' => '&lt;script&gt;alert(1)&lt;/script&gt;']])];
        yield 'unsafe URL' => [json_encode(['url' => 'javascript:alert(1)'])];
        yield 'formula' => [json_encode(['nested' => ['formula' => '=1+1']])];
        yield 'inline handler' => [json_encode(['text' => '<img src=x onerror=alert(1)>'])];
    }

    #[DataProvider('unsafeJson')]
    public function testJsonStillRejectsUnsafeText(string $value): void
    {
        $this->expectException(RuntimeException::class);
        $this->roundTrip('attribs', $value);
    }

    public function testEmptyAndNullJsonRetainTheirStorageConventions(): void
    {
        self::assertSame('{}', JemImportSecurityHelper::sanitiseValue('attribs', ''));
        self::assertSame('{}', JemImportSecurityHelper::sanitiseValue('metadata', ''));
        self::assertSame('', JemImportSecurityHelper::sanitiseValue('translations', ''));
        self::assertNull(JemImportSecurityHelper::sanitiseValue('image_metadata', null));
        self::assertSame('{}', $this->roundTrip('attribs', '{}'));
        self::assertSame('[]', $this->roundTrip('attribs', '[]'));
    }

    public function testDecodedObjectsAreCheckedForUnsafeNestedValues(): void
    {
        $this->expectException(RuntimeException::class);
        JemImportSecurityHelper::sanitiseRecord(['metadata' => (object) [
            'nested' => (object) ['value' => 'javascript:alert(1)'],
        ]], 'source_json', 4);
    }

    public function testMalformedOptionalJsonIsRejectedInsteadOfErased(): void
    {
        $this->expectException(RuntimeException::class);
        JemImportSecurityHelper::sanitiseRecord(['translations' => '{broken'], 'types', 7);
    }

    public function testJsonRootMustBeAnObjectOrList(): void
    {
        $this->expectException(RuntimeException::class);
        JemImportSecurityHelper::sanitiseRecord(['metadata' => 'false'], 'events', 2);
    }

    public function testJsonRetainsExistingUrlPathAndTextPolicies(): void
    {
        $source = ['url' => 'file:///private/file', 'datimage' => '../private',
            'color' => 'red;background:blue', 'language' => 'invalid language',
            'safe' => ['url' => 'https://example.org/events', 'datimage' => 'events/photo.jpg', 'color' => '#AABBCC']];
        $actual = json_decode($this->roundTrip('attribs', json_encode($source)), true);
        self::assertSame('', $actual['url']);
        self::assertSame('', $actual['datimage']);
        self::assertSame('', $actual['color']);
        self::assertSame('*', $actual['language']);
        self::assertSame('https://example.org/events', $actual['safe']['url']);
        self::assertSame('events/photo.jpg', $actual['safe']['datimage']);
        self::assertSame('#aabbcc', $actual['safe']['color']);
    }

    public function testIntegerCoercionMatchesAllExportedTableSchemas(): void
    {
        $method = new ReflectionMethod(JemImportSecurityHelper::class, 'isIntegerField');
        $sql = file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        preg_match_all('/CREATE TABLE IF NOT EXISTS `#__jem_(events|venues|categories|types|attachments|cats_event_relations)`\s*\((.*?)\) ENGINE/is', $sql, $tables, PREG_SET_ORDER);
        self::assertCount(6, $tables);
        foreach ($tables as $table) {
            preg_match_all('/^\s*`([^`]+)`\s+([a-z]+)/im', $table[2], $columns, PREG_SET_ORDER);
            foreach ($columns as $column) {
                if ($method->invoke(null, $column[1])) {
                    self::assertMatchesRegularExpression('/int$/i', $column[2], $table[1] . '.' . $column[1]);
                }
            }
        }
        self::assertSame(-2, $this->roundTrip('published', '-2'));
        self::assertSame(10, $this->roundTrip('maxplaces', '10'));
        self::assertSame('00123', $this->roundTrip('postalCode', '00123'));
        self::assertSame('+34 912 345 678', $this->roundTrip('phone', '+34 912 345 678'));
        self::assertSame('-3.703790', $this->roundTrip('longitude', '-3.703790'));
    }

    /**
     * Exercise JEM's CSV writer, reader, escape metadata and import sanitization.
     *
     * @param string $field Exported column name.
     * @param string $value Source value before CSV encoding.
     * @param string $separator CSV separator.
     * @param string $enclosure CSV enclosure.
     * @return mixed Value presented to table validation.
     */
    private function roundTrip(string $field, string $value, string $separator = ';', string $enclosure = '"')
    {
        $row = JemCsvMetadataHelper::addVersion([$field => $value], '5.0.2rc3');
        $stream = fopen('php://temp', 'w+');
        try {
            JemCsv::putRow($stream, array_keys($row), $separator, $enclosure, '', "\n", true);
            JemCsv::putRow($stream, array_values($row), $separator, $enclosure, '', "\n", true);
            rewind($stream);
            $header = fgetcsv($stream, 0, $separator, $enclosure, '');
            $data = fgetcsv($stream, 0, $separator, $enclosure, '');
            $data = JemCsvMetadataHelper::restoreFormulaEscapes($data,
                JemCsvMetadataHelper::findVersionColumn($header), JemCsvMetadataHelper::findEscapeColumn($header));
            return JemImportSecurityHelper::sanitiseRecord([$field => $data[0]], 'events', 2)[$field];
        } finally {
            fclose($stream);
        }
    }
}
