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
 * Raw: Venues
 */
class JemViewVenues extends HtmlView
{
    /**
     * Creates the PDF output for the Venues view.
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();

        if ($app->input->getCmd('layout', '') !== 'pdf') {
            $app->close();

            return;
        }

        $model = $this->getModel();
        $model->setState('list.start', 0);
        $model->setState('list.limit', 0);

        JemPdfView::renderVenueList(Text::_('COM_JEM_VENUES'), (array) $model->getItems(), 'jem-venues.pdf');
    }
}
