<?php
/**
 * JEMEmbed is a Plugin to provide JEM Events in JSON format.
 * For more information visit joomlaeventmanager.net
 *
 * @package    JEM
 * @subpackage JEM Embed Plugin
 * @author     JEM Team <info@joomlaeventmanager.net>
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Log\Log;

$helper = JPATH_SITE . '/components/com_jem/helpers/helper.php';
$output = JPATH_SITE . '/components/com_jem/classes/output.class.php';
$route  = JPATH_SITE . '/components/com_jem/helpers/route.php';
$policy = __DIR__ . '/requestpolicy.php';

if (!is_file($helper) || !is_file($output) || !is_file($route) || !is_file($policy)) {
    return;
}

BaseDatabaseModel::addIncludePath(JPATH_SITE.'/components/com_jem/models', 'JemModel');
require_once $helper;
require_once $output;
require_once $route;
require_once $policy;

/**
 * JEM List Events Plugin - JSON API Version
 */
class PlgContentJemembed extends CMSPlugin
{
    /** all options with their default values */
    protected static $optionDefaults = array(
        'type'              => 'unfinished',
        'show_featured'     => 'off',
        'title'             => 'on',
        'cut_title'         => 100,
        'show_date'         => 'on',
        'date_format'       => '',
        'show_time'         => 'on',
        'time_format'       => '',
        'show_enddatetime'  => 'on',
        'catids'            => '',
        'show_category'     => 'on',
        'venueids'          => '',
        'show_venue'        => 'on',
        'max_events'        => '100',
        'start'             => '0',
    );

    private const RATE_WINDOW_SECONDS = 60;
    private const RATE_LIMIT_PER_IP = 60;
    private const RATE_LIMIT_PER_CREDENTIAL = 300;
    private const RESPONSE_CACHE_MINUTES = 1;

    /**
     * Constructor
     * @param object $subject The object to observe
     * @param array  $config  An array that holds the plugin configuration
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage();
        $this->loadLanguage('com_jem', JPATH_ADMINISTRATOR.'/components/com_jem');
    }

    /**
     * Validate the API token
     * 
     * @return string|false Validated token, an empty public identity, or false
     */
    protected function validateToken()
    {
        $app = Factory::getApplication();
        $requireToken = (bool) $this->params->get('require_token', 1);
        $authorization = trim((string) $app->input->server->get('HTTP_AUTHORIZATION', '', 'raw'));

        if ($authorization === '') {
            $authorization = trim((string) $app->input->server->get('REDIRECT_HTTP_AUTHORIZATION', '', 'raw'));
        }

        $token = '';

        if ($authorization !== '' && preg_match('/^Bearer[\x20\x09]+(.+)$/i', $authorization, $matches)) {
            $token = trim($matches[1]);
        }

        if (!$requireToken && $authorization === '') {
            return '';
        }

        if ($token === '' || !JemEmbedRequestPolicy::tokenMatches(
            $token,
            (string) $this->params->get('api_tokens', '')
        )) {
            return false;
        }

        return $token;
    }

    /**
     * Get the administrator-configured origin for absolute URLs.
     */
    protected function getSiteDomain()
    {
        return JemEmbedRequestPolicy::normaliseBaseUrl($this->params->get('base_url', ''));
    }

    /**
     * AJAX endpoint to retrieve events in JSON format
     * Authenticate with: Authorization: Bearer YOUR_SECURITY_TOKEN
     * 
     * Optional parameters:
     * - type: today, unfinished, upcoming, ongoing, archived, newest, open, all
     * - featured: on or off
     * - title: on, link, off
     * - date: on, link, off
     * - time: on, off
     * - enddatetime: on, off
     * - catids: comma-separated list of category IDs
     * - category: on, link, off
     * - venueids: comma-separated list of venue IDs
     * - venue: on, link, off
     * - max: maximum number of events to return (1-100)
     * - start: result offset (0-10000)
     * - cuttitle: maximum length of title before truncation
     */
    public function onAjaxJemembed()
    {
        $app = Factory::getApplication();
        $method = strtoupper((string) $app->input->server->getCmd('REQUEST_METHOD', 'GET'));
        $query = (string) $app->input->server->getString('QUERY_STRING', '');

        if ($method !== 'GET' || !JemEmbedRequestPolicy::isQueryStringAllowed($query)) {
            return $this->publicError('Request could not be processed.');
        }

        $clientAddress = (string) $app->input->server->getString('REMOTE_ADDR', 'unknown');

        if (filter_var($clientAddress, FILTER_VALIDATE_IP) === false) {
            $clientAddress = 'unknown';
        }

        if (!$this->consumeRateLimit('ip:' . $clientAddress, self::RATE_LIMIT_PER_IP)) {
            return $this->publicError('Request limit exceeded.');
        }

        // URL credentials are deliberately rejected to keep secrets out of logs,
        // browser history, referrers and intermediary caches.
        if ($app->input->exists('token')) {
            return $this->publicError('Authentication required.');
        }

        $allowedRequestParameters = array(
            'option', 'plugin', 'group', 'format', 'type', 'featured', 'title',
            'cuttitle', 'date', 'time', 'enddatetime', 'catids', 'category',
            'venueids', 'venue', 'max', 'start', 'dateformat', 'timeformat', 'lang',
        );
        $unknownParameters = array_diff(array_keys($app->input->getArray()), $allowedRequestParameters);

        if ($unknownParameters) {
            return $this->publicError('Request could not be processed.');
        }

        if ($app->input->exists('lang')
            && !preg_match('/^[a-z]{2,3}(?:-[a-z]{2})?$/i', $app->input->getString('lang', ''))) {
            return $this->publicError('Request could not be processed.');
        }

        $token = $this->validateToken();

        if ($token === false) {
            return $this->publicError('Authentication required.');
        }

        $credentialIdentity = $token === '' ? 'public' : hash('sha256', $token);

        if (!$this->consumeRateLimit('credential:' . $credentialIdentity, self::RATE_LIMIT_PER_CREDENTIAL)) {
            return $this->publicError('Request limit exceeded.');
        }

        try {
            $parameters = self::$optionDefaults;
            $siteDomain = $this->getSiteDomain();
            
            // Map request parameters to internal parameters
            $paramMapping = [
                'type' => 'type',
                'featured' => 'show_featured',
                'title' => 'title',
                'cuttitle' => 'cut_title',
                'date' => 'show_date',
                'time' => 'show_time',
                'enddatetime' => 'show_enddatetime',
                'catids' => 'catids',
                'category' => 'show_category',
                'venueids' => 'venueids',
                'venue' => 'show_venue',
                'max' => 'max_events',
                'start' => 'start',
                'dateformat' => 'date_format',
                'timeformat' => 'time_format'
            ];
            
            // Get parameters from request
            foreach ($paramMapping as $requestParam => $internalParam) {
                if ($app->input->exists($requestParam)) {
                    $parameters[$internalParam] = $app->input->getString($requestParam);
                }
            }
            
            $parameters = JemEmbedRequestPolicy::normaliseParameters($parameters);
            $cacheData = array($parameters, $siteDomain, $app->getLanguage()->getTag());
            $cacheKey = hash('sha256', json_encode($cacheData) ?: serialize($cacheData));
            $cachedResponse = $this->getCachedResponse($cacheKey);

            if (is_array($cachedResponse)) {
                return $cachedResponse;
            }

            $eventlist = $this->_load($parameters);
            
            // Format events for JSON output
            $events = [];
            foreach ($eventlist as $event) {
                $linkdetails = $this->buildUrl(Route::_(JemHelperRoute::getEventRoute($event->slug)), $siteDomain);
                $linkdate = $this->buildUrl(
                    Route::_(JemHelperRoute::getRoute($event->dates !== null ? str_replace('-', '', $event->dates) : '', 'day')),
                    $siteDomain
                );
                $linkvenue = $this->buildUrl(Route::_(JemHelperRoute::getVenueRoute($event->venueslug)), $siteDomain);
                
                // Format title based on parameters
                $fulltitle = htmlspecialchars($event->title, ENT_COMPAT, 'UTF-8');
                $displayTitle = $fulltitle;
                if (mb_strlen($fulltitle) > $parameters['cut_title']) {
                    $displayTitle = mb_substr($fulltitle, 0, max(1, $parameters['cut_title'] - 1)) . '…';
                }
                
                // Build the formatted event data
                $formattedEvent = [
                    'id' => $event->id,
                    'title' => [
                        'full' => $fulltitle,
                        'display' => $displayTitle,
                        'url' => $linkdetails,
                        'display_mode' => $parameters['title']
                    ],
                    'slug' => $event->slug,
                    'description' => JemEmbedRequestPolicy::truncateDescription($event->introtext),
                    'featured' => (bool)$event->featured,
                    'dates' => [
                        'start_date' => $event->dates,
                        'end_date' => $event->enddates,
                        'start_time' => $event->times,
                        'end_time' => $event->endtimes,
                        'formatted_start_date' => JemOutput::formatdate($event->dates, $parameters['date_format']),
                        'formatted_start_time' => $event->times ? JemOutput::formattime($event->times, $parameters['time_format']) : '',
                        'formatted_end_time' => $event->endtimes ? JemOutput::formattime($event->endtimes, $parameters['time_format']) : '',
                        'date_url' => $linkdate,
                        'date_display_mode' => $parameters['show_date'],
                        'time_display_mode' => $parameters['show_time'],
                        'enddatetime_display_mode' => $parameters['show_enddatetime']
                    ]
                ];
                
                // Add venue details if it exists
                if ($event->venue) {
                    $formattedEvent['venue'] = [
                        'id' => $event->locid,
                        'name' => $event->venue,
                        'slug' => $event->venueslug,
                        'url' => $linkvenue,
                        'city' => $event->city,
                        'state' => $event->state,
                        'country' => $event->country,
                        'display_mode' => $parameters['show_venue']
                    ];
                } else {
                    $formattedEvent['venue'] = null;
                }
                
                // Add categories
                $formattedEvent['categories'] = $this->_formatCategories($event->categories, $parameters['show_category'], $siteDomain);
                
                $events[] = $formattedEvent;
            }
            
            $nextStart = count($events) === $parameters['max_events']
                && ($parameters['start'] + count($events)) <= JemEmbedRequestPolicy::MAX_START
                ? $parameters['start'] + count($events)
                : null;
            $response = [
                'success' => true, 
                'meta' => [
                    'count' => count($events),
                    'next_start' => $nextStart,
                    'parameters' => $parameters
                ],
                'data' => $events
            ];

            $this->storeCachedResponse($cacheKey, $response);

            return $response;
        } catch (\Throwable $e) {
            $this->logInternal('JEM Embed request failed: ' . $e->getMessage(), Log::ERROR);

            return $this->publicError('Request could not be processed.');
        }
    }

    protected function consumeRateLimit(string $identity, int $limit): bool
    {
        return JemEmbedRequestPolicy::consumeRateLimit(
            JPATH_CACHE . '/plg_content_jemembed_rate',
            $identity,
            $limit,
            self::RATE_WINDOW_SECONDS
        );
    }

    protected function publicError(string $message): array
    {
        return array('success' => false, 'error' => $message);
    }

    protected function buildUrl(string $route, string $siteDomain): string
    {
        if ($siteDomain === '' || preg_match('#^https?://#i', $route)) {
            return $route;
        }

        return rtrim($siteDomain, '/') . '/' . ltrim($route, '/');
    }

    protected function getCachedResponse(string $cacheKey)
    {
        try {
            $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController(
                'output',
                array(
                    'defaultgroup' => 'plg_content_jemembed',
                    'lifetime' => self::RESPONSE_CACHE_MINUTES,
                    'caching' => true,
                    'storage' => 'file',
                )
            );

            return $cache->get($cacheKey);
        } catch (\Throwable $e) {
            $this->logInternal('JEM Embed cache read failed: ' . $e->getMessage(), Log::WARNING);

            return false;
        }
    }

    protected function storeCachedResponse(string $cacheKey, array $response): void
    {
        try {
            $cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController(
                'output',
                array(
                    'defaultgroup' => 'plg_content_jemembed',
                    'lifetime' => self::RESPONSE_CACHE_MINUTES,
                    'caching' => true,
                    'storage' => 'file',
                )
            );
            $cache->store($response, $cacheKey);
        } catch (\Throwable $e) {
            $this->logInternal('JEM Embed cache write failed: ' . $e->getMessage(), Log::WARNING);
        }
    }

    protected function logInternal(string $message, int $level): void
    {
        try {
            Log::add($message, $level, 'plg_content_jemembed');
        } catch (\Throwable $e) {
            // Logging must never alter the public endpoint response.
        }
    }
    
    /**
     * Format categories for JSON output
     * 
     * @param array $categories The categories to format
     * @param string $displayMode The display mode (on, link, off)
     * @param string $siteDomain The site domain for absolute URLs
     * @return array The formatted categories
     */
    protected function _formatCategories($categories, $displayMode = 'off', $siteDomain = '')
    {
        if (!$categories) {
            return [];
        }
        
        $result = [];
        if (is_array($categories)) {
            foreach (array_slice($categories, 0, JemEmbedRequestPolicy::MAX_FILTER_IDS) as $category) {
                if (is_object($category)) {
                    $cat = [
                        'id' => $category->id,
                        'name' => $category->catname,
                        'slug' => $category->catslug,
                        'url' => $this->buildUrl(
                            Route::_(JemHelperRoute::getCategoryRoute($category->catslug)),
                            $siteDomain
                        ),
                        'display_mode' => $displayMode
                    ];
                    $result[] = $cat;
                }
            }
        }
        
        return $result;
    }

    /**
     * Load events based on parameters
     */
    protected function _load($parameters)
    {
        // Retrieve Eventslist model for the data
        $model = BaseDatabaseModel::getInstance('Eventslist', 'JemModel', array('ignore_request' => true));
        $guest = JemFactory::getUser(0);

        // The feed is always evaluated as a public visitor. A logged-in Joomla
        // session or a configured locked-access preview must never widen it.
        $model->setState('filter.access_levels', $guest->getAuthorisedViewLevels());
        $model->setState('filter.strict_access', true);

        // Set max events limit
        $model->setState('list.limit', (int) $parameters['max_events']);
        $model->setState('list.start', (int) $parameters['start']);

        // Filter by categories
        if (!empty($parameters['catids'])) {
            $included_cats = explode(",", $parameters['catids']);
            // Sanitize array of category IDs
            $included_cats = array_filter(array_map('intval', $included_cats));
            if (!empty($included_cats)) {
                $model->setState('filter.category_id', $included_cats);
                $model->setState('filter.category_id.include', 1);
            }
        }

        // Filter by venues
        if (!empty($parameters['venueids'])) {
            // Parse comma-separated venue IDs
            $venue_ids = explode(",", $parameters['venueids']);
            // Sanitize array of venue IDs
            $venue_ids = array_filter(array_map('intval', $venue_ids));
            if (!empty($venue_ids)) {
                $model->setState('filter.venue_id', $venue_ids);
                $model->setState('filter.venue_id.include', 1);
            }
        }

        // Filter by featured status
        if ($parameters['show_featured'] == 'on' || $parameters['show_featured'] == '1') {
            $model->setState('filter.featured', 1);
        } elseif ($parameters['show_featured'] == 'off' || $parameters['show_featured'] == '0') {
            // Explicitly show only non-featured events
            $model->setState('filter.featured', 0);
        }
        // If nothing specified, we show all events (featured and non-featured)

        // Set type filters
        $type = isset($parameters['type']) ? $parameters['type'] : 'unfinished';
        switch ($type) {
                case 'today': // All events starting today.
                    $to_date = JemHelper::getJoomlaDate();
                    $model->setState('filter.published', 1);
                    $model->setState('filter.orderby', array('a.dates ASC', 'a.times ASC'));
                    $where = ' DATEDIFF (a.dates, "'. $to_date .'") = 0';
                    $model->setState('filter.calendar_to', $where);
                    break;
                default:
                case 'unfinished': // All upcoming events, incl. today. (Default filter)
                    $model->setState('filter.published', 1);
                    $model->setState('filter.orderby', array('a.start_utc ASC', 'a.dates ASC', 'a.times ASC'));
                    $where = JemHelper::getEventDateTimeWhere('end', '>');
                    $model->setState('filter.calendar_to', $where);
                    break;
                case 'upcoming': // All upcoming events, excl. today.
                    $model->setState('filter.published', 1);
                    $model->setState('filter.orderby', array('a.start_utc ASC', 'a.dates ASC', 'a.times ASC'));
                    $where = JemHelper::getEventDateTimeWhere('start', '>');
                    $model->setState('filter.calendar_to', $where);
                    break;
                case 'ongoing': // All now ongoing events.
                    $model->setState('filter.published', 1);
                    $model->setState('filter.orderby', array('a.start_utc ASC', 'a.dates ASC', 'a.times ASC'));
                    $where = '(' . JemHelper::getEventDateTimeWhere('start', '<=') . ' AND ' . JemHelper::getEventDateTimeWhere('end', '>=') . ')';
                    $model->setState('filter.calendar_to', $where);
                    break;
                case 'archived': // Archived events only.
                    $model->setState('filter.published', 2);
                    $model->setState('filter.orderby', array('a.dates DESC', 'a.times DESC'));
                    break;
                case 'newest': // Newest events = events with the highest IDs.
                    $model->setState('filter.published', 1);
                    $model->setState('filter.orderby', array('a.id DESC'));
                    break;
                case 'open': // Open events = events with no start and end date.
                    $model->setState('filter.published', 1);
                    $model->setState('filter.orderby', array('a.id DESC'));
                    $model->setState('filter.opendates', 2);
                    break;
                case 'all': // All events.
                    $model->setState('filter.published', array(1, 2));
                    $model->setState('filter.orderby', array('a.dates ASC', 'a.times ASC'));
                    $model->setState('filter.opendates', 1);
                    break;
        }

        $model->setState('filter.groupby', array('a.id'));

        // Retrieve the available Events.
        return $model->getItems();
    }
}
