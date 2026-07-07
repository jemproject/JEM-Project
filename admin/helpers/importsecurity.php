<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Central import sanitiser for external and legacy import flows.
 */
class JemImportSecurityHelper
{
    public static function assertRecordSafe(array $record, $context = '')
    {
        $findings = self::findRecordThreats($record, $context);

        if ($findings) {
            throw new RuntimeException('Unsafe import data blocked: ' . implode('; ', array_slice($findings, 0, 5)));
        }
    }

    public static function findRecordThreats(array $record, $context = '')
    {
        $findings = array();

        foreach ($record as $field => $value) {
            $findings = array_merge($findings, self::findValueThreats((string) $field, $value, $context));
        }

        return array_values(array_unique($findings));
    }

    public static function findValueThreats($field, $value, $context = '')
    {
        if (is_array($value)) {
            return self::findRecordThreats($value, $context);
        }

        if ($value === null || $value === '') {
            return array();
        }

        $field = (string) $field;
        $text = (string) $value;
        $findings = array();

        if (preg_match('#<\s*/?\s*(script|iframe|object|embed|svg|math|meta|link|base|form|input|button|textarea|select|option)\b#i', $text)) {
            $findings[] = $field . ' contains blocked HTML tags';
        }

        if (preg_match('/\son[a-z0-9_:-]+\s*=/i', $text)) {
            $findings[] = $field . ' contains inline event handlers';
        }

        if (preg_match('#(?:javascript|vbscript|data)\s*:#i', $text)) {
            $findings[] = $field . ' contains an unsafe URL scheme';
        }

        if (preg_match('/<\?(?:php|=)?/i', $text) || preg_match('/\?>/', $text)) {
            $findings[] = $field . ' contains PHP code markers';
        }

        $xhrToken = 'XMLHttp' . 'Request';
        $fetchToken = 'fet' . 'ch';
        if (preg_match('/\b(?:eval|setTimeout|setInterval|Function|document\.cookie|localStorage|sessionStorage|' . $xhrToken . '|' . $fetchToken . ')\s*\(/i', $text)) {
            $findings[] = $field . ' contains script-like code';
        }

        $trimmed = trim($text);
        if (preg_match('/^[\s]*[=+@]/', $text) || (strpos($trimmed, '-') === 0 && !is_numeric($trimmed))) {
            $findings[] = $field . ' looks like a spreadsheet formula';
        }

        return $findings;
    }

    public static function sanitiseRecord(array $record, $context = '')
    {
        self::assertRecordSafe($record, $context);

        foreach ($record as $field => $value) {
            $record[$field] = self::sanitiseValue((string) $field, $value, (string) $context);
        }

        return $record;
    }

    public static function sanitiseRecordList(array $records, $context = '')
    {
        foreach ($records as $index => $record) {
            if (is_array($record)) {
                $records[$index] = self::sanitiseRecord($record, $context);
            }
        }

        return $records;
    }

    public static function sanitiseValue($field, $value, $context = '')
    {
        $findings = self::findValueThreats($field, $value, $context);

        if ($findings) {
            throw new RuntimeException('Unsafe import data blocked: ' . implode('; ', array_slice($findings, 0, 5)));
        }

        if (is_array($value)) {
            return self::sanitiseRecord($value, $context);
        }

        if ($value === null) {
            return null;
        }

        $field = (string) $field;

        if (self::isIntegerField($field)) {
            return (int) $value;
        }

        if (in_array($field, array('latitude', 'longitude'), true)) {
            $number = trim(str_replace(',', '.', (string) $value));
            return is_numeric($number) ? number_format((float) $number, 6, '.', '') : null;
        }

        if (in_array($field, array('url', 'online_meeting_url', 'website', 'webpage'), true)) {
            return self::sanitiseUrl($value);
        }

        if (in_array($field, array('datimage', 'fullimage', 'locimage', 'image', 'attachment'), true)) {
            return self::sanitiseRelativePath($value);
        }

        if ($field === 'color') {
            $color = trim((string) $value);
            return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? strtolower($color) : '';
        }

        if (in_array($field, array('metadata', 'attribs'), true)) {
            return self::sanitiseJsonObject($value);
        }

        if ($field === 'language') {
            $language = self::plainText($value, false);
            return preg_match('/^[a-zA-Z0-9_\-\*]+$/', $language) ? $language : '*';
        }

        return self::plainText($value, true);
    }

    protected static function isIntegerField($field)
    {
        static $fields = array(
            'id', 'published', 'access', 'ordering', 'created_by', 'modified_by',
            'checked_out', 'hits', 'featured', 'locid', 'catid', 'type_id', 'day_type_id',
            'article_id',
            'registra', 'unregistra', 'maxplaces', 'reservedplaces',
            'minbookeduser', 'maxbookeduser', 'waitinglist', 'requestanswer',
            'show_dates', 'map', 'event_status', 'ticket_availability',
        );

        return in_array((string) $field, $fields, true);
    }

    protected static function plainText($value, $neutraliseFormula)
    {
        $text = (string) $value;
        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('#<(script|style|iframe|object|embed|svg|math)[^>]*>.*?</\1>#is', '', $text);
        $text = strip_tags($text);
        $text = trim($text);

        if ($neutraliseFormula && preg_match('/^[\s]*[=+\-@]/', $text)) {
            $text = "'" . $text;
        }

        return $text;
    }

    protected static function sanitiseUrl($value)
    {
        $url = self::plainText($value, false);

        if ($url === '') {
            return '';
        }

        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }

        if (!preg_match('#^[a-z][a-z0-9+\-.]*://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (!in_array($scheme, array('http', 'https'), true) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return substr($url, 0, 2048);
    }

    protected static function sanitiseRelativePath($value)
    {
        $path = self::plainText($value, false);

        if ($path === '' || preg_match('#^[a-z][a-z0-9+\-.]*:#i', $path)) {
            return '';
        }

        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = ltrim($path, '/');

        if (strpos($path, '../') !== false || strpos($path, '..\\') !== false) {
            return '';
        }

        return substr($path, 0, 512);
    }

    protected static function sanitiseJsonObject($value)
    {
        $text = trim((string) $value);

        if ($text === '') {
            return '{}';
        }

        $decoded = json_decode($text, true);

        if (!is_array($decoded)) {
            return '{}';
        }

        $decoded = self::sanitiseRecord($decoded, 'json');

        return json_encode($decoded);
    }
}
