<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;

class JemViewReminder extends JemAdminView
{
    public function display($tpl = null)
    {
        if (!JemHelperBackend::canManage('jem.notifications.templates')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $this->form = $this->get('Form');
        $this->item = $this->get('Item');
        $this->state = $this->get('State');
        $errors = $this->get('Errors');
        if (is_array($errors) && $errors) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');

            return false;
        }
        Factory::getApplication()->input->set('hidemainmenu', true);
        ToolbarHelper::title(empty($this->item->id) ? Text::_('COM_JEM_REMINDER_ADD') : Text::_('COM_JEM_REMINDER_EDIT'), 'clock');
        ToolbarHelper::apply('reminder.apply');
        ToolbarHelper::save('reminder.save');
        ToolbarHelper::save2new('reminder.save2new');
        ToolbarHelper::cancel('reminder.cancel', empty($this->item->id) ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
        parent::display($tpl);
    }
}
