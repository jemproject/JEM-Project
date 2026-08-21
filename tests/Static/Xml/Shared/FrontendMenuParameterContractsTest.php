<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrontendMenuParameterContractsTest extends TestCase
{
    #[DataProvider('runtimeParameterProvider')]
    public function testRuntimeParameterIsExposedByItsMenuXml(
        string $xmlPath,
        string $fieldName,
        string $fieldType,
        string $default,
        string $consumerPath
    ): void {
        $xml = new DOMDocument();
        self::assertTrue($xml->load(JEM_TEST_ROOT . '/' . $xmlPath));

        $xpath = new DOMXPath($xml);
        $nodes = $xpath->query('//fields[@name="params"]//field[@name="' . $fieldName . '"]');

        self::assertInstanceOf(DOMNodeList::class, $nodes);
        self::assertSame(1, $nodes->length, $xmlPath . ' must define ' . $fieldName . ' exactly once.');

        $field = $nodes->item(0);
        self::assertInstanceOf(DOMElement::class, $field);
        self::assertSame($fieldType, $field->getAttribute('type'));
        self::assertSame($default, $field->getAttribute('default'));

        $consumer = (string) file_get_contents(JEM_TEST_ROOT . '/' . $consumerPath);
        self::assertStringContainsString("get('" . $fieldName . "'", $consumer);
    }

    /**
     * @return array<string, array{string, string, string, string, string}>
     */
    public static function runtimeParameterProvider(): array
    {
        return array(
            'Category subcategories' => array(
                'site/views/category/tmpl/default.xml',
                'usecat',
                'radio',
                '1',
                'site/models/categories.php',
            ),
            'Day category mode' => array(
                'site/views/day/tmpl/default.xml',
                'categoryswitch',
                'radio',
                '0',
                'site/models/day.php',
            ),
            'Day timetable category mode' => array(
                'site/views/day/tmpl/timetable.xml',
                'categoryswitch',
                'radio',
                '0',
                'site/models/day.php',
            ),
            'Events List category mode' => array(
                'site/views/eventslist/tmpl/default.xml',
                'categoryswitch',
                'radio',
                '0',
                'site/models/eventslist.php',
            ),
            'Events Map auto center' => array(
                'site/views/eventsmap/tmpl/default.xml',
                'map_auto_center',
                'radio',
                '1',
                'site/views/eventsmap/view.html.php',
            ),
            'Search top category' => array(
                'site/views/search/tmpl/default.xml',
                'top_category',
                'categories',
                '0',
                'site/models/search.php',
            ),
            'Search date filter type' => array(
                'site/views/search/tmpl/default.xml',
                'date_filter_type',
                'list',
                '0',
                'site/models/search.php',
            ),
        );
    }
}
