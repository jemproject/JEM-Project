<?php

declare(strict_types=1);

// Render the real checkout templates without booting or modifying an installed Joomla site.
if (!defined('JEM_TEST_ROOT')) {
    require_once dirname(__DIR__) . '/bootstrap.php';
}
require_once __DIR__ . '/recurrence_source_stream.php';
stream_wrapper_register('jemrecurrence', RecurrenceSourceStream::class);
define('JPATH_SITE', 'jemrecurrence://site');

final class DayLayoutRegistry
{
    public function __construct(private array $values = []) {}
    public function get($key, $default = null) { return $this->values[$key] ?? $default; }
}

final class DayLayoutInput
{
    public function getInt($key, $default = 0) { return ['Itemid' => 42, 'catid' => 7, 'locid' => 0][$key] ?? $default; }
    public function getCmd($key, $default = '') { return $default; }
    public function getString($key, $default = '') { return $default; }
}

final class DayLayoutServices
{
    public static function getApplication() { return (object) ['input' => new DayLayoutInput()]; }
    public static function _($value, ...$arguments)
    {
        if ($value === 'date') { return $arguments[0]; }
        return [
            'COM_JEM_TIMETABLE_NO_VENUE' => 'No venue', 'COM_JEM_TIMETABLE_ALL_DAY' => 'All day',
            'COM_JEM_TIMETABLE_TODAY' => 'Today', 'COM_JEM_VENUE_SHORT' => 'Venue', 'COM_JEM_TIME_SHORT' => 'Time',
            'COM_JEM_VENUEBOARD_OTHER_EVENTS' => 'Other events',
            'COM_JEM_TIMELINE_DAYS_WINDOW_1' => '1 day',
        ][$value] ?? $value;
    }
    public static function sprintf($key, ...$values) { return implode(' ', $values) . ' days'; }
    public static function loadCss($name): void {}
    public static function getEventTimeZoneName($event): string { return 'Europe/Madrid'; }
    public static function getJoomlaDate(): string { return '2026-11-05'; }
    public static function isValidDate($date): bool { return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date); }
    public static function calendarSpecialDays(...$args): array { return []; }
    public static function renderCalendarSpecialDayBadges(...$args): string { return ''; }
    public static function renderCalendarContactFilterContext(...$args): string { return ''; }
    public static function getEventRoute($slug): string { return 'index.php?option=com_jem&view=event&id=' . $slug; }
    public static function getVenueRoute($slug): string { return 'index.php?option=com_jem&view=venue&id=' . $slug; }
    public static function getCategoryList(...$args): array { return []; }
    public static function translateType(...$args): void {}
    public static function createButtonBar(...$args): string { return ''; }
    public static function footer(): string { return ''; }
    public static function recurrenceicon(...$args): string { return ''; }
    public static function publishstateicon(...$args): string { return ''; }
    public static function eventStatusFallbackBadge(...$args): string { return ''; }
    public static function icon($path, $class, ...$args): string { return '<span class="' . $class . '">›</span>'; }
}

class_alias(DayLayoutRegistry::class, 'Joomla\Registry\Registry');
foreach (['Joomla\CMS\Factory', 'Joomla\CMS\Language\Text', 'Joomla\CMS\Router\Route',
    'Joomla\CMS\HTML\HTMLHelper', 'JemHelper', 'JemOutput', 'JemHelperRoute', 'jemhtml'] as $class) {
    class_alias(DayLayoutServices::class, $class);
}

final class DayLayoutFixture
{
    public array $rows;
    public object $params;
    public object $jemsettings;
    public string $day = '2026-11-05';
    public string $daydate = '5 November 2026';
    public string $pageclass_sfx = '';
    public string $task = '';
    public string $print_link = '';
    public array $permissions = [];
    public array $venueBoard = ['mode' => 'venues', 'locations' => []];
    public array $venueBoardAssignments = [];

    public function __construct(array $params = [], ?array $rows = null)
    {
        $this->params = new DayLayoutRegistry($params + [
            'show_page_heading' => 0, 'timeline_show_event_image' => 0, 'timeline_show_venue_image' => 0,
            'timeline_show_category' => 0, 'timeline_show_type' => 0, 'timeline_show_detail_icons' => 0,
            'timeline_show_event_intro' => 0, 'timeline_days_to_show' => 3, 'timeline_day_background' => 'none',
            'timetable_minimum_hours' => 1,
        ]);
        $this->jemsettings = (object) ['catlinklist' => 0, 'imagewidth' => 100, 'imagehight' => 100];
        $this->rows = $rows ?? self::events();
    }

    public static function event(int $id, string $start = '', string $end = '', array $extra = []): object
    {
        return (object) ($extra + ['id' => $id, 'slug' => (string) $id, 'title' => 'Event ' . $id,
            'dates' => '2026-11-05', 'enddates' => '2026-11-05', 'times' => $start, 'endtimes' => $end,
            'attribs' => [], 'categories' => [], 'locid' => 0, 'venue' => '', 'introtext' => '',
            'published' => 1, 'featured' => 0, 'recurrence_type' => 0]);
    }

    public static function events(): array
    {
        $events = [];
        foreach (['06:00', '07:00', '07:30', '07:45', '08:00', '21:45', '22:00', '22:15'] as $i => $time) {
            $events[] = self::event($i + 1, $time);
        }
        $events[] = self::event(9, '07:00', '09:00');
        $events[] = self::event(10, '23:30', '01:30', ['enddates' => '2026-11-06', 'title' => 'Overnight']);
        $events[] = self::event(11, '10:00', '18:00', ['enddates' => '2026-11-07', 'title' => 'Daily course']);
        $events[] = self::event(12);
        $events[] = self::event(13, '', '', ['locid' => 3, 'venue' => 'All-day venue']);
        $events[] = self::event(14, '', '', ['locid' => 3, 'venue' => 'All-day venue']);
        $events[] = self::event(15, '07:45', '09:00', ['locid' => 2, 'venue' => 'Hall B']);
        $events[] = self::event(16, '07:45', '09:00', ['locid' => 2, 'venue' => 'Hall B']);
        $events[] = self::event(17, '09:00', '17:00', ['title' => 'Programme', 'enddates' => '2026-11-07',
            'attribs' => ['event_schedule' => ['version' => 1, 'mode' => 'per_day', 'days' => [
                ['offset' => 0, 'closed' => false, 'all_day' => false, 'slots' => [
                    ['start' => '09:00', 'end' => '12:00'], ['start' => '14:00', 'end' => '17:00']]],
                ['offset' => 1, 'closed' => true, 'all_day' => false, 'slots' => []],
                ['offset' => 2, 'closed' => false, 'all_day' => true, 'slots' => []],
            ]]]]);
        return $events;
    }

    public function escape($text): string { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
    public function getName(): string { return 'day'; }
    public function getModel() { return null; }

    public function render(string $layout = 'timetable'): string
    {
        if (!in_array($layout, ['timetable', 'responsive/timetable', 'timeline', 'responsive/timeline', 'venueboard'], true)) {
            throw new InvalidArgumentException('Unexpected fixture layout');
        }
        ob_start();
        try {
            include JEM_TEST_ROOT . '/site/views/day/tmpl/' . $layout . '.php';
            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}

if (isset($argv[1]) && $argv[1] === '--html') {
    $fixture = new DayLayoutFixture(['timetable_orientation' => $argv[3] ?? 'horizontal',
        'timetable_hour_display' => $argv[4] ?? 'event_hours']);
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<style>body{margin:16px;font-family:Arial,sans-serif}*{box-sizing:border-box}</style><style>';
    echo file_get_contents(JEM_TEST_ROOT . '/media/css/timetable.css');
    echo '</style></head><body>' . $fixture->render($argv[2] ?? 'timetable') . '</body></html>';
}
