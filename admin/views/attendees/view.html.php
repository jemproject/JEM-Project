<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


/**
 * View class for the JEM attendees screen
 *
 * @package JEM
 *
 */
class JEMViewAttendees extends JViewLegacy {
	public function display($tpl = null)
	{
		$app = JFactory::getApplication();

		if($this->getLayout() == 'print') {
			$this->_displayprint($tpl);
			return;
		}

		//initialise variables
		$db			= JFactory::getDBO();

		//get vars
		$filter_order		= $app->getUserStateFromRequest('com_jem.attendees.filter_order', 'filter_order', 'u.username', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.attendees.filter_order_Dir',	'filter_order_Dir',	'', 'word');
		$filter_waiting		= $app->getUserStateFromRequest('com_jem.attendees.waiting',	'filter_waiting',	0, 'int');
		$filter 			= $app->getUserStateFromRequest('com_jem.attendees.filter', 'filter', '', 'int');
		$search 			= $app->getUserStateFromRequest('com_jem.attendees.filter_search', 'filter_search', '', 'string');
		$search 			= $db->escape(trim(JString::strtolower($search)));

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		// Get data from the model
		$rows 		= $this->get('Data');
		$pagination = $this->get('Pagination');
		$event 		= $this->get('Event');

 		if (JEMHelper::isValidDate($event->dates)) {
			$event->dates = JEMOutput::formatdate($event->dates);
		} else {
			$event->dates = JText::_('COM_JEM_OPEN_DATE');
		}

		//build filter selectlist
		$filters = array();
		$filters[] = JHtml::_('select.option', '1', JText::_('COM_JEM_NAME'));
		$filters[] = JHtml::_('select.option', '2', JText::_('COM_JEM_USERNAME'));
		$lists['filter'] = JHtml::_('select.genericlist', $filters, 'filter', 'size="1" class="inputbox"', 'value', 'text', $filter);

		// search filter
		$lists['search'] = $search;

		// waiting list status
		$options = array(JHtml::_('select.option', 0, JText::_('COM_JEM_ATT_FILTER_ALL')),
		                  JHtml::_('select.option', 1, JText::_('COM_JEM_ATT_FILTER_ATTENDING')),
		                  JHtml::_('select.option', 2, JText::_('COM_JEM_ATT_FILTER_WAITING'))) ;
		$lists['waiting'] = JHtml::_('select.genericlist', $options, 'filter_waiting', 'onChange="this.form.submit();"', 'value', 'text', $filter_waiting);

		// table ordering
		$lists['order_Dir'] = $filter_order_Dir;
		$lists['order']		= $filter_order;

		//assign to template
		$this->lists 		= $lists;
		$this->rows 		= $rows;
		$this->pagination 	= $pagination;
		$this->event 		= $event;

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Prepares the print screen
	 *
	 * @param $tpl
	 *
	 *
	 */
	public function _displayprint($tpl = null)
	{
		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		$rows = $this->get('Data');
		$event = $this->get('Event');

		if (JEMHelper::isValidDate($event->dates)) {
			$event->dates = JEMOutput::formatdate($event->dates);
		} else {
			$event->dates = JText::_('COM_JEM_OPEN_DATE');
		}

		//assign data to template
		$this->rows = $rows;
		$this->event = $event;

		parent::display($tpl);
	}


	/**
	 * Add Toolbar
	 */
	protected function addToolbar()
	{
		JToolBarHelper::title(JText::_('COM_JEM_REGISTERED_USERS'), 'users');

		JToolBarHelper::addNew('attendees.add');
		JToolBarHelper::editList('attendees.edit');
		JToolBarHelper::spacer();
		JToolBarHelper::deleteList('', 'attendees.remove', 'COM_JEM_ATTENDEES_DELETE');
		JToolBarHelper::spacer();
		JToolBarHelper::custom('attendees.back', 'back', 'back', JText::_('COM_JEM_ATT_BACK'), false);
		JToolBarHelper::divider();
		JToolBarHelper::help('registereduser', true);
	}
}
?>