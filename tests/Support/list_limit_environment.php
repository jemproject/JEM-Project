<?php

declare(strict_types=1);

use Joomla\Input\Input;
use Joomla\Registry\Registry;

/** Use PDO SQLite in isolation; Joomla's legacy extension-name probe rejects pdo_sqlite. */
final class ListLimitTestDatabase extends \Joomla\Database\Sqlite\SqliteDriver
{
    /** @return bool Whether the actual PDO driver used by this fixture is loaded. */
    public static function isSupported()
    {
        return extension_loaded('pdo_sqlite');
    }
}

/** Isolated session with real Joomla input filtering; never reads site configuration. */
final class ListLimitTestApplication
{
    public Input $input;
    public array $session = [];

    /** @return void Initialize the synthetic request. */
    public function __construct()
    {
        $this->input = new Input([]);
    }

    /** @return Input Current input. */
    public function getInput(): Input { return $this->input; }

    /** @param string|null $component Component. @return Registry Menu parameters. */
    public function getParams(?string $component = null): Registry { return new Registry(); }

    /** @param string $key Setting. @param mixed $default Fallback. @return mixed */
    public function get(string $key, mixed $default = null): mixed
    {
        return $key === 'list_limit' ? 20 : $default;
    }

    /** @return string Application name required by Pagination. */
    public function getName(): string { return 'site'; }

    /** @param string $client Client name. @return bool Whether this is that client. */
    public function isClient(string $client): bool { return $client === 'site'; }

    /** @param string $key Session key. @param mixed $default Fallback. @return mixed */
    public function getUserState(string $key, mixed $default = null): mixed
    {
        return $this->session[$key] ?? $default;
    }

    /** @param string $key Session key. @param mixed $value New value. @return mixed Previous value. */
    public function setUserState(string $key, mixed $value): mixed
    {
        $previous = $this->session[$key] ?? null;
        $this->session[$key] = $value;

        return $previous;
    }

    /** @param string $key Key. @param string $request Input. @param mixed $default Fallback. @param string $type Filter. @return mixed */
    public function getUserStateFromRequest($key, $request, $default = null, $type = 'none'): mixed
    {
        $value = $this->input->get($request, null, $type);
        if ($value === null) {
            return $this->getUserState($key, $default);
        }
        $this->setUserState($key, $value);

        return $value;
    }
}

/** Synthetic settings only. */
final class JemHelper
{
    public static int $defaultLimit = 10;

    /** @return object Component defaults. */
    public static function config(): object { return (object) ['display_num' => self::$defaultLimit]; }
}

/** Test query only; production Venues List and Joomla pagination/state methods are inherited. */
final class ListLimitVenueModel extends JemModelVenueslist
{
    /** @return \Joomla\Database\QueryInterface Isolated ordered rows. */
    protected function getListQuery()
    {
        return $this->getDatabase()->getQuery(true)->select('id')->from('page_rows')->order('id');
    }
}
