<?php

declare(strict_types=1);

use JemProject\Tests\EventsBlogPagination\Application;
use JemProject\Tests\EventsBlogPagination\Factory;
use JemProject\Tests\EventsBlogPagination\Model;
use JemProject\Tests\EventsBlogPagination\Values;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventsBlogPaginationTest extends TestCase
{
    /** @return void Load production state/filter methods with isolated framework dependencies. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/eventsblog_pagination_environment.php';
        $methods = '';
        foreach ([
            'eventslist' => ['normaliseParamIds', 'normaliseParamStrings'],
            'eventsblog' => ['populateState', 'limitSelection', 'getPeriodBounds'],
        ] as $model => $names) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/models/' . $model . '.php');
            foreach ($names as $name) {
                self::assertSame(1, preg_match(
                    '/(?:public|protected|private) function ' . preg_quote($name, '/')
                    . '\\([^\\n]*\\)\\s*\\{.*?^    \\}/ms',
                    $source,
                    $match
                ), $name);
                $methods .= $match[0] . "\n";
            }
        }
        eval('namespace JemProject\\Tests\\EventsBlogPagination; class Model extends ModelDependencies {'
            . $methods . '}');
    }

    /**
     * Execute the actual state method for one menu/request.
     *
     * @param array<string, mixed> $params Menu options.
     * @param array<string, mixed> $request Request values.
     * @return array<string, mixed> Effective model state.
     */
    private function visit(array $params, array $request): array
    {
        Factory::$application = new Application(new Values($request), new Values($params));

        return (new Model())->state;
    }

    /** @return iterable<string, array{array, int}> Defaults, supported sizes and existing bounds. */
    public static function menuSizes(): iterable
    {
        yield 'default' => [[], 12];
        foreach ([1 => 1, 12 => 12, 24 => 24, 100 => 100, 0 => 1, -10 => 1, 101 => 100] as $value => $expected) {
            yield 'configured ' . $value => [['blog_events_per_page' => $value], $expected];
        }
        yield 'numeric menu string' => [['blog_events_per_page' => '18'], 18];
    }

    /** @param array $params Menu options. @param int $expected Effective size. @return void */
    #[DataProvider('menuSizes')]
    public function testMenuSizeWinsOnEveryPage(array $params, int $expected): void
    {
        foreach ([null, 1, 0, -1, 5, 500, 100000, 'invalid'] as $requestedLimit) {
            foreach ([0, $expected, $expected * 2] as $offset) {
                $request = ['limitstart' => $offset];
                if ($requestedLimit !== null) {
                    $request['limit'] = $requestedLimit;
                }
                $state = $this->visit($params, $request);
                self::assertSame($expected, $state['list.limit']);
                self::assertSame($offset, $state['list.start']);
            }
        }
    }

    /** @return void Verify consecutive pages select distinct fixture records without a URL limit. */
    public function testNavigationUsesConfiguredSizeAndOffsetOnly(): void
    {
        $events = range(1, 25);
        $pages = [];
        foreach ([0, 12, 24] as $offset) {
            $state = $this->visit([], ['Itemid' => 123, 'limitstart' => $offset]);
            $pages[] = array_slice($events, $state['list.start'], $state['list.limit']);
        }
        self::assertSame([range(1, 12), range(13, 24), [25]], $pages);
        self::assertSame($events, array_merge(...$pages));
    }

    /** @return void Negative/misaligned positions are normalized using the configured size. */
    public function testOffsetNormalizationIgnoresRequestLimit(): void
    {
        foreach ([-12 => 0, 1 => 0, 13 => 12, 25 => 24] as $offset => $expected) {
            $state = $this->visit([], ['limit' => 1, 'limitstart' => $offset]);
            self::assertSame(12, $state['list.limit']);
            self::assertSame($expected, $state['list.start']);
        }
    }

    /** @return void Blog filters and ordering remain effective alongside fixed pagination. */
    public function testFiltersRemainIndependentOfPageSize(): void
    {
        $params = [
            'blog_events_per_page' => 24,
            'blog_filter_categories' => [7],
            'blog_filter_venues' => [8],
            'blog_filter_types' => [9],
            'blog_filter_countries' => ['ES'],
        ];
        $request = [
            'limit' => 1, 'limitstart' => 24, 'blog_category' => 7,
            'blog_venue' => 8, 'blog_type' => 9, 'blog_country' => 'es',
        ];
        $state = $this->visit($params, $request);
        self::assertSame(24, $state['list.limit']);
        self::assertSame(24, $state['list.start']);
        self::assertSame(7, $state['filter.category_id']);
        self::assertSame(8, $state['filter.venue_id']);
        self::assertSame(9, $state['filter.type_id']);
        self::assertSame(['ES'], $state['filter.country_id']);
        self::assertSame('a.dates', $state['list.ordering']);
        self::assertSame('ASC', $state['list.direction']);
    }

    /** @return void The menu field and model agree on the default and existing supported range. */
    public function testMenuFieldDeclaresDefaultAndBounds(): void
    {
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/site/views/eventsblog/tmpl/default.xml');
        $fields = $xml->xpath('//fields[@name="params"]//field[@name="blog_events_per_page"]');
        self::assertCount(1, $fields);
        self::assertSame('12', (string) $fields[0]['default']);
        self::assertSame('1', (string) $fields[0]['min']);
        self::assertSame('100', (string) $fields[0]['max']);
    }
}
