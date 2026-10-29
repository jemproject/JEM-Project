<?php

declare(strict_types=1);

// External dependencies only. The tests execute the real calendar module helper.
namespace Joomla\CMS\Helper {
    class ModuleHelper
    {
    }
}

namespace Joomla\CMS\MVC\Model {
    final class BaseDatabaseModel
    {
        public static array $items = [];

        public static function addIncludePath(string $path, string $prefix): void
        {
        }

        public static function getInstance(string $name, string $prefix, array $config): self
        {
            return new self();
        }

        public function setState(string $key, mixed $value): void
        {
        }

        public function getItems(): array
        {
            return self::$items;
        }
    }
}

namespace Joomla\CMS {
    final class Factory
    {
        public static function getDbo(): object
        {
            return new class {
                public function quote(string $value): string
                {
                    return "'" . $value . "'";
                }
            };
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

namespace Joomla\CMS\Language {
    final class Text
    {
        public static function sprintf(string $key, mixed ...$args): string
        {
            $strings = parse_ini_file(
                JEM_TEST_ROOT . '/modules/mod_jem_cal/language/en-GB/mod_jem_cal.ini'
            );

            return sprintf($strings[$key] ?? $key, ...$args);
        }
    }
}

namespace {
    define('JPATH_SITE', JEM_TEST_ROOT . '/site');

    final class CalendarTooltipParams
    {
        public function __construct(private array $values = [])
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->values[$key] ?? $default;
        }
    }

    final class JemHelper
    {
        public static function globalattribs(): CalendarTooltipParams
        {
            return new CalendarTooltipParams();
        }

        public static function getValidIds(mixed $ids): array
        {
            return [];
        }

        public static function applyEventRouteLayout(string $route, object $params): string
        {
            return $route;
        }
    }

    final class JemHelperRoute
    {
        public static function getEventRoute(string $slug): string
        {
            return 'index.php?option=com_jem&view=event&id=' . $slug;
        }
    }

    final class JemFactory
    {
        public static function getUser(): object
        {
            return new class {
                public function getAuthorisedViewLevels(): array
                {
                    return [1];
                }
            };
        }
    }
}
