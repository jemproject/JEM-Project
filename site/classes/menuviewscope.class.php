<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Matches a routed JEM request to the menu item that owns its page text.
 */
class JemMenuViewScope
{
    /**
     * @param   array|object  $query      Active menu query.
     * @param   string        $view       Current JEM view name.
     * @param   mixed|null    $requestId  Current routed record id.
     *
     * @return  boolean
     */
    static public function matches($query, $view, $requestId = null)
    {
        $query = is_object($query) ? (array) $query : $query;
        $view  = strtolower(trim((string) $view));

        if (!is_array($query) || $view === '') {
            return false;
        }

        if (($query['option'] ?? '') !== 'com_jem'
            || strtolower((string) ($query['view'] ?? '')) !== $view) {
            return false;
        }

        if (!array_key_exists('id', $query)) {
            return true;
        }

        $menuId = self::normaliseId($query['id']);

        if ($menuId === null) {
            return true;
        }

        $requestId = self::normaliseId($requestId);

        // Joomla may store id=0 on list-view menu items while omitting the id
        // from their canonical URL. Both forms identify the same menu view.
        if ($menuId === '0' && $requestId === null) {
            return true;
        }

        return $menuId === $requestId;
    }

    /**
     * Normalises a routed id such as "42:event-alias" for comparison.
     *
     * @param   mixed  $value  Menu or request id.
     *
     * @return  string|null
     */
    static private function normaliseId($value)
    {
        if (is_array($value) || is_object($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d+)(?::.*)?$/', $value, $matches)) {
            return (string) (int) $matches[1];
        }

        return $value;
    }
}
