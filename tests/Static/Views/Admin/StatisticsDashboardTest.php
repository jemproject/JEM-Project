<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class StatisticsDashboardTest extends TestCase
{
    /**
     * Keep detail introductions below their headings despite Atum flex headers.
     *
     * @return void
     */
    public function testDetailIntroductionsUseStackedHeaders(): void
    {
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/statistics/tmpl/default.php');

        foreach (['FUTURE_EVENTS', 'VENUE_INFRASTRUCTURE'] as $section) {
            $key = 'COM_JEM_STATISTICS_' . $section;
            self::assertMatchesRegularExpression(
                '~<div class="card-header d-block">\s*'
                . '<h2 class="h4 mb-1"><\?php echo Text::_\(\x27' . $key . '\x27\); \?></h2>\s*'
                . '<p class="text-muted mb-0"><\?php echo Text::_\(\x27' . $key . '_DESC\x27\); \?></p>~',
                $template,
                $section
            );
        }
    }

    public function testDashboardProvidesOperationalFiltersAndAccessibleTimeSeries(): void
    {
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/statistics/tmpl/default.php');

        foreach (array('metric', 'period', 'group', 'date_from', 'date_to', 'state_filter', 'subtype', 'venue_id', 'category_id', 'type_id', 'parent_event_id', 'author_id') as $filter) {
            self::assertStringContainsString('name="' . $filter . '"', $template, $filter);
        }

        self::assertStringContainsString('<svg class="jem-statistics-chart"', $template);
        self::assertStringContainsString('role="img"', $template);
        self::assertStringContainsString('COM_JEM_STATISTICS_TIME_AXIS', $template);
        self::assertStringContainsString('COM_JEM_STATISTICS_DATA_TABLE', $template);
    }

    public function testDashboardCoversHierarchyCapacityInfrastructureAndProfileGatedRevenue(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/statistics.php');
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/statistics/tmpl/default.php');

        foreach (array('parent_event_id', 'parent_venue_id', '#__jem_venue_capacity_profiles', '#__jem_venue_spaces', '#__jem_venue_capacity_areas', '#__jem_register_capacity_allocations') as $expected) {
            self::assertStringContainsString($expected, $model, $expected);
        }

        self::assertStringContainsString('SUM(r.grand_total)', $model);
        self::assertStringContainsString('registration_revision = r.revision', $model);
        self::assertStringContainsString('COM_JEM_STATISTICS_FUTURE_EVENTS', $template);
        self::assertStringContainsString('COM_JEM_STATISTICS_VENUE_INFRASTRUCTURE', $template);
        self::assertStringContainsString('COM_JEM_STATISTICS_REGISTRATION_ACTIVITY', $template);
        self::assertStringContainsString('COM_JEM_STATISTICS_BOOKING_VALUE_TREND', $template);
        self::assertStringContainsString('COM_JEM_STATISTICS_PROGRAMME_SUMMARY', $template);
        self::assertStringContainsString('COM_JEM_STATISTICS_OCCUPANCY', $template);
        self::assertStringContainsString('COM_JEM_STATISTICS_WORKFLOW_ACTIVITY', $template);
        self::assertStringContainsString('getBookingValueTimeline', $model);
        self::assertStringContainsString('getRegistrationWorkflowSummary', $model);
        self::assertStringContainsString('getProgrammeSummary', $model);
        self::assertStringContainsString('&& $commerceEnabled', $model);
        self::assertStringContainsString('$commerceEnabled', $template);
        self::assertStringContainsString('created_by = ', $model);
    }

    public function testDashboardDoesNotRepriceHistoricalOrdersOrCallExternalServices(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/statistics.php');
        $template = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/statistics/tmpl/default.php');

        self::assertStringNotContainsString('PricingQuote', $model);
        self::assertStringNotContainsString('recalculate', strtolower($model));
        self::assertStringNotContainsString('fetch(', $template);
        self::assertStringNotContainsString('XMLHttpRequest', $template);
    }

    public function testCanonicalBackendLayoutStylesTheDashboardResponsively(): void
    {
        $relativePath = 'media/css/backend.css';
        $css = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);
        self::assertStringContainsString('.jem-statistics-card-grid', $css, $relativePath);
        self::assertStringContainsString('.jem-statistics-chart', $css, $relativePath);
        self::assertStringContainsString('.jem-statistics-order-kpis', $css, $relativePath);
        self::assertStringContainsString('.jem-statistics-money-grid', $css, $relativePath);
        self::assertStringContainsString('@media (max-width: 767.98px)', $css, $relativePath);
    }
}
