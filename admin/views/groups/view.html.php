<?php
/**
 * @version 2.1.6
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
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

class JemViewGroups extends JemAdminView
{
	protected $items;
	protected $pagination;
	protected $state;

	public function display($tpl = null)
	{
		$user        = JemFactory::getUser();
		$jemsettings = JEMAdmin::config();

		// Initialise variables.
		$this->items      = $this->get('Items');
		$this->pagination = $this->get('Pagination');
		$this->state      = $this->get('State');

		// loading Mootools
		JHtml::_('behavior.framework');

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		// add style to description of the tooltip (hastip)
		JHtml::_('behavior.tooltip');

		// assign data to template
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
		JToolBarHelper::title(JText::_('COM_JEM_GROUPS'), 'groups');

		/* retrieving the allowed actions for the user */
		$canDo = JEMHelperBackend::getActions(0);

		/* create */
		if (($canDo->get('core.create'))) {
			JToolBarHelper::addNew('group.add');
		}

		/* edit */
		if (($canDo->get('core.edit'))) {
			JToolBarHelper::editList('group.edit');
			JToolBarHelper::divider();
		}

		if ($canDo->get('core.edit.state')) {
			JToolBarHelper::checkin('groups.checkin');
		}

		JToolBarHelper::deleteList('COM_JEM_CONFIRM_DELETE', 'groups.remove', 'JACTION_DELETE');

		JToolBarHelper::divider();
		JToolBarHelper::help('listgroups', true);
	}
}
?>