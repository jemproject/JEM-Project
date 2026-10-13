<?php

declare(strict_types=1);

/** Real Joomla SQL driver, with a disposable database and a query log. */
final class EventUtcOrderingDatabase extends \Joomla\Database\Sqlite\SqliteDriver
{
    public array $queries = [];

    public static function isSupported()
    {
        return extension_loaded('pdo_sqlite');
    }

    public function escape($text, $extra = false)
    {
        $this->connect();
        return substr($this->connection->quote((string) $text), 1, -1);
    }

    public function execute()
    {
        $this->queries[] = (string) $this->sql;
        return parent::execute();
    }
}

/** External application/session only; event table, helper and ordering are real. */
final class EventUtcOrderingApplication
{
    public \Joomla\Input\Input $input;

    public function __construct(public JemUser $user)
    {
        $this->input = new \Joomla\Input\Input([]);
    }

    public function getSession(): self
    {
        return $this;
    }

    public function get($key, $default = null)
    {
        return $key === 'user' ? $this->user : $default;
    }

    public function isClient($name): bool
    {
        return $name === 'site';
    }

    public function getConfig(): \Joomla\Registry\Registry
    {
        return \Joomla\CMS\Factory::$config;
    }

    public function getDispatcher(): \Joomla\Event\Dispatcher
    {
        return new \Joomla\Event\Dispatcher();
    }
}
