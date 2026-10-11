<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DayNavigationContextTest extends TestCase
{
    public function testTimetableTodayKeepsItsLayoutAndContext(): void
    {
        $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/day/tmpl/timetable.php');
        self::assertStringNotContainsString('$todayMenuItem', $source);
        self::assertStringContainsString(
            "\$todayLink = Route::_('index.php?option=com_jem&view=day&layout=timetable&id='"
            . " . str_replace('-', '', JemHelper::getJoomlaDate()) . \$extraLink);",
            $source
        );
        foreach (['Itemid', 'catid', 'locid'] as $parameter) {
            self::assertStringContainsString("\$extraLinkParts[] = '$parameter='", $source);
        }
        self::assertStringContainsString(
            "include dirname(__DIR__) . '/timetable.php';",
            (string) file_get_contents(JEM_TEST_ROOT . '/site/views/day/tmpl/responsive/timetable.php')
        );
    }
}
