<?php
/**
 * @package    JEM
 * @copyright  (C) 2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/** Match detail routes to their own menu or a compatible list context. */
final class JemDetailMenu
{
    /**
     * Read a numeric ID or a legacy id:alias without accepting arbitrary numeric prefixes.
     *
     * @param mixed $value Stored menu ID or route slug.
     * @return int|null Non-negative ID, or null for an invalid value.
     */
    public static function id($value): ?int
    {
        if ((!is_int($value) && !is_string($value))
            || !preg_match('/^([0-9]+)(?::[^:\s\/?#&]+)?$/uD', (string) $value, $matches)) {
            return null;
        }

        $digits = ltrim($matches[1], '0');
        $id = (int) $matches[1];

        return (string) $id === ($digits === '' ? '0' : $digits) ? $id : null;
    }

    /**
     * Check whether a menu represents exactly the requested detail and layout.
     *
     * @param array $menu Stored menu query.
     * @param array $query Requested component query.
     * @return bool True only for the same valid record, view and layout.
     */
    public static function matches(array $menu, array $query): bool
    {
        $id = self::id($query['id'] ?? null);

        return $id !== null && $id > 0
            && ($menu['option'] ?? '') === 'com_jem'
            && in_array($query['view'] ?? '', ['event', 'venue', 'category'], true)
            && ($menu['view'] ?? '') === $query['view']
            && self::id($menu['id'] ?? null) === $id
            && (($menu['layout'] ?? '') ?: 'default') === (($query['layout'] ?? '') ?: 'default');
    }

    /**
     * Recognise list origins without borrowing another detail record's menu settings.
     *
     * @param array $menu Stored menu query.
     * @param array $query Requested component query, including known category/venue scope.
     * @return bool Whether this menu can provide the route's list context.
     */
    public static function isListContext(array $menu, array $query): bool
    {
        if (($menu['option'] ?? '') !== 'com_jem') {
            return false;
        }

        $lists = [
            'event' => ['eventslist', 'eventsblog', 'eventsmap', 'calendar', 'weekcal', 'annualcalendar', 'day', 'search',
                'typeevents', 'myevents', 'myattendances', 'mytimeline'],
            'venue' => ['venues', 'venueslist', 'venuesmap', 'typevenues', 'myvenues'],
            'category' => ['categories', 'categoriesdetailed'],
        ];
        $view = $menu['view'] ?? '';
        if (in_array($view, $lists[$query['view'] ?? ''] ?? [], true)) {
            return true;
        }

        // A category/venue is an event list only when the link carries that same scope.
        if (($query['view'] ?? '') === 'event' && in_array($view, ['category', 'venue'], true)) {
            $id = self::id($query[$view === 'category' ? 'catid' : 'locid'] ?? null);

            return $id !== null && $id > 0 && self::id($menu['id'] ?? null) === $id;
        }

        return false;
    }

    /**
     * Select a detail menu from Joomla's published, access- and language-filtered items.
     *
     * @param array $items Eligible component menus returned by SiteMenu::getItems.
     * @param array $query Requested detail route and optional explicit Itemid.
     * @param int $activeId Current menu ID, or zero when there is none.
     * @return int|null Exact menu, compatible list menu, or null for a menu-less route.
     */
    public static function select(array $items, array $query, int $activeId): ?int
    {
        $id = self::id($query['id'] ?? null);
        if ($id === null || $id < 1) {
            return null;
        }

        $byId = [];
        foreach ($items as $item) {
            $byId[(int) $item->id] = $item;
        }

        $explicit = $query['Itemid'] ?? null;
        if ((is_int($explicit) || is_string($explicit)) && ctype_digit((string) $explicit)) {
            $item = $byId[(int) $explicit] ?? null;
            if ($item && (self::matches($item->query, $query) || self::isListContext($item->query, $query))) {
                return (int) $item->id;
            }
        }

        $active = $byId[$activeId] ?? null;
        if ($active && self::matches($active->query, $query)) {
            return $activeId;
        }

        $exact = null;
        foreach ($items as $item) {
            if (self::matches($item->query, $query)
                && ($exact === null || ($exact->language === '*' && $item->language !== '*'))) {
                $exact = $item;
            }
        }
        if ($exact !== null) {
            return (int) $exact->id;
        }

        if ($active && self::isListContext($active->query, $query)) {
            return $activeId;
        }
        // Without a known origin, use a general list rather than an arbitrary scoped calendar/type.
        foreach ($items as $item) {
            if (in_array($item->query['view'] ?? '', [
                'eventslist', 'eventsblog', 'eventsmap', 'venues', 'venueslist', 'venuesmap',
                'categories', 'categoriesdetailed',
            ], true) && self::isListContext($item->query, $query)) {
                return (int) $item->id;
            }
        }

        return null;
    }
}
