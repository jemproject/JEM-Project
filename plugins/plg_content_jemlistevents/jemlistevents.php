<?php
/**
 * JemListEvent is a Plugin to display events in articles.
 * For more information visit joomlaeventmanager.net
 *
 * @package    JEM
 * @subpackage JEM Listevents Plugin
 * @author     JEM Team <info@joomlaeventmanager.net>, Luis Raposo
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\Database\ParameterType;

BaseDatabaseModel::addIncludePath(JPATH_SITE.'/components/com_jem/models', 'JemModel');
require_once JPATH_SITE.'/components/com_jem/helpers/helper.php';
require_once(JPATH_SITE.'/components/com_jem/classes/output.class.php');


/**
 * JEM List Events Plugin
 *
 * @since  2.2.2
 */
class PlgContentJemlistevents extends CMSPlugin
{
    /** all options with their default values
     */
    protected static $optionDefaults = array(
            'type'              => 'unfinished',
            'show_featured'     => 'on',
            'title'             => 'on',
            'cut_title'         => 40,
            'show_date'         => 'on',
            'date_format'       => '',
            'show_time'         => 'on',
            'time_format'       => '',
            'show_enddatetime'  => 'off', // for backward compatibility
            'catids'            => '',
            'show_category'     => 'off',
            'venueids'          => '',
            'show_venue'        => 'off',
            'max_events'        => '5',
            'no_events_msg'     => '',
            );

    /** options we have to convert from numbers to 'on'/'off'
     */
    protected static $optionConvert = array('show_featured', 'show_time', 'show_enddatetime');

    /** all text tokens with their corresponding option
     */
    protected static $optionTokens = array(              //  {jemlistevents
            'type'        => 'type',                     //  [type=today|unfinished|ongoing|upcoming|archived|newest|open|all];
            'featured'    => 'show_featured',            //  [featured=on|off|only];
            'title'       => 'title',                    //  [title=on|link|off];
            'cuttitle'    => 'cut_title',                //  [cuttitle=n];
            'date'        => 'show_date',                //  [date=on|link|off];
      //    'dateformat'  => 'date_format',            //
            'time'        => 'show_time',                //  [time=on|off];
      //    'timeformat'  => 'time_format',            //
            'enddatetime' => 'show_enddatetime',         //  [enddatetime=on|off];
            'catids'      => 'catids',                   //  [catids=n];
            'category'    => 'show_category',            //  [category=on|link|off];
            'venueids'    => 'venueids',                 //  [venueids=n];
            'venue'       => 'show_venue',               //  [venue=on|link|off];
            'max'         => 'max_events',               //  [max=n];
            'noeventsmsg' => 'no_events_msg',            //  [noeventsmsg=msg]
            );                                           // }

    /** all text tokens with their corresponding option
     */
    protected static $tokenValues = array(    // {jemlistevents
            'type'        => array('today', 'unfinished', 'upcoming', 'ongoing', 'archived', 'newest', 'open', 'all'),
            'featured'    => array('on', 'off', 'only'),
            'title'       => array('on', 'link', 'off'),
            'date'        => array('on', 'link', 'off'),
            'time'        => array('on', 'off'),
            'enddatetime' => array('on', 'off'),
            'category'    => array('on', 'link', 'off'),
            'venue'       => array('on', 'link', 'off'),
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

    //    JemHelper::addFileLogger();
    } // __construct()

    /**
     * Plugin that outputs a list of events from JEM
     *
     * @param   string   $context  The context of the content being passed to the plugin.
     * @param   mixed    &$row     An object with a "text" property
     * @param   mixed    $params   Additional parameters. See {@see PlgContentContent()}.
     * @param   integer  $page     Optional page number. Unused. Defaults to zero.
     *
     * @return  boolean  True on success.
     */
    public function onContentPrepare($context, &$row, &$params, $page = 0)
    {
        // Don't run this plugin when the content is being indexed
        if ($context == 'com_finder.indexer') {
            return true;
        }

        // simple performance check to determine whether the bot should process further
        if (empty($row->text) || mb_strpos($row->text, 'jemlistevents') === false) {
            return true;
        }
        
        $templateName = Factory::getApplication()->getTemplate();
        
        // load CSS-file
        $document = Factory::getApplication()->getDocument();
        $wa = $document->getWebAssetManager();
        $templatePath = JPATH_BASE . '/templates/' . $templateName . '/css/jemlistevents.css';
        if(file_exists($templatePath)) {
            $wa->registerAndUseStyle('jemlistevents', 'templates/' . $templateName . '/css/jemlistevents.css');
            }
        else {
            $wa->registerAndUseStyle('jemlistevents', 'media/plg_content_jemlistevents/css/jemlistevents.css');
            }
            
        // expression to search for
        $regex = '/{jemlistevents\s*(.*?)}/i';

        // check whether the plugin has been unpublished
        if (!$this->params->get('enabled', 1)) {
            $row->text = preg_replace($regex, '', $row->text);
            return true;
        }

        // find all instances of plugin and put in $matches
        preg_match_all($regex, $row->text, $matches);

        // plugin only processes if there are any instances of the plugin in the text
        if ($matches) {
            $this->_process($row, $matches, $regex);
        }

        return true;
    } // onContentPrepare()

    // The proccessing function
    protected function _process(&$content, &$matches, $regex)
    {
        // Get plugin parameters
        $defaults = array();
        foreach (self::$optionDefaults as $k => $v) {
            $defaults[$k] = $this->params->def($k, $v);
            if (in_array($k, self::$optionConvert) && is_numeric($defaults[$k])) {
                $defaults[$k] = ($defaults[$k] == '0') ? 'off' : 'on';
            }
        }

        for ($i = 0; $i < count($matches[0]); ++$i)
        {
            $match = $matches[1][$i];
            $params  = $defaults;
            $options = explode(';', $match);

            foreach ($options as $option)
            {
                $option = str_replace(array('[', ']'), '', $option);
                $pair = explode("=", $option, 2);
                if (empty($pair[0]) || empty($pair[1])) {
                    continue;
                }
                $token = strtolower(trim($pair[0]));
                if (preg_match('/[ \'"]*(.*)[ \'"]*/', $pair[1], $m)) {
                    $value = $m[1];
                    // is this a known option?
                    if (array_key_exists($token, self::$optionTokens)) {
                        // option limited to specific values?
                        if (!array_key_exists($token, self::$tokenValues) || (in_array($value, self::$tokenValues[$token]))) {
                            $params[self::$optionTokens[$token]] = $value;
                        }
                    }
                }
            } // foreach options

            $eventlist     = $this->_load($params);
            $display       = $this->_display($eventlist, $params, $i);
            $content->text = str_replace($matches[0][$i], $display, $content->text);
        } // foreach matches
    } // _process()

    // The function who takes care for the 'completing' of the plugins' actions : load the events
    protected function _load($parameters)
    {
        // Retrieve Eventslist model for the data
        $model = BaseDatabaseModel::getInstance('Eventslist', 'JemModel', array('ignore_request' => true));

        if (isset($parameters['max_events'])) {
            $max = $parameters['max_events'];
            $model->setState('list.limit', ($max > 0) ? $max : 100);
        }

        /****************************
         * FILTER CATEGORIES.
         ****************************/
        if (isset($parameters['catids']) && !empty($parameters['catids'])) {
            $included_cats = explode(",", $parameters['catids']);
            // Sanitize array of category IDs
            $included_cats = array_map('intval', $included_cats);
            $model->setState('filter.category_id', $included_cats);
            $model->setState('filter.category_id.include', 1);
        }

        /****************************
         * FILTER - VENUE.
         ****************************/
        if (isset($parameters['venueids']) && !empty($parameters['venueids'])) {
            $included_venues = explode(",", $parameters['venueids']);
            // Sanitize array of venue IDs
            $included_venues = array_map('intval', $included_venues);
            $model->setState('filter.venue_id', $included_venues);
            $model->setState('filter.venue_id.include', 1);
        }

        if (isset($parameters['show_featured'])) {
            $featured = $parameters['show_featured'];

            switch ($featured) {
                case 'on': // Yes: Show both featured and non-featured events
                    // No additional filtering needed
                break;
                case 'off': // No: Show only non-featured events
                    $model->setState('filter.featured', 0);
                break;
                case 'only': // Only: Show only featured events
                    $model->setState('filter.featured', 1);
                break;
            }
        }

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
            // Log the error to the Joomla log
            JLog::add(
                sprintf('Error in JemListEvents plugin: %s (File: %s, Line: %s)', 
                    $e->getMessage(), 
                    $e->getFile(), 
                    $e->getLine()
                ), 
                JLog::ERROR, 
                'plg_content_jemlistevents'
            );

            // Show a user-friendly error message
            Factory::getApplication()->enqueueMessage(
                Text::_('PLG_CONTENT_JEMLISTEVENTS_ERROR_LOADING_EVENTS'), 
                'error'
            );
            // Set to default filter (unfinished events)
            $model->setState('filter.published', 1);
            $model->setState('filter.orderby', array('a.dates ASC', 'a.times ASC'));
        }

        //$model->setState('filter.calendar_from', $cal_from);

        $model->setState('filter.groupby', array('a.id'));

        // Retrieve the available Events.
        $rows = $model->getItems();

        return $rows;
    } // _load()

    // The function who takes care for the 'completing' of the plugins' actions : display the events.
    protected function _display($rows, $parameters, $listevents_id)
    {
        include_once JPATH_BASE."/components/com_jem/helpers/route.php";

        $html_list  = '<div class="jemlistevents" id="jemlistevents-'.$listevents_id.'">';
        $html_list .= '<table class="table table-hover table-striped">';

        // Check if there are events
        if ($rows === false) {
            $rows = array(); // to skip foreach w/o warning
        }
        
        $n_event = 0;
        
        // thead only if there are events
        if (count($rows) > 0) {
            // insert table header
            $html_list .= '<thead><tr>';

            $columns = [
                'title' => 'COM_JEM_TITLE',
                'show_date' => 'COM_JEM_DATE',
                'show_venue' => 'COM_JEM_VENUE',
                'show_category' => 'COM_JEM_CATEGORY'
            ];

            // Standard columns
            foreach ($columns as $param => $translation) {
                if ($parameters[$param] !== 'off') {
                    $html_list .= '<th>' . Text::_($translation) . '</th>';
                }
            }
            // Special handling for time column
            if ($parameters['show_time'] !== 'off') {
                $showTimeColumn = ($parameters['show_date'] === 'off' && $parameters['show_enddatetime'] !== 'off') ||
                                 ($parameters['show_enddatetime'] === 'off');
                if ($showTimeColumn) {
                       $html_list .= '<th>' . Text::_('COM_JEM_STARTTIME_SHORT') . '</th>';
                }
            }
            $html_list .= '</tr></thead>';
        }

        // Count active columns for colspan in "no events"
        $cols_count = 0;
        if ($parameters['title'] !== 'off') $cols_count++;
        if ($parameters['show_date'] !== 'off') $cols_count++;
        if ($parameters['show_venue'] !== 'off') $cols_count++;
        if ($parameters['show_category'] !== 'off') $cols_count++;
        if ($parameters['show_time'] !== 'off' && 
            (($parameters['show_date'] === 'off' && $parameters['show_enddatetime'] !== 'off') ||
             ($parameters['show_enddatetime'] === 'off'))) {
            $cols_count++;
        }
        
        // at least 1 Spalte column
        $cols_count = max(1, $cols_count);

        foreach ($rows as $event)
        {
            $linkdetails = Route::_(JemHelperRoute::getEventRoute($event->slug));
            $linkdate    = Route::_(JemHelperRoute::getRoute($event->dates !== null ? str_replace('-', '', $event->dates) : '','day'));
            $linkvenue   = Route::_(JemHelperRoute::getVenueRoute($event->venueslug));

            $featured_class = isset($event->featured) && $event->featured ? ' jemlistevent-featured' : '';
            $html_list .= '<tr class="listevent event'.($n_event + 1).$featured_class.'">';

            if ($parameters['title'] !== 'off') {
                $html_list .= '<td class="eventtitle" data-label="' . Text::_('COM_JEM_TITLE') . '">';
                $html_list .= (($parameters['title'] === 'link') ? ('<a href="'.$linkdetails.'">') : '');
                $fulltitle  = htmlspecialchars($event->title, ENT_COMPAT, 'UTF-8');
                if (mb_strlen($fulltitle) > $parameters['cut_title']) {
                    $title = mb_substr($fulltitle, 0, $parameters['cut_title']).'&nbsp;…';
                } else {
                    $title = $fulltitle;
                }
                $html_list .= $title;
                $html_list .= (($parameters['title'] === 'link') ? '</a>' : '');
                $html_list .= '</td>';
            }

            if (($parameters['show_enddatetime'] === 'off') || ($parameters['show_date'] === 'off')) {
                if ($parameters['show_date'] !== 'off') {
                    // Display startdate.
                    $html_list .= '<td class="eventdate" data-label="' . Text::_('COM_JEM_DATE') . '">';
                    if ($event->dates) {
                        $html_list .= (($parameters['show_date'] === 'link') ? ('<a href="'.$linkdate.'">') : '');
                        $html_list .= JemOutput::formatdate($event->dates, $parameters['date_format']);
                        $html_list .= (($parameters['show_date'] === 'link') ? '</a>' : '');
                    }
                    $html_list .= '</td>';
                }

                if ($parameters['show_time'] !== 'off') {
                    // Display starttime.
                    $html_list .= ' '.'<td class="eventtime" data-label="' . Text::_('COM_JEM_STARTTIME') . '">';
                    if ($event->times) {
                        $html_list .= JemOutput::formattime($event->times, $parameters['time_format']);
                    }
                    // Display endtime if requested.
                    if ($event->endtimes && ($parameters['show_enddatetime'] !== 'off')) {
                        $html_list .= ' - ' . JemOutput::formattime($event->endtimes, $parameters['time_format']);
                    }
                    $html_list .= '</td>';
                }
            } else { // single column with all start/end date/time values
                $params = array(
                    'dateStart' => $event->dates,
                    'timeStart' => $event->times,
                    'dateEnd' => $event->enddates,
                    'timeEnd' => $event->endtimes,
                    'dateFormat' => $parameters['date_format'],
                    'timeFormat' => $parameters['time_format'],
                    'showTime' => $parameters['show_time'] !== 'off',
                    'showDayLink' => $parameters['show_date'] === 'link',
                    );

                // Display start/end date/time.
                $html_list .= ' '.'<td class="eventdatetime" data-label="' . Text::_('COM_JEM_STARTTIME_SHORT') . '">';
                $html_list .= JemOutput::formatDateTime($params);
                $html_list .= '</td>';
            }

            if ($parameters['show_venue'] !== 'off') {
                $html_list .= '<td class="eventvenue" data-label="' . Text::_('COM_JEM_VENUE') . '">';
                if ($event->venue) {
                    $html_list .= (($parameters['show_venue'] === 'link') ? ('<a href="'.$linkvenue.'">') : '');
                    $html_list .= $event->venue;
                    $html_list .= (($parameters['show_venue'] === 'link') ? '</a>' : '');
                }
                $html_list .= '</td>';
            }

            if ($parameters['show_category'] !== 'off') {
                if ($parameters['show_category'] === 'link') {
                    $catlink = 1;
                } else {
                    $catlink = false;
                }

                $html_list .= '<td class="eventcategory" data-label="' . Text::_('COM_JEM_CATEGORY') . '">';
                if ($event->categories) {
                    $html_list .= implode(", ", JemOutput::getCategoryList($event->categories, $catlink));
                }
                $html_list .= "</td>";
            }

            $html_list .= '</tr>';

            $n_event++;
            if ((int)$parameters['max_events'] && ($n_event >= (int)$parameters['max_events'])) {
                break;
            }
        } // foreach rows

        if ($n_event === 0) {
            $html_list .= '<tr><td colspan="' . $cols_count . '" class="no-events-message">';
            $html_list .= $parameters['no_events_msg'];
            $html_list .= '</td></tr>';
        }

        $html_list .= '</table>';
        $html_list .= '</div>';

        return $html_list;
    } // _display()
}
