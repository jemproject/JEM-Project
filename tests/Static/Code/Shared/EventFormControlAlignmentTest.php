<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventFormControlAlignmentTest extends TestCase
{
    public function testBackendTimeFieldsUseAnInlineLeftAlignedGroup(): void
    {
        foreach (array('starttime.php', 'endtime.php') as $fileName) {
            $field = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/fields/' . $fileName);

            self::assertStringContainsString('form-select select-time', $field, $fileName);
            self::assertStringContainsString('class="jem-time-select"', $field, $fileName);
        }

        foreach (array('backend.css', 'backend-responsive.css') as $fileName) {
            $css = (string) file_get_contents(JEM_TEST_ROOT . '/media/css/' . $fileName);

            self::assertStringContainsString('.jem-time-select {', $css, $fileName);
            self::assertStringContainsString('#event-form joomla-field-fancy-select .choices__item', $css, $fileName);
            self::assertStringNotContainsString("\ndiv.controls {", $css, $fileName);
            self::assertStringContainsString('div.item-image div.controls {', $css, $fileName);
        }
    }

    public function testCategoryChoicesUsePlainTextHierarchyLabels(): void
    {
        foreach (array(
            'admin/models/fields/catoptions.php',
            'site/models/fields/catoptions.php',
        ) as $relativePath) {
            $field = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);

            self::assertStringContainsString('($options[$i]->level ?? 1) - 1', $field, $relativePath);
            self::assertStringContainsString('str_repeat("\\xC2\\xA0\\xC2\\xA0", $depth)', $field, $relativePath);
            self::assertStringContainsString('($depth > 0 ? \'└─ \' : \'\')', $field, $relativePath);
            self::assertStringNotContainsString('$options[$i]->treename', $field, $relativePath);
        }
    }
}
