<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TypeIconLayoutTest extends TestCase
{
    public function testIconPickerUsesAFullWidthRowAndLargerButtons(): void
    {
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/type/tmpl/edit.php');
        $iconPosition = strpos($template, 'jem-type-icon-field');
        $colorPosition = strpos($template, 'jem-type-color-field');

        self::assertIsInt($iconPosition);
        self::assertIsInt($colorPosition);
        self::assertGreaterThan($iconPosition, $colorPosition, 'The color field should be below the icon picker.');
        self::assertStringContainsString('minmax(3.75rem, 1fr)', $template);
        self::assertStringContainsString('height: 3.75rem', $template);
        self::assertStringContainsString('font-size: 1.5rem', $template);
        self::assertStringContainsString('#jem-icon-glyph', $template);
        self::assertStringContainsString('id="jem-icon-search"', $template);
        self::assertStringContainsString("renderPicker(input.value.trim())", $template);
        self::assertStringContainsString("'fa-solid fa-faucet': 'fountain fuente water agua tap grifo'", $template);
        self::assertStringContainsString("input.addEventListener('keydown'", $template);
        foreach (array('nature', 'food', 'health', 'education', 'sports', 'technology', 'animals', 'other') as $category) {
            self::assertStringContainsString('value="' . $category . '"', $template);
        }
        self::assertStringContainsString("select.value === 'other'", $template);
        self::assertStringContainsString('categorised.indexOf(value) === -1', $template);
        self::assertStringNotContainsString('col-md-6 mb-3', substr($template, $iconPosition, $colorPosition - $iconPosition));
    }
}
