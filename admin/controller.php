<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
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


	function __construct()
	{
		parent::__construct();
	}


	/**
	 * Display the view
	 *
	 */
	public function display($cachable = false, $urlparams = false)
	{
		require_once JPATH_COMPONENT.'/helpers/helper.php';

		// Load the submenu.
		// if no view found then refert to jem

		JEMHelperBackend::addSubmenu(JRequest::getCmd('view', 'main'));

		parent::display();
		return $this;
	}


	/**
	 * @todo check code
	 * Function to clear recurrences, not used
	 */
	function clearrecurrences()
	{
		$model = $this->getModel('events');
		$model->clearrecurrences();
		$this->setRedirect('index,php?option=com_jem', Jtext::_('COM_JEM_RECURRENCES_CLEARED'));
	}

	/**
	 * Delete attachment
	 *
	 * @return true on sucess
	 * @access private
	 *
	 * Views:
	 * category, event, venue
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