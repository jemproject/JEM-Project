<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventCategoryBindingTest extends TestCase
{
    public function testEventModelBindsStoredCategoriesToTheFormItem(): void
    {
        $model = $this->read('/admin/models/event.php');

        self::assertStringContainsString(
            '$item->cats = $this->getEventCategoryIds((int) $item->id);',
            $model
        );
        self::assertStringContainsString('protected function getEventCategoryIds($eventId)', $model);
        self::assertStringContainsString('->from($db->quoteName(\'#__jem_cats_event_relations\'))', $model);
        self::assertStringContainsString('->order($db->quoteName(\'ordering\')', $model);
    }

    public function testCategoryFieldUsesBoundDataInsteadOfTheRequestId(): void
    {
        $field = $this->read('/admin/models/fields/catoptions.php');
        $view = $this->read('/admin/views/event/view.html.php');

        self::assertStringContainsString('$this->normaliseCategoryIds($this->value)', $field);
        self::assertStringContainsString('$this->normaliseCategoryIds($this->default)', $field);
        self::assertStringNotContainsString("getInt('id')", $field);
        self::assertStringNotContainsString("#__jem_cats_event_relations", $field);
        self::assertStringNotContainsString("get('Catsselected')", $view);
        self::assertStringNotContainsString("Lists['category']", $view);
    }

    public function testEventSaveNormalizesAndValidatesSubmittedCategories(): void
    {
        $model = $this->read('/admin/models/event.php');

        self::assertStringContainsString(
            '$this->normaliseEventCategoryIds($data[\'cats\'] ?? array())',
            $model
        );
        self::assertStringContainsString('validateEventCategoryIds($cats, $backend, $new)', $model);
        self::assertStringContainsString('$data[\'cats\']         = $cats;', $model);
        self::assertStringContainsString('$user->getJemCategories(', $model);
        self::assertStringContainsString('array_diff($categories, array_unique($allowedIds))', $model);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents(JEM_TEST_ROOT . $relativePath);

        self::assertNotFalse($contents, $relativePath);

        return $contents;
    }
}
