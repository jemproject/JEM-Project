<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;

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

        $app          = Factory::getApplication();
        $jinput       = $app->input;

        $year = (int)$jinput->getInt('yearID', date("Y"));
        $month = (int)$jinput->getInt('monthID', date("m"));
        $layout = $jinput->getCmd('layout', '');

        if ($layout === 'pdf' && $jinput->getBool('venue_calendar_pdf', false)) {
            $model = $this->getModel('VenueCal');
            $model->setState('list.start', 0);
            $model->setState('list.limit', 0);
            $model->setDate(mktime(0, 0, 1, $month, 1, $year));
            $venueid = $jinput->getInt('id');

            JemPdfView::renderMonthlyCalendar(
                Text::_('COM_JEM_VENUE') . ' ' . $venueid . ' - ' . $year . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                (array) $model->getItems(),
                'jem-venue-' . $venueid . '-' . $year . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '.pdf',
                $year,
                $month,
                $app->getParams()
            );

            return;
        }

        if ($layout === 'pdf') {
            $model = $this->getModel();
            $model->setState('list.start', 0);
            $model->setState('list.limit', 0);
            $venue = $this->get('Venue');

            if (empty($venue)) {
                Factory::getApplication()->close();

                return;
            }

            $user = JemFactory::getUser();
            if (empty($venue->user_has_access_venue)) {
                if ($user->get('guest') || !$user->get('id')) {
                    $app->enqueueMessage(Text::_('COM_JEM_LOGIN_TO_ACCESS'), 'warning');
                    $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . base64_encode($app->input->server->getString('REQUEST_URI')), false));

                    return;
                }

                throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            if (trim((string) $venue->locdescription) !== '' && trim((string) $venue->locdescription) !== '<br>') {
                $venue->text = $venue->locdescription;
                $venue->title = $venue->venue;
                $params = $app->getParams();
                PluginHelper::importPlugin('content');
                $app->triggerEvent('onContentPrepare', array('com_jem.venue', &$venue, &$params, 0));
                $venue->locdescription = $venue->text;
            }

            if (!empty($venue->url) && !preg_match('%^http(s)?://%', $venue->url)) {
                $venue->url = 'https://' . $venue->url;
            }

            $rows = $model->getItems();
            $alias = trim((string) ($venue->slug ?: $venue->id));
            $alias = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $alias);

            JemPdfView::renderVenueDetail(
                trim((string) $venue->venue) !== '' ? (string) $venue->venue : Text::_('COM_JEM_VENUE'),
                $venue,
                (array) $rows,
                $app->getParams(),
                'jem-venue-' . $alias . '.pdf'
            );

            return;
        }

        if ($settings2->get('global_show_ical_icon','0')==1) {
            // Get data from the model
            $model = $this->getModel('VenueCal');
            $model->setState('list.start',0);
            $model->setState('list.limit',$settings->ical_max_items);
            $model->setDate(mktime(0, 0, 1, $month, 1, $year));
            $rows = $model->getItems();
            $venueid = $jinput->getInt('id');

            // initiate new CALENDAR
            $vcal     = JemHelper::getCalendarTool();
            $filename = "events_venue_" . $venueid . "_" . $year . str_pad($month, 2, '0', STR_PAD_LEFT) . ".ics";

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
