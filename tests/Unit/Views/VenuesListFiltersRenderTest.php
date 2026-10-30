<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class VenuesListFiltersRenderTest extends TestCase
{
    /**
     * Check actual parent/list markup, independent controls and owning form.
     *
     * @param array<string, mixed> $options Fixture configuration.
     *
     * @return void
     */
    #[DataProvider('filterCases')]
    public function testFilterPlacementAndVisibility(array $options): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/venues_filter_fixture.php';
        defined('JPATH_SITE') || define('JPATH_SITE', JEM_TEST_ROOT);
        $view = new VenuesFilterFixture($options);
        $html = $view->render();
        self::assertSame(1, substr_count($html, '<form '), 'Do not nest classic and responsive forms.');
        self::assertStringContainsString(
            $options['layout'] ? '<ul class="eventlist">' : '<table class="eventtable',
            $html,
            'Template lookup must preserve the selected layout style.'
        );
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML('<!doctype html><html><body>' . $html . '</body></html>');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);
        $hidden = str_contains($options['suffix'], 'jem-hidefilter');
        $search = !$hidden && ($options['search'] || str_contains($options['suffix'], 'jem-showfilter'));
        $country = !$hidden && $options['country'];
        $limit = !$hidden && $options['limit'];
        $all = !$hidden && $options['all'];
        $expected = [
            '//input[@id="filter_search"]' => $search,
            '//select[@id="filter_type"]' => $search,
            '//select[@id="filter_country"]' => $country,
            '//select[@id="limit"]' => $limit,
            '//input[@name="show_all_venues" and @type="checkbox"]' => $all,
            '//input[@name="show_all_venues" and @type="hidden"]' => $all,
        ];

        self::assertSame(1, $xpath->query('//form[@id="adminForm"]')->length);
        self::assertSame(1, $xpath->query('//*[@data-fixture-pagination]')->length);

        foreach ($expected as $query => $visible) {
            self::assertSame((int) $visible, $xpath->query($query)->length, $query);

            if ($visible) {
                self::assertSame(
                    1,
                    $xpath->query($query . '/ancestor::form[@id="adminForm"]')->length,
                    'Control must belong to adminForm: ' . $query
                );
            }
        }

        $any = $search || $country || $limit || $all;
        self::assertSame((int) $any, $xpath->query('//*[@id="jem_filter"]')->length);
        self::assertSame((int) ($search || $country), $xpath->query('//*[@id="jem_filter"]//button[@type="button"]')->length);
        self::assertSame((int) ($search || $country), $xpath->query('//*[@id="jem_filter"]//button[@type="submit"]')->length);

        if ($search) {
            self::assertSame($view->lists['search'], $xpath->evaluate('string(//input[@id="filter_search"]/@value)'));
        }

        if ($any) {
            $position = strpos($html, 'id="jem_filter"');
            $listPosition = strpos($html, $options['layout'] ? '<ul class="eventlist">' : '<table class="eventtable');

            if (str_contains($options['suffix'], 'jem-filterbelow')) {
                self::assertGreaterThan($listPosition, $position);
            } else {
                self::assertLessThan($listPosition, $position);
            }
        }
    }

    /**
     * Include all independent switches, Type Venues reuse and page-class precedence.
     *
     * @return iterable<string, array{array<string, mixed>}> Named render configurations.
     */
    public static function filterCases(): iterable
    {
        foreach (['', 'responsive'] as $layout) {
            foreach (['', 'jem-filterbelow'] as $position) {
                foreach (['venueslist', 'typevenues'] as $view) {
                    for ($mask = 0; $mask < 16; $mask++) {
                        $options = [
                            'layout' => $layout, 'suffix' => $position, 'view' => $view,
                            'search' => (bool) ($mask & 1), 'country' => (bool) ($mask & 2),
                            'limit' => (bool) ($mask & 4), 'all' => (bool) ($mask & 8),
                            'rows' => (bool) ($mask & 1),
                        ];

                        yield "$layout:$position:$view:$mask" => [$options];
                    }
                }

                foreach (['jem-hidefilter', 'jem-showfilter', 'jem-hidefilter jem-showfilter'] as $suffix) {
                    $enabled = !str_contains($suffix, 'jem-showfilter');

                    yield "$layout:$position:$suffix" => [[
                        'layout' => $layout, 'suffix' => trim($position . ' ' . $suffix),
                        'view' => 'venueslist', 'search' => $enabled, 'country' => $enabled,
                        'limit' => $enabled, 'all' => $enabled, 'rows' => false,
                    ]];
                }
            }
        }
    }
}
