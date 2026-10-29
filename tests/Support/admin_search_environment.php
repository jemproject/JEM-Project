<?php

declare(strict_types=1);

// Session, configuration, permissions and tree presentation are test doubles.
// Input, ListModel, the JEM models and SQL queries use production code.
final class AdminSearchApplication extends \Joomla\CMS\Application\CMSApplication
{
    private array $userState = [];

    public function __construct(array $request)
    {
        $this->setRequest($request);
        $this->config = new \Joomla\Registry\Registry(['list_limit' => 0]);
    }

    public function setRequest(array $request): void
    {
        $this->input = new \Joomla\Input\Input($request);
    }

    public function getUserState($key, $default = null)
    {
        return $this->userState[$key] ?? $default;
    }

    public function setUserState($key, $value)
    {
        $old = $this->getUserState($key);
        $this->userState[$key] = $value;
        return $old;
    }

    public function bootComponent($component): \Joomla\CMS\Extension\ComponentInterface
    {
        return new \Joomla\CMS\Extension\LegacyComponent('com_jem');
    }

    public function getName()
    {
        return 'administrator';
    }

    protected function doExecute()
    {
    }
}

final class AdminSearchDatabase extends \Joomla\Database\Sqlite\SqliteDriver
{
    public static function isSupported()
    {
        return extension_loaded('pdo_sqlite');
    }

    public function escape($text, $extra = false)
    {
        $this->connect();
        return substr($this->connection->quote((string) $text), 1, -1);
    }

    public function addConcat(): void
    {
        $this->connect();
        $this->connection->sqliteCreateFunction('CONCAT', static fn (...$parts) => implode('', $parts));
    }

    // PDO SQLite does not provide SELECT row counts. Count the actual result
    // rows for Joomla's grouped-query total fallback in this isolated fixture.
    public function getNumRows()
    {
        $count = 0;
        while ($this->fetchArray() !== false) {
            $count++;
        }
        return $count;
    }
}

final class JemConfig
{
    public static function getInstance(): self
    {
        return new self();
    }

    public function toRegistry(): \Joomla\Registry\Registry
    {
        return new \Joomla\Registry\Registry();
    }
}

final class JemFactory
{
    public static function getUser(): self
    {
        return new self();
    }

    public function authorise($action): bool
    {
        return true;
    }

    public function getAuthorisedViewLevels(): array
    {
        return [1];
    }
}

final class JemHelperBackend
{
    public static function can($entity, $action): bool
    {
        return true;
    }

    public static function canManage($action): bool
    {
        return true;
    }
}

final class JemHelper
{
    public static function config(): object
    {
        return new stdClass();
    }
}

// Flat children stand in for the tree presentation helper; the category model's
// SQL search and its post-tree result filtering are both exercised unchanged.
final class JemCategories
{
    public static function treerecurse($id, $indent, $list, &$children, ...$options): array
    {
        return $children[$id] ?? [];
    }
}
