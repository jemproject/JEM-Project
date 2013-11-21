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
class JEMControllerEvent extends JControllerLegacy
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
	 * Saves the registration to the database
	 */
	function userregister()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit('Invalid Token');
	
		$id 	= JRequest::getInt('rdid', 0, 'post');
	
		// Get the model
		$model = $this->getModel('Event', 'JEMModel');
	
		$model->setId($id);
		$register_id = $model->userregister();
		if (!$register_id)
		{
			$msg = $model->getError();
			$this->setRedirect(JRoute::_(JEMHelperRoute::getEventRoute($id), false), $msg, 'error');
			$this->redirect();
			return;
		}
	
		JPluginHelper::importPlugin('jem');
		$dispatcher = JDispatcher::getInstance();
		$dispatcher->trigger('onEventUserRegistered', array($register_id));
	
		$cache = JFactory::getCache('com_jem');
		$cache->clean();
	
		$msg = JText::_('COM_JEM_REGISTERED_SUCCESSFULL');
	
		$this->setRedirect(JRoute::_(JEMHelperRoute::getEventRoute($id), false), $msg);
	}
	
	/**
	 * Deletes a registered user
	 */
	function delreguser()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit('Invalid Token');
	
		$id = JRequest::getInt('rdid', 0, 'post');
	
		// Get/Create the model
		$model = $this->getModel('Event', 'JEMModel');
	
		$model->setId($id);
		$model->delreguser();
	
		JEMHelper::updateWaitingList($id);
	
		JPluginHelper::importPlugin('jem');
		$dispatcher = JDispatcher::getInstance();
		$dispatcher->trigger('onEventUserUnregistered', array($id));
	
		$cache = JFactory::getCache('com_jem');
		$cache->clean();
	
		$msg = JText::_('COM_JEM_UNREGISTERED_SUCCESSFULL');
		$this->setRedirect(JRoute::_(JEMHelperRoute::getEventRoute($id), false), $msg);
	}
}
?>