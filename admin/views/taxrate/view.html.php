<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

class JemViewTaxrate extends JemAdminView
{
    public $form;
    public $item;
    public $state;

    public function display($tpl = null)
    {
        require_once JPATH_SITE . '/components/com_jem/classes/featurepolicy.class.php';
        if (!JemFeaturePolicy::current()->allows(JemFeaturePolicy::FEATURE_PRICING)) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        if (!JemHelperBackend::canManage('core.options')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->form = $this->get('Form');
        $this->item = $this->get('Item');
        $this->state = $this->get('State');
        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }
        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        Factory::getApplication()->input->set('hidemainmenu', true);
        $isNew = empty($this->item->id);
        $user = JemFactory::getUser();
        $checkedOut = !empty($this->item->checked_out) && (int) $this->item->checked_out !== (int) $user->get('id');
        $canDo = JemHelperBackend::getActions();
        ToolbarHelper::title($isNew ? Text::_('COM_JEM_TAX_RATE_ADD') : Text::_('COM_JEM_TAX_RATE_EDIT'), 'percent');

        if (!$checkedOut && ($canDo->get('core.edit') || ($isNew && $canDo->get('core.create')))) {
            ToolbarHelper::apply('taxrate.apply');
            $toolbar = Toolbar::getInstance('toolbar');
            $saveGroup = $toolbar->dropdownButton('save-group')->toggleSplit(true)->icon('icon-save')->buttonClass('btn btn-success')->listCheck(false);
            $saveGroup->getChildToolbar()->save('taxrate.save');
            if ($canDo->get('core.create')) {
                $saveGroup->getChildToolbar()->save2new('taxrate.save2new');
            }
        }
        ToolbarHelper::cancel('taxrate.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
        ToolbarHelper::inlinehelp();
    }
}
