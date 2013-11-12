<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * JEM Component Editevent Controller
 *
 * @package JEM
 *
 */
class JEMControllerEditevent extends JControllerLegacy
{
	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();
	}


	/**
	 * Logic for canceling an event edit task
	 */
	function cancelevent()
	{
		$user		= JFactory::getUser();
		$id			= JRequest::getInt('id');
		$session 	= JFactory::getSession();

		$session->clear('eventform', 'com_jem');

		// Must be logged in
		if ($user->get('id') < 1) {
			throw new Exception(JText::_('JERROR_ALERTNOAUTHOR'),403);
			return;
		}

		if ($id) {
			// Create and load a events table
			$row = JTable::getInstance('jem_events', '');

			$row->load($id);
			$row->checkin();

			$this->setRedirect(JRoute::_(JEMHelperRoute::getEventRoute($id), false));
		} else {
			$link = JRequest::getString('referer', JURI::base(), 'post');
			$this->setRedirect($link);
		}
	}

	/**
	 * Logic for canceling an event and proceed to add a venue
	 */
	function addvenue()
	{
		$user	= JFactory::getUser();
		$id		= JRequest::getInt('id');

		$post = JRequest::get('post');
		//sticky forms
		$session = JFactory::getSession();
		$session->set('venueform', $post, 'com_jem');

		// Must be logged in
		if ($user->get('id') < 1) {
			throw new Exception(JText::_('JERROR_ALERTNOAUTHOR'),403);
			return;
		}

		if ($id) {
			// Create and load a events table
			$row = JTable::getInstance('jem_events', '');

			$row->load($id);
			$row->checkin();
		}

		$this->setRedirect(JRoute::_('index.php?view=editvenue', false));
	}

	/**
	 * Cleanes and saves the submitted event to the database
	 * TODO: Check if the user is allowed to post events assigned to this category/venue
	 */
	function saveevent()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit('Invalid Token');

		//get image
		$file 		= JRequest::getVar('userfile', '', 'files', 'array');
		$post 		= JRequest::get('post');

		//sticky forms
		$session = JFactory::getSession();
		$session->set('eventform', $post, 'com_jem');

		$isNew = ($post['id']) ? false : true;

		$model = $this->getModel('editevent');

		// Mock up a JTable class for finder
		$row = new stdClass;
		$row->id = $post['id'];
		// TODO: Get correct access level for onFinderBeforeSave and onFinderAfterSave
		$row->access = 1;

		JPluginHelper::importPlugin('finder');
		$dispatcher = JDispatcher::getInstance();
		$res = $dispatcher->trigger('onFinderBeforeSave', array('com_jem.event', $row, $isNew));

		if ($returnid = $model->store($post, $file)) {
			$row->id = $returnid;
			$msg 	= JText::_('COM_JEM_EVENT_SAVED');
			$link 	= JRoute::_(JEMHelperRoute::getEventRoute($returnid), false) ;

			JPluginHelper::importPlugin('jem');
			$res = $dispatcher->trigger('onEventEdited', array($returnid, $isNew));
			$res = $dispatcher->trigger('onFinderAfterSave', array('com_jem.event', $row, $isNew));

			$cache = JFactory::getCache('com_jem');
			$cache->clean();

			$session->clear('eventform', 'com_jem');
		} else {
			$msg = '';
			//back to form
			$link 	= JRoute::_('index.php?view=editevent', false) ;

			JError::raiseWarning('SOME_ERROR_CODE', $model->getError());
		}

		$model->checkin();

		$this->setRedirect($link, $msg);
	}
}
?>