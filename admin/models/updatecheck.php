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
    const UPDATE_FILE = 'update_pkg_jem.xml';
    const UPDATE_URL = 'https://www.joomlaeventmanager.net/updatecheck/update_pkg_jem.xml';
    const LOCAL_UPDATE_DIRECTORY = 'media/com_jem/update';
    const MAX_UPDATE_SIZE = 1048576;

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
        $localUpdate      = self::hasLocalUpdateXml();
        $updateFile       = $localUpdate ? self::getLocalUpdatePath() : self::UPDATE_URL;
        $updateSource     = $localUpdate ? self::getLocalUpdateSource() : self::UPDATE_URL;
        $updatedata       = new stdClass();

        $updatedata->failed           = 0;
        $updatedata->installedversion = $installedversion;
        $updatedata->current          = null;
        $updatedata->updateurl        = $updateSource;
        $updatedata->islocalupdate    = $localUpdate;
        $updatedata->xmlversion       = '';
        $updatedata->xmlpublished     = '';
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
            $xml = self::loadUpdateXml($updateXml);

            if ($xml !== false && isset($xml->update)) {
                $updatedata->xmlversion = trim((string) $xml['version']);
                $updatedata->xmlpublished = trim((string) $xml['published']);
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

        if (is_file($filename)) {
            $size = @filesize($filename);

            if ($size === false || $size < 1 || $size > self::MAX_UPDATE_SIZE) {
                return false;
            }

            $contents = @file_get_contents($filename);

            return ($contents === false || trim($contents) === '') ? false : $contents;
        }

        if (!hash_equals(self::UPDATE_URL, (string) $filename)) {
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

        return ($contents === false
            || trim($contents) === ''
            || strlen($contents) > self::MAX_UPDATE_SIZE)
            ? false
            : $contents;
    }

    protected static function loadUpdateXml($source)
    {
        $source = (string) $source;

        if ($source === ''
            || strlen($source) > self::MAX_UPDATE_SIZE
            || preg_match('/<!DOCTYPE|<!ENTITY/i', $source)) {
            return false;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string(
                $source,
                'SimpleXMLElement',
                LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($xml === false
            || $xml->getName() !== 'updates'
            || trim((string) $xml['version']) !== '1.0'
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $xml['published'])) !== 1) {
            return false;
        }

        foreach ($xml->update as $update) {
            $downloadUrl = trim((string) $update->downloads->downloadurl);
            $platform = trim((string) $update->targetplatform['version']);

            if (trim((string) $update->element) !== 'pkg_jem'
                || trim((string) $update->type) !== 'package'
                || trim((string) $update->version) === ''
                || trim((string) $update->targetplatform['name']) !== 'joomla'
                || $platform === ''
                || strlen($platform) > 128
                || @preg_match('/^' . str_replace('/', '\\/', $platform) . '/', JVERSION) === false
                || !self::isHttpsUrl($downloadUrl)) {
                return false;
            }

            foreach (array('infourl', 'stablechangelog', 'betachangelog') as $urlNode) {
                $url = trim((string) $update->{$urlNode});

                if ($url !== '' && !self::isHttpsUrl($url)) {
                    return false;
                }
            }
        }

        if (!isset($xml->update)) {
            return false;
        }

        return $xml;
    }

    protected static function isHttpsUrl($url)
    {
        $parts = parse_url((string) $url);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && !empty($parts['host']);
    }

    public static function getLocalUpdatePath()
    {
        return JPATH_ROOT . '/' . self::LOCAL_UPDATE_DIRECTORY . '/' . self::UPDATE_FILE;
    }

    public static function getLocalUpdateSource()
    {
        return self::LOCAL_UPDATE_DIRECTORY . '/' . self::UPDATE_FILE;
    }

    public static function hasLocalUpdateXml()
    {
        $path = self::getLocalUpdatePath();

        return is_dir(dirname($path)) && is_file($path);
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
