<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Pagination\Pagination;

/**
 * Housekeeping-View
 */
class JemViewHousekeeping extends JemAdminView
{

    public function display($tpl = null) {

        $app = Factory::getApplication();

        if (!JemHelperBackend::canManage('jem.tools.manage')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->totalcats = $this->get('Countcats');
        $legacyReport = $app->getUserState('com_jem.housekeeping.image_profile_report', null);
        $auditActive = $app->input->getInt('imageaudit', 0) === 1 || is_array($legacyReport);
        $this->imageProfileReport = null;
        $this->imagePagination = null;
        $this->imageBatchLimit = 25;

        if ($auditActive) {
            $model = $this->getModel();
            $ordering = $app->getUserStateFromRequest(
                'com_jem.housekeeping.image_ordering',
                'filter_order',
                'file',
                'cmd'
            );
            $direction = $app->getUserStateFromRequest(
                'com_jem.housekeeping.image_direction',
                'filter_order_Dir',
                'asc',
                'cmd'
            );
            $limitstart = $app->input->getInt('limitstart', 0);
            $this->imageBatchLimit = $model::IMAGE_NORMALISE_BATCH_LIMIT;
            $this->imageProfileReport = $model->auditImageProfiles(
                $ordering,
                $direction,
                $limitstart,
                $model::IMAGE_CANDIDATE_PAGE_LIMIT
            );
            $this->imagePagination = new Pagination(
                (int) $this->imageProfileReport['candidate_total'],
                (int) $this->imageProfileReport['limitstart'],
                (int) $this->imageProfileReport['limit']
            );
            $app->setUserState('com_jem.housekeeping.image_profile_report', null);
        }

        // add toolbar
        $this->addToolbar();

        parent::display($tpl);
    }


    /**
     * Add Toolbar
     */
    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_JEM_HOUSEKEEPING'), 'housekeeping');

        ToolbarHelper::back('JTOOLBAR_BACK', 'index.php?option=com_jem&view=main');
        ToolbarHelper::divider();
        ToolBarHelper::help('housekeeping', true, 'https://www.joomlaeventmanager.net/documentation/backend/control-panel/housekeeping');
    }
}
?>
