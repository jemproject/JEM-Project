<?php
/** Render production layouts with synthetic rows; no installed site or database is changed. */
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
namespace Joomla\CMS\HTML {
    final class HTMLHelper
    {
        public static function _(string $key, ...$args): string
        {
            if ($key === 'grid.id') {
                return '<input type="checkbox" name="cid[]" id="cb' . (int) $args[0]
                    . '" value="' . (int) $args[1] . '">';
            }
            return $key === 'form.token' ? '<input type="hidden" name="fixture_token" value="1">' : '';
        }
    }
}
namespace Joomla\CMS\Router {
    final class Route
    {
        public static function _(string $url): string
        {
            return $url;
        }
    }
}
namespace Joomla\CMS\Uri {
    final class Uri
    {
        public static function root($pathOnly = false): string
        {
            return '';
        }
    }
}
namespace {
    define('_JEXEC', 1);

    final class StatusFixtureSettings
    {
        public function __construct(private array $values = [])
        {
        }

        public function get($key, $default = null)
        {
            return $this->values[$key] ?? $default;
        }

        public function __get($key)
        {
            return str_ends_with($key, 'width') ? '100%' : 0;
        }
    }

    final class JemHelper
    {
        public static function jemStringContains(string $haystack, string $needle): bool
        {
            return str_contains($haystack, $needle);
        }
    }

    final class JemOutput
    {
        public static function publishstateicon($row, ...$args): string
        {
            return '<span data-state="' . (int) $row->published . '"><img alt="Status" width="20" height="20"'
                . ' src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\'/%3E"></span>';
        }
    }

    #[AllowDynamicProperties]
    final class StatusFixtureView
    {
        public function escape($value): string
        {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        }

        public function render(string $view, string $layout, int $state, bool $print): string
        {
            $this->params = new StatusFixtureSettings(['displayattendeecolumn' => 0]);
            $this->settings = new StatusFixtureSettings();
            $this->jemsettings = new StatusFixtureSettings();
            $this->permissions = (object) ['canPublishEvent' => true, 'canPublishVenue' => true];
            $this->action = '/submit';
            $this->task = '';
            $this->pageclass_sfx = '';
            $this->showdate = 0;
            $this->print = $print;
            $this->itemid = 0;
            $this->enableemailaddress = 0;
            $this->lists = ['order_Dir' => 'ASC', 'order' => '', 'search' => '', 'filter' => ''];
            $this->pagination = $this->events_pagination = new class {
                public function getLimitBox(): string
                {
                    return '';
                }

                public function getPagesLinks(): string
                {
                    return '';
                }

                public function getPagesCounter(): string
                {
                    return '';
                }
            };
            $rows = [];
            // Row zero has no permission; row two is absent. A row action must
            // not submit a previously selected checkbox after either gap.
            foreach ([0 => false, 1 => true, 3 => true, 4 => true] as $index => $allowed) {
                $rows[$index] = (object) [
                    'id' => 100 + $index, 'eventid' => 100 + $index,
                    'published' => $index === 4 ? -2 : $state,
                    'params' => new StatusFixtureSettings(['access-change' => $allowed]),
                ];
            }
            $this->events = $this->venues = $rows;
            $path = dirname(__DIR__, 3) . '/site/views/' . $view . '/tmpl/'
                . ($layout === 'responsive' ? 'responsive/' : '') . 'default_'
                . ($view === 'myevents' ? 'events' : 'venues') . '.php';
            ob_start();
            include $path;
            return ob_get_clean();
        }
    }

    $view = $argv[1] ?? 'myevents';
    $layout = $argv[2] ?? 'standard';
    if (!in_array($view, ['myevents', 'myvenues'], true) || !in_array($layout, ['standard', 'responsive'], true)) {
        throw new InvalidArgumentException('Unknown status fixture');
    }
    echo (new StatusFixtureView())->render($view, $layout, (int) ($argv[3] ?? 1), ($argv[4] ?? '') === 'print');
}
