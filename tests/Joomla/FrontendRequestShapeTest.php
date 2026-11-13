<?php

declare(strict_types=1);

use Joomla\Input\Input;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Execute production pagination/search blocks with native Joomla request filtering. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FrontendRequestShapeTest extends TestCase
{
    /** @return void Load Joomla input and the production scalar guard without site writes. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT; no database is used.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        require_once JEM_TEST_ROOT . '/site/classes/filterinput.class.php';
    }

    /** @return iterable<string, array{string}> Request sources handled by Joomla. */
    public static function channels(): iterable
    {
        foreach (['request', 'get', 'post'] as $channel) {
            yield $channel => [$channel];
        }
    }

    /** @param array $values Request values. @param string $channel Source. @return Input Native input. */
    private function input(array $values, string $channel): Input
    {
        if ($channel === 'request') {
            return new Input($values);
        }
        $global = '_' . strtoupper($channel);
        $previous = $GLOBALS[$global] ?? [];
        try {
            $GLOBALS[$global] = $values;
            return (new Input([]))->$channel;
        } finally {
            $GLOBALS[$global] = $previous;
        }
    }

    /** @param string $file Source. @param string $from Start. @param string $until End. @return string Actual code. */
    private function block(string $file, string $from, string $until): string
    {
        $source = str_replace("\r\n", "\n", file_get_contents(JEM_TEST_ROOT . '/' . $file));
        $start = strpos($source, $from);
        self::assertNotFalse($start, $file . ': ' . $from);
        $end = strpos($source, $until, $start);
        self::assertNotFalse($end, $file . ': ' . $until);
        return substr($source, $start, $end - $start);
    }

    /** @param string $code Production block. @param array $variables Dependencies. @return array State and locals. */
    private function execute(string $code, array $variables): array
    {
        $model = new class {
            public array $state = [];

            /** @param string $key State name. @param mixed $value Stored value. @return void */
            public function setState(string $key, mixed $value): void
            {
                $this->state[$key] = $value;
            }
        };
        $run = eval('use Joomla\\String\\StringHelper; return function (array $variables): array {'
            . 'extract($variables);' . $code
            . '; return ["state" => $this->state, "locals" => get_defined_vars()]; };');
        return $run->call($model, $variables);
    }

    /** @param string $channel Request source. @return void Blog keeps menu size and valid offsets. */
    #[DataProvider('channels')]
    public function testBlogOffsets(string $channel): void
    {
        $code = $this->block('site/models/eventsblog.php', '// Blog page size belongs', "\$this->setState('list.ordering'");
        foreach ([[['1'], 0], [['nested' => ['24']], 0], [null, 0], ['25', 24], ['-12', 0]] as [$value, $expected]) {
            $request = $value === null ? [] : ['limitstart' => $value];
            $result = $this->execute($code, [
                'params' => new Registry(['blog_events_per_page' => 12]),
                'input' => $this->input($request + ['limit' => 1], $channel),
            ]);
            self::assertSame(12, $result['state']['list.limit']);
            self::assertSame($expected, $result['state']['list.start']);
            self::assertSame(array_slice(range(1, 30), $expected, 12), array_slice(range(1, 30), $result['state']['list.start'], $result['state']['list.limit']));
        }
    }

    /** @param string $channel Request source. @return void Venues preserve defaults, valid pages and All. */
    #[DataProvider('channels')]
    public function testVenuesPagination(string $channel): void
    {
        $code = $this->block('site/models/venues.php', '// List state information', '# params');
        foreach ([
            [['limit' => ['1'], 'limitstart' => 20], 10, 20],
            [['limit' => 5, 'limitstart' => ['1']], 5, 0],
            [['limit' => ['nested' => ['1']], 'limitstart' => ['nested' => ['1']]], 10, 0],
            [[], 10, 0],
            [['limit' => '5', 'limitstart' => '12'], 5, 10],
            [['limit' => '0', 'limitstart' => '20'], 0, 0],
        ] as [$request, $limit, $offset]) {
            $result = $this->execute($code, [
                'params' => new Registry(['display_venues_num' => 10]),
                'app' => (object) ['input' => $this->input($request, $channel)],
            ]);
            self::assertSame($limit, $result['state']['list.limit']);
            self::assertSame($offset, $result['state']['list.start']);
        }
    }

    /** @param string $channel Request source. @return void Content plugins always receive an integer page. */
    #[DataProvider('channels')]
    public function testEventContentOffset(string $channel): void
    {
        $code = $this->block('site/models/event.php', '$offset =', '// Load the parameters.');
        foreach ([[['1'], 0], [['nested' => ['1']], 0], [null, 0], ['2', 2]] as [$value, $expected]) {
            $result = $this->execute($code, [
                'app' => (object) ['input' => $this->input($value === null ? [] : ['limitstart' => $value], $channel)],
            ]);
            self::assertSame($expected, $result['state']['list.offset']);
            self::assertSame($expected + 1, $result['state']['list.offset'] + 1);
        }
    }

    /** @param string $channel Request source. @return void Empty and populated maps tolerate malformed offsets. */
    #[DataProvider('channels')]
    public function testMapPagesIncludingEmptyResults(string $channel): void
    {
        $code = $this->block('site/views/venuesmap/view.html.php', '// Pagination over the filtered venueslist', '$pagination = new Pagination');
        $app = new class {
            /** @param string $key Setting. @param mixed $default Fallback. @return mixed Default value. */
            public function get(string $key, mixed $default): mixed
            {
                return $default;
            }
        };
        foreach ([[], [11, 12, 13, 14]] as $venues) {
            foreach ([[['1'], 0], [['nested' => ['1']], 0], [null, 0], ['2', 2], ['99', $venues ? 0 : 99]] as [$value, $expected]) {
                $result = $this->execute($code, [
                    'params' => new Registry(['venues_list_limit' => 2]), 'app' => $app,
                    'jinput' => $this->input($value === null ? [] : ['limitstart' => $value], $channel),
                    'venueslist' => $venues,
                ]);
                self::assertSame($expected, $result['locals']['limitstart']);
                self::assertSame(array_slice($venues, $expected, 2), $result['locals']['venueslistPage']);
            }
        }
    }

    /** @param string $channel Request source. @return void Invalid search types retain keywords through the title fallback. */
    #[DataProvider('channels')]
    public function testSearchKeepsKeywordPredicate(string $channel): void
    {
        $file = 'site/models/search.php';
        $code = $this->block($file, '$filter_type       =', '$filter_continent');
        $code .= $this->block($file, '$filter_type = StringHelper::strtolower', "\n            }\n") . "\n}";
        foreach ([[['1'], 'a.title'], [['nested' => ['venue']], 'a.title'], [null, 'a.title'], ['title', 'a.title'], ['venue', 'l.venue'], ['CITY', 'l.city']] as [$value, $column]) {
            $result = $this->execute($code, [
                'app' => (object) ['input' => $this->input($value === null ? [] : ['filter_type' => $value], $channel)],
                'filter' => "'%needle%'", 'where' => '',
            ]);
            self::assertSame(" AND LOWER($column) LIKE '%needle%'", $result['locals']['where']);
        }
    }

    /** @param string $channel Request source. @return void Search form and links use the same fallback as its query. */
    #[DataProvider('channels')]
    public function testSearchFormAndLinksKeepTheFallback(string $channel): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/views/search/view.html.php');
        preg_match_all('/JemFilterInput::read\(\$app->input, \'filter_type\', [^)]*\)/', $source, $matches);
        self::assertCount(2, $matches[0]);
        foreach ($matches[0] as $read) {
            foreach ([[['1'], 'title'], [null, 'title'], ['venue', 'venue']] as [$value, $expected]) {
                $result = $this->execute('$selection = ' . $read . ';', [
                    'app' => (object) ['input' => $this->input($value === null ? [] : ['filter_type' => $value], $channel)],
                ]);
                self::assertSame($expected, $result['locals']['selection']);
            }
        }
    }
}
