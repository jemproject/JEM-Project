<?php
/** Render production shared markup with deterministic data; no Joomla site mutations. */
declare(strict_types=1);

namespace Joomla\CMS {
    class Factory {
        public static function getApplication(): object {
            return new class {
                public object $input;
                public function __construct() { $this->input = new class { public function getInt($name): int { return $name === 'Itemid' ? 42 : 0; } }; }
                public function get($name, $default = null) { return $name === 'offset' ? 'Europe/Madrid' : $default; }
            };
        }
    }
}
namespace Joomla\CMS\Language {
    class Text {
        public static array $strings = [];
        public static function _($key): string { return self::$strings[$key] ?? $key; }
        public static function sprintf($key, ...$values): string { return sprintf(self::_($key), ...$values); }
    }
}
namespace Joomla\CMS\Router { class Route { public static function _($url, $xhtml = true): string { return $url; } } }
namespace Joomla\CMS\HTML { class HTMLHelper { public static function _($method, $date, ...$args): string { return $date; } } }
namespace {
    define('_JEXEC', 1);
    $source = dirname(__DIR__, 2);
    require $source . '/site/classes/registrationstatus.class.php';
    class JemOutput {
        public static function formatdate($date): string { return $date; }
        public static function formattime($time): string { return $time; }
    }
    $strings = parse_ini_file($source . '/site/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
    \Joomla\CMS\Language\Text::$strings = $strings + ['JALL' => 'All', 'JPUBLISHED' => 'Published',
        'JUNPUBLISHED' => 'Unpublished', 'JARCHIVED' => 'Archived', 'JTRASHED' => 'Trashed', 'JNEXT' => 'Next', 'JPREV' => 'Previous'];
    $backend = ($argv[1] ?? 'site') === 'admin';
    $hidden = ($argv[2] ?? '') === 'hidden';
    $options = $hidden ? ['show_country_filter' => 0, 'default_country' => 'ES', 'show_city_column' => 1] : [];
    $filters = JemRegistrationStatus::filters($options, ['mode' => 'week', 'date' => '2026-11-10',
        'country' => 'ES', 'region' => 'Community of Madrid', 'county' => 'Madrid', 'city' => 'Madrid'],
        new \DateTimeImmutable('2026-11-10', new \DateTimeZone('Europe/Madrid')), 1);
    $event = (object) ['id' => 2, 'title' => '<script>alert(1)</script>', 'dates' => '2026-11-10', 'times' => '10:00',
        'venue' => 'Main venue', 'event_status' => 'scheduled', 'published' => 1,
        'country' => 'ES', 'region' => 'Community of Madrid', 'state' => 'Madrid', 'city' => 'Madrid'];
    $totals = ['capacity' => 50, 'confirmed' => 40, 'reserved' => 2, 'waiting' => 3, 'invited' => 0, 'free' => 8, 'cancelled' => 10];
    $report = [
        'rows' => [
            ['key' => 'event-1', 'parent' => '', 'depth' => 0, 'kind' => 'programme', 'event' => $event, 'name' => 'Programme', 'context' => true,
                'totals' => ['confirmed' => 40, 'waiting' => 3, 'cancelled' => 10]],
            ['key' => 'event-2', 'parent' => 'event-1', 'depth' => 1, 'kind' => 'event', 'event' => $event, 'name' => $event->title, 'context' => false, 'totals' => $totals],
            ['key' => 'event-2-area', 'parent' => 'event-2', 'depth' => 2, 'kind' => 'area', 'event' => $event, 'name' => 'Front area', 'space' => 'Room A', 'context' => false,
                'totals' => ['capacity' => 40, 'confirmed' => 25, 'waiting' => 3, 'free' => 8]],
        ],
        'filters' => $filters, 'options' => $options, 'backend' => $backend, 'updated' => '2026-11-10 12:00 CET',
        'page' => 2, 'pages' => 3, 'groups' => 50, 'historyId' => 0, 'canHistory' => true,
        'choices' => ['category' => [1 => 'General'], 'venue' => [1 => 'Main venue'], 'country' => ['ES' => 'Spain', 'DE' => 'Germany'],
            'region' => ['Community of Madrid' => 'Community of Madrid'], 'county' => ['Madrid' => 'Madrid'], 'city' => ['Madrid' => 'Madrid']],
    ];
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/bootstrap.css"><link rel="stylesheet" href="/registration-status.css"><script defer src="/registration-status.js"></script><body><main style="padding:16px">';
    $view = new class ($report) {
        public function __construct(public array $report) {}
        public function render(string $path): void { require $path; }
    };
    $view->render($source . '/site/common/views/tmpl/registrationstatus.php');
    echo '</main></body></html>';
}
