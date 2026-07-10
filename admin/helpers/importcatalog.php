<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Http\HttpFactory;

/**
 * Helper for the remote JEM import catalog XML.
 */
class JemImportCatalogHelper
{
    const CATALOG_FILE = 'import_catalog_jem.xml';
    const CATALOG_URL = 'https://www.joomlaeventmanager.net/updatecheck/import_catalog_jem.xml';

    protected static $catalog = null;

    public static function getCatalogPath()
    {
        return JPATH_ROOT . '/' . self::CATALOG_FILE;
    }

    public static function getCatalogSource()
    {
        return self::CATALOG_URL;
    }

    public static function getStatus()
    {
        $catalog = self::getCatalog();

        return array(
            'available' => (bool) $catalog['available'],
            'source' => (string) $catalog['source'],
            'version' => (string) $catalog['version'],
            'published' => (string) $catalog['published'],
            'error' => (string) $catalog['error'],
        );
    }

    public static function getEntries()
    {
        $catalog = self::getCatalog();

        return $catalog['entries'];
    }

    protected static function getCatalog()
    {
        if (self::$catalog !== null) {
            return self::$catalog;
        }

        self::$catalog = array(
            'available' => false,
            'source' => self::getCatalogSource(),
            'version' => '',
            'published' => '',
            'error' => '',
            'entries' => array(),
        );

        $xmlSource = self::downloadCatalogXml(self::getCatalogSource());

        if ($xmlSource === '') {
            self::$catalog['error'] = 'download';

            return self::$catalog;
        }

        self::parseCatalogXml($xmlSource);

        return self::$catalog;
    }

    protected static function downloadCatalogXml($url)
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        try {
            if (class_exists(HttpFactory::class)) {
                $http = HttpFactory::getHttp();
                $response = $http->get($url, array(), 10);

                if ((int) $response->code >= 200 && (int) $response->code < 300) {
                    return (string) $response->body;
                }
            }
        } catch (\Throwable $e) {
            // Fall through to the stream fallback below.
        }

        if (!ini_get('allow_url_fopen')) {
            return '';
        }

        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 10,
                'user_agent' => 'JEM import catalog',
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
            ),
        ));

        $data = @file_get_contents($url, false, $context);

        return is_string($data) ? $data : '';
    }

    protected static function parseCatalogXml($xmlSource)
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string((string) $xmlSource);

        if (!$xml) {
            self::$catalog['error'] = 'parse';
            libxml_clear_errors();

            return;
        }

        $entries = array();

        foreach ($xml->entry as $node) {
            $entry = self::normaliseEntry($node);

            if ($entry['id'] !== '') {
                $entries[] = $entry;
            }
        }

        self::$catalog['available'] = true;
        self::$catalog['version'] = trim((string) $xml['version']);
        self::$catalog['published'] = trim((string) $xml['published']);
        self::$catalog['entries'] = $entries;
        libxml_clear_errors();
    }

    public static function getEntry($id)
    {
        foreach (self::getEntries() as $entry) {
            if ($entry['id'] === (string) $id) {
                return $entry;
            }
        }

        return null;
    }

    public static function getCountries(array $entries)
    {
        $countries = array();

        foreach ($entries as $entry) {
            $code = (string) ($entry['country'] ?? '');

            if ($code === '') {
                continue;
            }

            $countries[$code] = (string) ($entry['country_name'] ?? $code);
        }

        ksort($countries);

        return $countries;
    }

    public static function getCounties(array $entries)
    {
        $counties = array();

        foreach ($entries as $entry) {
            $value = (string) ($entry['county'] ?? '');

            if ($value !== '') {
                $counties[$value] = $value;
            }
        }

        ksort($counties);

        return $counties;
    }

    public static function getCities(array $entries)
    {
        $cities = array();

        foreach ($entries as $entry) {
            $value = (string) ($entry['city'] ?? '');

            if ($value !== '') {
                $cities[$value] = $value;
            }
        }

        ksort($cities);

        return $cities;
    }

    public static function getContext($type)
    {
        $type = strtolower((string) $type);

        if ($type === 'venues') {
            return 'venues';
        }

        if ($type === 'specialdays' || $type === 'special-days') {
            return 'specialdays';
        }

        return 'events';
    }

    public static function getTab($type)
    {
        $context = self::getContext($type);

        if ($context === 'venues') {
            return 'venue-import';
        }

        if ($context === 'specialdays') {
            return 'special-days';
        }

        return 'event-import';
    }

    protected static function normaliseEntry($node)
    {
        $mapping = array();

        if (isset($node->mapping)) {
            foreach ($node->mapping->field as $field) {
                $source = trim((string) $field['source']);
                $target = trim((string) $field['target']);

                if ($source !== '') {
                    $mapping[$source] = $target;
                }
            }
        }

        $defaults = array();

        if (isset($node->defaults)) {
            foreach ($node->defaults->default as $default) {
                $name = trim((string) $default['name']);

                if ($name !== '') {
                    $defaults[$name] = trim((string) $default['value']);
                }
            }
        }

        return array(
            'id' => trim((string) $node['id']),
            'country' => strtoupper(trim((string) $node['country'])),
            'country_name' => trim((string) $node['country_name']),
            'county' => trim((string) $node['county']),
            'city' => trim((string) $node['city']),
            'type' => strtolower(trim((string) $node['type'])),
            'format' => strtolower(trim((string) $node['format'])),
            'profile' => trim((string) $node['profile']),
            'title' => trim((string) $node->title),
            'description' => trim((string) $node->description),
            'source' => trim((string) $node->source['url']),
            'provider' => trim((string) $node->source['provider']),
            'license' => trim((string) $node->license),
            'category_rule' => trim((string) $node->category['rule']),
            'updated' => trim((string) $node->updated),
            'mapping' => $mapping,
            'defaults' => $defaults,
        );
    }
}
