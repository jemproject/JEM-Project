<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryMonitorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class RegistrationStatisticsProfileJoomlaIntegrationTest extends JoomlaTestCase
{
    /**
     * Cover profile capability and registration permission independently.
     *
     * @return array<string, array{string, bool}>
     */
    public static function profilePermissions(): array
    {
        $cases = [];
        foreach (['essential', 'advanced', 'ticketing'] as $profile) {
            $cases[$profile . ' permitted'] = [$profile, true];
            $cases[$profile . ' denied'] = [$profile, false];
        }

        return $cases;
    }

    /**
     * Verify populated/empty summaries and rendered KPIs without changing the site profile.
     *
     * @param string $profile In-memory operating profile.
     * @param bool $permitted Registration-statistics permission supplied by the view.
     * @return void
     */
    #[DataProvider('profilePermissions')]
    public function testRegistrationStatisticsRespectProfileAndPermission(string $profile, bool $permitted): void
    {
        self::bootJoomlaSite();
        if (!defined('JPATH_COMPONENT')) {
            define('JPATH_COMPONENT', JPATH_ADMINISTRATOR . '/components/com_jem');
        }
        Factory::getApplication()->loadLanguage(Language::getInstance('en-GB'));
        require_once JPATH_SITE . '/components/com_jem/factory.php';
        require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
        require_once JEM_TEST_ROOT . '/admin/models/statistics.php';
        self::assertSame(
            hash_file('sha256', JEM_TEST_ROOT . '/site/classes/featurepolicy.class.php'),
            hash_file('sha256', JPATH_SITE . '/components/com_jem/classes/featurepolicy.class.php'),
            'Use the candidate profile policy when testing Essential, Advanced and Ticketing.'
        );
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $settings = JemConfig::getInstance()->toRegistry();
        $oldProfile = $settings->get('operating_profile');
        $oldMonitor = $db->getMonitor();
        $author = 1 + (int) $db->setQuery('SELECT MAX(created_by) FROM #__jem_events')->loadResult();
        $category = 1 + (int) $db->setQuery('SELECT MAX(catid) FROM #__jem_cats_event_relations')->loadResult();
        foreach (['jem_events', 'jem_register', 'jem_cats_event_relations'] as $table) {
            $status = $db->setQuery('SHOW TABLE STATUS WHERE Name = ' . $db->quote($db->getPrefix() . $table))->loadObject();
            self::assertSame('InnoDB', $status->Engine, 'Fixture tables must support rollback.');
        }
        $db->transactionStart();
        try {
            $settings->set('operating_profile', $profile);
            $event = (object) [
                'title' => 'Registration statistics profile fixture',
                'alias' => 'registration-statistics-test-' . bin2hex(random_bytes(6)),
                'created_by' => $author, 'published' => 1, 'dates' => '2026-11-14',
                'introtext' => '', 'fulltext' => '', 'created_by_alias' => '', 'metadata' => '',
            ];
            $db->insertObject('#__jem_events', $event, 'id');
            $relation = (object) ['itemid' => $event->id, 'catid' => $category];
            $db->insertObject('#__jem_cats_event_relations', $relation);
            $bookings = [
                [1, 0, 1, 'classic', null], [1, 0, 1, 'classic', null],
                [1, 0, 2, 'classic', null], [1, 0, 2, 'single', '25.00'],
                [1, 1, 1, 'classic', null], [1, 1, 3, 'classic', null],
                [0, 0, 1, 'classic', null], [-1, 0, 1, 'classic', null],
            ];
            foreach ($bookings as [$state, $waiting, $places, $pricing, $total]) {
                $booking = (object) [
                    'event' => $event->id, 'uid' => null, 'reference' => bin2hex(random_bytes(14)),
                    'status' => $state, 'waiting' => $waiting, 'places' => $places,
                    'pricing_mode' => $pricing, 'currency' => $pricing === 'classic' ? '' : 'EUR',
                    'grand_total' => $total, 'created' => '2026-11-14 10:00:00',
                ];
                $db->insertObject('#__jem_register', $booking, 'id');
            }
            // A priced booking outside the selected author/category must not enter either total.
            $other = clone $event;
            unset($other->id);
            $other->created_by = $author + 1;
            $other->alias .= '-other';
            $db->insertObject('#__jem_events', $other, 'id');
            $outside = (object) [
                'event' => $other->id, 'uid' => null, 'reference' => bin2hex(random_bytes(14)),
                'status' => 1, 'waiting' => 0, 'places' => 99, 'pricing_mode' => 'single',
                'currency' => 'EUR', 'grand_total' => '999.00', 'created' => '2026-11-14 10:00:00',
            ];
            $db->insertObject('#__jem_register', $outside, 'id');
            $model = new JemModelStatistics();
            $filters = $model->getFilters();
            $filters->metric = 'registrations';
            $filters->author_id = $author;
            $filters->category_id = $category;
            $permissions = ['events' => false, 'venues' => false, 'registrations' => $permitted];
            $commerce = $profile !== 'essential';
            $monitor = new class implements QueryMonitorInterface {
                public array $queries = [];

                /** Capture issued SQL to check that disabled revenue is not calculated. */
                public function startQuery(string $sql, ?array $boundParams = null): void
                {
                    $this->queries[] = $sql;
                }

                /** No timing or output is needed for this query monitor. */
                public function stopQuery(): void
                {
                }
            };
            $db->setMonitor($monitor);
            $dashboard = $model->getDashboardData($filters, $permissions);
            $db->setMonitor($oldMonitor);
            self::assertSame($commerce, $dashboard->commerce_enabled);
            $revenueQueries = preg_grep('/SUM\(r\.grand_total\)/i', $monitor->queries);
            self::assertSame($permitted && $commerce, (bool) $revenueQueries);
            if ($permitted) {
                $expected = ['confirmed' => 4, 'confirmed_places' => 6, 'waiting' => 2,
                    'invited' => 1, 'cancelled' => 1, 'classic' => 7, 'priced' => 1];
                foreach ($expected as $field => $count) {
                    self::assertObjectHasProperty($field, $dashboard->registration_commercial);
                    self::assertSame($count, $dashboard->registration_commercial->{$field}, $field);
                }
                self::assertSame(2, $dashboard->registration_workflow->current_waiting);
                $money = $dashboard->registration_commercial->revenue;
                self::assertCount($commerce ? 1 : 0, $money);
                if ($commerce) {
                    self::assertSame('EUR', $money[0]->currency);
                    self::assertSame('25.00', $money[0]->total);
                }
            } else {
                self::assertSame(['revenue' => []], (array) $dashboard->registration_commercial);
                self::assertSame([], $dashboard->cards);
                self::assertSame([], $dashboard->booking_value_series);
            }

            // Use the actual template and reject PHP warnings, including missing KPI properties.
            $html = $this->renderDashboard($filters, $dashboard, $permissions);
            $dom = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                $dom->loadHTML($html);
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
            $xpath = new DOMXPath($dom);
            $kpis = $xpath->query('(//div[contains(@class,"jem-statistics-order-kpis")])[1]/div/strong');
            $rendered = [];
            foreach ($kpis as $kpi) {
                $rendered[] = (int) $kpi->textContent;
            }
            self::assertSame($permitted ? ($commerce ? [4, 6, 2, 1, 1, 7, 1] : [4, 6, 2, 1, 1, 7]) : [], $rendered);
            self::assertSame($permitted && $commerce, str_contains($html, 'class="jem-statistics-revenue'));

            $empty = clone $filters;
            $empty->category_id = $category + 1;
            $emptyDashboard = $model->getDashboardData($empty, $permissions);
            if ($permitted) {
                foreach (array_keys($expected) as $field) {
                    self::assertSame(0, $emptyDashboard->registration_commercial->{$field}, $field);
                }
            }
            self::assertSame([], $emptyDashboard->registration_commercial->revenue);
            // Preserve the existing public one-argument method contract for other callers.
            $legacy = $model->getRegistrationCommercialSummary($filters);
            self::assertSame(4, $legacy->confirmed);
            self::assertSame('25.00', $legacy->revenue[0]->total);
        } finally {
            $db->setMonitor($oldMonitor);
            $settings->set('operating_profile', $oldProfile);
            $db->transactionRollback();
        }
    }

    /**
     * Render the source dashboard layout with the native Joomla view helpers.
     *
     * @param object $filters Dashboard filters.
     * @param object $dashboard Native model output.
     * @param array $permissions View-level permission flags.
     * @return string Rendered HTML, never sent to the browser.
     */
    private function renderDashboard(object $filters, object $dashboard, array $permissions): string
    {
        $view = new class (['name' => 'statistics']) extends HtmlView {
            public object $filters;
            public object $statistics;
            public object $filterOptions;
            public array $permissions;

            /** Render the unchanged layout without adding toolbar or controller operations. */
            public function render(): string
            {
                ob_start();
                try {
                    require JEM_TEST_ROOT . '/admin/views/statistics/tmpl/default.php';

                    return (string) ob_get_contents();
                } finally {
                    ob_end_clean();
                }
            }
        };
        $view->filters = $filters;
        $view->statistics = $dashboard;
        $view->permissions = $permissions;
        $view->filterOptions = (object) ['venues' => [], 'categories' => [], 'types' => [], 'programmes' => [], 'authors' => []];
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ($severity & (E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE)) {
                throw new ErrorException($message, 0, $severity, $file, $line);
            }

            return false;
        });
        try {
            return $view->render();
        } finally {
            restore_error_handler();
        }
    }
}
