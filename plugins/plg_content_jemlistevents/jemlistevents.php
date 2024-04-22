<?php
/**
 * JemListEvent is a Plugin to display events in articles.
 * For more information visit joomlaeventmanager.net
 *
 * @version    4.2.1
 * @package    JEM
 * @subpackage JEM_Listevents_Plugin
 * @author     JEM Team <info@joomlaeventmanager.net>, Luis Raposo
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

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
			'type'             => 'unfinished',
		    'show_featured'    => 'off',
			'title'            => 'on',
			'cut_title'        => 40,
			'show_date'        => 'on',
			'date_format'      => '',
			'show_time'        => 'on',
			'time_format'      => '',
			'show_enddatetime' => 'off', // for backward compatibility
			'catids'           => '',
			'show_category'    => 'off',
			'venueids'         => '',
			'show_venue'       => 'off',
			'max_events'       => '5',
			'no_events_msg'    => '',
			);

	/** options we have to convert from numbers to 'on'/'off'
	 */
	protected static $optionConvert = array('show_featured', 'show_time', 'show_enddatetime');

	/** all text tokens with their corresponding option
	 */
	protected static $optionTokens = array(      // {jemlistevents
			'type'        => 'type',             //  [type=today|unfinished|upcoming|archived|newest];
		    'featured'    => 'show_featured',    //  [featured=on|off];
			'title'       => 'title',            //  [title=on|link|off];
			'cuttitle'    => 'cut_title',        //  [cuttitle=n];
			'date'        => 'show_date',        //  [date=on|link|off];
		//	'dateformat'  => 'date_format',      //
			'time'        => 'show_time',        //  [time=on|off];
		//	'timeformat'  => 'time_format',      //
			'enddatetime' => 'show_enddatetime', //  [enddatetime=on|off];
			'catids'      => 'catids',           //  [catids=n];
			'category'    => 'show_category',    //  [category=on|link|off];
			'venueids'    => 'venueids',         //  [venueids=n];
			'venue'       => 'show_venue',       //  [venue=on|link|off];
			'max'         => 'max_events',       //  [max=n];
			'noeventsmsg' => 'no_events_msg',    //  [noeventsmsg=msg]
			);                                   // }

	/** all text tokens with their corresponding option
	 */
	protected static $tokenValues = array(    // {jemlistevents
			'type'        => array('today', 'unfinished', 'upcoming', 'archived', 'newest'),
		    'featured'    => array('on', 'off'),
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

	//	JemHelper::addFileLogger();
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
		if (!isset($row->text) || \Joomla\String\StringHelper::strpos($row->text, 'jemlistevents') === false) {
			return true;
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
		if (isset($parameters['catids'])) {
			$catids = $parameters['catids'];

			if ($catids) {
				$included_cats = explode(",", $catids);// make change in default code by jwsolution
				$model->setState('filter.category_id', $included_cats);
				$model->setState('filter.category_id.include', 1);
			}
		}

		/****************************
		 * FILTER - VENUE.
		 ****************************/
		if (isset($parameters['venueids'])) {
			$venueids = $parameters['venueids'];

			if ($venueids) {
				$model->setState('filter.venue_id', $venueids);
				$model->setState('filter.venue_id.include', 1);
			}
		}

		if (isset($parameters['show_featured'])) {
			$featured = $parameters['show_featured'];

			if ($featured == 'on') {
				$model->setState('filter.featured', 1);
			}
		}

		$type = $parameters['type'];
		$offset_hourss = 0;

		switch ($type) {
		case 'today': // All events starting today.
			//$offset_minutes = ($offset_hourss * 60);
			$timestamp = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
			$to_date = date('Y-m-d', $timestamp);
			$model->setState('filter.published', 1);
			$model->setState('filter.orderby', array('a.dates ASC', 'a.times ASC'));
			$where = ' DATEDIFF (a.dates, "'. $to_date .'") = 0';
			$model->setState('filter.calendar_to',$where);
			//$cal_from  = "((TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(a.dates,' ',IFNULL(a.times,'00:00:00'))) > $offset_minutes) ";
			//$cal_from .= ($type === 1) ? " OR (TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(IFNULL(a.enddates,a.dates),' ',IFNULL(a.endtimes,'23:59:59'))) > $offset_minutes)) " : ") ";
			break;
		default:
		case 'unfinished': // All upcoming events, incl. today.
			//$offset_minutes = ($offset_hourss * 60);
			$timestamp = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
			$model->setState('filter.published', 1);
			$model->setState('filter.orderby', array('a.dates ASC', 'a.times ASC'));
			$timestamp = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
			$to_date = date('Y-m-d', $timestamp);
            $where = ' (DATEDIFF (a.dates, "'. $to_date .'") = 0 AND a.enddates IS null) OR (DATEDIFF (a.dates, "'. $to_date .'") <= 0 AND DATEDIFF (a.enddates, "'. $to_date .'") >= 0)';
			$model->setState('filter.calendar_to', $where);
			break;
		case 'upcoming': // All upcoming events, excl. today.
			$timestamp = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
			$offset_minutes = ($offset_hourss * 60);
			$model->setState('filter.published', 1);
			$model->setState('filter.orderby', array('a.dates ASC', 'a.times ASC'));
			$to_date = date('Y-m-d', $timestamp);
			$where = ' DATEDIFF (a.dates, "'. $to_date .'") > 0';
			$model->setState('filter.calendar_to', $where);
			break;
		case 'archived': // Archived events only.
			$model->setState('filter.published', 2);
			$model->setState('filter.orderby', array('a.dates DESC', 'a.times DESC'));
			$cal_from = "";
			break;
		case 'newest': //newest events = events with the highest IDs
			$model->setState('filter.published', 1);
			$model->setState('filter.orderby', array('a.id DESC'));
			break;
		} // switch type

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

		if ($rows === false) {
			$rows = array(); // to skip foreach w/o warning
		}

		$n_event = 0;
		foreach ($rows as $event)
		{
			$linkdetails = JRoute::_(JemHelperRoute::getEventRoute($event->slug));
			$linkdate    = JRoute::_(JemHelperRoute::getRoute(str_replace('-', '', $event->dates), 'day'));
			$linkvenue   = JRoute::_(JemHelperRoute::getVenueRoute($event->venueslug));

			$html_list .= '<tr class="listevent event'.($n_event + 1).'">';

			if ($parameters['title'] !== 'off') {
				$html_list .= '<td class="eventtitle">';
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
					$html_list .= '<td class="eventdate">';
					if ($event->dates) {
						$html_list .= (($parameters['show_date'] === 'link') ? ('<a href="'.$linkdate.'">') : '');
						$html_list .= JemOutput::formatdate($event->dates, $parameters['date_format']);
						$html_list .= (($parameters['show_date'] === 'link') ? '</a>' : '');
					}
					$html_list .= '</td>';
				}

				if ($parameters['show_time'] !== 'off') {
					// Display starttime.
					$html_list .= ' '.'<td class="eventtime">';
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
				$html_list .= ' '.'<td class="eventdatetime">';
				$html_list .= JemOutput::formatDateTime($params);
				$html_list .= '</td>';
			}

			if ($parameters['show_venue'] !== 'off') {
				$html_list .= '<td class="eventvenue">';
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

				$html_list .= "<td>";
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
			$html_list .= $parameters['no_events_msg'];
		}

		$html_list .= '</table>';
		$html_list .= '</div>';

		return $html_list;
	} // _display()

}
