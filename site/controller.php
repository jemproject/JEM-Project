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
			throw new Exception(JText::_('JERROR_ALERTNOAUTHOR'),403);
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

		$this->setRedirect( JRoute::_('index.php?view=editvenue', false ) );
	}



	/**
	 * Logic for canceling an event and proceed to add a venue
	 *
	 * @since 0.9
	 */
	function unpublishtask()
	{

		$app = JFactory::getApplication();
		$menuitem = $app->getMenu()->getActive()->id;
		$input = $app->input;

		$cid 	= $input->get( 'cid', array(0), 'post', 'array' );

		$false = array_search('0', $cid);

		if ($false === 0) {
			JError::raiseNotice(100, JText::_('COM_JEM_SELECT_ITEM_TO_UNPUBLISH'));
			$this->setRedirect( 'index.php?option=com_jem&view=myevents'.'&Itemid='.$menuitem);
			return;
		}

		$model = $this->getModel('myevents');
		if(!$model->publish($cid, 0)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		$total = count( $cid );
		$msg 	= $total.' '.JText::_('COM_JEM_EVENT_UNPUBLISHED');


		$this->setRedirect( 'index.php?option=com_jem&view=myevents'.'&Itemid='.$menuitem, $msg );
	}



	/**
	 * Logic for canceling an event and proceed to add a venue
	 *
	 * @since 0.9
	 */
	function unpublish()
	{

		$app = JFactory::getApplication();
		$menuitem = $app->getMenu()->getActive()->id;
		$input = $app->input;

		$cid 	= $input->get( 'cid', array(0), 'post', 'array' );

		$false = array_search('0', $cid);

		if ($false === 0) {
			JError::raiseNotice(100, JText::_('COM_JEM_SELECT_ITEM_TO_UNPUBLISH'));
			$this->setRedirect( 'index.php?option=com_jem&view=myevents'.'&Itemid='.$menuitem);
			return;
		}

		$model = $this->getModel('myevents');
		if(!$model->publish($cid, 0)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		$total = count( $cid );
		$msg 	= $total.' '.JText::_('COM_JEM_EVENT_UNPUBLISHED');


		$this->setRedirect( 'index.php?option=com_jem&view=myevents'.'&Itemid='.$menuitem, $msg );
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
		$app = JFactory::getApplication();
		$menuitem = $app->getMenu()->getActive()->id;
		$input = $app->input;

		$cid 	= $input->get( 'cid', array(0), 'post', 'array' );

		$false = array_search('0', $cid);

		if ($false === 0) {
			JError::raiseNotice(100, JText::_('COM_JEM_SELECT_ITEM_TO_PUBLISH'));
			$this->setRedirect( 'index.php?option=com_jem&view=myevents'.'&Itemid='.$menuitem);
			return;
		}

		$model = $this->getModel('myevents');
		if(!$model->publish($cid, 1)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		$total = count( $cid );
		$msg 	= $total.' '.JText::_('COM_JEM_EVENT_PUBLISHED');

		$this->setRedirect( 'index.php?option=com_jem&view=myevents'.'&Itemid='.$menuitem, $msg );
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

		$app = JFactory::getApplication();
		$menuitem = $app->getMenu()->getActive()->id;
		$input = $app->input;


		$cid 	= $input->get( 'cid', array(0), 'post', 'array' );

			$false = array_search('0', $cid);

		if ($false === 0) {
			JError::raiseNotice(100, JText::_('COM_JEM_SELECT_ITEM_TO_TRASH'));
			$this->setRedirect( 'index.php?option=com_jem&view=myevents'.'&Itemid='.$menuitem);
			return;
		}

		$model = $this->getModel('myevents');
		if(!$model->publish($cid, -2)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		$total = count( $cid );
		$msg 	= $total.' '.JText::_('COM_JEM_EVENT_TRASHED');

		$this->setRedirect( 'index.php?option=com_jem&view=myevents'.'&Itemid='.$menuitem, $msg );
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
			throw new Exception(JText::_('JERROR_ALERTNOAUTHOR'),403);
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
			/* echo $msg; */
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

		//get image
		$file 		= JRequest::getVar( 'userfile', '', 'files', 'array' );
		$post 		= JRequest::get( 'post' );

		$Itemid 		= JRequest::getCmd( 'Itemid' );

		//sticky forms
		$session = JFactory::getSession();
		$session->set('venueform', $post, 'com_jem');

		$isNew = ($post['id']) ? false : true;

		$model = $this->getModel('editvenue');

		//Sanitize
		$post['locdescription'] = JRequest::getVar( 'locdescription', '', 'post', 'string', JREQUEST_ALLOWRAW );
		if (JRequest::getVar( 'latitude', '', 'post', 'string') == '') {
			unset($post['latitude']);
		}
		if (JRequest::getVar( 'longitude', '', 'post', 'string') == '') {
			unset($post['longitude']);
		}

		$mode = JRequest::getVar('mode');

		if ($returnid = $model->store($post, $file)) {
			$row->id = $returnid;


			$msg 	= JText::_( 'COM_JEM_VENUE_SAVED' );


			/*$link 	= JRoute::_( JEMHelperRoute::getRoute($returnid), false) ;*/
			$link = 'index.php?option=com_jem&view=venueevents&id='.$returnid.'&Itemid='.$Itemid;


			JPluginHelper::importPlugin( 'jem' );
			$dispatcher = JDispatcher::getInstance();
			$res = $dispatcher->trigger( 'onVenueEdited', array( $returnid, $isNew ) );

			$cache = JFactory::getCache('com_jem');
			$cache->clean();

			$session->clear('venueform', 'com_jem');

			} else {

				$msg = '';
				//back to form
				$link 	= JRoute::_('index.php?view=editvenue', false) ;
				JError::raiseWarning('SOME_ERROR_CODE', $model->getError() );
			}
			$model->checkin();


			if ($mode == 'ajax') {
				$model->setId($returnid);
				$venue = $model->getVenue();

				// fill the event form venue field, and close.
				$js = "window.parent.elSelectVenue('". $venue->id ."', '". str_replace( array("'", "\""), array("\\'", ""), $venue->venue)."')";
				$doc = JFactory::getDocument();
				$doc->addScriptDeclaration($js);
				// echo $msg;

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
			$msg 	= JText::_( 'COM_JEM_EVENT_SAVED' );
			$link 	= JRoute::_( JEMHelperRoute::getRoute($returnid), false) ;

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
		$model = $this->getModel('Event', 'JEMModel');

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
		$model =  $this->getModel('Event', 'JEMModel');

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
	 * for attachment downloads
	 *
	 */
	function getfile()
	{
		$id = JRequest::getInt('file');

		$gid = JEMHelper::getGID();

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
		$id	 = JRequest::getVar( 'id', 0, 'request', 'int' );

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



	/**
	 * Exporttask
	 * view: attendees
	 */
	function attendeeexport()
	{
		$app = JFactory::getApplication();

		$model = $this->getModel('attendees');

		$datas = $model->getData();

		header('Content-Type: text/x-csv');
		header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Content-Disposition: attachment; filename=attendees.csv');
		header('Pragma: no-cache');

		$export = '';
		$col = array();

		for($i=0; $i < count($datas); $i++)
		{
			$data = $datas[$i];

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
		}

		echo $export;

		$app->close();
	}


	/**
	 * toggletask
	 * view: attendees
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
			JPluginHelper::importPlugin( 'jem' );
			$dispatcher = JDispatcher::getInstance();
			$res = $dispatcher->trigger( 'onUserOnOffWaitinglist', array( $id ) );

			if ($attendee->waiting)
			{
				$msg = JText::_('COM_JEM_ADDED_TO_ATTENDING');
			}
			else
			{
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

	/**
	 * removetask
	 * view=attendees
	 */
	function attendeeremove()
	{


		$cid = JRequest::getVar( 'cid', array(0), 'post', 'array' );
		$id 	= JRequest::getInt('id');
		$fid = JRequest::getInt('Itemid');
		$total 	= count( $cid );

		$model = $this->getModel('attendees');

		if (!is_array( $cid ) || count( $cid ) < 1) {
			JError::raiseError(500, JText::_('COM_JEM_SELECT_ITEM_TO_DELETE'));
		}

		if(!$model->remove($cid)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		$cache = JFactory::getCache('com_jem');
		$cache->clean();

		$msg = $total.' '.JText::_( 'COM_JEM_REGISTERED_USERS_DELETED');

		$this->setRedirect( 'index.php?option=com_jem&view=attendees&id='.$id.'&Itemid='.$fid, $msg );

	}





}
?>