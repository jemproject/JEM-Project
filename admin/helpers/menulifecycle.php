<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/** Identify frontend destinations owned by this JEM installation without changing menu rows. */
final class JemMenuLifecycle
{
    /**
     * Select local JEM links, aliases and empty generated groups, regardless of publication/access.
     *
     * @param array  $rows        Frontend menu rows, including parents and alias destinations.
     * @param string $siteRoot   Absolute URL of the installed Joomla site.
     * @param int    $componentId Current com_jem extension ID, or zero if unavailable.
     * @return array Selected items, and component items whose extension ID may be repaired.
     */
    public static function select(array $rows, string $siteRoot, int $componentId = 0): array
    {
        $items = $children = $targets = $groups = $selected = $components = [];
        foreach ($rows as $row) {
            $row = (object) $row;
            $id = (int) $row->id;
            if ($id <= 1 || (int) ($row->client_id ?? 0) !== 0) {
                continue;
            }
            $items[$id] = $row;
            $children[(int) ($row->parent_id ?? 0)][] = $id;
        }

        foreach ($items as $id => $item) {
            $type = (string) ($item->type ?? '');
            $params = json_decode((string) ($item->params ?? '{}'), true);
            $params = is_array($params) ? $params : [];
            if ($type === 'alias') {
                $targets[$id] = self::menuId($params['aliasoptions'] ?? 0);
                continue;
            }

            $query = self::localQuery((string) ($item->link ?? ''), $siteRoot);
            if ($query !== null) {
                if (array_key_exists('option', $query)) {
                    if ($query['option'] === 'com_jem') {
                        $selected[$id] = $item;
                        if ($type === 'component') {
                            $components[] = $id;
                        }
                    }
                    // An explicit different component wins over a stale component_id or Itemid.
                    continue;
                }
                if ($type === 'component' && $componentId > 0 && (int) ($item->component_id ?? 0) === $componentId) {
                    $selected[$id] = $item;
                    $components[] = $id;
                } else {
                    $targets[$id] = self::menuId($query['Itemid'] ?? 0);
                }
            }

            if (self::isGeneratedGroup($item, $params, $items)) {
                $groups[$id] = true;
            }
        }

        // A fixed-point walk resolves alias chains and nested groups, but never follows cycles forever.
        do {
            $changed = false;
            foreach ($targets as $id => $target) {
                if (!isset($selected[$id]) && isset($selected[$target])) {
                    $selected[$id] = $items[$id];
                    $changed = true;
                }
            }
            foreach ($groups as $id => $unused) {
                if (isset($selected[$id])) {
                    continue;
                }
                $pending = $children[$id] ?? [];
                $seen = [$id => true];
                while ($pending) {
                    $childId = array_pop($pending);
                    if (!isset($selected[$childId]) || isset($seen[$childId])) {
                        continue 2;
                    }
                    $seen[$childId] = true;
                    array_push($pending, ...($children[$childId] ?? []));
                }
                $selected[$id] = $items[$id];
                $changed = true;
            }
        } while ($changed);

        // Delete deepest first; foreign descendants of a direct JEM link are never selected implicitly.
        uasort($selected, static function ($left, $right): int {
            return (int) ($right->level ?? 0) <=> (int) ($left->level ?? 0);
        });

        return ['items' => $selected, 'components' => $components, 'all' => $items];
    }

    /**
     * Parse only this site's entry points, allowing arbitrary query order and encoded separators.
     *
     * @param string $link     Stored menu link.
     * @param string $siteRoot Absolute site URL; unknown origins are not assumed to be local.
     * @return array|null Parsed query, or null for an external/non-entry-point link.
     */
    private static function localQuery(string $link, string $siteRoot): ?array
    {
        $link = html_entity_decode(trim($link), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $uri = parse_url($link);
        $root = parse_url($siteRoot) ?: [];
        if ($uri === false || isset($uri['user']) || isset($uri['pass'])
            || (isset($uri['scheme']) && !in_array(strtolower($uri['scheme']), ['http', 'https'], true))) {
            return null;
        }
        if (isset($uri['host'])) {
            if (empty($root['host']) || strcasecmp($uri['host'], $root['host']) !== 0) {
                return null;
            }
            $port = $uri['port'] ?? (strtolower($uri['scheme'] ?? 'http') === 'https' ? 443 : 80);
            $rootPort = $root['port'] ?? (strtolower($root['scheme'] ?? 'http') === 'https' ? 443 : 80);
            if ($port !== $rootPort && (!in_array($port, [80, 443], true) || !in_array($rootPort, [80, 443], true))) {
                return null;
            }
        }
        $basePath = rtrim((string) ($root['path'] ?? ''), '/');
        $path = rawurldecode((string) ($uri['path'] ?? ''));
        if (!str_starts_with($path, '/')) {
            $path = $basePath . '/' . $path;
        }
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($segments);
            } elseif ($segment !== '' && $segment !== '.') {
                $segments[] = $segment;
            }
        }
        $path = '/' . implode('/', $segments);
        if (!in_array($path, [$basePath ?: '/', $basePath . '/index.php', $basePath . '/administrator/index.php'], true)) {
            return null;
        }
        parse_str((string) ($uri['query'] ?? ''), $query);
        return $query;
    }

    /**
     * Read a positive scalar menu ID without coercing arrays, negatives or partial numbers.
     *
     * @param mixed $value Stored alias or Itemid value.
     * @return int Valid positive ID, otherwise zero.
     */
    private static function menuId($value): int
    {
        return (is_int($value) || is_string($value)) && ctype_digit((string) $value) ? max(0, (int) $value) : 0;
    }

    /**
     * Recognise marked generated containers, or their original dedicated-menu structure.
     * A title alone never establishes ownership; repurposed links are not containers.
     *
     * @param object $item   Menu item to inspect.
     * @param array  $params Decoded menu parameters.
     * @param array  $items  Frontend items indexed by ID.
     * @return bool Whether this is a JEM-generated container candidate.
     */
    private static function isGeneratedGroup(object $item, array $params, array $items): bool
    {
        if (!in_array($item->type ?? '', ['heading', 'separator', 'url'], true)
            || !in_array(trim((string) ($item->link ?? '')), ['', '#'], true)) {
            return false;
        }
        if (($params['jem_generated_menu'] ?? null) === 1 || ($params['jem_generated_menu'] ?? null) === '1') {
            return true;
        }
        if (($item->menutype ?? '') !== 'jem-frontend-menu') {
            return false;
        }
        $alias = (string) ($item->alias ?? '');
        $known = ['jem', 'jem-frontend', 'events', 'calendars', 'venues', 'categories', 'types', 'management', 'user-area', 'modules'];
        if (!in_array($alias, $known, true)
            && !preg_match('/^view-(events|calendars|venues|categories|types|management|user)-[a-z][a-z0-9]*$/D', $alias)
            && !preg_match('/^module-mod-jem(?:-(?:banner|cal|jubilee|map|teaser|types|wide))?$/D', $alias)) {
            return false;
        }
        $seen = [];
        while ($item && !isset($seen[(int) $item->id])) {
            $seen[(int) $item->id] = true;
            if (($item->menutype ?? '') !== 'jem-frontend-menu') {
                return false;
            }
            if (in_array($item->alias ?? '', ['jem', 'jem-frontend'], true)
                && in_array($item->type ?? '', ['heading', 'url'], true)
                && in_array(trim((string) ($item->link ?? '')), ['', '#'], true)) {
                return true;
            }
            $item = $items[(int) ($item->parent_id ?? 0)] ?? null;
        }
        return false;
    }
}
