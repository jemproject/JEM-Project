<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Views;

use JemProject\Tests\ExportLink\Application;
use JemProject\Tests\ExportLink\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Follow the real view/template link into the model's venue-event selection policy. */
final class VenuesListPrintSelectionTest extends TestCase
{
    /** @return void Load production export helpers with isolated Joomla request/routing boundaries. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/export_link_environment.php';
    }

    /** @return iterable<string, array{string, int, bool, ?int, string}> Layout, settings and request contexts. */
    public static function contexts(): iterable
    {
        foreach (['default', 'responsive/default'] as $layout) {
            foreach ([0, 1, 2] as $mode) {
                foreach ([false, true] as $control) {
                    foreach ([null, 0, 1] as $showAll) {
                        foreach (['', 'archive'] as $task) {
                            yield implode(':', [$layout, $mode, (int) $control, $showAll ?? 'absent', $task])
                                => [$layout, $mode, $control, $showAll, $task];
                        }
                    }
                }
            }
        }
    }

    /**
     * Run the production model policy without loading database or session services.
     *
     * @param int $mode Configured venue-event restriction.
     * @param bool $control Whether the checkbox is enabled by the menu.
     * @param array<string, mixed> $request Input before or after following Print.
     * @return array<string, mixed> Effective model filter states.
     */
    private static function selection(int $mode, bool $control, array $request): array
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/venueslist.php');
        self::assertSame(1, preg_match('/        \$eventMode =.*?(?=        ###########)/s', $source, $match));
        $params = new class ($mode, $control) {
            /** @param int $mode Event mode. @param bool $control Visible checkbox. */
            public function __construct(private int $mode, private bool $control)
            {
            }

            /** @param string $key Parameter. @param mixed $default Fallback. @return mixed Setting. */
            public function get(string $key, mixed $default = null): mixed
            {
                return match ($key) {
                    'venue_event_filter' => $this->mode,
                    'show_venue_event_filter' => $this->control,
                    default => $default,
                };
            }
        };
        $jinput = new class ($request) {
            /** @param array<string, mixed> $request Request values. */
            public function __construct(private array $request)
            {
            }

            /** @param string $key Parameter. @param bool $default Fallback. @return bool Checkbox state. */
            public function getBool(string $key, bool $default = false): bool
            {
                return (bool) ($this->request[$key] ?? $default);
            }
        };
        $state = new class {
            public array $values = [];

            /** @param string $key State name. @param mixed $value State value. @return void */
            public function setState(string $key, mixed $value): void
            {
                $this->values[$key] = $value;
            }
        };
        eval(str_replace('$this->', '$state->', $match[0]));

        return $state->values;
    }

    /**
     * Preserve selection, other filters, menu and archive context through both shipped templates.
     *
     * @param string $layout Template under test.
     * @param int $mode Configured event restriction.
     * @param bool $control Whether the menu enables Show All Venues.
     * @param int|null $showAll Checkbox value or absent input.
     * @param string $task Normal or archive request.
     * @return void
     */
    #[DataProvider('contexts')]
    public function testPrintUsesTheViewSelection(
        string $layout,
        int $mode,
        bool $control,
        ?int $showAll,
        string $task
    ): void {
        $request = [
            'filter_search' => 'Madrid & café+', 'filter_type' => 2,
            'filter_country' => ['ES', 'PT'], 'Itemid' => 123, 'task' => $task,
        ];
        if ($showAll !== null) {
            $request['show_all_venues'] = $showAll;
        }
        $before = self::selection($mode, $control, $request);
        Factory::$application = new Application($request);
        $view = file_get_contents(JEM_TEST_ROOT . '/site/views/venueslist/view.html.php');
        $template = file_get_contents(JEM_TEST_ROOT . '/site/views/venueslist/tmpl/' . $layout . '.php');
        self::assertSame(1, preg_match('/\$print_link\s*=\s*Route::_\([^;]+;/', $view, $assignment));
        self::assertSame(1, preg_match('/^\s*\$btn_params =[^;]+;/m', $template, $button));
        $build = eval(
            'namespace JemProject\Tests\ExportLink; return static function ($task, $before) {'
            . $assignment[0]
            . '$viewData = (object) ["task" => $task, "print_link" => $print_link,'
            . '"lists" => ["show_all_venues" => $before["filter.show_all_venues"]]];'
            . str_replace('$this->', '$viewData->', $button[0])
            . 'return [$print_link, $btn_params]; };'
        );
        [$viewUrl, $buttons] = $build($task, $before);
        parse_str((string) parse_url($buttons['print_link'], PHP_URL_QUERY), $query);
        $after = self::selection($mode, $control, $query);

        self::assertSame($before, $after, 'Printing must not restore a disabled event restriction.');
        self::assertSame($viewUrl, $buttons['print_link'], 'Templates must reuse the prepared view URL.');
        self::assertSame($task, $buttons['task']);
        self::assertSame($task, $query['task'] ?? '');
        self::assertSame($request['filter_search'], $query['filter_search']);
        self::assertSame('2', $query['filter_type']);
        self::assertSame(['ES', 'PT'], $query['filter_country']);
        self::assertSame('123', $query['Itemid']);
        self::assertSame('1', $query['print']);
        self::assertSame('component', $query['tmpl']);
        self::assertSame($request, Factory::$application->input->values);
    }
}
