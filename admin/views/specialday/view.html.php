<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

class JemViewSpecialday extends JemAdminView
{
    public $form;
    public $item;
    public $state;
    public $dayTypes;

    public function display($tpl = null)
    {
        $this->form  = $this->get('Form');
        $this->item  = $this->get('Item');
        $this->state = $this->get('State');
        $this->dayTypes = JemHelper::calendarSpecialDayTypes();

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

        $user        = JemFactory::getUser();
        $isNew       = ((int) $this->item->id === 0);
        $checkedOutId = (int) ($this->item->checked_out ?? 0);
        $checkedOut  = !($checkedOutId === 0 || $checkedOutId === (int) $user->get('id'));
        $canDo       = JemHelperBackend::getActions();
        $canSave     = !$checkedOut && ($canDo->get('core.edit') || $canDo->get('core.create'));
        $canSave2New = !$checkedOut && $canDo->get('core.create');
        $canSave2Copy = !$isNew && !$checkedOut && $canDo->get('core.create');
        $cancelText  = $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE';

        ToolbarHelper::title($isNew ? Text::_('COM_JEM_SPECIAL_DAY_ADD') : Text::_('COM_JEM_SPECIAL_DAY_EDIT'), 'calendar');

        if ($canSave) {
            ToolbarHelper::apply('specialday.apply');

            $toolbar = Toolbar::getInstance('toolbar');
            $saveGroup = $toolbar->dropdownButton('save-group')
                ->toggleSplit(true)
                ->icon('icon-save')
                ->buttonClass('btn btn-success')
                ->listCheck(false);

            $childBar = $saveGroup->getChildToolbar();
            $childBar->save('specialday.save');

            if ($canSave2New) {
                $childBar->save2new('specialday.save2new');
            }

            if ($canSave2Copy) {
                $childBar->save2copy('specialday.save2copy');
            }
        }

        ToolbarHelper::cancel('specialday.cancel', $cancelText);
        ToolbarHelper::divider();
        ToolbarHelper::inlinehelp();
    }
}
