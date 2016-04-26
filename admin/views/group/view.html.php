<?php
/**
 * @version 2.1.6
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


/**
 * View class Group
 *
 * @package Joomla
 * @subpackage JEM
 *
 */
class JemViewGroup extends JemAdminView
{
	protected $form;
	protected $item;
	protected $state;

	public function display($tpl = null)
	{
		// Initialise variables.
		$this->form	 = $this->get('Form');
		$this->item	 = $this->get('Item');
		$this->state = $this->get('State');

		// Check for errors.
		if (count($errors = $this->get('Errors'))) {
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}

		JHtml::_('behavior.modal', 'a.modal');
		JHtml::_('behavior.tooltip');
		JHtml::_('behavior.formvalidation');

		//initialise variables
		$jemsettings = JEMHelper::config();
		$document	= JFactory::getDocument();
		$this->settings	= JEMAdmin::config();
		$task		= JFactory::getApplication()->input->get('task', '');
		$this->task = $task;
		$url 		= JUri::root();

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		$maintainers 		= $this->get('Members');
		$available_users 	= $this->get('Available');

		//make data safe
		JFilterOutput::objectHTMLSafe($this->item);

		//create selectlists
		$lists = array();
		$lists['maintainers']		= JHtml::_('select.genericlist', $maintainers, 'maintainers[]', array('class'=>'inputbox','size'=>'20','onDblClick'=>'moveOptions(document.adminForm[\'maintainers[]\'], document.adminForm[\'available_users\'])', 'multiple'=>'multiple', 'style'=>'padding: 6px; width: 98%;'), 'value', 'text');
		$lists['available_users']	= JHtml::_('select.genericlist', $available_users, 'available_users', array('class'=>'inputbox','size'=>'20','onDblClick'=>'moveOptions(document.adminForm[\'available_users\'], document.adminForm[\'maintainers[]\'])', 'multiple'=>'multiple','style'=>'padding: 6px; width: 98%;'), 'value', 'text');

		$this->jemsettings		= $jemsettings;
		$this->lists 		= $lists;

		$this->addToolbar();
		parent::display($tpl);
	}


	/**
	 * Add the page title and toolbar.
	 *
	 */
	protected function addToolbar()
	{
		JFactory::getApplication()->input->set('hidemainmenu', true);

		$user		= JemFactory::getUser();
		$isNew		= ($this->item->id == 0);
		$checkedOut	= !($this->item->checked_out == 0 || $this->item->checked_out == $user->get('id'));
		$canDo		= JEMHelperBackend::getActions();

		JToolBarHelper::title($isNew ? JText::_('COM_JEM_GROUP_ADD') : JText::_('COM_JEM_GROUP_EDIT'), 'groupedit');

		// If not checked out, can save the item.
		if (!$checkedOut && ($canDo->get('core.edit')||$canDo->get('core.create'))) {
			JToolBarHelper::apply('group.apply');
			JToolBarHelper::save('group.save');
		}
		if (!$checkedOut && $canDo->get('core.create')) {
			JToolBarHelper::save2new('group.save2new');
		}
		// If an existing item, can save to a copy.
		if (!$isNew && $canDo->get('core.create')) {
			JToolBarHelper::save2copy('group.save2copy');
		}

		if (empty($this->item->id))  {
			JToolBarHelper::cancel('group.cancel');
		} else {
			JToolBarHelper::cancel('group.cancel', 'JTOOLBAR_CLOSE');
		}

		JToolBarHelper::divider();
		JToolBarHelper::help('editgroup', true);
	}
}
?>