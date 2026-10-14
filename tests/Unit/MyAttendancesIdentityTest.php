<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Executes production SELECT/slug methods with disposable SQLite, not a live Joomla database. */
final class MyAttendancesIdentityTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $model = file_get_contents(JEM_TEST_ROOT . '/site/models/myattendances.php');
        $helper = file_get_contents(JEM_TEST_ROOT . '/site/helpers/helper.php');
        $extract = static function (string $source, string $name): string {
            if (!preg_match('/((?:static public|protected) function ' . $name . '\(.*?)(?=\r?\n    \/\*\*)/s', $source, $matches)) {
                throw new RuntimeException('Production method not found: ' . $name);
            }

            return $matches[1];
        };
        eval('final class AttendanceIdentityQuery {
            protected function _buildAttendingWhere() { return ""; }
            protected function _buildOrderByAttending() { return ""; }
            public function sql() { return $this->_buildQueryAttending(); }
            ' . $extract($model, '_buildQueryAttending') . '}');
        eval('final class AttendanceIdentityContent {'
            . $extract($helper, 'applyAssociatedArticleEventContent')
            . $extract($helper, 'applyAssociatedArticleEventContentToEvents') . '}');
    }

    #[DataProvider('identities')]
    public function testVenueNeverReplacesEventIdentity(?int $venueId, string $alias): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires PDO SQLite; no installed Joomla data is used.');
        }
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->sqliteCreateFunction('CHAR_LENGTH', static fn ($value) => strlen((string) $value));
        $db->sqliteCreateFunction('CONCAT_WS', static fn ($separator, ...$values) => implode($separator,
            array_filter($values, static fn ($value) => $value !== null)));
        $sql = str_replace('#__', 't_', (new AttendanceIdentityQuery())->sql());

        // Discover the actual selected/joined columns instead of duplicating the production SELECT.
        foreach (['a' => 'events', 'l' => 'venues', 'r' => 'register',
            'rel' => 'cats_event_relations', 'c' => 'categories'] as $tableAlias => $table) {
            preg_match_all('/\b' . $tableAlias . '\.([a-zA-Z_][a-zA-Z_0-9]*)/', $sql, $matches);
            $columns = array_unique($matches[1]);
            $definitions = array_map(static fn ($column) => '"' . $column . '" '
                . (in_array($column, ['id', 'event', 'locid', 'itemid', 'catid', 'article_id'], true) ? 'INTEGER' : 'TEXT'), $columns);
            $db->exec('CREATE TABLE t_jem_' . $table . ' (' . implode(', ', $definitions) . ')');
        }
        $db->prepare('INSERT INTO t_jem_events (id, locid, alias, article_id, title) VALUES (94, ?, ?, 0, ?)')
            ->execute([$venueId ?? 0, $alias, 'Saturday operations']);
        if ($venueId !== null) {
            $db->prepare('INSERT INTO t_jem_venues (id, alias, venue) VALUES (?, ?, ?)')
                ->execute([$venueId, 'airfield', 'Airfield']);
        }
        $db->exec("INSERT INTO t_jem_register (event, status) VALUES (94, '1')");
        $db->exec('INSERT INTO t_jem_cats_event_relations (itemid, catid) VALUES (94, 3)');
        $db->exec('INSERT INTO t_jem_categories (id) VALUES (3)');

        $row = $db->query($sql)->fetchObject();
        self::assertSame(94, $row->eventid);
        self::assertSame(94, $row->id, 'Event links and attendance controls must use the event ID, not the venue ID.');
        self::assertSame($venueId, $row->l_id);
        $expectedSlug = $alias !== '' ? '94:' . $alias : '94';
        self::assertSame($expectedSlug, (string) $row->slug);
        AttendanceIdentityContent::applyAssociatedArticleEventContentToEvents([$row], [1]);
        self::assertSame($expectedSlug, (string) $row->slug, 'Content processing must not change the event identity.');
        self::assertSame(94, $row->id);
    }

    public static function identities(): iterable
    {
        foreach ([1, 94, null] as $venue) {
            foreach (['ybss-saturday-ops', ''] as $alias) {
                yield 'venue-' . ($venue ?? 'none') . '-alias-' . ($alias !== '' ? 'yes' : 'no') => [$venue, $alias];
            }
        }
    }
}
