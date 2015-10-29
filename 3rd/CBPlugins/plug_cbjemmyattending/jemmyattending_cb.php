<?php
/**
 * @package My Attending
 * @version 2.1.4 for JEM v2.0 / v2.1 & CB v1.9 / v2.0
 * @author JEM Community
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2
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

	protected $jemFound = false;

	/* JEM Attending tab
	 */
	function __construct()
	{
		// Check if JEM is installed.
		$this->jemFound = class_exists('JemImage') && class_exists('JemOutput') && class_exists('JemHelperRoute');

		$this->cbTabHandler();
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

		$UElanguagePath=$_CB_framework->getCfg('absolute_path').'/components/com_comprofiler/plugin/user/plug_cbjemmyattending';
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
			        . ' r.waiting'
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
	public function getTabTitle($tab, $user, $ui, $postdata)
	{
		/* loading global variables */
		global $_CB_database, $ueConfig;

		// On CB 1.9 this function isn't part of API so it will be never called by CB.
		// Ensure not to call non-existent function on parent class just in case wrongly called.
		if (version_compare($ueConfig['version'], '2.0', 'lt')) {
			return false;
		}

		$total = 0;
		$juser = JFactory::getUser();

		if ($this->jemFound && $this->_checkPermission($user, $juser) &&
		    isset($tab->position) && (strpos($tab->position, 'canvas_') === 0) && ($tab->displaytype == 'menu')) {
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
		global $_CB_database,$_CB_framework;

		if (!$this->jemFound) {
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

		/* access rights check */
		// $user is profile's owner but we need logged-in user here
		$juser = JFactory::getUser();

		if (!$this->_checkPermission($user, $juser)) {
			return ''; // which will completely hide the tab
		}

		/* load css */
		//$_CB_framework->addCustomHeadTag("<link href=\"".$_CB_framework->getCfg('live_site')."/components/com_comprofiler/plugin/user/plug_cbjemmyattending/jemmyattending_cb.css\" rel=\"stylesheet\" type=\"text/css\" />");
		$_CB_framework->document->addHeadStyleSheet($_CB_framework->getCfg('live_site').'/components/com_comprofiler/plugin/user/plug_cbjemmyattending/jemmyattending_cb.css');

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

		/* start of headerline */
		$return .= "\n\t\t<tr class='jemmyattendingtableheader'>";

		/* Title header */
		$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableTitle'>";
		$return .= "\n\t\t\t\t" . _JEMMYATTENDING_TITLE;
		$return .= "\n\t\t\t</th>";

		/* Description header */
		if ($event_description) {
			$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableDesc'>";
			$return .= "\n\t\t\t\t" . _JEMMYATTENDING_DESC;
			$return .= "\n\t\t\t</th>";
		}

		/* City header */
		if ($event_venue) {
			$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableVenue'>";
			$return .= "\n\t\t\t\t" . _JEMMYATTENDING_CITY;
			$return .= "\n\t\t\t</th>";
		}

		/* Startdate and enddate in a single column */
		if ($event_datecombi) {
			$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableStartEnd'>";
			$return .= "\n\t\t\t\t" . _JEMMYATTENDING_START_END;
			$return .= "\n\t\t\t</th>";
		} else {
			/* Startdate header */
			if ($event_startdate) {
				$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableStart'>";
				$return .= "\n\t\t\t\t" . _JEMMYATTENDING_START;
				$return .= "\n\t\t\t</th>";
			}

			/* Enddate header */
			if ($event_enddate) {
				$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableExp'>";
				$return .= "\n\t\t\t\t" . _JEMMYATTENDING_EXPIRE;
				$return .= "\n\t\t\t</th>";
			}
		}

		/* Status header */
		$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableStatus'>";
		$return .= "\n\t\t\t\t" . _JEMMYATTENDING_STATUS;
		$return .= "\n\t\t\t</th>";

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

				/*
				 * Status
				 * show icon to indicate if registered or on waiting list
				 */
				$img = JRoute::_($_CB_framework->getCfg('live_site') . ($result->waiting ? '/media/com_jem/images/publish_y.png' : '/media/com_jem/images/tick.png'));
				$tip = $result->waiting ? _JEMMYATTENDING_STATUS_WAITINGLIST : _JEMMYATTENDING_STATUS_REGISTERED;
				$return .= "\n\t\t\t<td class='jemmyattendingCBTabTableStatus'>";
				$return .= "\n\t\t\t\t<img src='$img' alt='$tip' title='$tip'>";
				$return .= "\n\t\t\t</td>";

				/* Closing the rowline */
				$return .= "\n\t\t</tr>";
			} // end of displaying rows
		} else {
			// When no data has been found the user will see a message

			/* display no listings */
			$return .= '<tr><td class="jemmyattendingCBTabTableTitle" span="9">'._JEMMYATTENDING_NO_LISTING.'</td></tr>';
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
