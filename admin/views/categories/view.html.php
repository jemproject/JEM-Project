<?php
/**
 * @version     2.2.3
 * @package     JEM
 * @copyright   Copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright   Copyright (C) 2005-2009 Christoph Lukes
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

/**
 *  View class for the JEM Categories screen
 */
class JemViewCategories extends JemAdminView
{
	protected $items;
	protected $pagination;
	protected $state;

	/**
	 * Display the view
	 */
	public function display($tpl = null)
	{
		$this->state		= $this->get('State');
		$this->items		= $this->get('Items');
		$this->pagination	= $this->get('Pagination');

		// Check for errors.
		$errors = $this->get('Errors');
		if (is_array($errors) && count($errors)) {
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		// Preprocess the list of items to find ordering divisions.
		foreach ($this->items as &$item) {
			$this->ordering[$item->parent_id][] = $item->id;
		}

		// Levels filter.
		$options	= array();
		$options[]	= JHtml::_('select.option', '1', JText::_('J1'));
		$options[]	= JHtml::_('select.option', '2', JText::_('J2'));
		$options[]	= JHtml::_('select.option', '3', JText::_('J3'));
		$options[]	= JHtml::_('select.option', '4', JText::_('J4'));
		$options[]	= JHtml::_('select.option', '5', JText::_('J5'));
		$options[]	= JHtml::_('select.option', '6', JText::_('J6'));
		$options[]	= JHtml::_('select.option', '7', JText::_('J7'));
		$options[]	= JHtml::_('select.option', '8', JText::_('J8'));
		$options[]	= JHtml::_('select.option', '9', JText::_('J9'));
		$options[]	= JHtml::_('select.option', '10', JText::_('J10'));

		$this->f_levels = $options;

		$this->addToolbar();
		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 */
	protected function addToolbar()
	{

		// Initialise variables.
		$canDo		= null;
		$user		= JemFactory::getUser();

		// Get the results for each action.
		$canDo = JemHelperBackend::getActions(0);

		JToolBarHelper::title(JText::_('COM_JEM_CATEGORIES'), 'elcategories');

		if ($canDo->get('core.create')) {
			 JToolBarHelper::addNew('category.add');
		}

		if ($canDo->get('core.edit' ) || $canDo->get('core.edit.own')) {
			JToolBarHelper::editList('category.edit');
			JToolBarHelper::divider();
		}

		if ($canDo->get('core.edit.state')) {
			JToolBarHelper::publish('categories.publish', 'JTOOLBAR_PUBLISH', true);
			JToolBarHelper::unpublish('categories.unpublish', 'JTOOLBAR_UNPUBLISH', true);
			JToolBarHelper::divider();
			JToolBarHelper::archiveList('categories.archive');
		}

		if ($user->authorise('core.admin')) { // todo: is that correct?
			JToolBarHelper::checkin('categories.checkin');
		}

		if ($this->state->get('filter.published') == -2 && $canDo->get('core.delete')) {
			JToolBarHelper::deleteList('COM_JEM_CONFIRM_DELETE', 'categories.remove', 'JTOOLBAR_EMPTY_TRASH');
		}
		elseif ($canDo->get('core.edit.state')) {
			JToolBarHelper::trash('categories.trash');
		}

		if ($canDo->get('core.admin')) {
			JToolBarHelper::divider();
			JToolBarHelper::custom('categories.rebuild', 'refresh.png', 'refresh_f2.png', 'JTOOLBAR_REBUILD', false);
		}

		JToolBarHelper::divider();
		JToolBarHelper::help('listcategories', true);
	}
}