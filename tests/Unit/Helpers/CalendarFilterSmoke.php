<?php

declare(strict_types=1);

namespace Joomla\CMS\Language {
    final class Text
    {
        public static function _(string $key): string
        {
            return $key;
        }
    }
}

namespace {
    define('_JEXEC', 1);

    require_once dirname(__DIR__, 3) . '/site/helpers/calendarfilter.php';

    $category = static function (int $id, string $name): object {
        return (object) ['id' => $id, 'catname' => $name, 'color' => '#cc0000'];
    };
    $event = static function (string $date, array $categories, int $venue, string $name): object {
        return (object) [
            'dates' => $date,
            'categories' => $categories,
            'locid' => $venue,
            'venue' => $name,
        ];
    };
    $rows = [
        $event('2026-10-25', [$category(2, 'Rock')], 7, 'Hall'),
        $event('2026-10-25', [$category(2, 'Rock'), $category(3, '<Museum>')], 8, 'Gallery'),
        $event('2026-11-01', [$category(4, 'Outside')], 9, 'Other'),
    ];

    $categoryOnly = JemCalendarFilterHelper::render($rows, 1, '2026-10-25', '2026-10-25');
    $venueOnly = JemCalendarFilterHelper::render($rows, 5, '2026-10-25', '2026-10-25');
    $both = JemCalendarFilterHelper::render($rows, 3, '2026-10-25', '2026-10-25');
    $attributes = JemCalendarFilterHelper::attributes($rows[1], 'example event-filter');

    $checks = [
        'no legend' => JemCalendarFilterHelper::render($rows, 0) === '',
        'category count' => str_contains($categoryOnly, 'Rock (2)'),
        'category escaping' => str_contains($categoryOnly, '&lt;Museum&gt; (1)'),
        'period restriction' => !str_contains($both, 'Outside'),
        'category-only option' => !str_contains($categoryOnly, 'eventVenues'),
        'venue-only option' => !str_contains($venueOnly, 'eventCat'),
        'venue count' => str_contains($venueOnly, 'Hall (1)'),
        'both options' => str_contains($both, 'eventCat') && str_contains($both, 'eventVenues'),
        'filter attributes' => str_contains($attributes, 'data-categories="cat2 cat3"')
            && str_contains($attributes, 'data-venue="venue8"'),
    ];

    foreach ($checks as $name => $passed) {
        if (!$passed) {
            throw new \RuntimeException('Calendar filter check failed: ' . $name);
        }
    }

    echo 'Calendar filter checks passed: ' . count($checks) . PHP_EOL;
}
