<?php
/**
 * @version 2.2.3
 * @package JEM
 * @copyright (C) 2013-2018 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

jimport('joomla.filesystem.file');
jimport('joomla.filesystem.folder');

// ensure JemFactory is loaded (because this class is used by modules or plugins too)
require_once(JPATH_SITE.'/components/com_jem/factory.php');

/**
 * Holds some usefull functions to keep the code a bit cleaner
 */
class JemHelper
{
	/**
	 * Pulls settings from database and stores in an static object
	 *
	 * @return object
	 */
	static public function config()
	{
		static $config;

		if (!is_object($config)) {
			$jemConfig = JemConfig::getInstance();
			$config = clone $jemConfig->toObject(); // We need a copy to ensure not to store 'params' we add below!

			$config->params = JComponentHelper::getParams('com_jem');
		}

		return $config;
	}

	/**
	 * Pulls settings from database and stores in an static object
	 *
	 * @return object
	 */
	static public function globalattribs()
	{
		static $globalregistry;
		if (!is_object($globalregistry)) {
			$globalregistry = new JRegistry(self::config()->globalattribs);
		}

		return $globalregistry;
	}

	/**
	 * Retrieves the CSS-settings from database and stores in an static object
	 */
	static public function retrieveCss()
	{
		static $registryCSS;
		if (!is_object($registryCSS)) {
			$registryCSS = new JRegistry(self::config()->css);
		}

		return $registryCSS;
	}

	/**
	 * Setup a file logger for JEM.
	 */
	static public function addFileLogger()
	{
		jimport('joomla.log.log');

		// Let admin choose the log level.
		$jemconfig = JemConfig::getInstance()->toRegistry();
		$lvl = (int)$jemconfig->get('globalattribs.loglevel', 0);

		switch ($lvl) {
		case 1: // ERROR or higher
			$loglevel = JLog::ERROR   * 2 - 1;
			break;
		case 2: // WARNING or higher
			$loglevel = JLog::WARNING * 2 - 1;
			break;
		case 3: // INFO or higher
			$loglevel = JLog::INFO    * 2 - 1;
			break;
		case 4: // DEBUG or higher
			$loglevel = JLog::DEBUG   * 2 - 1;
			break;
		case 5: // ALL
			$loglevel = JLog::ALL;
			break;
		case 0: // OFF
		default:
			$loglevel = 0;
			break;
		}

		if ($loglevel > 0) {
			JLog::addLogger(array('text_file' => 'jem.log.php', 'text_entry_format' => '{DATE} {TIME} {PRIORITY} {CATEGORY} {WHERE} : {MESSAGE}'), $loglevel, array('JEM'));
		}
	}

	/**
	 * Add en entry to JEM's log file.
	 *
	 * @param  $message The message to print
	 * @param  $where   The location the message was generated, default: null
	 * @param  $type    The log level, default: DEBUG
	 */
	static public function addLogEntry($message, $where = null, $type = JLog::DEBUG)
	{
		$logEntry = new JLogEntry($message, $type, 'JEM');
		$logEntry->where = empty($where) ? '' : ($where . '()');

		JLog::add($logEntry);
	}

	/**
	 * Performs daily scheduled cleanups
	 *
	 * Currently it archives and removes outdated events
	 * and takes care of the recurrence of events
	 */
	static public function cleanup($forced = 0)
	{
		$jemsettings  = JemHelper::config();
		$weekstart    = $jemsettings->weekdaystart;
		$anticipation = $jemsettings->recurrence_anticipation;

		$now = time(); // UTC
		$offset = idate('Z'); // timezone offset for "new day" test
		$lastupdate = (int)$jemsettings->lastupdate;
		$runningupdate = isset($jemsettings->runningupdate) ? $jemsettings->runningupdate : 0;
		$maxexectime = get_cfg_var('max_execution_time');
		$delay = min(86400, max(300, $maxexectime * 2));

		// New (local) day since last update?
		$nrdaysnow = floor(($now + $offset) / 86400);
		$nrdaysupdate = floor(($lastupdate + $offset) / 86400);

		if (($nrdaysnow > $nrdaysupdate) || $forced) {
			JemHelper::addLogEntry('forced: ' . $forced . ', now: '. $now . ', last update: ' . $lastupdate .
		                           ', running update: ' . $runningupdate . ', delay: ' . $delay . ', tz-offset: ' . $offset, __METHOD__);

			if (($runningupdate + $delay) < $now) {
				// Set timestamp of running cleanup
				JemConfig::getInstance()->set('runningupdate', $now);

				JemHelper::addLogEntry('  do cleanup...', __METHOD__);

				// trigger an event to let plugins handle whatever cleanup they want to do.
				if (JPluginHelper::importPlugin('jem')) {
					$dispatcher = JemFactory::getDispatcher();
					$dispatcher->trigger('onJemBeforeCleanup', array($jemsettings, $forced));
				}

				$db = JFactory::getDbo();
				$query = $db->getQuery(true);

				// Get the last event occurence of each recurring published events, with unlimited repeat, or last date not passed.
				// Ignore published field to prevent duplicate events.
				$nulldate = '0000-00-00';
				$query = ' SELECT id, CASE recurrence_first_id WHEN 0 THEN id ELSE recurrence_first_id END AS first_id, '
				       . ' recurrence_number, recurrence_type, recurrence_limit_date, recurrence_limit, recurrence_byday, '
				       . ' MAX(dates) as dates, MAX(enddates) as enddates, MAX(recurrence_counter) as counter '
				       . ' FROM #__jem_events '
				       . ' WHERE recurrence_type <> "0" '
				       . ' AND CASE recurrence_limit_date WHEN '.$nulldate.' THEN 1 ELSE NOW() < recurrence_limit_date END '
				       . ' AND recurrence_number <> "0" '
				       . ' GROUP BY first_id'
				       . ' ORDER BY dates DESC';

				$db->SetQuery($query);
				$recurrence_array = $db->loadAssocList();

				// If there are results we will be doing something with it
				foreach ($recurrence_array as $recurrence_row)
				{
					// get the info of reference event for the duplicates
					$ref_event = JTable::getInstance('Event', 'JemTable');
					$ref_event->load($recurrence_row['id']);

					$db = JFactory::getDbo();
					$query = $db->getQuery(true);
					$query->select('*');
					$query->from($db->quoteName('#__jem_events').' AS a');
					$query->where('id = '.(int)$recurrence_row['id']);
					$db->setQuery($query);
					$reference = $db->loadAssoc();

					// if reference event is "unpublished"(0) new event is "unpublished" too
					// but on "archived"(2) and "trashed"(-2) reference events create "published"(1) event
					if ($reference['published'] != 0) {
						$reference['published'] = 1;
					}

					// the first day of the week is used for certain rules
					$recurrence_row['weekstart'] = $weekstart;

					// calculate next occurence date
					$recurrence_row = JemHelper::calculate_recurrence($recurrence_row);

					// add events as long as we are under the interval and under the limit, if specified.
					while (($recurrence_row['recurrence_limit_date'] == $nulldate
							|| strtotime($recurrence_row['dates']) <= strtotime($recurrence_row['recurrence_limit_date']))
							&& strtotime($recurrence_row['dates']) <= time() + 86400 * $anticipation)
					{
						$new_event = JTable::getInstance('Event', 'JemTable');
						$new_event->bind($reference, array('id', 'hits', 'dates', 'enddates','checked_out_time','checked_out'));
						$new_event->recurrence_first_id = $recurrence_row['first_id'];
						$new_event->recurrence_counter = $recurrence_row['counter'] + 1;
						$new_event->dates = $recurrence_row['dates'];
						$new_event->enddates = $recurrence_row['enddates'];
						$new_event->_autocreate = true; // to tell table class this has to be stored AS IS (the underscore is important!)

						if ($new_event->store())
						{
							$recurrence_row['counter']++;
							//duplicate categories event relationships
							$query = ' INSERT INTO #__jem_cats_event_relations (itemid, catid) '
							       . ' SELECT ' . $db->Quote($new_event->id) . ', catid FROM #__jem_cats_event_relations '
							       . ' WHERE itemid = ' . $db->Quote($ref_event->id);
							$db->setQuery($query);

							if ($db->execute() === false) {
								// run query always but don't show error message to "normal" users
								$user = JemFactory::getUser();
								if($user->authorise('core.manage')) {
									echo JText::_('Error saving categories for event "' . $ref_event->title . '" new recurrences\n');
								}
							}
						}

						$recurrence_row = JemHelper::calculate_recurrence($recurrence_row);
					}
				}

				//delete outdated events
				if ($jemsettings->oldevent == 1) {
					$query = 'DELETE FROM #__jem_events WHERE dates > 0 AND '
					       .' DATE_SUB(NOW(), INTERVAL '.(int)$jemsettings->minus.' DAY) > (IF (enddates IS NOT NULL, enddates, dates))';
					$db->SetQuery($query);
					$db->execute();
				}

				//Set state archived of outdated events
				if ($jemsettings->oldevent == 2) {
					$query = 'UPDATE #__jem_events SET published = 2 WHERE dates > 0 AND '
					       .' DATE_SUB(NOW(), INTERVAL '.(int)$jemsettings->minus.' DAY) > (IF (enddates IS NOT NULL, enddates, dates)) '
					       .' AND published = 1';
					$db->SetQuery($query);
					$db->execute();
				}

				//Set state trashed of outdated events
				if ($jemsettings->oldevent == 3) {
					$query = 'UPDATE #__jem_events SET published = -2 WHERE dates > 0 AND '
					       .' DATE_SUB(NOW(), INTERVAL '.(int)$jemsettings->minus.' DAY) > (IF (enddates IS NOT NULL, enddates, dates)) '
					       .' AND published = 1';
					$db->SetQuery($query);
					$db->execute();
				}

				// Cleanup registrations
				$query = 'DELETE FROM #__jem_register WHERE event NOT IN (SELECT id FROM #__jem_events)';
				$db->SetQuery($query);
				$db->execute();

				// Set timestamp of last cleanup
				JemConfig::getInstance()->set('lastupdate', $now);
				// Clear timestamp of running cleanup
				JemConfig::getInstance()->set('runningupdate', 0);
			}

			JemHelper::addLogEntry('finished.', __METHOD__);
		}
	}

	/**
	 * this methode calculate the next date
	 */
	static public function calculate_recurrence($recurrence_row)
	{
		// get the recurrence information
		$recurrence_number = $recurrence_row['recurrence_number'];
		$recurrence_type = $recurrence_row['recurrence_type'];

		$day_time = 86400;	// 60s * 60min * 24h
		$week_time = $day_time * 7;
		$date_array = JemHelper::generate_date($recurrence_row['dates'], $recurrence_row['enddates']);

		switch($recurrence_type) {
			case "1":
				// +1 hour for the Summer to Winter clock change
				$start_day = mktime(1, 0, 0, $date_array["month"], $date_array["day"], $date_array["year"]);
				$start_day = $start_day + ($recurrence_number * $day_time);
				break;
			case "2":
				// +1 hour for the Summer to Winter clock change
				$start_day = mktime(1, 0, 0, $date_array["month"], $date_array["day"], $date_array["year"]);
				$start_day = $start_day + ($recurrence_number * $week_time);
				break;
			case "3": // month recurrence
				/*
				 * warning here, we have to make sure the date exists:
				 * 31 of october + 1 month = 31 of november, which doesn't exists => skip the date!
				 */
				$start_day = mktime(1,0,0,($date_array["month"] + $recurrence_number),$date_array["day"],$date_array["year"]);

				$i = 1;
				while (date('d', $start_day) != $date_array["day"] && $i < 20) { // not the same day of the month... try next date !
					$i++;
					$start_day = mktime(1,0,0,($date_array["month"] + $recurrence_number*$i),$date_array["day"],$date_array["year"]);
				}
				break;
			case "4": // weekday
				// the selected weekdays
				$selected = JemHelper::convert2CharsDaysToInt(explode(',', $recurrence_row['recurrence_byday']), 0);
				$days_names = array('sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday');
				$litterals = array('first', 'second', 'third', 'fourth');
				if (count($selected) == 0)
				{
					// this shouldn't happen, but if it does, to prevent problem use the current weekday for the repetition.
					JError::raiseWarning(500, JText::_('COM_JEM_WRONG_EVENTRECURRENCE_WEEKDAY'));
					$current_weekday = (int) $date_array["weekday"];
					$selected = array($current_weekday);
				}

				$start_day = null;
				foreach ($selected as $s)
				{
					$next = null;
					switch ($recurrence_number) {
						case 6: // before last 'x' of the month
							$next      = strtotime("previous ".$days_names[$s].' - 1 week ',
							                mktime(1,0,0,$date_array["month"]+1 ,1,$date_array["year"]));
							$nextmonth = strtotime("previous ".$days_names[$s].' - 1 week ',
							                mktime(1,0,0,$date_array["month"]+2 ,1,$date_array["year"]));
							break;
						case 5: // last 'x' of the month
							$next      = strtotime("previous ".$days_names[$s],
							                mktime(1,0,0,$date_array["month"]+1 ,1,$date_array["year"]));
							$nextmonth = strtotime("previous ".$days_names[$s],
							                mktime(1,0,0,$date_array["month"]+2 ,1,$date_array["year"]));
							break;
						case 4: // xth 'x' of the month
						case 3:
						case 2:
						case 1:
						default:
							$next      = strtotime($litterals[$recurrence_number-1]." ".$days_names[$s].' of this month',
							                mktime(1,0,0,$date_array["month"]   ,1,$date_array["year"]));
							$nextmonth = strtotime($litterals[$recurrence_number-1]." ".$days_names[$s].' of this month',
							                mktime(1,0,0,$date_array["month"]+1 ,1,$date_array["year"]));
							break;
					}

					// is the next / nextm day eligible for next date ?
					if ($next && $next > strtotime($recurrence_row['dates'])) // after current date !
					{
						if (!$start_day || $start_day > $next) { // comes before the current 'start_date'
							$start_day = $next;
						}
					}
					if ($nextmonth && (!$start_day || $start_day > $nextmonth)) {
						$start_day = $nextmonth;
					}
				}
				break;
		}

		if (!$start_day) {
			return false;
		}
		$recurrence_row['dates'] = date("Y-m-d", $start_day);

		if ($recurrence_row['enddates']) {
			$recurrence_row['enddates'] = date("Y-m-d", $start_day + $date_array["day_diff"]);
		}

		if ($start_day < $date_array["unixtime"]) {
			JError::raiseError(500, JText::_('COM_JEM_RECURRENCE_DATE_GENERATION_ERROR'));
		}

		return $recurrence_row;
	}

	/**
	 * Method to dissolve recurrence of given id.
	 *
	 * @param  int     The id to clear as recurrence first id.
	 *
	 * @return boolean True on success.
	 */
	static public function dissolve_recurrence($first_id)
	{
		// Sanitize the id.
		$first_id = (int)$first_id;

		if (empty($first_id)) {
			return false;
		}

		try {
			$db = JFactory::getDbo();
			$nulldate = explode(' ', $db->getNullDate());
			$db->setQuery('UPDATE #__jem_events'
			            . ' SET recurrence_first_id = 0, recurrence_type = 0'
			            . '   , recurrence_counter = 0, recurrence_number = 0'
			            . '   , recurrence_limit = 0, recurrence_limit_date = ' . $db->quote($nulldate[0])
			            . '   , recurrence_byday = ' . $db->quote('')
			            . ' WHERE recurrence_first_id = ' . $first_id
			             );
			$db->execute();
		} catch (Exception $e) {
			return false;
		}

		return true;
	}

	/**
	 * This method deletes an image file if unused.
	 *
	 * @param  string $type     one of 'event', 'venue', 'category', 'events', 'venues', 'categories'
	 * @param  mixed  $filename filename as stored in db, or null (which deletes all unused files)
	 *
	 * @return bool true on success, false on error
	 * @access public
	 */
	static public function delete_unused_image_files($type, $filename = null)
	{
		switch ($type) {
		case 'event':
		case 'events':
			$folder = 'events';
			$countquery_tmpl = ' SELECT id FROM #__jem_events WHERE datimage = ';
			$imagequery      = ' SELECT datimage AS image, COUNT(*) AS count FROM #__jem_events GROUP BY datimage';
			break;
		case 'venue':
		case 'venues':
			$folder = 'venues';
			$countquery_tmpl = ' SELECT id FROM #__jem_venues WHERE locimage = ';
			$imagequery      = ' SELECT locimage AS image, COUNT(*) AS count FROM #__jem_venues GROUP BY locimage';
			break;
		case 'category':
		case 'categories':
			$folder = 'categories';
			$countquery_tmpl = ' SELECT id FROM #__jem_categories WHERE image = ';
			$imagequery      = ' SELECT image, COUNT(*) AS count FROM #__jem_categories GROUP BY image';
			break;
		default;
			return false;
		}

		$fullPath = JPath::clean(JPATH_SITE.'/images/jem/'.$folder.'/'.$filename);
		$fullPaththumb = JPath::clean(JPATH_SITE.'/images/jem/'.$folder.'/small/'.$filename);
		if (is_file($fullPath)) {
			// Count usage and don't delete if used elsewhere.
			$db = JFactory::getDBO();
			$db->setQuery($countquery_tmpl . $db->quote($filename));
			if (null === ($usage = $db->loadObjectList())) {
				return false;
			}
			if (empty($usage)) {
				JFile::delete($fullPath);
				if (JFile::exists($fullPaththumb)) {
					JFile::delete($fullPaththumb);
				}

				return true;
			}
		}
		elseif (empty($filename) && is_dir($fullPath)) {
			// get image files used
			$db = JFactory::getDBO();
			$db->setQuery($imagequery);
			if (null === ($used = $db->loadAssocList('image', 'count'))) {
				return false;
			}

			// get all files and delete if not in $used
			$fileList = JFolder::files($fullPath);
			if ($fileList !== false) {
				foreach ($fileList as $file)
				{
					if (is_file($fullPath.$file) && substr($file, 0, 1) != '.' && !isset($used[$file])) {
						JFile::delete($fullPath.$file);
						if (JFile::exists($fullPaththumb.$file)) {
							JFile::delete($fullPaththumb.$file);
						}
					}
				}

				return true;
			}
		}

		return false;
	}

	/**
	 * This method deletes attachment files if unused.
	 *
	 * @param  mixed $type one of 'event', 'venue', 'category', ... or false for all
	 *
	 * @return bool true on success, false on error
	 * @access public
	 */
	static public function delete_unused_attachment_files($type = false)
	{
		$jemsettings = JemHelper::config();
		$basepath    = JPATH_SITE.'/'.$jemsettings->attachments_path;
		$db          = JFactory::getDBO();
		$res         = true;

		// Get list of all folders matching type (format is "$type$id")
		$folders = JFolder::folders($basepath, ($type ? '^'.$type : '.'), false, false, array('.', '..'));

		// Get list of all used attachments of given type
		$fnames = array();
		foreach ($folders as $f) {
			$fnames[] = $db->Quote($f);
		}
		$query = ' SELECT object, file '
		       . ' FROM #__jem_attachments ';
		if (!empty($fnames)) {
			$query .= ' WHERE object IN ('.implode(',', $fnames).')';
		}
		$db->setQuery($query);
		$files_used = $db->loadObjectList();
		$files = array();
		foreach ($files_used as $used) {
			$files[$used->object.'/'.$used->file] = true;
		}

		// Delete unused files and folders (ignore 'index.html')
		foreach ($folders as $folder) {
			$files = JFolder::files($basepath.'/'.$folder, '.', false, false, array('index.html'), array());
			if (!empty($files)) {
				foreach ($files as $file) {
					if (!array_key_exists($folder.'/'.$file, $files)) {
						$res &= JFile::delete($basepath.'/'.$folder.'/'.$file);
					}
				}
			}
			$files = JFolder::files($basepath.'/'.$folder, '.', false, true, array('index.html'), array());
			if (empty($files)) {
				$res &= JFolder::delete($basepath.'/'.$folder);
			}
		}
	}

	/**
	 * this method generate the date string to a date array
	 *
	 * @param  string the date string
	 * @return array  the date informations
	 * @access public
	 */
	static public function generate_date($startdate, $enddate)
	{
		$validEnddate = JemHelper::isValidDate($enddate);

		$startdate = explode("-",$startdate);
		$date_array = array("year" => $startdate[0],
							"month" => $startdate[1],
							"day" => $startdate[2],
							"weekday" => date("w",mktime(1,0,0,$startdate[1],$startdate[2],$startdate[0])),
							"unixtime" => mktime(1,0,0,$startdate[1],$startdate[2],$startdate[0]));

		if ($validEnddate) {
			$enddate = explode("-", $enddate);
			$day_diff = (mktime(1,0,0,$enddate[1],$enddate[2],$enddate[0]) - mktime(1,0,0,$startdate[1],$startdate[2],$startdate[0]));
			$date_array["day_diff"] = $day_diff;
		}

		return $date_array;
	}

	/**
	 * return day number of the week starting with 0 for first weekday
	 *
	 * @param  array of 2 letters day
	 * @return array of int
	 */
	static function convert2CharsDaysToInt($days, $firstday = 0)
	{
		$result = array();
		foreach ($days as $day)
		{
			switch (strtoupper($day))
			{
				case 'MO':
					$result[] = 1 - $firstday;
					break;
				case 'TU':
					$result[] = 2 - $firstday;
					break;
				case 'WE':
					$result[] = 3 - $firstday;
					break;
				case 'TH':
					$result[] = 4 - $firstday;
					break;
				case 'FR':
					$result[] = 5 - $firstday;
					break;
				case 'SA':
					$result[] = 6 - $firstday;
					break;
				case 'SU':
					$result[] = (7 - $firstday) % 7;
					break;
				default:
					JError::raiseWarning(500, JText::_('COM_JEM_WRONG_EVENTRECURRENCE_WEEKDAY'));
			}
		}

		return $result;
	}


	/**
	 * Build the select list for access level
	 */
	static public function getAccesslevelOptions($ownonly = false, $disabledLevels = false)
	{
		$db = JFactory::getDBO();
		$where = '';
		$selDisabled = '';
		if ($ownonly) {
			$levels = JFactory::getUser()->getAuthorisedViewLevels();
			$allLevels = $levels;
			if (!empty($disabledLevels)) {
				if (!is_array($disabledLevels)) {
					$disabledLevels = array($disabledLevels);
				}
				foreach ($disabledLevels as $level) {
					if (((int)$level > 0) && (!in_array((int)$level, $levels))) {
						$allLevels[] = $level;
					}
				}
				$selDisabled = ', IF (id IN ('.implode(',', $levels).'), \'\', \'disabled\') AS disabled';
			}
			$where = ' WHERE id IN ('.implode(',', $allLevels).')';
		}

		$query = 'SELECT id AS value, title AS text' . $selDisabled
		       . ' FROM #__viewlevels'
		       . $where
		       . ' ORDER BY ordering, id'
		       ;

		//JemHelper::addLogEntry('AccessLevel query: ' . $query, __METHOD__);

		$db->setQuery($query);
		$groups = $db->loadObjectList();

		//JemHelper::addLogEntry('result: ' . print_r($groups, true), __METHOD__);

		return $groups;
	}

	static public function buildtimeselect($max, $name, $selected, $class = array('class'=>'inputbox'))
	{
		$timelist = array();
		$timelist[0] = JHtml::_('select.option', '', '');

		if ($max == 23) {
			// does user prefer 12 or 24 hours format?
			$jemreg = JemConfig::getInstance()->toRegistry();
			$format = $jemreg->get('formathour', false);
		} else {
			$format = false;
		}

		foreach (range(0, $max) as $value) {
			if ($value < 10) {
				$value = '0'.$value;
			}

			$timelist[] = JHtml::_('select.option', $value, ($format ? strftime($format, strtotime("$value:00:00")) : $value));
		}

		return JHtml::_('select.genericlist', $timelist, $name, $class, 'value', 'text', $selected);
	}

	/**
	 * returns mime type of a file
	 *
	 * @param  string file path
	 * @return string mime type
	 */
	static public function getMimeType($filename)
	{
		if (function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME);
			$mimetype = finfo_file($finfo, $filename);
			finfo_close($finfo);
			return $mimetype;
		}
		else if (function_exists('mime_content_type') && 0)
		{
			return mime_content_type($filename);
		}
		else
		{
			$mime_types = array(
				'txt' => 'text/plain',
				'htm' => 'text/html',
				'html' => 'text/html',
				'php' => 'text/html',
				'css' => 'text/css',
				'js' => 'application/javascript',
				'json' => 'application/json',
				'xml' => 'application/xml',
				'swf' => 'application/x-shockwave-flash',
				'flv' => 'video/x-flv',

				// images
				'png' => 'image/png',
				'jpe' => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'jpg' => 'image/jpeg',
				'gif' => 'image/gif',
				'bmp' => 'image/bmp',
				'ico' => 'image/vnd.microsoft.icon',
				'tiff' => 'image/tiff',
				'tif' => 'image/tiff',
				'svg' => 'image/svg+xml',
				'svgz' => 'image/svg+xml',

				// archives
				'zip' => 'application/zip',
				'rar' => 'application/x-rar-compressed',
				'exe' => 'application/x-msdownload',
				'msi' => 'application/x-msdownload',
				'cab' => 'application/vnd.ms-cab-compressed',

				// audio/video
				'mp3' => 'audio/mpeg',
				'qt' => 'video/quicktime',
				'mov' => 'video/quicktime',

				// adobe
				'pdf' => 'application/pdf',
				'psd' => 'image/vnd.adobe.photoshop',
				'ai' => 'application/postscript',
				'eps' => 'application/postscript',
				'ps' => 'application/postscript',

				// ms office
				'doc' => 'application/msword',
				'rtf' => 'application/rtf',
				'xls' => 'application/vnd.ms-excel',
				'ppt' => 'application/vnd.ms-powerpoint',

				// open office
				'odt' => 'application/vnd.oasis.opendocument.text',
				'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
			);

			//$ext = strtolower(array_pop(explode('.',$filename)));
			$var = explode('.',$filename);
			$ext = strtolower(array_pop($var));
			if (array_key_exists($ext, $mime_types)) {
				return $mime_types[$ext];
			}
			else {
				return 'application/octet-stream';
			}
		}
	}

	/**
	 * updates waiting list of specified event
	 *
	 * @param  int     event id
	 * @param  boolean bump users off/to waiting list
	 * @return bool
	 */
	static public function updateWaitingList($event)
	{
		$db = Jfactory::getDBO();

		// get event details for registration
		$query = ' SELECT maxplaces, waitinglist FROM #__jem_events WHERE id = ' . $db->Quote($event);
		$db->setQuery($query);
		$event_places = $db->loadObject();

		// get attendees after deletion, and their status
		$query = 'SELECT r.id, r.waiting '
		       . ' FROM #__jem_register AS r'
		       . ' WHERE r.status = 1 AND r.event = '.$db->Quote($event)
		       . ' ORDER BY r.uregdate ASC '
		       ;
		$db->SetQuery($query);
		$res = $db->loadObjectList();

		$registered = 0;
		$waiting = array();
		foreach ((array) $res as $r)
		{
			if ($r->waiting) {
				$waiting[] = $r->id;
			} else {
				$registered++;
			}
		}

		if (($registered < $event_places->maxplaces) && count($waiting))
		{
			// need to bump users to attending status
			$bumping = array_slice($waiting, 0, $event_places->maxplaces - $registered);
			$query = ' UPDATE #__jem_register SET waiting = 0 WHERE id IN ('.implode(',', $bumping).')';
			$db->setQuery($query);
			if ($db->execute() === false) {
				Jerror::raisewarning(0, JText::_('COM_JEM_FAILED_BUMPING_USERS_FROM_WAITING_TO_CONFIRMED_LIST').': '.$db->getErrorMsg());
			} else {
				foreach ($bumping AS $register_id)
				{
					JPluginHelper::importPlugin('jem');
					$dispatcher = JemFactory::getDispatcher();
					$res = $dispatcher->trigger('onUserOnOffWaitinglist', array($register_id));
				}
			}
		}

		return true;
	}

	/**
	 * Adds attendees numbers to rows
	 *
	 * @param  $data reference to event rows
	 * @return false on error, $data on success
	 */
	static public function getAttendeesNumbers(& $data)
	{
		// Make sure this is an array and it is not empty
		if (!is_array($data) || !count($data)) {
			return false;
		}

		// Get the ids of events
		$ids = array();
		foreach ($data as $event) {
			$ids[] = (int)$event->id;
		}
		$ids = implode(",", $ids);

		$db = Jfactory::getDBO();

		// status 1: user registered (attendee or waiting list), status -1: user exlicitely unregistered, status 0: user is invited but hadn't answered yet
		$query = ' SELECT COUNT(id) as total,'
		       . '        COUNT(IF(status =  1 AND waiting <= 0, 1, null)) AS registered,'
		       . '        COUNT(IF(status =  1 AND waiting >  0, 1, null)) AS waiting,'
		       . '        COUNT(IF(status = -1,                  1, null)) AS unregistered,'
		       . '        COUNT(IF(status =  0,                  1, null)) AS invited,'
		       . '        event '
		       . ' FROM #__jem_register '
		       . ' WHERE event IN (' . $ids .')'
		       . ' GROUP BY event ';

		$db->setQuery($query);
		$res = $db->loadObjectList('event');

		foreach ($data as $k => &$event) { // by reference for direct edit
			if (isset($res[$event->id])) {
				$event->regTotal   = $res[$event->id]->total;
				$event->regCount   = $res[$event->id]->registered;
				$event->waiting    = $res[$event->id]->waiting;
				$event->unregCount = $res[$event->id]->unregistered;
				$event->invited    = $res[$event->id]->invited;
			} else {
				$event->regTotal   = 0;
				$event->regCount   = 0;
				$event->waiting    = 0;
				$event->unregCount = 0;
				$event->invited    = 0;
			}
			$event->available = max(0, $event->maxplaces - $event->regCount);
		}

		return $data;
	}

	/**
	 * returns timezone name
	 */
	static public function getTimeZoneName()
	{
		$user     = JemFactory::getUser();
		$userTz   = $user->getParam('timezone');
		$timeZone = JFactory::getConfig()->get('offset');

		/* disabled for now
		if($userTz) {
			$timeZone = $userTz;
		}
		*/
		return $timeZone;
	}

	/**
	 * return initialized calendar tool class for ics export
	 *
	 * @return object
	 */
	static public function getCalendarTool()
	{
		require_once JPATH_SITE.'/components/com_jem/classes/iCalcreator.class.php';
		$timezone_name = JemHelper::getTimeZoneName();

		$vcal = new vcalendar();
		if (!file_exists(JPATH_SITE.'/cache/com_jem')) {
			jimport('joomla.filesystem.folder');
			JFolder::create(JPATH_SITE.'/cache/com_jem');
		}
		$vcal->setConfig('directory', JPATH_SITE.'/cache/com_jem');
		$vcal->setProperty("calscale", "GREGORIAN");
		$vcal->setProperty('method', 'PUBLISH');
		if ($timezone_name) {
			$vcal->setProperty("X-WR-TIMEZONE", $timezone_name);
		}
		return $vcal;
	}

	static public function icalAddEvent(&$calendartool, $event)
	{
		require_once JPATH_SITE.'/components/com_jem/classes/iCalcreator.class.php';
		$jemsettings   = JemHelper::config();
		$timezone_name = JemHelper::getTimeZoneName();
		$config        = JFactory::getConfig();
		$sitename      = $config->get('sitename');

		// get categories names
		$categories = array();
		foreach ($event->categories as $c) {
			$categories[] = $c->catname;
		}

		// no start date...
		$validdate = JemHelper::isValidDate($event->dates);

		if (!$event->dates || !$validdate) {
			return false;
		}

		// make end date same as start date if not set
		if (!$event->enddates) {
			$event->enddates = $event->dates;
		}

		// start
		if (!preg_match('/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/', $event->dates, $start_date)) {
			JError::raiseError(0, JText::_('COM_JEM_ICAL_EXPORT_WRONG_STARTDATE_FORMAT'));
		}

		$date = array('year' => (int) $start_date[1], 'month' => (int) $start_date[2], 'day' => (int) $start_date[3]);

		// all day event if start time is not set
		if (!$event->times) // all day !
		{
			$dateparam = array('VALUE' => 'DATE');

			// for ical all day events, dtend must be send to the next day
			$event->enddates = strftime('%Y-%m-%d', strtotime($event->enddates.' +1 day'));

			if (!preg_match('/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/', $event->enddates, $end_date)) {
				JError::raiseError(0, JText::_('COM_JEM_ICAL_EXPORT_WRONG_ENDDATE_FORMAT'));
			}

			$date_end = array('year' => $end_date[1], 'month' => $end_date[2], 'day' => $end_date[3]);
			$dateendparam = array('VALUE' => 'DATE');
		}
		else // not all day events, there is a start time
		{
			if (!preg_match('/([0-9]{2}):([0-9]{2}):([0-9]{2})/', $event->times, $start_time)) {
				JError::raiseError(0, JText::_('COM_JEM_ICAL_EXPORT_WRONG_STARTTIME_FORMAT'));
			}

			$date['hour'] = $start_time[1];
			$date['min']  = $start_time[2];
			$date['sec']  = $start_time[3];
			$dateparam = array('VALUE' => 'DATE-TIME');
			if ($jemsettings->ical_tz == 1) {
				$dateparam['TZID'] = $timezone_name;
			}

			if (!$event->endtimes || $event->endtimes == '00:00:00') {
				$event->endtimes = $event->times;
			}

			// if same day but end time < start time, change end date to +1 day
			if ($event->enddates == $event->dates &&
			    strtotime($event->dates.' '.$event->endtimes) < strtotime($event->dates.' '.$event->times))
			{
				$event->enddates = strftime('%Y-%m-%d', strtotime($event->enddates.' +1 day'));
			}

			if (!preg_match('/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2})/', $event->enddates, $end_date)) {
				JError::raiseError(0, JText::_('COM_JEM_ICAL_EXPORT_WRONG_ENDDATE_FORMAT'));
			}

			$date_end = array('year' => $end_date[1], 'month' => $end_date[2], 'day' => $end_date[3]);

			if (!preg_match('/([0-9]{2}):([0-9]{2}):([0-9]{2})/', $event->endtimes, $end_time)) {
				JError::raiseError(0, JText::_('COM_JEM_ICAL_EXPORT_WRONG_STARTTIME_FORMAT'));
			}

			$date_end['hour'] = $end_time[1];
			$date_end['min']  = $end_time[2];
			$date_end['sec']  = $end_time[3];
			$dateendparam = array('VALUE' => 'DATE-TIME');
			if ($jemsettings->ical_tz == 1) {
				$dateendparam['TZID'] = $timezone_name;
			}
		}

		// item description text
		$description = $event->title.'\\n';
		$description .= JText::_('COM_JEM_CATEGORY').': '.implode(', ', $categories).'\\n';

		$link = JUri::root().JemHelperRoute::getEventRoute($event->slug);
		$link = JRoute::_($link);
		$description .= JText::_('COM_JEM_ICS_LINK').': '.$link.'\\n';

		// location
		$location = array($event->venue);
		if (isset($event->street) && !empty($event->street)) {
			$location[] = $event->street;
		}

		if (isset($event->postalCode) && !empty($event->postalCode) && isset($event->city) && !empty($event->city)) {
			$location[] = $event->postalCode.' '.$event->city;
		} else {
			if (isset($event->postalCode) && !empty($event->postalCode)) {
				$location[] = $event->postalCode;
			}
			if (isset($event->city) && !empty($event->city)) {
				$location[] = $event->city;
			}
		}

		if (isset($event->countryname) && !empty($event->countryname)) {
			$exp = explode(",",$event->countryname);
			$location[] = $exp[0];
		}

		$location = implode(",", $location);

		$e = new vevent();
		$e->setProperty('summary', $event->title);
		$e->setProperty('categories', implode(', ', $categories));
		$e->setProperty('dtstart', $date, $dateparam);
		if (count($date_end)) {
			$e->setProperty('dtend', $date_end, $dateendparam);
		}
		$e->setProperty('description', $description);
		if ($location != '') {
			$e->setProperty('location', $location);
		}
		$e->setProperty('url', $link);
		$e->setProperty('uid', 'event'.$event->id.'@'.$sitename);
		$calendartool->addComponent($e); // add component to calendar
		return true;
	}

	/**
	 * return true is a date is valid (not null, or 0000-00...)
	 *
	 * @param  string $date
	 * @return boolean
	 */
	static public function isValidDate($date)
	{
		if (is_null($date)) {
			return false;
		}
		if ($date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
			return false;
		}
		if (!strtotime($date)) {
			return false;
		}
		return true;
	}

	/**
	 * return true is a time is valid (not null, or 00:00:00...)
	 *
	 * @param  string $time
	 * @return boolean
	 */
	static public function isValidTime($time)
	{
		if (is_null($time)) {
			return false;
		}

		if (!strtotime($time)) {
			return false;
		}
		return true;
	}

	/**
	 * Returns array of positive numbers
	 *
	 * @param  mixed array or string with comma separated list of ids
	 * @return mixed array of numbers greater zero or false
	 */
	static public function getValidIds($ids_in)
	{
		$ids_out = array();
		$tmp = is_array($ids_in) ? $ids_in : explode(',', $ids_in);
		foreach ($tmp as $id) {
			if ((int)$id > 0) {
				$ids_out[] = (int)$id;
			}
		}

		return (empty($ids_out) ? false : $ids_out);
	}

	/**
	 * Creates a tooltip
	 */
	static public function caltooltip($tooltip, $title = '', $text = '', $href = '', $class = '', $time = '', $color = '')
	{
		if (version_compare(JVERSION, '3.3', 'lt')) {
			$tooltip = htmlspecialchars($tooltip);
			if ($title) {
				$title = htmlspecialchars($title);
				$title = $title . '::';
			}
		} else {
			// on Joomla! 3.3+ we must use the new tooltips
			JHtml::_('bootstrap.tooltip');
			if (0) { /* old style using 'hasTip' */
				$title = JHtml::tooltipText($title, '<div style="font-weight:normal;">'.$tooltip.'</div>', 0);
			} else { /* new style using 'has Tooltip' */
				$class = str_replace('hasTip', '', $class) . ' hasTooltip';
				$title = JHtml::tooltipText($title, $tooltip, 0); // this calls htmlspecialchars()
			}
			$tooltip = '';
		}

		if ($href) {
			$href = JRoute::_ ($href);
			$tip = '<span class="'.$class.'" title="'.$title.$tooltip.'"><a href="'.$href.'">'.$time.$text.'</a></span>';
		} else {
			$tip = '<span class="'.$class.'" title="'.$title.$tooltip.'">'.$text.'</span>';
		}

		return $tip;
	}

	/**
	 * Function to retrieve IP
	 * @author: https://gist.github.com/cballou/2201933
	 */
	static public function retrieveIP()
	{
		$ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
		foreach ($ip_keys as $key) {
			if (array_key_exists($key, $_SERVER) === true) {
				foreach (explode(',', $_SERVER[$key]) as $ip) {
					// trim for safety measures
					$ip = trim($ip);
					// attempt to validate IP
					if (self::validate_ip($ip)) {
						return $ip;
					}
				}
			}
		}
		return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : false;
	}

	/**
	 * Ensures an ip address is both a valid IP and does not fall within
	 * a private network range.
	 *
	 * @author: https://gist.github.com/cballou/2201933
	 */
	static public function validate_ip($ip)
	{
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
			return false;
		}
		return true;
	}

	static public function loadCss($css)
	{
		$settings = self::retrieveCss();

		if ($settings->get('css_'.$css.'_usecustom','0')) {

			# we want to use custom so now check if we've a file
			$file = $settings->get('css_'.$css.'_customfile');
			$is_file = false;

			# something was filled, now check if we've a valid file
			if ($file) {
				$file = preg_replace('%^/([^/]*)%', '$1', $file); // remove leading single slash
				$is_file = JFile::exists(JPATH_SITE.'/'.$file);

				if ($is_file) {
					# at this point we do have a valid file but let's check the extension too.
					$ext =  JFile::getExt($file);
					if ($ext != 'css') {
						# the file is valid but the extension not so let's return false
						$is_file = false;
					}
				}
			}

			if ($is_file) {
				# we do have a valid file so we will use it.
				$css = JHtml::_('stylesheet', $file, array(), false);
			} else {
				# unfortunately we don't have a valid file so we're looking at the default
				$css = JHtml::_('stylesheet', 'com_jem/'.$css.'.css', array(), true);
			}
		} else {
			# here we want to use the normal css
			$css = JHtml::_('stylesheet', 'com_jem/'.$css.'.css', array(), true);
		}

		return $css;
	}

	static public function defineCenterMap($data = false)
	{
		# retrieve venue
		$venue = $data->getValue('venue');

		if ($venue) {
			# latitude/longitude
			$lat  = $data->getValue('latitude');
			$long = $data->getValue('longitude');

			if ($lat == 0.000000) {
				$lat = null;
			}

			if ($long == 0.000000) {
				$long = null;
			}

			if ($lat && $long) {
				$location = '['.$data->getValue('latitude').','.$data->getValue('longitude').']';
			} else {
				# retrieve address-info
				$postalCode = $data->getValue('postalCode');
				$city       = $data->getValue('city');
				$street     = $data->getValue('street');

				$location = '"'.$street.' '.$postalCode.' '.$city.'"';
			}
			$location = 'location:'.$location.',';
		} else {
			$location = '';
		}

		return $location;
	}

	/**
	 * Load Custom CSS
	 *
	 * @return boolean
	 */
	static public function loadCustomCss()
	{
		$settings = self::retrieveCss();

		$style = "";

		# background-colors
		$bg_filter            = $settings->get('css_color_bg_filter');
		$bg_h2                = $settings->get('css_color_bg_h2');
		$bg_jem               = $settings->get('css_color_bg_jem');
		$bg_table_th          = $settings->get('css_color_bg_table_th');
		$bg_table_td          = $settings->get('css_color_bg_table_td');
		$bg_table_tr_entry2   = $settings->get('css_color_bg_table_tr_entry2');
		$bg_table_tr_hover    = $settings->get('css_color_bg_table_tr_hover');
		$bg_table_tr_featured = $settings->get('css_color_bg_table_tr_featured');

		if ($bg_filter) {
			$style .= "div#jem #jem_filter {background-color:".$bg_filter.";}";
		}

		if ($bg_h2) {
			$style .= "div#jem h2 {background-color:".$bg_h2.";}";
		}

		if ($bg_jem) {
			$style .= "div#jem {background-color:".$bg_jem.";}";
		}

		if ($bg_table_th) {
			$style .= "div#jem table.eventtable th {background-color:" . $bg_table_th . ";}";
		}

		if ($bg_table_td) {
			$style .= "div#jem table.eventtable td {background-color:" . $bg_table_td . ";}";
		}

		if ($bg_table_tr_entry2) {
			$style .= "div#jem table.eventtable tr.sectiontableentry2 td {background-color:" . $bg_table_tr_entry2 . ";}";
		}

		if ($bg_table_tr_hover) {
			$style .= "div#jem table.eventtable tr:hover td {background-color:" . $bg_table_tr_hover . ";}";
		}

		if ($bg_table_tr_featured) {
			$style .= "div#jem table.eventtable tr.featured td {background-color:" . $bg_table_tr_featured . ";}";
		}

		# border-colors
		$border_filter   = $settings->get('css_color_border_filter');
		$border_h2       = $settings->get('css_color_border_h2');
		$border_table_th = $settings->get('css_color_border_table_th');
		$border_table_td = $settings->get('css_color_border_table_td');

		if ($border_filter) {
			$style .= "div#jem #jem_filter {border-color:" . $border_filter . ";}";
		}

		if ($border_h2) {
			$style .= "div#jem h2 {border-color:".$border_h2.";}";
		}

		if ($border_table_th) {
			$style .= "div#jem table.eventtable th {border-color:" . $border_table_th . ";}";
		}
		if ($border_table_td) {
			$style .= "div#jem table.eventtable td {border-color:" . $border_table_td . ";}";
		}

		# font-color
		$font_table_h2   = $settings->get('css_color_font_h2');
		$font_table_td   = $settings->get('css_color_font_table_td');
		$font_table_td_a = $settings->get('css_color_font_table_td_a');

		if ($font_table_h2) {
			$style .= "div#jem h2 {color:" . $font_table_h2 . ";}";
		}

		if ($font_table_td) {
			$style .= "div#jem table.eventtable td {color:" . $font_table_td . ";}";
		}

		if ($font_table_td_a) {
			$style .= "div#jem table.eventtable td a {color:" . $font_table_td_a . ";}";
		}

		$document = JFactory::getDocument();
		$document->addStyleDeclaration($style);

		return true;
	}

	/**
	 * Loads Custom Tags
	 *
	 * @return boolean
	 */
	static public function loadCustomTag()
	{
		$document = JFactory::getDocument();
		$tag = "";
		$tag .= "<!--[if IE]><style type='text/css'>.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->";

		$document->addCustomTag($tag);

		return true;
	}

	/**
	 * Get a variable from the manifest file (actually, from the manifest cache).
	 *
	 * @param  $column  manifest_cache(1),params(2)
	 * @param  $setting name of setting to retrieve
	 * @param  $type    compononent(1), plugin(2)
	 * @param  $name    name to search in column name
	 */
	static public function getParam($column, $setting, $type, $name)
	{
		switch ($column) {
			case 1:
				$column = 'manifest_cache';
				break;
			case 2:
				$column = 'params';
				break;
		}

		switch ($type) {
			case 1:
				$type = 'component';
				break;
			case 2:
				$type = 'plugin';
				break;
			case 3:
				$type = 'module';
				break;
		}

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select(array($column));
		$query->from('#__extensions');
		$query->where(array('name = '.$db->quote($name),'type = '.$db->quote($type)));
		$db->setQuery($query);

		$manifest = json_decode($db->loadResult(), true);
		$result = $manifest[ $setting ];

		if (empty($result)) {
			$result = 'N/A';
		}

		return $result;
	}

	static public function getCountryOptions()
	{
		$options = array();
		$options = array_merge(JemHelperCountries::getCountryOptions(),$options);

		array_unshift($options, JHtml::_('select.option', '0', JText::_('COM_JEM_SELECT_COUNTRY')));

		return $options;
	}

	/**
	 * This method transliterates a string into a URL
	 * safe string or returns a URL safe UTF-8 string
	 * based on the global configuration
	 *
	 * @param  string  $string  String to process
	 *
	 * @return string  Processed string
	 *
	 * @see    JApplication, JApplicationHelper
	 * @since  2.1.7
	 */
	static public function stringURLSafe($string)
	{
		if (version_compare(JVERSION, '3.2', 'ge')) {
			return JApplicationHelper::stringURLSafe($string);
		} else {
			return JApplication::stringURLSafe($string);
		}
	}
}
