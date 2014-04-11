<?php
/**
 * @version 1.9.6
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
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
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Display the view
	 */
	function display($cachable = false, $urlparams = false)
	{
		$document	= JFactory::getDocument();
		$user = JFactory::getUser();

		// Set the default view name and format from the Request.
		$id				= JRequest::getInt('a_id');
		$viewName 		= JRequest::getCmd('view', 'eventslist');
		$viewFormat 	= $document->getType();
		$layoutName 	= JRequest::getCmd('layout', 'edit');

		
		// Check for edit form.
		if ($viewName == 'editevent' && !$this->checkEditId('com_jem.edit.event', $id)) {
			// Somehow the person just went to the form - we don't allow that.
			return JError::raiseError(403, JText::sprintf('JLIB_APPLICATION_ERROR_UNHELD_ID', $id));
		}
		

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
	 * for attachment downloads
	 *
	 */
	function getfile()
	{
		$id = JRequest::getInt('file');

		$path = JEMAttachment::getAttachmentPath($id);

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

	function ajaximageremove()
	{
		$id = JRequest::getVar('id', null, 'request', 'int');
		if (!$id) {
			echo 0;
			jexit();
		}
		$folder = JRequest::getVar('type', null, 'request', 'string');

		if ($folder == 'events') {
			$getquery = ' SELECT datimage FROM #__jem_events WHERE id = '.(int)$id;
			$updatequery = ' UPDATE #__jem_events SET datimage=\'\' WHERE id = '.(int)$id;
		} else if ($folder == 'venues') {
			$getquery = ' SELECT locimage FROM #__jem_venues WHERE id = '.(int)$id;
			$updatequery = ' UPDATE #__jem_venues SET locimage=\'\' WHERE id = '.(int)$id;
		} else {
			echo 0;
			jexit();
		}

		$db = JFactory::getDBO();
		$db->setQuery($getquery);
		if (!$image_obj = $db->loadObject()) {
			echo 0;
			jexit();
		}

		if ($folder == 'events') {
			$image = $image_obj->datimage;
		} else if ($folder == 'venues') {
			$image = $image_obj->locimage;
		}

		$fullPath = JPath::clean(JPATH_SITE.'/images/jem/'.$folder.'/'.$image);
		$fullPaththumb = JPath::clean(JPATH_SITE.'/images/jem/'.$folder.'/small/'.$image);
		if (is_file($fullPath)) {
			$db->setQuery($updatequery);
			if (!$db->query()) {
				echo 0;
				jexit();
			}
			
			JFile::delete($fullPath);
			if (JFile::exists($fullPaththumb)) {
				JFile::delete($fullPaththumb);
			}
		}

		echo 1;
		jexit();
	}
}
?>