<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Build the public Schema.org Event entity from effective event data.
 *
 * The builder is deliberately unaware of JEM Operating Profiles. Callers
 * provide only facts that are public and visible in the current response.
 */
final class JemEventStructuredData
{
    private const EVENT_STATUSES = array(
        'scheduled'    => 'https://schema.org/EventScheduled',
        'cancelled'    => 'https://schema.org/EventCancelled',
        'postponed'    => 'https://schema.org/EventPostponed',
        'rescheduled'  => 'https://schema.org/EventRescheduled',
        'moved_online' => 'https://schema.org/EventMovedOnline',
    );

    /**
     * Build a JSON-LD Event entity. An empty array means that the public event
     * lacks the minimum identity, date or location needed for safe output.
     */
    public static function build($event, array $context): array
    {
        return self::analyse($event, $context)['data'];
    }

    /**
     * Build the entity and report current Google Event eligibility separately
     * from general Schema.org validity.
     */
    public static function analyse($event, array $context): array
    {
        $canonicalUrl = self::absoluteUrl(
            (string) ($context['canonical_url'] ?? ''),
            (string) ($context['base_url'] ?? '')
        );
        $name = self::normaliseText(self::value($event, 'title'), 300);
        $dates = self::dates($event, $context);
        $physicalLocation = self::physicalLocation($event, $context);
        $virtualLocation = self::virtualLocation($event, $context);
        $statusKey = strtolower(trim((string) self::value($event, 'event_status')));
        $statusKey = isset(self::EVENT_STATUSES[$statusKey]) ? $statusKey : 'scheduled';

        if ($statusKey === 'moved_online') {
            $location = $virtualLocation;
        } elseif ($physicalLocation && $virtualLocation) {
            $location = array($physicalLocation, $virtualLocation);
        } else {
            $location = $physicalLocation ?: $virtualLocation;
        }

        $missing = array();
        if ($canonicalUrl === '') {
            $missing[] = 'canonical_url';
        }
        if ($name === '') {
            $missing[] = 'name';
        }
        if (empty($dates['startDate'])) {
            $missing[] = 'start_date';
        }
        if (!$location) {
            $missing[] = 'location';
        }

        if ($missing) {
            return array(
                'data' => array(),
                'schema_valid' => false,
                'google_eligible' => false,
                'reasons' => $missing,
            );
        }

        $data = array(
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            '@id' => $canonicalUrl . '#event',
            'url' => $canonicalUrl,
            'mainEntityOfPage' => $canonicalUrl,
            'name' => $name,
            'startDate' => $dates['startDate'],
        );

        if (!empty($dates['endDate'])) {
            $data['endDate'] = $dates['endDate'];
        }
        $data['eventStatus'] = self::EVENT_STATUSES[$statusKey];

        if ($statusKey === 'moved_online' && $virtualLocation) {
            $data['eventAttendanceMode'] = 'https://schema.org/OnlineEventAttendanceMode';
        } elseif ($physicalLocation && $virtualLocation) {
            $data['eventAttendanceMode'] = 'https://schema.org/MixedEventAttendanceMode';
        } elseif ($virtualLocation) {
            $data['eventAttendanceMode'] = 'https://schema.org/OnlineEventAttendanceMode';
        } else {
            $data['eventAttendanceMode'] = 'https://schema.org/OfflineEventAttendanceMode';
        }
        $data['location'] = $location;

        $images = array();
        foreach ((array) ($context['image_urls'] ?? array()) as $imageUrl) {
            $imageUrl = self::absoluteUrl((string) $imageUrl, (string) ($context['base_url'] ?? ''));
            if ($imageUrl !== '' && !in_array($imageUrl, $images, true)) {
                $images[] = $imageUrl;
            }
        }
        if ($images) {
            $data['image'] = $images;
        }

        $description = self::normaliseText((string) ($context['description'] ?? ''), 5000);
        if ($description !== '') {
            $data['description'] = $description;
        }

        $reasons = array();
        if (!$physicalLocation) {
            $reasons[] = 'google_requires_physical_location';
        } elseif (empty($physicalLocation['address'])) {
            $reasons[] = 'google_requires_public_address';
        }
        if (!empty(self::value($event, 'reginvitedonly'))
            || (array_key_exists('general_public', $context) && !$context['general_public'])) {
            $reasons[] = 'google_requires_general_public_attendance';
        }

        return array(
            'data' => $data,
            'schema_valid' => true,
            'google_eligible' => !$reasons,
            'reasons' => $reasons,
        );
    }

    /**
     * Render JSON-LD using flags that prevent HTML/script injection.
     */
    public static function render(array $data): string
    {
        if (!$data) {
            return '';
        }

        try {
            $json = json_encode(
                $data,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
                | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $error) {
            return '';
        }

        return '<script type="application/ld+json">' . $json . '</script>';
    }

    /**
     * Convert editor HTML to concise public text.
     */
    public static function normaliseText($html, int $maximumLength = 0): string
    {
        $html = (string) $html;
        $html = preg_replace('/<(script|style|template|noscript)\b[^>]*>.*?<\/\1>/isu', ' ', $html) ?? $html;
        $html = preg_replace('/<\/?(?:p|div|br|li|h[1-6]|tr|td|th)\b[^>]*>/iu', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? $text;
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        if ($maximumLength > 0 && self::length($text) > $maximumLength) {
            $text = rtrim(self::substring($text, 0, $maximumLength));
            $lastSpace = strrpos($text, ' ');
            if ($lastSpace !== false && $lastSpace > (int) ($maximumLength * 0.75)) {
                $text = substr($text, 0, $lastSpace);
            }
            $text = rtrim($text, " \t\n\r\0\x0B,.;:-") . '…';
        }

        return $text;
    }

    /**
     * Resolve a public absolute HTTP(S) URL without exposing URL credentials.
     */
    public static function absoluteUrl($url, $baseUrl = ''): string
    {
        $url = trim(html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $baseUrl = trim(html_entity_decode((string) $baseUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            return '';
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) && !preg_match('#^https?://#i', $url)) {
            return '';
        }

        if (str_starts_with($url, '//')) {
            $baseScheme = parse_url($baseUrl, PHP_URL_SCHEME);
            $url = ($baseScheme === 'http' ? 'http:' : 'https:') . $url;
        } elseif (!preg_match('#^https?://#i', $url)) {
            $base = parse_url($baseUrl);
            if (!is_array($base) || empty($base['scheme']) || empty($base['host'])) {
                return '';
            }
            $origin = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');
            if (str_starts_with($url, '/')) {
                $url = $origin . $url;
            } else {
                $basePath = isset($base['path']) ? rtrim($base['path'], '/') : '';
                $url = $origin . $basePath . '/' . ltrim($url, '/');
            }
        }

        $parts = parse_url($url);
        if (!is_array($parts)
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), array('http', 'https'), true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return $url;
    }

    private static function dates($event, array $context): array
    {
        $startDate = self::validDate(self::value($event, 'dates'));
        if ($startDate === '') {
            return array();
        }

        $showTime = !array_key_exists('show_time', $context) || (bool) $context['show_time'];
        $startTime = $showTime ? self::validTime(self::value($event, 'times')) : '';
        $endDate = self::validDate(self::value($event, 'enddates'));
        $endTime = $showTime ? self::validTime(self::value($event, 'endtimes')) : '';
        $timeZone = self::timeZone((string) ($context['timezone'] ?? ''));

        $dates = array('startDate' => self::dateValue($startDate, $startTime, $timeZone));
        if ($endDate === '' && $startTime !== '' && $endTime !== '') {
            $endDate = $startDate;
        }
        if ($endDate !== '') {
            $dates['endDate'] = self::dateValue($endDate, $endTime, $timeZone);
        }

        return $dates;
    }

    private static function physicalLocation($event, array $context): array
    {
        if (empty($context['physical_location_visible'])) {
            return array();
        }

        $name = self::normaliseText(self::value($event, 'venue'), 300);
        if ($name === '') {
            return array();
        }

        $location = array('@type' => 'Place', 'name' => $name);
        if (!empty($context['physical_address_visible'])) {
            $address = array('@type' => 'PostalAddress');
            foreach (array(
                'street' => 'streetAddress',
                'city' => 'addressLocality',
                'state' => 'addressRegion',
                'postalCode' => 'postalCode',
                'country' => 'addressCountry',
            ) as $source => $property) {
                $value = self::normaliseText(self::value($event, $source), 300);
                if ($value !== '') {
                    $address[$property] = $property === 'addressCountry' && strlen($value) === 2
                        ? strtoupper($value)
                        : $value;
                }
            }
            if (count($address) > 1) {
                $location['address'] = $address;
            }
        }

        return $location;
    }

    private static function virtualLocation($event, array $context): array
    {
        if (empty($context['virtual_location_visible'])) {
            return array();
        }

        $url = self::absoluteUrl(
            (string) ($context['virtual_location_url'] ?? ''),
            (string) ($context['base_url'] ?? '')
        );
        if ($url === '') {
            return array();
        }

        $location = array('@type' => 'VirtualLocation', 'url' => $url);
        $name = self::normaliseText(
            (string) ($context['virtual_location_name'] ?? self::value($event, 'online_meeting_label')),
            300
        );
        if ($name !== '') {
            $location['name'] = $name;
        }

        return $location;
    }

    private static function dateValue(string $date, string $time, ?DateTimeZone $timeZone): string
    {
        if ($time === '') {
            return $date;
        }
        if (!$timeZone) {
            return $date . 'T' . substr($time, 0, 5);
        }

        try {
            return (new DateTimeImmutable($date . ' ' . $time, $timeZone))->format('Y-m-d\TH:iP');
        } catch (Exception $error) {
            return $date . 'T' . substr($time, 0, 5);
        }
    }

    private static function validDate($value): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            return '';
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date && ($errors === false || !$errors['warning_count'] && !$errors['error_count'])
            && $date->format('Y-m-d') === $value
            ? $value
            : '';
    }

    private static function validTime($value): string
    {
        $value = trim((string) $value);

        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', $value) ? $value : '';
    }

    private static function timeZone(string $name): ?DateTimeZone
    {
        try {
            return $name !== '' ? new DateTimeZone($name) : null;
        } catch (Exception $error) {
            return null;
        }
    }

    private static function value($event, string $property)
    {
        if (is_object($event)) {
            return $event->{$property} ?? '';
        }

        return is_array($event) ? ($event[$property] ?? '') : '';
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private static function substring(string $value, int $start, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, $start, $length, 'UTF-8')
            : substr($value, $start, $length);
    }
}
