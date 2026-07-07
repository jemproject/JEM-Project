<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Category View
 */
class JemViewCategory extends JemAdminView
{
    public $form;
    public $item;
    public $state;

    /**
     * Display the view
     */
    public function display($tpl = null)
    {
        $this->form        = $this->get('Form');
        $this->item        = $this->get('Item');
        $this->state    = $this->get('State');
        $this->canDo    = JemHelperBackend::getActions($this->state->get('category.component'));

        $app = Factory::getApplication();
        $this->document = $app->getDocument();
        $uri = Uri::getInstance();

        // Check for errors.
        $errors = $this->get('Errors');
        if (is_array($errors) && count($errors)) {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }
        $wa = $app->getDocument()->getWebAssetManager();
        // Load css
        $wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');
        $wa->registerStyle('jem.colorpicker', 'com_jem/colorpicker.css');

        // Load Script
        $this->document->addScript($uri->root().'media/com_jem/js/colorpicker.js');

        // build grouplist
        // @todo: make a form-field for this one
        $groups = $this->get('Groups');

        $grouplist = array();
        if (!empty($this->item->groupid) && !array_key_exists($this->item->groupid, $groups)) {
            $grouplist[] = HTMLHelper::_('select.option', $this->item->groupid, Text::sprintf('COM_JEM_CATEGORY_UNKNOWN_GROUP', $this->item->groupid));
        }
        $grouplist[] = HTMLHelper::_('select.option', '0', Text::_('COM_JEM_CATEGORY_NO_GROUP'));
        $grouplist   = array_merge($grouplist, $groups);

        $Lists['groups'] = HTMLHelper::_('select.genericlist', $grouplist, 'groupid', array('size'=>'1','class'=>'inputbox form-select m-0'), 'value', 'text', $this->item->groupid);
        $this->Lists     = $Lists;

        parent::display($tpl);

        $app->input->set('hidemainmenu', true);
        $this->addToolbar();
    }

    /**
     * Add the page title and toolbar.
     */
    protected function addToolbar()
    {
        // Initialise variables.
        $user        = JemFactory::getUser();
        $userId      = $user->get('id');
        $isNew       = ($this->item->id == 0);
        $checkedOut  = !($this->item->checked_out == 0 || $this->item->checked_out == $userId);

        // Get the results for each action.
        $canDo = JemHelperBackend::getActions();
        $canCreateCategory = $canDo->get('core.create') || count($user->getAuthorisedCategories('com_jem', 'core.create')) > 0;
        $canEditCategory   = !$checkedOut && ($canDo->get('core.edit') || ($canDo->get('core.edit.own') && $this->item->created_user_id == $userId));
        $canSave           = ($isNew && $canCreateCategory) || (!$isNew && $canEditCategory);
        $canSave2New       = ($isNew && $canCreateCategory) || (!$isNew && $canEditCategory && $canDo->get('core.create'));
        $canSave2Copy      = !$isNew && $canDo->get('core.create');
        $cancelText        = $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE';

        $title = Text::_('COM_JEM_CATEGORY_BASE_'.($isNew?'ADD':'EDIT').'_TITLE');
        // Prepare the toolbar.
        ToolbarHelper::title($title, 'category-'.($isNew?'add':'edit').' -category-'.($isNew?'add':'edit'));

        if ($canSave) {
            ToolbarHelper::apply('category.apply');

            $toolbar = Toolbar::getInstance('toolbar');
            $saveGroup = $toolbar->dropdownButton('save-group')
                ->toggleSplit(true)
                ->icon('icon-save')
                ->buttonClass('btn btn-success')
                ->listCheck(false);

            $childBar = $saveGroup->getChildToolbar();
            $childBar->save('category.save');

            if ($canSave2New) {
                $childBar->save2new('category.save2new');
            }

            if ($canSave2Copy) {
                $childBar->save2copy('category.save2copy');
            }
        }

        ToolbarHelper::cancel('category.cancel', $cancelText);

        ToolbarHelper::divider();
        ToolbarHelper::inlinehelp();
        ToolBarHelper::help('editcategories', true, 'https://www.joomlaeventmanager.net/documentation/backend/categories/add-category');
    }
}