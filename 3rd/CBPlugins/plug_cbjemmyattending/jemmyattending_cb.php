<?php
/**
 * @package My Attending
 * @version JEM v1.9.5 & CB 1.9
 * @author JEM Community
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 *
 * Just a note:
 * Keep the query code inline with my-attendances view
 *
 */

if (! (defined('_VALID_CB') || defined('_JEXEC') || defined('_VALID_MOS')))
{
	die();
}

require_once (JPATH_SITE.'/components/com_jem/classes/image.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/output.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/Zebra_Image.php');
require_once (JPATH_SITE.'/components/com_jem/helpers/helper.php');
require_once (JPATH_SITE.'/components/com_jem/helpers/route.php');


class jemmyattendingTab extends cbTabHandler {
	/* JEM Attending tab
	 */
	function __construct()
	{
		$this->cbTabHandler();
	}


	/**
	 * Retrieve the languagefile
	 * The file is located in the folder language
	 */
	function _getLanguageFile() {
		global $_CB_framework;
		$UElanguagePath=$_CB_framework->getCfg('absolute_path').'/components/com_comprofiler/plugin/user/plug_cbjemmyattending';
		if (file_exists($UElanguagePath.'/language/'.$_CB_framework->getCfg('lang').'.php')) {
			include_once($UElanguagePath.'/language/'.$_CB_framework->getCfg('lang').'.php');
		} else include_once($UElanguagePath.'/language/english.php');
	}


	/**
	 * Display Tab
	 */
	function getDisplayTab($tab,$user,$ui) {
		/* loading global variables */
		global $_CB_database,$_CB_framework;

		/* loading the language function */
		self::_getLanguageFile();

		/*loading params set by the backend*/
		$params = $this->params;

		/* other variables */
		$return = null;

		$event_description = $params->get('event_description');
		$event_enddate = $params->get('event_enddate');
		$event_startdate = $params->get('event_startdate');
		$event_venue = $params->get('event_venue');

		/* message at the bottom of the table */
		$event_tab_message = $params->get('hwTabMessage', "");

		/* load css */
		$_CB_framework->addCustomHeadTag("<link href=\"".$_CB_framework->getCfg('live_site')."/components/com_comprofiler/plugin/user/plug_cbjemmyattending/jemmyattending_cb.css\" rel=\"stylesheet\" type=\"text/css\" />");

		/* check for tabdescription */
		if ($tab->description == null)
		{
			$tabdescription = "_JEMMYATTENDING_NO_DESCRIPTION";
		} else {
			$tabdescription = $tab->description;
		}

		/*
		 * Tab description
		 *
		 * the text will be on top of the table
		 * can be filled in the backend, section: Tab management
		 */

		// html content is allowed in descriptions
		$return .= "\t\t<div class=\"tab_Description\">". $tabdescription. "</div>\n";

		/*
		 * Check if gd is enabled, for thumbnails
		 *
		 * Is not used at the moment
		 */

		//get param for thumbnail
// 		$query = "SELECT gddisabled FROM #__jem_settings";
// 		$_CB_database->setQuery($query);
// 		$thumb= $_CB_database->loadResult();


		/*
		 * Check for an Itemid
		 * Used for links
		 */

		// get itemid
		$query = "SELECT `id` FROM `#__menu` WHERE `link` LIKE '%index.php?option=com_jem&view=eventslist%' AND `type` = 'component' AND `published` = '1' LIMIT 1";
		$_CB_database->setQuery($query);

		$S_Itemid= $_CB_database->loadResult();

		if(!$S_Itemid) {
			$S_Itemid = 999999;
		}

		// retrieval user parameters
		$userid = $user->id;

		// Support Joomla access levels instead of single group id
		$juser = JFactory::getUser($userid);
		$levels = $juser->getAuthorisedViewLevels();

		/*
		 * Query
		 *
		 * Retrieval of the data
		 * Keep it inline with the my-events view
		 */

		// get events
		$query = 'SELECT DISTINCT a.id AS eventid, a.dates, a.enddates, a.times, a.endtimes, a.title, a.created, a.locid, CONCAT(a.introtext,a.fulltext) AS text, a.published,'
				. ' l.id, l.venue, l.city, l.state, l.url, '
				. ' c.catname, c.id AS catid, '
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug, '
				. ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug '
				. ' FROM #__jem_events AS a INNER JOIN #__jem_register AS r ON r.event = a.id '
				. ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid '
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id '
				. ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid '
				. ' WHERE a.published = 1 AND c.published = 1 AND r.uid = '.$userid.' AND c.access IN (' . implode(',', $levels) . ')'
				. ' AND DATE_SUB(NOW(), INTERVAL 1 DAY) < (IF (a.enddates IS NOT NULL, a.enddates, a.dates))'
				. ' ORDER BY a.dates, a.times'
				;
		$_CB_database->setQuery($query);
		$results = $_CB_database->loadObjectList();

		/*
		 * Headers
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
		if ($event_description==1) {
			$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableDesc'>";
			$return .= "\n\t\t\t\t" . _JEMMYATTENDING_DESC;
			$return .= "\n\t\t\t</th>";
		}

		/* City header */
		if ($event_venue==1) {
			$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableVenue'>";
			$return .= "\n\t\t\t\t" . _JEMMYATTENDING_CITY;
			$return .= "\n\t\t\t</th>";
		}

		/* Startdate header */
		if ($event_startdate==1) {
			$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableStart'>";
			$return .= "\n\t\t\t\t" . _JEMMYATTENDING_START;
			$return .= "\n\t\t\t</th>";
		}

		/* Enddate header */
		if ($event_enddate==1) {
			$return .= "\n\t\t\t<th class='jemmyattendingCBTabTableExp'>";
			$return .= "\n\t\t\t\t" . _JEMMYATTENDING_EXPIRE;
			$return .= "\n\t\t\t</th>";
		}

		/* End of headerline */
		$return .= "\n\t\t</tr>";

		/*
		 * Counting data
		 * If data is available start with the rows
		 */
		$entryCount = 0;
		if(count($results)) {
			foreach($results as $result) {
				$entryCount++;

				/* Variables */
				$query = "SELECT formatShortDate FROM #__jem_settings";
				$_CB_database->setQuery($query);
				$settings= $_CB_database->loadObjectList();

				/*
				 * adding the class row0/row1 to the rows
				 *
				 * this is for the coloring of the rows
				 * The variable has been added to the tr of the rows
			 	 */
				$CSSClass = $entryCount%2 ? "row0" : "row1";

				/*
				 * Start of rowline
				 *
				 * The variable for the tr class has been defined above
				 * result stands for the variables of the query
				 */
				$return .= "\n\t\t<tr class='{$CSSClass}'>";

				/* Title field */
				$return .= "\n\t\t\t<td class='jemmyattendingCBTabTableTitle'>";
				$return .= "\n\t\t\t\t<a href=\"". JRoute::_(JEMHelperRoute::getEventRoute($result->eventid)) ."\">{$result->title}</a>";
				$return .= "\n\t\t\t</td>";


				/*
				 * Description field
				 *
				 * the max length is specified
				 * the (...) is being added behind the description, also with small descriptions
				 */
				if ($event_description==1) {
					$description = substr($result->text,0,150);
					$return .= "\n\t\t\t<td class='jemmyattendingCBTabTableDesc'>";
					$return .= "\n\t\t\t\t{$description} (...)";
					$return .= "\n\t\t\t</td>";
				}

				/* Venue field
				 *
				 * a link to the venueevent is specified so people can visit the venue page
				 */
				if ($event_venue==1) {
					$location = "<a href='".JRoute::_(JEMHelperRoute::getVenueRoute($result->locid))."'>{$result->venue}</a>";
					$return .= "\n\t\t\t<td class='jemmyattendingCBTabTableVenue'>";
					$return .= "\n\t\t\t\t$location <small style='font-style:italic;'>- {$result->city}</small>";
					$return .= "\n\t\t\t</td>";
				}

				/* Startdate field */
				if ($event_startdate==1) {
					$startdate2 =	JEMOutput::formatdate($result->dates, $settings[0]->formatShortDate);
					$return .= "\n\t\t\t<td class='jemmyattendingCBTabTablestart'>";
					$return .= "\n\t\t\t\t{$startdate2}";
					$return .= "\n\t\t\t</td>";
				}

				/*
				 * Enddate
				 * if no enddate is given nothing will show up
				 */
				if ($event_enddate==1) {
					$enddate2 =	JEMOutput::formatdate($result->enddates, $settings[0]->formatShortDate);
					$return .= "\n\t\t\t<td class='jemmyattendingCBTabTableExp'>";
					$return .= "\n\t\t\t\t{$enddate2}";
					$return .= "\n\t\t\t</td>";
				}

				/* Closing the rowline */
				$return .= "\n\t\t</tr>";

			} // end of displaying rows
		} else {
			// When no data has been found the user will see a message

			/* display no listings */
			$return .= _JEMMYATTENDING_NO_LISTING;
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
