<?php
/**
 * @version 2.3.15
 * @package JEM
 * @copyright (C) 2013-2023 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('JPATH_PLATFORM') or die;
use Joomla\CMS\Language\Text;

JFormHelper::loadFieldClass('calendar');

/**
 * Form Field class for JEM needs.
 *
 * Advances JFormFieldCalendar for better country-specific date format support.
 *
 * @since  2.2.3
 */
if (version_compare(JVERSION, '3.7', 'ge')) {

	class JFormFieldCalendarJem extends JFormFieldCalendar
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
			$exampleTimestamp = strtotime("12/31/2017 23:59");
			$date_format = str_replace("%","",$this->format);
			$hint = Text::sprintf('COM_JEM_DATEFIELD_HINT', date($date_format, $exampleTimestamp));

			$extraData = array(
				'hint' => $hint,
			);

			return array_merge($data, $extraData);
		}
	}

} else {

	class JFormFieldCalendarJem extends JFormFieldCalendar
	{
		/**
		 * The form field type.
		 *
		 * @var    string
		 * @note   MUST be public.
		 */
		public $type = 'CalendarJem';

		/**
		 * Method to get the field input markup.
		 *
		 * @return  string  The field input markup.
		 */
		protected function getInput()
		{
			// don't translate format; it MUST be Y-m-d to keep calendar popup working

			if (empty($this->hint)) {
				// add hint regarding date/time format accepted in edit field
				$exampleTimestamp = strtotime("12/31/2017 23:59");
				$this->hint = Text::sprintf('COM_JEM_DATEFIELD_HINT', date($this->format, $exampleTimestamp));
			}

			return parent::getInput();
		}
	}

}
