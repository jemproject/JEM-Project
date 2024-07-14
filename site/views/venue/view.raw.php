<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;

/**
 * Raw: Venue
 */
class JemViewVenue extends HtmlView
{
	/**
	 * Creates the output for the Venue view
	 */
	public function display($tpl = null)
	{
		$settings  = JemHelper::config();
		$settings2 = JemHelper::globalattribs();
		$jinput    = Factory::getApplication()->input;

		if ($settings2->get('global_show_ical_icon','0')==1) {
			// Get data from the model
			$model = $this->getModel();
			$model->setState('list.start',0);
			$model->setState('list.limit',$settings->ical_max_items);
			$rows = $model->getItems();
			$venueid = $jinput->getInt('id');

			// initiate new CALENDAR
			$vcal = JemHelper::getCalendarTool();
			$vcal->setConfig("filename", "venue".$venueid.".ics");

			if (!empty($rows)) {
				foreach ($rows as $row) {
					JemHelper::icalAddEvent($vcal, $row);
				}
			}

			// generate and redirect output to user browser
			$vcal->returnCalendar();
		} else {
			return;
		}
	}
}
