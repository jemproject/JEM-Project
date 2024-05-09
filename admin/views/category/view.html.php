<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Category View
 */
class JemViewCategory extends JemAdminView
{
	protected $form;
	protected $item;
	protected $state;

	/**
	 * Display the view
	 */
	public function display($tpl = null)
	{
		$this->form		= $this->get('Form');
		$this->item		= $this->get('Item');
		$this->state	= $this->get('State');
		$this->canDo	= JemHelperBackend::getActions($this->state->get('category.component'));

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
		// HTMLHelper::_('stylesheet', 'com_jem/backend.css', array(), true);
		// HTMLHelper::_('stylesheet', 'com_jem/colorpicker.css', array(), true);
			
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
		$user		= JemFactory::getUser();
		$userId		= $user->get('id');

		$isNew		= ($this->item->id == 0);
		$checkedOut	= !($this->item->checked_out == 0 || $this->item->checked_out == $userId);

		// Get the results for each action.
		$canDo = JemHelperBackend::getActions();

		$title = Text::_('COM_JEM_CATEGORY_BASE_'.($isNew?'ADD':'EDIT').'_TITLE');
		// Prepare the toolbar.
		ToolbarHelper::title($title, 'category-'.($isNew?'add':'edit').' -category-'.($isNew?'add':'edit'));

		// For new records, check the create permission.
		if ($isNew && (count($user->getAuthorisedCategories('com_jem', 'core.create')) > 0)) {
			ToolbarHelper::apply('category.apply');
			ToolbarHelper::save('category.save');
			ToolbarHelper::save2new('category.save2new');
		}

		// If not checked out, can save the item.
		elseif (!$checkedOut && ($canDo->get('core.edit') || ($canDo->get('core.edit.own') && $this->item->created_user_id == $userId))) {
			ToolbarHelper::apply('category.apply');
			ToolbarHelper::save('category.save');
			if ($canDo->get('core.create')) {
				ToolbarHelper::save2new('category.save2new');
			}
		}

		// If an existing item, can save to a copy.
		if (!$isNew && $canDo->get('core.create')) {
			ToolbarHelper::save2copy('category.save2copy');
		}

		if (empty($this->item->id))  {
			ToolbarHelper::cancel('category.cancel');
		} else {
			ToolbarHelper::cancel('category.cancel', 'JTOOLBAR_CLOSE');
		}

		ToolbarHelper::divider();
		ToolbarHelper::inlinehelp();
		ToolBarHelper::help('editcategories', true, 'https://www.joomlaeventmanager.net/documentation/manual/backend/categories/add-category');
	}
}
