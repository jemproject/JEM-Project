<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Filesystem\File;


/**
 * Model-Updatecheck
 */
class JemModelUpdatecheck extends BaseDatabaseModel
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
		$updateFile       = "https://www.joomlaeventmanager.net/updatecheck/update_pkg_jem.xml";
		$checkFile        = self::CheckFile($updateFile);
		$updatedata       = new stdClass();

		if ($checkFile) {
			$xml = simplexml_load_string(file_get_contents($updateFile));
			$jversion = JVERSION;
			foreach($xml->update as $updatexml) {
				$version = $updatexml->targetplatform["version"]->__toString();
				if (preg_match('/^' . $version . '/', $jversion)) {
					//version to check, not visible in table
					$updatedata->version = $updatexml->version;

					//in table
					$updatedata->versiondetail    = $updatexml->version;
					$updatedata->date             = JemOutput::formatdate($updatexml->date);
					$updatedata->info             = $updatexml->infourl;
					$updatedata->download         = $updatexml->downloads->downloadurl;
					$updatedata->notes            = $updatexml->notes;
					$updatedata->changes          = explode(';', $updatexml->changes);
					$updatedata->failed           = 0;
					$updatedata->installedversion = $installedversion;
					$updatedata->current          = version_compare($installedversion, $updatedata->version);
				}
			}
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
		$ext =  File::getExt($filename);
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
