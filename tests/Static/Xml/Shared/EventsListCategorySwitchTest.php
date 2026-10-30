<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventsListCategorySwitchTest extends TestCase
{
    /**
     * Validate the real menu definition with PHP 8.3-compatible DOM APIs.
     *
     * @return void
     */
    public function testEventsListMenuDefinesIncludeExcludeModeBeforeCategoryIds(): void
    {
        $path = JEM_TEST_ROOT . '/site/views/eventslist/tmpl/default.xml';
        $xml = new DOMDocument();

        self::assertTrue($xml->load($path));

        $this->assertCategoryFilterFields($xml);
    }

    /**
     * Check field uniqueness, metadata and relative order within the same group.
     *
     * @param DOMDocument $xml Menu XML or an isolated regression fixture.
     * @return void
     */
    private function assertCategoryFilterFields(DOMDocument $xml): void
    {
        $xpath = new DOMXPath($xml);
        $switch = $this->field($xpath, 'categoryswitch');
        $categories = $this->field($xpath, 'categoryswitchcats');

        self::assertSame('radio', $switch->getAttribute('type'));
        self::assertSame('0', $switch->getAttribute('default'));
        self::assertSame(array('1', '0'), $this->optionValues($switch));
        self::assertSame('text', $categories->getAttribute('type'));
        self::assertSame('COM_JEM_CATEGORYSWITCHCATS', $categories->getAttribute('label'));
        self::assertTrue(
            $xpath->evaluate('count(following-sibling::field[@name="categoryswitchcats"]) = 1', $switch),
            'The Include/Exclude selector must be rendered before Category IDs.'
        );
    }

    /**
     * Exercise the same assertions without modifying the real menu XML.
     *
     * @param string $source Isolated XML definition.
     * @param bool   $valid  Whether the definition satisfies the field contract.
     * @return void
     */
    #[DataProvider('categoryFilterFixtures')]
    public function testCategoryFilterContractRejectsInvalidDefinitions(string $source, bool $valid): void
    {
        $xml = new DOMDocument();
        self::assertTrue($xml->loadXML($source));

        if (!$valid) {
            $this->expectException(AssertionFailedError::class);
        }

        $this->assertCategoryFilterFields($xml);
    }

    /**
     * Provide valid ordering and independent malformed-field regression cases.
     *
     * @return iterable<string, array{string, bool}> XML and its expected validity.
     */
    public static function categoryFilterFixtures(): iterable
    {
        $switch = '<field name="categoryswitch" type="radio" default="0">'
            . '<option value="1">Include</option><option value="0">Exclude</option></field>';
        $categories = '<field name="categoryswitchcats" type="text" label="COM_JEM_CATEGORYSWITCHCATS"/>';
        $open = '<form><fields name="params"><fieldset name="basic">';
        $close = '</fieldset></fields></form>';

        yield 'correct order' => [$open . $switch . $categories . $close, true];
        yield 'comment and whitespace between' => [
            $open . $switch . "\n<!-- separator -->\n" . $categories . $close, true,
        ];
        yield 'unrelated field between' => [
            $open . $switch . '<field name="unrelated"/>' . $categories . $close, true,
        ];
        yield 'reversed order' => [$open . $categories . $switch . $close, false];
        yield 'missing switch' => [$open . $categories . $close, false];
        yield 'missing category IDs' => [$open . $switch . $close, false];
        yield 'duplicate switch' => [$open . $switch . $switch . $categories . $close, false];
        yield 'duplicate category IDs' => [$open . $switch . $categories . $categories . $close, false];
        yield 'different fieldset' => [
            $open . $switch . '</fieldset><fieldset name="other">' . $categories . $close, false,
        ];
        yield 'nested category IDs' => [
            $open . $switch . '<wrapper>' . $categories . '</wrapper>' . $close, false,
        ];
        yield 'wrong switch type' => [
            $open . str_replace('type="radio"', 'type="text"', $switch) . $categories . $close, false,
        ];
        yield 'wrong switch default' => [
            $open . str_replace('default="0"', 'default="1"', $switch) . $categories . $close, false,
        ];
        yield 'wrong switch options' => [
            $open . str_replace('value="1"', 'value="0"', $switch) . $categories . $close, false,
        ];
        yield 'wrong category IDs type' => [
            $open . $switch . str_replace('type="text"', 'type="radio"', $categories) . $close, false,
        ];
        yield 'wrong category IDs label' => [
            $open . $switch . str_replace('COM_JEM_CATEGORYSWITCHCATS', 'WRONG', $categories) . $close, false,
        ];
    }

    /**
     * Preserve the administrator-facing Category IDs label.
     *
     * @return void
     */
    public function testCategoryIdsUsesTheClearAdministratorLabel(): void
    {
        $language = (string) file_get_contents(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini');

        self::assertStringContainsString('COM_JEM_CATEGORYSWITCHCATS="Category IDs"', $language);
    }

    /**
     * Find exactly one named field before checking its values or position.
     *
     * @param DOMXPath $xpath Document query context.
     * @param string   $name  Expected field name.
     * @return DOMElement Unique matching field.
     */
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
     * Collect option values in their displayed order.
     *
     * @param DOMElement $field Select or radio field to inspect.
     * @return list<string> Ordered option values.
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
