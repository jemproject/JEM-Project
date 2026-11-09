<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Execute the production Search model/SQL without touching an installed site's data. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SearchFilterControlsTest extends TestCase
{
    private SearchScopeDatabase $db;
    private SearchScopeApplication $app;
    private SearchScopeModel $model;

    /** @return void Load Joomla framework code and create an isolated SQL fixture. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php') || !extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT and pdo_sqlite; no site is modified.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        require_once JEM_TEST_ROOT . '/tests/Support/recurrence_source_stream.php';
        if (!in_array('jemrecurrence', stream_get_wrappers(), true)) {
                stream_wrapper_register('jemrecurrence', RecurrenceSourceStream::class);
        }
        if (!defined('JPATH_SITE')) {
                define('JPATH_SITE', 'jemrecurrence://site');
        }


        spl_autoload_register(static function (string $class) use ($root): void {
            $prefix = 'Joomla\\CMS\\';
            if (str_starts_with($class, $prefix)) {
                $path = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/site/models/search.php';
        require_once JEM_TEST_ROOT . '/tests/Support/search_scope_environment.php';

        // Use the actual publication-window helper; isolate unrelated hierarchy/age dependencies.
        $helper = file_get_contents(JEM_TEST_ROOT . '/site/helpers/helper.php');
        self::assertSame(1, preg_match(
            '/static public function getEventPublicationWhere\\([^\\n]*\\)\\s*\\{.*?^    \\}/ms',
            $helper,
            $match
        ));
        eval('use Joomla\\CMS\\Factory; class JemHelper extends SearchScopeHelperDependencies {' . $match[0] . '}');
        $this->db = new SearchScopeDatabase(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 't_']);
        $container = new Joomla\DI\Container();
        $container->set('DatabaseDriver', $this->db);
        $container->alias(Joomla\Database\DatabaseInterface::class, 'DatabaseDriver');
        $container->set('SiteRouter', new stdClass());
        Factory::$container = $container;
        $this->app = new SearchScopeApplication();
        Factory::$application = $this->app;
        Factory::$language = new class {
            /** @return string Language tag used by Joomla's date factory. */
            public function getTag(): string
            {
                return 'en-GB';
            }
        };
        $this->model = (new ReflectionClass(SearchScopeModel::class))->newInstanceWithoutConstructor();
        $this->model->setDatabase($this->db);
        $this->model->setState('limit', 12);
        $this->model->setState('limitstart', 12);
        $this->model->setState('filter_order', 'a.title');
        $this->model->setState('filter_order_Dir', 'ASC');

        foreach ([
            'CREATE TABLE t_jem_events (id INTEGER, published INTEGER, locid INTEGER, type_id INTEGER, access INTEGER, publish_up TEXT, publish_down TEXT, parent_event_id INTEGER, show_in_calendar INTEGER, dates TEXT, title TEXT)',
            'CREATE TABLE t_jem_venues (id INTEGER, venue TEXT, city TEXT, state TEXT, country TEXT, published INTEGER)',
            'CREATE TABLE t_jem_countries (iso2 TEXT, name TEXT, continent TEXT, published INTEGER)',
            'CREATE TABLE t_jem_types (id INTEGER, name TEXT, entity INTEGER, published INTEGER, access INTEGER, ordering INTEGER)',
            'CREATE TABLE t_jem_cats_event_relations (itemid INTEGER, catid INTEGER)',
        ] as $sql) {
            $this->db->setQuery($sql)->execute();
        }
        foreach ([
            [1, 1, 'ES', null, null, '2020-01-01'],
            [2, 1, 'FR', null, null, date('Y-m-d')],
            [3, 1, 'DE', null, null, '2099-01-01'],
            [4, 2, 'IT', null, '2020-01-01 00:00:00', '2020-01-01'],
            [5, 2, 'PT', '2099-01-01 00:00:00', null, '2099-01-01'],
            [6, 0, 'GB', null, null, '2020-01-01'],
            [7, -2, 'NL', null, null, '2020-01-01'],
            [8, 1, 'IE', '2099-01-01 00:00:00', null, '2099-01-01'],
            [9, 1, 'PL', null, '2020-01-01 00:00:00', '2020-01-01'],
        ] as [$id, $published, $country, $up, $down, $date]) {
            foreach ([
                'events' => ['id' => $id, 'published' => $published, 'locid' => $id, 'type_id' => $id,
                    'access' => 1, 'publish_up' => $up, 'publish_down' => $down,
                    'parent_event_id' => 0, 'show_in_calendar' => 1, 'dates' => $date],
                'venues' => ['id' => $id, 'venue' => 'Venue ' . $id, 'city' => 'City ' . $id,
                    'country' => $country, 'published' => 1],
                'countries' => ['iso2' => $country, 'name' => 'Country ' . $id, 'continent' => 'EU', 'published' => 1],
                'types' => ['id' => $id, 'name' => 'Type ' . $id, 'entity' => 1, 'published' => 1, 'access' => 1, 'ordering' => $id],
                'cats_event_relations' => ['itemid' => $id, 'catid' => 2],
            ] as $table => $values) {
                $row = (object) $values;
                $this->db->insertObject('#__jem_' . $table, $row);
            }
        }

        foreach ([
            "UPDATE t_jem_events SET title = 'Event ' || id",
            "UPDATE t_jem_venues SET country = 'ES', state = 'North' WHERE id = 1",
            "UPDATE t_jem_venues SET country = 'ES', state = 'South' WHERE id = 2",
            "UPDATE t_jem_venues SET country = 'JP', state = 'East' WHERE id = 3",
            "INSERT INTO t_jem_countries VALUES ('JP', 'Japan', 'AS', 1)",
            "UPDATE t_jem_venues SET state = 'King''s County' WHERE id = 4",
            "UPDATE t_jem_venues SET state = 'West' WHERE id = 5",
        ] as $sql) {
            $this->db->setQuery($sql)->execute();
        }
    }

    /** @param mixed $scope Stored setting. @param array $request Visitor input. @return void */
    private function configure(mixed $scope, array $request = []): void
    {
        $values = $scope === null ? [] : ['search_event_scope' => $scope];
        $values += ['filter_show_continent' => 0, 'filter_show_state' => 0];
        $this->app->params = new Registry($values);
        $this->app->active = new SearchScopeMenuItem(
            ['option' => 'com_jem', 'view' => 'search'],
            $this->app->params
        );
        $this->app->input = new Input($request + ['filter_category' => 2, 'Itemid' => 123]);
        foreach ($request as $key => $value) {
            $this->app->input->post->set($key, $value);
        }
    }

    /** @return int[] Actual SQL result IDs, using the production Search WHERE clause. */
    private function resultIds(): array
    {
        $where = (new ReflectionMethod(JemModelSearch::class, '_buildWhere'))->invoke($this->model);
        $query = 'SELECT DISTINCT a.id FROM #__jem_events AS a'
            . ' INNER JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
            . ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
            . ' LEFT JOIN #__jem_countries AS c ON c.iso2 = l.country' . $where . ' ORDER BY a.id';

        return array_map('intval', $this->db->setQuery($query)->loadColumn());
    }


    /** @return iterable<string, array{string, string, mixed}> Optional controls. */
    public static function optionalFilters(): iterable
    {
        foreach ([
            'filter_category' => ['category', 999],
            'filter_venue_id' => ['venue', 999],
            'filter_type_id' => ['eventtype', 999],
            'filter_continent' => ['continent', 'XX'],
            'filter_country' => ['country', 'XX'],
            'filter_state' => ['state', 'Unknown region'],
            'filter_city' => ['city', 'Unknown city'],
            'filter_date_from' => ['date_from', '2099-01-01'],
            'filter_date_to' => ['date_to', '1900-01-01'],
        ] as $field => [$option, $value]) {
            yield $field => [$field, $option, $value];
        }
    }

    /** @param string $field Input. @param string $option Setting. @param mixed $value Stale value. @return void */
    #[DataProvider('optionalFilters')]
    public function testDisabledFiltersIgnoreRequestAndSession(string $field, string $option, mixed $value): void
    {
        $this->configure('published', ['filter_search' => 'Event', 'filter_type' => 'title', $field => $value]);
        $this->app->params->set('filter_show_' . $option, 0);
        if ($field === 'filter_category') {
            // The fixture does not model category ancestry; use its actual root as menu scope.
            $this->app->params->set('top_category', 2);
        }
        $this->app->state['com_jem.search.' . $field] = $value;
        self::assertFalse($this->model->isSearchFilterEnabled($field));
        self::assertSame('', $this->model->getSearchFilter($field));
        self::assertSame([1, 2, 3], $this->resultIds());
        $this->app->input = new Input(['filter_search' => 'Event', 'filter_type' => 'title', 'filter_category' => 2]);
        self::assertSame([1, 2, 3], $this->resultIds(), 'Session-only stale filter must also be ignored.');
    }

    /** @return void Verify actual SQL throughout the continent/country/region/city chain. */
    public function testLocationOptionsCascadeAndRestoreCountryFromSession(): void
    {
        $this->configure('published', ['filter_continent' => 'EU', 'filter_country' => 'ES']);
        $this->app->params->set('filter_show_continent', 1);
        $this->app->params->set('filter_show_state', 1);
        self::assertSame(['North', 'South'], array_column($this->model->getStateOptions(), 'value'));
        $this->app->input->set('filter_state', 'North');
        self::assertSame(['City 1'], array_column($this->model->getCityOptions(), 'value'));
        self::assertSame([1], $this->resultIds());
        $this->app->input->set('filter_city', 'City 1');
        self::assertSame([1], array_map(static fn ($row) => (int) $row->value, $this->model->getVenueOptions()));
        $this->app->input = new Input(['filter_category' => 2]);
        self::assertSame(['City 1'], array_column($this->model->getCityOptions(), 'value'), 'Session country/region survive pagination.');
        $this->app->input = new Input(['filter_continent' => 'AS', 'filter_country' => '', 'filter_state' => '', 'filter_city' => '']);
        self::assertSame(['JP'], array_column($this->model->getCountryOptions(), 'value'));
        self::assertSame([], $this->model->getStateOptions(), 'Country must be chosen first.');
        $this->app->input->set('filter_country', 'JP');
        self::assertSame(['East'], array_column($this->model->getStateOptions(), 'value'));
        self::assertSame([], $this->model->getCityOptions(), 'Region must be chosen first.');
        $this->app->input->set('filter_state', 'East');
        self::assertSame(['City 3'], array_column($this->model->getCityOptions(), 'value'));
    }

    /** @return void Independently enabled child controls do not require hidden parents. */
    public function testCityAndRegionWorkWithoutCountryControl(): void
    {
        $this->configure('published', ['filter_city' => 'City 2', 'filter_country' => 'JP', 'filter_state' => 'South']);
        $this->app->params->set('filter_show_country', 0);
        $this->app->params->set('filter_show_city', 1);
        $this->app->params->set('filter_show_state', 1);
        self::assertSame([2], $this->resultIds());
        self::assertSame(['City 2'], array_column($this->model->getCityOptions(), 'value'));
    }

    /** @return void Preserve legacy field visibility while allowing independent new settings. */
    public function testLegacyDefaultsAndExplicitOptions(): void
    {
        $this->configure('published');
        $this->app->params->set('filter_show_dates', 0);
        $this->app->params->set('filter_show_country', 0);
        self::assertFalse($this->model->isSearchFilterEnabled('filter_date_from'));
        self::assertFalse($this->model->isSearchFilterEnabled('filter_date_to'));
        self::assertFalse($this->model->isSearchFilterEnabled('filter_city'));
        self::assertFalse($this->model->isSearchFilterEnabled('filter_state'));
        $this->app->params->set('filter_show_date_from', 1);
        $this->app->params->set('filter_show_city', 1);
        self::assertTrue($this->model->isSearchFilterEnabled('filter_date_from'));
        self::assertTrue($this->model->isSearchFilterEnabled('filter_city'));
        self::assertTrue($this->model->isSearchFilterEnabled('filter_search'));
        $this->app->params->set('filter_show_limit', 0);
        $this->app->input->set('limit', 999);
        self::assertSame(12, $this->model->getSearchFilter('limit', 12, 'int'));
    }

    /**
     * Verify the real Joomla form shows only Yes/No and retains legacy or explicit values.
     *
     * @return void
     */
    public function testMenuVisibilityButtonsResolveLegacyValues(): void
    {
        require_once JEM_TEST_ROOT . '/admin/models/fields/searchfiltertoggle.php';
        $metadata = simplexml_load_file(JEM_TEST_ROOT . '/site/views/search/tmpl/default.xml');
        $fields = $metadata->xpath('//field[@type="searchfiltertoggle"]');
        self::assertCount(4, $fields);
        foreach ([
            [[], [], 1],
            [['filter_show_country' => 0, 'filter_show_dates' => 0], [], 0],
            [['filter_show_country' => 1, 'filter_show_dates' => 1], [], 1],
            [['filter_show_country' => 0, 'filter_show_dates' => 0], ['value' => ''], 0],
            [['filter_show_country' => 0, 'filter_show_dates' => 0], ['value' => '1'], 1],
            [['filter_show_country' => 1, 'filter_show_dates' => 1], ['value' => '0'], 0],
        ] as [$legacy, $saved, $expected]) {
            $form = new Joomla\CMS\Form\Form('search-menu');
            $form->load('<form><fields name="params"><field name="filter_show_country" type="radio" />'
                . '<field name="filter_show_dates" type="hidden" /></fields></form>');
            $form->bind(['params' => $legacy]);
            foreach ($fields as $element) {
                $field = new JFormFieldSearchfiltertoggle($form);
                self::assertTrue($field->setup($element, $saved['value'] ?? null, 'params'));
                self::assertInstanceOf(Joomla\CMS\Form\Field\RadioField::class, $field);
                self::assertSame((string) $expected, (string) $field->value);
                self::assertSame('btn-group btn-group-yesno', $field->class);
                self::assertSame(['1', '0'], array_map('strval', $element->xpath('option/@value')));
                $name = (string) $element['name'];
                $this->app->params = new Registry($legacy);
                if (array_key_exists('value', $saved)) {
                    $this->app->params->set($name, $saved['value']);
                }
                self::assertSame((bool) $expected, $this->model->isSearchFilterEnabled(
                    str_replace('filter_show_', 'filter_', $name)
                ));
            }
        }
    }

    /**
     * Provide date boundaries for both Search modes and timezone-sensitive epoch parsing.
     *
     * @return iterable<string, array{string, int, string, string, bool, array}>
     */
    public static function dateBoundaries(): iterable
    {
        foreach (['UTC', 'Europe/Madrid', 'America/New_York'] as $timezone) {
            foreach ([0, 1] as $mode) {
                $fromEpoch = $mode === 1 ? [2, 3, 4, 7] : [2, 3, 7];
                foreach ([
                    'from epoch' => ['1970-01-01', '', true, $fromEpoch],
                    'to epoch' => ['', '1970-01-01', true, [1, 2, 4]],
                    'exact epoch' => ['1970-01-01', '1970-01-01', true, $mode === 1 ? [2, 4] : [2]],
                    'before epoch from' => ['1969-12-31', '', true, [2, 3, 4, 7]],
                    'before epoch to' => ['', '1969-12-30', true, [1]],
                    'modern date' => ['2026-10-31', '2026-10-31', true, [7]],
                    'invalid from' => ['not-a-date', '', true, [1, 2, 3, 4, 7]],
                    'invalid to' => ['', 'not-a-date', true, [1, 2, 3, 4, 7]],
                    'empty boundaries' => ['', '', true, [1, 2, 3, 4, 7]],
                    'disabled controls' => ['1970-01-01', '1970-01-01', false, [1, 2, 3, 4, 7]],
                    'valid from invalid to' => ['1970-01-01', 'not-a-date', true, $fromEpoch],
                    'reversed boundaries' => ['1970-01-02', '1969-12-30', true, []],
                ] as $case => $values) {
                    yield $timezone . ' / mode ' . $mode . ' / ' . $case => [$timezone, $mode, ...$values];
                }
            }
        }
    }

    /**
     * Execute the production date predicates, including epoch zero and overlapping events.
     *
     * @param string $timezone PHP default timezone.
     * @param int $mode Start-date (0) or complete event-range (1) matching.
     * @param string $from Lower visitor boundary.
     * @param string $to Upper visitor boundary.
     * @param bool $enabled Whether the administrator enables the date controls.
     * @param int[] $expected Expected public, published event IDs.
     * @return void
     */
    #[DataProvider('dateBoundaries')]
    public function testSearchDateBoundaries(
        string $timezone,
        int $mode,
        string $from,
        string $to,
        bool $enabled,
        array $expected
    ): void {
        $previousTimezone = date_default_timezone_get();
        try {
            date_default_timezone_set($timezone);
            $this->db->setQuery('ALTER TABLE t_jem_events ADD COLUMN enddates TEXT')->execute();
            $this->db->setQuery('DELETE FROM t_jem_events')->execute();
            foreach ([
                [1, '1969-12-30', null, 1, 1],
                [2, '1970-01-01', null, 1, 1],
                [3, '1970-01-02', null, 1, 1],
                [4, '1969-12-31', '1970-01-02', 1, 1],
                [5, '1970-01-01', null, 1, 9],
                [6, '1970-01-01', null, 2, 1],
                [7, '2026-10-31', null, 1, 1],
            ] as [$id, $date, $end, $published, $access]) {
                $row = (object) ['id' => $id, 'dates' => $date, 'enddates' => $end,
                    'published' => $published, 'access' => $access, 'locid' => $id,
                    'parent_event_id' => 0, 'show_in_calendar' => 1];
                $this->db->insertObject('#__jem_events', $row);
            }
            // Implement the two MySQL date expressions in this isolated SQLite fixture.
            $pdo = $this->db->getConnection();
            $pdo->sqliteCreateFunction('DATEDIFF', static function ($a, $b): ?int {
                if ($a === null || $b === null) {
                    return null;
                }

                return (int) (new DateTimeImmutable($b))->diff(new DateTimeImmutable($a))->format('%r%a');
            }, 2);
            $pdo->sqliteCreateFunction('IF', static fn ($condition, $yes, $no) => $condition ? $yes : $no, 3);
            $this->configure('published', ['filter_date_from' => $from, 'filter_date_to' => $to]);
            $this->app->params->set('date_filter_type', $mode);
            $this->app->params->set('filter_show_date_from', (int) $enabled);
            $this->app->params->set('filter_show_date_to', (int) $enabled);

            self::assertSame($expected, $this->resultIds());
        } finally {
            date_default_timezone_set($previousTimezone);
        }
    }

    /** @return void Clearing all values also overwrites stale session values. */
    public function testClearLeavesNoSearchRestrictionOrOldSessionValue(): void
    {
        $this->configure('published', ['filter_country' => 'ES', 'filter_state' => 'North', 'filter_city' => 'City 1']);
        $this->app->params->set('filter_show_state', 1);
        self::assertSame([1], $this->resultIds());
        foreach (['filter_country', 'filter_state', 'filter_city', 'filter_continent', 'filter_search',
            'filter_date_from', 'filter_date_to'] as $field) {
            $this->app->input->set($field, '');
        }
        $this->app->input->set('filter_category', 1);
        self::assertSame([], $this->resultIds(), 'Existing no-search/no-results behavior is preserved.');
        self::assertSame('', $this->app->state['com_jem.search.filter_city']);
        self::assertSame('', $this->app->state['com_jem.search.filter_state']);
    }

    /** @return void Keep publication scope and ACL; quote names with apostrophes safely. */
    public function testLocationFiltersPreserveScopeAndAccess(): void
    {
        $this->configure('published', ['filter_state' => "King's County"]);
        $this->app->params->set('filter_show_country', 0);
        $this->app->params->set('filter_show_state', 1);
        self::assertSame([], $this->resultIds());
        $this->app->params->set('search_event_scope', 'archived');
        self::assertSame([4], $this->resultIds());
        self::assertSame(["King's County", 'West'], array_column($this->model->getStateOptions(), 'value'));
        $this->db->setQuery('UPDATE t_jem_events SET access = 2 WHERE id = 4')->execute();
        self::assertSame([], $this->resultIds(), 'A matching region must not bypass event ACL.');
    }

    /** @return void The first configured geographic field starts enabled; later fields require predecessors. */
    public function testProgressiveActivationSkipsDisabledFields(): void
    {
        $this->configure('published');
        $this->app->params->set('filter_show_continent', 1);
        $this->app->params->set('filter_show_state', 1);
        self::assertTrue($this->model->isLocationFilterAvailable('filter_continent'));
        self::assertFalse($this->model->isLocationFilterAvailable('filter_country'));
        self::assertSame('filter_continent', $this->model->getLocationPrerequisite('filter_city'));
        self::assertFalse($this->model->isLocationFilterAvailable('filter_state'));
        self::assertFalse($this->model->isLocationFilterAvailable('filter_city'));
        $this->app->input->set('filter_continent', 'EU');
        self::assertSame('filter_country', $this->model->getLocationPrerequisite('filter_city'));
        self::assertTrue($this->model->isLocationFilterAvailable('filter_country'));
        self::assertFalse($this->model->isLocationFilterAvailable('filter_state'));
        $this->app->input->set('filter_country', 'ES');
        self::assertSame('filter_state', $this->model->getLocationPrerequisite('filter_city'));
        self::assertTrue($this->model->isLocationFilterAvailable('filter_state'));
        self::assertFalse($this->model->isLocationFilterAvailable('filter_city'));
        $this->app->input->set('filter_state', 'North');
        self::assertTrue($this->model->isLocationFilterAvailable('filter_city'));
        $this->app->input->set('filter_country', '');
        $this->app->input->set('filter_city', 'City 1');
        self::assertSame('', $this->model->getSearchFilter('filter_city'), 'A URL cannot bypass an unselected parent.');
        $this->app->params->set('filter_show_continent', 0);
        $this->app->params->set('filter_show_state', 0);
        self::assertTrue($this->model->isLocationFilterAvailable('filter_country'));
        self::assertFalse($this->model->isLocationFilterAvailable('filter_city'));
        $this->app->input->set('filter_country', 'ES');
        self::assertTrue($this->model->isLocationFilterAvailable('filter_city'));
    }

    /** @return void Clear resets hidden fields and pagination in the session, not just the DOM. */
    public function testExplicitClearResetsHiddenSessionFilters(): void
    {
        $this->configure('published', ['filter_city' => 'City 1', 'limit' => 100, 'limitstart' => 40]);
        $this->app->params->set('filter_show_city', 0);
        foreach (['filter_city', 'filter_state', 'filter_country', 'filter_date_from', 'filter_date_to'] as $name) {
            $this->app->state['com_jem.search.' . $name] = 'stale';
        }
        $this->app->input->post->set('search_reset', 1);
        (new ReflectionMethod(JemModelSearch::class, 'resetSearchFilters'))->invoke($this->model, 12);
        foreach (['filter_city', 'filter_state', 'filter_country', 'filter_date_from', 'filter_date_to'] as $name) {
            self::assertSame('', $this->app->state['com_jem.search.' . $name]);
        }
        self::assertSame(12, $this->app->input->getInt('limit'));
        self::assertSame(0, $this->app->input->getInt('limitstart'));
        self::assertSame('published', $this->model->getEventSearchScope());
        self::assertFalse($this->model->isSearchFilterEnabled('filter_city'));
    }

    /** @return void Disabling the category control does not remove the menu's category boundary. */
    public function testHiddenCategoryKeepsConfiguredRootRestriction(): void
    {
        $this->configure('published', ['filter_search' => 'Event', 'filter_type' => 'title', 'filter_category' => 2]);
        $this->app->params->set('filter_show_category', 0);
        $this->app->params->set('top_category', 999);
        self::assertSame([], $this->resultIds());
    }

    /** @return void Keep the university sample city and county/region geographically distinct. */
    public function testUniversitySampleUsesCommunityOfMadridAsRegion(): void
    {
        $sql = file_get_contents(JEM_TEST_ROOT . '/admin/assets/sampledata.sql');
        self::assertSame(1, preg_match("/^\\(12, 'Northbridge University Innovation Lab'.*$/m", $sql, $match));
        $values = str_getcsv(ltrim($match[0], '('), ',', "'", '\\');
        self::assertSame('Madrid', $values[7]);
        self::assertSame('Community of Madrid', $values[11]);
        self::assertSame('ES', $values[12]);
        self::assertStringContainsString('(24, 12, CURDATE()', $sql);
    }

    /** @return void Hidden fields cannot be re-enabled by visitor-supplied option names. */
    public function testRequestCannotEnableDisabledControls(): void
    {
        $this->configure('published', ['filter_state' => 'North', 'filter_show_state' => 1]);
        $this->app->params->set('filter_show_state', 0);
        self::assertSame([1, 2, 3], $this->resultIds());
    }
}

