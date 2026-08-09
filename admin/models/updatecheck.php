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
        $updatedata->updateurl        = $updateFile;
        $updatedata->joomlaversion    = JVERSION;
        $updatedata->phpversion       = PHP_VERSION;
        $updatedata->installeddate    = $this->getInstalledDate();
        $updatedata->manifestpath     = JPATH_COMPONENT_ADMINISTRATOR . '/jem.xml';
        $updatedata->localnotes       = $this->getInstalledNotes();
        $updatedata->localdate        = $updatedata->installeddate;
        $updatedata->stablechangelog  = 'https://www.joomlaeventmanager.net/project/changelog-jem-5';
        $updatedata->betachangelog    = 'https://www.joomlaeventmanager.net/project/changelog-jem/betas';

        $updateXml = self::fetchUpdateXml($updateFile);

        if ($updateXml !== false) {
            $xml = simplexml_load_string($updateXml);

            if ($xml !== false && isset($xml->update)) {
                $jversion = JVERSION;
                $selectedUpdate = null;
                $highestPlatformUpdate = null;
                $installedUpdate = null;

                foreach ($xml->update as $updatexml) {
                    if (version_compare($installedversion, (string) $updatexml->version) === 0) {
                        $installedUpdate = $updatexml;
                    }

                    $versionPattern = (string) $updatexml->targetplatform['version'];

                    if (
                        $highestPlatformUpdate === null
                        || $this->compareUpdatePlatform($updatexml, $highestPlatformUpdate) > 0
                    ) {
                        $highestPlatformUpdate = $updatexml;
                    }

                    if ($versionPattern !== '' && preg_match('/^' . str_replace('/', '\/', $versionPattern) . '/', $jversion) === 1) {
                        if ($selectedUpdate === null || version_compare((string) $updatexml->version, (string) $selectedUpdate->version, 'gt')) {
                            $selectedUpdate = $updatexml;
                        }
                    }
                }

                $selectedUpdate = $selectedUpdate ?: $highestPlatformUpdate;

                if ($selectedUpdate !== null) {
                    $this->assignUpdateData($updatedata, $selectedUpdate, $installedversion);

                    if ($installedUpdate !== null) {
                        $updatedata->localnotes = explode(';', (string) $installedUpdate->notes);
                        $updatedata->localdate  = JemOutput::formatdate($installedUpdate->date);
                    }
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
        $updatedata->stablechangelog  = isset($updatexml->stablechangelog)
            ? (string) $updatexml->stablechangelog
            : $updatedata->stablechangelog;
        $updatedata->betachangelog    = isset($updatexml->betachangelog)
            ? (string) $updatexml->betachangelog
            : $updatedata->betachangelog;
        $updatedata->download         = (string) $updatexml->downloads->downloadurl;
        $updatedata->targetplatform   = (string) $updatexml->targetplatform['version'];
        $updatedata->phpminimum       = $this->getPhpMinimum($updatexml);
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

    private function getInstalledDate()
    {
        try {
            $db = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select($db->quoteName('manifest_cache'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_jem'));

            $db->setQuery($query);
            $manifest = json_decode((string) $db->loadResult(), true);

            if (!empty($manifest['creationDate'])) {
                return JemOutput::formatdate($manifest['creationDate']);
            }
        } catch (Exception $e) {
            return '';
        }

        return '';
    }

    private function getInstalledNotes()
    {
        $manifestPath = JPATH_COMPONENT_ADMINISTRATOR . '/jem.xml';

        if (!File::exists($manifestPath)) {
            return array();
        }

        $manifest = @simplexml_load_file($manifestPath);

        if ($manifest === false || !isset($manifest->notes)) {
            return array();
        }

        return array_values(array_filter(array_map(
            'trim',
            explode(';', (string) $manifest->notes)
        )));
    }

    private function getPhpMinimum($updatexml)
    {
        foreach (array('php_minimum', 'phpminimum', 'php_minimum_version') as $property) {
            if (isset($updatexml->{$property}) && trim((string) $updatexml->{$property}) !== '') {
                return trim((string) $updatexml->{$property});
            }
        }

        return '';
    }

    private function compareUpdatePlatform($leftUpdate, $rightUpdate)
    {
        $leftPlatform  = $this->getPlatformVersionRank((string) $leftUpdate->targetplatform['version']);
        $rightPlatform = $this->getPlatformVersionRank((string) $rightUpdate->targetplatform['version']);
        $platformCompare = version_compare($leftPlatform, $rightPlatform);

        if ($platformCompare !== 0) {
            return $platformCompare;
        }

        return version_compare((string) $leftUpdate->version, (string) $rightUpdate->version);
    }

    private function getPlatformVersionRank($versionPattern)
    {
        if (preg_match_all('/\d+(?:\.\d+)*/', $versionPattern, $matches) === false || empty($matches[0])) {
            return '0';
        }

        $highest = '0';

        foreach ($matches[0] as $version) {
            if (version_compare($version, $highest, 'gt')) {
                $highest = $version;
            }
        }

        return $highest;
    }
}
?>
