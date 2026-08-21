<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventsListCategorySwitchTest extends TestCase
{
    public function testEventsListMenuDefinesIncludeExcludeModeBeforeCategoryIds(): void
    {
        $path = JEM_TEST_ROOT . '/site/views/eventslist/tmpl/default.xml';
        $xml = new DOMDocument();

        self::assertTrue($xml->load($path));

        $xpath = new DOMXPath($xml);
        $switch = $this->field($xpath, 'categoryswitch');
        $categories = $this->field($xpath, 'categoryswitchcats');

        self::assertSame('radio', $switch->getAttribute('type'));
        self::assertSame('0', $switch->getAttribute('default'));
        self::assertSame(array('1', '0'), $this->optionValues($switch));
        self::assertSame('text', $categories->getAttribute('type'));
        self::assertSame('COM_JEM_CATEGORYSWITCHCATS', $categories->getAttribute('label'));
        self::assertTrue(
            (bool) ($switch->compareDocumentPosition($categories) & DOMNode::DOCUMENT_POSITION_FOLLOWING),
            'The Include/Exclude selector must be rendered before Category IDs.'
        );
    }

    public function testCategoryIdsUsesTheClearAdministratorLabel(): void
    {
        $language = (string) file_get_contents(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini');

        self::assertStringContainsString('COM_JEM_CATEGORYSWITCHCATS="Category IDs"', $language);
    }

    private function field(DOMXPath $xpath, string $name): DOMElement
    {
        $nodes = $xpath->query('//field[@name="' . $name . '"]');

        self::assertInstanceOf(DOMNodeList::class, $nodes);
        self::assertSame(1, $nodes->length, $name . ' must be defined exactly once.');

        $field = $nodes->item(0);
        self::assertInstanceOf(DOMElement::class, $field);

        return $field;
    }

    /**
     * @return list<string>
     */
    private function optionValues(DOMElement $field): array
    {
        $values = array();

        foreach ($field->getElementsByTagName('option') as $option) {
            $values[] = $option->getAttribute('value');
        }

        return $values;
    }
}
