<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView;

/**
 * Raw: Annual Calendar
 */
class JemViewAnnualcalendar extends HtmlView
{
    /**
     * Creates the iCalendar output for the Annual Calendar view.
     */
    public function display($tpl = null)
    {
        $settings  = JemHelper::config();
        $settings2 = JemHelper::globalattribs();
        $app       = Factory::getApplication();
        $jinput    = $app->input;
        $params    = $app->getParams();

        $year       = (int) $jinput->getInt('yearID', date('Y'));
        $startMonth = max(1, min(12, (int) $params->get('annual_start_month', 1)));
        $periodStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $startMonth));
        $periodEnd   = $periodStart->modify('+12 months -1 day');

        if ($settings2->get('global_show_ical_icon', '0') == 1) {
            $model = $this->getModel();
            $model->setState('list.start', 0);
            $model->setState('list.limit', $settings->ical_max_items);

            $rows = $model->getItems();

            $vcal = JemHelper::getCalendarTool();
            $filename = 'jem_events_year_' . $periodStart->format('Ymd') . '_' . $periodEnd->format('Ymd') . '.ics';

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    JemHelper::icalAddEvent($vcal, $row);
                }
            }

            $vcal->returnCalendar(false, false, true, $filename);
        }
    }
}
?>
