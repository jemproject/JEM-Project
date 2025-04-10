<?php
declare(strict_types=1);

// Secure Handling of HTTP-Host-Header
$host = $_SERVER['HTTP_HOST'];
$isSecure = ($_SERVER['HTTPS'] ?? '') === 'on' 
    || ($_SERVER['SERVER_PORT'] ?? '') == 443;
$currentUrl = ($isSecure ? 'https://' : 'http://') . $host;

// Security Headers
header("Access-Control-Allow-Origin: $currentUrl"); // to allow all domains use header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300'); // Cache for 5 minutes
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ##########################################
// Configuration - EDIT BELOW
// ##########################################

// URL of the Joomla source website with the JEM events
const SOURCE_DOMAIN = 'https://www.example.com/';  // Include https:// and trailing slash!

// API token, set in the jemembed plugin settings of the Joomla source website
const SECURITY_TOKEN = 'SECURITY_TOKEN'; 

// Cache settings
const CACHE_ENABLED = true;       // Set to false to disable caching
const CACHE_TTL = 300;            // Cache lifetime in seconds (5 minutes)

// Parameters to define which events are loaded
$config = [
    'type' => 'unfinished', // today|unfinished|upcoming|archived|newest
    'show_featured' => 'off', // 0 = off, 1 = on, 2 = only
    'title' => 'link', // on|link|off
    'cut_title' => 100, // a positive number
    'show_date' => 'on', // on|link|off
    'date_format' => '', // 
    'show_time' => 'on', // on|off
    'time_format' => '', // 
    'show_enddatetime' => 'on', // on|off
    'catids' => '', // comma separated category ids
    'show_category' => 'on', // on|link|off
    'venueids' => '', // comma separated venue ids
    'show_venue' => 'on', // on|link|off
    'max_events' => 100 // a positive number
];

// ############################################
// END Configuration - DO NOT EDIT BELOW
// ############################################

final class JemEventsFetcher
{
    private const URL_BASE_PATH = 'index.php?option=com_ajax&plugin=jemembed&group=content&format=json';
    private const DEFAULT_USER_AGENT = 'JemEventsProxy/1.0 (+https://github.com/your-repo)';
    private const CURL_TIMEOUT = 15;

    public function __construct(
        private string $sourceDomain,
        private string $token,
        private array $config,
        private bool $cacheEnabled = true,
        private int $cacheTtl = 300
    ) {
        $this->validateDomain($sourceDomain);
        $this->sourceDomain = rtrim($sourceDomain, '/') . '/';
    }

    public function fetchEvents(): void
    {
        $feedUrl = $this->buildFeedUrl();

        // Try to get from cache first
        if ($this->cacheEnabled) {
            $cachedResponse = $this->getCachedResponse($feedUrl);
            if ($cachedResponse !== null) {
                echo $cachedResponse;
                return;
            }
        }
        
        // No cached data, fetch from remote
        $ch = $this->initCurl($feedUrl);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        $this->handleResponse($response, $httpCode, $feedUrl, $ch);
        curl_close($ch);
    }

    private function buildFeedUrl(): string
    {
        $params = array_merge(
            ['token' => $this->token],
            $this->config
        );
        
        return $this->sourceDomain . self::URL_BASE_PATH . '&' . 
            http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function initCurl(string $url): CurlHandle
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR => false,
            CURLOPT_USERAGENT => self::DEFAULT_USER_AGENT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => self::CURL_TIMEOUT,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Encoding: gzip',
                'X-Forwarded-For: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
            ]
        ]);
        return $ch;
    }

    private function handleResponse($response, int $httpCode, string $feedUrl, CurlHandle $ch): void
    {
        if (curl_errno($ch)) {
            $this->outputError('Proxy error: ' . curl_error($ch), $feedUrl);
            return;
        }

        if ($httpCode >= 400) {
            $this->outputError("HTTP error: $httpCode", $feedUrl);
            return;
        }

        $this->validateAndProcessJson($response, $feedUrl);
    }

    private function validateAndProcessJson(string $response, string $url): void
    {
        json_decode($response);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->outputError('Invalid JSON: ' . json_last_error_msg(), $url);
            return;
        }

        // Cache valid response if caching is enabled
        if ($this->cacheEnabled) {
            $this->setCachedResponse($url, $response);
        }

        echo $response;
    }

    private function validateDomain(string $domain): void
    {
        if (!filter_var($domain, FILTER_VALIDATE_URL)) {
            $this->outputError('Invalid source domain format');
            exit;
        }

        if (parse_url($domain, PHP_URL_SCHEME) !== 'https') {
            $this->outputError('Source domain must use HTTPS');
            exit;
        }
    }

    private function getCachedResponse(string $url): ?string
    {
        $cacheKey = md5($url);
        if (function_exists('apcu_exists') && apcu_exists($cacheKey)) {
            return apcu_fetch($cacheKey);
        }
        return null;
    }

    private function setCachedResponse(string $url, string $response): void
    {
        if (function_exists('apcu_store')) {
            $cacheKey = md5($url);
            apcu_store($cacheKey, $response, $this->cacheTtl);
        }
    }

    private function outputError(string $message, string $url = ''): void
    {
        $error = ['error' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8')];
        if ($url) {
            $error['url'] = $url;
        }
        echo json_encode($error);
        $this->logError($message, $url);
    }

    private function logError(string $message, string $url): void
    {
        // Optional: Error-Logging implementieren
        // error_log("JemEventsFetcher error: $message, URL: $url");
    }
}

try {
    $fetcher = new JemEventsFetcher(
        SOURCE_DOMAIN, 
        SECURITY_TOKEN, 
        $config,
        CACHE_ENABLED,
        CACHE_TTL
    );
    $fetcher->fetchEvents();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Service unavailable']);
    // Optional: Log the actual error for debugging
    // error_log("JemEventsFetcher critical error: " . $e->getMessage());
    exit;
}