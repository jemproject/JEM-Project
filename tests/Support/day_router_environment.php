<?php

declare(strict_types=1);

// Only the environment is replaced. RouterView, MenuRules and StandardRules
// are loaded from the Joomla installation selected by the test runner.
namespace Joomla\CMS\Component {
    final class ComponentHelper
    {
        public static function getComponent(string $name): object
        {
            return (object) ['id' => 42];
        }

        public static function getComponentName(object $router, string $default): string
        {
            return $default;
        }
    }
}

namespace Joomla\CMS\Language {
    final class Multilanguage
    {
        public static bool $enabled = false;

        public static function isEnabled(): bool
        {
            return self::$enabled;
        }
    }
}

namespace Joomla\CMS\Plugin {
    final class PluginHelper
    {
        public static bool $strictRouting = false;

        public static function getPlugin(string $type, string $name): object
        {
            return (object) ['params' => json_encode(['strictrouting' => (int) self::$strictRouting])];
        }
    }
}

namespace {
    final class DayRouterMenu
    {
        public ?int $activeId = null;

        public function __construct(public array $items)
        {
        }

        public function getItem($id): ?object
        {
            return $this->items[(int) $id] ?? null;
        }

        public function getActive(): ?object
        {
            return $this->activeId === null ? null : $this->getItem($this->activeId);
        }

        public function getDefault(string $language = '*'): ?object
        {
            return null;
        }

        public function getItems($attributes, $values): array
        {
            return array_values(array_filter($this->items, static function (object $item) use ($values): bool {
                return $item->component_id === $values[0]
                    && in_array($item->language, $values[1], true);
            }));
        }
    }

    final class DayRouterApplication
    {
        public function get(string $key, mixed $default = null): mixed
        {
            return $key === 'language' ? 'en-GB' : $default;
        }
    }
}
