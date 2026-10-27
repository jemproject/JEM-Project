<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EventslistTraceTest extends TestCase
{
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/site/classes/log.class.php';
    }

    public function testTraceMetricsContainOnlyNumericAndBooleanValues(): void
    {
        $method = new ReflectionMethod(JemLog::class, 'normaliseTraceMetrics');
        $actual = $method->invoke(null, array(
            'page_count' => 7,
            'remaining_count' => 0,
            'search_active' => false,
            'has_more' => true,
            'event_title' => 'Private event',
            'url' => '/events?search=secret',
            'numeric_string' => '12',
            'float_value' => 1.5,
            'array_value' => array(1),
            'invalid-name' => 2,
            'step' => 200,
            'menu_id' => 300,
            3 => 99,
        ));

        self::assertSame(array(
            'page_count' => 7,
            'remaining_count' => 0,
            'search_active' => false,
            'has_more' => true,
        ), $actual);
    }

    public function testTraceDoesNotAddStandardRequestContext(): void
    {
        $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/classes/log.class.php');

        self::assertStringContainsString("self::getConfiguredLevel() < 4", $source);
        self::assertStringContainsString("\$context, false);", $source);
    }

    public function testTraceCoversTheMainQueryAndPostQueryStages(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/eventslist.php');
        $view = (string) file_get_contents(JEM_TEST_ROOT . '/site/views/eventslist/view.html.php');

        foreach (array(
            'populateState.return',
            '_populatePublishState.return',
            'getViewAccessLevels.return',
            'getListQuery.return',
            '_getListCount.return',
            'getItems.query_return',
            'getCategoriesForItems.return',
            'getItems.visibility_return',
            'getItems.return',
        ) as $stage) {
            self::assertStringContainsString("traceEventslist('" . $stage . "'", $model);
        }

        self::assertStringContainsString("traceEventslist('view.pagination.return'", $view);
        self::assertStringContainsString("traceEventslist('view.display.return'", $view);
    }
}
