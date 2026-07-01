<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;

/**
 * Raw: Venuesmap
 */
class JemViewVenuesMap extends HtmlView
{
    /**
     * Creates the PDF output for the Venues Map view.
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

        $params = $app->getParams();
        $mapProvider = (string) $params->get('map_provider', 'osm') === 'google' ? 'google' : 'osm';
        $showVenueImage = (int) $params->get('showvenueimage', 1) === 1;

        JemPdfView::renderVenueList(Text::_('COM_JEM_VENUES_MAP'), (array) $model->getItems(), 'jem-venues-map.pdf', 'map', $mapProvider, $showVenueImage);
    }
}
