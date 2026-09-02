<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventsFilterLayoutTest extends TestCase
{
    public function testEventsUseJoomlaResponsiveSearchTools(): void
    {
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/events/tmpl/default.php');
        $css = (string) file_get_contents(JEM_TEST_ROOT . '/media/css/backend.css');

        self::assertStringContainsString("LayoutHelper::render('joomla.searchtools.default'", $template);
        self::assertStringContainsString('$this->filterForm->renderControlFields()', $template);
        self::assertStringNotContainsString('jem-events-admin-filter-bar', $template);
        self::assertStringNotContainsString('jem-events-table-actions', $template);
        self::assertStringNotContainsString('jem-events-column-selector', $template);
        self::assertStringNotContainsString('moveColumnSelector', $template);
        self::assertStringNotContainsString('.jem-events-admin-filter-bar', $css);
        self::assertStringNotContainsString('.jem-events-table-actions', $css);
        self::assertStringNotContainsString('.jem-admin-filter-columns', $css);
    }
}
