<?php

declare(strict_types=1);

// Replace external configuration/rule storage only. The model, category routing,
// JemUser decisions, Joomla ListModel, SQL builder and Rules are production code.
namespace Joomla\CMS\Access {
    final class Access
    {
        public static array $rules = [];

        public static function getAssetRules($asset, $recursive = false, $recursiveParentAsset = true): Rules
        {
            return new Rules(self::$rules[$asset] ?? []);
        }
    }
}

namespace {
    final class JemHelper
    {
        public static object $config;
        public static \Joomla\Registry\Registry $attribs;

        public static function config(): object
        {
            return self::$config;
        }

        public static function globalattribs(): \Joomla\Registry\Registry
        {
            return self::$attribs;
        }

        public static function applyAssociatedArticleEventContentToEvents(...$arguments): void
        {
        }
    }

    final class EventslistLoadingDatabase extends \Joomla\Database\Sqlite\SqliteDriver
    {
        public array $queries = [];

        public static function isSupported()
        {
            return extension_loaded('pdo_sqlite');
        }

        public function escape($text, $extra = false)
        {
            // Equivalent SQLite escaping using the PDO connection; the optional
            // separate SQLite3 PHP extension need not be installed for this fixture.
            $this->connect();
            $escaped = substr($this->connection->quote($text), 1, -1);
            return $escaped;
        }

        public function execute()
        {
            $this->queries[] = (string) $this->sql;
            return parent::execute();
        }

        // PDO SQLite reports zero for SELECT rowCount. Supply the buffered count
        // expected by Joomla's fallback, without connecting to a Joomla database.
        public function getNumRows()
        {
            $count = 0;
            while ($this->fetchArray() !== false) {
                $count++;
            }
            return $count;
        }
    }

    final class EventslistLoadingApplication
    {
        public \Joomla\Registry\Registry $params;

        public function __construct(public EventslistLoadingUser $user)
        {
            $this->params = new \Joomla\Registry\Registry();
        }

        public function getParams(): \Joomla\Registry\Registry
        {
            return $this->params;
        }

        public function getSession(): self
        {
            return $this;
        }

        public function get($name)
        {
            return $name === 'user' ? $this->user : null;
        }
    }

    final class EventslistLoadingUser extends JemUser
    {
        public array $levels = [1];
        public array $permissions = [];
        public array $jemGroups = [];
        public array $canArguments = [];

        public function __construct()
        {
            $this->id = 7;
            $this->guest = 0;
            $this->groups = [2];
        }

        public function getAuthorisedViewLevels()
        {
            return $this->levels;
        }

        public function getAuthorisedGroups()
        {
            return $this->groups;
        }

        public function authorise($action, $assetname = null)
        {
            return $this->permissions[$action] ?? false;
        }

        public function getJemGroups($asset = false)
        {
            return $this->jemGroups;
        }

        public function can($action, $type, $id = false, $created_by = false, $categoryIds = false)
        {
            $this->canArguments[] = [$action, $type, $id, $created_by, $categoryIds];
            return parent::can($action, $type, $id, $created_by, $categoryIds);
        }
    }

    final class EventslistLoadingModel extends JemModelEventslist
    {
        public function __construct(EventslistLoadingDatabase $db)
        {
            $this->state = new \Joomla\CMS\MVC\Model\State();
            $this->__state_set = true;
            $this->context = 'eventslist.loading';
            $this->setDatabase($db);
            $this->setState('list.start', 0);
            $this->setState('list.limit', 0);
            $this->setState('list.skip_attendee_numbers', true);
        }

        public function aclIds(array $ids): array
        {
            return $this->getAclCategoryIdsForItems($ids);
        }

        public function countQuery($query): int
        {
            return $this->_getListCount($query);
        }

        // Use a minimal event projection, but exercise the real ListModel loader,
        // page limits, JEM getItems decoration and ACL calls.
        protected function getListQuery()
        {
            return $this->getDatabase()->getQuery(true)
                ->select('a.*')->from('#__jem_events as a')->group('a.id')->order('a.id');
        }
    }
}
