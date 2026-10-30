<?php

declare(strict_types=1);

// Only external services are stubbed; helpers and both plugins execute unchanged.
namespace Joomla\CMS\MVC\Model {
    final class BaseDatabaseModel
    {
        public static array|false $items = [];
        public static array $states = [];
        public static string $error = '';
        public static ?\Closure $loadItems = null;
        public static function addIncludePath($path, $prefix): void
        {
        }

        public static function getInstance($name, $prefix, $config): self
        {
            return new self();
        }

        public function setState($name, $value): void
        {
            self::$states[$name] = $value;
        }

        public function getItems(): array|false
        {
            return self::$loadItems !== null ? (self::$loadItems)() : self::$items;
        }

        public function getError(): string
        {
            return self::$error;
        }
    }
}

namespace Joomla\CMS\Plugin {
    class CMSPlugin
    {
    }
}

namespace Joomla\CMS\FactoryFixture {
    final class Application
    {
        public object $input;
        public function get($key, $default = null)
        {
            return $key === 'offset' ? 'UTC' : $default;
        }

        public function getLanguage(): object
        {
            return new class {
                public function getTag(): string
                {
                    return 'en-GB';
                }
            };
        }
    }
}

namespace Joomla\CMS {
    final class Factory
    {
        public static object $application;
        public static ?object $database = null;
        public static function getApplication(): object
        {
            return self::$application;
        }

        public static function getUser(): object
        {
            return \JemFactory::getUser();
        }

        public static function getContainer(): object
        {
            return new class {
                public function get($key): object
                {
                    if (Factory::$database !== null) {
                        return Factory::$database;
                    }

                    return new class {
                        public function quote($text): string
                        {
                            return "'" . $text . "'";
                        }
                    };
                }
            };
        }
    }
}

namespace Joomla\CMS\Date {
    class Date extends \DateTime
    {
        public function __construct($date = 'now', $timezone = 'UTC')
        {
            parent::__construct($date, new \DateTimeZone($timezone ?: 'UTC'));
        }

        public function toSql($local = false): string
        {
            return $this->format('Y-m-d H:i:s');
        }

        public function format(string $format, bool $local = false, bool $translate = true): string
        {
            return parent::format($format);
        }
    }
}

namespace Joomla\CMS\Router {
    final class Route
    {
        public static function _($url): string
        {
            return $url;
        }
    }
}

namespace Joomla\CMS\Language {
    final class Text
    {
        public static function _($key): string
        {
            return $key;
        }

        public static function sprintf($key, ...$args): string
        {
            return $key;
        }
    }
}

namespace Joomla\CMS\Uri {
    final class Uri
    {
        public static function base($pathOnly = false): string
        {
            return '';
        }

        public static function root(): string
        {
            return 'https://example.test/';
        }

        public static function getInstance(): self
        {
            return new self();
        }
    }
}

namespace Joomla\CMS\Log {
    final class Log
    {
        public const ERROR = 8;
    }
}

namespace {
    final class TitleRenderingParams
    {
        public function __construct(private array $values = [])
        {
        }

        public function get($key, $default = null)
        {
            return $this->values[$key] ?? $default;
        }

        public function set($key, $value): void
        {
            $this->values[$key] = $value;
        }
    }
    final class JemHelper
    {
        public static array $hourOffsets = [];
        public static array $minuteOffsets = [];
        public static string $hourOffsetDate = '2026-10-30';

        /**
         * Record module wiring; real date arithmetic is tested separately with Joomla's helper.
         *
         * @param int $hours Configured signed hours.
         * @return string Controlled target date for the SQL fixture.
         */
        public static function getJoomlaDateWithHourOffset(int $hours): string
        {
            self::$hourOffsets[] = $hours;
            return self::$hourOffsetDate;
        }

        public static function getJoomlaDate(): string
        {
            return '2026-10-30';
        }

        public static function getEventDateTimeWhere($kind, $operator, $offsetMinutes = 0): string
        {
            self::$minuteOffsets[] = $offsetMinutes;
            return 'a.' . $kind . '_utc ' . $operator . " '2026-10-30 12:00:00'";
        }

        public static function config(): object
        {
            return (object) [];
        }

        public static function getValidIds($value): array
        {
            return [];
        }

        public static function getAssociatedArticles($events, $levels): array
        {
            return [];
        }

        public static function applyEventRouteLayout($route, $params): string
        {
            return $route;
        }
    }
    final class JemOutput
    {
        public static array $timeCalls = [];

        /**
         * Record time-formatting inputs; tests exercise module policy, not shared formatting.
         *
         * @param string $time Stored clock value.
         * @param string $format Requested display format.
         * @param bool $addSuffix Whether the configured suffix is requested.
         * @return string Unformatted clock value for explicit forwarding assertions.
         */
        public static function formattime($time, $format = '', $addSuffix = true): string
        {
            self::$timeCalls[] = [$time, $format, $addSuffix];
            return $time;
        }

        public static function moduleStatusRibbonScale($params): int
        {
            return 100;
        }

        public static function getCategoryList($categories, $linked): array
        {
            return [];
        }

        public static function formatDateTime(...$args): string
        {
            return '';
        }

        public static function formatdate(...$args): string
        {
            return '';
        }

        public static function formatSchemaOrgDateTime(...$args): string
        {
            return '';
        }
    }
    final class JemImage
    {
        public static function getModuleEventImageData(...$args)
        {
            return null;
        }
    }
    final class JemHelperRoute
    {
        public static function getEventRoute($slug): string
        {
            return 'event/' . $slug;
        }

        public static function getVenueRoute($slug): string
        {
            return 'venue/' . $slug;
        }

        public static function getRoute($id, $view): string
        {
            return $view . '/' . $id;
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

                public function getParam($key, $default = null)
                {
                    return $default;
                }
            };
        }
    }
    /** Satisfy installation-only includes with the dependency stubs above, without writing files. */
    final class TitleRenderingIncludeStream
    {
        public $context;
        private int $position = 0;
        private const SOURCE = '<?php';
        public function stream_open($path, $mode, $options, &$openedPath): bool
        {
            return $mode === 'rb';
        }

        public function stream_read($count): string
        {
            $result = substr(self::SOURCE, $this->position, $count);
            $this->position += strlen($result);
            return $result;
        }

        public function stream_eof(): bool
        {
            return $this->position >= strlen(self::SOURCE);
        }

        public function stream_stat(): array
        {
            return ['mode' => 0100444, 'size' => strlen(self::SOURCE)];
        }

        public function url_stat($path, $flags): array
        {
            return $this->stream_stat();
        }

        public function stream_set_option($option, $arg1, $arg2): bool
        {
            return false;
        }
    }
    stream_wrapper_register('jemtitle', TitleRenderingIncludeStream::class);
    define('JPATH_SITE', 'jemtitle://site');
    \Joomla\CMS\Factory::$application = new \Joomla\CMS\FactoryFixture\Application();
}
