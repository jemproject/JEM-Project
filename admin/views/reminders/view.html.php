<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

class JemViewReminders extends JemAdminView
{
    public function display($tpl = null)
    {
        if (!JemHelperBackend::canManage('jem.notifications.templates')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        $errors = $this->get('Errors');
        if (is_array($errors) && $errors) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');

            return false;
        }
        $this->addToolbar();
        parent::display($tpl);
    }

    private function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_JEM_REMINDERS'), 'clock');
        ToolbarHelper::addNew('reminder.add');
        ToolbarHelper::editList('reminder.edit');
        $toolbar = Toolbar::getInstance('toolbar');
        $dropdown = $toolbar->dropdownButton('status-group')
            ->text('JTOOLBAR_CHANGE_STATUS')->toggleSplit(false)->icon('icon-ellipsis-h')
            ->buttonClass('btn btn-action')->listCheck(true);
        $child = $dropdown->getChildToolbar();
        $child->publish('reminders.publish')->listCheck(true);
        $child->unpublish('reminders.unpublish')->listCheck(true);
        ToolbarHelper::deleteList('COM_JEM_REMINDER_CONFIRM_DELETE', 'reminders.delete');
    }
}
