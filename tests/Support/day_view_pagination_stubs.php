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
    }
}

namespace {
    // Loaded only inside isolated tests, never into Joomla or the main PHPUnit process.
    final class JemHelper
    {
        public static function config(): object
        {
            return (object) [];
        }

        public static function globalattribs(): object
        {
            return (object) [];
        }

        public static function loadCss(string $name): void
        {
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
            return (object) [];
        }
    }

    final class DayPaginationApplication
    {
        public object $input;

        public function __construct()
        {
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
            return new class {
                public function getActive(): ?object
                {
                    return null;
                }
            };
        }

        public function getDocument(): object
        {
            return (object) [];
        }

        public function getParams(): object
        {
            return (object) [];
        }

        public function getPathWay(): object
        {
            return (object) [];
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

    class JemView
    {
        public DayPaginationModel $model;
        public string $layout = 'default';

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
                throw new DayPaginationItemsLoaded($this->model->getItems());
            }

            throw new \LogicException('Unexpected view request: ' . $property);
        }
    }
}
