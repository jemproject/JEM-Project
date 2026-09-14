<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

class JemMailtoHelper
{
    public const RATE_WINDOW_SECONDS = 86400;
    public const SESSION_RATE_LIMIT = 5;
    public const ACCOUNT_RATE_LIMIT = 5;
    public const IP_RATE_LIMIT = 20;

    public static function addLink($url, array $context = array())
    {
        $hash = sha1($url);
        self::cleanHashes();

        $app = Factory::getApplication();
        $session      = $app->getSession();
        $mailto_links = $session->get('com_jem.links', array());

        if (!isset($mailto_links[$hash]))
        {
            $mailto_links[$hash] = new stdClass;
        }

        $mailto_links[$hash]->link   = $url;
        $mailto_links[$hash]->expiry = time();
        $mailto_links[$hash]->context = self::normaliseLinkContext($context);
        $session->set('com_jem.links', $mailto_links);

        return $hash;
    }

    public static function cleanHashes($lifetime = 1440)
    {
        // Flag for if we've cleaned on this cycle
        static $cleaned = false;

        if (!$cleaned)
        {
            $past         = time() - $lifetime;
            $app = Factory::getApplication();
            $session      = $app->getSession();
            $mailto_links = $session->get('com_jem.links', array());

            foreach ($mailto_links as $index => $link)
            {
                if ($link->expiry < $past)
                {
                    unset($mailto_links[$index]);
                }
            }

            $session->set('com_jem.links', $mailto_links);
            $cleaned = true;
        }
    }

    public static function validateHash($hash)
    {
        $retval  = false;
        $app = Factory::getApplication();
        $session = $app->getSession();

        self::cleanHashes();
        $mailto_links = $session->get('com_jem.links', array());

        if (isset($mailto_links[$hash]))
        {
            $retval = $mailto_links[$hash]->link;
        }

        return $retval;
    }

    /**
     * Return the trusted JEM item context stored with a mail link.
     *
     * @param   string  $hash  Session-bound link hash.
     *
     * @return  array
     */
    public static function getLinkContext($hash): array
    {
        $app = Factory::getApplication();
        $session = $app->getSession();

        self::cleanHashes();
        $mailtoLinks = $session->get('com_jem.links', array());

        if (!isset($mailtoLinks[$hash]) || !is_object($mailtoLinks[$hash])) {
            return array();
        }

        return self::normaliseLinkContext((array) ($mailtoLinks[$hash]->context ?? array()));
    }

    /**
     * Normalise the JEM item metadata used for action logging.
     *
     * @param   array  $context  Candidate view and item identifier.
     *
     * @return  array
     */
    public static function normaliseLinkContext(array $context): array
    {
        $view = strtolower(trim((string) ($context['view'] ?? '')));
        $allowedViews = array('category', 'event', 'venue', 'venueslist');

        if (!in_array($view, $allowedViews, true)) {
            return array();
        }

        $id = (int) ($context['id'] ?? 0);

        if ($view !== 'venueslist' && $id < 1) {
            return array();
        }

        return array('view' => $view, 'id' => $id);
    }

    /**
     * Check whether the current visitor can use the public mail form.
     *
     * Mail delivery is restricted to authenticated Joomla users. Guests can
     * still share the public URL without involving the site mail service.
     *
     * @param   object  $app  Joomla application.
     *
     * @return  boolean
     */
    public static function canCurrentUserSend($app)
    {
        return !$app->getIdentity()->guest;
    }

    /**
     * Reject control characters, oversized values and header-like input.
     *
     * @param   array  $data  Submitted mail fields.
     *
     * @return  boolean
     */
    public static function containsForbiddenHeaderData(array $data)
    {
        $maximumLengths = array(
            'emailto' => 254,
            'emailfrom' => 254,
            'sender' => 128,
            'subject' => 255,
        );
        $headerPattern = '/^(?:content-type|mime-version|content-transfer-encoding|bcc|cc|reply-to|from|to)\s*:/i';

        foreach ($maximumLengths as $field => $maximumLength) {
            if (!isset($data[$field]) || !is_scalar($data[$field])) {
                return true;
            }

            $value = (string) $data[$field];

            if (strlen($value) > $maximumLength
                || preg_match('//u', $value) !== 1
                || preg_match('/[\x00-\x1f\x7f]/', $value)
                || preg_match($headerPattern, ltrim($value))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Consume the layered mail submission quotas without storing identities.
     *
     * @param   string        $directory   Private cache directory.
     * @param   string        $remote      Direct remote address.
     * @param   string        $sessionId   Current Joomla session identifier.
     * @param   integer       $userId      Current Joomla user identifier.
     * @param   string        $secret      Joomla site secret.
     * @param   integer|null  $now         Optional clock for deterministic tests.
     *
     * @return  boolean
     */
    public static function consumeSubmissionLimits(
        string $directory,
        string $remote,
        string $sessionId,
        int $userId,
        string $secret,
        ?int $now = null
    ): bool
    {
        $sessionId = trim($sessionId);
        $secret = trim($secret);

        if ($directory === '' || $sessionId === '' || $userId < 1 || $secret === '') {
            return false;
        }

        $limits = array(
            hash_hmac('sha256', "session\0" . $sessionId, $secret) => self::SESSION_RATE_LIMIT,
            hash_hmac('sha256', "account\0" . $userId, $secret) => self::ACCOUNT_RATE_LIMIT,
        );
        $remote = self::normaliseRemoteAddress($remote);

        if ($remote !== '') {
            $limits[hash_hmac('sha256', "ip\0" . $remote, $secret)] = self::IP_RATE_LIMIT;
        }

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return false;
        }

        $path = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . '.limits.json';
        $handle = @fopen($path, 'c+b');

        if ($handle === false) {
            return false;
        }

        $now = $now ?? time();
        $allowed = false;

        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }

            rewind($handle);
            $stored = stream_get_contents($handle, 1048577);

            if (!is_string($stored) || strlen($stored) > 1048576) {
                return false;
            }

            $state = $stored === '' ? array() : json_decode($stored, true);

            if (!is_array($state)) {
                return false;
            }

            foreach ($state as $identity => $entry) {
                $window = is_array($entry) && isset($entry['window']) ? (int) $entry['window'] : 0;

                if (!preg_match('/^[a-f0-9]{64}$/D', (string) $identity)
                    || $window < 1
                    || $now < $window
                    || ($now - $window) >= self::RATE_WINDOW_SECONDS) {
                    unset($state[$identity]);
                }
            }

            foreach ($limits as $identity => $limit) {
                $entry = $state[$identity] ?? array('window' => $now, 'count' => 0);
                $count = isset($entry['count']) ? max(0, (int) $entry['count']) : 0;

                if ($count >= $limit) {
                    return false;
                }

                $state[$identity] = array('window' => (int) $entry['window'], 'count' => $count);
            }

            foreach (array_keys($limits) as $identity) {
                ++$state[$identity]['count'];
            }

            $payload = json_encode($state);

            if (!is_string($payload)) {
                return false;
            }

            rewind($handle);
            $allowed = ftruncate($handle, 0)
                && fwrite($handle, $payload) === strlen($payload)
                && fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        return (bool) $allowed;
    }

    /**
     * Normalise an untrusted direct remote address.
     *
     * Invalid values are omitted from the shared IP quota rather than being
     * grouped into one fallback identity.
     *
     * @param   mixed  $address  Remote address value.
     *
     * @return  string
     */
    public static function normaliseRemoteAddress($address): string
    {
        if (!is_scalar($address)) {
            return '';
        }

        $address = trim((string) $address);

        return filter_var($address, FILTER_VALIDATE_IP) !== false ? $address : '';
    }
}
