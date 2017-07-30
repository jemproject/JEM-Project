<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

jimport('joomla.application.component.model');
jimport('joomla.filesystem.file');


/**
 * Model-Updatecheck
 */
class JemModelUpdatecheck extends JModelLegacy
{
	protected $_updatedata = null;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Retrieval of update-data
	 */
	public function getUpdatedata()
	{
		$installedversion = JemHelper::getParam(1, 'version', 1, 'com_jem');
		$updateFile       = "http://www.joomlaeventmanager.net/updatecheck/update.xml";
		$checkFile        = self::CheckFile($updateFile);
		$updatedata       = new stdClass();

		if ($checkFile) {
			$xml = simplexml_load_file($updateFile);

			//version to check, not visible in table
			$updatedata->version          = $xml->version;

			//in table
			$updatedata->versiondetail    = $xml->versiondetail;
			$updatedata->date             = JemOutput::formatdate($xml->date);
			$updatedata->info             = $xml->info;
			$updatedata->download         = $xml->download;
			$updatedata->notes            = $xml->notes;
			$updatedata->changes          = explode(';', $xml->changes);
			$updatedata->failed           = 0;
			$updatedata->installedversion = $installedversion;
			$updatedata->current          = version_compare($installedversion, $updatedata->version);
		} else {
			$updatedata->failed           = 1;
			$updatedata->installedversion = $installedversion;
		}

		return $updatedata;
	}

	/**
	 * Check to see if update-file exists
	 */
	protected static function CheckFile($filename)
	{
		$ext =  JFile::getExt($filename);
		if ($ext == 'xml') {
			if (@file_get_contents($filename, 0, null, 0, 1)) {
				return true;
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
}
?>