<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Helpers;

use DOMDocument;
use JemProject\Tests\PrintLink\Application;
use JemProject\Tests\PrintLink\Factory;
use JemProject\Tests\PrintLink\JemHelper;
use JemProject\Tests\PrintLink\JemOutput;
use JemProject\Tests\PrintLink\Registry;
use JemProject\Tests\PrintLink\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Focused URL, view wiring and rendered print-button regression coverage. */
final class FrontendPrintLinkTest extends TestCase
{
    /** @return void Load actual output methods with isolated Joomla boundaries. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/print_link_environment.php';
        $source = file_get_contents(JEM_TEST_ROOT . '/site/classes/output.class.php');
        $methods = '';
        foreach (['buildPrintLink', 'printbutton', 'escapeLinkAttribute', 'escapeHtmlAttribute', 'tooltip'] as $name) {
            if ($name === 'buildPrintLink' && !str_contains($source, 'function buildPrintLink(')) {
                continue; // Let the new regression fail clearly on the original implementation.
            }
            self::assertSame(1, preg_match('/(?:static (?:public|protected)|(?:public|protected) static) function '
                . $name . '\([^\n]*\)[^\n]*\s*\{.*?^    \}/ms', $source, $match), $name);
            $methods .= $match[0] . "\n";
        }
        eval('namespace JemProject\Tests\PrintLink; final class JemOutput {' . $methods . '}');
    }

    /** @return void Reset settings, routing observations and popup mode. */
    protected function setUp(): void
    {
        Factory::$application = new Application();
        JemHelper::$settings = new Registry(['global_show_print_icon' => 1, 'global_show_icons' => 0]);
        Route::$calls = [];
    }

    /** @return array<string, string> Existing caller URL forms and query edge cases. */
    private static function urls(): array
    {
        return [
            'direct-search' => 'https://example.test/jl602/index.php?option=com_jem&view=eventslist&Itemid=101&filter_search=TESTSEARCH',
            'year-filter' => 'https://example.test/jl602/events?filter_search=TESTSEARCH&filter_year=2026',
            'internal-event' => 'index.php?option=com_jem&view=event&id=42&Itemid=101',
            'internal-print-layout' => 'index.php?option=com_jem&view=attendees&layout=print&task=print&tmpl=component&id=42',
            'sef-no-query' => 'https://example.test/jl602/events',
            'routed-relative' => '/jl602/events?filter_search=TESTSEARCH',
            'empty-query' => '/jl602/events?',
            'html-escaped' => '/jl602/events?filter_search=A%26B&amp;print=1&amp;tmpl=component',
            'existing-controls' => '/jl602/events?filter_search=TESTSEARCH&print=0&tmpl=module&task=archive',
            'duplicate-controls' => '/jl602/events?print=0&print=1&tmpl=component&tmpl=module&task=archive&task=archive',
            'encoded-search' => '/jl602/events?filter_search=' . rawurlencode('A&B ? # " café + %'),
            'array-filter' => '/jl602/events?filter_cats%5B%5D=2&filter_cats%5B%5D=7',
            'fragment' => '/jl602/events?filter_search=TESTSEARCH#results',
            'fragment-question' => '/jl602/events#results?section=2',
            'query-only' => '?filter_search=TESTSEARCH',
            'joomla-query-only' => '&option=com_jem&view=event&id=42',
            'array-controls' => '/jl602/events?print%5B%5D=0&tmpl%5B%5D=module',
        ];
    }

    /** @return iterable<string, array{string, bool}> URLs and explicit archive context. */
    public static function builderCases(): iterable
    {
        foreach (self::urls() as $name => $url) {
            foreach ([false, true] as $archive) {
                yield $name . ':' . (int) $archive => [$url, $archive];
            }
        }
    }

    /**
     * Read query values as PHP would, keeping fragments and HTML separators out of the query.
     * @param string $url URL. @return array<string, mixed> Effective query values.
     */
    private static function query(string $url): array
    {
        $url = explode('#', $url, 2)[0];
        $raw = str_starts_with($url, '&') ? substr($url, 1) : (explode('?', $url, 2)[1] ?? '');
        parse_str(str_replace('&amp;', '&', $raw), $query);
        return $query;
    }

    /** @param string $url URL. @param bool $archive Force archive task. @return array<string, mixed> Expected values. */
    private static function expected(string $url, bool $archive): array
    {
        $query = self::query($url);
        $query['print'] = '1';
        $query['tmpl'] = 'component';
        if ($archive) {
            $query['task'] = 'archive';
        }
        ksort($query);
        return $query;
    }

    /** @param string $url Original URL. @param bool $archive Archive context. @return void */
    #[DataProvider('builderCases')]
    public function testBuilderPreservesQueryValues(string $url, bool $archive): void
    {
        self::assertTrue(method_exists(JemOutput::class, 'buildPrintLink'), 'A shared print-link builder is required.');
        $result = JemOutput::buildPrintLink($url, $archive);
        $query = self::query($result);
        ksort($query);
        self::assertSame(self::expected($url, $archive), $query);
        self::assertSame($result, JemOutput::buildPrintLink($result, $archive));
        self::assertSame(explode('#', $url, 2)[1] ?? null, explode('#', $result, 2)[1] ?? null);
        if (!str_starts_with($url, '&')) {
            self::assertSame(explode('?', explode('#', $url, 2)[0], 2)[0], explode('?', $result, 2)[0]);
        }
        foreach (['print', 'tmpl'] as $key) {
            self::assertSame(1, preg_match_all('/(?:[?&])' . $key . '=/', explode('#', $result, 2)[0]));
        }
    }

    /** @return iterable<string, array{string, int, string, bool}> View assignment, URL and archive context. */
    public static function viewCases(): iterable
    {
        foreach (['eventslist' => 2, 'eventsmap' => 1, 'typeevents' => 2, 'typevenues' => 2] as $view => $count) {
            foreach (range(0, $count - 1) as $index) {
                foreach (['direct-search', 'sef-no-query', 'encoded-search', 'array-filter', 'existing-controls'] as $name) {
                    yield "$view:$index:$name" => [$view, $index, self::urls()[$name], $view === 'eventslist' && $index === 0];
                }
            }
        }
    }

    /** @param string $view View. @param int $index Assignment. @param string $url Current URI. @param bool $archive Context. @return void */
    #[DataProvider('viewCases')]
    public function testEveryAffectedAssignmentUsesTheBuilder(string $view, int $index, string $url, bool $archive): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/view.html.php');
        $count = preg_match_all('/\$(?:this->)?print_link\s*=\s*(?:\$uri->toString|JemOutput::buildPrintLink)[^;]+;/', $source, $matches);
        self::assertSame($view === 'eventsmap' ? 1 : 2, $count);
        $assignment = str_replace('$this->', '$viewData->', $matches[0][$index]);
        $run = eval('namespace JemProject\Tests\PrintLink; return static function (string $url, string $task) {'
            . '$uri = new Uri($url); $viewData = new \stdClass();'
            . $assignment . 'return [$print_link ?? $viewData->print_link, $uri->toString()]; };');
        [$result, $original] = $run($url, $archive ? 'archive' : '');
        $query = self::query($result);
        ksort($query);
        self::assertSame(self::expected($url, $archive), $query);
        self::assertSame($url, $original, 'Preparing a print link must not mutate the current URI.');
    }

    /** @return iterable<string, array{string, bool, bool, bool}> URL, icon/text, popup and enabled settings. */
    public static function buttonCases(): iterable
    {
        foreach (self::urls() as $name => $url) {
            foreach ([false, true] as $icons) {
                foreach ([false, true] as $popup) {
                    foreach ([false, true] as $enabled) {
                        yield "$name:$icons:$popup:$enabled" => [$url, $icons, $popup, $enabled];
                    }
                }
            }
        }
    }

    /**
     * Check the actual renderer, including its visibility, native-print and escaped link paths.
     * @param string $url Input URL. @param bool $icons Icons enabled. @param bool $popup Print page. @param bool $enabled Print allowed.
     * @return void
     */
    #[DataProvider('buttonCases')]
    public function testPrintButtonCompatibility(string $url, bool $icons, bool $popup, bool $enabled): void
    {
        JemHelper::$settings->values = ['global_show_print_icon' => (int) $enabled, 'global_show_icons' => (int) $icons];
        Factory::$application->input->print = (int) $popup;
        $html = JemOutput::printbutton($url, null);
        if (!$enabled) {
            self::assertNull($html);
            self::assertSame([], Route::$calls);
            return;
        }
        $dom = new DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        self::assertSame(1, $dom->getElementsByTagName('a')->length);
        $anchor = $dom->getElementsByTagName('a')->item(0);
        if ($popup) {
            self::assertSame('#', $anchor->getAttribute('href'));
            self::assertSame('window.print();return false;', $anchor->getAttribute('onclick'));
            self::assertSame([], Route::$calls);
            return;
        }
        self::assertCount(1, Route::$calls, 'Preserve routing through the existing escaping helper.');
        $query = self::query($anchor->getAttribute('href'));
        ksort($query);
        self::assertSame(self::expected($url, false), $query);
        self::assertStringContainsString('window.open(this.href,', $anchor->getAttribute('onclick'));
        self::assertSame('', $anchor->getAttribute('onmouseover'));
        self::assertSame('', $anchor->getAttribute('onfocus'));
        self::assertSame($icons, str_contains($html, 'jem-printbutton'));
        self::assertStringNotContainsString('&amp;amp;', $html);
        foreach (['print', 'tmpl'] as $key) {
            self::assertSame(1, preg_match_all('/(?:[?&])' . $key . '=/', explode('#', $anchor->getAttribute('href'), 2)[0]));
        }
    }
}
