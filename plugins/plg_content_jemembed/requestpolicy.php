<?php
/**
 * @package    JEM
 * @subpackage JEM Embed Plugin
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Input, credential and resource policy for the JEM Embed endpoint.
 */
final class JemEmbedRequestPolicy
{
    public const MAX_EVENTS = 100;
    public const MAX_START = 10000;
    public const MAX_FILTER_IDS = 50;
    public const MAX_FILTER_LENGTH = 512;
    public const MAX_TITLE_LENGTH = 500;
    public const MAX_FORMAT_LENGTH = 64;
    public const MAX_DESCRIPTION_LENGTH = 5000;
    public const MAX_QUERY_LENGTH = 4096;
    public const MAX_TOKEN_LENGTH = 512;
    public const MAX_CONFIGURED_TOKENS = 20;
    public const MAX_TOKEN_CONFIGURATION_LENGTH = 12288;

    /**
     * Validate and normalise the complete endpoint parameter set.
     *
     * @throws InvalidArgumentException
     */
    public static function normaliseParameters(array $parameters): array
    {
        $parameters['type'] = self::normaliseEnum(
            $parameters['type'] ?? 'unfinished',
            array('today', 'unfinished', 'upcoming', 'ongoing', 'archived', 'newest', 'open', 'all'),
            'type'
        );
        $parameters['show_featured'] = self::normaliseSwitch($parameters['show_featured'] ?? 'off', 'featured');

        foreach (array('title', 'show_date', 'show_category', 'show_venue') as $name) {
            $parameters[$name] = self::normaliseDisplayMode($parameters[$name] ?? 'on', $name);
        }

        foreach (array('show_time', 'show_enddatetime') as $name) {
            $parameters[$name] = self::normaliseSwitch($parameters[$name] ?? 'on', $name);
        }

        $parameters['max_events'] = self::normaliseInteger(
            $parameters['max_events'] ?? self::MAX_EVENTS,
            1,
            self::MAX_EVENTS,
            'max'
        );
        $parameters['start'] = self::normaliseInteger(
            $parameters['start'] ?? 0,
            0,
            self::MAX_START,
            'start'
        );
        $parameters['cut_title'] = self::normaliseInteger(
            $parameters['cut_title'] ?? 100,
            1,
            self::MAX_TITLE_LENGTH,
            'cuttitle'
        );
        $parameters['catids'] = self::normaliseIdList($parameters['catids'] ?? '', 'catids');
        $parameters['venueids'] = self::normaliseIdList($parameters['venueids'] ?? '', 'venueids');
        $parameters['date_format'] = self::normaliseFormat($parameters['date_format'] ?? '', 'dateformat');
        $parameters['time_format'] = self::normaliseFormat($parameters['time_format'] ?? '', 'timeformat');

        return $parameters;
    }

    /**
     * Compare one presented token against the configured raw or SHA-256 values.
     */
    public static function tokenMatches(string $presentedToken, string $configuredTokens): bool
    {
        $length = strlen($presentedToken);

        if ($length === 0 || $length > self::MAX_TOKEN_LENGTH
            || preg_match('/[\x00-\x20\x7f]/', $presentedToken)
            || strlen($configuredTokens) > self::MAX_TOKEN_CONFIGURATION_LENGTH) {
            return false;
        }

        $tokens = array_map('trim', explode(',', $configuredTokens));

        if (count($tokens) > self::MAX_CONFIGURED_TOKENS) {
            return false;
        }

        $presentedDigest = hash('sha256', $presentedToken);
        $matched = false;

        foreach ($tokens as $configuredToken) {
            if ($configuredToken === '') {
                continue;
            }

            if (preg_match('/^sha256:([a-f0-9]{64})$/i', $configuredToken, $matches)) {
                $configuredDigest = strtolower($matches[1]);
            } elseif (strlen($configuredToken) <= self::MAX_TOKEN_LENGTH
                && !preg_match('/[\x00-\x1f\x7f]/', $configuredToken)) {
                $configuredDigest = hash('sha256', $configuredToken);
            } else {
                $configuredDigest = str_repeat('0', 64);
            }

            $currentMatch = hash_equals($configuredDigest, $presentedDigest);
            $matched = $currentMatch || $matched;
        }

        return $matched;
    }

    /**
     * Apply an atomic fixed-window limit to one opaque request identity.
     */
    public static function consumeRateLimit(
        string $directory,
        string $identity,
        int $limit,
        int $windowSeconds,
        ?int $now = null
    ): bool {
        if ($directory === '' || $identity === '' || $limit < 1 || $windowSeconds < 1) {
            return false;
        }

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return false;
        }

        $now = $now ?? time();
        $path = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . hash('sha256', $identity) . '.json';
        $handle = @fopen($path, 'c+b');

        if ($handle === false) {
            return false;
        }

        $allowed = false;

        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }

            rewind($handle);
            $stored = stream_get_contents($handle, 1024);
            $state = is_string($stored) && $stored !== '' ? json_decode($stored, true) : null;
            $windowStarted = is_array($state) && isset($state['window']) ? (int) $state['window'] : $now;
            $count = is_array($state) && isset($state['count']) ? max(0, (int) $state['count']) : 0;

            if ($now < $windowStarted || ($now - $windowStarted) >= $windowSeconds) {
                $windowStarted = $now;
                $count = 0;
            }

            if ($count < $limit) {
                $payload = json_encode(array('window' => $windowStarted, 'count' => $count + 1));

                if (is_string($payload)) {
                    rewind($handle);
                    $written = ftruncate($handle, 0)
                        && fwrite($handle, $payload) === strlen($payload)
                        && fflush($handle);
                    $allowed = (bool) $written;
                }
            }

            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        if ($allowed) {
            self::cleanupRateLimitDirectory($directory, $now, max(3600, $windowSeconds * 2));
        }

        return $allowed;
    }

    /**
     * Accept only an administrator-configured HTTP(S) origin.
     */
    public static function normaliseBaseUrl($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $value = trim((string) $value);

        if ($value === '' || strlen($value) > 2048 || preg_match('/[\x00-\x20\x7f]/', $value)
            || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        $parts = parse_url($value);

        if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), array('http', 'https'), true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])) {
            return '';
        }

        return rtrim($value, '/');
    }

    public static function isQueryStringAllowed(string $query): bool
    {
        return strlen($query) <= self::MAX_QUERY_LENGTH && strpos($query, "\0") === false;
    }

    public static function truncateDescription($value): string
    {
        $value = is_scalar($value) ? (string) $value : '';

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value, 'UTF-8') > self::MAX_DESCRIPTION_LENGTH
                ? mb_substr($value, 0, self::MAX_DESCRIPTION_LENGTH - 1, 'UTF-8') . '…'
                : $value;
        }

        return strlen($value) > self::MAX_DESCRIPTION_LENGTH
            ? substr($value, 0, self::MAX_DESCRIPTION_LENGTH - 3) . '...'
            : $value;
    }

    private static function normaliseEnum($value, array $allowed, string $name): string
    {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Invalid ' . $name . ' parameter.');
        }

        $value = strtolower(trim((string) $value));

        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException('Invalid ' . $name . ' parameter.');
        }

        return $value;
    }

    private static function normaliseSwitch($value, string $name): string
    {
        if ((string) $value === '1') {
            $value = 'on';
        } elseif ((string) $value === '0') {
            $value = 'off';
        }

        return self::normaliseEnum($value, array('on', 'off'), $name);
    }

    private static function normaliseDisplayMode($value, string $name): string
    {
        if ((string) $value === '1') {
            $value = 'on';
        } elseif ((string) $value === '0') {
            $value = 'off';
        }

        return self::normaliseEnum($value, array('on', 'link', 'off'), $name);
    }

    private static function normaliseInteger($value, int $minimum, int $maximum, string $name): int
    {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Invalid ' . $name . ' parameter.');
        }

        $value = trim((string) $value);

        if (!preg_match('/^(?:0|[1-9][0-9]*)$/D', $value)) {
            throw new InvalidArgumentException('Invalid ' . $name . ' parameter.');
        }

        $number = filter_var($value, FILTER_VALIDATE_INT);

        if ($number === false || $number < $minimum || $number > $maximum) {
            throw new InvalidArgumentException('Invalid ' . $name . ' parameter.');
        }

        return (int) $number;
    }

    private static function normaliseIdList($value, string $name): string
    {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Invalid ' . $name . ' parameter.');
        }

        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (strlen($value) > self::MAX_FILTER_LENGTH) {
            throw new InvalidArgumentException('Invalid ' . $name . ' parameter.');
        }

        $parts = explode(',', $value);

        if (count($parts) > self::MAX_FILTER_IDS) {
            throw new InvalidArgumentException('Invalid ' . $name . ' parameter.');
        }

        $ids = array();

        foreach ($parts as $part) {
            $part = trim($part);

            if (!preg_match('/^[1-9][0-9]*$/D', $part)) {
                throw new InvalidArgumentException('Invalid ' . $name . ' parameter.');
            }

            $id = filter_var($part, FILTER_VALIDATE_INT, array(
                'options' => array('min_range' => 1, 'max_range' => 2147483647),
            ));

            if ($id === false) {
                throw new InvalidArgumentException('Invalid ' . $name . ' parameter.');
            }

            $ids[(int) $id] = (int) $id;
        }

        return implode(',', array_values($ids));
    }

    private static function normaliseFormat($value, string $name): string
    {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Invalid ' . $name . ' parameter.');
        }

        $value = (string) $value;

        if (strlen($value) > self::MAX_FORMAT_LENGTH || preg_match('/[\x00-\x1f\x7f]/', $value)
            || preg_match('//u', $value) !== 1) {
            throw new InvalidArgumentException('Invalid ' . $name . ' parameter.');
        }

        return $value;
    }

    private static function cleanupRateLimitDirectory(string $directory, int $now, int $maximumAge): void
    {
        $marker = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '.cleanup';
        $handle = @fopen($marker, 'c+b');

        if ($handle === false) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX | LOCK_NB)) {
                return;
            }

            rewind($handle);
            $lastCleanup = (int) stream_get_contents($handle, 32);

            if (($now - $lastCleanup) < 3600) {
                flock($handle, LOCK_UN);
                return;
            }

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, (string) $now);
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }

        foreach ((array) glob(rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '*.json') as $path) {
            $modified = @filemtime($path);

            if ($modified !== false && ($now - $modified) > $maximumAge) {
                @unlink($path);
            }
        }
    }
}
