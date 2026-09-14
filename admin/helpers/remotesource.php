<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Downloads reviewed remote import sources without allowing the destination
 * to escape to a private or otherwise non-public network address.
 */
class JemRemoteSourceHelper
{
    const ERROR_INVALID_URL = 'COM_JEM_IMPORT_EXTERNAL_URL_INVALID';
    const ERROR_UNSUPPORTED = 'COM_JEM_IMPORT_EXTERNAL_URL_UNSUPPORTED';
    const ERROR_DOWNLOAD_FAILED = 'COM_JEM_IMPORT_EXTERNAL_URL_DOWNLOAD_FAILED';
    const ERROR_TOO_LARGE = 'COM_JEM_IMPORT_EXTERNAL_URL_TOO_LARGE';

    const DEFAULT_MAX_BYTES = 10485760;
    const MAX_REDIRECTS = 3;
    const MAX_URL_LENGTH = 2048;
    const MAX_HEADER_BYTES = 65536;
    const MAX_ADDRESS_ATTEMPTS = 4;
    const CONNECT_TIMEOUT = 10;
    const REQUEST_TIMEOUT = 20;

    /**
     * Download a remote source after validating and pinning every network hop.
     *
     * The optional resolver/requester callbacks exist for deterministic tests;
     * production callers must omit them.
     *
     * @return array{body:string, final_url:string, extension:string, name:string, headers:array}
     */
    public static function download(
        $url,
        array $allowedExtensions,
        $preferredExtension = '',
        $maxBytes = self::DEFAULT_MAX_BYTES,
        $resolver = null,
        $requester = null
    ) {
        $allowedExtensions = array_values(array_unique(array_filter(array_map(static function ($extension) {
            $extension = strtolower(trim((string) $extension));

            return preg_match('/^[a-z0-9]{1,10}$/', $extension) ? $extension : '';
        }, $allowedExtensions))));

        if (!$allowedExtensions) {
            throw new RuntimeException(self::ERROR_UNSUPPORTED);
        }

        $maxBytes = (int) $maxBytes;
        if ($maxBytes < 1) {
            throw new RuntimeException(self::ERROR_TOO_LARGE);
        }

        if ($resolver !== null && !is_callable($resolver)) {
            throw new InvalidArgumentException('The remote source resolver must be callable.');
        }

        if ($requester !== null && !is_callable($requester)) {
            throw new InvalidArgumentException('The remote source requester must be callable.');
        }

        $currentUrl = trim((string) $url);
        $preferredExtension = strtolower(trim((string) $preferredExtension));
        $initialScheme = '';
        $visited = array();

        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            $target = self::inspectUrl(
                $currentUrl,
                $allowedExtensions,
                $preferredExtension,
                $resolver
            );

            if ($initialScheme === '') {
                $initialScheme = $target['scheme'];
            } elseif ($initialScheme === 'https' && $target['scheme'] !== 'https') {
                throw new RuntimeException(self::ERROR_INVALID_URL);
            }

            if (isset($visited[$target['url']])) {
                throw new RuntimeException(self::ERROR_DOWNLOAD_FAILED);
            }
            $visited[$target['url']] = true;

            $response = $requester !== null
                ? call_user_func($requester, $target, $maxBytes)
                : self::requestTarget($target, $maxBytes);
            $response = self::normaliseResponse($response, $maxBytes);
            $status = (int) $response['status'];

            if (in_array($status, array(301, 302, 303, 307, 308), true)) {
                if ($redirects >= self::MAX_REDIRECTS) {
                    throw new RuntimeException(self::ERROR_DOWNLOAD_FAILED);
                }

                $location = self::getHeaderValue($response['headers'], 'location');
                if ($location === '') {
                    throw new RuntimeException(self::ERROR_DOWNLOAD_FAILED);
                }

                $preferredExtension = $target['extension'];
                $currentUrl = self::resolveRedirectUrl($target['url'], $location);
                continue;
            }

            if ($status < 200 || $status >= 300 || $response['body'] === '') {
                throw new RuntimeException(self::ERROR_DOWNLOAD_FAILED);
            }

            return array(
                'body' => $response['body'],
                'final_url' => $target['url'],
                'extension' => $target['extension'],
                'name' => self::buildSourceName($target),
                'headers' => $response['headers'],
            );
        }

        throw new RuntimeException(self::ERROR_DOWNLOAD_FAILED);
    }

    /**
     * Validate a URL, resolve all of its addresses and select only public ones.
     *
     * @return array{url:string,scheme:string,host:string,port:int,path:string,query:string,extension:string,addresses:array,literal_ip:bool}
     */
    public static function inspectUrl($url, array $allowedExtensions, $preferredExtension = '', $resolver = null)
    {
        $url = trim((string) $url);
        if ($url === '' || strlen($url) > self::MAX_URL_LENGTH || preg_match('/[\x00-\x20\x7f]/', $url)) {
            throw new RuntimeException(self::ERROR_INVALID_URL);
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new RuntimeException(self::ERROR_INVALID_URL);
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, array('http', 'https'), true)
            || !empty($parts['user'])
            || !empty($parts['pass'])
            || empty($parts['host'])) {
            throw new RuntimeException(self::ERROR_INVALID_URL);
        }

        $host = strtolower(trim((string) $parts['host'], '[]'));
        $host = rtrim($host, '.');
        if (!self::isValidHost($host)) {
            throw new RuntimeException(self::ERROR_INVALID_URL);
        }

        $defaultPort = $scheme === 'https' ? 443 : 80;
        $port = isset($parts['port']) ? (int) $parts['port'] : $defaultPort;
        if ($port !== $defaultPort) {
            throw new RuntimeException(self::ERROR_INVALID_URL);
        }

        $path = (string) ($parts['path'] ?? '/');
        $path = $path === '' ? '/' : $path;
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        $query = (string) ($parts['query'] ?? '');

        $allowedExtensions = array_values(array_unique(array_map('strtolower', $allowedExtensions)));
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $preferredExtension = strtolower(trim((string) $preferredExtension));
        if ($extension === '' && $preferredExtension !== '') {
            $extension = $preferredExtension;
        }
        if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException(self::ERROR_UNSUPPORTED);
        }

        $literalIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        if ($literalIp) {
            $addresses = array($host);
        } else {
            $addresses = $resolver !== null
                ? call_user_func($resolver, $host)
                : self::resolveHost($host);
        }

        if (!is_array($addresses) || !$addresses) {
            throw new RuntimeException(self::ERROR_DOWNLOAD_FAILED);
        }

        $validatedAddresses = array();
        foreach ($addresses as $address) {
            $address = strtolower(trim((string) $address, '[]'));
            if (filter_var($address, FILTER_VALIDATE_IP) === false || !self::isPublicIp($address)) {
                throw new RuntimeException(self::ERROR_INVALID_URL);
            }
            $validatedAddresses[$address] = $address;
        }

        if (!$validatedAddresses) {
            throw new RuntimeException(self::ERROR_DOWNLOAD_FAILED);
        }

        $urlHost = strpos($host, ':') !== false ? '[' . $host . ']' : $host;
        $normalisedUrl = $scheme . '://' . $urlHost . $path;
        if ($query !== '') {
            $normalisedUrl .= '?' . $query;
        }

        if (filter_var($normalisedUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException(self::ERROR_INVALID_URL);
        }

        return array(
            'url' => $normalisedUrl,
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'path' => $path,
            'query' => $query,
            'extension' => $extension,
            'addresses' => array_values($validatedAddresses),
            'literal_ip' => $literalIp,
        );
    }

    /**
     * Return true only for globally routable IPv4 or IPv6 addresses.
     */
    public static function isPublicIp($ip)
    {
        $ip = strtolower(trim((string) $ip, '[]'));
        if ($ip === '' || strpos($ip, '%') !== false || strpos($ip, '::ffff:') === 0) {
            return false;
        }

        $filterFlags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
        if (defined('FILTER_FLAG_GLOBAL_RANGE')) {
            $filterFlags |= FILTER_FLAG_GLOBAL_RANGE;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, $filterFlags) === false) {
            return false;
        }

        if (strpos($ip, ':') !== false && !self::isIpInCidr($ip, '2000::/3')) {
            return false;
        }

        $blockedCidrs = strpos($ip, ':') === false
            ? array(
                '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8',
                '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
                '192.31.196.0/24', '192.52.193.0/24', '192.88.99.0/24',
                '192.168.0.0/16', '192.175.48.0/24', '198.18.0.0/15', '198.51.100.0/24',
                '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
            )
            : array(
                '::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96',
                '64:ff9b:1::/48', '100::/64', '100:0:0:1::/64', '2001::/23',
                '2001:db8::/32', '2002::/16', '2620:4f:8000::/48',
                '3fff::/20', '5f00::/16', 'fc00::/7', 'fe80::/10', 'ff00::/8',
            );

        foreach ($blockedCidrs as $cidr) {
            if (self::isIpInCidr($ip, $cidr)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve an HTTP Location header without trusting the next destination.
     * The resulting URL is validated again by inspectUrl() before it is used.
     */
    public static function resolveRedirectUrl($baseUrl, $location)
    {
        $location = trim((string) $location);
        if ($location === '' || strlen($location) > self::MAX_URL_LENGTH || preg_match('/[\x00-\x20\x7f]/', $location)) {
            throw new RuntimeException(self::ERROR_INVALID_URL);
        }

        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $base = parse_url((string) $baseUrl);
        if (!is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            throw new RuntimeException(self::ERROR_INVALID_URL);
        }

        if (strpos($location, '//') === 0) {
            return strtolower((string) $base['scheme']) . ':' . $location;
        }

        $host = trim((string) $base['host'], '[]');
        $authority = strpos($host, ':') !== false ? '[' . $host . ']' : $host;
        if (!empty($base['port'])) {
            $authority .= ':' . (int) $base['port'];
        }
        $origin = strtolower((string) $base['scheme']) . '://' . $authority;

        if ($location[0] === '?') {
            return $origin . ((string) ($base['path'] ?? '/')) . $location;
        }

        if ($location[0] === '#') {
            $query = isset($base['query']) ? '?' . $base['query'] : '';

            return $origin . ((string) ($base['path'] ?? '/')) . $query . $location;
        }

        $locationParts = parse_url($location);
        if ($locationParts === false || isset($locationParts['scheme']) || isset($locationParts['host'])) {
            throw new RuntimeException(self::ERROR_INVALID_URL);
        }

        $locationPath = (string) ($locationParts['path'] ?? '');
        if ($locationPath === '' || $locationPath[0] !== '/') {
            $basePath = (string) ($base['path'] ?? '/');
            $directory = substr($basePath, 0, strrpos($basePath, '/') + 1);
            $locationPath = $directory . $locationPath;
        }
        $locationPath = self::normalisePath($locationPath);
        $query = isset($locationParts['query']) ? '?' . $locationParts['query'] : '';

        return $origin . $locationPath . $query;
    }

    public static function getErrorLanguageKeys()
    {
        return array(
            self::ERROR_INVALID_URL,
            self::ERROR_UNSUPPORTED,
            self::ERROR_DOWNLOAD_FAILED,
            self::ERROR_TOO_LARGE,
        );
    }

    protected static function isValidHost($host)
    {
        if ($host === '' || strlen($host) > 253 || preg_match('/[\x00-\x20\x7f%]/', $host)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        return preg_match(
            '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/i',
            $host
        ) === 1;
    }

    protected static function isIpInCidr($ip, $cidr)
    {
        list($network, $prefixLength) = explode('/', (string) $cidr, 2);
        $packedIp = @inet_pton((string) $ip);
        $packedNetwork = @inet_pton($network);
        $prefixLength = (int) $prefixLength;

        if ($packedIp === false || $packedNetwork === false || strlen($packedIp) !== strlen($packedNetwork)) {
            return false;
        }

        $maxBits = strlen($packedIp) * 8;
        if ($prefixLength < 0 || $prefixLength > $maxBits) {
            return false;
        }

        $wholeBytes = intdiv($prefixLength, 8);
        if ($wholeBytes > 0 && substr($packedIp, 0, $wholeBytes) !== substr($packedNetwork, 0, $wholeBytes)) {
            return false;
        }

        $remainingBits = $prefixLength % 8;
        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xff << (8 - $remainingBits)) & 0xff;

        return (ord($packedIp[$wholeBytes]) & $mask) === (ord($packedNetwork[$wholeBytes]) & $mask);
    }

    protected static function resolveHost($host)
    {
        $addresses = array();

        if (function_exists('dns_get_record')) {
            $types = 0;
            if (defined('DNS_A')) {
                $types |= DNS_A;
            }
            if (defined('DNS_AAAA')) {
                $types |= DNS_AAAA;
            }

            if ($types) {
                $records = @dns_get_record($host, $types);
                if (is_array($records)) {
                    foreach ($records as $record) {
                        if (!empty($record['ip'])) {
                            $addresses[] = $record['ip'];
                        }
                        if (!empty($record['ipv6'])) {
                            $addresses[] = $record['ipv6'];
                        }
                    }
                }
            }
        }

        $ipv4 = @gethostbynamel($host);
        if (is_array($ipv4)) {
            $addresses = array_merge($addresses, $ipv4);
        }

        return array_values(array_unique(array_filter(array_map('strval', $addresses))));
    }

    protected static function requestTarget(array $target, $maxBytes)
    {
        $deadline = microtime(true) + self::REQUEST_TIMEOUT;
        $addresses = array_slice($target['addresses'], 0, self::MAX_ADDRESS_ATTEMPTS);

        foreach ($addresses as $address) {
            $remainingSeconds = $deadline - microtime(true);
            if ($remainingSeconds <= 0) {
                break;
            }

            $response = function_exists('curl_init')
                ? self::requestWithCurl($target, $address, $maxBytes, $remainingSeconds)
                : self::requestWithStream($target, $address, $maxBytes, $remainingSeconds);

            if ($response !== null) {
                return $response;
            }
        }

        throw new RuntimeException(self::ERROR_DOWNLOAD_FAILED);
    }

    protected static function requestWithCurl(array $target, $address, $maxBytes, $timeoutSeconds)
    {
        $handle = curl_init();
        if ($handle === false) {
            return null;
        }

        $body = '';
        $headers = array();
        $currentHeaders = array();
        $headerBytes = 0;
        $tooLarge = false;
        $headerOverflow = false;

        $headerCallback = static function ($curl, $line) use (&$headers, &$currentHeaders, &$headerBytes, &$headerOverflow, &$tooLarge, $maxBytes) {
            $length = strlen($line);
            $headerBytes += $length;
            if ($headerBytes > self::MAX_HEADER_BYTES) {
                $headerOverflow = true;
                return 0;
            }

            $trimmed = trim($line);
            if (preg_match('#^HTTP/\S+\s+\d{3}#i', $trimmed)) {
                $currentHeaders = array();
            } elseif ($trimmed === '') {
                $headers = $currentHeaders;
            } elseif (strpos($line, ':') !== false) {
                list($name, $value) = explode(':', $line, 2);
                $name = strtolower(trim($name));
                $value = trim($value);
                if ($name !== '') {
                    $currentHeaders[$name][] = $value;
                    if ($name === 'content-length' && ctype_digit($value) && (int) $value > $maxBytes) {
                        $tooLarge = true;
                        return 0;
                    }
                }
            }

            return $length;
        };

        $writeCallback = static function ($curl, $chunk) use (&$body, &$tooLarge, $maxBytes) {
            $length = strlen($chunk);
            if (strlen($body) + $length > $maxBytes) {
                $tooLarge = true;
                return 0;
            }
            $body .= $chunk;

            return $length;
        };

        $timeoutSeconds = max(1, (int) ceil((float) $timeoutSeconds));
        $options = array(
            CURLOPT_URL => $target['url'],
            CURLOPT_HTTPGET => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_HEADERFUNCTION => $headerCallback,
            CURLOPT_WRITEFUNCTION => $writeCallback,
            CURLOPT_CONNECTTIMEOUT => min(self::CONNECT_TIMEOUT, $timeoutSeconds),
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_USERAGENT => 'JEM remote import',
            CURLOPT_HTTPHEADER => array(
                'Accept: text/csv, application/json, application/xml, text/xml, text/calendar, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/octet-stream;q=0.5',
                'Accept-Encoding: identity',
                'Connection: close',
            ),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROXY => '',
            CURLOPT_NOSIGNAL => true,
        );

        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }

        if (!$target['literal_ip']) {
            $resolvedAddress = strpos($address, ':') !== false ? '[' . $address . ']' : $address;
            $options[CURLOPT_RESOLVE] = array($target['host'] . ':' . $target['port'] . ':' . $resolvedAddress);
        }

        if (strpos($address, ':') !== false && defined('CURL_IPRESOLVE_V6')) {
            $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V6;
        } elseif (defined('CURL_IPRESOLVE_V4')) {
            $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }

        curl_setopt_array($handle, $options);
        $success = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($tooLarge) {
            throw new RuntimeException(self::ERROR_TOO_LARGE);
        }
        if ($success === false || $headerOverflow || $status < 100) {
            return null;
        }

        return array('status' => $status, 'headers' => $headers, 'body' => $body);
    }

    protected static function requestWithStream(array $target, $address, $maxBytes, $timeoutSeconds)
    {
        $timeoutSeconds = max(0.1, (float) $timeoutSeconds);
        $deadline = microtime(true) + $timeoutSeconds;
        $connectHost = strpos($address, ':') !== false ? '[' . $address . ']' : $address;
        $connectUrl = $target['scheme'] . '://' . $connectHost . ':' . $target['port'] . $target['path'];
        if ($target['query'] !== '') {
            $connectUrl .= '?' . $target['query'];
        }

        $hostHeader = strpos($target['host'], ':') !== false ? '[' . $target['host'] . ']' : $target['host'];
        $context = stream_context_create(array(
            'http' => array(
                'follow_location' => 0,
                'max_redirects' => 0,
                'ignore_errors' => true,
                'method' => 'GET',
                'protocol_version' => 1.1,
                'timeout' => min(self::CONNECT_TIMEOUT, $timeoutSeconds),
                'user_agent' => 'JEM remote import',
                'header' => "Host: {$hostHeader}\r\nAccept-Encoding: identity\r\nConnection: close\r\n",
            ),
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $target['host'],
                'SNI_enabled' => true,
            ),
        ));

        $stream = @fopen($connectUrl, 'rb', false, $context);
        if (!is_resource($stream)) {
            return null;
        }

        $metadata = stream_get_meta_data($stream);
        $wrapperData = (array) ($metadata['wrapper_data'] ?? array());
        $headerBytes = 0;
        foreach ($wrapperData as $line) {
            $headerBytes += strlen((string) $line) + 2;
            if ($headerBytes > self::MAX_HEADER_BYTES) {
                fclose($stream);
                return null;
            }
        }

        $headers = self::parseStreamHeaders($wrapperData);
        $contentLength = self::getHeaderValue($headers, 'content-length');
        if ($contentLength !== '' && ctype_digit($contentLength) && (int) $contentLength > $maxBytes) {
            fclose($stream);
            throw new RuntimeException(self::ERROR_TOO_LARGE);
        }

        $body = '';
        while (!feof($stream)) {
            $remainingSeconds = $deadline - microtime(true);
            if ($remainingSeconds <= 0) {
                fclose($stream);
                return null;
            }

            $seconds = (int) floor($remainingSeconds);
            $microseconds = (int) (($remainingSeconds - $seconds) * 1000000);
            stream_set_timeout($stream, $seconds, $microseconds);
            $chunk = fread($stream, 8192);
            $metadata = stream_get_meta_data($stream);
            if (!empty($metadata['timed_out'])) {
                fclose($stream);
                return null;
            }
            if ($chunk === false) {
                fclose($stream);
                return null;
            }
            if ($chunk === '' && !feof($stream)) {
                fclose($stream);
                return null;
            }
            if (strlen($body) + strlen($chunk) > $maxBytes) {
                fclose($stream);
                throw new RuntimeException(self::ERROR_TOO_LARGE);
            }
            $body .= $chunk;
        }
        fclose($stream);

        return array(
            'status' => (int) ($headers[':status'][0] ?? 0),
            'headers' => $headers,
            'body' => $body,
        );
    }

    protected static function parseStreamHeaders(array $lines)
    {
        $headers = array();
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $line, $match)) {
                $headers = array(':status' => array($match[1]));
                continue;
            }
            if (strpos($line, ':') === false) {
                continue;
            }
            list($name, $value) = explode(':', $line, 2);
            $name = strtolower(trim($name));
            if ($name !== '') {
                $headers[$name][] = trim($value);
            }
        }

        return $headers;
    }

    protected static function normaliseResponse($response, $maxBytes)
    {
        if (!is_array($response)) {
            throw new RuntimeException(self::ERROR_DOWNLOAD_FAILED);
        }

        $body = (string) ($response['body'] ?? '');
        if (strlen($body) > $maxBytes) {
            throw new RuntimeException(self::ERROR_TOO_LARGE);
        }

        return array(
            'status' => (int) ($response['status'] ?? 0),
            'headers' => is_array($response['headers'] ?? null) ? $response['headers'] : array(),
            'body' => $body,
        );
    }

    protected static function getHeaderValue(array $headers, $name)
    {
        $name = strtolower((string) $name);
        foreach ($headers as $headerName => $values) {
            if (strtolower((string) $headerName) !== $name) {
                continue;
            }

            if (is_array($values)) {
                return trim((string) end($values));
            }

            return trim((string) $values);
        }

        return '';
    }

    protected static function normalisePath($path)
    {
        $segments = explode('/', (string) $path);
        $normalised = array();
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($normalised);
                continue;
            }
            $normalised[] = $segment;
        }

        return '/' . implode('/', $normalised);
    }

    protected static function buildSourceName(array $target)
    {
        $name = rawurldecode(basename((string) $target['path']));
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name);
        $name = trim((string) $name, '.-');
        if ($name === '' || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== $target['extension']) {
            $name = 'catalog-source.' . $target['extension'];
        }

        return substr($name, 0, 180);
    }
}
