<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EventsFilterLayoutTest extends TestCase
{
    public function testFullHdFiltersStayOnOneLineWithActionsBelowOnTheRight(): void
    {
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/events/tmpl/default.php');
        $css = (string) file_get_contents(JEM_TEST_ROOT . '/media/css/backend.css');

        self::assertStringContainsString('jem-admin-filter-bar jem-events-admin-filter-bar', $template);
        self::assertStringContainsString('class="jem-admin-filter-actions jem-events-table-actions"', $template);
        self::assertStringContainsString('id="jem-events-column-selector"', $template);
        self::assertStringContainsString("target.appendChild(selector);", $template);
        self::assertStringContainsString("selector.classList.remove('float-end', 'pb-2');", $template);
        self::assertMatchesRegularExpression(
            '/@media \(min-width: 1600px\)[\s\S]+\.jem-events-admin-filter-bar\s*\{[\s\S]+flex-wrap: nowrap;/',
            $css
        );
        self::assertStringContainsString('.jem-admin-filter-actions {', $css);
        self::assertStringContainsString('.jem-admin-filter-columns:empty {', $css);
        self::assertStringContainsString('.jem-events-table-actions {', $css);

        $filterEnd = strpos($template, '</fieldset>');
        $tableActions = strpos($template, 'jem-events-table-actions');
        $table = strpos($template, '<div class="table">');

        self::assertIsInt($filterEnd);
        self::assertIsInt($tableActions);
        self::assertIsInt($table);
        self::assertTrue($filterEnd < $tableActions && $tableActions < $table);
    }
}
