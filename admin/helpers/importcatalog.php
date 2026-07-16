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
    const CUSTOM_CATALOG_DIRECTORY = 'media/com_jem/import';
    const MAX_CATALOG_SIZE = 1048576;

    protected static $catalog = null;

    public static function getCatalogPath()
    {
        return JPATH_ROOT . '/' . self::CUSTOM_CATALOG_DIRECTORY . '/' . self::CATALOG_FILE;
    }

    public static function getCustomCatalogSource()
    {
        return self::CUSTOM_CATALOG_DIRECTORY . '/' . self::CATALOG_FILE;
    }

    public static function hasCustomCatalog()
    {
        return is_file(self::getCatalogPath());
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
            'is_custom' => (bool) $catalog['is_custom'],
            'custom_file' => (bool) $catalog['custom_file'],
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
            'is_custom' => false,
            'custom_file' => self::hasCustomCatalog(),
        );

        if (self::$catalog['custom_file']) {
            $path = self::getCatalogPath();
            $size = @filesize($path);
            $xmlSource = $size !== false && $size <= self::MAX_CATALOG_SIZE
                ? @file_get_contents($path)
                : '';

            if (is_string($xmlSource) && $xmlSource !== '') {
                self::$catalog['source'] = self::getCustomCatalogSource();
                self::parseCatalogXml($xmlSource);

                if (self::$catalog['available']) {
                    self::$catalog['is_custom'] = true;
                    return self::$catalog;
                }
            }

            self::$catalog['available'] = false;
            self::$catalog['version'] = '';
            self::$catalog['published'] = '';
            self::$catalog['entries'] = array();
            self::$catalog['error'] = '';
            self::$catalog['source'] = self::getCatalogSource();
        }

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
        $error = '';
        $xml = self::loadCatalogXml($xmlSource, $error);

        if (!$xml) {
            self::$catalog['error'] = $error ?: 'parse';

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
    }

    public static function validateCatalogXml($xmlSource, &$error = '')
    {
        $xml = self::loadCatalogXml($xmlSource, $error);

        if (!$xml) {
            return false;
        }

        $ids = array();
        $allowedFormats = array(
            'events' => array('csv', 'json', 'xml', 'ics'),
            'venues' => array('csv', 'json', 'xml', 'xlsx'),
            'specialdays' => array('csv', 'json', 'xml', 'ics'),
            'special-days' => array('csv', 'json', 'xml', 'ics'),
        );

        foreach ($xml->entry as $node) {
            $id = trim((string) $node['id']);
            $type = strtolower(trim((string) $node['type']));
            $format = strtolower(trim((string) $node['format']));

            if ($id === '' || !preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/i', $id)) {
                $error = 'invalid_entry_id';
                return false;
            }

            if (isset($ids[$id])) {
                $error = 'duplicate_entry_id';
                return false;
            }

            if (trim((string) $node->title) === '' || trim((string) $node->source['url']) === '') {
                $error = 'missing_entry_data';
                return false;
            }

            if (!isset($allowedFormats[$type]) || !in_array($format, $allowedFormats[$type], true)) {
                $error = 'unsupported_entry';
                return false;
            }

            if (isset($node->items)) {
                $itemCount = trim((string) $node->items['count']);
                $itemCount = $itemCount !== '' ? $itemCount : trim((string) $node->items);

                if ($itemCount === '' || !ctype_digit($itemCount)) {
                    $error = 'invalid_item_count';
                    return false;
                }
            }

            $ids[$id] = true;
        }

        if (!$ids) {
            $error = 'empty_catalog';
            return false;
        }

        return true;
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
        $xml = simplexml_load_string($xmlSource, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$xml || $xml->getName() !== 'jem-import-catalog') {
            $error = 'parse';
            return null;
        }

        if (trim((string) $xml['version']) !== '1.0') {
            $error = 'unsupported_version';
            return null;
        }

        return $xml;
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

    public static function getTypes(array $entries)
    {
        $types = array();

        foreach ($entries as $entry) {
            $type = self::getContext($entry['type'] ?? '');
            $types[$type] = $type;
        }

        ksort($types);

        return $types;
    }

    public static function getFormats(array $entries)
    {
        $formats = array();

        foreach ($entries as $entry) {
            $format = strtolower(trim((string) ($entry['format'] ?? '')));

            if ($format !== '') {
                $formats[$format] = strtoupper($format);
            }
        }

        ksort($formats);

        return $formats;
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
        $itemCount = null;

        if (isset($node->items)) {
            $rawItemCount = trim((string) $node->items['count']);
            $rawItemCount = $rawItemCount !== '' ? $rawItemCount : trim((string) $node->items);
            $itemCount = ctype_digit($rawItemCount) ? (int) $rawItemCount : null;
        }

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
        $staticValues = array();

        if (isset($node->defaults)) {
            foreach ($node->defaults->default as $default) {
                $name = trim((string) $default['name']);

                if ($name !== '') {
                    $value = trim((string) $default['value']);
                    $defaults[$name] = $value;
                    $staticValues[] = array(
                        'field' => $name,
                        'value' => $value,
                        'mode' => 'if_empty',
                    );
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
            'item_count' => $itemCount,
            'item_count_checked' => isset($node->items) ? trim((string) $node->items['checked']) : '',
            'mapping' => $mapping,
            'defaults' => $defaults,
            'static_values' => $staticValues,
        );
    }
}
