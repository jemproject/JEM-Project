<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
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
class JemController extends JControllerLegacy
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
	public function display($cachable = false, $urlparams = false)
	{
		$document   = JFactory::getDocument();
		$user       = JemFactory::getUser();

		// Set the default view name and format from the Request.
		$jinput     = JFactory::getApplication()->input;
		$id         = $jinput->getInt('a_id', 0);
		$viewName   = $jinput->getCmd('view', 'eventslist');
		$viewFormat = $document->getType();
		$layoutName = $jinput->getCmd('layout', 'edit');

		// Check for edit form.
		if ($viewName == 'editevent' && !$this->checkEditId('com_jem.edit.event', $id)) {
			// Somehow the person just went to the form - we don't allow that.
			return JError::raiseError(403, JText::sprintf('JLIB_APPLICATION_ERROR_UNHELD_ID', $id));
		}

		$view = $this->getView($viewName, $viewFormat);
		if ($view) {
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
	 */
	public function getfile()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit('Invalid Token');

		$id = JFactory::getApplication()->input->getInt('file', 0);

		$path = JemAttachment::getAttachmentPath($id);

		$mime = JemHelper::getMimeType($path);

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
	 * @access public
	 */
	public function ajaxattachremove()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit('Invalid Token');

		$jemsettings = JemHelper::config();
		$res = 0;

		if ($jemsettings->attachmentenabled > 0) {
			$id	 = JFactory::getApplication()->input->getInt('id', 0);
			$res = JemAttachment::remove($id);
		} // else don't delete anything

		if (!$res) {
			echo 0; // The caller expects an answer!
			jexit();
		}

		$cache = JFactory::getCache('com_jem');
		$cache->clean();

		echo 1; // The caller expects an answer!
		jexit();
	}

	/**
	 * Remove image
	 * @deprecated since version 1.9.7
	 */
	public function ajaximageremove()
	{
		// prevent unwanted usage
		jexit();
	}
}
?>