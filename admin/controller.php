<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * JEM Component Controller
 */
class JemController extends BaseController
{
	/**
	 * @var    string The default view.
	 */
	protected $default_view = 'main';


	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Display the view
	 */
	public function display($cachable = false, $urlparams = false)
	{
		// Load the submenu - but not on edit views.
		// if no view found then refert to main
		$jinput = Factory::getApplication()->input;
		$view = $jinput->getCmd('view', 'main');
		// add all views you won't see the submenu / sidebar
		//  - on J! 2.5 param 'hidemainmenu' let's not show the submenu
		//    but on J! 3.x the submenu (sidebar) is shown with non-clickable entries.
		//    The alternative would be to move the addSubmenu call to all views the sidebar should be shown.
		static $views_without_submenu = array('attendee', 'category', 'event', 'group', 'source', 'venue');

		if (!in_array($view, $views_without_submenu)) {
			JemHelperBackend::addSubmenu($view);
		}

		parent::display();
		return $this;
	}

	/**
	 * Delete attachment
	 *
	 * Views: event, venue
	 * Reference to the task is located in the attachments.js
	 *
	 * @return true on sucess
	 * @access public
	 */
	public function ajaxattachremove()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit('Invalid Token');

		$id = Factory::getApplication()->input->request->getInt('id', 0);

		$res = JemAttachment::remove($id);
		if (!$res) {
			echo 0;
			jexit();
		}

		$cache = Factory::getCache('com_jem');
		$cache->clean();

		echo 1;
		jexit();
	}
}
?>
