<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 */

defined('_JEXEC') or die;


/**
 * View class for the JEM Groups screen
 *
 * @package Joomla
 * @subpackage JEM
 *
 */

 class JEMViewGroups extends JViewLegacy {

	protected $items;
	protected $pagination;
	protected $state;

	public function display($tpl = null)
	{
		$user 		= JFactory::getUser();
		$document	= JFactory::getDocument();

		$jemsettings = JEMAdmin::config();

		// Initialise variables.
		$this->items		= $this->get('Items');
		$this->pagination	= $this->get('Pagination');
		$this->state		= $this->get('State');

		// loading Mootools
		JHtml::_('behavior.framework');

		//add css and submenu to document
		$document->addStyleSheet(JURI::root().'media/com_jem/css/backend.css');

		//add style to description of the tooltip (hastip)
		JHTML::_('behavior.tooltip');

		//assign data to template
		//$this->lists		= $lists;
		$this->user			= $user;
		$this->jemsettings  = $jemsettings;

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
		}


	/**
	 * Add Toolbar
	 */
	protected function addToolbar()
	{
		/* submenu */
		require_once JPATH_COMPONENT . '/helpers/helper.php';

		/* Adding title + icon
		 *
		 * the icon is mapped within backend.css
		 * The word 'venues' is referring to the venues icon
		 * */
		JToolBarHelper::title(JText::_('COM_JEM_GROUPS'), 'groups');

		/* retrieving the allowed actions for the user */
		$canDo = JEMHelperBackend::getActions(0);

		/* create */
		if (($canDo->get('core.create'))) {
			JToolBarHelper::addNew('group.add');
		}

		/* edit */
		JToolBarHelper::spacer();
		if (($canDo->get('core.edit'))) {
			JToolBarHelper::editList('group.edit');
		}

		/* state */
		/*
		if ($canDo->get('core.edit.state'))
		{

			if ($this->state->get('filter_state') != 2)
			{
				JToolBarHelper::publishList('groups.publish', 'JTOOLBAR_PUBLISH', true);
				JToolBarHelper::unpublishList('groups.unpublish', 'JTOOLBAR_UNPUBLISH', true);
			}

			if ($this->state->get('filter_state') != -1)
			{
				JToolBarHelper::divider();
				if ($this->state->get('filter_state') != 2)
				{
					JToolBarHelper::archiveList('groups.archive');
				}
				elseif ($this->state->get('filter_state') == 2)
				{
					JToolBarHelper::unarchiveList('groups.publish');
				}
			}
		}
		*/

		if ($canDo->get('core.edit.state')) {
			JToolBarHelper::checkin('groups.checkin');
		}

		/*
		if ($this->state->get('filter_state') == -2 && $canDo->get('core.delete')) {
			JToolBarHelper::deleteList('', 'events.delete', 'JTOOLBAR_EMPTY_TRASH');
			JToolBarHelper::divider();
		} elseif ($canDo->get('core.edit.state')) {
			JToolBarHelper::trash('events.trash');
			JToolBarHelper::divider();
		}
		*/

		JToolBarHelper::deleteList('COM_JEM_CONFIRM_DELETE', 'groups.remove', 'JACTION_DELETE');
		JToolBarHelper::spacer();
		JToolBarHelper::help('listgroups', true);
	}
}
?>