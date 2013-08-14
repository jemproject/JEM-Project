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
 * JEM Component Groups Controller
 *
 * @package JEM
 * 
 */
class JEMControllerGroup extends JEMController
{
	/**
	 * Constructor
	 *
	 * 
	 */
	function __construct()
	{
		parent::__construct();
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

		$session 	= JFactory::getSession();

		$session->clear('groupform', 'com_jem');

		$group = JTable::getInstance('jem_groups', '');
		$group->bind(JRequest::get('post'));
		$group->checkin();

		$this->setRedirect( 'index.php?option=com_jem&view=groups' );
	}

	/**
	 * logic to create the new event screen
	 *
	 * @access public
	 * @return void
	 * 
	 */
	function add( )
	{
		$this->setRedirect( 'index.php?option=com_jem&view=group' );
	}

	/**
	 * logic to create the edit event screen
	 *
	 * @access public
	 * @return void
	 * 
	 */
	function edit( )
	{
		JRequest::setVar( 'view', 'group' );
		JRequest::setVar( 'hidemainmenu', 1 );

		$model = $this->getModel('group');
		$user	= JFactory::getUser();

		// Error if checkedout by another administrator
		if ($model->isCheckedOut( $user->get('id') )) {
			$this->setRedirect( 'index.php?option=com_jem&view=groups', JText::_( 'COM_JEM_EDITED_BY_ANOTHER_ADMIN' ) );
		}

		$model->checkout();

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

		$post 	= JRequest::get( 'post' );

		//sticky forms
		$session = JFactory::getSession();
		$session->set('groupform', $post, 'com_jem');

		$model = $this->getModel('group');

		if ($model->store($post)) {

			$link 	= 'index.php?option=com_jem&view=groups';
			$msg	= JText::_( 'COM_JEM_GROUP_SAVED');

			$session->clear('groupform', 'com_jem');

		} else {

			$link 	= 'index.php?option=com_jem&view=group';
			$msg	= '';

		}

		$model->checkin();

		$this->setRedirect( $link, $msg );
 	}

	/**
	 * logic to remove a group
	 *
	 * @access public
	 * @return void
	 * 
	 */
 	function remove()
	{
		$cid = JRequest::getVar( 'cid', array(0), 'post', 'array' );

		$total = count( $cid );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseError(500, JText::_('COM_JEM_SELECT_ITEM_TO_DELETE'));
		}

		$model = $this->getModel('groups');

		if(!$model->delete($cid)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		$msg = $total.' '.JText::_( 'COM_JEM_GROUPS_DELETED');

		$this->setRedirect( 'index.php?option=com_jem&view=groups', $msg );
	}
	
	
	
	/**
	 * Logic to publish events
	 *
	 * @access public
	 * @return void
	 * 
	 */
	function enableaddvenue()
	{
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );
	
		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseError(500, JText::_('COM_JEM_SELECT_ITEM'));
		}
	
		$model = $this->getModel('groups');
		if(!$model->addvenue($cid, 1)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}
	
	
		$total = count( $cid );
		$msg 	= $total.' '.JText::_('COM_JEM_EVENT_PUBLISHED');
	
		 $this->setRedirect( 'index.php?option=com_jem&view=groups', $msg );
	}
	
	
	/**
	 * Logic to unpublish events
	 *
	 * @access public
	 * @return void
	 * 
	 */
	function disableaddvenue()
	{
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );
	
		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseError(500, JText::_('COM_JEM_SELECT_ITEM'));
		}
	
		$model = $this->getModel('groups');
		if(!$model->addvenue($cid, 0)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}
	
		$total = count( $cid );
		$msg 	= $total.' '.JText::_('COM_JEM_EVENT_UNPUBLISHED');
	
		 $this->setRedirect( 'index.php?option=com_jem&view=groups', $msg );
	}
	
	
	
}
?>