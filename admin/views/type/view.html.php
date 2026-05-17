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

class JemViewType extends JemAdminView
{
    protected $form;
    protected $item;
    protected $state;

    public function display($tpl = null)
    {
        $this->form  = $this->get('Form');
        $this->item  = $this->get('Item');
        $this->state = $this->get('State');

        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');
        $wa->registerAndUseStyle('com_jem.fontawesome', 'com_jem/vendor/fontawesome-free/css/all.min.css');

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $user       = JemFactory::getUser();
        $isNew      = ($this->item->id == 0);
        $checkedOut = !($this->item->checked_out == 0 || $this->item->checked_out == $user->get('id'));
        $canDo      = JemHelperBackend::getActions();

        ToolbarHelper::title($isNew ? Text::_('COM_JEM_TYPE_ADD') : Text::_('COM_JEM_TYPE_EDIT'), 'tag');

        if (!$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'))) {
            ToolbarHelper::apply('type.apply');
            ToolbarHelper::save('type.save');
        }
        if (!$checkedOut && $canDo->get('core.create')) {
            ToolbarHelper::save2new('type.save2new');
        }
        if (!$isNew && $canDo->get('core.create')) {
            ToolbarHelper::save2copy('type.save2copy');
        }

        ToolbarHelper::cancel($isNew ? 'type.cancel' : 'type.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
        ToolbarHelper::divider();
        ToolbarHelper::inlinehelp();
    }
}
