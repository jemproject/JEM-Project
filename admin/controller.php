<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
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
		// Load the submenu.
		// if no view found then refert to main

		JEMHelperBackend::addSubmenu(JRequest::getCmd('view', 'main'));

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
		$id = JRequest::getVar('id', 0, 'request', 'int');

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