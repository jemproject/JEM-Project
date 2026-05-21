<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Factory;
use Joomla\Filesystem\File;

/**
 * Model-Updatecheck
 */
class JemModelUpdatecheck extends BaseDatabaseModel
{
    protected $_updatedata = null;

    /**
     * Constructor
     */
    public function __construct($config = array(), $factory = null)
    {
        parent::__construct($config, $factory);
        
        // Set the dispatcher for Joomla 6 compatibility
        if (method_exists($this, 'setDispatcher')) {
            $this->setDispatcher(Factory::getApplication()->getDispatcher());
        }
    }

    /**
     * Retrieval of update-data
     */
    public function getUpdatedata()
    {
        $installedversion = JemHelper::getParam(1, 'version', 1, 'com_jem');
        $updateFile       = "https://www.joomlaeventmanager.net/updatecheck/update_pkg_jem.xml";
        $updatedata       = new stdClass();

        $updatedata->failed           = 0;
        $updatedata->installedversion = $installedversion;
        $updatedata->current          = null;

        $updateXml = self::fetchUpdateXml($updateFile);

        if ($updateXml !== false) {
            $xml = simplexml_load_string($updateXml);

            if ($xml !== false && isset($xml->update)) {
                $jversion = JVERSION;
                $selectedUpdate = null;
                $latestUpdate = null;

                foreach ($xml->update as $updatexml) {
                    if ($latestUpdate === null || version_compare((string) $updatexml->version, (string) $latestUpdate->version, 'gt')) {
                        $latestUpdate = $updatexml;
                    }

                    $versionPattern = (string) $updatexml->targetplatform['version'];

                    if ($versionPattern !== '' && preg_match('/^' . str_replace('/', '\/', $versionPattern) . '/', $jversion) === 1) {
                        if ($selectedUpdate === null || version_compare((string) $updatexml->version, (string) $selectedUpdate->version, 'gt')) {
                            $selectedUpdate = $updatexml;
                        }
                    }
                }

                $selectedUpdate = $selectedUpdate ?: $latestUpdate;

                if ($selectedUpdate !== null) {
                    $this->assignUpdateData($updatedata, $selectedUpdate, $installedversion);
                }
            } else {
                $updatedata->failed = 1;
            }
        } else {
            $updatedata->failed = 1;
        }

        return $updatedata;
    }

    /**
     * @param  stdClass          $updatedata
     * @param  SimpleXMLElement  $updatexml
     * @param  string            $installedversion
     * @return void
     */
    private function assignUpdateData($updatedata, $updatexml, $installedversion)
    {
        $version = (string) $updatexml->version;

        $updatedata->version          = $version;
        $updatedata->versiondetail    = $version;
        $updatedata->date             = JemOutput::formatdate($updatexml->date);
        $updatedata->info             = (string) $updatexml->infourl;
        $updatedata->download         = (string) $updatexml->downloads->downloadurl;
        $updatedata->notes            = explode(';', (string) $updatexml->notes);
        $updatedata->changes          = explode(';', (string) $updatexml->changes);
        $updatedata->failed           = 0;
        $updatedata->installedversion = $installedversion;
        $updatedata->current          = version_compare($installedversion, $version);
    }

    protected static function fetchUpdateXml($filename)
    {
        $ext =  File::getExt($filename);
        if ($ext != 'xml') {
            return false;
        }

        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 5,
            ),
            'ssl' => array(
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ),
        ));

        $contents = @file_get_contents($filename, false, $context);

        return ($contents === false || trim($contents) === '') ? false : $contents;
    }
}
?>
