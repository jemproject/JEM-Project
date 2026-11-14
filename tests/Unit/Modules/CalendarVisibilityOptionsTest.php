<?php

declare(strict_types=1);

namespace JemProject\Tests\CalendarVisibility;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Real module configuration and event-ID predicate with an isolated SQL boundary. */
final class CalendarVisibilityOptionsTest extends TestCase
{
    /** @return void Load source methods and the production empty-calendar bounds helper. */
    public static function setUpBeforeClass(): void
    {
        $autoload = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\') . '/libraries/vendor/autoload.php';
        if (!is_file($autoload) || !extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT and pdo_sqlite.');
        }
        require_once $autoload;
        require_once JEM_TEST_ROOT . '/site/helpers/calendarnavigation.php';
        $source = file_get_contents(JEM_TEST_ROOT . '/modules/mod_jem_cal/helper.php');
        $methods = '';
        foreach (['createCalendarModel', 'getNavigationRange'] as $name) {
            self::assertSame(1, preg_match('/    (?:protected|public) static function ' . $name . '\(.*?^    \}/ms', $source, $match));
            $methods .= $match[0];
        }
        eval('namespace ' . __NAMESPACE__ . '; final class Module {' . $methods
            . 'public static function model($params, &$links) {return self::createCalendarModel($params, $links);}}');
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/eventslist.php');
        $start = strpos($source, '        # Filter by a single or group of events.');
        $end = strpos($source, '        ## FILTER-ACCESS ##', $start);
        self::assertIsInt($start);
        self::assertIsInt($end);
        Model::$eventFilter = substr($source, $start, $end - $start);
    }

    /** @return void Reset synthetic inherited state and the empty-calendar setting. */
    protected function setUp(): void
    {
        BaseDatabaseModel::$excludeIds = false;
        Model::$months = 3;
    }

    /** @return iterable<string,array{mixed,mixed,array,string}> All four options, as integers and stored strings. */
    public static function combinations(): iterable
    {
        foreach ([[0, 0, [], ''], [1, 0, [1], '&pub=1'], [0, 1, [2], '&pub=2'], [1, 1, [1, 2], '&pub=1,2']] as $case) {
            yield $case[0] . '/' . $case[1] . ' integers' => $case;
            yield $case[0] . '/' . $case[1] . ' strings' => [(string) $case[0], (string) $case[1], $case[2], $case[3]];
        }
    }

    /** @param mixed $current Published option. @param mixed $archive Archive option. @param array $ids Expected IDs. @param string $publication Day link scope. @return void */
    #[DataProvider('combinations')]
    public function testVisibilityOptions(mixed $current, mixed $archive, array $ids, string $publication): void
    {
        $params = new \Joomla\Registry\Registry(['CurrentEvents' => $current, 'ArchivedEvents' => $archive, 'catid' => [3], 'venid' => [4]]);
        $links = '';
        $model = Module::model($params, $links);
        self::assertSame($ids, $model->ids());
        self::assertSame('&catids=3&locids=4' . $publication, $links);
        self::assertSame([3], $model->state['filter.category_id']);
        self::assertSame([4], $model->state['filter.venue_id']);
        self::assertSame('a.id', $model->state['filter.groupby']);
    }

    /** @return void Missing options retain their existing published-only defaults. */
    public function testDefaultOptions(): void
    {
        $links = '';
        self::assertSame([1], Module::model(new \Joomla\Registry\Registry(), $links)->ids());
        self::assertSame('&pub=1', $links);
    }

    /** @return void Explicit empty selection cannot accidentally become exclusion of ID zero. */
    public function testBothOffOverridesAnInheritedExcludeFlag(): void
    {
        BaseDatabaseModel::$excludeIds = true;
        $links = '';
        $model = Module::model(new \Joomla\Registry\Registry(['CurrentEvents' => 0, 'ArchivedEvents' => 0]), $links);
        self::assertTrue($model->state['filter.event_id.include']);
        self::assertSame([], $model->ids());
    }

    /** @return void Both-off navigation uses the configured empty window, not distant event dates. */
    public function testEmptyNavigationWindow(): void
    {
        $params = new \Joomla\Registry\Registry(['CurrentEvents' => 0, 'ArchivedEvents' => 0]);
        self::assertSame(['start' => '2026-10-01', 'end' => '2026-12-31'], Module::getNavigationRange($params));
        Model::$months = 1;
        self::assertSame(['start' => '2026-11-01', 'end' => '2026-11-30'], Module::getNavigationRange($params));
        self::assertSame(['start' => '2031-01-01', 'end' => '2031-01-31'], Module::getNavigationRange(new \Joomla\Registry\Registry()));
    }
}

/** Substitute only model construction and unrelated venue/category lookup. */
final class BaseDatabaseModel
{
    public static bool $excludeIds = false;
    /** @param mixed ...$arguments Factory signature. @return Model Isolated state and SQL consumer. */
    public static function getInstance(...$arguments): Model { return new Model(); }
}

final class JemHelper
{
    /** @param mixed $ids Synthetic module ID selection. @return array */
    public static function getValidIds($ids): array { return (array) $ids; }
}

/** Minimal SQL consumer: preserve production event-ID filtering and record model scope. */
final class Model
{
    public static string $eventFilter;
    public static int $months = 3;
    public array $state;
    private PDO $db;

    /** Seed published, archived and unpublished records without accessing Joomla data. */
    public function __construct()
    {
        $this->state = ['filter.published' => 1, 'filter.event_id.include' => !BaseDatabaseModel::$excludeIds];
        $this->db = new PDO('sqlite::memory:');
        $this->db->exec("CREATE TABLE events(id INTEGER PRIMARY KEY,published INTEGER,dates TEXT); INSERT INTO events VALUES(1,1,'2031-01-01'),(2,2,'2032-02-01'),(3,0,'2033-03-01');");
    }
    /** @param string $key State name. @param mixed $value Value. @return void */
    public function setState($key, $value): void { $this->state[$key] = $value; }
    /** @param string $key State name. @param mixed $default Fallback. @return mixed */
    public function getState($key, $default = null) { return $this->state[$key] ?? $default; }
    /** @return array Visible fixture IDs. */
    public function ids(): array { return array_map('intval', array_column($this->rows(), 'id')); }
    /** @return array SQL result; publication is a fixture boundary, event-ID predicate is production code. */
    private function rows(): array
    {
        $query = new Predicates();
        eval('use Joomla\\Utilities\\ArrayHelper;' . self::$eventFilter);
        $where = array_merge(['a.published IN (' . implode(',', (array) $this->state['filter.published']) . ')'], $query->conditions);
        return $this->db->query('SELECT a.id,a.dates FROM events a WHERE ' . implode(' AND ', $where) . ' ORDER BY a.id')->fetchAll(PDO::FETCH_ASSOC);
    }
    /** @return array Production bounds computed from the isolated query's visible dates. */
    public function getCalendarNavigationRange(): array
    {
        $dates = array_column($this->rows(), 'dates');
        return \JemCalendarNavigation::bounds($dates ? min($dates) : null, $dates ? max($dates) : null, '2026-11-14', self::$months);
    }
}

final class Predicates
{
    public array $conditions = [];
    /** @param string $condition SQL predicate. @return self */
    public function where($condition): self { $this->conditions[] = $condition; return $this; }
}
