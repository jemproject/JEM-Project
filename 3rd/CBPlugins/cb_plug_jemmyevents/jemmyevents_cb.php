<?php
/**
 * @package    My Events
 * @version    2.8.0 (for JEM 4 & CB v2.8)
 * @author     JEM Community
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * Just a note:
 * Keep the query code inline with my-events view
 *
 */

defined('_JEXEC') or die;

@include_once (JPATH_SITE.'/components/com_jem/classes/image.class.php');
@include_once (JPATH_SITE.'/components/com_jem/classes/Zebra_Image.php');
@include_once (JPATH_SITE.'/components/com_jem/classes/output.class.php');
@include_once (JPATH_SITE.'/components/com_jem/helpers/helper.php');
@include_once (JPATH_SITE.'/components/com_jem/helpers/route.php');


class jemmyeventsTab extends cbTabHandler {

	protected $_jemFound = false;

	// does #__jem_register table has a column 'state'
	protected $_found_state_field = false;

	/**
	 * Show My Events
	 */
	function __construct()
	{
		global $_CB_database;

		// Check if JEM is installed.
		$this->_jemFound = class_exists('JemImage') && class_exists('JemOutput') && class_exists('JemHelperRoute');

		parent::__construct();

		$reg_fields = $_CB_database->getTableFields('#__jem_register');
		$this->_found_state_field = array_key_exists('status', $reg_fields['#__jem_register']);
	}


	/**
	 * Retrieve the languagefile
	 * The file is located in the folder language
	 */
	function _getLanguageFile()
	{
		global $_CB_framework;

		/* need JEM's language file, e.g. for JEMOutput::formatDateTime() */
		$lang = JFactory::getLanguage();
		$lang->load('com_jem', JPATH_BASE.'/components/com_jem');

		$UElanguagePath = dirname(__FILE__);
		if (file_exists($UElanguagePath.'/language/'.$_CB_framework->getCfg('lang').'.php')) {
			include_once($UElanguagePath.'/language/'.$_CB_framework->getCfg('lang').'.php');
		} else {
			include_once($UElanguagePath.'/language/english.php');
		}
	}

	/**
	 * Returns a list of categories corresponding to given event id.
	 * @param  int   $eventId ID of the event
	 * @param  array $levels  List of view access levels
	 * @return array List of categories
	 */
	protected function getCategories($eventId, $levels)
	{
		/* loading global variables */
		global $_CB_database;

		if (!is_array($levels) || empty($levels)) {
			$levels = array(1);
		}

		$query = 'SELECT DISTINCT c.id AS catid, c.catname,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
				. ' FROM #__jem_categories AS c'
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.catid = c.id'
				. ' WHERE rel.itemid = '.(int)$eventId
				. ' AND c.published = 1'
				. ' AND c.access IN (' . implode(',', $levels) . ')'
		;
		$_CB_database->setQuery($query);
		$results = $_CB_database->loadObjectList();

		return $results;
	}

	/**
	 * Creates and returns the sql query.
	 */
	protected function _getQuery($user, $fast = false)
	{
		$userid = $user->id;

		// Support Joomla access levels instead of single group id
		// Note: $user is one which profile is requested, not the asking user!
		//       $juser is the asking user which view access levels must be used.
		$juser  = JFactory::getUser();
		$levels = $juser->getAuthorisedViewLevels();

		$myprofile = !$juser->guest && ($juser->get('id') == $userid); // true if both users are equal

		$where_pub = 'a.published = 1';
		// maybe user would like to see published, unpublished, and archived own events
		//$where_pub = 'a.published ' . ($myprofile ? 'IN (0,1,2)' : '= 1');
		// TODO: Then we should show icon indicating unpublished or archived events

		$query      = 'SELECT DISTINCT a.id';
		if (!$fast) {
			$query .= '  AS eventid, a.id, a.dates, a.datimage, a.enddates, a.times, a.endtimes, a.title, a.created, a.locid,'
			        . ' CONCAT(a.introtext,a.fulltext) AS text, a.published, a.registra, a.maxplaces, a.waitinglist,'
					. ' l.venue, l.city, l.state, l.url,'
			        . ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug'
			        . ',CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
			        ;
		}
		$query     .= ' FROM `#__jem_events` AS a '
			        . ' LEFT JOIN `#__jem_venues` AS l ON l.id = a.locid '
			        . ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id '
			        . ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid '
		            . ' WHERE ' . $where_pub . ' AND a.created_by = ' . $userid . '  AND a.access IN (' . implode(',', $levels) . ')'
		            . '  AND c.published = 1 AND c.access IN (' . implode(',', $levels) . ')'
			        . ' GROUP BY a.id'
			        . ' ORDER BY a.dates, a.times'
			        ;

		return $query;
	}


	/**
	 * Append number of events to the title.
	 *
	 * since CB 2.0, on CB 1.9 it's simply not called
	 */
	public function getTabTitle($tab, $user, $ui, $postdata, $reason=NULL)
	{
		/* loading global variables */
		global $_CB_database;

		$total = 0;

		if ($this->_jemFound) {
			$query = $this->_getQuery($user, true);
			$_CB_database->setQuery($query);
			$result = $_CB_database->loadResultArray();
			$total = !empty($result) ? count($result) : 0;
			return parent::getTabTitle($tab, $user, $ui, $postdata) . ' <span class="badge badge-default">' . (int)$total . '</span>';
		}

		return parent::getTabTitle($tab, $user, $ui, $postdata);
	}

	/**
	 * Display Tab
	 */
	function getDisplayTab($tab, $user, $ui)
	{
		/* loading global variables */
		global $_CB_database, $_CB_framework;

		if (!$this->_jemFound) {
			return '';
		}

		/* loading the language function */
		self::_getLanguageFile();

		// Support Joomla access levels instead of single group id
		// Note: $user is one which profile is requested, not the asking user!
		//       $juser is the asking user which view access levels must be used.
		$juser     = JFactory::getUser();
		$levels    = $juser->getAuthorisedViewLevels();
		$myprofile = !empty($user->id) && ($juser->get('id') == $user->id); // true if both users are equal

		/*loading params set by the backend*/
		$params = $this->params;

		/* message at the bottom of the table */
		$event_tab_message = $params->get('hwTabMessage', "");

		/* other variables */
		$return = null;

		$event_image = $params->get('event_image');
		$end_date = $params->get('end_date');
		$start_date = $params->get('start_date');
		$date_combi = $params->get('date_combi');
		$event_place = $params->get('event_place');
		$event_categories = $params->get('event_categories');
		// Show attendee "statistic" to event owner only.
		$event_attending = $myprofile && $params->get('event_attending');

		/* load css */
		$_CB_framework->document->addHeadStyleSheet(dirname(__FILE__).'/jemmyevents_cb.css');

		/*
		 * Tab description
		 *
		 * the text will be on top of the table
		 * can be filled in the backend, section: Tab management
		 */

		if (!empty($tab->description)) {
			// html content is allowed in descriptions
			$return .= "\t\t<div class=\"tab_Description\">". $tab->description . "</div>\n";
		}

		/*
		 * Query
		 *
		 * Retrieval of the data
		 * Keep it inline with the my-events view
		 */
		$query = $this->_getQuery($user);
		$_CB_database->setQuery($query);
		$results = $_CB_database->loadObjectList();

		/* Headers
		 *
		 * The classes are retrieved from:
		 * components/com_comprofiler/plugin/user/plug_cbjemmyevents/jemmyevents_cb.css
		 *
		 * The language strings are retrieved from:
		 * components/com_comprofiler/plugin/user/plug_cbjemmyevents/language/*languagecode*
		 *
		 * defining a new language can be done like:
		 * - add a new string, like: _JEMMYEVENTS_NEWNAME
		 * - add the translation to the language file
		 */

		/* start of form */
		$return .= "\n\t<form method=\"post\" name=\"jemmyeventsForm\">";

		/* Start of Table */
		$return .= "\n\t<table  class='jemmyeventsCBTabTable'>";
		$return .= "\n\t<table  class='table table-hover mb-0'>";
		/* start of headerline */
		$return .= "\n\t\t<tr class='jemmyeventstableheader'>";
		$span = 0;

		/* start of imagefield */
		if ($event_image) {
			$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableTitle'>";
			$return .= "\n\t\t\t\t" . CBTxt::T( 'JEMMYEVENTS_IMAGE', 'Image' );
			$return .= "\n\t\t\t</th>";
			++$span;
		}

		/* Title header */
		$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableTitle'>";
		$return .= "\n\t\t\t\t" . CBTxt::T( 'JEMMYEVENTS_TITLE', 'Title' );
		$return .= "\n\t\t\t</th>";
		++$span;

		/* Category header */
		if ($event_categories) {
			$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableCat'>";
			$return .= "\n\t\t\t\t" . CBTxt::T( 'JEMMYEVENTS_CATEGORY', 'Category' );
			$return .= "\n\t\t\t</th>";
			++$span;
		}

		/* Startdate and Enddate combined column header */
		if ($date_combi) {
			$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableStartEnd'>";
			$return .= "\n\t\t\t\t" . CBTxt::T( 'JEMMYEVENTS_DATE', 'StartEnd' );
			$return .= "\n\t\t\t</th>";
			++$span;
		}
		else {
			/* Startdate header */
			if ($start_date) {
				$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableStart'>";
				$return .= "\n\t\t\t\t" . CBTxt::T( 'JEMMYEVENTS_START', 'Startdate' );
				$return .= "\n\t\t\t</th>";
				++$span;
			}

			/* Enddate header */
			if ($end_date) {
				$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableExp'>";
				$return .= "\n\t\t\t\t" . CBTxt::T( 'JEMMYEVENTS_END', 'Enddate' );
				$return .= "\n\t\t\t</th>";
				++$span;
			}
		}
		/* City header */
		if ($event_place) {
			$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableVenue'>";
			$return .= "\n\t\t\t\t" . CBTxt::T( 'JEMMYEVENTS_VENUE', 'Venue' );
			$return .= "\n\t\t\t</th>";
			++$span;
		}
		/* Attendees */
		if ($event_attending) {
			$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableReg'>";
			$return .= "\n\t\t\t\t" . CBTxt::T( 'JEMMYEVENTS_ATTENDING', 'Attending' );
			$return .= "\n\t\t\t</th>";
			++$span;
		}

		/* End of headerline */
		$return .= "\n\t\t</tr>";

		/*
		 * Counting data
		 * If data is available start with the rows
		 */
		if (count($results)) {
			$config = JemHelper::config();
			$formatShortDate = $config->formatShortDate;

			$odd = 1;
			foreach ($results as $result) {
				$odd = 1 - $odd; // toggle {0, 1} for alternating row css classes

				/*
				 * Start of rowline
				 *
				 * The variable for the tr class has been defined above
				 * result stands for the variables of the query
				 */
				$return .= "\n\t\t<tr class='row{$odd}'>";

				/* Image field */
				if ($event_image) {
					$dimage =	JEMImage::flyercreator($result->datimage, 'event');
					$pimage =	JEMOutput::flyer($result, $dimage, 'event');
					$return .= "\n\t\t\t<td class='jemmyeventsCBTabTableImage'>";
					$return .= "\n\t\t\t\t{$pimage}";
					$return .= "\n\t\t\t</td>";
				}

				/* Title field */
				$return .= "\n\t\t\t<td class='jemmyeventsCBTabTableTitle'>";
				$return .= "\n\t\t\t\t<a href=\"". JRoute::_(JEMHelperRoute::getEventRoute($result->slug)) ."\">{$result->title}</a>";
				$return .= "\n\t\t\t</td>";

				/* Category field */
				if ($event_categories) {
					$categories = $this->getCategories($result->eventid, $levels);
					$cats = array();
					if (is_array($cats)) {
						foreach ($categories as $cat) {
							$cats[] = "<a href='".JRoute::_(JEMHelperRoute::getCategoryRoute($cat->catslug))."'>{$cat->catname}</a>";
						}
					}
					if (empty($cats)) {
						$cats[] = '-';
					}
					$return .= "\n\t\t\t<td class='jemmyeventsCBTabTableCat'>";
					$return .= "\n\t\t\t\t".implode(', ', $cats);
					$return .= "\n\t\t\t</td>";
				}

				/* Startdate/Enddate combined field */
				if ($date_combi) {
					$datecombi2 = JEMOutput::formatDateTime($result->dates, $result->times, $result->enddates, $result->endtimes, $formatShortDate);
					$return .= "\n\t\t\t<td class='jemmyeventsCBTabTableStartEnd'>";
					$return .= "\n\t\t\t\t{$datecombi2}";
					$return .= "\n\t\t\t</td>";
				}
				else {
					/* Startdate field */
					if ($start_date) {
						$startdate2 = JEMOutput::formatDateTime($result->dates, $result->times, '', '', $formatShortDate);
						$return .= "\n\t\t\t<td class='jemmyeventsCBTabTableStart'>";
						$return .= "\n\t\t\t\t{$startdate2}";
						$return .= "\n\t\t\t</td>";
					}

					/*
					 * Enddate
					 * if no enddate is given nothing will show up
					 */
					if ($end_date) {
						$enddate2 = $result->enddates ? JEMOutput::formatDateTime($result->enddates, $result->endtimes, '', '', $formatShortDate)
							                          : JEMOutput::formattime($result->endtimes);
						$return .= "\n\t\t\t<td class='jemmyeventsCBTabTableExp'>";
						$return .= "\n\t\t\t\t{$enddate2}";
						$return .= "\n\t\t\t</td>";
					}
				}
				/* Venue field
				 *
				 * a link to the venueevent is specified so people can visit the venue page
				 */
				if ($event_place) {
					$location = empty($result->venueslug) ? '' : "<a href='".JRoute::_(JEMHelperRoute::getVenueRoute($result->venueslug))."'>{$result->venue}</a>";
					$return .= "\n\t\t\t<td class='jemmyeventsCBTabTableVenue'>";
					$return .= "\n\t\t\t\t$location";
					if (!empty($result->city)) {
						$return .= "<small style='font-style:italic;'> - {$result->city}</small>";
					}
																
												  
					$return .= "\n\t\t\t</td>";
				}				
				/* Attendees field */
				if ($event_attending) {
					$regs = '-';
					if ($config->showfroregistra || ($result->registra & 1)) {
						if ($this->_found_state_field) {
							// state 1: user registered, state -1: user exlicitely unregistered, state 0: user is invited but hadn't answered yet
							$qry = "SELECT COUNT(IF(waiting <= 0 AND status = 1, 1, null)) AS registered, COUNT(IF(waiting > 0 AND status = 1, 1, null)) AS waiting,"
							     . " COUNT(IF(status = -1, 1, null)) AS unregistered, COUNT(IF(status = 0, 1, null)) AS invited FROM #__jem_register WHERE event = $result->eventid";
						} else {
							$qry = "SELECT COUNT(IF(waiting <= 0, 1, null)) AS registered, COUNT(IF(waiting > 0, 1, null)) AS waiting FROM #__jem_register WHERE event = $result->eventid";
						}
						$_CB_database->setQuery($qry);
						$objList = $_CB_database->loadObjectList();
						if (is_array($objList)) {
							$regs = (int)$objList[0]->registered;
							if ($result->maxplaces) {
								$regs .= ' / '.(int)$result->maxplaces;
								$waits = (int)$objList[0]->waiting;
								if ($result->waitinglist && $waits) {
									$regs .= ', + '.$waits;
								}
							}
							if (!empty($objList[0]->unregistered)) {
								$regs .= ', - '.(int)$objList[0]->unregistered;
							}
							if (!empty($objList[0]->invited)) {
								$regs .= ', ? '.(int)$objList[0]->invited .' ';
							}
						}
						unset($objList);
					}

					$return .= "\n\t\t\t<td class='jemmyeventsCBTabTableReg'>";
					$return .= "\n\t\t\t\t{$regs}";
					$return .= "\n\t\t\t</td>";
				}

				/* Closing the rowline */
				$return .= "\n\t\t</tr>";
			} // end of displaying rows
		} else {
			// When no data has been found the user will see a message

			/* display no listings */
			$return .= '<tr><td class="jemmyeventsCBTabTableTitle" colspan="'.$span.'">'.CBTxt::T( 'JEMMYEVENTS_NOENTRY', 'No entries' ).'</td></tr>';
		}

		/* closing tag of the table */
		$return .="</table>";

		/* closing of the form */
		$return .="</form>";

		/*
		 * Message for at the bottom, below the table
		 *
		 * At the top we did specify the variable
		 * but not sure where we can fill it
		 */
		$return .= "\t\t<div>\n<p>". htmlspecialchars($event_tab_message). "</p></div>\n";

		/*
		 * Showing the code
		 *
		 * We did specify the code above, but we do want to display it to the user
		 * There were a lot of "$return ." and all of them will be printed.
		 */
		return $return;
	}
}
?>
