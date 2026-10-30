<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;

/** Fixed reference instant used only by the isolated source-method clock substitutions. */
final class TypesModuleClock
{
    public static string $now = '2026-10-29 20:13:00';
}

/** Joomla's real SQL builder/driver against disposable SQLite memory. */
final class TypesModuleDatabase extends \Joomla\Database\Sqlite\SqliteDriver
{
    /** @return bool Whether the fixture's PDO driver is available. */
    public static function isSupported()
    {
        return extension_loaded('pdo_sqlite');
    }

    /**
     * Quote using SQLite itself, independent of the optional native sqlite3 extension.
     *
     * @param mixed $text Text to escape.
     * @param bool $extra Unused LIKE escaping flag for these fixtures.
     * @return string Escaped text without surrounding quotes.
     */
    public function escape($text, $extra = false)
    {
        $this->connect();
        return substr($this->connection->quote((string) $text), 1, -1);
    }
}

/** External application services only; no installed Joomla application is booted. */
final class TypesModuleApplication
{
    /** @return Registry Isolated Joomla configuration, including its timezone. */
    public function getConfig(): Registry
    {
        return Factory::$config;
    }

    /** @return bool Enable the module's real language predicates. */
    public function getLanguageFilter(): bool
    {
        return true;
    }

    /** @param string $name Client name. @return bool Whether this is the site client. */
    public function isClient(string $name): bool
    {
        return $name === 'site';
    }

    /** @return object Fixed frontend language service. */
    public function getLanguage(): object
    {
        return new class {
            /** @return string Fixture language tag. */
            public function getTag(): string
            {
                return 'en-GB';
            }
        };
    }

    /** @param string $key Config key. @param mixed $default Fallback. @return mixed Value. */
    public function get(string $key, mixed $default = null): mixed
    {
        return Factory::getConfig()->get($key, $default);
    }
}

/** Isolated user identity; SQL access predicates in the module remain real. */
final class JemFactory
{
    /** @return object Public-only viewer with no edit permissions. */
    public static function getUser(): object
    {
        return new class {
            /** @return array View levels for the fixture viewer. */
            public function getAuthorisedViewLevels(): array
            {
                return [1];
            }

            /** @param mixed ...$arguments Permission context. @return bool No edit rights. */
            public function can(...$arguments): bool
            {
                return false;
            }
        };
    }
}

/** Unrelated display service; event selection and SQL execute in the actual module. */
final class JemOutput
{
    /** @param object $type Type object. @return void No translation in this fixture. */
    public static function translateType($type): void
    {
    }
}

/** Age-policy boundary double used only by the 5.1 module. */
final class JemAgeAccess
{
    /** @param mixed ...$arguments Query context. @return string Fixture visibility predicate. */
    public static function sqlVisibilityCondition(...$arguments): string
    {
        return 'a.fixture_age_visible = 1';
    }

    /** @param mixed ...$arguments Presentation context. @return void No decoration needed. */
    public static function decorateEvent(...$arguments): void
    {
    }

    /** @param object $event Selected row. @return bool SQL already enforced fixture visibility. */
    public static function canViewEvent($event): bool
    {
        return true;
    }
}

/** Non-temporal helper boundaries; temporal methods are loaded from the actual checkout. */
abstract class TypesModuleServices
{
    /** @param string $alias Event alias. @param array $levels View levels. @return string Filter. */
    public static function getEventParentVisibilityWhere($alias, array $levels): string
    {
        return $alias . '.fixture_parent_visible = 1';
    }

    /** @param string $alias Event alias. @param array $levels View levels. @return string Filter. */
    public static function getVenueHierarchyVisibilityWhere($alias, array $levels): string
    {
        return '(' . $alias . '.locid IS NULL OR ' . $alias . '.locid = 0 OR EXISTS '
            . '(SELECT 1 FROM #__jem_venues visible_venue WHERE visible_venue.id = ' . $alias . '.locid'
            . ' AND visible_venue.published = 1 AND visible_venue.access IN (' . implode(',', $levels) . ')))';
    }

    /** @param array $events Selected rows. @param array $levels View levels. @return void No articles. */
    public static function applyAssociatedArticleEventContentToEvents($events, $levels): void
    {
    }

    /** @param array $events Selected rows. @param array $levels View levels. @return array No articles. */
    public static function getAssociatedArticles($events, $levels): array
    {
        return [];
    }
}

