<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;

/**
 * View class: Attendees
 */
class JemViewAttendees extends JemAdminView
{
	public function display($tpl = null)
	{
		$app = Factory::getApplication();
		$db = Factory::getContainer()->get('DatabaseDriver');

		$this->jemsettings = JemHelper::config();

		if($this->getLayout() == 'print') {
			$this->_displayprint($tpl);
			return;
		}

		$filter_status    = $app->getUserStateFromRequest('com_jem.attendees.filter_status', 'filter_status', -2, 'int');
		$filter_type      = $app->getUserStateFromRequest('com_jem.attendees.filter_type',   'filter_type',    0, 'int');
		$filter_search    = $app->getUserStateFromRequest('com_jem.attendees.filter_search', 'filter_search', '', 'string');
		$filter_search    = $db->escape(trim(\Joomla\String\StringHelper::strtolower($filter_search)));

		// Load css
		$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
		$wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');

		// Get data from the model
		$event = $this->get('Event');

		$this->items      = $this->get('Items');
		$this->pagination = $this->get('Pagination');
		$this->state      = $this->get('State');

		// Check for errors.
		$errors = $this->get('Errors');
		if (is_array($errors) && count($errors)) {
			Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
			return false;
		}

		// check for data error
		if (empty($event)) {
			$app->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');
			return false;
		}

 		if (JemHelper::isValidDate($event->dates)) {
			$event->dates = JemOutput::formatdate($event->dates);
		} else {
			$event->dates = Text::_('COM_JEM_OPEN_DATE');
		}

		//build filter selectlist
		$filters = array();
		$filters[] = HTMLHelper::_('select.option', '1', Text::_('COM_JEM_NAME'));
		$filters[] = HTMLHelper::_('select.option', '2', Text::_('COM_JEM_USERNAME'));
		$lists['filter'] = HTMLHelper::_('select.genericlist', $filters, 'filter_type', array('size'=>'1','class'=>'inputbox'), 'value', 'text', $filter_type);

		// search filter
		$lists['search'] = $filter_search;

		// registration status
		$options = array(HTMLHelper::_('select.option', -2, Text::_('COM_JEM_ATT_FILTER_ALL')),
		                 HTMLHelper::_('select.option',  0, Text::_('COM_JEM_ATT_FILTER_INVITED')),
		                 HTMLHelper::_('select.option', -1, Text::_('COM_JEM_ATT_FILTER_NOT_ATTENDING')),
		                 HTMLHelper::_('select.option',  1, Text::_('COM_JEM_ATT_FILTER_ATTENDING')),
		                 HTMLHelper::_('select.option',  2, Text::_('COM_JEM_ATT_FILTER_WAITING')));
		$lists['status'] = HTMLHelper::_('select.genericlist', $options, 'filter_status', array('onChange'=>'this.form.submit();'), 'value', 'text', $filter_status);

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
		HTMLHelper::_('stylesheet', 'com_jem/backend.css', array(), true);

		$rows = $this->get('Items');
		$event = $this->get('Event');

		if (JemHelper::isValidDate($event->dates)) {
			$event->dates = JemOutput::formatdate($event->dates);
		} else {
			$event->dates = Text::_('COM_JEM_OPEN_DATE');
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
		ToolbarHelper::title(Text::_('COM_JEM_REGISTERED_USERS'), 'users');

		ToolbarHelper::addNew('attendees.add');
		ToolbarHelper::editList('attendees.edit');
		ToolbarHelper::custom('attendees.setNotAttending', 'loop', 'loop', Text::_('COM_JEM_ATTENDEES_SETNOTATTENDING'), true);
		ToolbarHelper::custom('attendees.setAttending', 'loop', 'loop', Text::_('COM_JEM_ATTENDEES_SETATTENDING'), true);
		if ($this->event->waitinglist) {
			ToolbarHelper::custom('attendees.setWaitinglist', 'loop', 'loop', Text::_('COM_JEM_ATTENDEES_SETWAITINGLIST'), true);
		}
		ToolbarHelper::spacer();
		ToolbarHelper::custom('attendees.export', 'download', 'download', Text::_('COM_JEM_EXPORT'), false);

		$eventid 	= $this->event->id;
		$link_print = 'index.php?option=com_jem&amp;view=attendees&amp;layout=print&amp;tmpl=component&amp;eventid='.$eventid;

		$bar = JToolBar::getInstance('toolbar');
		$bar->appendButton('Popup', 'print', 'COM_JEM_PRINT', $link_print, 600, 300);

		ToolbarHelper::deleteList('COM_JEM_CONFIRM_DELETE', 'attendees.remove', 'COM_JEM_ATTENDEES_DELETE');
		ToolbarHelper::spacer();
		ToolbarHelper::custom('attendees.back', 'back', 'back', Text::_('COM_JEM_ATT_BACK'), false);
		ToolbarHelper::divider();
		ToolbarHelper::help('registereduser', true);
	}
}
