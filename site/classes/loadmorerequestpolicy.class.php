<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Input and resource policy for the public events list load-more endpoint.
 */
final class JemLoadMoreRequestPolicy
{
    public const MAX_OFFSET = 10000;
    public const MAX_LIMIT = 100;
    public const MAX_MONTH_LENGTH = 64;
    public const MAX_QUERY_LENGTH = 4096;
    public const RATE_LIMIT = 120;
    public const RATE_WINDOW_SECONDS = 60;

    private const ALLOWED_PARAMETERS = array(
        'option',
        'view',
        'task',
        'format',
        'Itemid',
        'offset',
        'limit',
        'lastDisplayedMonth',
        'loadmore_context',
        'lang',
        'language',
    );

    /**
     * Validate and normalise the endpoint parameter set.
     *
     * @throws InvalidArgumentException
     */
    public static function normaliseParameters(array $parameters): array
    {
        $unknown = array_diff(array_keys($parameters), self::ALLOWED_PARAMETERS);

        if ($unknown) {
            throw new InvalidArgumentException('Unexpected request parameter.');
        }

        self::requireValue($parameters['option'] ?? 'com_jem', 'com_jem', 'option');
        self::requireValue($parameters['view'] ?? 'eventslist', 'eventslist', 'view');
        self::requireValue($parameters['task'] ?? '', 'loadmore', 'task');
        self::requireValue($parameters['format'] ?? '', 'json', 'format');

        if (array_key_exists('Itemid', $parameters)) {
            self::normaliseInteger($parameters['Itemid'], 0, 2147483647, 'Itemid');
        }

        $context = self::normaliseScalar($parameters['loadmore_context'] ?? '', 'context');

        if (!in_array($context, array('', 'archive'), true)) {
            throw new InvalidArgumentException('Invalid context parameter.');
        }

        foreach (array('lang', 'language') as $name) {
            if (!array_key_exists($name, $parameters)) {
                continue;
            }

            $language = self::normaliseScalar($parameters[$name], $name);

            if (strlen($language) > 32 || !preg_match('/^[A-Za-z0-9_-]*$/D', $language)) {
                throw new InvalidArgumentException('Invalid language parameter.');
            }
        }

        return array(
            'offset' => self::normaliseInteger(
                $parameters['offset'] ?? 0,
                0,
                self::MAX_OFFSET,
                'offset'
            ),
            'limit' => self::normaliseInteger(
                $parameters['limit'] ?? 10,
                1,
                self::MAX_LIMIT,
                'limit'
            ),
            'lastDisplayedMonth' => self::normaliseMonth($parameters['lastDisplayedMonth'] ?? ''),
            'context' => $context,
        );
    }

    public static function isQueryStringAllowed(string $query): bool
    {
        return strlen($query) <= self::MAX_QUERY_LENGTH && strpos($query, "\0") === false;
    }

    public static function isGetRequest($method): bool
    {
        return is_scalar($method) && strtoupper(trim((string) $method)) === 'GET';
    }

    public static function normaliseRemoteAddress($address): string
    {
        if (!is_scalar($address)) {
            return 'unknown';
        }

        $address = trim((string) $address);

        return filter_var($address, FILTER_VALIDATE_IP) !== false ? $address : 'unknown';
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

    private static function requireValue($value, string $expected, string $name): void
    {
        $value = self::normaliseScalar($value, $name);

        if ($value !== $expected) {
            throw new InvalidArgumentException('Invalid ' . $name . ' parameter.');
        }
    }

    private static function normaliseScalar($value, string $name): string
    {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException('Invalid ' . $name . ' parameter.');
        }

        return trim((string) $value);
    }

    private static function normaliseInteger($value, int $minimum, int $maximum, string $name): int
    {
        $value = self::normaliseScalar($value, $name);

        if (!preg_match('/^(?:0|[1-9][0-9]*)$/D', $value)) {
            throw new InvalidArgumentException('Invalid ' . $name . ' parameter.');
        }

        $number = filter_var($value, FILTER_VALIDATE_INT);

        if ($number === false || $number < $minimum || $number > $maximum) {
            throw new InvalidArgumentException('Invalid ' . $name . ' parameter.');
        }

        return (int) $number;
    }

    private static function normaliseMonth($value): string
    {
        $value = self::normaliseScalar($value, 'last displayed month');

        if (strlen($value) > self::MAX_MONTH_LENGTH
            || preg_match('/[\x00-\x1f\x7f]/', $value)
            || preg_match('//u', $value) !== 1) {
            throw new InvalidArgumentException('Invalid last displayed month parameter.');
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
