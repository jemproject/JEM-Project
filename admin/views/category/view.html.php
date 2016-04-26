<?php
/**
 * @version     2.1.6
 * @package     JEM
 * @copyright   Copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright   Copyright (C) 2005-2009 Christoph Lukes
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

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
		$this->canDo	= JEMHelperBackend::getActions($this->state->get('category.component'));


		$document	= JFactory::getDocument();

		// Check for errors.
		if (count($errors = $this->get('Errors'))) {
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);
		JHtml::_('stylesheet', 'com_jem/colorpicker.css', array(), true);

		// Load Script
		$document->addScript(JUri::root().'media/com_jem/js/colorpicker.js');

		// build grouplist
		// @todo: make a form-field for this one
		$groups = $this->get('Groups');

		$grouplist = array();
		if (!empty($this->item->groupid) && !array_key_exists($this->item->groupid, $groups)) {
			$grouplist[] = JHtml::_('select.option', $this->item->groupid, JText::sprintf('COM_JEM_CATEGORY_UNKNOWN_GROUP', $this->item->groupid));
		}
		$grouplist[] = JHtml::_('select.option', '0', JText::_('COM_JEM_CATEGORY_NO_GROUP'));
		$grouplist   = array_merge($grouplist, $groups);

		$Lists['groups'] = JHtml::_('select.genericlist', $grouplist, 'groupid', array('size'=>'1','class'=>'inputbox'), 'value', 'text', $this->item->groupid);
		$this->Lists     = $Lists;

		parent::display($tpl);

		JFactory::getApplication()->input->set('hidemainmenu', true);
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
		$canDo = JEMHelperBackend::getActions();

		$title = JText::_('COM_JEM_CATEGORY_BASE_'.($isNew?'ADD':'EDIT').'_TITLE');
		// Prepare the toolbar.
		JToolBarHelper::title($title, 'category-'.($isNew?'add':'edit').' -category-'.($isNew?'add':'edit'));

		// For new records, check the create permission.
		if ($isNew && (count($user->getAuthorisedCategories('com_jem', 'core.create')) > 0)) {
			JToolBarHelper::apply('category.apply');
			JToolBarHelper::save('category.save');
			JToolBarHelper::save2new('category.save2new');
		}

		// If not checked out, can save the item.
		elseif (!$checkedOut && ($canDo->get('core.edit') || ($canDo->get('core.edit.own') && $this->item->created_user_id == $userId))) {
			JToolBarHelper::apply('category.apply');
			JToolBarHelper::save('category.save');
			if ($canDo->get('core.create')) {
				JToolBarHelper::save2new('category.save2new');
			}
		}

		// If an existing item, can save to a copy.
		if (!$isNew && $canDo->get('core.create')) {
			JToolBarHelper::save2copy('category.save2copy');
		}

		if (empty($this->item->id))  {
			JToolBarHelper::cancel('category.cancel');
		} else {
			JToolBarHelper::cancel('category.cancel', 'JTOOLBAR_CLOSE');
		}

		JToolBarHelper::divider();
		JToolBarHelper::help('editcategories', true);
	}
}