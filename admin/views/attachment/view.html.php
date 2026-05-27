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

class JemViewAttachment extends JemAdminView
{
    public $form;
    public $fileInfo;
    public $item;
    public $linkedItem;
    public $state;

    public function display($tpl = null)
    {
        $this->form  = $this->get('Form');
        $this->fileInfo = $this->get('FileInfo');
        $this->item  = $this->get('Item');
        $this->linkedItem = $this->get('LinkedItem');
        $this->state = $this->get('State');

        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $isNew = empty($this->item->id);
        $canDo = JemHelperBackend::getActions();

        ToolbarHelper::title($isNew ? Text::_('COM_JEM_ATTACHMENT_ADD') : Text::_('COM_JEM_ATTACHMENT_EDIT'), 'attachment');

        if ($canDo->get('core.edit') || $canDo->get('core.create')) {
            ToolbarHelper::apply('attachment.apply');
            ToolbarHelper::save('attachment.save');
        }

        ToolbarHelper::cancel($isNew ? 'attachment.cancel' : 'attachment.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
        ToolbarHelper::divider();
        ToolbarHelper::inlinehelp();
    }
}
