<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/admin/helpers/importbudget.php';

final class ImportBudgetTest extends TestCase
{
    public function testJsonDecoderAcceptsBoundedRecords(): void
    {
        $decoded = JemImportBudgetHelper::decodeJson('{"events":[{"title":"Safe event"}]}');

        self::assertSame('Safe event', $decoded['events'][0]['title']);
    }

    public function testJsonDecoderRejectsExcessiveDepthBeforeImport(): void
    {
        $json = str_repeat('[', JemImportBudgetHelper::MAX_STRUCTURE_DEPTH + 1)
            . '0'
            . str_repeat(']', JemImportBudgetHelper::MAX_STRUCTURE_DEPTH + 1);

        $this->expectException(RuntimeException::class);
        JemImportBudgetHelper::decodeJson($json);
    }

    public function testJsonDecoderAcceptsTheDocumentedMaximumDepth(): void
    {
        $json = str_repeat('[', JemImportBudgetHelper::MAX_STRUCTURE_DEPTH)
            . '0'
            . str_repeat(']', JemImportBudgetHelper::MAX_STRUCTURE_DEPTH);

        self::assertIsArray(JemImportBudgetHelper::decodeJson($json));
    }

    public function testXmlRejectsDtdAndEntityDeclarations(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE events [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><events>&xxe;</events>';

        $this->expectException(RuntimeException::class);
        JemImportBudgetHelper::loadXml($xml);
    }

    public function testXmlParserRestoresLibxmlErrorMode(): void
    {
        $original = libxml_use_internal_errors(false);

        try {
            self::assertInstanceOf(SimpleXMLElement::class, JemImportBudgetHelper::loadXml('<events><event /></events>'));
            self::assertFalse(libxml_use_internal_errors());
        } finally {
            libxml_use_internal_errors($original);
        }
    }

    public function testRecordCollectionRejectsMoreThanTenThousandRows(): void
    {
        $this->expectException(RuntimeException::class);
        JemImportBudgetHelper::assertRecordList(
            array_fill(0, JemImportBudgetHelper::MAX_RECORDS + 1, array())
        );
    }

    public function testIcsRejectsAnOversizedUnfoldedProperty(): void
    {
        $content = "BEGIN:VCALENDAR\nBEGIN:VEVENT\nDESCRIPTION:"
            . str_repeat('a', JemImportBudgetHelper::MAX_FIELD_BYTES + 1)
            . "\nEND:VEVENT\nEND:VCALENDAR";

        $this->expectException(RuntimeException::class);
        JemImportBudgetHelper::assertIcs($content);
    }
}
