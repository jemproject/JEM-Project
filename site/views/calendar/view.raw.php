<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;

/**
 * Raw: Calendar
 */
class JemViewCalendar extends HtmlView
{
    /**
     * Creates the output for the Calendar view
     */
    public function display($tpl = null)
    {
        $settings  = JemHelper::config();
        $settings2 = JemHelper::globalattribs();
        $app       = Factory::getApplication();
        $jinput    = $app->input;

        $year  = (int)$jinput->getInt('yearID', date("Y"));
        $month = (int)$jinput->getInt('monthID', date("m"));
        $layout = $jinput->getCmd('layout', '');

        if ($layout === 'pdf') {
            $model = $this->getModel();
            $model->setState('list.start', 0);
            $model->setState('list.limit', 0);
            $model->setDate(mktime(0, 0, 1, $month, 1, $year));
            $params = $app->getParams();
            $menuitem = $app->getMenu()->getActive();
            $title = (string) $params->get('page_title', $menuitem ? $menuitem->title : Text::_('COM_JEM_CALENDAR'));

            JemPdfView::renderMonthlyCalendar(
                $title,
                (array) $model->getItems(),
                'jem-calendar-' . $year . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '.pdf',
                $year,
                $month,
                $params
            );

            return;
        }

        if ($settings2->get('global_show_ical_icon','0')==1) {
            // Get data from the model
            $model = $this->getModel();
            $model->setState('list.start',0);
            $model->setState('list.limit',$settings->ical_max_items);
            $model->setDate(mktime(0, 0, 1, $month, 1, $year));

            $rows = $model->getItems();

            // initiate new CALENDAR
            $vcal     = JemHelper::getCalendarTool();
            $filename = "events_month_" . $year . str_pad($month, 2, '0', STR_PAD_LEFT) . ".ics";

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    JemHelper::icalAddEvent($vcal, $row);
                }
            }

            // generate and redirect output to user browser
            JemHelper::sendCalendar($vcal, $filename);
        }
    }
}
