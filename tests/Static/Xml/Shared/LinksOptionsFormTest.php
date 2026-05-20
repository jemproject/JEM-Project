<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LinksOptionsFormTest extends TestCase
{
    private const LAYOUT_OPTIONS = array('row', 'row_full', 'row_uniform', 'column', 'column_full', 'column_uniform');

    private const ORDER_OPTIONS = array(
        'image_icon_text',
        'image_text_icon',
        'icon_text_image',
        'icon_image_text',
        'text_image_icon',
        'text_icon_image',
    );

    /**
     * @return iterable<string, array{string}>
     */
    public static function eventFormProvider(): iterable
    {
        yield 'admin event' => array(JEM_TEST_ROOT . '/admin/models/forms/event.xml');
        yield 'site event' => array(JEM_TEST_ROOT . '/site/models/forms/event.xml');
    }

    #[DataProvider('eventFormProvider')]
    public function testLinksLayoutFieldUsesFullSelectOptions(string $path): void
    {
        $field = $this->field($path, 'links_layout');

        self::assertSame('list', $field->getAttribute('type'));
        self::assertSame('row', $field->getAttribute('default'));
        self::assertSame(self::LAYOUT_OPTIONS, $this->optionValues($field));
    }

    #[DataProvider('eventFormProvider')]
    public function testLinksOrderFieldUsesFullSelectOptions(string $path): void
    {
        $field = $this->field($path, 'links_order');

        self::assertSame('list', $field->getAttribute('type'));
        self::assertSame('image_icon_text', $field->getAttribute('default'));
        self::assertSame(self::ORDER_OPTIONS, $this->optionValues($field));
    }

    private function field(string $path, string $name): DOMElement
    {
        self::assertFileExists($path);

        $xml = new DOMDocument();
        $xml->load($path);
        $xpath = new DOMXPath($xml);
        $nodes = $xpath->query('//field[@name="' . $name . '"]');

        self::assertInstanceOf(DOMNodeList::class, $nodes);
        self::assertSame(1, $nodes->length, $name . ' must be defined once in ' . $this->relativePath($path));

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

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}
