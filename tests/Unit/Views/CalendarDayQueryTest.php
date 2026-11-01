<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Views;

use PDO;
use PHPUnit\Framework\TestCase;

/** Execute Day's actual predicates against an isolated, in-memory event fixture. */
final class CalendarDayQueryTest extends TestCase
{
    private PDO $db;

    /** @return void Synthetic rows only; no Joomla database is opened. */
    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->sqliteCreateFunction('IF', static fn ($condition, $yes, $no) => $condition ? $yes : $no, 3);
        $this->db->exec('CREATE TABLE events(id INTEGER, dates TEXT, enddates TEXT, locid INTEGER, published INTEGER, access INTEGER)');
        $this->db->exec('CREATE TABLE jem_cats_event_relations(itemid INTEGER, catid INTEGER)');
        for ($id = 1; $id <= 13; ++$id) {
            $this->db->exec("INSERT INTO events VALUES($id,'2027-01-15','2027-01-15',9,2,1)");
            $this->db->exec("INSERT INTO jem_cats_event_relations VALUES($id,7)");
        }
        $this->db->exec("INSERT INTO events VALUES(14,'2027-01-15',NULL,9,1,1),
            (15,'2027-01-15',NULL,9,2,2), (16,'2027-01-14','2027-01-16',9,2,1),
            (17,'2027-01-15',NULL,10,2,1)");
        // Multiple categories: hiding either category must hide the event.
        $this->db->exec('INSERT INTO jem_cats_event_relations VALUES(1,8),(16,7),(17,7)');
    }

    /** @return void Load production methods, replacing only the parent query/database boundary. */
    public static function setUpBeforeClass(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/day.php');
        $methods = '';
        foreach (['getListQuery', 'applyDayScope', 'getStoreId', 'getDayEnd'] as $name) {
            self::assertSame(1, preg_match('/(?:protected|private|public) function ' . $name
                . '\([^\n]*\)[^\n]*\s*\{.*?^    \}/ms', $source, $match), $name);
            $methods .= $match[0];
        }
        eval('namespace ' . __NAMESPACE__ . '; class CalendarDayQueryFixture extends CalendarDayQueryBoundary {'
            . $methods . 'public function sql() { return $this->getListQuery(); } public function key() { return $this->getStoreId(); }}');
    }

    /** @param array $state Additional scope. @param int $start Offset. @param int $limit Page size. @return array Matching IDs. */
    private function ids(array $state = [], int $start = 0, int $limit = 100): array
    {
        $model = new CalendarDayQueryFixture($this->db, $state);
        $where = str_replace('#__', '', implode(' AND ', $model->sql()->where));
        return array_map('intval', $this->db->query(
            'SELECT a.id FROM events a WHERE ' . $where . ' ORDER BY a.id LIMIT ' . $limit . ' OFFSET ' . $start
        )->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return void Overflow still returns the 13 archived records, with no published/private leakage. */
    public function testArchivePaginationMatchesTheCalendarPopulation(): void
    {
        $state = ['filter.calendar_startdayonly' => true, 'filter.req_venid' => 9];
        self::assertSame(range(1, 13), $this->ids($state));
        self::assertSame(range(1, 10), $this->ids($state, 0, 10));
        self::assertSame([11, 12, 13], $this->ids($state, 10, 10));
        self::assertSame([], $this->ids($state, 20, 10));
    }

    /** @return void Start-only and legend exclusions only narrow the parent ACL/publication result. */
    public function testDateAndLegendRestrictions(): void
    {
        self::assertSame(array_merge(range(1, 13), [16]), $this->ids(['filter.req_venid' => 9]));
        self::assertSame(range(2, 13), $this->ids(['filter.req_venid' => 9,
            'filter.calendar_startdayonly' => true, 'filter.calendar_excluded_categories' => '8']));
        self::assertSame([], $this->ids(['filter.calendar_excluded_categories' => '7']));
        self::assertSame([17], $this->ids(['filter.calendar_excluded_venues' => '9']));
        self::assertSame([], $this->ids(['filter.calendar_hide_all' => true]));
    }

    /** @return void Totals/items cannot reuse a cached result with different legend exclusions or date. */
    public function testCacheKeyIncludesDayContext(): void
    {
        $model = new CalendarDayQueryFixture($this->db);
        $base = $model->key();
        $model->state['filter.calendar_excluded_categories'] = '8';
        self::assertNotSame($base, $model->key());
        $model->state = [];
        $model->_date = '2027-01-16';
        self::assertNotSame($base, $model->key());
    }
}

/** Minimal parent boundary; production predicates are appended to immutable guest/archive conditions. */
class CalendarDayQueryBoundary
{
    public string $_date = '2027-01-15';
    public string $_dateEnd = '2027-01-15';
    public function __construct(public PDO $_db, public array $state = []) {}
    public function getState($key, $default = null) { return $this->state[$key] ?? $default; }
    protected function getStoreId($id = '') { return $id; }
    protected function getListQuery()
    {
        return new class {
            public array $where = ['a.published = 2', 'a.access = 1'];
            public function where($sql) { $this->where[] = '(' . $sql . ')'; return $this; }
        };
    }
}

