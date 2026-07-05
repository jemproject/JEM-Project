<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
 
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\Field\CalendarField;

/**
 * Form Field class for JEM needs.
 *
 * Advances CalendarField for better country-specific date format support.
 *
 * @since  2.2.3
 */

class JFormFieldCalendarJem extends CalendarField
{
    /**
     * The form field type.
     *
     * @var    string
     */
    protected $type = 'CalendarJem';

    /**
     * Method to get the data to be passed to the layout for rendering.
     *
     * @return  array
     */
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();
        $data['firstday'] = $this->getJemFirstWeekday((int) ($data['firstday'] ?? 0));

        if (!empty($this->hint)) {
            return $data;
        }

        // add hint regarding date/time format accepted in edit field
        $exampleTimestamp = strtotime("NOW");
        $date_format = str_replace("%","",$this->format);
        $hint = Text::sprintf('COM_JEM_DATEFIELD_HINT', date($date_format, $exampleTimestamp));

        return array_merge($data, ['hint' => $hint]);
    }

    /**
     * Return JEM's configured first day of week for Joomla's calendar field.
     *
     * @param   integer  $fallback  Joomla language fallback value.
     *
     * @return  integer  0 for Sunday, 1 for Monday.
     */
    private function getJemFirstWeekday($fallback = 0)
    {
        try {
            if (!class_exists('JemHelper')) {
                require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
            }

            $settings = JemHelper::config();

            return ((int) ($settings->weekdaystart ?? $fallback) === 1) ? 1 : 0;
        } catch (Throwable $e) {
            return ((int) $fallback === 1) ? 1 : 0;
        }
    }
}
