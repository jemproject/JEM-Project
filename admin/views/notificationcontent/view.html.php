<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;

class JemViewNotificationcontent extends JemAdminView
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
        $this->form = new Form('notificationcontent', array('control' => 'jform'));
        $this->form->loadFile(JPATH_COMPONENT_ADMINISTRATOR . '/models/forms/notificationcontent.xml');
        $this->form->bind((array) $this->item);

        $title = $this->item->section === 'footer'
            ? 'COM_JEM_NOTIFICATION_TAB_FOOTER'
            : 'COM_JEM_NOTIFICATION_TAB_DISCLAIMER';
        ToolbarHelper::title(Text::_($title), 'envelope');
        ToolbarHelper::apply('notificationcontent.apply');
        ToolbarHelper::save('notificationcontent.save');
        if ($this->item->customized) {
            ToolbarHelper::custom(
                'notificationcontent.reset',
                'refresh',
                '',
                'COM_JEM_NOTIFICATION_TEMPLATE_RESET',
                false
            );
        }
        ToolbarHelper::cancel('notificationcontent.cancel', 'JTOOLBAR_CLOSE');

        parent::display($tpl);
    }
}
