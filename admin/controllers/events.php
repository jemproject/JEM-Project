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
class JEMControllerEvents extends JEMController
{
	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();

		// Register Extra task
		$this->registerTask( 'apply', 		'save' );
		$this->registerTask( 'copy',	 	'edit' );
	}

	/**
	 * Logic to publish events
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function publish()
	{
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseError(500, JText::_('COM_JEM_SELECT_ITEM_TO_PUBLISH'));
		}

		$model = $this->getModel('events');
		if(!$model->publish($cid, 1)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		JPluginHelper::importPlugin('finder');
		$dispatcher = JDispatcher::getInstance();
		$res = $dispatcher->trigger('onFinderChangeState', array('com_jem.event', $cid, 1));

		$total = count( $cid );
		$msg 	= $total.' '.JText::_('COM_JEM_EVENT_PUBLISHED');

		$this->setRedirect( 'index.php?option=com_jem&view=events', $msg );
	}


	/**
	 * Logic to unpublish events
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function unpublish()
	{
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseError(500, JText::_('COM_JEM_SELECT_ITEM_TO_UNPUBLISH'));
		}

		$model = $this->getModel('events');
		if(!$model->publish($cid, 0)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		JPluginHelper::importPlugin('finder');
		$dispatcher = JDispatcher::getInstance();
		$res = $dispatcher->trigger('onFinderChangeState', array('com_jem.event', $cid, 0));

		$total = count( $cid );
		$msg 	= $total.' '.JText::_('COM_JEM_EVENT_UNPUBLISHED');

		$this->setRedirect( 'index.php?option=com_jem&view=events', $msg );
	}


	/**
	 * Logic to trash events
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function trash()
	{
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseError(500, JText::_('COM_JEM_SELECT_ITEM_TO_UNPUBLISH'));
		}

		$model = $this->getModel('events');
		if(!$model->publish($cid, -2)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		JPluginHelper::importPlugin('finder');
		$dispatcher = JDispatcher::getInstance();
		$res = $dispatcher->trigger('onFinderChangeState', array('com_jem.event', $cid, -2));

		$total = count( $cid );
		$msg 	= $total.' '.JText::_('COM_JEM_EVENT_TRASHED');

		$this->setRedirect( 'index.php?option=com_jem&view=events', $msg );
	}


	/**
	 * Logic to archive events
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function archive()
	{
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseError(500, JText::_('COM_JEM_SELECT_ITEM_TO_ARCHIVE'));
		}

		$model = $this->getModel('events');
		if(!$model->publish($cid, 2)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		JPluginHelper::importPlugin('finder');
		$dispatcher = JDispatcher::getInstance();
		$res = $dispatcher->trigger('onFinderChangeState', array('com_jem.event', $cid, 2));

		$total = count( $cid );
		$msg 	= $total.' '.JText::_('COM_JEM_EVENT_ARCHIVED');

		$this->setRedirect( 'index.php?option=com_jem&view=events', $msg );
	}


	/**
	 * unarchives an Event
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
	function unarchive()
	{
		$cid 	= JRequest::getVar( 'cid', array(0), 'post', 'array' );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseError(500, JText::_('COM_JEM_SELECT_ITEM_TO_UNARCHIVE' ) );
		}

		$model = $this->getModel('events');

		if(!$model->publish($cid, 0)) {
			echo "<script> alert('".$model->getError(true)."'); window.history.go(-1); </script>\n";
		}

		JPluginHelper::importPlugin('finder');
		$dispatcher = JDispatcher::getInstance();
		$res = $dispatcher->trigger('onFinderChangeState', array('com_jem.event', $cid, 0));

		$total = count( $cid );
		$msg 	= $total.' '.JText::_('COM_JEM_EVENTS_UNARCHIVED');

		$this->setRedirect( 'index.php?option=com_jem&view=events', $msg );
	}


	/**
	 * logic for cancel an action
	 *
	 * @access public
	 * @return void
	 * @since 0.9
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
	 * @since 0.9
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

		JPluginHelper::importPlugin('finder');
		$dispatcher = JDispatcher::getInstance();
		$res = $dispatcher->trigger('onFinderBeforeSave', array('com_jem.event', $row, $isNew));

		if ($returnid = $model->store($post)) {
			$row->id = $returnid;
			$res = $dispatcher->trigger('onFinderAfterSave', array('com_jem.event', $row, $isNew));

			switch ($task)
			{
				case 'apply' :
					$link = 'index.php?option=com_jem&controller=events&view=event&hidemainmenu=1&cid[]='.$returnid;
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

	/**
	 * logic to remove an event
	 *
	 * @access public
	 * @return void
	 * @since 0.9
	 */
 	function remove()
	{
		$cid = JRequest::getVar( 'cid', array(0), 'post', 'array' );

		$total = count( $cid );

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseError(500, JText::_('COM_JEM_SELECT_ITEM_TO_DELETE'));
		}

		$model = $this->getModel('events');
		if(!$model->delete($cid)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		$msg = $total.' '.JText::_( 'COM_JEM_EVENTS_DELETED');

		JPluginHelper::importPlugin('finder');
		$dispatcher = JDispatcher::getInstance();
		foreach($cid as $id) {
			// Mock up a JTable class for finder
			$row = new stdClass;
			$row->id = $id;

			$res = $dispatcher->trigger('onFinderAfterDelete', array('com_jem.event', $row));
		}

		$cache = JFactory::getCache('com_jem');
		$cache->clean();

		$this->setRedirect( 'index.php?option=com_jem&view=events', $msg );
	}

	/**
	 * Fetch hit count of the event
	 *
	 * @access public
	 * @return void
	 * @since 1.1
	 */
	function gethits()
	{
		$id 	= JRequest::getInt('id', 0);
		$model 	= $this->getModel('event');
		$hits 	= $model->gethits($id);

		if ($hits) {
			echo $hits;
		} else {
			echo 0;
		}
	}

	/**
	 * Reset hit count of the event
	 *
	 * @access public
	 * @return void
	 * @since 1.1
	 */
	function resethits()
	{
		$id		= JRequest::getInt( 'id', 0 );
		$model = $this->getModel('event');

		$model->resetHits($id);

		echo 0;
	}
}
?>