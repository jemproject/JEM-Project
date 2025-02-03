<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\Form\Field\CalendarField;

FormHelper::loadFieldClass('calendar');

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

        if (!empty($this->hint)) {
            return $data;
        }

        // add hint regarding date/time format accepted in edit field
        $exampleTimestamp = strtotime("NOW");
        $date_format = str_replace("%","",$this->format);
        $hint = Text::sprintf('COM_JEM_DATEFIELD_HINT', date($date_format, $exampleTimestamp));

        $extraData = array(
            'hint' => $hint,
        );

        return array_merge($data, $extraData);
    }
}
