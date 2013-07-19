<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * JEM Component Events Controller
 *
 * @package JEM
 * @since 0.9
 */
class JEMControllerEvent extends JEMController
{
	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();
		
		$this->registerTask( 'apply', 		'save' );
		$this->registerTask( 'copy',	 	'edit' );
		
	}

	/**
	 * logic for cancel an action
	 *
	 * @access public
	 * @return void
	 * 
	 */
	function cancel()
	{
		// Check for request forgeries
		JRequest::checkToken() or die( 'Invalid Token' );
	
		$group =  JTable::getInstance('jem_events', '');
		$group->bind(JRequest::get('post'));
		$group->checkin();
	
		$this->setRedirect( 'index.php?option=com_jem&view=events' );
	}
	
	
	/**
	 * logic to create the new event screen
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function add( )
	{
		$this->setRedirect( 'index.php?option=com_jem&view=event' );
	}
	
	
	/**
	 * logic to create the edit event screen
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function edit( )
	{
		JRequest::setVar( 'view', 'event' );
		JRequest::setVar( 'hidemainmenu', 1 );
	
		$model 	= $this->getModel('event');
		$task 	= JRequest::getVar('task');
	
		if ($task == 'copy') {
			JRequest::setVar( 'task', $task );
		} else {
	
			$user	= JFactory::getUser();
			// Error if checkedout by another administrator
			if ($model->isCheckedOut( $user->get('id') )) {
				$this->setRedirect( 'index.php?option=com_jem&view=events', JText::_( 'COM_JEM_EDITED_BY_ANOTHER_ADMIN' ) );
			}
			$model->checkout();
		}
		parent::display();
	}
	
	
	/**
	 * logic to save an event
	 *
	 * @access public
	 * @return void
	 * 
	 */
	function save()
	{
		// Check for request forgeries
		JRequest::checkToken() or die( 'Invalid Token' );
	
		$task		= JRequest::getVar('task');
	
		$post = JRequest::get( 'post' );
		$post['datdescription'] = JRequest::getVar( 'datdescription', '', 'post','string', JREQUEST_ALLOWRAW );
		$post['datdescription']	= str_replace( '<br>', '<br />', $post['datdescription'] );
	
		$isNew = ($post['id']) ? false : true;
	
		$model = $this->getModel('event');
	
		// Mock up a JTable class for finder
		$row = new stdClass;
		$row->id = $post['id'];
		// TODO: Get correct access level for onFinderBeforeSave and onFinderAfterSave
		$row->access = 1;
	
		JPluginHelper::importPlugin('finder');
		$dispatcher = JDispatcher::getInstance();
		$res = $dispatcher->trigger('onFinderBeforeSave', array('com_jem.event', $row, $isNew));
	
		if ($returnid = $model->store($post)) {
			$row->id = $returnid;
			$res = $dispatcher->trigger('onFinderAfterSave', array('com_jem.event', $row, $isNew));
	
			switch ($task)
			{
				case 'apply' :
					$link = 'index.php?option=com_jem&view=event&hidemainmenu=1&cid[]='.$returnid;
					break;
	
				default :
					$link = 'index.php?option=com_jem&view=events';
					break;
			}
			$msg	= JText::_( 'COM_JEM_EVENT_SAVED');
	
			$cache = JFactory::getCache('com_jem');
			$cache->clean();
	
		} else {
	
			$msg 	= '';
			$link = 'index.php?option=com_jem&view=events';
	
		}
	
		$model->checkin();
	
		$this->setRedirect( $link, $msg );
	}
	
	
	function showaddvenue()
	{
	JRequest::setVar( 'view', 'event' );
	JRequest::setVar( 'layout', 'addvenue'  );
	
	parent::display();
	}
	
	
	
	/**
	 * saves the venue in the database
	 *
	 * @access public
	 * @return void
	 * 
	 */
	function addvenue()
	{
		// Sanitize
		$post = JRequest::get( 'post' );
		$post['locdescription'] = JRequest::getVar( 'locdescription', '', 'post', 'string', JREQUEST_ALLOWRAW );
	
	
		$model = $this->getModel('venue');
		$model->store($post);
		$model->checkin();
	
		$msg	= JText::_( 'COM_JEM_VENUE_SAVED');
		$link 	= 'index.php?option=com_jem&view=event&layout=addvenue&tmpl=component';
	
		$this->setRedirect( $link, $msg );
	}
	
}
?>