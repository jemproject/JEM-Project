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
 * JEM Component Controller
 *
 * @package JEM
 *
 */
class JEMController extends JControllerLegacy
{
	/**
	 * Constructor
	 */
	function __construct()
	{
		parent::__construct();
	}

	/**
	 * Display the view
	 */
	function display($cachable = false, $urlparams = false)
	{
		$document	= JFactory::getDocument();

		// Set the default view name and format from the Request.
		$viewName 		= JRequest::getCmd('view', 'eventslist');
		$viewFormat 	= $document->getType();
		$layoutName 	= JRequest::getCmd('layout', 'default');

		if ($view = $this->getView($viewName, $viewFormat)) {
			// Do any specific processing by view.
			switch ($viewName) {
				case 'attendees':
				case 'calendar':
				case 'categories':
				case 'categoriesdetailed':
				case 'category':
				case 'day':
				case 'editevent':
				case 'editvenue':
				case 'event':
				case 'eventslist':
				case 'myattendances':
				case 'myevents':
				case 'myvenues':
				case 'search':
				case 'venue':
				case 'venues':
				case 'weekcal':
					$model = $this->getModel($viewName);
					break;
				default:
					$model = $this->getModel('eventslist');
					break;
			}

			// Push the model into the view
			if ($viewName == 'venue') {
				$model1 = $this->getModel('Venue');
				$model2 = $this->getModel('VenueCal');

				$view->setModel($model1, true);
				$view->setModel($model2);
			} elseif($viewName == 'category') {
				$model1 = $this->getModel('Category');
				$model2 = $this->getModel('CategoryCal');

				$view->setModel($model1, true);
				$view->setModel($model2);
			} else {
				$view->setModel($model, true);
			}

			$view->setLayout($layoutName);

			// Push document object into the view.
			$view->document = $document;

			$view->display();
		}
	}



	/**
	 * Logic for canceling an event and proceed to add a venue
	 *
	 *
	 */
	function unpublishtask()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit('Invalid Token');

		$app = JFactory::getApplication();
		$input = $app->input;

		$cid = $input->get('cid', array(0), 'post', 'array');

		$false = array_search('0', $cid);

		if ($false === 0) {
			JError::raiseNotice(100, JText::_('COM_JEM_SELECT_ITEM_TO_UNPUBLISH'));
			$this->setRedirect(JEMHelperRoute::getMyEventsRoute());
			return;
		}

		$model = $this->getModel('myevents');
		if(!$model->publish($cid, 0)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		$total = count($cid);
		$msg 	= $total.' '.JText::_('COM_JEM_EVENT_UNPUBLISHED');

		$this->setRedirect(JEMHelperRoute::getMyEventsRoute(), $msg);
	}

	/**
	 * Logic for canceling an event and proceed to add a venue
	 *
	 *
	 */
	function unpublish()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit('Invalid Token');

		$app = JFactory::getApplication();
		$input = $app->input;

		$cid = $input->get('cid', array(0), 'post', 'array');

		$false = array_search('0', $cid);

		if ($false === 0) {
			JError::raiseNotice(100, JText::_('COM_JEM_SELECT_ITEM_TO_UNPUBLISH'));
			$this->setRedirect(JEMHelperRoute::getMyEventsRoute());
			return;
		}

		$model = $this->getModel('myevents');
		if(!$model->publish($cid, 0)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		$total = count($cid);
		$msg 	= $total.' '.JText::_('COM_JEM_EVENT_UNPUBLISHED');

		$this->setRedirect(JEMHelperRoute::getMyEventsRoute(), $msg);
	}

	/**
	 * Logic to publish events
	 *
	 * @access public
	 * @return void
	 *
	 */
	function publish()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit('Invalid Token');

		$app = JFactory::getApplication();
		$input = $app->input;

		$cid = $input->get('cid', array(0), 'post', 'array');

		$false = array_search('0', $cid);

		if ($false === 0) {
			JError::raiseNotice(100, JText::_('COM_JEM_SELECT_ITEM_TO_PUBLISH'));
			$this->setRedirect(JEMHelperRoute::getMyEventsRoute());
			return;
		}

		$model = $this->getModel('myevents');
		if(!$model->publish($cid, 1)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		$total = count($cid);
		$msg 	= $total.' '.JText::_('COM_JEM_EVENT_PUBLISHED');

		$this->setRedirect(JEMHelperRoute::getMyEventsRoute(), $msg);
	}

	/**
	 * Logic to trash events
	 *
	 * @access public
	 * @return void
	 */
	function trash()
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit('Invalid Token');

		$app = JFactory::getApplication();
		$input = $app->input;

		$cid = $input->get('cid', array(0), 'post', 'array');

		$false = array_search('0', $cid);

		if ($false === 0) {
			JError::raiseNotice(100, JText::_('COM_JEM_SELECT_ITEM_TO_TRASH'));
			$this->setRedirect(JEMHelperRoute::getMyEventsRoute());
			return;
		}

		$model = $this->getModel('myevents');
		if(!$model->publish($cid, -2)) {
			echo "<script> alert('".$model->getError()."'); window.history.go(-1); </script>\n";
		}

		$total = count($cid);
		$msg 	= $total.' '.JText::_('COM_JEM_EVENT_TRASHED');

		$this->setRedirect(JEMHelperRoute::getMyEventsRoute(), $msg);
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
	 *
	 */
	function ajaxattachremove()
	{
		$id	 = JRequest::getVar('id', 0, 'request', 'int');

		$res = JEMAttachment::remove($id);
		if (!$res) {
			echo 0;
			jexit();
		}

		$cache = JFactory::getCache('com_jem');
		$cache->clean();

		echo 1;
		jexit();
	}

	/**
	 * Exporttask
	 * view: attendees
	 */
	function attendeeexport()
	{
		$app = JFactory::getApplication();

		$jinput = JFactory::getApplication()->input;
		$enableemailadress = $jinput->get('em','','int');


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

			$col[] = str_replace("\"", "\"\"", $data->username);
			if ($enableemailadress == 1)
			{
			$col[] = str_replace("\"", "\"\"", $data->email);
			}
			$col[] = str_replace("\"", "\"\"", JHtml::_('date',$data->uregdate, JText::_('DATE_FORMAT_LC2')));
			
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

	


}
?>