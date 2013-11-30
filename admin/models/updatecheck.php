<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Updatecheck Model
 *
 * @package JEM
 *
 */
class JEMModelUpdatecheck extends JModelLegacy
{
	/**
	 * Events data in array
	 *
	 * @var array
	 */
	var $_updatedata = null;

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();
	}

	/**
	 * Logic for the Update Check
	 *
	 * @access public
	 * @return object
	 *
	 */
	function getUpdatedata()
	{
		$installedversion = self::getParam('version');

		include_once(JPATH_COMPONENT_ADMINISTRATOR.'/classes/Snoopy.class.php');

		$snoopy = new Snoopy();

		//set the source file
		$file = 'http://www.joomlaeventmanager.net/updatecheck/update.csv';

		$snoopy->read_timeout 	= 30;
		$snoopy->agent 			= "Mozilla/5.0 (compatible; Konqueror/3.2; Linux 2.6.2) (KHTML, like Gecko)";

		$snoopy->fetch($file);

		$_updatedata = null;

		if ($snoopy->status != 200 || $snoopy->error) {
			$_updatedata = new stdClass();
			$_updatedata->failed = 1;
		} else {
			$data = explode('|', $snoopy->results);

			$_updatedata = new stdClass();

			/* version to check, not visible in table */
			$_updatedata->version 		= $data[0];

			/* in table */
			$_updatedata->versiondetail	= $data[1];
			$_updatedata->date			= JEMOutput::formatdate($data[2]);
			$_updatedata->info 			= $data[3];
			$_updatedata->download 		= $data[4];
			$_updatedata->notes			= $data[5];
			$_updatedata->changes 		= explode(';', $data[6]);
			$_updatedata->failed 		= 0;
			$_updatedata->installedversion 		= $installedversion;

			$_updatedata->current = version_compare($installedversion, $_updatedata->version);
		}

		return $_updatedata;
	}


	/**
	 * get a variable from the manifest file (actually, from the manifest cache).
	 * in this case it will be the installed version of jem
	 */
	function getParam($name) {
		$db = JFactory::getDbo();

		$query = $db->getQuery(true);
		$query->select(array('manifest_cache'));
		$query->from('#__extensions');
		$query->where(array('name = '.$db->quote('com_jem')));
		$db->setQuery($query);

		$manifest = json_decode($db->loadResult(), true);
		return $manifest[ $name ];
	}
}
?>