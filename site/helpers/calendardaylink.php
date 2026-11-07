<?php
/**
 * @package    JEM
 * @copyright  (C) 2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/** Build a Day drill-down without copying arbitrary request parameters or menu configuration. */
final class JemCalendarDayLink
{
    /**
     * Preserve the source menu while entering the first page of one calendar day.
     *
     * @param string $url Internal Day URL containing the date and category/venue scope.
     * @param int $itemId Source menu ID, or zero for a menu-less view.
     * @param string $task Source task; only archive may be propagated.
     * @return string Unrouted Day URL. Explicit Day layouts remain unchanged.
     */
    public static function build(string $url, int $itemId, string $task): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        if (($query['option'] ?? '') !== 'com_jem' || ($query['view'] ?? '') !== 'day') {
            throw new InvalidArgumentException('Expected an internal JEM Day URL.');
        }
        if ($itemId > 0) {
            $query['Itemid'] = $itemId;
        }
        if ($task === 'archive') {
            $query['task'] = 'archive';
        }
        // Calendar layouts are not Day layouts. An explicitly selected Day layout wins.
        $query += array('layout' => 'default', 'timeline_days_to_show' => 1,
            'limitstart' => 0, 'filter_search' => '', 'filter_type' => 0, 'jemcal' => 1);

        return 'index.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Read only the additional Day scope shared by navigation, forms and exports.
     *
     * @param object $input Joomla filtered input.
     * @return array Validated scope; no arbitrary configuration, tasks or tokens.
     */
    public static function context($input): array
    {
        $query = array();
        foreach (array('topcat', 'jemcal', 'calhide') as $key) {
            $value = $input->getInt($key, 0);
            if ($value > 0) {
                $query[$key] = $key === 'topcat' ? $value : 1;
            }
        }
        foreach (array('catids', 'locids', 'pub', 'calcats', 'calvenues') as $key) {
            // Joomla's string filter may return arrays for array-valued input.
            $value = $input->get($key, '', 'string');
            $ids = array_filter(explode(',', is_string($value) ? $value : ''), static function ($id) {
                return ctype_digit($id) && (int) $id > 0;
            });
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if ($ids) {
                $query[$key] = implode(',', $ids);
            }
        }

        return $query;
    }

    /**
     * Recognise a calendar menu without relying on visitor-supplied view names.
     *
     * @param array $query Active Joomla menu query.
     * @return string Calendar source view, or an empty string for an unrelated menu.
     */
    public static function sourceView(array $query): string
    {
        if (($query['option'] ?? '') !== 'com_jem') {
            return '';
        }
        $view = $query['view'] ?? '';
        if (in_array($view, array('calendar', 'weekcal', 'annualcalendar'), true)
            || (in_array($view, array('category', 'venue'), true) && ($query['layout'] ?? '') === 'calendar')) {
            return $view;
        }

        return '';
    }
}

