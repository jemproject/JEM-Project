<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport('joomla.application.component.controller');

/**
 * EventList Component Attendees Controller
 *
 * @package Joomla
 * @subpackage EventList
 * @since 0.9
 */
class EventListControllerAttendees extends EventListController
{
	/**
	 * Constructor
	 *
	 *@since 0.9
	 */
	function __construct()
	{
		parent::__construct();

		// Register Extra task
		$this->registerTask( 'add', 		'edit' );
		$this->registerTask( 'apply', 		'save' );
	}

	/**
	 * Delete attendees
	 *
	 * @return true on sucess
	 * @access private
	 * @since 0.9
	 */
	function remove()
	{
		$cid = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$id 	= JRequest::getInt('id');
		$total 	= count( $cid );

		$model = $this->getModel('attendees');
		
		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseError(500, JText::_( 'Select an item to delete' ) );
		}

		if(!$model->remove($cid)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		$cache = &JFactory::getCache('com_eventlist');
		$cache->clean();

		$msg = $total.' '.JText::_( 'REGISTERED USERS DELETED');

		$this->setRedirect( 'index.php?option=com_eventlist&view=attendees&id='.$id, $msg );
	}

	function export()
	{
		$app			=& JFactory::getApplication();;

		$model = $this->getModel('attendees');

		$datas = $model->getData();

		header('Content-Type: text/x-csv');
		header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Content-Disposition: attachment; filename=attendees.csv');
		header('Pragma: no-cache');

		$k = 0;
		$export = '';
		$col = array();

		for($i=0, $n=count( $datas ); $i < $n; $i++)
		{
			$data = &$datas[$i];

    		$col[] = str_replace("\"", "\"\"", $data->name);
    		$col[] = str_replace("\"", "\"\"", $data->username);
    		$col[] = str_replace("\"", "\"\"", $data->email);
    		$col[] = str_replace("\"", "\"\"", JHTML::Date( $data->uregdate, JText::_( 'DATE_FORMAT_LC2' ) ));
    		$col[] = str_replace("\"", "\"\"", $data->uid);

   	 		for($j = 0; $j < count($col); $j++)
    		{
        		$export .= "\"" . $col[$j] . "\"";

        		if($j != count($col)-1)
       	 		{
            		$export .= ";";
        		}
    		}
    		$export .= "\r\n";
    		$col = '';

			$k = 1 - $k;
		}

		echo $export;

		$app->close();
	}

	/**
	 * redirect to events page
	 */
  function back()
  {
    $this->setRedirect( 'index.php?option=com_eventlist&view=events' );
  }
  
  function toggle()
  {  	
		$id = JRequest::getInt('id');
		
		$model = $this->getModel('attendee');
		$model->setId($id);
		
		$attendee = $model->getData();
		$res = $model->toggle();
		
		$type = 'message';
		
		if ($res) 
		{						
			JPluginHelper::importPlugin( 'eventlist' );
	    $dispatcher =& JDispatcher::getInstance();
	   	$res = $dispatcher->trigger( 'onUserOnOffWaitinglist', array( $id ) );
	   	
			if ($attendee->waiting)
			{
				$msg = JText::_('COM_EVENTLIST_ADDED_TO_ATTENDING');
			}
			else
			{			
				$msg = JText::_('COM_EVENTLIST_ADDED_TO_WAITING');
			}
		}
		else
		{
			$msg = JText::_('COM_EVENTLIST_WAITINGLIST_TOGGLE_ERROR').': '.$model->getError();
			$type = 'error';
		}
		$this->setRedirect('index.php?option=com_eventlist&view=attendees&id='.$attendee->event, $msg, $type);
		$this->redirect();
  }

	/**
	 * logic for cancel an action
	 *
	 * @access public
	 * @return void
	 * @since 1.1
	 */
	function cancel()
	{
		// Check for request forgeries
		JRequest::checkToken() or die( 'Invalid Token' );
		
		$venue = & JTable::getInstance('eventlist_register', '');
		$venue->bind(JRequest::get('post'));
		$venue->checkin();

		$this->setRedirect( 'index.php?option=com_eventlist&view=attendees&id='.JRequest::getInt('event') );
	}

	/**
	 * logic to create the edit attendee view
	 *
	 * @access public
	 * @return void
	 * @since 1.1
	 */
	function edit( )
	{
		JRequest::setVar( 'view', 'attendee' );
		JRequest::setVar( 'hidemainmenu', 1 );

		$model 	= $this->getModel('attendee');
		$user	=& JFactory::getUser();

//		// Error if checkedout by another administrator
//		if ($model->isCheckedOut( $user->get('id') )) {
//			$this->setRedirect( 'index.php?option=com_eventlist&view=attendees', JText::_( 'EDITED BY ANOTHER ADMIN' ) );
//		}
//
//		$model->checkout();
		
		parent::display();
	}

	/**
	 * saves the attendee in the database
	 *
	 * @access public
	 * @return void
	 * @since 1.1
	 */
	function save()
	{
		// Check for request forgeries
		JRequest::checkToken() or die( 'Invalid Token' );
		
		$task		= JRequest::getVar('task');

		// Sanitize
		$post = JRequest::get( 'post' );

		$model = $this->getModel('attendee');

		if ($row = $model->store($post)) 
		{
			if (JRequest::getInt('sendemail', 0))
			{
				JPluginHelper::importPlugin( 'eventlist' );
		    $dispatcher =& JDispatcher::getInstance();
		   	$res = $dispatcher->trigger( 'onEventUserRegistered', array( $row->id ) );
			}
			
			switch ($task)
			{
				case 'apply':
					$link = 'index.php?option=com_eventlist&view=attendee&hidemainmenu=1&cid[]='.$row->id.'&event='.$row->event;
					break;

				default:
					$link = 'index.php?option=com_eventlist&view=attendees&id='.$row->event;
					break;
			}
			$msg	= JText::_( 'ATTENDEE SAVED');

			$cache = &JFactory::getCache('com_eventlist');
			$cache->clean();			

		} else {

			$msg 	= '';
			$link 	= 'index.php?option=com_eventlist&view=attendees&id='.JRequest::getInt('event');

		}
		$this->setRedirect( $link, $msg );
	}
	
	function selectUser()
	{
		JRequest::setVar('view', 'userelement');
		parent::display();
	}
}
?>