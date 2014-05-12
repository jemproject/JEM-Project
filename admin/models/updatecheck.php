<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

jimport('joomla.application.component.model');


/**
 * Updatecheck-Model
 */
class JemModelUpdatecheck extends JModelLegacy
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
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Retrieval of update-data
	 */
	function getUpdatedata()
	{
		$installedversion	= self::getParam('version');
		$updateFile			= "http://www.joomlaeventmanager.net/updatecheck/update.xml";
		$checkFile			= self::CheckFile($updateFile);
		$updatedata 		= new stdClass();

		if ($checkFile) {
			$xml = simplexml_load_file($updateFile);

			//version to check, not visible in table
			$updatedata->version 			= $xml->version;

			//in table
			$updatedata->versiondetail		= $xml->versiondetail;
			$updatedata->date				= JEMOutput::formatdate($xml->date);
			$updatedata->info 				= $xml->info;
			$updatedata->download 			= $xml->download;
			$updatedata->notes				= $xml->notes;
			$updatedata->changes 			= explode(';', $xml->changes);
			$updatedata->failed 			= 0;
			$updatedata->installedversion	= $installedversion;
			$updatedata->current			= version_compare($installedversion, $updatedata->version);
		} else {
			$updatedata->failed 			= 1;
			$updatedata->installedversion	= $installedversion;
		}
		
		return $updatedata;
	}


	/**
	 * Check to see if update-file exists
	 */
	function CheckFile($filename) {
		$ext =  JFile::getExt($filename);
		if ($ext == 'xml') {
			if(@file_get_contents($filename,0,null,0,1)){
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
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