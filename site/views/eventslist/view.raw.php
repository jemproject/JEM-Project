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
 * Raw: Eventlist
 */
class JemViewEventslist extends HtmlView
{
    /**
     * Creates the output for the Eventslist view
     */
    public function display($tpl = null)
    {
        $settings  = JemHelper::config();
        $settings2 = JemHelper::globalattribs();
        $app = Factory::getApplication();
        $layout = $app->input->getCmd('layout', '');

        if ($layout === 'pdf') {
            $model = $this->getModel();
            $model->setState('list.start', 0);
            $model->setState('list.limit', 0);
            $rows = $model->getItems();
            $params = $app->getParams();
            $menu = $app->getMenu();
            $menuActive = $menu ? $menu->getActive() : null;
            $title = trim((string) $params->get('page_heading', ''));

            if ($title === '') {
                $title = trim((string) $params->get('page_title', ''));
            }

            if ($title === '' && $menuActive) {
                $title = trim((string) $menuActive->title);
            }

            if ($title === '') {
                $title = Text::_('COM_JEM_EVENTS');
            }

            if ($app->input->getCmd('task', '') === 'archive') {
                $title .= ' - ' . Text::_('COM_JEM_ARCHIVE');
            }

            JemPdfView::renderLinkedEventList($title, (array) $rows, 'jem-events.pdf');

            return;
        }

        if ($settings2->get('global_show_ical_icon','0')==1) {
            // Get data from the model
            $model = $this->getModel();
            $model->setState('list.start',0);
            $model->setState('list.limit',$settings->ical_max_items);
            $rows = $model->getItems();

            // initiate new CALENDAR
            $vcal     = JemHelper::getCalendarTool();
            $filename = "events.ics";

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    JemHelper::icalAddEvent($vcal, $row);
                }
            }

            // generate and redirect output to user browser
            $vcal->returnCalendar(false, false, true, $filename);
        }
    }
}
