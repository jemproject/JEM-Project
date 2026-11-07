<?php
/**
 * @package    JEM
 * @copyright  (C) 2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/** Read scalar frontend filters without accepting or persisting structured values. */
final class JemFilterInput
{
    /**
     * Read a scalar request filter, preserving Joomla's normal value filtering.
     *
     * @param   object  $input    Joomla input (GET, POST or merged request).
     * @param   string  $name     Request field name.
     * @param   mixed   $default  Trusted scalar or null fallback.
     * @param   string  $type     Joomla scalar filter type.
     *
     * @return  mixed  Filtered scalar, or the fallback for absent/structured input.
     */
    public static function read($input, string $name, $default = '', string $type = 'string')
    {
        if (!is_scalar($input->get($name, null, 'raw'))) {
            return $default;
        }

        $value = $input->get($name, $default, $type);

        return is_scalar($value) ? $value : $default;
    }

    /**
     * Resolve a scalar request/session filter and repair previously invalid state.
     *
     * Invalid submissions retain valid saved filters. Invalid saved arrays/objects
     * are replaced with the default, even when no new filter is submitted. This
     * method must not be used for filters which intentionally accept arrays.
     *
     * @param   object   $app        Application providing input and user state.
     * @param   string   $key        Existing view/menu-scoped session key.
     * @param   string   $request    Request field name.
     * @param   mixed    $default    Trusted scalar or null fallback.
     * @param   string   $type       Joomla scalar filter type.
     * @param   boolean  $resetPage  Match ListModel pagination and Search Tools input.
     *
     * @return  mixed  Effective scalar filter, or the trusted fallback.
     */
    public static function state(
        $app,
        string $key,
        string $request,
        $default = '',
        string $type = 'string',
        bool $resetPage = false
    ) {
        $current = $app->getUserState($key, $default);
        if ($current !== null && !is_scalar($current)) {
            $current = $default;
            $app->setUserState($key, $current);
            if ($resetPage) {
                $app->input->set('limitstart', 0);
            }
        }

        $input = $app->input;
        if ($resetPage && $input->get($request, null, 'raw') === null && str_starts_with($request, 'filter_')) {
            // ListModel also accepts Search Tools' filter[name] form fields.
            $filters = $input->get('filter', array(), 'raw');
            $name = substr($request, 7);
            if (is_array($filters) && isset($filters[$name])) {
                $input = new \Joomla\Input\Input(array($request => $filters[$name]));
            }
        }

        $value = self::read($input, $request, null, $type);
        if ($value === null) {
            return $current;
        }
        if ($resetPage && $current != $value) {
            $app->input->set('limitstart', 0);
        }
        $app->setUserState($key, $value);

        return $value;
    }
}
