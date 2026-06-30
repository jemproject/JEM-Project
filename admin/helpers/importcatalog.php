<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Helper for the optional root import-catalog.xml file.
 */
class JemImportCatalogHelper
{
    public static function getCatalogPath()
    {
        return JPATH_ROOT . '/import-catalog.xml';
    }

    public static function getCatalogSource()
    {
        return 'import-catalog.xml';
    }

    public static function getEntries()
    {
        $path = self::getCatalogPath();

        if (!is_file($path)) {
            return array();
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path);

        if (!$xml) {
            return array();
        }

        $entries = array();

        foreach ($xml->entry as $node) {
            $entry = self::normaliseEntry($node);

            if ($entry['id'] !== '') {
                $entries[] = $entry;
            }
        }

        return $entries;
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
