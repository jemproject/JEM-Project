<?php

declare(strict_types=1);

namespace Joomla\CMS {
    final class Factory
    {
        public static array $messages = [];
        public static bool $failWarning = false;

        /** @return object Recording application; global plugin replay is forbidden. */
        public static function getApplication(): object
        {
            return new class {
                /** @return void Record a user warning or simulate a notification failure. */
                public function enqueueMessage($message, $type): void
                {
                    if (Factory::$failWarning) {
                        throw new \RuntimeException('Warning unavailable');
                    }
                    Factory::$messages[] = [$message, $type];
                }

                /** @return never Fail if deferred work redispatches all plugins. */
                public function triggerEvent(...$args): never
                {
                    throw new \LogicException('Unexpected global plugin replay');
                }
            };
        }
    }
}

namespace Joomla\CMS\Language {
    final class Text
    {
        /** @return string Untranslated key for warning assertions. */
        public static function _($key): string
        {
            return $key;
        }
    }
}

namespace Joomla\CMS\Log {
    final class Log
    {
        public const ERROR = 8;
        public static array $entries = [];
        public static bool $fail = false;

        /** @return void Record a log entry or simulate logging failure. */
        public static function add($message, $level, $category): void
        {
            if (self::$fail) {
                throw new \RuntimeException('Log unavailable');
            }
            self::$entries[] = [$message, $level, $category];
        }
    }
}

namespace Joomla\Component\Finder\Administrator\Indexer {
    /** Substitute only Finder's database operations; execute the real JEM plugin. */
    class Adapter
    {
        protected $old_access;
        protected $old_cataccess;
        public static array $calls = [];
        public static array $storedAccess = [];
        public static ?\Closure $indexCallback = null;

        /** @return void Supply the pre-save access level to the real before-save handler. */
        protected function checkItemAccess($row): void
        {
            $this->old_access = self::$storedAccess[(int) $row->id] ?? 1;
        }

        /** @return void Record the row used for an index access update. */
        protected function itemAccessChange($row): void
        {
            self::$calls[] = ['access', (int) $row->id, (int) $row->access];
        }

        /** @return void Record indexing and optionally execute a native SQL probe. */
        protected function reindex($id): void
        {
            self::$calls[] = ['index', (int) $id];
            if (self::$indexCallback !== null) {
                (self::$indexCallback)((int) $id);
            }
        }

        /** @return void Record existing category behaviour outside event deferral. */
        protected function categoryAccessChange($row): void
        {
            self::$calls[] = ['category', (int) $row->id];
        }
    }
}

namespace {
    require_once JEM_TEST_ROOT . '/plugins/plg_finder_jem/jem.php';

    final class FinderDeferralPluginProbe extends plgFinderJEM
    {
        /** Construct the real plugin without booting Joomla's application container. */
        public function __construct()
        {
        }
    }
}
