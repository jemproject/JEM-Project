<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView;

/**
 * Raw: weekcal
 */
class JemViewWeekcal extends HtmlView
{
	/**
	 * Creates the output for the Weekcal view
	 */
	public function display($tpl = null)
	{
		$settings  = JemHelper::config();
		$settings2 = JemHelper::globalattribs();
		$app       = Factory::getApplication();
		$jinput    = $app->input;

        $year = (int)$jinput->getInt('yearID', date("Y"));
        $week = (int)$jinput->getInt('weekID', $this->get('Currentweek'));

        if ($settings2->get('global_show_ical_icon','0')==1) {
            // Get data from the model
            $model = $this->getModel();
            $model->setState('list.start',0);
            $model->setState('list.limit',$settings->ical_max_items);
            $rows = $this->get('Items');

			// initiate new CALENDAR
			$vcal = JemHelper::getCalendarTool();

            $vcal->setConfig("filename", "events_week_" . $year . $week . ".ics");

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
