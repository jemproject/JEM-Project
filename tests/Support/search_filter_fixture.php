<?php

declare(strict_types=1);

namespace Joomla\CMS\Language {
    /** Real English language strings without bootstrapping an installed application. */
    final class Text
    {
        /** @param string $key Language key. @return string Translated fixture label. */
        public static function _(string $key): string
        {
            static $strings;
            $strings ??= parse_ini_file(dirname(__DIR__, 2) . '/site/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
            return $strings[$key] ?? ($key === 'JSEARCH_FILTER_CLEAR' ? 'Clear' : $key);
        }
    }
}

namespace {
    define('_JEXEC', 1);

    /** Supply synthetic options while rendering the actual production filter markup. */
    final class SearchFilterFixture
    {
        public array $lists = [];
        public object $jemsettings;
        public object $pagination;
        public string $filter_date_from = '2026-01-01';
        public string $filter_date_to = '2026-12-31';
        public bool $filter_show_dates = true;
        public bool $filter_show_date_from = true;
        public bool $filter_show_date_to = true;
        public bool $filter_show_eventtype = true;
        public bool $filter_show_category = true;
        public bool $filter_show_venue = true;
        public bool $filter_show_continent = true;
        public bool $filter_show_country = true;
        public bool $filter_show_state = true;
        public bool $filter_show_city = true;
        public bool $filter_show_limit = true;

        /** @param string $hidden Comma-separated option suffixes to hide. */
        public function __construct(string $hidden)
        {
            $this->jemsettings = (object) ['display_num' => 10];
            $this->pagination = new class {
                /** @return string Synthetic page-size control. */
                public function getLimitBox(): string
                {
                    return '<select id="limit" name="limit" class="form-select"><option>5</option><option>10</option>'
                        . '<option selected>20</option><option>100</option><option>200</option><option>500</option>'
                        . '<option value="0">All</option></select>';
                }
            };
            foreach (array_filter(explode(',', $hidden)) as $name) {
                $property = 'filter_show_' . $name;
                if (property_exists($this, $property)) {
                    $this->$property = false;
                }
            }
            $this->filter_show_dates = $this->filter_show_date_from || $this->filter_show_date_to;
            $this->lists['filter'] = 'Previous keywords';
            foreach ([
                'filter_types' => ['filter_type', 'title', 'venue'],
                'event_types' => ['filter_type_id', '0', '7'],
                'categories' => ['filter_category', '1', '2'],
                'venues' => ['filter_venue_id', '0', '7'],
                'continents' => ['filter_continent', '', 'EU'],
                'countries' => ['filter_country', '', 'ES'],
                'states' => ['filter_state', '', 'North'],
                'cities' => ['filter_city', '', 'Madrid'],
            ] as $key => [$name, $empty, $selected]) {
                $label = $key === 'filter_types' ? 'Title' : 'A very long location or category name to test responsive field width';
                $this->lists[$key] = '<select class="form-select" id="' . $name . '" name="' . $name . '">'
                    . '<option value="' . $empty . '">Select...</option>'
                    . '<option selected value="' . $selected . '">' . $label . '</option></select>';
            }
        }

        /** @param string $layout Relative template path. @return string Actual filter block HTML. */
        public function render(string $layout): string
        {
            $source = file_get_contents(dirname(__DIR__, 2) . '/site/views/search/tmpl/' . $layout);
            $start = strpos($source, '<div id="jem_filter"');
            $end = strpos($source, '<!-- /SEARCH FILTERS -->');
            if ($start === false || $end === false) {
                throw new \RuntimeException('Missing production filter boundaries.');
            }
            ob_start();
            eval('use Joomla\\CMS\\Language\\Text; ?>' . substr($source, $start, $end - $start));
            return ob_get_clean();
        }
    }

    $layouts = ['default_table.php', 'responsive/default_jem_eventslist.php', 'responsive/default_jem_eventslist_small.php'];
    $layout = $layouts[(int) ($argv[1] ?? 0)] ?? $layouts[0];
    echo '<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/bootstrap.css">'
        . '<link rel="stylesheet" href="/search.css"><script src="/jquery.js"></script><script src="/search.js"></script>'
        . '</head><body><div id="jem"><form id="adminForm" method="post" '
        . 'action="/?option=com_jem&amp;view=search&amp;Itemid=123&amp;filter_city=old&amp;limitstart=20">'
        . (new SearchFilterFixture($argv[2] ?? ''))->render($layout)
        . '<input type="hidden" name="filter_order" value="a.title">'
        . '<input type="hidden" name="filter_order_Dir" value="ASC"></form></div></body></html>';
}

