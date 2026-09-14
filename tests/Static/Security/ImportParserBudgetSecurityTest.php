<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ImportParserBudgetSecurityTest extends TestCase
{
    public function testExternalImportFormatsUseTheSharedBudget(): void
    {
        $controller = (string) file_get_contents(JEM_TEST_ROOT . '/admin/controllers/import.php');
        $budget = (string) file_get_contents(JEM_TEST_ROOT . '/admin/helpers/importbudget.php');

        self::assertStringContainsString("require_once JPATH_COMPONENT_ADMINISTRATOR . '/helpers/importbudget.php'", $controller);
        self::assertGreaterThanOrEqual(3, substr_count($controller, 'JemImportBudgetHelper::decodeJson('));
        self::assertGreaterThanOrEqual(3, substr_count($controller, 'JemImportBudgetHelper::loadXml('));
        self::assertGreaterThanOrEqual(3, substr_count($controller, 'JemImportBudgetHelper::assertFileSize('));
        self::assertStringContainsString('JemImportBudgetHelper::assertIcs($content);', $controller);
        self::assertStringContainsString('public const MAX_RECORDS = 10000;', $budget);
        self::assertStringContainsString('public const MAX_FIELDS = 512;', $budget);
        self::assertStringContainsString('public const MAX_STRUCTURE_DEPTH = 32;', $budget);
        self::assertStringContainsString('JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING', $budget);
        self::assertStringContainsString('LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT', $budget);
        self::assertStringContainsString("preg_match('/<\\s*!(?:DOCTYPE|ENTITY)\\b/i'", $budget);
        self::assertStringNotContainsString("simplexml_load_string(\$content, 'SimpleXMLElement', LIBXML_NOCDATA)", $controller);
    }

    public function testEverySpecialDaysCsvEntryPointUsesTheSameLimits(): void
    {
        foreach (array('admin/controllers/specialdays.php', 'site/controllers/specialdays.php') as $relativePath) {
            $source = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);

            self::assertStringContainsString("helpers/importbudget.php'", $source, $relativePath);
            self::assertStringContainsString('JemImportBudgetHelper::assertFileSize(', $source, $relativePath);
            self::assertStringContainsString('JemImportBudgetHelper::assertRecordCount(', $source, $relativePath);
            self::assertStringContainsString('JemImportBudgetHelper::assertTabularRow(', $source, $relativePath);
        }
    }

    public function testXlsxXmlParserRejectsDeclarationsAndRestoresLibxmlState(): void
    {
        $helper = (string) file_get_contents(JEM_TEST_ROOT . '/admin/helpers/importxlsx.php');

        self::assertStringContainsString("preg_match('/<\\s*!(?:DOCTYPE|ENTITY)\\b/i'", $helper);
        self::assertStringContainsString('LIBXML_NONET | LIBXML_COMPACT', $helper);
        self::assertStringContainsString('$previous = libxml_use_internal_errors(true);', $helper);
        self::assertStringContainsString('libxml_use_internal_errors($previous);', $helper);
    }
}
