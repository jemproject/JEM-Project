<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Filter\InputFilter;

/**
 * Central import sanitiser for external and legacy import flows.
 */
class JemImportSecurityHelper
{
    const CORE_BLOCKED_TAGS = 'script,object,embed,svg,math,meta,link,base,form,input,button,textarea,select,option';

    protected static $policy = null;

    public static function getCoreBlockedTags()
    {
        return explode(',', self::CORE_BLOCKED_TAGS);
    }

    public static function normaliseTagList($value, &$invalid = array())
    {
        $invalid = array();
        $tags = array();

        foreach (preg_split('/[\s,;]+/', strtolower(trim((string) $value)), -1, PREG_SPLIT_NO_EMPTY) as $tag) {
            if (!preg_match('/^[a-z][a-z0-9-]{0,31}$/', $tag)) {
                $invalid[] = $tag;
                continue;
            }

            $tags[] = $tag;
        }

        return implode(', ', array_values(array_unique($tags)));
    }

    public static function normaliseHostList($value, &$invalid = array())
    {
        $invalid = array();
        $hosts = array();

        foreach (preg_split('/[\s,;]+/', strtolower(trim((string) $value)), -1, PREG_SPLIT_NO_EMPTY) as $host) {
            $host = rtrim($host, '.');
            if (strlen($host) > 253
                || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host)) {
                $invalid[] = $host;
                continue;
            }

            $hosts[] = $host;
        }

        return implode("\n", array_values(array_unique($hosts)));
    }

    public static function resetPolicyCache()
    {
        self::$policy = null;
    }

    public static function assertRecordSafe(array $record, $context = '', $sourceLine = null)
    {
        $findings = self::findRecordThreats($record, $context);

        if ($findings) {
            throw new RuntimeException(self::formatBlockedMessage($findings, $context, $sourceLine, $record));
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

        $policy = self::getPolicy();
        $blockedTags = array_values(array_unique(array_merge(self::getCoreBlockedTags(), $policy['additional_tags'])));
        if (!$policy['allow_trusted_iframes']) {
            $blockedTags[] = 'iframe';
            $blockedTags = array_values(array_unique($blockedTags));
        }
        $blockedPattern = implode('|', array_map(function ($tag) {
            return preg_quote($tag, '#');
        }, $blockedTags));

        if ($blockedPattern !== '' && preg_match_all('#<\s*/?\s*(' . $blockedPattern . ')\b#i', $text, $matches)) {
            $tags = array_values(array_unique(array_map('strtolower', $matches[1])));
            $findings[] = $field . ' contains blocked HTML tags [' . implode(', ', $tags) . ']';
        }

        if ($policy['allow_trusted_iframes'] && preg_match_all('#<\s*iframe\b[^>]*>#i', $text, $iframeMatches)) {
            preg_match_all('#<\s*iframe\b[^>]*>.*?</iframe\s*>#is', $text, $completeIframeMatches);
            if (count($iframeMatches[0]) !== count($completeIframeMatches[0])) {
                $findings[] = $field . ' contains malformed iframe markup';
            }

            foreach ($iframeMatches[0] as $iframeTag) {
                $iframeFinding = self::findIframeThreat($iframeTag, $policy['trusted_iframe_hosts']);
                if ($iframeFinding !== '') {
                    $findings[] = $field . ' ' . $iframeFinding;
                }
            }
        }

        if (preg_match_all('/\s(on[a-z0-9_:-]+)\s*=/i', $text, $matches)) {
            $handlers = array_values(array_unique(array_map('strtolower', $matches[1])));
            $findings[] = $field . ' contains inline event handlers [' . implode(', ', $handlers) . ']';
        }

        if (preg_match_all('#(javascript|vbscript|data)\s*:#i', $text, $matches)) {
            $schemes = array_values(array_unique(array_map('strtolower', $matches[1])));
            $findings[] = $field . ' contains unsafe URL schemes [' . implode(', ', $schemes) . ']';
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

    public static function sanitiseRecord(array $record, $context = '', $sourceLine = null)
    {
        self::assertRecordSafe($record, $context, $sourceLine);

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

    public static function sanitiseValue($field, $value, $context = '', $sourceLine = null)
    {
        $findings = self::findValueThreats($field, $value, $context);

        if ($findings) {
            throw new RuntimeException(self::formatBlockedMessage($findings, $context, $sourceLine));
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

        if (self::isHtmlField($field, $context)) {
            return self::sanitiseHtml($value);
        }

        return self::plainText($value, true);
    }

    protected static function getPolicy()
    {
        if (self::$policy !== null) {
            return self::$policy;
        }

        $additionalTags = '';
        $allowTrustedIframes = 0;
        $trustedIframeHosts = '';

        try {
            if (class_exists('JemHelper')) {
                $settings = JemHelper::globalattribs();
                $additionalTags = (string) $settings->get('import_additional_blocked_tags', '');
                $allowTrustedIframes = (int) $settings->get('import_allow_trusted_iframes', 0);
                $trustedIframeHosts = (string) $settings->get('import_trusted_iframe_hosts', '');
            } elseif (class_exists('JemConfig')) {
                $settings = JemConfig::getInstance()->toRegistry();
                $additionalTags = (string) $settings->get('globalattribs.import_additional_blocked_tags', '');
                $allowTrustedIframes = (int) $settings->get('globalattribs.import_allow_trusted_iframes', 0);
                $trustedIframeHosts = (string) $settings->get('globalattribs.import_trusted_iframe_hosts', '');
            }
        } catch (Throwable $exception) {
            // Fail closed with the built-in policy if settings cannot be loaded.
        }

        $invalid = array();
        $additionalTags = self::normaliseTagList($additionalTags, $invalid);
        $additionalTags = $additionalTags === '' ? array() : preg_split('/,\s*/', $additionalTags);
        $trustedIframeHosts = self::normaliseHostList($trustedIframeHosts, $invalid);
        $trustedIframeHosts = $trustedIframeHosts === '' ? array() : preg_split('/\R/', $trustedIframeHosts);

        self::$policy = array(
            'additional_tags' => $additionalTags,
            'allow_trusted_iframes' => $allowTrustedIframes === 1 && !in_array('iframe', $additionalTags, true),
            'trusted_iframe_hosts' => $trustedIframeHosts,
        );

        return self::$policy;
    }

    protected static function findIframeThreat($iframeTag, array $trustedHosts)
    {
        $src = self::getHtmlAttribute($iframeTag, 'src');

        if ($src === '') {
            return 'contains iframe without a valid src attribute';
        }

        $scheme = strtolower((string) parse_url($src, PHP_URL_SCHEME));
        $host = strtolower(rtrim((string) parse_url($src, PHP_URL_HOST), '.'));

        if ($scheme !== 'https' || $host === '' || filter_var($src, FILTER_VALIDATE_URL) === false) {
            return 'contains iframe with an unsafe source';
        }

        foreach ($trustedHosts as $trustedHost) {
            if ($host === $trustedHost || substr($host, -strlen('.' . $trustedHost)) === '.' . $trustedHost) {
                return '';
            }
        }

        return 'contains iframe from an untrusted host [' . ($host !== '' ? $host : 'unknown') . ']';
    }

    protected static function getHtmlAttribute($tag, $attribute)
    {
        if (preg_match('/\b' . preg_quote($attribute, '/') . '\s*=\s*(["\'])(.*?)\1/is', $tag, $matches)) {
            return html_entity_decode(trim($matches[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (preg_match('/\b' . preg_quote($attribute, '/') . '\s*=\s*([^\s>]+)/i', $tag, $matches)) {
            return html_entity_decode(trim($matches[1], " \t\n\r\0\x0B\"'"), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }

    /**
     * Build a diagnostic message without copying untrusted import values into logs.
     *
     * @param   array       $findings    Detected security findings.
     * @param   string      $context     Import entity or source type.
     * @param   integer|null $sourceLine  Source CSV line or imported record number.
     * @param   array       $record      Optional record used only for safe identifiers.
     *
     * @return string
     */
    protected static function formatBlockedMessage(array $findings, $context = '', $sourceLine = null, array $record = array())
    {
        $details = array();
        $context = trim((string) $context);

        if ($context !== '') {
            $details[] = 'entity=' . preg_replace('/[^a-zA-Z0-9_#.-]/', '', $context);
        }

        if ($sourceLine !== null && (int) $sourceLine > 0) {
            $details[] = 'line=' . (int) $sourceLine;
        }

        foreach (array('id', 'eventid', 'itemid', 'object', 'locid', 'catid') as $identifier) {
            if (!isset($record[$identifier]) || !is_scalar($record[$identifier])) {
                continue;
            }

            $value = trim((string) $record[$identifier]);
            if ($value !== '' && preg_match('/^-?\d+$/', $value)) {
                $details[] = $identifier . '=' . (int) $value;
            }
        }

        $prefix = 'Unsafe import data blocked';
        if ($details) {
            $prefix .= ' (' . implode(', ', array_values(array_unique($details))) . ')';
        }

        return $prefix . ': ' . implode('; ', array_slice($findings, 0, 5));
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

    /**
     * Identify editor-backed fields whose safe markup must survive JEM migration.
     */
    protected static function isHtmlField($field, $context)
    {
        if (in_array((string) $field, array('introtext', 'fulltext', 'datdescription', 'locdescription', 'catdescription'), true)) {
            return true;
        }

        if ((string) $field !== 'description') {
            return false;
        }

        $context = strtolower((string) $context);

        return in_array($context, array(
            'categories', 'jem_categories', '#__jem_categories',
            'groups', 'jem_groups', '#__jem_groups',
            'specialdays', 'jem_special_days', '#__jem_special_days',
        ), true);
    }

    /**
     * Preserve normal editor markup while applying Joomla's XSS-aware HTML filter.
     */
    protected static function sanitiseHtml($value)
    {
        $text = (string) $value;
        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        $trustedIframes = array();
        $policy = self::getPolicy();

        if ($policy['allow_trusted_iframes'] && $policy['trusted_iframe_hosts']) {
            $text = preg_replace_callback(
                '#<iframe\b[^>]*>.*?</iframe\s*>#is',
                function ($matches) use (&$trustedIframes, $policy) {
                    if (self::findIframeThreat($matches[0], $policy['trusted_iframe_hosts']) !== '') {
                        return $matches[0];
                    }

                    $token = 'JEMTRUSTEDIFRAME' . count($trustedIframes) . 'TOKEN';
                    $trustedIframes[$token] = self::rebuildTrustedIframe($matches[0]);

                    return $token;
                },
                $text
            );
        }

        if (class_exists(InputFilter::class)) {
            $filter = InputFilter::getInstance(array(), array(), 1, 1);
            $text = trim((string) $filter->clean($text, 'html'));
        } else {
            // Unit tools may load this helper without Joomla; remain conservative there.
            $text = self::plainText($text, true);
        }

        return $trustedIframes ? strtr($text, $trustedIframes) : $text;
    }

    protected static function rebuildTrustedIframe($iframe)
    {
        $src = self::getHtmlAttribute($iframe, 'src');
        $attributes = array(
            'src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"',
            'loading="lazy"',
            'referrerpolicy="strict-origin-when-cross-origin"',
        );

        foreach (array('width', 'height') as $name) {
            $value = self::getHtmlAttribute($iframe, $name);
            if ($value !== '' && preg_match('/^(?:[1-9][0-9]{0,3}|100%)$/', $value)) {
                $attributes[] = $name . '="' . $value . '"';
            }
        }

        $title = self::getHtmlAttribute($iframe, 'title');
        if ($title !== '') {
            $attributes[] = 'title="' . htmlspecialchars(substr(strip_tags($title), 0, 255), ENT_QUOTES, 'UTF-8') . '"';
        }

        $allow = self::getHtmlAttribute($iframe, 'allow');
        if ($allow !== '' && strlen($allow) <= 255 && preg_match('/^[a-z0-9; _-]+$/i', $allow)) {
            $attributes[] = 'allow="' . htmlspecialchars($allow, ENT_QUOTES, 'UTF-8') . '"';
        }

        if (preg_match('/\ballowfullscreen(?:\s*=\s*(["\'])?allowfullscreen\1)?\b/i', $iframe)) {
            $attributes[] = 'allowfullscreen';
        }

        return '<iframe ' . implode(' ', $attributes) . '></iframe>';
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
