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
final class SearchMenuScopeTest extends TestCase
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
        spl_autoload_register(static function (string $class) use ($root): void {
            $prefix = 'Joomla\\CMS\\';
            if (str_starts_with($class, $prefix)) {
                $path = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });
        // Load checkout methods, isolating only installed-path dependency loading.
        require_once JEM_TEST_ROOT . '/tests/Support/type_language_environment.php';
        eval('use Joomla\\CMS\\Factory; class JemOutput {'
            . \JemProject\Tests\TypeLanguage\methods('site/classes/output.class.php', ['getTypeTranslationValue']) . '}');
        $search = file_get_contents(JEM_TEST_ROOT . '/site/models/search.php');
        $search = str_replace('__DIR__', var_export(JEM_TEST_ROOT . '/site/models', true), $search);
        $search = preg_replace('/^ *require_once .*output\\.class\\.php.*;\\R/m', '', $search);
        eval(substr($search, 5));
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
            public string $tag = 'en-GB';
            /** @return string Language tag used by Joomla's date factory. */
            public function getTag(): string
            {
                return $this->tag;
            }
        };
        $this->model = (new ReflectionClass(SearchScopeModel::class))->newInstanceWithoutConstructor();
        $this->model->setDatabase($this->db);
        $this->model->setState('limit', 12);
        $this->model->setState('limitstart', 12);
        $this->model->setState('filter_order', 'a.title');
        $this->model->setState('filter_order_Dir', 'ASC');

        foreach ([
            'CREATE TABLE t_jem_events (id INTEGER, published INTEGER, locid INTEGER, type_id INTEGER, access INTEGER, publish_up TEXT, publish_down TEXT, parent_event_id INTEGER, show_in_calendar INTEGER, dates TEXT)',
            'CREATE TABLE t_jem_venues (id INTEGER, venue TEXT, city TEXT, country TEXT, published INTEGER)',
            'CREATE TABLE t_jem_countries (iso2 TEXT, name TEXT, continent TEXT, published INTEGER)',
            'CREATE TABLE t_jem_types (id INTEGER, name TEXT, entity INTEGER, published INTEGER, access INTEGER, ordering INTEGER, base_language TEXT, translations TEXT)',
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
    }

    /** @return void Type option IDs retain their scope while labels use exact translation or original text. */
    public function testTypeOptionsTranslateWithoutChangingIds(): void
    {
        $this->configure('published');
        Factory::$language->tag = 'es-ES';
        $translations = json_encode(['es-ES' => ['name' => 'Tipo traducido']]);
        $this->db->setQuery('UPDATE t_jem_types SET base_language = ' . $this->db->quote('en-GB')
            . ', translations = ' . $this->db->quote($translations) . ' WHERE id = 1')->execute();
        $types = $this->model->getEventTypeOptions();
        self::assertSame([1, 2, 3], array_map('intval', array_column($types, 'value')));
        self::assertSame(['Tipo traducido', 'Type 2', 'Type 3'], array_column($types, 'text'));
        Factory::$language->tag = 'fr-FR';
        self::assertSame(['Type 1', 'Type 2', 'Type 3'], array_column($this->model->getEventTypeOptions(), 'text'));
    }

    /** @param mixed $scope Stored setting. @param array $request Visitor input. @return void */
    private function configure(mixed $scope, array $request = []): void
    {
        $values = $scope === null ? [] : ['search_event_scope' => $scope];
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

    /** @return iterable<string, array{mixed, array, array}> Menu settings crossed with hostile request fields. */
    public static function scopes(): iterable
    {
        foreach (['published' => [1, 2, 3], 'archived' => [4, 5], 'all' => [1, 2, 3, 4, 5]] as $scope => $ids) {
            foreach ([
                'absent' => [],
                'archive request' => ['task' => 'archive', 'search_event_scope' => 'archived', 'filter_published' => 2],
                'all request' => ['task' => 'display', 'search_event_scope' => 'all', 'filter_published' => -2],
            ] as $label => $request) {
                yield "$scope / $label" => [$scope, $request, $ids];
            }
        }
        yield 'missing setting' => [null, ['task' => 'archive'], [1, 2, 3]];
        yield 'invalid setting' => ['invalid', ['task' => 'archive'], [1, 2, 3]];
    }

    /** @param mixed $scope Stored setting. @param array $request Visitor input. @param array $ids Expected IDs. @return void */
    #[DataProvider('scopes')]
    public function testResultsAndOptionQueriesUseOnlyMenuScope(mixed $scope, array $request, array $ids): void
    {
        $this->configure($scope, $request);
        // This test isolates event scope, starting location selection at Country.
        $this->app->params->set('filter_show_continent', 0);
        $this->app->params->set('filter_show_state', 0);
        self::assertSame($ids, $this->resultIds());
        foreach (['getVenueOptions', 'getEventTypeOptions'] as $method) {
            self::assertSame($ids, array_map(static fn ($row) => (int) $row->value, $this->model->$method()), $method);
        }
        $countries = [1 => 'ES', 2 => 'FR', 3 => 'DE', 4 => 'IT', 5 => 'PT'];
        self::assertSame(array_map(static fn ($id) => $countries[$id], $ids),
            array_column($this->model->getCountryOptions(), 'value'));
        foreach ([1 => 'ES', 4 => 'IT', 6 => 'GB', 7 => 'NL', 8 => 'IE', 9 => 'PL'] as $id => $country) {
            $this->app->input->set('filter_country', $country);
            self::assertSame(in_array($id, $ids, true) ? ['City ' . $id] : [],
                array_column($this->model->getCityOptions(), 'value'), $country);
        }
    }

    /** @return void Unfiltered type options must also honor the configured publication scope. */
    public function testTypeOptionsWithoutGeographicalOrCategoryFilters(): void
    {
        $this->configure('archived', ['filter_category' => 0]);
        self::assertSame([4, 5], array_map(static fn ($row) => (int) $row->value, $this->model->getEventTypeOptions()));
    }

    /** @return void Missing or unrelated menus cannot enable archive search via merged component/request params. */
    public function testMissingAndUnrelatedMenusFallBackToPublished(): void
    {
        $this->configure('all', ['task' => 'archive', 'search_event_scope' => 'all']);
        foreach ([null, ['option' => 'com_jem', 'view' => 'eventslist'], ['option' => 'com_content', 'view' => 'search']] as $query) {
            $this->app->active = $query === null ? null : new SearchScopeMenuItem($query, new Registry(['search_event_scope' => 'all']));
            self::assertSame([1, 2, 3], $this->resultIds());
        }
    }

    /** @return void Published/archive union must not bypass the existing event access constraint. */
    public function testAllScopePreservesAccessFiltering(): void
    {
        $this->configure('all');
        $this->db->setQuery('UPDATE #__jem_events SET access = 2 WHERE id IN (1,4)')->execute();
        self::assertSame([2, 3, 5], $this->resultIds());
    }

    /** @return void Another Search menu owns its own scope, even with previous request state. */
    public function testSwitchingMenuUsesItsOwnStoredConfiguration(): void
    {
        $this->configure('archived', ['task' => 'archive']);
        self::assertSame([4, 5], $this->resultIds());
        $this->app->active = new SearchScopeMenuItem(
            ['option' => 'com_jem', 'view' => 'search'],
            new Registry(['search_event_scope' => 'published'])
        );
        $this->app->active->id = 456;
        self::assertSame([1, 2, 3], $this->resultIds());
    }

    /** @return void Date fallback ordering follows the menu, not task=archive. */
    public function testDateTieBreakOrderingUsesMenuScope(): void
    {
        foreach (['published' => 'ASC', 'archived' => 'DESC', 'all' => 'ASC'] as $scope => $direction) {
            $this->configure($scope, ['task' => $scope === 'archived' ? 'display' : 'archive']);
            $order = (new ReflectionMethod(JemModelSearch::class, '_buildOrderBy'))->invoke($this->model);
            self::assertStringContainsString('a.dates ' . $direction, $order);
        }
    }

    /** @return void Pagination retains the Search menu without propagating the obsolete archive task. */
    public function testPaginationRetainsMenuAndDropsTask(): void
    {
        $this->configure('archived', ['task' => 'archive']);
        $pagination = $this->model->getPagination();
        self::assertSame(123, $pagination->getAdditionalUrlParam('Itemid'));
        self::assertNull($pagination->getAdditionalUrlParam('task'));
        self::assertSame(12, $pagination->limit);
        self::assertSame(12, $pagination->limitstart);
    }


    /** @return void Location filters remain AND constraints even when the scope includes an OR. */
    public function testLocationFiltersCannotBeBypassedByMixedScope(): void
    {
        foreach (['published' => [], 'archived' => [4], 'all' => [4]] as $scope => $ids) {
            $this->configure($scope, ['filter_country' => 'IT', 'filter_continent' => 'EU', 'task' => 'archive']);
            self::assertSame($ids, $this->resultIds());
            foreach (['getVenueOptions', 'getEventTypeOptions'] as $method) {
                self::assertSame($ids, array_map(static fn ($row) => (int) $row->value, $this->model->$method()));
            }
            $this->app->input->set('filter_continent', 'AF');
            self::assertSame([], $this->resultIds());
            foreach (['getCountryOptions', 'getVenueOptions', 'getEventTypeOptions'] as $method) {
                self::assertSame([], $this->model->$method(), $method);
            }
        }
    }

    /** @return void Archived rows must still belong to the selected category in mixed mode. */
    public function testCategoryFilterCannotBeBypassedByMixedScope(): void
    {
        $this->configure('all');
        $this->db->setQuery('UPDATE #__jem_cats_event_relations SET catid = 3 WHERE itemid = 5')->execute();
        self::assertSame([1, 2, 3, 4], $this->resultIds());
        self::assertSame([1, 2, 3, 4],
            array_map(static fn ($row) => (int) $row->value, $this->model->getEventTypeOptions()));
    }


    /** @return void The admin option is translated and no frontend scope/task control is introduced. */
    public function testMenuMetadataAndFrontendContract(): void
    {
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/site/views/search/tmpl/default.xml');
        $fields = $xml->xpath('//fields[@name="params"]//field[@name="search_event_scope"]');
        self::assertCount(1, $fields);
        self::assertSame('published', (string) $fields[0]['default']);
        self::assertSame(['published', 'archived', 'all'],
            array_map(static fn ($option) => (string) $option['value'], $fields[0]->xpath('option')));
        $language = parse_ini_file(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
        foreach (['label', 'description'] as $attribute) {
            self::assertArrayHasKey((string) $fields[0][$attribute], $language);
        }
        foreach ($fields[0]->xpath('option') as $option) {
            self::assertArrayHasKey((string) $option, $language);
        }
        foreach (['default.php', 'responsive/default.php'] as $layout) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/search/tmpl/' . $layout);
            self::assertStringNotContainsString('name="task"', $source);
            self::assertStringNotContainsString('name="search_event_scope"', $source);
        }
        $view = file_get_contents(JEM_TEST_ROOT . '/site/views/search/view.html.php');
        self::assertStringNotContainsString("getCmd('task'", $view);
        self::assertStringNotContainsString('view=search&task=archive', $view);
        self::assertStringContainsString('getEventSearchScope()', $view);
    }
}
