<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Helpers;

use JemProject\Tests\ExportLink\Application;
use JemProject\Tests\ExportLink\Factory;
use JemProject\Tests\ExportLink\JemOutput;
use JemProject\Tests\ExportLink\Route;
use JemProject\Tests\ExportLink\Uri;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Execute shared export URL code and actual view assignments with isolated Joomla boundaries. */
final class ExportFilterContextTest extends TestCase
{
    /** @return void Load production methods and reset request/routing spies. */
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/export_link_environment.php';
        Factory::$application = new Application([]);
        Route::$calls = [];
    }

    /** @param string $url Export URL. @return array<string, mixed> Decoded query. */
    private static function query(string $url): array
    {
        $url = explode('#', $url, 2)[0];
        $query = str_starts_with($url, '&') ? substr($url, 1) : (explode('?', $url, 2)[1] ?? '');
        parse_str(str_replace('&amp;', '&', $query), $values);
        return $values;
    }

    /** @return iterable<string, array{array<string, mixed>, array<string, mixed>}> Filters and decoded expectations. */
    public static function filters(): iterable
    {
        yield 'text and encoding' => [
            ['filter_search' => 'Música & Rock+Jazz #1', 'filter_type' => 1],
            ['filter_search' => 'Música & Rock+Jazz #1', 'filter_type' => '1'],
        ];
        yield 'zero search' => [['filter_search' => '0'], ['filter_search' => '0']];
        yield 'map filters' => [
            ['jem_map_filter_mode' => 'date', 'jem_map_filter_date' => '2026-10-31',
                'jem_map_filter_country' => 'ES', 'jem_map_filter_city' => 'Madrid', 'jem_map_filter_catid' => 4],
            ['jem_map_filter_mode' => 'date', 'jem_map_filter_date' => '2026-10-31',
                'jem_map_filter_country' => 'ES', 'jem_map_filter_city' => 'Madrid', 'jem_map_filter_catid' => '4'],
        ];
        yield 'clear search' => [['filter_search' => ''], ['filter_search' => '']];
        yield 'category type' => [['filter_typeid' => 8], ['filter_typeid' => '8']];
        yield 'grouped types' => [['typeid' => '0'], ['typeid' => '0']];
        yield 'clear inherited type' => [['typeid' => ''], ['typeid' => '']];
        yield 'venue countries' => [['filter_country' => ['ES', 'GB']], ['filter_country' => ['ES', 'GB']]];
        yield 'clear countries' => [['filter_country' => []], ['filter_country' => ['']]];
        yield 'all venues' => [['show_all_venues' => 1], ['show_all_venues' => '1']];
        yield 'contacts and fields' => [
            ['filter_contacts' => [3, 5], 'filter_custom' => ['12' => 'A&B'], 'filter_contact_category' => 4],
            ['filter_contacts' => ['3', '5'], 'filter_custom' => ['12' => 'A&B'], 'filter_contact_category' => '4'],
        ];
        yield 'attendee status' => [['filter_status' => -2, 'filter' => 1], ['filter_status' => '-2', 'filter' => '1']];
        yield 'special days' => [
            ['filter_day_type' => 'holiday', 'filter_state' => '1', 'filter_year' => 2029],
            ['filter_day_type' => 'holiday', 'filter_state' => '1', 'filter_year' => '2029'],
        ];
        yield 'day scope' => [
            ['locids' => '1,2', 'catids' => '3,4', 'catid' => 3, 'locid' => 1, 'topcat' => 5, 'pub' => '1,2'],
            ['locids' => '1,2', 'catids' => '3,4', 'catid' => '3', 'locid' => '1', 'topcat' => '5', 'pub' => '1,2'],
        ];
        yield 'calendar context' => [
            ['yearID' => 2026, 'monthID' => 10, 'weekID' => 44, 'mode' => 'agenda'],
            ['yearID' => '2026', 'monthID' => '10', 'weekID' => '44', 'mode' => 'agenda'],
        ];
        yield 'timeline context' => [
            ['show_archived_events' => 1, 'timeline_days_to_show' => 7, 'jem_timeline_layout' => 'compact'],
            ['show_archived_events' => '1', 'timeline_days_to_show' => '7', 'jem_timeline_layout' => 'compact'],
        ];
        yield 'sort and language' => [
            ['filter_order' => 'a.dates', 'filter_order_Dir' => 'DESC', 'filter_month' => '2026-10', 'lang' => 'es'],
            ['filter_order' => 'a.dates', 'filter_order_Dir' => 'DESC', 'filter_month' => '2026-10', 'lang' => 'es'],
        ];
    }

    /**
     * Verify request filters survive both output modes without request mutation.
     * @param array $request GET/POST input. @param array $expected Decoded values. @return void
     */
    #[DataProvider('filters')]
    public function testFilterRoundTrip(array $request, array $expected): void
    {
        Factory::$application = new Application($request);
        foreach (['print=1&tmpl=component', 'format=raw&layout=pdf'] as $output) {
            $base = 'index.php?option=com_jem&view=categories&' . $output;
            $url = JemOutput::prepareExportLink($base);
            $expectedQuery = self::query($base) + $expected;
            $actualQuery = self::query($url);
            ksort($expectedQuery);
            ksort($actualQuery);
            self::assertSame($expectedQuery, $actualQuery);
            self::assertSame($url, JemOutput::prepareExportLink($url));
            self::assertSame($request, Factory::$application->input->values);
        }
    }

    /** @return void Carry only known context, not input actions, tokens or pagination overrides. */
    public function testDestinationControlsAndAllowlist(): void
    {
        Factory::$application = new Application([
            'id' => 888, 'Itemid' => 123, 'task' => 'delete', 'format' => 'html',
            'layout' => 'edit', 'tmpl' => 'module', 'option' => 'com_users', 'view' => 'edit',
            'return' => 'private-return', 'token' => 'never-forward', 'cid' => [9],
            'filter_access' => 1, 'filter_reset' => 1, 'limit' => 1, 'limitstart' => 10,
        ]);
        $url = JemOutput::prepareExportLink(
            'index.php?option=com_jem&view=venue&id=7&Itemid=999&format=raw&layout=pdf&filter_reset=1#result'
        );
        self::assertSame([
            'option' => 'com_jem', 'view' => 'venue', 'id' => '7',
            'Itemid' => '123', 'format' => 'raw', 'layout' => 'pdf',
        ], self::query($url));
        self::assertStringEndsWith('#result', $url);
    }

    /** @return void Preserve resolved Day values and attendee print action over unrelated input. */
    public function testExplicitValuesWinAndQueryOnlyWorks(): void
    {
        Factory::$application = new Application(['id' => 0, 'task' => 'archive', 'filter_search' => 'input']);
        $url = JemOutput::prepareExportLink(
            '&option=com_jem&view=day&id=20261031&amp;filter_search=resolved&task=print&limit=10&limitstart=20'
        );
        self::assertStringStartsWith('&option=', $url);
        self::assertSame([
            'option' => 'com_jem', 'view' => 'day', 'id' => '20261031',
            'filter_search' => 'resolved', 'task' => 'print', 'limit' => '10', 'limitstart' => '20',
        ], self::query($url));
    }

    /** @return iterable<string, array{array, mixed}> Request and menu category-type contexts. */
    public static function categoryContexts(): iterable
    {
        yield 'plain' => [[], null];
        yield 'menu type' => [[], 3];
        yield 'selected type' => [['typeid' => '8'], 3];
        yield 'grouped types' => [['typeid' => '0'], 3];
        yield 'clear inherited type' => [['typeid' => '', 'filter_typeid' => 8], 3];
        yield 'dropdown only' => [['filter_typeid' => 8], null];
        yield 'empty search result' => [['filter_search' => 'NO_MATCH'], null];
    }

    /**
     * Execute the Categories print assignment and real model type resolution.
     * @param array $request Current input. @param mixed $menuType Configured type. @return void
     */
    #[DataProvider('categoryContexts')]
    public function testCategoriesSelectionDoesNotChange(array $request, mixed $menuType): void
    {
        foreach (['', 'archive'] as $task) {
            Factory::$application = new Application($request + ['task' => $task, 'Itemid' => 123]);
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/categories/view.html.php');
            preg_match_all('/\$print_link\s*=\s*Route::_\([^;]+;/', $source, $matches);
            $index = $task === 'archive' ? 0 : 1;
            $run = eval('namespace JemProject\Tests\ExportLink; return static function () {'
                . $matches[0][$index] . 'return $print_link; };');
            $query = self::query($run());
            self::assertSame(
                JemOutput::resolveTypeSelection(array_key_exists('typeid', $request), $request['typeid'] ?? '', $menuType),
                JemOutput::resolveTypeSelection(array_key_exists('typeid', $query), $query['typeid'] ?? '', $menuType)
            );
            self::assertSame((string) ($request['filter_typeid'] ?? ''), $query['filter_typeid'] ?? '');
            self::assertSame($request['filter_search'] ?? '', $query['filter_search'] ?? '');
            self::assertSame($task, $query['task'] ?? '');
            self::assertSame('123', $query['Itemid']);
            self::assertArrayNotHasKey('id', $query, 'Do not replace a menu category root with hardcoded root 1.');
        }
    }

    /** @return iterable<string, array{string, string}> Actual print/PDF assignments in reviewed views. */
    public static function assignments(): iterable
    {
        foreach ([
            'annualcalendar', 'attendeeregistrations', 'attendees', 'calendar', 'categories',
            'category', 'event', 'eventslist', 'eventsmap', 'myattendances', 'myevents',
            'mytimeline', 'myvenues', 'typeevents', 'typevenues', 'venue', 'venues',
            'venueslist', 'venuesmap', 'weekcal',
        ] as $view) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/view.html.php');
            preg_match_all('/\$(?:this->)?(?:print_link|pdf_link)\s*=\s*(?:Route::_|JemOutput::)[^;]+;/', $source, $matches);
            foreach ($matches[0] as $index => $assignment) {
                yield $view . ':' . $index => [$view, $assignment];
            }
        }
    }

    /**
     * Run every affected assignment with form input absent from the URI.
     * @param string $view View name. @param string $assignment Production statement. @return void
     */
    #[DataProvider('assignments')]
    public function testViewsPreservePostedFiltersBeforeRouting(string $view, string $assignment): void
    {
        Factory::$application = new Application([
            'Itemid' => 123, 'filter_search' => 'NO_MATCH & café+', 'filter_type' => 1,
            'show_all_venues' => 1, 'filter_country' => ['ES'], 'typeid' => '',
        ]);
        $run = eval('namespace JemProject\Tests\ExportLink; return static function ($view) {'
            . '$url_base = "index.php?option=com_jem&view=" . $view;'
            . '$uri = new Uri($url_base);'
            . '$task = "archive"; $id = 7; $requestedTypeId = 7;'
            . '$typeObj = $venue = $category = $item = $event = (object) ["id" => 7, "slug" => "7:test"];'
            . '$partDate = "&yearID=2026&monthID=10&weekID=44";'
            . '$partItemid = "&Itemid=999"; $partVenid = "&id=7"; $partMode = "&mode=agenda";'
            . '$partArchive = "&task=archive";'
            . '$filterQuery = ["option" => "com_jem", "view" => $view, "format" => "raw", "layout" => "pdf"];'
            . '$viewData = new \stdClass();'
            . str_replace('$this->', '$viewData->', $assignment)
            . 'return $pdf_link ?? $print_link ?? $viewData->pdf_link ?? $viewData->print_link; };');
        $query = self::query($run($view));
        self::assertSame('NO_MATCH & café+', $query['filter_search'] ?? null);
        self::assertSame('1', $query['filter_type'] ?? null);
        self::assertSame('1', $query['show_all_venues'] ?? null);
        self::assertSame(['ES'], $query['filter_country'] ?? null);
        self::assertSame('', $query['typeid'] ?? null);
        self::assertSame('123', $query['Itemid'] ?? null);
        foreach (Route::$calls as $url) {
            self::assertSame('NO_MATCH & café+', self::query($url)['filter_search'] ?? null);
            self::assertSame('123', self::query($url)['Itemid'] ?? null);
        }
    }

    /**
     * Prefer submitted filters over old URI parameters when the URI is the source.
     * @return void
     */
    public function testCurrentUriUsesLatestSubmittedFilters(): void
    {
        Factory::$application = new Application(['filter_search' => 'NEW POST', 'filter_type' => 2]);
        $source = 'index.php?option=com_jem&view=eventslist&filter_search=OLD_GET&filter_type=1';
        $query = self::query(JemOutput::buildPrintLink(JemOutput::prepareExportLink($source, true)));
        self::assertSame('NEW POST', $query['filter_search']);
        self::assertSame('2', $query['filter_type']);
        self::assertSame('1', $query['print']);
        self::assertSame('component', $query['tmpl']);
    }

    /** @return void Generic PDFs keep POST filters and archive context without mutating the shared URI. */
    public function testFallbackPdfIncludesPostFilters(): void
    {
        Factory::$application = new Application([
            'Itemid' => 123, 'filter_search' => 'POST SEARCH', 'typeid' => '',
            'task' => 'archive', 'filter_country' => ['ES'],
        ]);
        Uri::$current = new Uri('index.php?option=com_jem&view=categories&print=1&tmpl=component&filter_search=STALE_GET');
        $before = Uri::$current->toString();
        $query = self::query(JemOutput::buildCurrentPdfLink());
        self::assertSame('POST SEARCH', $query['filter_search']);
        self::assertSame('', $query['typeid']);
        self::assertSame('archive', $query['task']);
        self::assertSame('123', $query['Itemid']);
        self::assertSame('raw', $query['format']);
        self::assertSame('pdf', $query['layout']);
        self::assertArrayNotHasKey('print', $query);
        self::assertArrayNotHasKey('tmpl', $query);
        self::assertSame($before, Uri::$current->toString());
    }
}
