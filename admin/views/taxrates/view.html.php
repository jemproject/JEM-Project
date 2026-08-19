<?php
/** @package JEM */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

class JemViewTaxrates extends JemAdminView
{
    public $items;
    public $pagination;
    public $state;
    public $countries;

    public function display($tpl = null)
    {
        require_once JPATH_SITE . '/components/com_jem/classes/featurepolicy.class.php';
        if (!JemFeaturePolicy::current()->allows(JemFeaturePolicy::FEATURE_PRICING)) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        if (!JemHelperBackend::canManage('core.options')) {
            throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        $this->countries = $this->get('Countries');
        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }
        Factory::getApplication()->getDocument()->getWebAssetManager()->useScript('table.columns');
        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_JEM_TAX_RATES'), 'percent');
        $toolbar = Toolbar::getInstance('toolbar');
        $canDo = JemHelperBackend::getActions(0);
        if ($canDo->get('core.create')) {
            ToolbarHelper::addNew('taxrate.add');
        }
        if ($canDo->get('core.edit')) {
            ToolbarHelper::editList('taxrate.edit');
        }
        if ($canDo->get('core.edit.state') || $canDo->get('core.admin')) {
            $actions = $toolbar->dropdownButton('status-group')->text('JTOOLBAR_CHANGE_STATUS')->toggleSplit(false)->icon('icon-ellipsis-h')->buttonClass('btn btn-action')->listCheck(true)->getChildToolbar();
            $actions->publish('taxrates.publish')->listCheck(true);
            $actions->unpublish('taxrates.unpublish')->listCheck(true);
            $actions->checkin('taxrates.checkin')->listCheck(true);
        }
        if ($canDo->get('core.delete')) {
            ToolbarHelper::deleteList('COM_JEM_CONFIRM_DELETE', 'taxrates.delete');
        }
        ToolbarHelper::inlinehelp();
    }
}
