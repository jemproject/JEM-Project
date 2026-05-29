<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;

class JemViewTypes extends JemAdminView
{
    public $items;
    public $pagination;
    public $state;

    public function display($tpl = null)
    {
        $this->items      = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state      = $this->get('State');

        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');
        $wa->useScript('table.columns');

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_JEM_TYPES'), 'tag');
        $toolbar = $this->getToolbarInstance();

        $canDo = JemHelperBackend::getActions(0);
        $canChangeState = $canDo->get('core.edit.state') || $canDo->get('core.admin');
        $canDelete = $canDo->get('core.delete');

        if ($canDo->get('core.create')) {
            ToolbarHelper::addNew('type.add');
        }
        if ($canDo->get('core.edit')) {
            ToolbarHelper::editList('type.edit');
            ToolbarHelper::divider();
        }
        if (($canChangeState || $canDelete) && $this->supportsToolbarDropdown($toolbar)) {
            $dropdown = $toolbar->dropdownButton('status-group')
                ->text('JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);
            $childBar = $dropdown->getChildToolbar();

            if ($canChangeState) {
                $childBar->publish('types.publish')->listCheck(true);
                $childBar->unpublish('types.unpublish')->listCheck(true);
                $childBar->checkin('types.checkin')->listCheck(true);
            }

            if ($canDelete) {
                $childBar->delete('types.remove', 'JACTION_DELETE')
                    ->message('COM_JEM_CONFIRM_DELETE')
                    ->listCheck(true);
            }
        } elseif ($canChangeState || $canDelete) {
            if ($canChangeState) {
                ToolbarHelper::publishList('types.publish');
                ToolbarHelper::unpublishList('types.unpublish');
                ToolbarHelper::checkin('types.checkin');
            }

            if ($canDelete) {
                ToolbarHelper::deleteList('COM_JEM_CONFIRM_DELETE', 'types.remove', 'JACTION_DELETE');
            }
        }

        ToolbarHelper::divider();
        ToolbarHelper::help('listtypes', true, 'https://www.joomlaeventmanager.net/documentation/backend/types');
    }
}
