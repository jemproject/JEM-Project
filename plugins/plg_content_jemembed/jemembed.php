<?php
/**
 * JEMEmbed is a Plugin to provide JEM Events in JSON format.
 * For more information visit joomlaeventmanager.net
 *
 * @package    JEM
 * @subpackage JEM Embed Plugin
 * @author     JEM Team <info@joomlaeventmanager.net>
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
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
        'type'             => 'unfinished',
        'show_featured'    => 'off',
        'max_events'       => '100',
        'catids'           => '',
        'venueids'         => '',
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
        $tokensList = array_map('trim', explode(',', $allowedTokens));
        
        // Check if the provided token is in the list of allowed tokens
        if (in_array($token, $tokensList)) {
            return true;
        }
        
        // If we've enabled CSRF token fallback, check for valid CSRF token
        $csrfFallback = (bool) $this->params->get('csrf_fallback', 0);
        if ($csrfFallback) {
            // Check if the token matches the session token (for same-origin requests)
            return Session::checkToken('get');
        }
        
        return false;
    }

    /**
     * AJAX endpoint to retrieve events in JSON format
     * Can be accessed via: index.php?option=com_ajax&plugin=jemembed&group=content&format=json&token=YOUR_API_TOKEN
     * 
     * Optional parameters:
     * - type: today, unfinished, upcoming, ongoing, archived, newest, open, all
     * - featured: on or off (or 1/0)
     * - catids: comma-separated list of category IDs
     * - venueids: comma-separated list of venue IDs
     * - max: maximum number of events to return
     * - token: API token for authentication
     */
    public function onAjaxJemembed()
    {
        // Check for valid token before processing the request
        if (!$this->validateToken()) {
            return ['success' => false, 'error' => 'Invalid or missing API token'];
        }
        
        try {
            // Get request parameters or use defaults
            $app = Factory::getApplication();
            $parameters = self::$optionDefaults;
            
            // Retrieve parameters from request
            $parameters['type'] = $app->input->getString('type', $parameters['type']);
            $parameters['show_featured'] = $app->input->getString('featured', $parameters['show_featured']);
            $parameters['max_events'] = $app->input->getInt('max', $parameters['max_events']);
            $parameters['catids'] = $app->input->getString('catids', $parameters['catids']);
            $parameters['venueids'] = $app->input->getString('venueids', $parameters['venueids']);
            
            // Load events
            $eventlist = $this->_load($parameters);
            
            // Format events for JSON output
            $events = [];
            foreach ($eventlist as $event) {
                $linkdetails = Route::_(JemHelperRoute::getEventRoute($event->slug));
                $linkdate = Route::_(JemHelperRoute::getRoute($event->dates !== null ? str_replace('-', '', $event->dates) : '', 'day'));
                $linkvenue = Route::_(JemHelperRoute::getVenueRoute($event->venueslug));
                
                $formattedEvent = [
                    'id' => $event->id,
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'description' => $event->introtext,
                    'dates' => [
                        'start_date' => $event->dates,
                        'end_date' => $event->enddates,
                        'start_time' => $event->times,
                        'end_time' => $event->endtimes,
                        'formatted_start_date' => JemOutput::formatdate($event->dates),
                        'formatted_start_time' => $event->times ? JemOutput::formattime($event->times) : ''
                    ],
                    'venue' => [
                        'id' => $event->locid,
                        'name' => $event->venue,
                        'slug' => $event->venueslug,
                        'url' => $linkvenue,
                        'city' => $event->city,
                        'state' => $event->state,
                        'country' => $event->country
                    ],
                    'categories' => $this->_formatCategories($event->categories),
                    'featured' => (bool)$event->featured,
                    'url' => $linkdetails,
                    'datelink' => $linkdate
                ];
                
                $events[] = $formattedEvent;
            }
            
            return ['success' => true, 'data' => $events];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Format categories for JSON output
     */
    protected function _formatCategories($categories)
    {
        if (!$categories) {
            return [];
        }
        
        $result = [];
        if (is_array($categories)) {
            foreach ($categories as $category) {
                if (is_object($category)) {
                    $cat = [
                        'id' => $category->id,
                        'name' => $category->catname,
                        'slug' => $category->catslug,
                        'url' => Route::_(JemHelperRoute::getCategoryRoute($category->catslug))
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

        if (isset($parameters['max_events'])) {
            $max = $parameters['max_events'];
            $model->setState('list.limit', ($max > 0) ? $max : 100);
        }

        // Filter by categories
        if (!empty($parameters['catids'])) {
            $included_cats = explode(",", $parameters['catids']);
            // Sanitize array of category IDs
            $included_cats = array_map('intval', $included_cats);
            $model->setState('filter.category_id', $included_cats);
            $model->setState('filter.category_id.include', 1);
        }

        // Filter by venues
        if (!empty($parameters['venueids'])) {
            // Sanitize venue ID directly
            $venueid = (int)$parameters['venueids'];
            $model->setState('filter.venue_id', $venueid);
            $model->setState('filter.venue_id.include', 1);
        }

        // Filter by featured status
        if ($parameters['show_featured'] == 'on' || $parameters['show_featured'] == '1') {
            $model->setState('filter.featured', 1);
        }

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
                    $model->setState('filter.calendar_to',$where);
                    break;
                default:
                case 'unfinished': // All upcoming events, incl. today.
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
                case 'newest': //newest events = events with the highest IDs
                    $model->setState('filter.published', 1);
                    $model->setState('filter.orderby', array('a.id DESC'));
                    break;
                case 'open': //open events = events with no start and enddate
                    $model->setState('filter.published', 1);
                    $model->setState('filter.orderby', array('a.id DESC'));
                    $model->setState('filter.opendates', 2);
                    break;
                case 'all': //all events
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