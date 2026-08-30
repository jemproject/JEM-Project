<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/remotesource.php';

/**
 * Reader and validator for the official JEM language package catalog.
 */
class JemLanguageCatalogHelper
{
    const CATALOG_FILE = 'language_catalog_jem.xml';
    const CATALOG_URL = 'https://www.joomlaeventmanager.net/updatecheck/language_catalog_jem.xml';
    const LOCAL_CATALOG_DIRECTORY = 'media/com_jem/languages';
    const MAX_CATALOG_SIZE = 1048576;

    protected static $catalog = null;

    /**
     * Return public catalog metadata for the administrator view.
     */
    public static function getStatus()
    {
        $catalog = self::getCatalog();

        return array(
            'available' => (bool) $catalog['available'],
            'source' => (string) $catalog['source'],
            'version' => (string) $catalog['version'],
            'published' => (string) $catalog['published'],
            'compatibility' => (string) $catalog['compatibility'],
            'error' => (string) $catalog['error'],
            'is_local' => (bool) $catalog['is_local'],
        );
    }

    /**
     * Return the validated language records indexed by language tag.
     */
    public static function getLanguages()
    {
        return self::getCatalog()['languages'];
    }

    public static function getLocalCatalogPath()
    {
        return JPATH_ROOT . '/' . self::LOCAL_CATALOG_DIRECTORY . '/' . self::CATALOG_FILE;
    }

    public static function getLocalCatalogSource()
    {
        return self::LOCAL_CATALOG_DIRECTORY . '/' . self::CATALOG_FILE;
    }

    public static function hasLocalCatalog()
    {
        $path = self::getLocalCatalogPath();

        return is_dir(dirname($path)) && is_file($path);
    }

    /**
     * Return one validated package from the current catalog.
     */
    public static function getPackage($packageId)
    {
        foreach (self::getLanguages() as $language) {
            foreach ($language['packages'] as $package) {
                if ($package['id'] === (string) $packageId) {
                    return $package;
                }
            }
        }

        return null;
    }

    /**
     * Validate a catalog without performing any network request.
     */
    public static function validateCatalogXml($xmlSource, &$error = '')
    {
        $xml = self::loadCatalogXml($xmlSource, $error);

        if (!$xml) {
            return false;
        }

        return self::normaliseLanguages($xml, $error) !== null;
    }

    /**
     * Return the major.minor compatibility branch for a JEM version.
     */
    public static function getVersionBranch($version)
    {
        return preg_match('/^(\d+)\.(\d+)/', trim((string) $version), $matches) === 1
            ? $matches[1] . '.' . $matches[2]
            : '';
    }

    /**
     * Return the major compatibility line for a JEM version.
     */
    public static function getVersionMajor($version)
    {
        return preg_match('/^(\d+)/', trim((string) $version), $matches) === 1
            ? $matches[1]
            : '';
    }

    /**
     * Select the newest package compatible with a JEM major release.
     */
    public static function selectCompatiblePackage(array $packages, $jemVersion)
    {
        $major = self::getVersionMajor($jemVersion);
        $selected = null;

        foreach ($packages as $package) {
            if ($major === '' || self::getVersionMajor($package['jem'] ?? '') !== $major) {
                continue;
            }

            if ($selected === null || version_compare($package['version'], $selected['version'], '>')) {
                $selected = $package;
            }
        }

        return $selected;
    }

    /**
     * Derive the action state independently from the presentation layer.
     */
    public static function getPackageState($tag, $joomlaLanguageInstalled, $installedVersion, $package)
    {
        if ((string) $tag === 'en-GB') {
            return 'built_in';
        }

        if ($package === null) {
            return 'incompatible';
        }

        if (!$joomlaLanguageInstalled) {
            return 'joomla_required';
        }

        $installedVersion = trim((string) $installedVersion);

        if ($installedVersion === '') {
            return 'available';
        }

        return version_compare($package['version'], $installedVersion, '>') ? 'update' : 'installed';
    }

    /**
     * Put Joomla's default language first, followed by the other installed
     * languages and then the languages that are not installed.
     */
    public static function sortLanguageItems(array $items, $defaultTag)
    {
        $defaultTag = trim((string) $defaultTag);

        usort($items, static function ($left, $right) use ($defaultTag) {
            $leftDefault = (string) $left['tag'] === $defaultTag;
            $rightDefault = (string) $right['tag'] === $defaultTag;

            if ($leftDefault !== $rightDefault) {
                return $leftDefault ? -1 : 1;
            }

            $leftInstalled = !empty($left['joomla_installed']);
            $rightInstalled = !empty($right['joomla_installed']);

            if ($leftInstalled !== $rightInstalled) {
                return $leftInstalled ? -1 : 1;
            }

            $byName = strcasecmp((string) $left['name'], (string) $right['name']);

            return $byName !== 0
                ? $byName
                : strcasecmp((string) $left['tag'], (string) $right['tag']);
        });

        return $items;
    }

    /**
     * Verify that an unpacked Joomla file-extension manifest matches the
     * package metadata selected from the catalog.
     */
    public static function validatePackageManifestXml($source, array $package)
    {
        $source = (string) $source;

        if ($source === ''
            || strlen($source) > self::MAX_CATALOG_SIZE
            || preg_match('/<!DOCTYPE|<!ENTITY/i', $source)) {
            return false;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $manifest = simplexml_load_string(
                $source,
                'SimpleXMLElement',
                LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $manifest !== false
            && $manifest->getName() === 'extension'
            && trim((string) $manifest['type']) === 'file'
            && trim((string) $manifest->name) === (string) ($package['element'] ?? '')
            && trim((string) $manifest->tag) === (string) ($package['tag'] ?? '')
            && trim((string) $manifest->version) === (string) ($package['version'] ?? '');
    }

    protected static function getCatalog()
    {
        if (self::$catalog !== null) {
            return self::$catalog;
        }

        self::$catalog = array(
            'available' => false,
            'source' => self::CATALOG_URL,
            'version' => '',
            'published' => '',
            'compatibility' => '',
            'error' => '',
            'is_local' => false,
            'languages' => array(),
        );

        if (self::hasLocalCatalog()) {
            self::$catalog['source'] = self::getLocalCatalogSource();
            self::$catalog['is_local'] = true;
            $xmlSource = self::getLocalCatalogXml();
        } else {
            $xmlSource = self::downloadCatalogXml();
        }

        if ($xmlSource === '') {
            self::$catalog['error'] = 'download';

            return self::$catalog;
        }

        $error = '';
        $xml = self::loadCatalogXml($xmlSource, $error);

        if (!$xml) {
            self::$catalog['error'] = $error ?: 'parse';

            return self::$catalog;
        }

        $languages = self::normaliseLanguages($xml, $error);

        if ($languages === null) {
            self::$catalog['error'] = $error ?: 'invalid_catalog';

            return self::$catalog;
        }

        self::$catalog['available'] = true;
        self::$catalog['version'] = trim((string) $xml['version']);
        self::$catalog['published'] = trim((string) $xml['published']);
        self::$catalog['compatibility'] = trim((string) $xml['compatibility']);
        self::$catalog['languages'] = $languages;

        return self::$catalog;
    }

    protected static function getLocalCatalogXml()
    {
        $path = self::getLocalCatalogPath();
        $size = @filesize($path);

        if ($size === false || $size < 1 || $size > self::MAX_CATALOG_SIZE) {
            return '';
        }

        $source = @file_get_contents($path);

        return is_string($source) ? $source : '';
    }

    protected static function downloadCatalogXml()
    {
        try {
            $download = JemRemoteSourceHelper::download(
                self::CATALOG_URL,
                array('xml'),
                'xml',
                self::MAX_CATALOG_SIZE
            );

            return (string) $download['body'];
        } catch (\Throwable $exception) {
            return '';
        }
    }

    protected static function loadCatalogXml($xmlSource, &$error = '')
    {
        $xmlSource = (string) $xmlSource;
        $error = '';

        if ($xmlSource === '' || strlen($xmlSource) > self::MAX_CATALOG_SIZE) {
            $error = 'invalid_size';
            return null;
        }

        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xmlSource)) {
            $error = 'external_entities';
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string(
                $xmlSource,
                'SimpleXMLElement',
                LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($xml === false || $xml->getName() !== 'jem-language-catalog') {
            $error = 'parse';
            return null;
        }

        if (trim((string) $xml['version']) !== '1.0') {
            $error = 'unsupported_version';
            return null;
        }

        if (trim((string) $xml['compatibility']) !== 'major') {
            $error = 'unsupported_compatibility';
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $xml['published'])) !== 1) {
            $error = 'invalid_published_date';
            return null;
        }

        return $xml;
    }

    protected static function normaliseLanguages($xml, &$error = '')
    {
        $languages = array();
        $packageIds = array();

        foreach ($xml->language as $languageNode) {
            $tag = trim((string) $languageNode['tag']);
            $name = trim((string) $languageNode['name']);

            if (preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', $tag) !== 1 || $name === '') {
                $error = 'invalid_language';
                return null;
            }

            if (isset($languages[$tag])) {
                $error = 'duplicate_language';
                return null;
            }

            $packages = array();

            foreach ($languageNode->package as $packageNode) {
                $package = self::normalisePackage($packageNode, $tag, $error);

                if ($package === null) {
                    return null;
                }

                if (isset($packageIds[$package['id']])) {
                    $error = 'duplicate_package';
                    return null;
                }

                $packageIds[$package['id']] = true;
                $packages[] = $package;
            }

            if (!$packages) {
                $error = 'missing_package';
                return null;
            }

            usort($packages, static function ($left, $right) {
                return version_compare($right['version'], $left['version']);
            });

            $languages[$tag] = array(
                'tag' => $tag,
                'name' => $name,
                'packages' => $packages,
            );
        }

        if (!$languages) {
            $error = 'empty_catalog';
            return null;
        }

        uasort($languages, static function ($left, $right) {
            return strcasecmp($left['name'], $right['name']);
        });

        return $languages;
    }

    protected static function normalisePackage($node, $tag, &$error)
    {
        $id = trim((string) $node['id']);
        $element = trim((string) $node['element']);
        $type = trim((string) $node['type']);
        $version = trim((string) $node['version']);
        $jemBranch = trim((string) $node['jem']);
        $released = trim((string) $node['released']);
        $layout = trim((string) $node['language_layout']);
        $filename = trim((string) $node['filename']);
        $bytes = trim((string) $node['bytes']);
        $url = trim((string) $node->download['url']);
        $algorithm = strtolower(trim((string) $node->checksum['algorithm']));
        $checksum = strtolower(trim((string) $node->checksum));

        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/i', $id) !== 1
            || $element !== 'com_jem_' . $tag
            || $type !== 'file'
            || preg_match('/^\d+\.\d+\.\d+(?:\.\d+)*$/', $version) !== 1
            || preg_match('/^\d+\.\d+$/', $jemBranch) !== 1
            || strpos($version, $jemBranch . '.') !== 0
            || preg_match('/^\d{4}-\d{2}-\d{2}$/', $released) !== 1
            || !in_array($layout, array('extension-local', 'global'), true)
            || $filename === ''
            || !ctype_digit($bytes)
            || (int) $bytes < 1
            || $algorithm !== 'sha256'
            || preg_match('/^[a-f0-9]{64}$/', $checksum) !== 1
            || !self::isOfficialDownloadUrl($url)) {
            $error = 'invalid_package';
            return null;
        }

        return array(
            'id' => $id,
            'tag' => $tag,
            'element' => $element,
            'type' => $type,
            'version' => $version,
            'jem' => $jemBranch,
            'released' => $released,
            'language_layout' => $layout,
            'filename' => $filename,
            'bytes' => (int) $bytes,
            'url' => $url,
            'checksum' => $checksum,
        );
    }

    protected static function isOfficialDownloadUrl($url)
    {
        $parts = parse_url((string) $url);

        if (!is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https'
            || strtolower((string) ($parts['host'] ?? '')) !== 'www.joomlaeventmanager.net'
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])
            || isset($parts['fragment'])) {
            return false;
        }

        $path = (string) ($parts['path'] ?? '');
        $query = (string) ($parts['query'] ?? '');

        if ($path === '/download') {
            return preg_match('/^download=\d+%3A[a-z0-9-]+$/', $query) === 1;
        }

        return $query === '' && preg_match('#^/[a-z0-9/_-]+\.zip$#i', $path) === 1;
    }
}
