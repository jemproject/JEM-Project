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
        $this->user         = $user;
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
        $canDo          = JemHelperBackend::getActions(0);
        $toolbar        = Toolbar::getInstance('toolbar');
        $canChangeState = $canDo->get('core.edit.state') || $canDo->get('core.admin');
        $canDelete      = $canDo->get('core.delete');
        $filterState    = $this->state->get('filter_state');

        /* create */
        if ($canDo->get('core.create')) {
            ToolbarHelper::addNew('group.add');
        }

        /* edit */
        if ($canDo->get('core.edit')) {
            ToolbarHelper::editList('group.edit');
            ToolbarHelper::divider();
        }

        if ($canChangeState) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);
            $childBar = $dropdown->getChildToolbar();

            if ($filterState != 2) {
                $childBar->publish('groups.publish')->listCheck(true);
                $childBar->unpublish('groups.unpublish')->listCheck(true);
            }

            if ($filterState != 2) {
                $childBar->archive('groups.archive')->listCheck(true);
            } else {
                $childBar->publish('groups.publish', 'JTOOLBAR_UNARCHIVE')->listCheck(true);
            }

            if ($filterState != -2) {
                $childBar->trash('groups.trash')->listCheck(true);
            }
        }

        if ($filterState == -2 && $canDelete) {
            ToolbarHelper::divider();
            $toolbar->delete('groups.remove', 'JTOOLBAR_EMPTY_TRASH')
                ->message('COM_JEM_CONFIRM_DELETE')
                ->listCheck(true);
        }

        ToolbarHelper::divider();
        ToolBarHelper::help('listgroups', true, 'https://www.joomlaeventmanager.net/documentation/backend/groups');
    }
}