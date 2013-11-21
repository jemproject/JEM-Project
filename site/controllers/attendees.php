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
class JEMControllerAttendees extends JControllerLegacy
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
	 * redirect to events page
	 */
	function back()
	{
		$this->setRedirect(JEMHelperRoute::getMyEventsRoute());
		$this->redirect();
	}
	
	/**
	 * removetask
	 */
	function attendeeremove()
	{
		$cid = JRequest::getVar('cid', array(0), 'post', 'array');
		$id  = JRequest::getInt('id');
		$fid = JRequest::getInt('Itemid');
		$total = count($cid);
	
		$model = $this->getModel('attendees');
	
		if (!is_array($cid) || count($cid) < 1) {
			JError::raiseError(500, JText::_('COM_JEM_SELECT_ITEM_TO_DELETE'));
		}
	
		if(!$model->remove($cid)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}
	
		$cache = JFactory::getCache('com_jem');
		$cache->clean();
	
		$msg = $total.' '.JText::_('COM_JEM_REGISTERED_USERS_DELETED');
	
		$this->setRedirect('index.php?option=com_jem&view=attendees&id='.$id.'&Itemid='.$fid, $msg);
	}
	
	
	/**
	 * toggletask
	 */
	function attendeetoggle()
	{
		$id = JRequest::getInt('id');
		$fid = JRequest::getInt('Itemid');
	
		$model = $this->getModel('attendee');
		$model->setId($id);
	
		$attendee = $model->getData();
		$res = $model->toggle();
	
		$type = 'message';
	
		if ($res)
		{
			JPluginHelper::importPlugin('jem');
			$dispatcher = JDispatcher::getInstance();
			$res = $dispatcher->trigger('onUserOnOffWaitinglist', array($id));
	
			if ($attendee->waiting) {
				$msg = JText::_('COM_JEM_ADDED_TO_ATTENDING');
			} else {
				$msg = JText::_('COM_JEM_ADDED_TO_WAITING');
			}
		}
		else
		{
			$msg = JText::_('COM_JEM_WAITINGLIST_TOGGLE_ERROR').': '.$model->getError();
			$type = 'error';
		}
	
		$this->setRedirect('index.php?option=com_jem&view=attendees&id='.$attendee->event.'&Itemid='.$fid, $msg, $type);
		$this->redirect();
	}
}
?>