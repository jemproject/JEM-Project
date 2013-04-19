<?php
/**
 * @version 1.1 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

 if(!defined('DS')) define('DS', DIRECTORY_SEPARATOR);
defined( '_JEXEC' ) or die;

jimport('joomla.application.component.controller');

/**
 * JEM Component Controller
 *
 * @package JEM
 * @since 0.9
 */
class JEMController extends JControllerLegacy
{
	/**
	 * Constructor
	 *
	 * @since 0.9
	 */
	function __construct()
	{
		parent::__construct();

		//register extratasks
		$this->registerTask( 'ical', 		'vcal' );
	}

	/**
	 * Display the view
	 * 
	 * @since 0.9
	 */
	function display($cachable = false, $urlparams = false)
	{
		parent::display();
	}

	/**
	 * Logic for canceling an event edit task
	 * 
	 * @since 0.9
	 */
	function cancelevent()
	{
		$user		=  JFactory::getUser();
		$id			= JRequest::getInt( 'id');
		$session 	=  JFactory::getSession();
		
		$session->clear('eventform', 'com_jem');

		// Must be logged in
		if ($user->get('id') < 1) {
			JError::raiseError( 403, JText::_('COM_JEM_ALERTNOTAUTH') );
			return;
		}

		if ($id) {
			// Create and load a events table
			$row = JTable::getInstance('jem_events', '');

			$row->load($id);
			$row->checkin();

			$this->setRedirect( JRoute::_( JEMHelperRoute::getRoute($id), false) );

		} else {
			$link = JRequest::getString('referer', JURI::base(), 'post');
			$this->setRedirect($link);
		}
	}

	/**
	 * Logic for canceling an event and proceed to add a venue
	 * 
	 * @since 0.9
	 */
	function addvenue()
	{
		$user	= JFactory::getUser();
		$id		= JRequest::getInt( 'id');
		
		$post = JRequest::get( 'post' );
		//sticky forms
		$session = JFactory::getSession();
		$session->set('eventform', $post, 'com_jem');

		// Must be logged in
		if ($user->get('id') < 1) {
			JError::raiseError( 403, JText::_('ALERTNOTAUTH') );
			return;
		}

		if ($id) {
			// Create and load a events table
			$row = JTable::getInstance('jem_events', '');

			$row->load($id);
			$row->checkin();
		}

		$this->setRedirect( JRoute::_('index.php?view=editvenue', false ) );
	}

	/**
	 * Logic for canceling a venue edit task
	 *
	 * @since 0.9
	 */
	function cancelvenue()
	{
		$user	=  JFactory::getUser();
		$id		= JRequest::getInt( 'id' );
		
    $mode = JRequest::getVar('mode');
		
		$session 	=  JFactory::getSession();
		
		$session->clear('venueform', 'com_jem');

		// Must be logged in
		if ($user->get('id') < 1) {
			JError::raiseError( 403, JText::_('COM_JEM_ALERTNOTAUTH') );
			return;
		}

		if ($id) {
			// Create and load a venues table
			$row = JTable::getInstance('jem_venues', '');

			$row->load($id);
			$row->checkin();
		      
			$link = JRoute::_('index.php?view=venueevents&id='.$id, false);
			
		} else {
			$link = JRequest::getString('referer', JURI::base(), 'post');
		}
	
		if ($mode == 'ajax') 
		{
		  // close the window.
		  $js = "window.parent.closeAdd() ";
		  $doc = JFactory::getDocument();
		  $doc->addScriptDeclaration($js);
		  echo $msg;
		  return;
		}
      
		$this->setRedirect($link);
	}

	/**
	 * Saves the submitted venue to the database
	 *
	 * @since 0.5
	 */
	function savevenue()
	{
		// Check for request forgeries
		JRequest::checkToken() or die( 'Invalid Token' );
		
		//Sanitize
		$post = JRequest::get( 'post' );
		$post['locdescription'] = JRequest::getVar( 'locdescription', '', 'post', 'string', JREQUEST_ALLOWRAW );
		
		//sticky forms
		$session = JFactory::getSession();
		$session->set('venueform', $post, 'com_jem');
    	
		if (JRequest::getVar( 'latitude', '', 'post', 'string') == '') {
      		unset($post['latitude']);
    	}
    	if (JRequest::getVar( 'longitude', '', 'post', 'string') == '') {
	      	unset($post['longitude']);
    	}

		$isNew = ($post['id']) ? false : true;
		
		$file 		= JRequest::getVar( 'userfile', '', 'files', 'array' );

		$model = $this->getModel('editvenue');

    $mode = JRequest::getVar('mode');
    
		if ($returnid = $model->store($post, $file)) {

			$msg 	= JText::_( 'COM_JEM_VENUE_SAVED' );
			
			//check if we return from an addvenue form
			if ($session->has('eventform', 'com_jem')) {
				$link = JRoute::_('index.php?view=editevent', false) ;
				} else {
				$link 	= JRoute::_('index.php?view=venueevents&id='.$returnid, false) ;
			}

			JPluginHelper::importPlugin( 'jem' );
      		$dispatcher = JDispatcher::getInstance();
      		$res = $dispatcher->trigger( 'onVenueEdited', array( $returnid, $isNew ) );

			$cache = JFactory::getCache('com_jem');
			$cache->clean();

		} else {

			$msg 		= '';
			//back to form
			$link 	= JRoute::_('index.php?view=editvenue', false) ;

			JError::raiseWarning('SOME_ERROR_CODE', $model->getError() );
		}

		$model->checkin();
	
		// in case it's called from modal window
    	if ($mode == 'ajax') {
      		$model->setId($returnid);
      		$venue = $model->getVenue();
      
      		// fill the event form venue field, and close.
      		$js = "window.parent.elSelectVenue('". $venue->id ."', '". str_replace( array("'", "\""), array("\\'", ""), $venue->venue)."')";
      		$doc = JFactory::getDocument();      
      		$doc->addScriptDeclaration($js);
      		echo $msg;
      		
      		return;
    	}
    
		$this->setRedirect($link, $msg );
	}

	/**
	 * Cleanes and saves the submitted event to the database
	 *
	 * TODO: Check if the user is allowed to post events assigned to this category/venue
	 *
	 * @since 0.4
	 */
	function saveevent()
	{
		// Check for request forgeries
		JRequest::checkToken() or die( 'Invalid Token' );
		
		//get image
		$file 		= JRequest::getVar( 'userfile', '', 'files', 'array' );
		$post 		= JRequest::get( 'post' );
		
		//sticky forms
		$session = JFactory::getSession();
		$session->set('eventform', $post, 'com_jem');
		
		$isNew = ($post['id']) ? false : true;

		$model = $this->getModel('editevent');

		if ($returnid = $model->store($post, $file)) {

			$msg 	= JText::_( 'COM_JEM_EVENT_SAVED' );
			$link 	= JRoute::_( JEMHelperRoute::getRoute($returnid), false) ;
			
			JPluginHelper::importPlugin( 'jem' );
			$dispatcher = JDispatcher::getInstance();
			$res = $dispatcher->trigger( 'onEventEdited', array( $returnid, $isNew ) );			

			$cache = JFactory::getCache('com_jem');
			$cache->clean();
			
			$session->clear('eventform', 'com_jem');

		} else {

			$msg 		= '';
			//back to form
			$link 	= JRoute::_('index.php?view=editevent', false) ;
			
			JError::raiseWarning('SOME_ERROR_CODE', $model->getError() );
		}

		$model->checkin();

		$this->setRedirect($link, $msg );
	}

	/**
	 * Saves the registration to the database
	 *
	 * @since 0.7
	 */
	function userregister()
	{
		// Check for request forgeries
		JRequest::checkToken() or die( 'Invalid Token' );

		$id 	= JRequest::getInt( 'rdid', 0, 'post' );

		// Get the model
		$model = $this->getModel('Details', 'JEMModel');

		$model->setId($id);
		$register_id = $model->userregister();
		if (!$register_id) 
		{
			$msg = $model->getError();
			$this->setRedirect(JRoute::_( JEMHelperRoute::getRoute($id), false), $msg, 'error' );
			$this->redirect();
			return;
		}
		
		JPluginHelper::importPlugin( 'jem' );
    $dispatcher = JDispatcher::getInstance();
   	$res = $dispatcher->trigger( 'onEventUserRegistered', array( $register_id ) );

		$cache = JFactory::getCache('com_jem');
		$cache->clean();

		$msg = JText::_( 'COM_JEM_REGISTERED_SUCCESSFULL' );

		$this->setRedirect(JRoute::_( JEMHelperRoute::getRoute($id), false), $msg );
	}

	/**
	 * Deletes a registered user
	 *
	 * @since 0.7
	 */
	function delreguser()
	{
		// Check for request forgeries
		JRequest::checkToken() or die( 'Invalid Token' );

		$id 	= JRequest::getInt( 'rdid', 0, 'post' );

		// Get/Create the model
		$model =  $this->getModel('Details', 'JEMModel');

		$model->setId($id);
		$model->delreguser();
		
		JEMHelper::updateWaitingList($id);
		
		JPluginHelper::importPlugin( 'jem' );
    $dispatcher = JDispatcher::getInstance();
    $res = $dispatcher->trigger( 'onEventUserUnregistered', array( $id ) ); 

		$cache = JFactory::getCache('com_jem');
		$cache->clean();

		$msg = JText::_( 'COM_JEM_UNREGISTERED_SUCCESSFULL' );
		$this->setRedirect( JRoute::_( JEMHelperRoute::getRoute($id), false), $msg );
	}

	/**
	 * offers the vcal/ical functonality
	 * 
	 * @todo Not yet working
	 *
	 * @author Lybegard Karl-Olof
	 * @since 0.9
	 */
	function vcal()
	{
		$app =  JFactory::getApplication();

		$task 			= JRequest::getWord( 'task' );
		$id 			= JRequest::getInt( 'id' );
		$user_offset 	= $app->getCfg( 'offset_user' );

		//get Data from model
		$model =  $this->getModel('Details', 'JEMModel');
		$model->setId((int)$id);

		$row = $model->getDetails();

		$Start = mktime(strftime('%H', strtotime($row->times)),
				strftime('%M', strtotime($row->times)),
				strftime('%S', strtotime($row->times)),
				strftime('%m', strtotime($row->dates)),
				strftime('%d', strtotime($row->dates)),
				strftime('%Y', strtotime($row->dates)),0);

		$End   = mktime(strftime('%H', strtotime($row->endtimes)),
				strftime('%M', strtotime($row->endtimes)),
				strftime('%S', strtotime($row->endtimes)),
				strftime('%m', strtotime($row->enddates)),
				strftime('%d', strtotime($row->enddates)),
				strftime('%Y', strtotime($row->enddates)),0);

		require_once (JPATH_COMPONENT_SITE.DS.'classes'.DS.'vcal.class.php');

		$v = new vCal();

		$v->setTimeZone($user_offset);
		$v->setSummary($row->venue.'-'.$row->catname.'-'.$row->title);
		$v->setDescription($row->datdescription);
		$v->setStartDate($Start);
		$v->setEndDate($End);
		$v->setLocation($row->street.', '.$row->plz.', '.$row->city.', '.$row->country);
		$v->setFilename((int)$row->did);

		if ($task == 'vcal') {
			$v->generateHTMLvCal();
		} else {
			$v->generateHTMLiCal();
		}

	}
	
	/**
	 * for attachement downloads
	 * 
	 */
	function getfile()
	{
		$id = JRequest::getInt('file');
		$user = JFactory::getUser();
		
		if (JFactory::getUser()->authorise('core.manage')) {
           $gid = (int) 3;      //viewlevel Special
           } else {
               if($user->get('id')) {
                   $gid = (int) 2;    //viewlevel Registered
               } else {
                   $gid = (int) 1;    //viewlevel Public
               }
           }
		
		$path = JEMAttachment::getAttachmentPath($id, $gid);
				
		$mime = JEMHelper::getMimeType($path);
		
		$doc = JFactory::getDocument();
		$doc->setMimeEncoding($mime);
		header('Content-Disposition: attachment; filename="'.basename($path).'"');
		if ($fd = fopen ($path, "r")) 
		{
	    $fsize = filesize($path);
	    header("Content-length: $fsize");
	    header("Cache-control: private"); //use this to open files directly
	    while(!feof($fd)) {
	        $buffer = fread($fd, 2048);
	        echo $buffer;
	    }
		}
		fclose ($fd);
		return;
	}

	/**
	 * Delete attachment
	 *
	 * @return true on sucess
	 * @access private
	 * @since 1.1
	 */
	function ajaxattachremove()
	{
		$id     = JRequest::getVar( 'id', 0, 'request', 'int' );

		$res = JEMAttachment::remove($id);
		if (!$res) {
			echo 0;
			exit();
		}

		$cache = JFactory::getCache('com_jem');
		$cache->clean();

		echo 1;
		exit();
	}
}
?>