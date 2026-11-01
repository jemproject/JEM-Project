<?php

declare(strict_types=1);

/** Only menu lookup is stubbed; helper, timezone resolution and iCalcreator are real. */
final class JemHelperRoute
{
    public static string $query = '';

    /** @param string $slug Event identifier. @return string Internal route with a synthetic menu context. */
    public static function getEventRoute($slug): string
    {
        return 'index.php?option=com_jem&view=event&id=' . rawurlencode((string) $slug) . self::$query;
    }
}

/** Native site routing with synthetic menus; no installed application or database is used. */
final class IcalRoutingEnvironment
{
    /**
     * Configure the real Joomla/JEM routers for an isolated export request.
     *
     * @param string $base Installation path.
     * @param bool $sef SEF routing switch.
     * @param bool $linked Include an event menu.
     * @param string $scheme Request scheme.
     * @param string $view Current export view.
     * @param bool $suffix Enable native format suffix building.
     * @return void
     */
    public static function configure(
        string $base = '',
        bool $sef = false,
        bool $linked = false,
        string $scheme = 'https',
        string $view = 'event',
        bool $suffix = false
    ): void {
        require_once JEM_TEST_ROOT . '/site/router.php';
        (new ReflectionProperty(\Joomla\CMS\Plugin\PluginHelper::class, 'plugins'))->setValue(null, []);
        $menu = new IcalRoutingMenu($linked ? [77 => (object) [
            'id' => 77, 'component_id' => 42, 'component' => 'com_jem', 'language' => '*',
            'query' => ['option' => 'com_jem', 'view' => 'event', 'id' => '130:dst-test'],
            'route' => 'event-menu', 'home' => 0,
        ]] : []);
        $router = (new ReflectionClass(\Joomla\CMS\Router\SiteRouter::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty($router, 'menu'))->setValue($router, $menu);
        $router->setComponentRouter('com_jem', new JemRouter(\Joomla\CMS\Factory::getApplication(), $menu));
        $router->attachBuildRule([$router, 'buildInit'], \Joomla\CMS\Router\Router::PROCESS_BEFORE);
        $router->attachBuildRule([$router, 'buildComponentPreprocess'], \Joomla\CMS\Router\Router::PROCESS_BEFORE);
        if ($sef) {
            $router->attachBuildRule([$router, 'buildSefRoute'], \Joomla\CMS\Router\Router::PROCESS_DURING);
            if ($suffix) {
                $router->attachBuildRule([$router, 'buildFormat'], \Joomla\CMS\Router\Router::PROCESS_AFTER);
            }
        }
        $router->attachBuildRule([$router, 'buildBase'], \Joomla\CMS\Router\Router::PROCESS_AFTER);
        $router->setVars(['option' => 'com_jem', 'view' => $view, 'format' => 'raw', 'layout' => 'ics']);
        foreach (['base', 'root'] as $property) {
            (new ReflectionProperty(\Joomla\CMS\Uri\Uri::class, $property))->setValue(
                null,
                ['prefix' => $scheme . '://ics.test', 'path' => $base]
            );
        }
        (new ReflectionProperty(\Joomla\CMS\Uri\Uri::class, 'instances'))->setValue(null, [
            'SERVER' => new \Joomla\CMS\Uri\Uri($scheme . '://ics.test' . $base . '/index.php'),
        ]);
        (new ReflectionProperty(\Joomla\CMS\Router\Route::class, '_router'))->setValue(null, ['site' => $router]);
        JemHelperRoute::$query = $linked ? '&Itemid=77' : '';
    }
}

/** Synthetic menu records consumed by the native router rules. */
final class IcalRoutingMenu
{
    /** @param array<int, object> $items Menu fixtures. */
    public function __construct(private array $items)
    {
    }

    /** @param int $id Menu ID. @return object|null Matching menu. */
    public function getItem($id): ?object
    {
        return $this->items[(int) $id] ?? null;
    }

    /** @return null No active menu is required for an explicit event route. */
    public function getActive()
    {
        return null;
    }

    /** @param string $language Requested language. @return null No homepage fixture. */
    public function getDefault(string $language = '*')
    {
        return null;
    }

    /** @param mixed $attributes Lookup names. @param array $values Lookup values. @return array Matching menus. */
    public function getItems($attributes, $values): array
    {
        return array_values(array_filter($this->items, static function (object $item) use ($values): bool {
            return $item->component_id === $values[0] && in_array($item->language, $values[1], true);
        }));
    }
}

