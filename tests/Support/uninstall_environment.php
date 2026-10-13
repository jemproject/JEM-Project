<?php

declare(strict_types=1);

namespace Joomla\CMS {
    final class Factory
    {
        public static \UninstallDatabase $database;

        public static function getContainer(): object
        {
            return new class {
                public function get(string $service): \UninstallDatabase
                {
                    if ($service !== 'DatabaseDriver') {
                        throw new \LogicException('Unexpected installer service: ' . $service);
                    }

                    return Factory::$database;
                }
            };
        }

        public static function getApplication(): object
        {
            return new class {
                public function getLanguage(): object
                {
                    return new class {
                        public function load(...$arguments): bool
                        {
                            return true;
                        }
                    };
                }
            };
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

namespace Joomla\Registry {
    final class Registry
    {
        private array $values = [];

        public function loadString(string $json): void
        {
            $this->values = json_decode($json, true) ?: [];
        }

        public function get(string $key, $default = null)
        {
            return $this->values[$key] ?? $default;
        }
    }
}

namespace Joomla\Filesystem {
    final class Folder
    {
        public static function delete(string $path): void
        {
            throw new \LogicException('These database tests must never delete files: ' . $path);
        }
    }
}

namespace {
    final class UninstallQuery
    {
        public function __call(string $name, array $arguments): self
        {
            return $this;
        }
    }

    final class UninstallDatabase
    {
        public array $tables = [];
        public array $dropped = [];
        public string $settings = '{}';
        public ?string $failOn = null;

        public function getQuery(bool $new): UninstallQuery
        {
            return new UninstallQuery();
        }

        public function setQuery(UninstallQuery $query): self
        {
            return $this;
        }

        public function execute(): bool
        {
            return true;
        }

        public function loadResult(): string
        {
            return $this->settings;
        }

        public function quoteName(string $name): string
        {
            return '`' . $name . '`';
        }

        public function quote(string $value): string
        {
            return "'" . $value . "'";
        }

        public function dropTable(string $table, bool $ifExists = true): self
        {
            if (!$ifExists) {
                throw new LogicException('Cleanup must tolerate absent tables.');
            }
            $this->dropped[] = $table;
            if ($table === $this->failOn) {
                throw new RuntimeException('Simulated drop failure');
            }
            unset($this->tables[$table]);

            return $this;
        }
    }
}
