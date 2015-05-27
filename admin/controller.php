<?php
/**
 * @version 2.1.4
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


/**
 * JEM Component Controller
 */
class JEMController extends JControllerLegacy
{
	/**
	 * @var		string	The default view.
	 *
	 */
	protected $default_view = 'main';


	public function __construct()
	{
		parent::__construct();
	}


	/**
	 * Display the view
	 *
	 */
	public function display($cachable = false, $urlparams = false)
	{
		// Load the submenu - but not on edit views.
		// if no view found then refert to main
		$jinput = JFactory::getApplication()->input;
		$view = $jinput->getCmd('view', 'main');
		// add all views you won't see the submenu / sidebar
		//  - on J! 2.5 param 'hidemainmenu' let's not show the submenu
		//    but on J! 3.x the submenu (sidebar) is shown with non-clickable entries.
		//    The alternative would be to move the addSubmenu call to all views the sidebar should be shown.
		static $views_without_submenu = array('attendee', 'category', 'event', 'group', 'source', 'venue');

		if (!in_array($view, $views_without_submenu)) {
			JEMHelperBackend::addSubmenu($view);
		}

		parent::display();
		return $this;
	}


	/**
	 * Delete attachment
	 *
	 * @return true on sucess
	 * @access private
	 *
	 * Views:
	 * event, venue
	 *
	 * Reference to the task is located in the attachments.js
	 *
	 */
	function ajaxattachremove()
	{
		$id = JFactory::getApplication()->input->request->getInt('id', 0);

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
}
?>