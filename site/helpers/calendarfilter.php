<?php
/**
 * @package    JEM
 * @copyright  (C) 2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

require_once __DIR__ . '/../classes/dayschedule.class.php';

/**
 * Interactive category and venue filters shared by calendar layouts.
 */
final class JemCalendarFilterHelper
{
    public static function attributes($row, string $baseClass = 'event-filter'): string
    {
        $categories = array();

        foreach ((array) ($row->categories ?? array()) as $category) {
            $id = (int) ($category->id ?? 0);

            if ($id > 0) {
                $categories[] = 'cat' . $id;
            }
        }

        $categories = array_unique($categories);
        $venue = (int) ($row->locid ?? 0);

        return ' class="' . self::escape($baseClass) . ' ' . implode(' ', $categories)
            . ($venue > 0 ? ' venue' . $venue : '') . '"' . self::dataAttributes($row);
    }

    public static function dataAttributes($row): string
    {
        $categories = array();

        foreach ((array) ($row->categories ?? array()) as $category) {
            $id = (int) ($category->id ?? 0);

            if ($id > 0) {
                $categories[] = 'cat' . $id;
            }
        }

        $venue = (int) ($row->locid ?? 0);

        return ' data-categories="' . implode(' ', array_unique($categories)) . '"'
            . ' data-venue="' . ($venue > 0 ? 'venue' . $venue : '') . '"';
    }

    public static function render(array $rows, int $displayLegend, string $startDate = '', string $endDate = ''): string
    {
        $showCategories = in_array($displayLegend, array(1, 2, 3, 4), true);
        $showVenues = in_array($displayLegend, array(3, 4, 5, 6), true);

        if (!$showCategories && !$showVenues) {
            return '';
        }

        $categories = array();
        $venues = array();

        foreach ($rows as $row) {
            if (isset($row->user_has_access_category) && !$row->user_has_access_category) {
                continue;
            }

            $date = (string) ($row->day_schedule['day'] ?? $row->dates ?? '');

            if (($startDate !== '' && $date < $startDate) || ($endDate !== '' && $date > $endDate)) {
                $spansDay = $startDate !== '' && $startDate === $endDate
                    && $date <= $startDate && (string) ($row->enddates ?? '') >= $startDate;

                if (!$spansDay || !JemDaySchedule::project($row, $startDate, JemHelper::getEventTimeZoneName($row))) {
                    continue;
                }
            }

            foreach ((array) ($row->categories ?? array()) as $category) {
                $id = (int) ($category->id ?? 0);

                if ($id < 1 || trim((string) ($category->catname ?? '')) === '') {
                    continue;
                }

                if (!isset($categories[$id])) {
                    $categories[$id] = array(
                        'name' => (string) $category->catname,
                        'color' => (string) ($category->color ?? ''),
                        'count' => 0,
                    );
                }

                $categories[$id]['count']++;
            }

            $venueId = (int) ($row->locid ?? 0);

            if ($venueId > 0 && trim((string) ($row->venue ?? '')) !== '') {
                if (!isset($venues[$venueId])) {
                    $venues[$venueId] = array(
                        'name' => (string) $row->venue,
                        'color' => (string) ($row->l_color ?? $row->venuecolor ?? ''),
                        'count' => 0,
                    );
                }

                $venues[$venueId]['count']++;
            }
        }

        if ((!$showCategories || !$categories) && (!$showVenues || !$venues)) {
            return '';
        }

        $html = '<div id="jlcalendarlegend" class="jem-calendar-filter-legend">'
            . '<div class="calendarButtons"><div class="calendarButtonsToggle">'
            . '<button type="button" id="buttonshowall" class="calendarButton btn btn-outline-dark me-2">'
            . self::escape(Text::_('COM_JEM_SHOWALL')) . '</button>'
            . '<button type="button" id="buttonhideall" class="calendarButton btn btn-outline-dark">'
            . self::escape(Text::_('COM_JEM_HIDEALL')) . '</button></div></div>';

        foreach (array(array($showCategories, $categories, 'eventCat', 'cat', Text::_('COM_JEM_CATEGORIES')),
            array($showVenues, $venues, 'eventVenues', 'venue', Text::_('COM_JEM_VENUES'))) as $group) {
            list($enabled, $items, $class, $prefix, $label) = $group;

            if (!$enabled || !$items) {
                continue;
            }

            $html .= '<div class="calendarLegends mt-4"><div class="legend-label">' . self::escape($label) . '</div>';

            foreach ($items as $id => $item) {
                $html .= '<button type="button" class="' . $class . ' btn btn-outline-dark me-2"'
                    . ' data-filter-class="' . $prefix . (int) $id . '" aria-pressed="true">';

                if (preg_match('/^#[0-9a-fA-F]{6}$/', $item['color'])) {
                    $html .= '<span class="colorpicblock ms-2" style="background-color:'
                        . self::escape($item['color']) . '"></span>';
                }

                $html .= '<span class="colorpicblocktext pe-2">'
                    . self::escape($item['name']) . ' (' . (int) $item['count'] . ')</span></button>';
            }

            $html .= '</div>';
        }

        return $html . '</div>';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
