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

namespace {
    /** Provide only the promotion settings used by the production service. */
    final class JemHelper
    {
        public static string $strategy = 'strict';

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
