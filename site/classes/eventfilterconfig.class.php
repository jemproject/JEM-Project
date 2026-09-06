<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Normalises the ordered filter configuration stored in menu parameters.
 */
final class JemEventFilterConfig
{
    public const VERSION = 2;
    public const MAX_CUSTOM_FIELDS = 10;
    public const CONTACT_CATEGORY = 'contact_category';
    public const CONTACT = 'contact';

    /**
     * Return the filter definitions available to the first Eventslist pilot.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return array(
            self::CONTACT_CATEGORY => array(
                'condition' => 'descendants',
                'conditions' => array('exact', 'descendants'),
                'multiple' => false,
            ),
            self::CONTACT => array(
                'condition' => 'in',
                'conditions' => array('in'),
                'multiple' => true,
            ),
        );
    }

    /**
     * Return the optional event custom-field keys supported by the contract.
     *
     * @return array<int, string>
     */
    public static function customKeys(): array
    {
        $keys = array();

        for ($i = 1; $i <= self::MAX_CUSTOM_FIELDS; $i++) {
            $keys[] = 'custom' . $i;
        }

        return $keys;
    }

    /**
     * Return true only for the ten physical JEM event custom fields.
     */
    public static function isCustomKey(string $key): bool
    {
        return in_array($key, self::customKeys(), true);
    }

    /**
     * Normalise a configured or visitor-supplied custom-field value.
     */
    public static function normaliseCustomValue($value): string
    {
        return self::normaliseText($value);
    }

    /**
     * Read and normalise the configuration from a menu Registry-like object.
     *
     * @param   mixed   $params  Menu parameters.
     * @param   string  $name    Parameter name.
     *
     * @return array{version: int, rows: array<int, array<string, mixed>>}
     */
    public static function fromParams($params, string $name = 'event_filters'): array
    {
        $value = is_object($params) && method_exists($params, 'get')
            ? $params->get($name, '')
            : '';

        return self::normalise($value);
    }

    /**
     * Normalise JSON, arrays, and Registry values to the shared filter contract.
     *
     * @param   mixed  $value  Stored configuration.
     *
     * @return array{version: int, rows: array<int, array<string, mixed>>}
     */
    public static function normalise($value): array
    {
        $decoded = self::decode($value);
        $inputRows = isset($decoded['rows']) && is_array($decoded['rows'])
            ? $decoded['rows']
            : (array_is_list($decoded) ? $decoded : array());
        $definitions = self::definitions();
        $rows = array();
        $seen = array();
        $customCount = 0;

        foreach ($inputRows as $inputRow) {
            if (is_object($inputRow)) {
                $inputRow = get_object_vars($inputRow);
            }

            if (!is_array($inputRow)) {
                continue;
            }

            $key = isset($inputRow['key']) ? (string) $inputRow['key'] : '';

            $definition = self::definitionFor($key);

            if ($definition === null || isset($seen[$key])) {
                continue;
            }

            if (self::isCustomKey($key) && ++$customCount > self::MAX_CUSTOM_FIELDS) {
                continue;
            }

            $rows[] = self::normaliseRow($key, $inputRow, $definition);
            $seen[$key] = true;
        }

        foreach ($definitions as $key => $definition) {
            if (!isset($seen[$key])) {
                $rows[] = self::normaliseRow($key, array(), $definition);
            }
        }

        return array(
            'version' => self::VERSION,
            'rows' => $rows,
        );
    }

    /**
     * Encode a normalised configuration for storage in a menu parameter.
     */
    public static function encode($value): string
    {
        return (string) json_encode(
            self::normalise($value),
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        );
    }

    /**
     * Return a single normalised row.
     *
     * @return array<string, mixed>
     */
    public static function row($configuration, string $key): array
    {
        foreach (self::normalise($configuration)['rows'] as $row) {
            if ($row['key'] === $key) {
                return $row;
            }
        }

        return array();
    }

    /**
     * Create a stable key for isolating frontend user state after menu changes.
     */
    public static function fingerprint($configuration): string
    {
        return substr(hash('sha256', self::encode($configuration)), 0, 16);
    }

    /**
     * Return true when a row contains a value that can be applied.
     */
    public static function hasValue(array $row): bool
    {
        $key = isset($row['key']) ? (string) $row['key'] : '';

        if ($key === self::CONTACT_CATEGORY) {
            return (int) $row['value'] > 0;
        }

        if ($key === self::CONTACT) {
            return !empty($row['value']);
        }

        return trim((string) ($row['value'] ?? '')) !== '';
    }

    /**
     * Decode a supported stored value.
     *
     * @return array<string|int, mixed>
     */
    private static function decode($value): array
    {
        if (is_object($value) && method_exists($value, 'toArray')) {
            $value = $value->toArray();
        } elseif (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return array();
            }

            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : array();
        }

        return is_array($value) ? $value : array();
    }

    /**
     * Normalise one filter row and enforce valid status combinations.
     *
     * @param   string                $key         Filter key.
     * @param   array<string, mixed>  $row         Stored row.
     * @param   array<string, mixed>  $definition  Registry definition.
     *
     * @return array<string, mixed>
     */
    private static function normaliseRow(string $key, array $row, array $definition): array
    {
        $condition = isset($row['condition']) ? (string) $row['condition'] : $definition['condition'];

        if (!in_array($condition, $definition['conditions'], true)) {
            $condition = $definition['condition'];
        }

        if ($key === self::CONTACT) {
            $value = self::normaliseIds($row['value'] ?? array());
        } elseif ($key === self::CONTACT_CATEGORY) {
            $value = max(0, (int) ($row['value'] ?? 0));
        } else {
            $value = self::normaliseText($row['value'] ?? '');
        }

        $active = self::normaliseBool($row['active'] ?? false);
        $visible = self::normaliseBool($row['visible'] ?? false);
        $editable = self::normaliseBool($row['editable'] ?? false);

        $normalisedValue = array(
            'key' => $key,
            'value' => $value,
        );

        if ($active && !self::hasValue($normalisedValue)) {
            $active = false;
        }

        if ($editable) {
            $visible = true;
        }

        if (!$visible) {
            $editable = false;
        }

        if (!$active && $visible && !$editable) {
            $editable = true;
        }

        return array(
            'key' => $key,
            'condition' => $condition,
            'value' => $value,
            'active' => $active,
            'visible' => $visible,
            'editable' => $editable,
        );
    }

    /**
     * Normalise a list of positive integer identifiers.
     *
     * @return array<int, int>
     */
    private static function normaliseIds($value): array
    {
        if (!is_array($value)) {
            $value = trim((string) $value);
            $value = $value === '' ? array() : explode(',', $value);
        }

        $ids = array_map('intval', $value);
        $ids = array_filter($ids, static function ($id) {
            return $id > 0;
        });

        return array_values(array_unique($ids));
    }

    /**
     * Return the fixed or optional definition for a supported row key.
     *
     * @return array<string, mixed>|null
     */
    private static function definitionFor(string $key): ?array
    {
        $definitions = self::definitions();

        if (isset($definitions[$key])) {
            return $definitions[$key];
        }

        if (self::isCustomKey($key)) {
            return array(
                'condition' => 'contains',
                'conditions' => array('contains', 'exact'),
                'multiple' => false,
            );
        }

        return null;
    }

    /**
     * Normalise a visitor-searchable custom-field value.
     */
    private static function normaliseText($value): string
    {
        if (is_array($value) || is_object($value)) {
            return '';
        }

        $value = trim(strip_tags((string) $value));
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';

        return function_exists('mb_substr')
            ? mb_substr($value, 0, 200, 'UTF-8')
            : substr($value, 0, 200);
    }

    /**
     * Normalise boolean-like menu values without treating "false" as true.
     */
    private static function normaliseBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
    }
}
