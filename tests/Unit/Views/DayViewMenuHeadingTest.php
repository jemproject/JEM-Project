<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DayViewMenuHeadingTest extends TestCase
{
    #[DataProvider('headingProvider')]
    public function testNavigationKeepsMenuHeadingAndBreadcrumb(
        string $layout,
        bool $legacyDate,
        bool $customHeading
    ): void {
        require_once JEM_TEST_ROOT . '/tests/Support/day_view_pagination_stubs.php';
        require_once JEM_TEST_ROOT . '/site/views/day/view.html.php';

        $app = new DayPaginationApplication();
        \Joomla\CMS\Factory::$application = $app;
        $app->menuItem = new class ($legacyDate, $customHeading) {
            public string $title = 'Green events';
            public array $query;

            public function __construct(bool $legacyDate, private bool $customHeading)
            {
                $this->query = ['option' => 'com_jem', 'view' => 'day'];
                if ($legacyDate) {
                    $this->query['id'] = '0';
                }
            }

            public function getParams(): DayViewParams
            {
                return new DayViewParams($this->customHeading
                    ? ['page_heading' => 'Our green programme', 'page_title' => 'Green calendar']
                    : []);
            }
        };
        $view = new JemViewDay();
        $view->model = new DayPaginationModel([], 10, 0);
        $view->layout = $layout;
        $view->stopAfterItems = false;
        $view->display();

        self::assertSame($customHeading ? 'Our green programme' : 'Green events', $view->params->get('page_heading'));
        self::assertSame($customHeading ? 'Green calendar' : 'Green events', $app->document->title);
        self::assertSame(['Green events'], $app->pathway->items);
    }

    public static function headingProvider(): iterable
    {
        foreach (['timeline', 'timetable', 'default'] as $layout) {
            foreach ([false, true] as $legacy) {
                foreach ([false, true] as $custom) {
                    yield "$layout legacy=$legacy custom=$custom" => [$layout, $legacy, $custom];
                }
            }
        }
    }
}
