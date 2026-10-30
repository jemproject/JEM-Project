<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Execute real module getList methods; dependencies are doubles, date predicates run in SQLite. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TodayModuleOffsetTest extends TestCase
{
    /**
     * Reuse the isolated module dependencies; no installed Joomla files or data are used.
     *
     * @return void
     */
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/title_rendering_stubs.php';
        foreach (['wide', 'teaser', 'banner'] as $module) {
            require_once JEM_TEST_ROOT . '/modules/mod_jem_' . $module . '/helper.php';
        }
    }

    /**
     * Exercise helper wiring for signed offsets and several selected calendar dates.
     *
     * @return iterable Module, configured hour offset, helper result and matching fixture IDs.
     */
    public static function todayModules(): iterable
    {
        foreach (['wide', 'teaser', 'banner'] as $module) {
            foreach ([
                [0, '2026-10-29', [1, 2, 3, 5]],
                [1, '2026-10-30', [4, 5]],
                [11, '2026-10-30', [4, 5]],
                [12, '2026-10-29', [1, 2, 3, 5]],
                [23, '2026-10-29', [1, 2, 3, 5]],
                [24, '2026-10-30', [4, 5]],
                [48, '2026-10-31', [5, 8]],
                [-1, '2026-10-28', [5, 7]],
                [-11, '2026-10-28', [5, 7]],
                [-12, '2026-10-29', [1, 2, 3, 5]],
                [-24, '2026-10-28', [5, 7]],
            ] as [$hours, $target, $expected]) {
                yield $module . ' ' . $hours => [$module, $hours, $target, $expected];
            }
        }
    }

    /**
     * Confirm exact hours reach the shared helper and the returned date drives day-based SQL.
     *
     * @param string $module   Module suffix.
     * @param int    $hours    Configured hours.
     * @param string $target   Date supplied by the isolated helper dependency.
     * @param array  $expected Expected IDs from the actual generated date predicate.
     * @return void
     */
    #[DataProvider('todayModules')]
    public function testTodayUsesHourOffsetAndPreservesDayMatching(string $module, int $hours, string $target, array $expected): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires pdo_sqlite for isolated module date predicates.');
        }
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE events (id INTEGER, dates TEXT, enddates TEXT, times TEXT, endtimes TEXT, published INTEGER)');
        $insert = $db->prepare('INSERT INTO events VALUES (?, ?, ?, ?, ?, ?)');
        foreach ([
            [1, '2026-10-29', null, '08:00:00', '09:00:00', 1],
            [2, '2026-10-29', null, '22:00:00', '23:00:00', 1],
            [3, '2026-10-29', '2026-10-29', null, null, 1],
            [4, '2026-10-30', null, null, null, 1],
            [5, '2026-10-28', '2026-10-31', null, null, 1],
            [6, null, null, null, null, 1],
            [7, '2026-10-28', null, null, null, 1],
            [8, '2026-10-31', null, null, null, 1],
            [9, '2026-10-29', null, null, null, 2],
            [10, '2026-10-29', null, null, null, 0],
        ] as $row) {
            $insert->execute($row);
        }
        Factory::$database = $db;
        JemHelper::$hourOffsetDate = $target;
        $matched = null;
        BaseDatabaseModel::$loadItems = static function () use ($db, &$matched): array {
            $state = BaseDatabaseModel::$states;
            $sql = 'SELECT a.id FROM events a WHERE ' . $state['filter.calendar_from']
                . ' AND a.published = ' . (int) $state['filter.published'] . ' ORDER BY a.id';
            $matched = array_map('intval', $db->query($sql)->fetchAll(PDO::FETCH_COLUMN));
            return []; // Presentation dependencies are outside the date-filter regression.
        };
        $params = new TitleRenderingParams(['type' => 3, 'offset_hours' => $hours, 'count' => 20, 'show_status_indicators' => 0]);
        $helper = 'ModJem' . ucfirst($module) . 'Helper';
        self::assertSame([], $helper::getList($params));
        self::assertSame([$hours], JemHelper::$hourOffsets);
        self::assertSame($expected, $matched);
        self::assertSame(1, BaseDatabaseModel::$states['filter.published']);
        self::assertSame(20, BaseDatabaseModel::$states['list.limit']);
        self::assertSame('a.id', BaseDatabaseModel::$states['filter.groupby']);
        self::assertStringContainsString($db->quote($target), BaseDatabaseModel::$states['filter.calendar_from']);
    }

    /**
     * Enumerate existing non-Today modes without changing their event-time rules.
     *
     * @return iterable Module and mode pairs.
     */
    public static function otherModes(): iterable
    {
        foreach (['wide', 'teaser', 'banner'] as $module) {
            foreach ([0, 1, 2] as $type) {
                yield $module . ' ' . $type => [$module, $type];
            }
        }
        yield 'teaser featured' => ['teaser', 4];
        yield 'banner open dates' => ['banner', 5];
    }

    /**
     * No other mode may use the new date helper or reinterpret its existing minute offset.
     *
     * @param string $module Module suffix.
     * @param int    $type   Existing selection mode.
     * @return void
     */
    #[DataProvider('otherModes')]
    public function testOtherModesKeepTheirExistingOffsetPolicy(string $module, int $type): void
    {
        $params = new TitleRenderingParams(['type' => $type, 'offset_hours' => -11, 'show_status_indicators' => 0]);
        $helper = 'ModJem' . ucfirst($module) . 'Helper';
        self::assertSame([], $helper::getList($params));
        self::assertSame([], JemHelper::$hourOffsets);
        if (in_array($type, [0, 1, 4], true)) {
            self::assertNotEmpty(JemHelper::$minuteOffsets);
            self::assertSame([-660], array_values(array_unique(JemHelper::$minuteOffsets)));
        } else {
            self::assertSame([], JemHelper::$minuteOffsets);
        }
        if ($type === 2) {
            self::assertSame(2, BaseDatabaseModel::$states['filter.published']);
        }
    }
}
