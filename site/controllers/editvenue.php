<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * JEM Component Attendees Controller
 *
 * @package JEM
 *
 */
class JEMControllerEditvenue extends JControllerLegacy
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
	 * Logic for canceling a venue edit task
	 */
	function cancelvenue()
	{
		$user	= JFactory::getUser();
		$id		= JRequest::getInt('id');

		$mode = JRequest::getVar('mode');

		$session = JFactory::getSession();

		$session->clear('venueform', 'com_jem');

		// Must be logged in
		if ($user->get('id') < 1) {
			throw new Exception(JText::_('JERROR_ALERTNOAUTHOR'),403);
			return;
		}

		if ($id) {
			// Create and load a venues table
			$row = JTable::getInstance('jem_venues', '');

			$row->load($id);
			$row->checkin();

			$link = JRoute::_(JEMHelperRoute::getVenueRoute($id), false);
		} else {
			$link = JRequest::getString('referer', JURI::base(), 'post');
		}

		if ($mode == 'ajax')
		{
			// close the window.
			$js = "window.parent.closeAdd() ";
			$doc = JFactory::getDocument();
			$doc->addScriptDeclaration($js);
			/* echo $msg; */
			return;
		}

		$this->setRedirect($link);
	}

	/**
	 * Saves the submitted venue to the database
	 */
	function savevenue()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit('Invalid Token');

		//get image
		$file 		= JRequest::getVar('userfile', '', 'files', 'array');
		$post 		= JRequest::get('post');

		//sticky forms
		$session = JFactory::getSession();
		$session->set('venueform', $post, 'com_jem');

		$isNew = ($post['id']) ? false : true;

		$model = $this->getModel('editvenue');

		//Sanitize
		$post['locdescription'] = JRequest::getVar('locdescription', '', 'post', 'string', JREQUEST_ALLOWRAW);
		if (JRequest::getVar('latitude', '', 'post', 'string') == '') {
			unset($post['latitude']);
		}
		if (JRequest::getVar('longitude', '', 'post', 'string') == '') {
			unset($post['longitude']);
		}

		$mode = JRequest::getVar('mode');

		if ($returnid = $model->store($post, $file)) {
			$msg 	= JText::_('COM_JEM_VENUE_SAVED');

			$link = JRoute::_(JEMHelperRoute::getVenueRoute($returnid), false);

			JPluginHelper::importPlugin('jem');
			$dispatcher = JDispatcher::getInstance();
			$dispatcher->trigger('onVenueEdited', array($returnid, $isNew));

			$cache = JFactory::getCache('com_jem');
			$cache->clean();

			$session->clear('venueform', 'com_jem');
		} else {
			$msg = '';
			//back to form
			$link 	= JRoute::_('index.php?view=editvenue', false) ;
			JError::raiseWarning('SOME_ERROR_CODE', $model->getError());
		}

		$model->checkin();

		if ($mode == 'ajax') {
			$model->setId($returnid);
			$venue = $model->getVenue();

			// fill the event form venue field, and close.
			$js = "window.parent.elSelectVenue('". $venue->id ."', '". str_replace(array("'", "\""), array("\\'", ""), $venue->venue)."')";
			$doc = JFactory::getDocument();
			$doc->addScriptDeclaration($js);
			// echo $msg;

			return;
		}
		$this->setRedirect($link, $msg);
	}
}
?>