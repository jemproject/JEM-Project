<?php

declare(strict_types=1);

/** Native SiteMenu filtering with published fixture records instead of a database. */
final class DetailRouterMenu extends Joomla\CMS\Menu\SiteMenu
{
    /** @param array $items Published menus. @param array $levels User's viewing access levels. */
    public function __construct(array $items, array $levels = [1])
    {
        $this->items = $items;
        $this->app = new DetailRouterApplication();
        $this->storedUser = new class ($levels) {
            public function __construct(private array $levels)
            {
            }

            public function getAuthorisedViewLevels(): array
            {
                return $this->levels;
            }
        };
    }

    /** @return bool Fixtures are already loaded; no database access. */
    public function load()
    {
        return true;
    }
}

/** Site application boundary; routing and access filtering remain native Joomla code. */
final class DetailRouterApplication
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $key === 'language' ? 'en-GB' : $default;
    }

    public function isClient(string $client): bool
    {
        return $client === 'site';
    }

    public function getLanguageFilter(): bool
    {
        return Joomla\CMS\Language\Multilanguage::isEnabled();
    }

    public function getLanguage(): object
    {
        return new class {
            public function getTag(): string
            {
                return 'en-GB';
            }
        };
    }
}
