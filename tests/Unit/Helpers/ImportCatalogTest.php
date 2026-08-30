<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/admin/helpers/importcatalog.php';

final class ImportCatalogTest extends TestCase
{
    public function testLocalCatalogHasAbsolutePriorityOverTheRemoteCatalog(): void
    {
        $helper = (string) file_get_contents(JEM_TEST_ROOT . '/admin/helpers/importcatalog.php');
        $localStart = strpos($helper, "if (self::\$catalog['custom_file'])");
        $remoteStart = strpos($helper, '$xmlSource = self::downloadCatalogXml');

        self::assertNotFalse($localStart);
        self::assertNotFalse($remoteStart);
        self::assertGreaterThan($localStart, $remoteStart);

        $localBranch = substr($helper, $localStart, $remoteStart - $localStart);

        self::assertStringContainsString('return self::$catalog;', $localBranch);
        self::assertStringNotContainsString('downloadCatalogXml', $localBranch);
    }

    public function testRepositoryCatalogPassesCustomUploadValidation(): void
    {
        $xml = (string) file_get_contents(JEM_TEST_ROOT . '/updatecheck/import_catalog_jem.xml');
        $error = '';

        self::assertTrue(JemImportCatalogHelper::validateCatalogXml($xml, $error), $error);
        self::assertSame('', $error);
    }

    public function testCatalogRejectsExternalEntityDeclarations(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE catalog [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><jem-import-catalog version="1.0"><entry /></jem-import-catalog>';
        $error = '';

        self::assertFalse(JemImportCatalogHelper::validateCatalogXml($xml, $error));
        self::assertSame('external_entities', $error);
    }

    public function testCatalogParserRestoresLibxmlErrorMode(): void
    {
        $method = (new ReflectionClass(JemImportCatalogHelper::class))->getMethod('loadCatalogXml');
        $error = '';
        $arguments = array(
            '<jem-import-catalog version="1.0" published="2026-08-30" />',
            &$error,
        );
        $original = libxml_use_internal_errors(false);

        try {
            self::assertInstanceOf(SimpleXMLElement::class, $method->invokeArgs(null, $arguments));
            self::assertSame('', $error);
            self::assertFalse(libxml_use_internal_errors());
        } finally {
            libxml_use_internal_errors($original);
        }
    }

    public function testCatalogRejectsUnsupportedSchemaVersion(): void
    {
        $xml = '<?xml version="1.0"?><jem-import-catalog version="2.0"><entry /></jem-import-catalog>';
        $error = '';

        self::assertFalse(JemImportCatalogHelper::validateCatalogXml($xml, $error));
        self::assertSame('unsupported_version', $error);
    }

    public function testCatalogRejectsMissingOrInvalidPublishedDate(): void
    {
        $entry = '<entry id="test" type="events" format="ics"><title>Test</title><source url="https://example.org/events.ics" /></entry>';
        $error = '';

        self::assertFalse(JemImportCatalogHelper::validateCatalogXml(
            '<jem-import-catalog version="1.0">' . $entry . '</jem-import-catalog>',
            $error
        ));
        self::assertSame('invalid_published_date', $error);

        self::assertFalse(JemImportCatalogHelper::validateCatalogXml(
            '<jem-import-catalog version="1.0" published="today">' . $entry . '</jem-import-catalog>',
            $error
        ));
        self::assertSame('invalid_published_date', $error);
    }

    public function testCatalogRejectsDuplicateEntryIdentifiers(): void
    {
        $entry = '<entry id="duplicate" type="events" format="ics"><title>Test</title><source url="https://example.org/events.ics" /></entry>';
        $xml = '<?xml version="1.0"?><jem-import-catalog version="1.0" published="2026-08-30">' . $entry . $entry . '</jem-import-catalog>';
        $error = '';

        self::assertFalse(JemImportCatalogHelper::validateCatalogXml($xml, $error));
        self::assertSame('duplicate_entry_id', $error);
    }

    public function testCatalogDefaultsAreDefinedAsImportStaticValues(): void
    {
        $helper = new ReflectionClass(JemImportCatalogHelper::class);
        $method = $helper->getMethod('normaliseEntry');
        $xml = simplexml_load_string('<entry id="test" type="events" format="csv"><title>Test</title><source url="https://example.org/events.csv"/><defaults><default name="language" value="en-GB"/></defaults></entry>');
        $entry = $method->invoke(null, $xml);

        self::assertSame('en-GB', $entry['defaults']['language']);
        self::assertSame(array(
            array('field' => 'language', 'value' => 'en-GB', 'mode' => 'if_empty'),
        ), $entry['static_values']);
    }

    public function testCatalogItemCountMetadataIsNormalised(): void
    {
        $helper = new ReflectionClass(JemImportCatalogHelper::class);
        $method = $helper->getMethod('normaliseEntry');
        $xml = simplexml_load_string('<entry id="test" type="events" format="json"><title>Test</title><source url="https://example.org/events.json"/><items count="940" checked="2026-07-15"/></entry>');
        $entry = $method->invoke(null, $xml);

        self::assertSame(940, $entry['item_count']);
        self::assertSame('2026-07-15', $entry['item_count_checked']);
    }

    public function testCatalogRejectsInvalidItemCount(): void
    {
        $xml = '<?xml version="1.0"?><jem-import-catalog version="1.0" published="2026-08-30"><entry id="test" type="events" format="json"><title>Test</title><source url="https://example.org/events.json"/><items count="many"/></entry></jem-import-catalog>';
        $error = '';

        self::assertFalse(JemImportCatalogHelper::validateCatalogXml($xml, $error));
        self::assertSame('invalid_item_count', $error);
    }

    public function testAlcalaWaterFountainsUseXlsxVenueMapping(): void
    {
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/updatecheck/import_catalog_jem.xml');
        self::assertNotFalse($xml);
        $entries = $xml->xpath('//entry[@id="es-madrid-alcala-water-fountains"]');

        self::assertCount(1, $entries);
        self::assertSame('venues', (string) $entries[0]['type']);
        self::assertSame('xlsx', (string) $entries[0]['format']);
        self::assertSame('Alcalá de Henares', (string) $entries[0]['city']);
        self::assertSame('24', (string) $entries[0]->items['count']);
        self::assertStringEndsWith('/relacion-fuentes-2024.xlsx', (string) $entries[0]->source['url']);

        $mapping = array();
        foreach ($entries[0]->mapping->field as $field) {
            $mapping[(string) $field['source']] = (string) $field['target'];
        }

        self::assertSame('venue', $mapping['FUENTE']);
        self::assertSame('coordinates', $mapping['COORDENADAS']);
    }
}
