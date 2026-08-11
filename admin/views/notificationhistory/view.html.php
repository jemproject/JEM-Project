<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;

class JemViewNotificationhistory extends JemAdminView
{
    public function display($tpl = null)
    {
        if (!JemHelperBackend::canManage('jem.notifications.history')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        $model = $this->getModel();
        $this->states = $model->getFilterValues('state');
        $this->types = $model->getFilterValues('notification_type');
        $this->languages = $model->getFilterValues('resolved_language');
        $this->canResend = JemHelperBackend::canManage('jem.notifications.resend');

        $errors = $this->get('Errors');
        if (is_array($errors) && $errors) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }
        ToolbarHelper::title(Text::_('COM_JEM_NOTIFICATION_HISTORY'), 'envelope');
        parent::display($tpl);
    }
}
