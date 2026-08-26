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
        $this->truncateNonce = JemHelper::issueActionNonce('housekeeping.truncateAllData');

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
