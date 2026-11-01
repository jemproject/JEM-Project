<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Input\Input;
use Joomla\Registry\Registry;

/** SQL executes in memory; no installed Joomla database is opened. */
final class SearchScopeDatabase extends \Joomla\Database\Sqlite\SqliteDriver
{
    /** @return bool Whether the isolated SQL driver is available. */
    public static function isSupported()
    {
        return extension_loaded('pdo_sqlite');
    }

    /** @param mixed $text Value. @param bool $extra Extra escaping. @return string SQL literal content. */
    public function escape($text, $extra = false)
    {
        $this->connect();

        return substr($this->connection->quote((string) $text), 1, -1);
    }
}

final class SearchScopeMenuItem
{
    public int $id = 123;

    /** @param array $query Menu destination. @param Registry $params Stored menu options. */
    public function __construct(public array $query, private Registry $params)
    {
    }

    /** @return Registry Stored settings, independent of request values. */
    public function getParams(): Registry
    {
        return $this->params;
    }
}

final class SearchScopeApplication
{
    /** @return object Current fixture language, shared with Joomla date services. */
    public function getLanguage(): object
    {
        return Factory::$language;
    }

    public Input $input;
    public ?SearchScopeMenuItem $active = null;
    public Registry $params;
    public array $state = [];

    /** @return void Initialize an isolated application/request. */
    public function __construct()
    {
        $this->input = new Input();
        $this->params = new Registry();
    }

    /** @return self Minimal menu service. */
    public function getMenu(): self
    {
        return $this;
    }

    /** @return SearchScopeMenuItem|null Active menu. */
    public function getActive(): ?SearchScopeMenuItem
    {
        return $this->active;
    }

    /** @param string|null $component Component name. @return Registry Effective component/menu settings. */
    public function getParams(?string $component = null): Registry
    {
        return $this->params;
    }

    /** @return string Joomla application name for pagination. */
    public function getName(): string
    {
        return 'site';
    }

    /** @param string $key Session key. @param mixed $value Stored value. @return void */
    public function setUserState(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
    }

    /** @param string $key Session key. @param string $request Input. @param mixed $default Fallback. @param string $type Filter. @return mixed Input value. */
    public function getUserStateFromRequest($key, $request, $default, $type): mixed
    {
        $value = $this->input->get($request, null, $type);
        if ($value !== null) {
            $this->state[$key] = $value;
        }

        return $this->state[$key] ?? $default;
    }
}

final class JemFactory
{
    /** @return self Synthetic public-view identity. */
    public static function getUser(): self
    {
        return new self();
    }

    /** @return int[] Public access levels. */
    public function getAuthorisedViewLevels(): array
    {
        return [1];
    }
}

final class JemCategories
{
    /** @param int $id Selected category. @return int[] Fixture category subtree. */
    public static function getChilds(int $id): array
    {
        return [$id];
    }
}

class SearchScopeHelperDependencies
{
    /** @param mixed ...$args Unrelated hierarchy context. @return string Neutral hierarchy fixture. */
    public static function getEventParentVisibilityWhere(...$args): string
    {
        return '1 = 1';
    }

    /** @param mixed ...$args Unrelated hierarchy context. @return string Neutral hierarchy fixture. */
    public static function getVenueHierarchyVisibilityWhere(...$args): string
    {
        return '1 = 1';
    }
}

final class JemAgeAccess
{
    /** @param mixed ...$args Unrelated age context. @return string Neutral age fixture. */
    public static function sqlVisibilityCondition(...$args): string
    {
        return '1 = 1';
    }
}

final class SearchScopeModel extends JemModelSearch
{
    /** @return int Synthetic total for pagination-link context checks only. */
    public function getTotal()
    {
        return 40;
    }
}
