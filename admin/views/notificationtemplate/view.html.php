<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;

class JemViewNotificationtemplate extends JemAdminView
{
    public $form;
    public $item;
    public $languages = array();

    public function display($tpl = null)
    {
        if (!JemHelperBackend::canManage('jem.notifications.templates')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $model = $this->getModel();
        $this->item = $model->getItem();
        $this->languages = $model->getLanguages();
        $this->form = new Form('notificationtemplate', array('control' => 'jform'));
        $this->form->loadFile(JPATH_COMPONENT_ADMINISTRATOR . '/models/forms/notificationtemplate.xml');
        $this->form->bind((array) $this->item);

        Factory::getApplication()->input->set('hidemainmenu', true);
        ToolbarHelper::title(Text::_('COM_JEM_NOTIFICATION_TEMPLATE_EDIT'), 'envelope');
        ToolbarHelper::apply('notificationtemplate.apply');
        ToolbarHelper::save('notificationtemplate.save');
        if ($this->item->customized) {
            ToolbarHelper::custom(
                'notificationtemplate.reset',
                'refresh',
                '',
                'COM_JEM_NOTIFICATION_TEMPLATE_RESET',
                false
            );
        }
        ToolbarHelper::cancel('notificationtemplate.cancel', 'JTOOLBAR_CLOSE');

        parent::display($tpl);
    }
}
