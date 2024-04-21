<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

/**
 * JEM Component Plugins Controller
 *
 * @package JEM
 *
*/
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

class JemControllerPlugins extends BaseController
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
		$db = Factory::getContainer()->get('DatabaseDriver');

		$query = $db->getQuery(true);
		$query->select(array('count(*)'));
		$query->from('#__extensions AS p');
		$query->where(array('p.name LIKE '.$db->quote("%jem%"), 'p.type = '.$db->quote("plugin")));

		$db->setQuery($query);

		$total = $db->loadResult();

		//any plugins installed? if not redirect to installation screen
		if ($total > 0){
			// $link = 'index.php?option=com_plugins&filter_search=jem';
			$link = 'index.php?option=com_plugins&filter[search]=jem';
			$msg = "";
		} else {
			$link = 'index.php?option=com_installer';
			$msg = Text::_("COM_JEM_PLUGINS_NOPLUGINSINSTALLED");
		}
		$this->setRedirect($link, $msg);
	}
}
?>
