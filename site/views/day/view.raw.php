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
 * Raw: Day
 */
class JemViewDay extends HtmlView
{
    /**
     * Creates the PDF output for the Day view.
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();

        if ($app->input->getCmd('layout', '') !== 'pdf') {
            $app->close();

            return;
        }

        $model = $this->getModel();
        $requestDate = $app->input->getCmd('id', '');

        if ($model && method_exists($model, 'setDate')) {
            $model->setDate($requestDate);
        }

        $model->setState('list.start', 0);
        $model->setState('list.limit', 0);

        $menuitem = $app->getMenu()->getActive();
        $params = $app->getParams('com_jem');

        if ($menuitem
            && isset($menuitem->query['option'], $menuitem->query['view'])
            && $menuitem->query['option'] === 'com_jem'
            && $menuitem->query['view'] === 'day'
            && method_exists($menuitem, 'getParams')) {
            foreach ($menuitem->getParams()->toArray() as $key => $value) {
                $params->set($key, $value);
            }
        }

        $menuLayout = $menuitem && isset($menuitem->query['layout']) ? (string) $menuitem->query['layout'] : '';
        $day = $this->get('Day');
        $title = $menuLayout === 'timeline' ? Text::_('COM_JEM_DAY_VIEW_TIMELINE_TITLE') : Text::_('COM_JEM_DAY_VIEW');

        if ($menuLayout === 'timeline') {
            JemPdfView::renderDayTimeline($title, (array) $model->getItems(), 'jem-day-timeline.pdf', (string) $day, $params);

            return;
        }

        JemPdfView::renderEventList($title, (array) $model->getItems(), 'jem-day.pdf', 'calendar', (string) $day);
    }
}
