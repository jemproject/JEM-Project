<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class StatisticsDashboardJoomlaIntegrationTest extends JoomlaTestCase
{
    public function testInstalledDashboardQueriesCurrentSchemaWithoutChangingData(): void
    {
        self::bootJoomlaSite();

        $modelPath = getenv('JEM_TEST_SOURCE') === '1'
            ? JEM_TEST_ROOT . '/admin/models/statistics.php'
            : JPATH_ADMINISTRATOR . '/components/com_jem/models/statistics.php';
        self::assertFileExists($modelPath, 'Install the rebuilt JEM package before running this test.');

        require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
        require_once $modelPath;

        $model = new JemModelStatistics();
        $filters = $model->getFilters();
        $filterOptions = $model->getFilterOptions();
        $dashboard = $model->getDashboardData($filters, array(
            'events' => true,
            'venues' => true,
            'registrations' => true,
        ));

        self::assertSame('all', $filters->metric);
        self::assertNotEmpty($dashboard->cards);
        self::assertArrayHasKey('events', $dashboard->cards);
        self::assertArrayHasKey('venues', $dashboard->cards);
        self::assertIsArray($dashboard->future_events);
        self::assertIsArray($dashboard->venue_infrastructure);
        self::assertIsArray($dashboard->programmes);
        self::assertIsArray($dashboard->booking_value_series);
        self::assertIsArray($dashboard->registration_commercial->revenue);
        self::assertObjectHasProperty('queue_resolution', $dashboard->registration_workflow);
        self::assertIsArray($filterOptions->venues);
        self::assertIsArray($filterOptions->categories);
        self::assertIsArray($filterOptions->types);
        self::assertIsArray($filterOptions->programmes);
        self::assertIsArray($filterOptions->authors);

        foreach ($dashboard->cards as $card) {
            self::assertNotEmpty($card->points, (string) $card->key);
            self::assertGreaterThanOrEqual(0, $card->period_total, (string) $card->key);
        }

        $scoped = clone $filters;
        $scoped->venue_id = isset($filterOptions->venues[0]) ? (int) $filterOptions->venues[0]->id : 0;
        $scoped->category_id = isset($filterOptions->categories[0]) ? (int) $filterOptions->categories[0]->id : 0;
        $scoped->type_id = isset($filterOptions->types[0]) ? (int) $filterOptions->types[0]->id : 0;
        $scoped->parent_event_id = isset($filterOptions->programmes[0]) ? (int) $filterOptions->programmes[0]->id : 0;
        $scoped->author_id = isset($filterOptions->authors[0]) ? (int) $filterOptions->authors[0]->id : 0;
        $scopedDashboard = $model->getDashboardData($scoped, array('events' => true, 'venues' => true, 'registrations' => true));
        self::assertArrayHasKey('events', $scopedDashboard->cards);
        self::assertArrayHasKey('registrations', $scopedDashboard->cards);
    }
}
