<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EventTitleTruncationTest extends TestCase
{
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/title_rendering_stubs.php';
    }

    #[DataProvider('titleProvider')]
    public function testModulesTruncateRawCharactersAndKeepHtmlSafe(
        string $raw,
        int $limit,
        string $prefix,
        bool $truncated
    ): void {
        foreach (['teaser', 'banner', 'wide', 'jubilee'] as $module) {
            require_once JEM_TEST_ROOT . '/modules/mod_jem_' . $module . '/helper.php';
            $event = $this->event($raw);
            BaseDatabaseModel::$items = [$event];
            $params = new TitleRenderingParams([
                'cuttitle' => $limit,
                'type' => 2,
                'show_status_indicators' => 0,
                'linkvenue' => 0,
            ]);
            $class = 'ModJem' . ucfirst($module) . 'Helper';
            $items = $class::getList($params);
            self::assertCount(1, $items, $module);
            $item = $items[0];
            $suffix = $truncated ? ($module === 'wide' ? '...' : '…') : '';
            $document = $this->html('<a title="' . $item->fulltitle . '">' . $item->title . '</a>');
            $link = $document->getElementsByTagName('a')->item(0);
            self::assertSame($prefix . $suffix, $link->textContent, $module);
            self::assertSame($raw, $link->getAttribute('title'), $module);
            self::assertSame(0, $document->getElementsByTagName('img')->length, $module);
            self::assertSame(0, $document->getElementsByTagName('script')->length, $module);
            self::assertSame($raw, $event->title, 'Do not mutate the model row');
        }
    }

    #[DataProvider('titleProvider')]
    public function testListEventsKeepsTitlesSafeInBothDisplayModes(
        string $raw,
        int $limit,
        string $prefix,
        bool $truncated
    ): void {
        require_once JEM_TEST_ROOT . '/plugins/plg_content_jemlistevents/jemlistevents.php';
        $plugin = (new ReflectionClass(PlgContentJemlistevents::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($plugin, 'generateTitleCell');

        foreach (['on', 'link'] as $mode) {
            $html = $method->invoke($plugin, $this->event($raw), [
                'title' => $mode,
                'cut_title' => $limit,
            ], 'https://example.test/event');
            $document = $this->html('<table><tr>' . $html . '</tr></table>');
            self::assertSame(
                $prefix . ($truncated ? "\u{00A0}…" : ''),
                $document->getElementsByTagName('td')->item(0)->textContent
            );
            self::assertSame($mode === 'link' ? 1 : 0, $document->getElementsByTagName('a')->length);
            self::assertSame(0, $document->getElementsByTagName('img')->length);
            self::assertSame(0, $document->getElementsByTagName('script')->length);
        }
    }

    #[DataProvider('titleProvider')]
    public function testEmbedReturnsPlainTextThroughJsonWithoutDoubleEncoding(
        string $raw,
        int $limit,
        string $prefix,
        bool $truncated
    ): void {
        require_once JEM_TEST_ROOT . '/plugins/plg_content_jemembed/jemembed.php';
        Factory::$application->input = new class ($limit) {
            public object $server;
            public function __construct(private int $limit)
            {
                $this->server = new class {
                    public function getCmd($key, $default = '')
                    {
                        return $default;
                    }
                    public function getString($key, $default = '')
                    {
                        return $default;
                    }
                };
            }
            public function exists($key): bool
            {
                return $key === 'cuttitle';
            }
            public function getString($key, $default = '')
            {
                return (string) $this->limit;
            }
            public function getArray(): array
            {
                return ['cuttitle' => (string) $this->limit];
            }
        };
        $plugin = new class ($this->event($raw)) extends PlgContentJemembed {
            public function __construct(private object $event)
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
                return null;
            }
            protected function storeCachedResponse(string $cacheKey, array $response): void
            {
            }
            protected function _load($parameters)
            {
                return [$this->event];
            }
            protected function logInternal(string $message, int $level): void
            {
                throw new RuntimeException($message);
            }
        };
        $response = json_decode(json_encode($plugin->onAjaxJemembed(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($response['success']);
        self::assertSame($raw, $response['data'][0]['title']['full']);
        self::assertSame($prefix . ($truncated ? '…' : ''), $response['data'][0]['title']['display']);
    }

    public static function titleProvider(): iterable
    {
        yield 'ampersand' => ['A & B more', 5, 'A & B', true];
        yield 'before ampersand' => ['A & B more', 2, 'A ', true];
        yield 'at ampersand' => ['A & B more', 3, 'A &', true];
        yield 'one character' => ['& more', 1, '&', true];
        yield 'quotes' => ['"\' & < > end', 8, '"\' & < >', true];
        yield 'multibyte' => ['Été 東京🎵 plus', 7, 'Été 東京🎵', true];
        yield 'short' => ['A & B', 20, 'A & B', false];
        yield 'exact length' => ['A & B', 5, 'A & B', false];
        yield 'empty' => ['', 1, '', false];
        yield 'literal entity text' => ['&amp; more', 5, '&amp;', true];
        yield 'html-like text' => ['<img src=x> more', 11, '<img src=x>', true];
        yield 'script-like text' => ['<script>bad</script>', 30, '<script>bad</script>', false];
    }

    private function html(string $markup): DOMDocument
    {
        $document = new DOMDocument();
        $document->loadHTML('<!doctype html><html><head><meta charset="UTF-8"></head><body>' . $markup . '</body></html>');
        return $document;
    }

    private function event(string $title): object
    {
        $event = (object) [
            'id' => 0, 'title' => $title, 'slug' => '1-event', 'venueslug' => '',
            'dates' => null, 'enddates' => null, 'times' => null, 'endtimes' => null,
            'categories' => [], 'locimage' => '', 'venue' => '', 'featured' => 0,
            'introtext' => '', 'fulltext' => '', 'locid' => 0, 'l_alias' => '',
            'locdescription' => '', 'user_has_access_event' => true,
            'user_has_access_category' => true, 'user_has_access_venue' => true,
        ];
        for ($index = 1; $index <= 10; ++$index) {
            $event->{'custom' . $index} = '';
        }
        return $event;
    }
}
