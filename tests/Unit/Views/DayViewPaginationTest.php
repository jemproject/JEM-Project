<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DayViewPaginationTest extends TestCase
{
    #[DataProvider('paginationProvider')]
    public function testViewLoadsTheExpectedRows(
        string $layout,
        int $limit,
        int $start,
        array $eligibleRows,
        array $expectedRows
    ): void {
        require_once JEM_TEST_ROOT . '/tests/Support/day_view_pagination_stubs.php';
        require_once JEM_TEST_ROOT . '/site/views/day/view.html.php';

        \Joomla\CMS\Factory::$application = new DayPaginationApplication();
        $model = new DayPaginationModel($eligibleRows, $limit, $start);
        $view = new JemViewDay();
        $view->model = $model;
        // Effective layout: the request need not contain a layout when supplied by a menu.
        $view->layout = $layout;

        try {
            $view->display();
            self::fail('The view must request event rows.');
        } catch (DayPaginationItemsLoaded $loaded) {
            self::assertSame($expectedRows, $loaded->rows);
            self::assertSame('20261011', $model->date);
        }
    }

    public static function paginationProvider(): iterable
    {
        $events = range(1, 25);

        foreach (['timeline', 'timetable'] as $layout) {
            yield $layout . ' exceeds global display number' => [$layout, 10, 0, $events, $events];
            yield $layout . ' clears a previous page offset' => [$layout, 10, 20, $events, $events];
            yield $layout . ' ignores a request limit of one' => [$layout, 1, 5, $events, $events];
            yield $layout . ' handles an empty interval' => [$layout, 10, 20, [], []];
        }

        yield 'default keeps first page' => ['default', 10, 0, $events, range(1, 10)];
        yield 'default keeps later page' => ['default', 10, 20, $events, range(21, 25)];
        yield 'default keeps show all' => ['default', 0, 0, $events, $events];
        yield 'custom list layout keeps pagination' => ['custom', 10, 10, $events, range(11, 20)];
    }
}
