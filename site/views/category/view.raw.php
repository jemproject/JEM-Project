<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView;

/**
 * Raw: Category
 */
class JemViewCategory extends HtmlView
{
	/**
	 * Creates the output for the Category view
	 */
	public function display($tpl = null)
	{
		$settings  = JemHelper::config();
		$settings2 = JemHelper::globalattribs();
		$app       = Factory::getApplication();
		$jinput    = $app->input;

        $year  = (int)$jinput->getInt('yearID', date("Y"));
        $month = (int)$jinput->getInt('monthID', date("m"));
		$catid = (int)$jinput->getInt('id', 0);

		if ($settings2->get('global_show_ical_icon','0')==1) {
			// Get data from the model
			$model = $this->getModel('CategoryCal');
			$model->setState('list.start',0);
			$model->setState('list.limit',$settings->ical_max_items);
            $model->setDate(mktime(0, 0, 1, $month, 1, $year));

            $rows = $model->getItems();

			// initiate new CALENDAR
			$vcal = JemHelper::getCalendarTool();
			$vcal->setConfig("filename", "events_category_" . $catid . "_". $year . $month . ".ics");

			if (!empty($rows)) {
				foreach ($rows as $row) {
					JemHelper::icalAddEvent($vcal, $row);
				}
			}

			// generate and redirect output to user browser
			$vcal->returnCalendar();
		}
	}
}
