<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ModJemTypesTopNOrderingTest extends TestCase
{
    public function testTopNOrdersByDateTimeAndStableIdBeforeApplyingLimit(): void
    {
        $source = (string) file_get_contents(JEM_TEST_ROOT . '/modules/mod_jem_types/helper.php');
        self::assertSame(
            1,
            preg_match('/->order\(array\((.*?)\)\)\s*->setLimit\(\$n\)/s', $source, $order)
        );
        preg_match_all('/\$db->quoteName\(\'a\.(dates|times|id)\'\) \. \' ASC\'/s', $order[1], $fields);

        self::assertSame(array('dates', 'times', 'id'), $fields[1]);
    }

    public function testTimedEventsAreSelectedChronologicallyDespiteReverseInsertionOrder(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('PDO SQLite is required for the ordering fixture.');
        }

        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE events (id INTEGER PRIMARY KEY, dates TEXT, times TEXT)');
        $db->exec(
            "INSERT INTO events VALUES (1, '2026-11-05', '20:00:00'), (2, '2026-11-05', '08:00:00'), "
            . "(3, '2026-11-05', '08:00:00'), (4, '2026-11-06', '07:00:00')"
        );

        self::assertSame(array(2), $this->eventIds($db, 1));
        self::assertSame(array(2, 3, 1), $this->eventIds($db, 3));

        $db->exec(
            "INSERT INTO events VALUES (5, '2026-11-05', NULL), (6, '2026-11-05', NULL), "
            . "(7, '2026-11-05', '00:00:00')"
        );

        self::assertSame(array(5, 6, 7, 2), $this->eventIds($db, 4));
    }

    private function eventIds(PDO $db, int $limit): array
    {
        $statement = $db->query(
            'SELECT a.id FROM events AS a ORDER BY a.dates ASC, a.times ASC, a.id ASC LIMIT ' . $limit
        );

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }
}
