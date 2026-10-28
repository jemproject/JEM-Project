<?php

declare(strict_types=1);

namespace Joomla\CMS {
    final class Factory
    {
        public static object $application;

        public static function getApplication(): object
        {
            return self::$application;
        }
    }
}

namespace Joomla\CMS\Uri {
    final class Uri
    {
        public static function getInstance(): self
        {
            return new self();
        }

        public static function root(): string
        {
            return '/';
        }

        public function toString(): string
        {
            return '/day';
        }
    }
}

namespace Joomla\CMS\Language {
    final class Text
    {
        public static function _(string $key): string
        {
            return $key;
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

namespace Joomla\CMS\HTML {
    final class HTMLHelper
    {
        public static function _(string $key, ...$args): string
        {
            return '';
        }
    }
}

namespace {
    // Loaded only inside isolated tests, never into Joomla or the main PHPUnit process.
    if (!defined('JPATH_ROOT')) {
        define('JPATH_ROOT', JEM_TEST_ROOT);
    }

    final class JemHelper
    {
        public static array $loadedCss = [];

        public static function config(): object
        {
            return (object) array_fill_keys(['showtitle', 'showlocate', 'showcity', 'showcat', 'showstate'], 0);
        }

        public static function globalattribs(): object
        {
            return (object) [];
        }

        public static function loadCss(string $name): void
        {
            self::$loadedCss[] = $name;
        }

        public static function loadCustomCss(): void
        {
        }

        public static function loadCustomTag(): void
        {
        }
    }

    final class JemFactory
    {
        public static function getUser(): object
        {
            return new class {
                public function can(string $action, string $type): bool
                {
                    return false;
                }
            };
        }
    }

    final class JemFrontendAccess
    {
        public static function canAddEventSomewhere(object $user): bool
        {
            return false;
        }
    }

    final class JemOutput
    {
        public static function formatdate(string $date): string
        {
            return $date;
        }
    }

    final class DayViewParams
    {
        public function __construct(private array $values = [])
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->values[$key] ?? $default;
        }

        public function set(string $key, mixed $value): void
        {
            $this->values[$key] = $value;
        }

        public function def(string $key, mixed $value): void
        {
            $this->values[$key] ??= $value;
        }

        public function toArray(): array
        {
            return $this->values;
        }
    }

    final class DayPaginationApplication
    {
        public object $input;
        public DayViewParams $params;
        public object $document;
        public object $pathway;
        public ?object $menuItem = null;

        public function __construct()
        {
            $this->params = new DayViewParams();
            $this->document = new class {
                public string $title = '';

                public function setTitle(string $title): void
                {
                    $this->title = $title;
                }

                public function addHeadLink(...$args): void
                {
                }

                public function addScript(string $url): void
                {
                }
            };
            $this->pathway = new class {
                public array $items = ['Original menu'];

                public function getPathway(): array
                {
                    return $this->items;
                }

                public function setItemName(int $index, string $name): void
                {
                    $this->items[$index] = $name;
                }

                public function addItem(string $name): void
                {
                    $this->items[] = $name;
                }
            };
            $this->input = new class {
                public function getCmd(string $key, string $default = ''): string
                {
                    return $key === 'id' ? '20261011' : $default;
                }

                public function getInt(string $key, int $default = 0): int
                {
                    return $default;
                }

                public function getBool(string $key, bool $default = false): bool
                {
                    return $default;
                }
            };
        }

        public function getMenu(): object
        {
            return new class ($this->menuItem) {
                public function __construct(private ?object $item)
                {
                }

                public function getActive(): ?object
                {
                    return $this->item;
                }
            };
        }

        public function getDocument(): object
        {
            return $this->document;
        }

        public function getParams(): object
        {
            return $this->params;
        }

        public function getPathWay(): object
        {
            return $this->pathway;
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $default;
        }

        public function getUserStateFromRequest(string $key, string $request, mixed $default, string $type): mixed
        {
            return $default;
        }
    }

    final class DayPaginationModel
    {
        public array $state = [];
        public string $date = '';
        private bool $populated = false;

        public function __construct(private array $eligibleRows, private int $limit, private int $start)
        {
        }

        public function setDate(string $date): void
        {
            $this->date = $date;
        }

        public function getDayEnd(): string
        {
            return $this->date;
        }

        public function getState(): array
        {
            // Joomla populates request/session state on the first getState(), not setState().
            if (!$this->populated) {
                $this->state['list.limit'] = $this->limit;
                $this->state['list.start'] = $this->start;
                $this->populated = true;
            }

            return $this->state;
        }

        public function setLimit(int $limit): void
        {
            $this->state['list.limit'] = $limit;
        }

        public function setLimitStart(int $start): void
        {
            $this->state['list.start'] = $start;
        }

        public function getItems(): array
        {
            $state = $this->getState();

            return array_slice($this->eligibleRows, $state['list.start'], $state['list.limit'] ?: null);
        }
    }

    final class DayPaginationItemsLoaded extends \RuntimeException
    {
        public function __construct(public array $rows)
        {
            parent::__construct('Stop after the real view loads its event rows.');
        }
    }

    #[\AllowDynamicProperties]
    class JemView
    {
        public DayPaginationModel $model;
        public string $layout = 'default';
        public bool $stopAfterItems = true;

        public function __construct($config = [])
        {
        }

        public function addCommonTemplatePath(): void
        {
        }

        public function getLayout(): string
        {
            return $this->layout;
        }

        public function getModel(): DayPaginationModel
        {
            return $this->model;
        }

        public function get(string $property): mixed
        {
            if ($property === 'Items') {
                if ($this->stopAfterItems) {
                    throw new DayPaginationItemsLoaded($this->model->getItems());
                }

                return $this->model->getItems();
            }
            if ($property === 'Day') {
                return '2026-10-11';
            }
            if ($property === 'Pagination') {
                return (object) [];
            }

            throw new \LogicException('Unexpected view request: ' . $property);
        }

        public function display($tpl = null)
        {
        }
    }
}
