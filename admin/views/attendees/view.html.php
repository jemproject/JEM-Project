<?php
/**
 * @version 2.1.7
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


/**
 * View class: Attendees
 */
class JemViewAttendees extends JemAdminView
{
	public function display($tpl = null)
	{
		$app = JFactory::getApplication();
		$db  = JFactory::getDBO();

		$this->jemsettings = JemHelper::config();

		if($this->getLayout() == 'print') {
			$this->_displayprint($tpl);
			return;
		}

		$filter_status    = $app->getUserStateFromRequest('com_jem.attendees.filter_status', 'filter_status', -2, 'int');
		$filter_type      = $app->getUserStateFromRequest('com_jem.attendees.filter_type',   'filter_type',   '', 'int');
		$filter_search    = $app->getUserStateFromRequest('com_jem.attendees.filter_search', 'filter_search', '', 'string');
		$filter_search    = $db->escape(trim(JString::strtolower($filter_search)));

		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		// Get data from the model
		$event = $this->get('Event');

		$this->items      = $this->get('Items');
		$this->pagination = $this->get('Pagination');
		$this->state      = $this->get('State');

		// Check for errors.
		if (count($errors = $this->get('Errors'))) {
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}

		// check for data error
		if (empty($event)) {
			$app->enqueueMessage(JText::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');
			return false;
		}

 		if (JEMHelper::isValidDate($event->dates)) {
			$event->dates = JEMOutput::formatdate($event->dates);
		} else {
			$event->dates = JText::_('COM_JEM_OPEN_DATE');
		}

		//build filter selectlist
		$filters = array();
		$filters[] = JHtml::_('select.option', '1', JText::_('COM_JEM_NAME'));
		$filters[] = JHtml::_('select.option', '2', JText::_('COM_JEM_USERNAME'));
		$lists['filter'] = JHtml::_('select.genericlist', $filters, 'filter_type', array('size'=>'1','class'=>'inputbox'), 'value', 'text', $filter_type);

		// search filter
		$lists['search'] = $filter_search;

		// registration status
		$options = array(JHtml::_('select.option', -2, JText::_('COM_JEM_ATT_FILTER_ALL')),
		                 JHtml::_('select.option',  0, JText::_('COM_JEM_ATT_FILTER_INVITED')),
		                 JHtml::_('select.option', -1, JText::_('COM_JEM_ATT_FILTER_NOT_ATTENDING')),
		                 JHtml::_('select.option',  1, JText::_('COM_JEM_ATT_FILTER_ATTENDING')),
		                 JHtml::_('select.option',  2, JText::_('COM_JEM_ATT_FILTER_WAITING')));
		$lists['status'] = JHtml::_('select.genericlist', $options, 'filter_status', array('onChange'=>'this.form.submit();'), 'value', 'text', $filter_status);

		//assign to template
		$this->lists 		= $lists;
		$this->event 		= $event;

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Prepares the print screen
	 */
	protected function _displayprint($tpl = null)
	{
		// Load css
		JHtml::_('stylesheet', 'com_jem/backend.css', array(), true);

		$rows = $this->get('Items');
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
		JToolBarHelper::custom('attendees.setNotAttending', 'loop', 'loop', JText::_('COM_JEM_ATTENDEES_SETNOTATTENDING'), true);
		JToolBarHelper::custom('attendees.setAttending', 'loop', 'loop', JText::_('COM_JEM_ATTENDEES_SETATTENDING'), true);
		if ($this->event->waitinglist) {
			JToolBarHelper::custom('attendees.setWaitinglist', 'loop', 'loop', JText::_('COM_JEM_ATTENDEES_SETWAITINGLIST'), true);
		}
		JToolBarHelper::spacer();
		JToolBarHelper::custom('attendees.export', 'download', 'download', JText::_('COM_JEM_EXPORT'), false);

		$eventid 	= $this->event->id;
		$link_print = 'index.php?option=com_jem&amp;view=attendees&amp;layout=print&amp;tmpl=component&amp;eventid='.$eventid;

		$bar = JToolBar::getInstance('toolbar');
		$bar->appendButton('Popup', 'print', 'COM_JEM_PRINT', $link_print, 600, 300);

		JToolBarHelper::deleteList('COM_JEM_CONFIRM_DELETE', 'attendees.remove', 'COM_JEM_ATTENDEES_DELETE');
		JToolBarHelper::spacer();
		JToolBarHelper::custom('attendees.back', 'back', 'back', JText::_('COM_JEM_ATT_BACK'), false);
		JToolBarHelper::divider();
		JToolBarHelper::help('registereduser', true);
	}
}
