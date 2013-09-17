<?php
/**
 * @package My Events
 * @version JEM v1.9.1 & CB 1.9
 * @author JEM Community
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 *
 * Just a note:
 * Keep the query code inline with my-attending view
 *
 */

if (! (defined('_VALID_CB') || defined('_JEXEC') || defined('_VALID_MOS')))
{
	die();
}

require_once (JPATH_SITE.'/components/com_jem/classes/image.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/Zebra_Image.php');
require_once (JPATH_SITE.'/components/com_jem/classes/output.class.php');
require_once (JPATH_SITE.'/components/com_jem/helpers/helper.php');
require_once (JPATH_SITE.'/components/com_jem/helpers/route.php');


class jemmyeventsTab extends cbTabHandler {
	/**
	 * Show My Events
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
		$UElanguagePath=$_CB_framework->getCfg('absolute_path').'/components/com_comprofiler/plugin/user/plug_cbjemmyevents';
		if (file_exists($UElanguagePath.'/language/'.$_CB_framework->getCfg('lang').'.php')) {
			include_once($UElanguagePath.'/language/'.$_CB_framework->getCfg('lang').'.php');
		} else include_once($UElanguagePath.'/language/english.php');
	}


	/**
	 * Retrieval of the setting fields
	 */
	function &config()
	{
		static $config;

		if (!is_object($config))
		{
			$db 	= JFactory::getDBO();
			$sql 	= 'SELECT * FROM #__jem_settings WHERE id = 1';
			$db->setQuery($sql);
			$config = $db->loadObject();
		}

		return $config;
	}


	/**
	 * Function for deleting an event
	 *
	 * not begin used
	 */
	function deleteRecord() {
		global $_CB_database;
		foreach($_POST as $delete_id) {
			$query = "DELETE FROM #__jem_events where id=".$delete_id;
			$_CB_database->setQuery($query);
		}
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

		/* message at the bottom of the table */
		$event_tab_message = $params->get('hwTabMessage', "");

		/* variables */
		$return = null;
		$result_title = null;

		$event_image = $params->get('event_image');
		$end_date = $params->get('end_date');
		$start_date = $params->get('start_date');
		$event_categories = $params->get('event_categories');
		$event_attending = $params->get('event_attending');

		/* load css */
		$_CB_framework->addCustomHeadTag("<link href=\"".$_CB_framework->getCfg('live_site')."/components/com_comprofiler/plugin/user/plug_cbjemmyevents/jemmyevents_cb.css\" rel=\"stylesheet\" type=\"text/css\" />");

		/* check for tabdescription */
		if($tab->description == null) {
			$tabdescription = _JEMMYEVENTS_NOTABDESCRIPTION;
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

		// Check if gd is enabled, for thumbnails

		//get param for thumbnail
// 		$query = "SELECT gddisabled FROM #__jem_settings";
// 		$_CB_database->setQuery($query);
// 		$thumb= $_CB_database->loadResult();


		/**
		 * Check for an Itemid
		 *
		 * Used for links
		 */

		// get eventslist itemid
		$query = "SELECT `id` FROM `#__menu` WHERE `link` LIKE '%index.php?option=com_jem&view=eventslist%' AND `type` = 'component' AND `published` = '1' LIMIT 1";
		$_CB_database->setQuery($query);

		$S_Itemid1= $_CB_database->loadResult();

		if(!$S_Itemid1) {
			$S_Itemid1 = 999999;
		}

		// retrieval user parameters
		$userid = $user->id;

		if (JFactory::getUser()->authorise('core.manage')) {
			$gid = (int) 3;             //viewlevel Special
		} else {
			if($user->get('id')) {
				$gid = (int) 2;      //viewlevel Registered
			} else {
				$gid = (int) 1;      //viewlevel Public
			}
		}

		/*
		 * Query
		 *
		 * Retrieval of the data
		 * Keep it inline with the my-events view
		 */
		$query = 'SELECT DISTINCT a.id AS eventid, a.id, a.dates, a.datimage, a.enddates, a.times, a.endtimes, a.title, a.created, a.locid, a.datdescription, a.published,a.registra, a.maxplaces, a.waitinglist,'
			. ' l.id, l.venue, l.city, l.state, l.url,'
			. ' c.catname, c.id AS catid,'
			. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
			. ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug,'
			. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslu '
			. ' FROM `#__jem_events` AS a '
			. ' LEFT JOIN `#__jem_venues` AS l ON l.id = a.locid '
			. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id '
			. ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid '
			. ' WHERE a.published = 1 AND c.published = 1 AND a.created_by = '.$userid.' AND c.access <= '.$gid
			. ' GROUP BY a.id'
			. ' ORDER BY a.dates'
			;
		$_CB_database->setQuery($query);
		$results = $_CB_database->loadObjectList();


		/* Not sure where this is for
		 *
		 *
		 * not used at the moment
		 */
		if ($userid == $user->id)
		 {
// 			$url = "index.php?option=com_jem&view=editevent" ;
			$query4 = "SELECT `published` FROM `#__jem_events` WHERE `created_by` = id and `published` = 1 " ;
			$_CB_database->setQuery($query4);
			$results4 = $_CB_database->loadObjectList();

			if ($results4 != null && count($results4) > 0) {
				$return .="<br><br>".count($results4)._JEMMYEVENTS_PUB."<br>";
			}
		}


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
		$return .= "\n\t<table  class='jemmyeventsCBTabTable' width=100% >";

		/* start of headerline */
		$return .= "\n\t\t<tr class='jemmyeventstableheader'>";

		/* start of imagefield */
		if($event_image==1) {
			$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableTitle'>";
			$return .= "\n\t\t\t\t" . _JEMMYEVENTS_IMAGE;
			$return .= "\n\t\t\t</th>";
		}

		/* Title header */
		$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableTitle'>";
		$return .= "\n\t\t\t\t" . _JEMMYEVENTS_TITLE;
		$return .= "\n\t\t\t</th>";

		/* Category header */
		if ($event_categories==1) {
			$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableCat'>";
			$return .= "\n\t\t\t\t" . _JEMMYEVENTS_CATEGORY;
			$return .= "\n\t\t\t</th>";
		}

		/* Startdate header */
		if($start_date==1) {
			$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableStart'>";
			$return .= "\n\t\t\t\t" . _JEMMYEVENTS_START;
			$return .= "\n\t\t\t</th>";
		}

		/* Enddate header */
		if($end_date==1) {
			$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableExp'>";
			$return .= "\n\t\t\t\t" . _JEMMYEVENTS_EXPIRE;
			$return .= "\n\t\t\t</th>";
		}

		/* Attendees */
		if ($event_attending==1) {
			$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableReg'>";
			$return .= "\n\t\t\t\t" . _JEMMYEVENTS_REGISTER;
			$return .= "\n\t\t\t</th>";
		}

		/* End of headerline */
		$return .= "\n\t\t</tr>";


		/*
		 * Counting data
		 *
		 * If data is available start with the rows
		 */
		$entryCount = 0;
		$cat = null;
		if(count($results)) {
			for ($i=0, $n = count($results); $i < $n; $i++) {
				$entryCount++;

				// defining variables
				$result = $results[$i];
// 				$checked = JHTML::_('grid.id', $i, $result->id);
// 				$catHref = JRoute::_(JEMHelperRoute::getCategoryRoute($result->catid));
// 				$cats = "\n\t\t\t<a href='{$catHref}' title='{$result->catname}'>{$result->catname}</a>";

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

				/* Image field */
				if($event_image == 1) {
					$dimage =	JEMImage::flyercreator($result->datimage, 'event');
					$pimage =	JEMOutput::flyer($result, $dimage, 'event');
					$return .= "\n\t\t\t<td class='jemmyeventsCBTabTableImage'>";
					$return .= "\n\t\t\t\t{$pimage}";
					$return .= "\n\t\t\t</td>";
				}

				/* Title field */
				$result_titles = explode(" " , $result->title);
				$result_title = implode("-" , $result_titles);
				$return .= "\n\t\t\t<td class='jemmyeventsCBTabTableTitle'>";
				$return .= "\n\t\t\t\t<a href=\"". JRoute::_(JEMHelperRoute::getEventRoute($result->eventid)) ."\">{$result->title}</a>";
				$return .= "\n\t\t\t</td>";

				/* Category field */
				if ($event_categories==1) {
					$cat = "<a href='".JRoute::_(JEMHelperRoute::getCategoryRoute($result->catid))."'>{$result->catname}</a>";
					$return .= "\n\t\t\t<td class='jemmyeventsCBTabTableCat'>";
					$return .= "\n\t\t\t\t$cat";
					$return .= "\n\t\t\t</td>";
				}

				/* Startdate field */
				if($start_date==1) {
					$startdate2 =	JEMOutput::formatdate($result->dates,$settings[0]->formatShortDate);
					$return .= "\n\t\t\t<td class='jemmyeventsCBTabTablestart'>";
					$return .= "\n\t\t\t\t{$startdate2}";
					$return .= "\n\t\t\t</td>";
				}

				/*
				 * Enddate
				 * if no enddate is given nothing will show up
				 */
				if($end_date==1) {
					$enddate2 =	JEMOutput::formatdate($result->enddates, $settings[0]->formatShortDate);
					$return .= "\n\t\t\t<td class='jemmyeventsCBTabTableExp'>";
					$return .= "\n\t\t\t\t{$enddate2}";
					$return .= "\n\t\t\t</td>";
				}

				/* Attendees field */
				/* @todo alter query, to add waitinglist */
				if ($event_attending) {
					$qry = "SELECT count(uid) AS regs FROM #__jem_register where `event`=$result->eventid";
					$_CB_database->setQuery($qry);
					$reg = $_CB_database->loadObjectList();

					$return .= "\n\t\t\t<td class='jemmyeventsCBTabTableReg'>";
					$return .= "\n\t\t\t\t{$reg[0]->regs}";
					$return .= "\n\t\t\t</td>";
				}

				/* Closing the rowline */
				$return .= "\n\t\t</tr>";

			} // end of displaying rows
		} else {
			// When no data has been found the user will see a message

			// display no listings
			$return .= _JEMMYEVENTS_NO_LISTING;
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

		/* Showing the code
		 *
		 * We did specify the code above, but we do want to display it to the user
		 * There were a lot of "$return ." and all of them will be printed.
		 */
		return $return;
	} // end or getDisplayTab function



	/********************************************************************
	 *																	*
	 * 																	*
	 * Start of Edittab													*
	 * 																	*
	 * This is the tab you see when going to edit-profile				*
	 * 																	*
	 * @todo figure out how to display the Add new event link			*
	 * 																	*
 	 * 																	*
	 *******************************************************************/
	function getEditTabDISABLED($tab,$user,$ui)
	{
		/* loading global variables */
		global $_CB_database,$_CB_framework;
		$adminurl = strstr($_SERVER['REQUEST_URI'], 'index');

		if ($adminurl != 'index2.php') {
			/* loading the language function */
			self::_getLanguageFile();

			/*loading params set by the backend*/
			$params = $this->params;

			/* message at the bottom of the table */
			$event_tab_message = $params->get('hwTabMessage', "");

			/* variables */
			$return = null;
			$result_title = null;

			$end_date = $params->get('end_date');
			$start_date = $params->get('start_date');
			$event_categories = $params->get('event_categories');
			$event_attending = $params->get('event_attending');

			/* load css */
			$_CB_framework->addCustomHeadTag("<link href=\"".$_CB_framework->getCfg('live_site')."/components/com_comprofiler/plugin/user/plug_cbjemmyevents/jemmyevents_cb.css\" rel=\"stylesheet\" type=\"text/css\" />");

			/* check for tabdescription */
			if($tab->description == null) {
				$tabdescription = _JEMMYEVENTS_NOTABDESCRIPTION;
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

			// Check if gd is enabled, for thumbnails

			//get param for thumbnail
// 			$query = "SELECT gddisabled FROM #__jem_settings";
// 			$_CB_database->setQuery($query);
// 			$thumb= $_CB_database->loadResult();

			/*
			 * Check for an Itemid
			 *
			 * Used for links
			 */

			// get  itemid
			$query = "SELECT `id` FROM `#__menu` WHERE `link` LIKE '%index.php?option=com_jem&view=eventslist%' AND `type` = 'component' AND `published` = '1' LIMIT 1";
			$_CB_database->setQuery($query);

			$S_Itemid1= $_CB_database->loadResult();

			if(!$S_Itemid1) {
				$S_Itemid1 = 999999;
			}


			// retrieval user parameters
			$userid = $user->id;

			if (JFactory::getUser()->authorise('core.manage')) {
				$gid = (int) 3;             //viewlevel Special
			} else {
				if($user->get('id')) {
					$gid = (int) 2;      //viewlevel Registered
				} else {
					$gid = (int) 1;      //viewlevel Public
				}
			}

			/* Query
		 	*
			* Retrieval of the data
			* Keep it inline with the my-events view
			*/
			$query = 'SELECT DISTINCT a.id AS eventid, a.id, a.dates, a.datimage, a.enddates, a.times, a.endtimes, a.title, a.created, a.locid, a.datdescription, a.published,a.registra, a.maxplaces, a.waitinglist,'
				. ' l.id, l.venue, l.city, l.state, l.url,'
				. ' c.catname, c.id AS catid,'
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
				. ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslu '
				. ' FROM `#__jem_events` AS a '
				. ' LEFT JOIN `#__jem_venues` AS l ON l.id = a.locid '
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id '
				. ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid '
				. ' WHERE (a.published = 1 OR a.published = 0) AND c.published = 1 AND a.created_by = '.$userid.' AND c.access <= '.$gid
				. ' GROUP BY a.id'
				. ' ORDER BY a.dates'
				;
			$_CB_database->setQuery($query);
			$results = $_CB_database->loadObjectList();



			//	$user_type=$results[0]->gid ;
			if ($userid == $user->id) {
				if ($user->gid == 8) {
					$url = "index.php?option=com_jem&view=editevent&Itemid=$S_Itemid1" ;
					$return .= "<a href='".JRoute::_($url)."' class='eventCBAddLink'>". _JEMMYEVENTS_ADDNEW. "</a>";
					$query4 = "SELECT `published` FROM `#__jem_events` WHERE `created_by` = $userid and `published` = 0 " ;
					$_CB_database->setQuery($query4);
					$results4 = $_CB_database->loadObjectList();

					if ($results4 != null && count($results4) > 0) {
						$return .="<br><br>".count($results4)._JEMMYEVENTS_PUB."<br>";
					}
				}
			}


			/*
			 * Headers
			 *
			 * The classes are retrieved from:
			 * components/com_comprofiler/plugin/user/plug_cbjemmyevents/jemmyevents_cb.css
			 *
			 * The language strings are retrieved from:
			 * components/com_comprofiler/plugin/user/plug_cbjemmyevents/language/*languagecode*
			 *
			 * defining a new language can be done like:
			 * - add a new string, like: _MYEVENTS_NEWNAME
			 * - add the translation to the language file
			 */

			/* start of form */
			$return .= "\n\t<form method=\"post\" name=\"jemmyeventsForm\">";

			/* Start of Table */
			$return .= "\n\t<table  class='jemmyeventsCBTabTable' width=100% >";

			/* start of headerline */
			$return .= "\n\t\t<tr class='jemmyeventstableheader'>";

			/* Title header */
			$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableTitle'>";
			$return .= "\n\t\t\t\t" . _JEMMYEVENTS_TITLE;
			$return .= "\n\t\t\t</th>";

			/* Category header */
			if ($event_categories==1) {
				$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableCat'>";
				$return .= "\n\t\t\t\t" . _JEMMYEVENTS_CATEGORY;
				$return .= "\n\t\t\t</th>";
			}

			/* Startdate header */
			if($start_date==1) {
				$return .= "\n\t\t\t<th class='jemmyeventsCBTabTablestart'>";
				$return .= "\n\t\t\t\t" . _JEMMYEVENTS_START;
				$return .= "\n\t\t\t</th>";
			}


			/* Enddate header */
			if($end_date==1) {
				$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableExp'>";
				$return .= "\n\t\t\t\t" . _JEMMYEVENTS_EXPIRE;
				$return .= "\n\t\t\t</th>";
			}


			/* Attendees */
			if ($event_attending) {
				$return .= "\n\t\t\t<th class='jemmyeventsCBTabTableExp'>";
				$return .= "\n\t\t\t\t" . _JEMMYEVENTS_REGISTER;
				$return .= "\n\t\t\t</th>";
			}

			/* End of headerline */
			$return .= "\n\t\t</tr>";


			/* Counting data
			*
			* If data is available start with the rows
			* */
			$entryCount = 0;
			$cat = null;
			if(count($results)) {
				for ($i=0, $n = count($results); $i < $n; $i++) {
					$entryCount++;

					// defining variables
					$result = $results[$i];
// 					$checked = JHTML::_('grid.id', $i, $result->id);
					$catHref = JRoute::_(JEMHelperRoute::getCategoryRoute($result->catid));
					$cat = "\n\t\t\t<a href='{$catHref}' title='{$result->catname}'>{$result->catname}</a>";

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
					$result_titles = explode(" " , $result->title);
					$result_title = implode("-" , $result_titles);
					$return .= "\n\t\t\t<td class='jemmyeventsCBTabTableTitle'>";
					$return .= "\n\t\t\t\t<a href=\"". JRoute::_('index.php?option=com_jem&view=event&id='.$result->id.'&Itemid='.$S_Itemid1) ."\">{$result->title}</a>";
					$return .= "\n\t\t\t</td>";

					/* Category field */
					if ($event_categories) {
						$return .= "\n\t\t\t<td class='jemmyeventsCBTabTableCat'>";
						$return .= "\n\t\t\t\t{$cat}";
						$return .= "\n\t\t\t</td>";
					}

					/* Startdate field */
					if($start_date==1) {
						$startdate2 =	JEMOutput::formatdate($result->dates, $settings[0]->formatShortDate);
						$return .= "\n\t\t\t<td class='jemmyeventsCBTabTablestart'>";
						$return .= "\n\t\t\t\t{$startdate2}";
						$return .= "\n\t\t\t</td>";
					}

					/* Enddate
					 * if no enddate is given nothing will show up
					* */
					if($end_date==1) {
						$enddate2 =	JEMOutput::formatdate($result->enddates, $settings[0]->formatShortDate);
						$return .= "\n\t\t\t<td class='jemmyeventsCBTabTableExp'>";
						$return .= "\n\t\t\t\t{$enddate2}";
						$return .= "\n\t\t\t</td>";
					}

					/* Attendees field */
					/* @todo alter query, to add waitinglist */
					if ($event_attending) {
						$qry = "SELECT count(uid) AS regs FROM #__jem_register where `event`=$result->eventid";
						$_CB_database->setQuery($qry);
						$reg = $_CB_database->loadObjectList();

						$return .= "\n\t\t\t<td class='jemmyeventsCBTabTableReg'>";
						$return .= "\n\t\t\t\t{$reg[0]->regs}";
						$return .= "\n\t\t\t</td>";
					}

					/* Closing the rowline */
					$return .= "\n\t\t</tr>";
				} // end of displaying rows
			} else {
				// When no data has been found the user will see a message

				// display no listings
				$return .= _JEMMYEVENTS_NO_LISTING;
			}

			/* closing tag of the table */
			$return .="</table>";

			/* closing of the form */
			$return .="</form>";

			/* Message for at the bottom, below the table
			 *
			* At the top we did specify the variable
			* but not sure where we can fill it
			*/
			$return .= "\t\t<div>\n<p>". htmlspecialchars($event_tab_message). "</p></div>\n";

			/* Showing the code
			 *
			* We did specify the code above, but we do want to display it to the user
			* There were a lot of "$return ." and all of them will be printed.
			*/
			return $return;


		} // end or getDisplayTab function

	}
}
?>
