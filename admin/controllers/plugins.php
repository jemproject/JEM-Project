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
 * JEM Component Plugins Controller
 *
 * @package JEM
 *
*/
class JemControllerPlugins extends JControllerLegacy
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Handles Plugin screen
	 *
	 * @access public
	 * @return void
	 */
	public function plugins()
	{
		$db = JFactory::getDBO();

		$query = $db->getQuery(true);
		$query->select(array('count(*)'));
		$query->from('#__extensions AS p');
		$query->where(array('p.name LIKE '.$db->quote("%jem%"), 'p.type = '.$db->quote("plugin")));

		$db->setQuery($query);

		$total = $db->loadResult();

		//any plugins installed? if not redirect to installation screen
		if ($total > 0){
			$link = 'index.php?option=com_plugins&filter_search=jem';
			$msg = "";
		} else {
			$link = 'index.php?option=com_installer';
			$msg = JText::_("COM_JEM_PLUGINS_NOPLUGINSINSTALLED");
		}
		$this->setRedirect($link, $msg);
	}
}
?>