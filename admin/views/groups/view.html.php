<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;

/**
 * View class for the JEM Groups screen
 *
 * @package Joomla
 * @subpackage JEM
 *
 */

class JemViewGroups extends JemAdminView
{
    public $items;
    public $pagination;
    public $state;

    public function display($tpl = null)
    {
        $user        = JemFactory::getUser();
        $jemsettings = JEMAdmin::config();

        // Initialise variables.
        $this->items      = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state      = $this->get('State');

        // Load css
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');

        // assign data to template
        $this->user            = $user;
        $this->jemsettings  = $jemsettings;

        // add toolbar
        $this->addToolbar();

        parent::display($tpl);
        }


    /**
     * Add Toolbar
     */
    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_JEM_GROUPS'), 'groups');

        /* retrieving the allowed actions for the user */
        $canDo = JEMHelperBackend::getActions(0);
        $toolbar = Toolbar::getInstance('toolbar');
        $canCheckin = $canDo->get('core.edit.state');
        $canDelete = $canDo->get('core.delete');

        /* create */
        if (($canDo->get('core.create'))) {
            ToolbarHelper::addNew('group.add');
        }

        /* edit */
        if (($canDo->get('core.edit'))) {
            ToolbarHelper::editList('group.edit');
            ToolbarHelper::divider();
        }

        if ($canCheckin) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);
            $childBar = $dropdown->getChildToolbar();

            $childBar->checkin('groups.checkin')->listCheck(true);
        }
        
        if ($canDelete) {
            $toolbar->delete('groups.remove', 'JACTION_DELETE')
                ->message('COM_JEM_CONFIRM_DELETE')
                ->listCheck(true);
        }

        ToolbarHelper::divider();
        ToolBarHelper::help('listgroups', true, 'https://www.joomlaeventmanager.net/documentation/backend/groups');
    }
}
