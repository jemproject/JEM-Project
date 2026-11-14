<?php

declare(strict_types=1);

namespace JemProject\Tests\CategoryExclusion;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Execute the actual category/event filter blocks against isolated SQL data. */
final class EventslistCategoryExclusionTest extends TestCase
{
    private PDO $db;

    /** @return void Load only source filter methods; no Joomla application or installed data is changed. */
    public static function setUpBeforeClass(): void
    {
        $autoload = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\') . '/libraries/vendor/autoload.php';
        if (!is_file($autoload) || !extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT and pdo_sqlite.');
        }
        require_once $autoload;
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/eventslist.php');
        $start = strpos($source, '        # Filter by a single or group of categories.');
        $end = strpos($source, '        # filter set by day-view', $start);
        self::assertIsInt($start);
        self::assertIsInt($end);
        $categoryCode = substr($source, $start, $end - $start);
        $start = strpos($source, "        \$filter_catid = \$this->getState('filter.filter_catid');");
        $end = strpos($source, '        ## FILTER - BYLOC ##', $start);
        self::assertIsInt($start);
        self::assertIsInt($end);
        $eventCode = substr($source, $start, $end - $start);
        self::assertSame(1, preg_match('/    protected function normaliseParamIds\(.*?^    \}/ms', $source, $ids));
        self::assertSame(1, preg_match('/    protected function applyMenuEventFilters\(.*?^    \}/ms', $source, $menu));
        eval('namespace ' . __NAMESPACE__ . '; use Joomla\\Utilities\\ArrayHelper;'
            . 'final class Scope extends Boundary {' . $ids[0] . $menu[0]
            . 'public function categories(): array {$query=new Predicates();' . $categoryCode . 'return $query->conditions;}'
            . 'public function events(): array {$query=new Predicates();' . $eventCode . 'return $query->conditions;}'
            . 'public function menu($params): void {$this->applyMenuEventFilters($params);}}');
    }

    /** @return void Seed five events, including multiple categories and one descendant category. */
    protected function setUp(): void
    {
        $mysql = getenv('JEM_TEST_CATEGORY_MYSQL') === '1';
        if ($mysql) {
            require_once rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\') . '/configuration.php';
            $config = new \JConfig();
            self::assertContains($config->host, ['localhost', '127.0.0.1']);
            $this->db = new PDO('mysql:host=' . $config->host . ';dbname=' . $config->db . ';charset=utf8mb4', $config->user, $config->password);
        } else {
            $this->db = new PDO('sqlite::memory:');
        }
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $create = $mysql ? 'CREATE TEMPORARY TABLE ' : 'CREATE TABLE ';
        $this->db->exec($create . 'events(id INTEGER PRIMARY KEY, dates TEXT); ' . $create . 'categories(id INTEGER PRIMARY KEY);'
            . $create . 'relations(itemid INTEGER,catid INTEGER,UNIQUE(catid,itemid)); CREATE INDEX rel_item ON relations(itemid);');
        $this->db->exec("INSERT INTO events VALUES(1,'2031-01-01'),(2,'2031-02-01'),(3,'2031-03-01'),(4,'2031-04-01'),(5,'2031-05-01');"
            . 'INSERT INTO categories VALUES(1),(2),(3),(4); INSERT INTO relations VALUES(1,1),(1,2),(2,1),(3,2),(4,3),(5,4),(5,2);');
    }

    /** @return iterable<string,array{mixed,bool,array}> Category A=1, B=2, other=3, child of A=4. */
    public static function filters(): iterable
    {
        yield 'exclude A' => [[1], false, [3, 4, 5]];
        yield 'exclude B' => [[2], false, [2, 4]];
        yield 'exclude A and B' => [[1, 2], false, [4]];
        yield 'include A' => [[1], true, [1, 2]];
        yield 'include B' => [[2], true, [1, 3, 5]];
        yield 'include A and B' => [[1, 2], true, [1, 2, 3, 5]];
        yield 'no excluded categories' => [[], false, [1, 2, 3, 4, 5]];
        yield 'scalar excluded category' => [1, false, [3, 4, 5]];
        yield 'duplicate and nonpositive ids' => [[1, 1, 0, -1], false, [3, 4, 5]];
        yield 'exclude all' => [[1, 2, 3, 4], false, []];
        yield 'unrelated category' => [[99], false, [1, 2, 3, 4, 5]];
    }

    /**
     * @param mixed $ids Selected categories.
     * @param bool $include Inclusion or exclusion.
     * @param array $expected Visible event IDs.
     * @return void Verify event results, totals, pagination and date extents use the same exclusion.
     */
    #[DataProvider('filters')]
    public function testEventExclusion(mixed $ids, bool $include, array $expected): void
    {
        $scope = new Scope($this->db, ['filter.category_id' => $ids, 'filter.category_id.include' => $include]);
        $this->assertResults($scope, $expected);
    }

    /** @return void The real menu-policy method expands descendants only when requested. */
    public function testMenuSubcategoryExpansion(): void
    {
        foreach ([false, true] as $children) {
            $scope = new Scope($this->db, []);
            $scope->menu(new \Joomla\Registry\Registry(['categoryswitch' => 0, 'categoryswitchcats' => [1],
                'includesubcategories' => $children]));
            self::assertSame($children ? [1, 4] : [1], $scope->state['filter.category_id']);
            $this->assertResults($scope, $children ? [3, 4] : [3, 4, 5]);
        }
    }

    /** @return void A category-calendar row restriction cannot override an event exclusion. */
    public function testCategoryCalendarRestriction(): void
    {
        $scope = new Scope($this->db, ['filter.category_id' => [1], 'filter.category_id.include' => false,
            'filter.filter_catid' => 2]);
        $this->assertResults($scope, [3, 5]);
    }

    /** @param Scope $scope Actual filter blocks. @param array $expected Visible IDs. @return void */
    private function assertResults(Scope $scope, array $expected): void
    {
        $where = $scope->events();
        $sql = 'SELECT DISTINCT a.id,a.dates FROM events a LEFT JOIN relations rel ON rel.itemid=a.id'
            . ' LEFT JOIN categories c ON c.id=rel.catid';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql = str_replace('#__jem_cats_event_relations', 'relations', $sql);
        self::assertSame($expected, array_map('intval', $this->db->query($sql . ' ORDER BY a.id')->fetchAll(PDO::FETCH_COLUMN)));
        self::assertSame(count($expected), (int) $this->db->query('SELECT COUNT(*) FROM (' . $sql . ') q')->fetchColumn());
        self::assertSame(array_slice($expected, 1, 1), array_map('intval', $this->db->query($sql . ' ORDER BY a.id LIMIT 1 OFFSET 1')->fetchAll(PDO::FETCH_COLUMN)));
        $dates = $this->db->query('SELECT MIN(dates),MAX(dates) FROM (' . $sql . ') q')->fetch(PDO::FETCH_NUM);
        self::assertSame($expected ? sprintf('2031-%02d-01', min($expected)) : null, $dates[0]);
        self::assertSame($expected ? sprintf('2031-%02d-01', max($expected)) : null, $dates[1]);
    }
}

/** Capture the production SQL builder predicates without duplicating their logic. */
final class Predicates
{
    public array $conditions = [];
    /** @param string $sql Predicate. @return self */
    public function where(string $sql): self { $this->conditions[] = $sql; return $this; }
}

/** Minimal application/state boundaries; SQL filters and menu expansion come from the source. */
class Boundary
{
    /** @param PDO $db Isolated connection. @param array $state Model state. */
    public function __construct(public PDO $db, public array $state) {}
    /** @param string $key State name. @param mixed $default Fallback. @return mixed */
    public function getState($key, $default = null) { return $this->state[$key] ?? $default; }
    /** @param string $key State name. @param mixed $value Value. @return void */
    public function setState($key, $value): void { $this->state[$key] = $value; }
    /** @param string $scope Category selector. @return array Independently queried category IDs. */
    public function getCategories($scope): array
    {
        $where = $this->categories();
        return $this->db->query('SELECT c.id FROM categories c' . ($where ? ' WHERE ' . implode(' AND ', $where) : ''))->fetchAll(PDO::FETCH_COLUMN);
    }
    /** @param int $id Parent category. @param bool $unused Existing method flag. @return array Fixture descendants. */
    public function getListChildCat($id, $unused): array { return $id === 1 ? [4] : []; }
    /** @param mixed $value Empty geographic menu option. @return array */
    protected function normaliseParamStrings($value): array { return []; }
    /** @param mixed $params Unselected contact options. @return void Leave unrelated contact filters inactive. */
    protected function applyMenuContactFilters($params): void {}
}
