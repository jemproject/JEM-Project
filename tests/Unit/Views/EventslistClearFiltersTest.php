<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EventslistClearFiltersTest extends TestCase
{
    /**
     * Verify real filter fragments without booting Joomla or rendering event rows.
     *
     * @param string $layout   Shared template relative to its template directory.
     * @param bool   $below    Whether to render the bottom filter fragment.
     * @param string $month    Previously selected month, or an empty selection.
     * @param bool   $editable Whether advanced editable filters own the Clear handler.
     *
     * @return void
     */
    #[DataProvider('filterCases')]
    public function testFilterControlsAndClearHandler(
        string $layout,
        bool $below,
        string $month,
        bool $editable
    ): void {
        class_alias(EventslistClearFiltersText::class, 'Joomla\CMS\Language\Text');

        $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/common/views/tmpl/' . $layout);
        $fragment = $this->filterFragment($source, $below);
        $view = new EventslistClearFiltersView($month, $editable);
        $document = new DOMDocument();
        $document->loadHTML('<html><body><form>' . $view->render($fragment) . '</form></body></html>');
        $xpath = new DOMXPath($document);

        foreach (['filter_search', 'filter_month'] as $name) {
            self::assertSame(1, $xpath->query('//input[@name="' . $name . '"]')->length);
            self::assertSame(1, $xpath->query('//*[@id="' . $name . '"]')->length);
        }

        self::assertSame($month, $xpath->evaluate('string(//input[@name="filter_month"]/@value)'));
        self::assertSame('month', $xpath->evaluate('string(//input[@name="filter_month"]/@type)'));
        self::assertSame("O'Brien", $xpath->evaluate('string(//input[@name="filter_search"]/@value)'));
        self::assertSame(1, $xpath->query('//label[@for="filter_month"]')->length);
        self::assertSame(1, $xpath->query('//select[@name="filter_type"]')->length);
        self::assertSame(1, $xpath->query('//button[@type="submit"]')->length);

        $buttons = $xpath->query('//button[@type="button"]');
        self::assertSame(1, $buttons->length);
        $clear = $buttons->item(0);

        if ($editable) {
            self::assertTrue($clear->hasAttribute('data-jem-main-filters-clear'));
            self::assertFalse($clear->hasAttribute('onclick'), 'Do not submit twice with advanced filters.');
        } else {
            $handler = $clear->getAttribute('onclick');
            self::assertStringContainsString("document.getElementById('filter_search').value='';", $handler);
            self::assertStringContainsString("document.getElementById('filter_month').value='';", $handler);
            self::assertSame(1, substr_count($handler, 'this.form.submit();'));
        }
    }

    /**
     * Cover both positions, an active/empty month and the available handler modes.
     *
     * @return iterable<string, array{string, bool, string, bool}> Named template cases.
     */
    public static function filterCases(): iterable
    {
        $layouts = [
            'responsive/default_jem_eventslist.php' => [false, true],
            'responsive/default_jem_eventslist_small.php' => [false, true],
            'default_events_table.php' => [false],
        ];
        $modes = is_file(JEM_TEST_ROOT . '/media/js/event-filters.js') ? [false, true] : [false];

        foreach ($layouts as $layout => $positions) {
            foreach ($positions as $below) {
                foreach (['', '2026-10'] as $month) {
                    foreach ($modes as $editable) {
                        $name = $layout . ($below ? ':bottom' : ':top')
                            . ':' . ($month ?: 'empty') . ($editable ? ':advanced' : ':legacy');

                        yield $name => [$layout, $below, $month, $editable];
                    }
                }
            }
        }
    }

    /**
     * Extract one balanced production filter div, preserving its PHP and handlers.
     *
     * The surrounding layout-position conditions and event rendering are outside
     * this isolated test; browser checks exercise the rendered handlers separately.
     *
     * @param string $source Complete trusted template source from this checkout.
     * @param bool   $below  Select the last rather than the first filter block.
     *
     * @return string Unmodified filter fragment.
     */
    private function filterFragment(string $source, bool $below): string
    {
        $start = $below ? strrpos($source, '<div id="jem_filter"') : strpos($source, '<div id="jem_filter"');
        self::assertNotFalse($start);
        preg_match_all('/<div\b[^>]*>|<\/div>/i', substr($source, $start), $tags, PREG_OFFSET_CAPTURE);
        $depth = 0;

        foreach ($tags[0] as [$tag, $offset]) {
            $depth += str_starts_with($tag, '</') ? -1 : 1;

            if ($depth === 0) {
                return substr($source, $start, $offset + strlen($tag));
            }
        }

        throw new RuntimeException('Unbalanced filter fragment.');
    }
}

final class EventslistClearFiltersText
{
    /**
     * Return a language key without requiring a Joomla language service.
     *
     * @param string $key Language key.
     *
     * @return string The unchanged key.
     */
    public static function _(string $key): string
    {
        return $key;
    }
}

final class EventslistClearFiltersView
{
    public array $lists;
    public array $eventFilters;
    public object $settings;

    /**
     * Supply only the state used by production filter fragments.
     *
     * @param string $month    Selected month.
     * @param bool   $editable Whether the advanced-filter script owns Clear.
     */
    public function __construct(string $month, bool $editable)
    {
        $this->lists = [
            'search' => "O'Brien",
            'month' => $month,
            'filter' => '<select name="filter_type"><option value="1">Title</option></select>',
        ];
        $this->eventFilters = ['has_editable' => $editable];
        $this->settings = new class {
            /**
             * Enable filters but omit pagination from this focused fixture.
             *
             * @param string $key     Setting name.
             * @param mixed  $default Unused fallback.
             *
             * @return int Fixture setting value.
             */
            public function get(string $key, mixed $default = null): int
            {
                return $key === 'global_display' ? 0 : 1;
            }
        };
    }

    /**
     * Render a trusted repository fragment with this view's synthetic state.
     *
     * @param string $fragment Unmodified production filter markup and PHP.
     *
     * @return string Rendered filter HTML.
     */
    public function render(string $fragment): string
    {
        ob_start();

        try {
            eval('use Joomla\CMS\Language\Text; ?>' . $fragment);

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
