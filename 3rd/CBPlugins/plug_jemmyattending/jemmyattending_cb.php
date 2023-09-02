<?php
/**
 * @package    JEM My Attending for CB
 * @version    4.1.0 (for JEM 4 & CB v2.8)
 * @author     JEM Community
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * Just a note:
 * Keep the query code inline with my-attendances view
 *
 */

defined('_JEXEC') or die;


@include_once (JPATH_SITE.'/components/com_jem/classes/image.class.php');
@include_once (JPATH_SITE.'/components/com_jem/classes/Zebra_Image.php');
@include_once (JPATH_SITE.'/components/com_jem/classes/output.class.php');
@include_once (JPATH_SITE.'/components/com_jem/helpers/helper.php');
@include_once (JPATH_SITE.'/components/com_jem/helpers/route.php');


class jemmyattendingTab extends cbTabHandler {

	protected $_jemFound = false;

	// does #__jem_register table has a column 'state'
	protected $_found_state_field = false;

	/* JEM Attending tab
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
	 * Check (asking) user's permissions.
	 */
	protected function _checkPermission($user, $juser)
	{
		// $user is profile's owner whilst $juser is asking user

		if ($juser->id != $user->id) {
			// we have to check if foreign announces are allowed to show
			$permitted = false;
			$settings = JEMHelper::globalattribs();

			switch ($settings->get('event_show_attendeenames', 2)) {
				case 0: // show to none
				default:
					break;
				case 1: // show to admins
					$permitted = $juser->authorise('core.manage', 'com_jem');
					break;
				case 2: // show to registered
					$permitted = !$juser->get('guest', 1);
					break;
				case 3: // show to all
					$permitted = true;
					break;
			}
		} else {
			$permitted = true;
		}

		return $permitted;
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

		$query      = 'SELECT DISTINCT a.id';
		if (!$fast) {
			$query .= '  AS eventid, a.dates, a.enddates, a.times, a.endtimes, a.title, a.created, a.locid,'
			        . ' CONCAT(a.introtext,a.fulltext) AS text, a.published,'
			        . ' l.venue, l.city, l.state, l.url, '
			        . ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug, '
			        . ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug, '
			        . ' r.waiting, ' . ($this->_found_state_field ? 'r.status' : '1') . ' AS reg_state '
			        . ($this->_found_state_field ? ', r.comment AS reg_comment' : '')
			        ;
		}
		$query     .= ' FROM #__jem_events AS a INNER JOIN #__jem_register AS r ON r.event = a.id '
		            . ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid '
			        . ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id '
			        . ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid '
		            . ' WHERE a.published = 1 AND r.uid = '.$userid
			        . '  AND a.access IN (' . implode(',', $levels) . ')'
			        . '  AND (a.dates IS NULL OR DATE_SUB(NOW(), INTERVAL 1 DAY) < (IF (a.enddates IS NOT NULL, a.enddates, a.dates)))'
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
		$juser = JFactory::getUser();

		if ($this->_jemFound && $this->_checkPermission($user, $juser)) {
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

		/*loading params set by the backend*/
		$params = $this->params;

		/* message at the bottom of the table */
		$event_tab_message = $params->get('hwTabMessage', "");

		/* other variables */
		$return = null;

		$event_description = $params->get('event_description');
		$event_enddate = $params->get('event_enddate');
		$event_startdate = $params->get('event_startdate');
		$event_datecombi = $params->get('event_datecombi');
		$event_venue = $params->get('event_venue');
		$reg_comment = $params->get('reg_comment') && $this->_found_state_field;

		/* access rights check */
		// $user is profile's owner but we need logged-in user here
		$juser = JFactory::getUser();

		if (!$this->_checkPermission($user, $juser)) {
			return ''; // which will completely hide the tab
		}

		/* load css */
		$_CB_framework->document->addHeadStyleSheet(dirname(__FILE__).'/jemmyattending_cb.css');

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

		// Support Joomla access levels instead of single group id; $juser is the asking user
		$levels = $juser->getAuthorisedViewLevels();

		/*
		 * Query
		 *
		 * Retrieval of the data
		 * Keep it inline with the my-events view
		 */

		// get announcements
		$query = $this->_getQuery($user);
		$_CB_database->setQuery($query);
		$results = $_CB_database->loadObjectList();

		/* Headers
		 *
		 * The classes are retrieved from:
		 * components/com_comprofiler/plugin/user/plug_cbjemmyattending/jemmyevents_cb.css
		 *
		 * The language strings are retrieved from:
		 * components/com_comprofiler/plugin/user/plug_cbjemmyattending/language/*languagecode*
		 *
		 * defining a new language can be done like:
		 * - add a new string, like: _EVENT_NEWNAME
		 * - add the translation to the language file
		 */

		/* start of form */
		$return .= "\n\t<form method=\"post\" name=\"jemmyattendingForm\">";

		/* Start of Table */
		$return .= "\n\t<table class='jemmyattendingCBTabTable'>";
		$return .= "\n\t<table class='table table-hover mb-0'>";
		/* start of headerline */
		$return .= "\n\t\t<tr class='jemmyattendingtableheader'>";

		/* Title header */
		$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableTitle'>";
		$return .= "\n\t\t\t\t" . CBTxt::T( 'JEMMYATTENDING_TITLE', 'Title' );
		$return .= "\n\t\t\t</th>";
		$span = 1;

		/* Description header */
		if ($event_description) {
			$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableDesc'>";
			$return .= "\n\t\t\t\t" . CBTxt::T( 'JEMMYATTENDING_DESC', 'Description' );
			$return .= "\n\t\t\t</th>";
			++$span;
		}

		/* Startdate and enddate in a single column */
		if ($event_datecombi) {
			$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableStartEnd'>";
			$return .= "\n\t\t\t\t" . CBTxt::T( 'JEMMYATTENDING_STARTEND', 'StartEnddate' );
			$return .= "\n\t\t\t</th>";
			++$span;
		} else {
			/* Startdate header */
			if ($event_startdate) {
				$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableStart'>";
				$return .= "\n\t\t\t\t" . CBTxt::T( 'JEMMYATTENDING_START', 'Startdate' );
				$return .= "\n\t\t\t</th>";
				++$span;
			}

			/* Enddate header */
			if ($event_enddate) {
				$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableExp'>";
				$return .= "\n\t\t\t\t" . CBTxt::T( 'JEMMYATTENDING_END', 'Enddate' );
				$return .= "\n\t\t\t</th>";
				++$span;
			}
		}
		/* City header */
		if ($event_venue) {
			$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableVenue'>";
			$return .= "\n\t\t\t\t" . CBTxt::T( 'JEMMYATTENDING_VENUE', 'Venue' );
			$return .= "\n\t\t\t</th>";
			++$span;
		}

		/* Status header */
		$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableStatus'>";
		$return .= "\n\t\t\t\t" . CBTxt::T( 'JEMMYATTENDING_SSTATUS', 'StatusAttending' );
		$return .= "\n\t\t\t</th>";
		++$span;

		/* Comment header */
		if ($reg_comment) {
			$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableComment'>";
			$return .= "\n\t\t\t\t" . CBTxt::T( 'JEMMYATTENDING_COMMENT', 'Comment' );
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

				/* Title field */
				$return .= "\n\t\t\t<td class='jemmyattendingCBTabTableTitle'>";
				$return .= "\n\t\t\t\t<a href=\"". JRoute::_(JEMHelperRoute::getEventRoute($result->slug)) ."\">{$result->title}</a>";
				$return .= "\n\t\t\t</td>";

				/*
				 * Description field
				 *
				 * the max length is specified
				 */
				if ($event_description) {
					$description = strip_tags($result->text);
					if (strlen($description) > 150) {
						$description = substr($description, 0, 150) . '...';
					}
					$return .= "\n\t\t\t<td class='jemmyattendingCBTabTableDesc'>";
					$return .= "\n\t\t\t\t{$description}";
					$return .= "\n\t\t\t</td>";
				}


				if ($event_datecombi) {
					/*
					 * Startdate and enddate in a single column
					 */
					$datecombi2 = JEMOutput::formatDateTime($result->dates, $result->times, $result->enddates, $result->endtimes, $formatShortDate);
					$return .= "\n\t\t\t<td class='jemmyattendingCBTabTableStartEnd'>";
					$return .= "\n\t\t\t\t{$datecombi2}";
					$return .= "\n\t\t\t</td>";
				} else {
					/*
					 * Startdate field
					 */
					if ($event_startdate) {
						$startdate2 = JEMOutput::formatDateTime($result->dates, $result->times, '', '', $formatShortDate);
						$return .= "\n\t\t\t<td class='jemmyattendingCBTabTableStart'>";
						$return .= "\n\t\t\t\t{$startdate2}";
						$return .= "\n\t\t\t</td>";
					}

					/*
					 * Enddate
					 * if no enddate is given nothing will show up
					 */
					if ($event_enddate) {
						$enddate2 = $result->enddates ? JEMOutput::formatDateTime($result->enddates, $result->endtimes, '', '', $formatShortDate)
							                          : JEMOutput::formattime($result->endtimes);
						$return .= "\n\t\t\t<td class='jemmyattendingCBTabTableExp'>";
						$return .= "\n\t\t\t\t{$enddate2}";
						$return .= "\n\t\t\t</td>";
					}
				}
				/* Venue field
				 *
				 * a link to the venueevent is specified so people can visit the venue page
				 */
				if ($event_venue) {
					$location = empty($result->venueslug) ? '' : "<a href='".JRoute::_(JEMHelperRoute::getVenueRoute($result->venueslug))."'>{$result->venue}</a>";
					$return .= "\n\t\t\t<td class='jemmyattendingCBTabTableVenue'>";
					$return .= "\n\t\t\t\t$location";
					if (!empty($result->city)) {
						$return .= "<small style='font-style:italic;'> - {$result->city}</small>";
					}
					$return .= "\n\t\t\t</td>";
				}


				/*
				 * Status
				 * show icon to indicate if registered or on waiting list
				 */
				switch ($result->reg_state) {
				case -1: // explicitely unregistered
					$img = JRoute::_($_CB_framework->getCfg('live_site') . '/media/com_jem/images/publish_r.png');
					$tip = CBTxt::T( 'JEMMYATTENDING_STATUS_UNREGISTERED', 'Not attending' );
					break;
				case  0: // invited, not answered yet
					$img = JRoute::_($_CB_framework->getCfg('live_site') . '/media/com_jem/images/invited.png');
					$tip = CBTxt::T( 'JEMMYATTENDING_STATUS_INVITED', 'Invited' );
					break;
				case  1: // registered
					$img = JRoute::_($_CB_framework->getCfg('live_site') . ($result->waiting ? '/media/com_jem/images/publish_y.png' : '/media/com_jem/images/tick.png'));
					$tip = $result->waiting ? CBTxt::T( 'JEMMYATTENDING_STATUS_WAITINGLIST', 'On Waitinglist' ) : CBTxt::T( 'JEMMYATTENDING_STATUS_REGISTERED', 'Attending' );
					break;
				default: // ? - shouldn't happen...
					$img = JRoute::_($_CB_framework->getCfg('live_site') . '/media/com_jem/images/disabled.png');
					$tip = CBTxt::T( 'JEMMYATTENDING_STATUS_UNKNOWN', 'Status unknown' );
					break;
				}
				$return .= "\n\t\t\t<td class='jemmyattendingCBTabTableStatus'>";
				$return .= "\n\t\t\t\t<img src='$img' alt='$tip' title='$tip'>";
				$return .= "\n\t\t\t</td>";

				/* Comment field */
				if ($reg_comment) {
					$comment = strip_tags($result->reg_comment);
					if (strlen($comment) > 150) {
						$comment = substr($comment, 0, 150) . '...';
					}
					$return .= "\n\t\t\t<td class='jemmyattendingCBTabTableComment'>";
					$return .= "\n\t\t\t\t{$comment}";
					$return .= "\n\t\t\t</td>";
				}

				/* Closing the rowline */
				$return .= "\n\t\t</tr>";
			} // end of displaying rows
		} else {
			// When no data has been found the user will see a message

			/* display no listings */
			$return .= '<tr><td class="jemmyattendingCBTabTableTitle" colspan="'.$span.'">'.CBTxt::T( 'JEMMYATTENDING_NOENTRY', 'No entries ' ).'</td></tr>';
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
