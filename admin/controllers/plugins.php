<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * JEM Component Archive Controller
 *
 * @package JEM
 * 
*/
class JEMControllerPlugins extends JEMController
{
	/**
	 * Constructor
	 *
	 *
	 */
	function __construct()
	{
		parent::__construct();
	}

	/**
	 * Handles Plugin screen
	 *
	 * @access public
	 * @return void
	 * 
	 */
	function plugins()
	{
		$db = JFactory::getDBO();

		$query = 'SELECT COUNT(*)'
				. ' FROM #__extensions AS p'
				. ' WHERE p.name LIKE '.$db->Quote("%jem%")
				. ' AND p.type = '.$db->Quote("plugin");
				;
		$db->setQuery( $query );

		$total = $db->loadResult();

		//any plugins installed? if not redirect to installation screen
		if ($total > 0){
			$link = 'index.php?option=com_plugins&filter_search=jem';
			$msg = "";
		} else {
			$link = 'index.php?option=com_installer';
			$msg = JText::_("NO JEM PLUGINS INSTALLED");
		}
		$this->setRedirect($link, $msg);
	}

}
?>