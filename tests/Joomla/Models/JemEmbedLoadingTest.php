<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\Mysqli\MysqliDriver;
use Joomla\Input\Input;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Real Embed loading/response code and Joomla SQL quoting; no installed site data is modified. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class JemEmbedLoadingTest extends TestCase
{
    private MysqliDriver $db;

    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT for Joomla database and input classes.');
        }

        require_once $root . '/libraries/vendor/autoload.php';
        require_once JEM_TEST_ROOT . '/tests/Support/title_rendering_stubs.php';
        require_once JEM_TEST_ROOT . '/plugins/plg_content_jemembed/jemembed.php';

        $this->db = new MysqliDriver([
            'host' => getenv('JEM_TEST_EMBED_MYSQL_HOST') ?: '127.0.0.1',
            'user' => getenv('JEM_TEST_EMBED_MYSQL_USER') ?: 'root',
            'password' => getenv('JEM_TEST_EMBED_MYSQL_PASSWORD') ?: '',
            'database' => '',
            'select' => false,
        ]);
        Factory::$database = $this->db;
    }

    #[DataProvider('feedModes')]
    public function testLoadingKeepsGuestFiltersAndQueryBounds(string $mode, array $published): void
    {
        $plugin = $this->plugin([
            'type' => $mode, 'catids' => '2,3', 'venueids' => '4,5',
            'featured' => 'only', 'max' => '2', 'start' => '3',
        ]);
        $response = $plugin->onAjaxJemembed();
        $state = BaseDatabaseModel::$states;

        self::assertTrue($response['success']);
        self::assertSame([1], $state['filter.access_levels']);
        self::assertTrue($state['filter.strict_access']);
        self::assertSame($published, (array) $state['filter.published']);
        self::assertSame([2, 3], $state['filter.category_id']);
        self::assertSame(1, $state['filter.category_id.include']);
        self::assertSame([4, 5], $state['filter.venue_id']);
        self::assertSame(1, $state['filter.venue_id.include']);
        self::assertSame(1, $state['filter.featured']);
        self::assertSame(2, $state['list.limit']);
        self::assertSame(3, $state['list.start']);
        self::assertSame(JemEmbedRequestPolicy::MAX_DESCRIPTION_LENGTH + 1, $state['list.content_limit']);
        self::assertTrue($state['list.compact_select']);
        self::assertTrue($state['list.skip_attendee_numbers']);
        self::assertStringContainsString(
            'LEFT(' . $this->db->quoteName('a.fulltext') . ', ' . $state['list.content_limit']
                . ') AS ' . $this->db->quoteName('fulltext'),
            $state['list.select']
        );
    }

    public static function feedModes(): iterable
    {
        foreach (['all', 'newest', 'upcoming', 'unfinished', 'today', 'ongoing', 'open', 'archived'] as $mode) {
            yield $mode => [$mode, $mode === 'all' ? [1, 2] : ($mode === 'archived' ? [2] : [1])];
        }
    }

    #[DataProvider('failureModes')]
    public function testQueryFailuresArePrivateAndNeverCached(string $mode): void
    {
        $message = 'SQL failure: private_table.fulltext, internal query details';
        BaseDatabaseModel::$loadItems = static function () use ($mode, $message): array|false {
            if ($mode === 'exception') {
                throw new RuntimeException($message);
            }

            BaseDatabaseModel::$error = $mode === 'false without detail' ? '' : $message;
            return $mode === 'empty with error' ? [] : false;
        };
        $plugin = $this->plugin(['type' => 'all']);
        $response = $plugin->onAjaxJemembed();

        self::assertSame(['success' => false, 'error' => 'Request could not be processed.'], $response);
        self::assertSame([], $plugin->cache);
        self::assertCount(1, $plugin->logs);
        self::assertStringNotContainsString('private_table', json_encode($response));
        if ($mode !== 'false without detail') {
            self::assertStringContainsString($message, $plugin->logs[0]);
        }
    }

    public static function failureModes(): iterable
    {
        foreach (['empty with error', 'false with error', 'false without detail', 'exception'] as $mode) {
            yield $mode => [$mode];
        }
    }

    public function testARealEmptyResultRemainsSuccessfulAndCacheable(): void
    {
        $loads = 0;
        BaseDatabaseModel::$loadItems = static function () use (&$loads): array {
            ++$loads;
            return [];
        };
        $plugin = $this->plugin(['type' => 'all']);
        $first = $plugin->onAjaxJemembed();

        self::assertTrue($first['success']);
        self::assertSame(0, $first['meta']['count']);
        self::assertSame([], $first['data']);
        self::assertSame($first, $plugin->onAjaxJemembed());
        self::assertSame(1, $loads);
        self::assertCount(1, $plugin->cache);
        self::assertSame([], $plugin->logs);
    }

    public function testOldCachedEmptyFeedDoesNotHideTheFixedQuery(): void
    {
        $plugin = $this->plugin(['type' => 'all']);
        $oldResponse = $plugin->onAjaxJemembed();
        $oldKey = hash('sha256', json_encode([
            $oldResponse['meta']['parameters'], 'https://example.test', 'en-GB',
        ]));
        $plugin->cache = [$oldKey => $oldResponse];
        BaseDatabaseModel::$items = [$this->event()];
        $response = $plugin->onAjaxJemembed();

        self::assertTrue($response['success']);
        self::assertSame(1, $response['meta']['count']);
        self::assertSame(7, $response['data'][0]['id']);
        self::assertSame($response, $plugin->onAjaxJemembed());
        self::assertCount(2, $plugin->cache);
    }

    #[DataProvider('sqlCases')]
    public function testGeneratedSelectRunsOnMysqlWithoutWritingTables(string $mode, int $length): void
    {
        if (!getenv('JEM_TEST_EMBED_MYSQL_HOST')) {
            self::markTestSkipped('Set JEM_TEST_EMBED_MYSQL_HOST to run read-only MySQL/MariaDB SELECT tests.');
        }

        $text = str_repeat('é', $length);
        $selected = [];
        BaseDatabaseModel::$loadItems = function () use ($text, &$selected): array {
            $row = $this->event();
            $row->introtext = $text;
            $row->fulltext = $text;
            $columns = [];
            foreach ([
                'alias', 'article_id', 'attribs', 'created_by', 'dates', 'enddates', 'endtimes',
                'featured', 'id', 'introtext', 'fulltext', 'locid', 'times', 'title',
            ] as $name) {
                $value = $row->$name ?? null;
                $columns[] = ($value === null ? 'NULL' : $this->db->quote((string) $value))
                    . ' AS ' . $this->db->quoteName($name);
            }

            // The exact SELECT produced by _load(), evaluated against synthetic values only.
            $sql = 'SELECT ' . BaseDatabaseModel::$states['list.select']
                . ' FROM (SELECT ' . implode(', ', $columns) . ') AS a';
            $selected = $this->db->setQuery($sql)->loadObjectList();
            foreach ($selected as $item) {
                $item->slug = '7-event';
                $item->venueslug = '';
                $item->venue = '';
                $item->categories = [];
            }
            return $selected;
        };
        $plugin = $this->plugin(['type' => $mode]);
        $response = $plugin->onAjaxJemembed();
        $limit = JemEmbedRequestPolicy::MAX_DESCRIPTION_LENGTH + 1;

        self::assertTrue($response['success'], implode('; ', $plugin->logs));
        self::assertCount(1, $selected);
        self::assertSame(min($length, $limit), mb_strlen($selected[0]->introtext, 'UTF-8'));
        self::assertSame(min($length, $limit), mb_strlen($selected[0]->fulltext, 'UTF-8'));
        self::assertSame(1, $response['meta']['count']);
        self::assertSame('A & B', $response['data'][0]['title']['full']);
        self::assertSame([], $plugin->logs);
    }

    public static function sqlCases(): iterable
    {
        foreach (['all', 'newest', 'upcoming'] as $mode) {
            foreach ([0, 12, 6000] as $length) {
                yield $mode . ' length=' . $length => [$mode, $length];
            }
        }
    }

    private function event(): object
    {
        return (object) [
            'id' => 7, 'title' => 'A & B', 'slug' => '7-event', 'venueslug' => '',
            'dates' => '2030-01-01', 'enddates' => null, 'times' => null, 'endtimes' => null,
            'categories' => [], 'venue' => '', 'featured' => 0, 'introtext' => '',
            'alias' => 'event', 'article_id' => 0, 'attribs' => '{}', 'created_by' => 0, 'locid' => 0,
        ];
    }

    private function plugin(array $query): object
    {
        Factory::$application->input = new Input($query);

        return new class extends PlgContentJemembed {
            public array $cache = [];
            public array $logs = [];

            public function __construct()
            {
            }

            protected function validateToken()
            {
                return '';
            }

            protected function getSiteDomain()
            {
                return 'https://example.test';
            }

            protected function consumeRateLimit(string $identity, int $limit): bool
            {
                return true;
            }

            protected function getCachedResponse(string $cacheKey)
            {
                return $this->cache[$cacheKey] ?? null;
            }

            protected function storeCachedResponse(string $cacheKey, array $response): void
            {
                $this->cache[$cacheKey] = $response;
            }

            protected function logInternal(string $message, int $level): void
            {
                $this->logs[] = $message;
            }
        };
    }
}
