<?php

declare(strict_types=1);

namespace Joomla\CMS\Router {
    /** Avoid routing through an installed administrator application in isolated SQL tests. */
    final class Route
    {
        /**
         * Preserve the plugin's internal return route for assertion.
         *
         * @param string $url Internal administrator URL.
         * @param bool $xhtml Whether to escape the URL.
         * @return string Unchanged route.
         */
        public static function _($url, $xhtml = true): string
        {
            return $url;
        }
    }
}

namespace {
    /** Represent the application's terminating redirect without ending PHPUnit. */
    final class UserDeletionRedirect extends RuntimeException
    {
    }

    /** Real SQL driver with explicit failure injection at the JEM protection queries. */
    final class UserDeletionDatabase extends \Joomla\Database\Mysqli\MysqliDriver
    {
        public bool $failTables = false;
        public bool $failCount = false;
        public array $countQueries = [];

        /**
         * List this disposable database's tables, or inject a failure.
         *
         * @return array Table names.
         */
        public function getTableList()
        {
            if ($this->failTables) {
                throw new RuntimeException('Injected table lookup failure');
            }

            return parent::getTableList();
        }

        /**
         * Execute SQL and record registration protection queries.
         *
         * @return bool Statement result.
         */
        public function execute()
        {
            $sql = (string) $this->sql;
            if (str_contains($sql, 'COUNT(*)') && str_contains($sql, '`#__jem_register`')) {
                $this->countQueries[] = $sql;
                if ($this->failCount) {
                    throw new RuntimeException('Injected registration lookup failure');
                }
            }

            return parent::execute();
        }
    }
}
