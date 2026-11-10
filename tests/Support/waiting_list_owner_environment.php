<?php

declare(strict_types=1);

namespace Joomla\CMS {
    /** Supply the isolated database and capture notifications without booting an installed site. */
    final class Factory
    {
        public static \Joomla\Database\Mysqli\MysqliDriver $db;

        /**
         * @return object Minimal database service container.
         */
        public static function getContainer(): object
        {
            return new class {
                /**
                 * @param string $name Database service name.
                 * @return object Isolated SQL driver.
                 */
                public function get($name): object
                {
                    return Factory::$db;
                }
            };
        }

        /**
         * @return object Fixture identity provider.
         */
        public static function getApplication(): object
        {
            return new class {
                /**
                 * @return object Synthetic administrator identity.
                 */
                public function enqueueMessage($message, $type = "message"): void {}

                public function getIdentity(): object
                {
                    return (object) ['id' => 100];
                }
            };
        }

        /**
         * @param string $group Cache group.
         * @return object No installed cache is touched.
         */
        public static function getCache($group): object
        {
            return new class {
                /**
                 * @return void Cache invalidation is outside this regression test.
                 */
                public function clean(): void
                {
                }
            };
        }
    }
}

namespace Joomla\CMS\Plugin {
    /** Keep external plugins and mail transports out of SQL eligibility tests. */
    final class PluginHelper
    {
        /**
         * @param mixed ...$args Plugin group/name.
         * @return void
         */
        public static function importPlugin(...$args): void
        {
        }
    }
}

namespace Joomla\CMS\Language {
    /** Translate public failure messages without booting a site or invoking mail. */
    final class Text
    {
        /** @param string $key Language key. @return string Source English message. */
        public static function _($key): string
        {
            $strings = parse_ini_file(JEM_TEST_ROOT . '/site/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
            return $strings[$key] ?? $key;
        }

        /** @param string $key Language key. @param mixed ...$args Formatting data. @return string Public message. */
        public static function sprintf($key, ...$args): string
        {
            return sprintf(self::_($key), ...$args);
        }
    }
}

namespace {
    /** Provide only the promotion settings used by the production service. */
    final class JemHelper
    {
        public static string $strategy = 'strict';

        /** @param mixed ...$args Failure context. @return void Do not write installed site logs. */
        public static function addLogEntry(...$args): void {}


        /**
         * @return object Automatic promotion remains enabled for all fixtures.
         */
        public static function config(): object
        {
            return (object) ['waitinglist_strategy' => self::$strategy, 'waitinglist_automatic' => 1];
        }
    }

    /** Capture actual transition dispatch without delivering mail or writing action logs. */
    final class JemFactory
    {
        public static array $notifications = [];

        /** @param int $id Synthetic booking holder. @return object Server-owned fixture identity. */
        public static function getUser($id): object
        {
            return new class ((int) $id) {
                public function __construct(public int $id) {}
                public function getAuthorisedViewLevels(): array { return [1]; }
                public function getAuthorisedGroups(): array { return [2]; }
            };
        }


        /**
         * @return object Recording event dispatcher.
         */
        public static function getDispatcher(): object
        {
            return new class {
                /**
                 * @param string $name Event name.
                 * @param array $args Event arguments.
                 * @return array
                 */
                public function triggerEvent($name, $args): array
                {
                    JemFactory::$notifications[] = [$name, $args,
                        (int) \Joomla\CMS\Factory::$db->setQuery('SELECT @@in_transaction')->loadResult()];
                    return [];
                }
            };
        }
    }

    /** Real MariaDB statements, with targeted failure injection and owner-query recording. */
    final class WaitingOwnerDriver extends \Joomla\Database\Mysqli\MysqliDriver
    {
        public array $ownerQueries = [];
        public string $failSql = '';

        /**
         * @return bool Execute SQL unless the test explicitly injects a failure at this statement.
         */
        public function execute()
        {
            $sql = preg_replace('/\s+/', ' ', (string) $this->sql);
            if (str_contains($sql, 'FROM `#__users`')) {
                $this->ownerQueries[] = $sql;
            }
            if ($this->failSql !== '' && str_contains($sql, $this->failSql)) {
                throw new RuntimeException('Injected SQL failure.');
            }
            return parent::execute();
        }
    }
}
