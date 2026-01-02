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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\CMS\User\User;

BaseDatabaseModel::addIncludePath(JPATH_SITE.'/components/com_jem/models', 'JemModel');
require_once JPATH_SITE.'/components/com_jem/helpers/helper.php';
require_once(JPATH_SITE.'/components/com_jem/classes/output.class.php');
require_once JPATH_SITE.'/components/com_jem/helpers/route.php';

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
    );

    /** all valid token values */
    protected static $tokenValues = array(
        'type'          => array('today', 'unfinished', 'upcoming', 'ongoing', 'archived', 'newest', 'open', 'all'),
        'featured'      => array('on', 'off'),
        'title'         => array('on', 'link', 'off'),
        'date'          => array('on', 'link', 'off'),
        'time'          => array('on', 'off'),
        'enddatetime'   => array('on', 'off'),
        'category'      => array('on', 'link', 'off'),
        'venue'         => array('on', 'link', 'off'),
    );

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
     * @return bool True if token is valid, false otherwise
     */
    protected function validateToken()
    {
        $app = Factory::getApplication();
        $token = $app->input->getString('token', '');
        
        // Check if we require token validation based on plugin settings
        $requireToken = (bool) $this->params->get('require_token', 1);
        
        // If token validation is disabled, always return true
        if (!$requireToken) {
            return true;
        }
        
        // Get the allowed API tokens from plugin parameters
        $allowedTokens = $this->params->get('api_tokens', '');
        
        $tokensList = array_filter(array_map('trim', explode(',', (string) $allowedTokens)));

        // Only check if tokens are actually present
        if (!empty($tokensList) && in_array($token, $tokensList, true)) {
            return true;
        }
        return false;
    }

    /**
     * Validate and clean input parameters
     * 
     * @param array $params The input parameters
     * @return array The validated and cleaned parameters
     */
    protected function validateParams($params)
    {
        $cleanParams = $params;
        
        // Validate type parameter
        if (isset($cleanParams['type']) && !in_array($cleanParams['type'], self::$tokenValues['type'])) {
            $cleanParams['type'] = self::$optionDefaults['type'];
        }
        
        // Validate boolean-like parameters
        $boolParams = ['show_featured', 'title', 'show_date', 'show_time', 'show_enddatetime', 'show_category', 'show_venue'];
        foreach ($boolParams as $param) {
            if (isset($cleanParams[$param])) {
                if ($cleanParams[$param] === '1') {
                    $cleanParams[$param] = 'on';
                } elseif ($cleanParams[$param] === '0') {
                    $cleanParams[$param] = 'off';
                }
            }
        }
        
        // Validate parameters with specific allowed values
        $valueParams = ['title', 'show_date', 'show_category', 'show_venue'];
        foreach ($valueParams as $param) {
            $tokenName = str_replace('show_', '', $param);
            if (isset($cleanParams[$param]) && !in_array($cleanParams[$param], self::$tokenValues[$tokenName])) {
                $cleanParams[$param] = self::$optionDefaults[$param];
            }
        }
        
        // Ensure numeric parameters are positive integers
        $numericParams = ['max_events', 'cut_title'];
        foreach ($numericParams as $param) {
            if (isset($cleanParams[$param])) {
                $cleanParams[$param] = max(1, (int)$cleanParams[$param]);
            }
        }
        
        return $cleanParams;
    }

    /**
     * Get the site domain for absolute URLs
     * 
     * @return string The site domain with protocol
     */
    protected function getSiteDomain()
    {
        $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                      $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        return rtrim($protocol . $_SERVER['HTTP_HOST'], '/');
    }

    /**
     * AJAX endpoint to retrieve events in JSON format
     * Can be accessed via: index.php?option=com_ajax&plugin=jemembed&group=content&format=json&token=YOUR_SECURITY_TOKEN
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
     * - max: maximum number of events to return
     * - cuttitle: maximum length of title before truncation
     * - token: API token for authentication
     */
    public function onAjaxJemembed()
    {
        // Check for valid token before processing the request
        if (!$this->validateToken()) {
            return ['success' => false, 'error' => 'Invalid or missing API token'];
        }
        
        try {
            // Get request parameters
            $app = Factory::getApplication();
            $parameters = self::$optionDefaults;
            
            // Get site domain for absolute URLs
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
                'dateformat' => 'date_format',
                'timeformat' => 'time_format'
            ];
            
            // Get parameters from request
            foreach ($paramMapping as $requestParam => $internalParam) {
                if ($app->input->exists($requestParam)) {
                    $parameters[$internalParam] = $app->input->getString($requestParam);
                }
            }
            
            // Validate and clean parameters
            $parameters = $this->validateParams($parameters);
            
            // Load events
            $eventlist = $this->_load($parameters);
            
            // Format events for JSON output
            $events = [];
            foreach ($eventlist as $event) {
                $linkdetails = $siteDomain . Route::_(JemHelperRoute::getEventRoute($event->slug));
                $linkdate = $siteDomain . Route::_(JemHelperRoute::getRoute($event->dates !== null ? str_replace('-', '', $event->dates) : '', 'day'));
                $linkvenue = $siteDomain . Route::_(JemHelperRoute::getVenueRoute($event->venueslug));
                
                // Format title based on parameters
                $fulltitle = htmlspecialchars($event->title, ENT_COMPAT, 'UTF-8');
                $displayTitle = $fulltitle;
                if (mb_strlen($fulltitle) > $parameters['cut_title']) {
                    $displayTitle = mb_substr($fulltitle, 0, $parameters['cut_title']) . '…';
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
                    'description' => $event->introtext,
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
            
            // Include the query parameters in the response
            return [
                'success' => true, 
                'meta' => [
                    'count' => count($events),
                    'parameters' => $parameters
                ],
                'data' => $events
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
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
        
        // If no siteDomain was passed, get it
        if (empty($siteDomain)) {
            $siteDomain = $this->getSiteDomain();
        }
        
        $result = [];
        if (is_array($categories)) {
            foreach ($categories as $category) {
                if (is_object($category)) {
                    $cat = [
                        'id' => $category->id,
                        'name' => $category->catname,
                        'slug' => $category->catslug,
                        'url' => $siteDomain . Route::_(JemHelperRoute::getCategoryRoute($category->catslug)),
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

        // Set max events limit
        if (isset($parameters['max_events']) && is_numeric($parameters['max_events'])) {
            $max = (int)$parameters['max_events'];
            $model->setState('list.limit', ($max > 0) ? $max : 100);
        }

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
        $db = Factory::getDbo();
        $timestamp = time();

        try {
            switch ($type) {
                case 'today': // All events starting today.
                    $to_date = date('Y-m-d', $timestamp);
                    $model->setState('filter.published', 1);
                    $model->setState('filter.orderby', array('a.dates ASC', 'a.times ASC'));
                    $where = ' DATEDIFF (a.dates, "'. $to_date .'") = 0';
                    $model->setState('filter.calendar_to', $where);
                    break;
                default:
                case 'unfinished': // All upcoming events, incl. today. (Default filter)
                    $to_date = date('Y-m-d H:i:s', $timestamp);
                    $model->setState('filter.published', 1);
                    $model->setState('filter.orderby', array('a.dates ASC', 'a.times ASC'));
                    $full_end_datetime = 'CONCAT(COALESCE(a.enddates, a.dates), " ", COALESCE(a.endtimes, "23:59:59"))';
                    $where = '(' . $full_end_datetime . ' > "' . $to_date . '")';
                    $model->setState('filter.calendar_to', $where);
                    break;
                case 'upcoming': // All upcoming events, excl. today.
                    $to_date = date('Y-m-d H:i:s', $timestamp);
                    $model->setState('filter.published', 1);
                    $model->setState('filter.orderby', array('a.dates ASC', 'a.times ASC'));
                    $full_start_datetime = 'CONCAT(a.dates, " ", COALESCE(a.times, "00:00:00"))';
                    $where = '(' . $full_start_datetime . ' > "' . $to_date . '")';
                    $model->setState('filter.calendar_to', $where);
                    break;
                case 'ongoing': // All now ongoing events.
                    $to_date = date('Y-m-d H:i:s', $timestamp);
                    $model->setState('filter.published', 1);
                    $model->setState('filter.orderby', array('a.dates ASC', 'a.times ASC'));
                    $full_start_datetime = 'CONCAT(a.dates, " ", COALESCE(a.times, "00:00:00"))';
                    $full_end_datetime = 'CONCAT(COALESCE(a.enddates, a.dates), " ", COALESCE(a.endtimes, "23:59:59"))';
                    $where = '(' . $full_start_datetime . ' <= "' . $to_date . '" AND ' . $full_end_datetime . ' >= "' . $to_date . '")';
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
        } catch (\Exception $e) {
            // Log the error
            Factory::getApplication()->enqueueMessage(
                sprintf('Error in JemEmbed plugin: %s', $e->getMessage()),
                'error'
            );
            
            // Set to default filter (unfinished events)
            $model->setState('filter.published', 1);
            $model->setState('filter.orderby', array('a.dates ASC', 'a.times ASC'));
        }

        $model->setState('filter.groupby', array('a.id'));

        // Retrieve the available Events.
        return $model->getItems();
    }
}