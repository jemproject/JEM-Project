<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AttachmentOptionsFormTest extends TestCase
{
    private const LAYOUT_OPTIONS_WITH_GLOBAL = array('', 'row', 'row_full', 'row_uniform', 'column', 'column_full', 'column_uniform');

    private const LAYOUT_OPTIONS_GLOBAL = array('row', 'row_full', 'row_uniform', 'column', 'column_full', 'column_uniform');

    private const ICON_SIZE_OPTIONS_WITH_GLOBAL = array('', 'none', 'normal', 'medium', 'large');

    private const ICON_SIZE_OPTIONS_GLOBAL = array('none', 'normal', 'medium', 'large');

    /**
     * @return iterable<string, array{string}>
     */
    public static function eventAndVenueFormProvider(): iterable
    {
        yield 'admin event' => array(JEM_TEST_ROOT . '/admin/models/forms/event.xml');
        yield 'admin venue' => array(JEM_TEST_ROOT . '/admin/models/forms/venue.xml');
        yield 'site event' => array(JEM_TEST_ROOT . '/site/models/forms/event.xml');
        yield 'site venue' => array(JEM_TEST_ROOT . '/site/models/forms/venue.xml');
    }

    #[DataProvider('eventAndVenueFormProvider')]
    public function testAttachmentLayoutOverrideFieldUsesFullSelectOptions(string $path): void
    {
        $field = $this->field($path, 'attachments_layout');

        self::assertSame('list', $field->getAttribute('type'));
        self::assertSame('form-select', $field->getAttribute('class'));
        self::assertSame(self::LAYOUT_OPTIONS_WITH_GLOBAL, $this->optionValues($field));
    }

    #[DataProvider('eventAndVenueFormProvider')]
    public function testAttachmentIconOverrideFieldUsesFullSelectOptions(string $path): void
    {
        $field = $this->field($path, 'attachments_icon_size');

        self::assertSame('list', $field->getAttribute('type'));
        self::assertSame('form-select', $field->getAttribute('class'));
        self::assertSame(self::ICON_SIZE_OPTIONS_WITH_GLOBAL, $this->optionValues($field));
    }

    #[DataProvider('eventAndVenueFormProvider')]
    public function testAttachmentFrameOverrideFieldIsYesNoRadio(string $path): void
    {
        $field = $this->field($path, 'attachments_frame');

        self::assertSame('radio', $field->getAttribute('type'));
        self::assertStringContainsString('btn-group-yesno', $field->getAttribute('class'));
        self::assertSame(array('1', '0'), $this->optionValues($field));
    }

    public function testGlobalAttachmentLayoutFieldUsesFullSelectOptions(): void
    {
        $field = $this->field(JEM_TEST_ROOT . '/admin/models/forms/settings.xml', 'attachments_layout');

        self::assertSame('list', $field->getAttribute('type'));
        self::assertStringContainsString('form-select', $field->getAttribute('class'));
        self::assertSame(self::LAYOUT_OPTIONS_GLOBAL, $this->optionValues($field));
    }

    public function testGlobalAttachmentIconFieldUsesFullSelectOptions(): void
    {
        $field = $this->field(JEM_TEST_ROOT . '/admin/models/forms/settings.xml', 'attachments_icon_size');

        self::assertSame('list', $field->getAttribute('type'));
        self::assertStringContainsString('form-select', $field->getAttribute('class'));
        self::assertSame(self::ICON_SIZE_OPTIONS_GLOBAL, $this->optionValues($field));
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
