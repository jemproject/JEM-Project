<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Holds the logic for all output related things
 *
 * @package JEM
 */
class JEMOutput {

	/**
	 * Writes footer.
	 *
	 */
	static function footer()
	{
		$app = JFactory::getApplication();
		$params = $app->getParams();

		if ($app->input->get('print','','int')) {
			//button in popup
		} else {
			// if ($params->get('copyright') == 1) {
			echo '<font color="grey">Powered by <a href="http://www.joomlaeventmanager.net" target="_blank">JEM</a></font>';
			// }
		}
	}

	/**
	 * Writes Event submission button
	 *
	 * @param int $dellink Access of user
	 * @param array $params needed params
	 **/
	static function submitbutton($dellink, &$params)
	{
		$settings = JEMHelper::config();
		$app = JFactory::getApplication();

		if ($dellink == 1) {
			JHTML::_('behavior.tooltip');

			if ($settings->icons) {
				$image = JHTML::image("media/com_jem/images/submitevent.png",JText::_('COM_JEM_DELIVER_NEW_EVENT'));
			} else {
				$image = JText::_('COM_JEM_DELIVER_NEW_EVENT');
			}

			if ($app->input->get('print','','int')) {
				//button in popup
				$output = '';
			} else {
				$link = 'index.php?view=editevent';
				$overlib = JText::_('COM_JEM_SUBMIT_EVENT_TIP');
				$output = '<a href="'.JRoute::_($link).'" class="editlinktip hasTip" title="'.JText::_('COM_JEM_DELIVER_NEW_EVENT')
				.'::'.$overlib.'">'.$image.'</a>';
			}
			return $output;
		}

		return;
	}

	/**
	 * Writes addvenuebutton
	 *
	 * @param int $addvenuelink Access of user
	 * @param array $params needed params
	 * @param $settings, retrieved from settings-table
	 *
	 * Active in views:
	 * venue, venues
	 **/
	static function addvenuebutton($addvenuelink, $params, $settings)
	{
		$app = JFactory::getApplication();

		if ($addvenuelink == 1) {
			JHTML::_('behavior.tooltip');

			if ($settings->icons) {
				$image = JHTML::image("media/com_jem/images/addvenue.png",JText::_('COM_JEM_DELIVER_NEW_VENUE'));
			} else {
				$image = JText::_('COM_JEM_DELIVER_NEW_VENUE');
			}

			if ($app->input->get('print','','int')) {
				//button in popup
				$output = '';
			} else {
				$link = 'index.php?view=editvenue';
				$overlib = JText::_('COM_JEM_DELIVER_NEW_VENUE_DESC');
				$output = '<a href="'.JRoute::_($link).'" class="editlinktip hasTip" title="'.JText::_('COM_JEM_DELIVER_NEW_VENUE')
				.'::'.$overlib.'">'.$image.'</a>';
			}
			return $output;
		}

		return;
	}

	/**
	 * Writes Archivebutton
	 *
	 * @param array $params needed params
	 * @param string $task The current task
	 *
	 * Views:
	 * Categories, Categoriesdetailed, Category, Eventslist, Search, Venue, Venues
	 */
	static function archivebutton(&$params, $task = NULL, $id = NULL)
	{
		$app = JFactory::getApplication();
		$settings = JEMHelper::config();

		if ($settings->show_archive_icon) {
			if ($settings->oldevent == 2) {

				JHTML::_('behavior.tooltip');

				$view = JRequest::getWord('view');

				if ($task == 'archive') {
					if ($settings->icons) {
						$image = JHTML::image("media/com_jem/images/el.png",JText::_('COM_JEM_SHOW_EVENTS'));
					} else {
						$image = JText::_('COM_JEM_SHOW_EVENTS');
					}
					$overlib = JText::_('COM_JEM_SHOW_EVENTS_TIP');
					$title = JText::_('COM_JEM_SHOW_EVENTS');

					if ($id) {
						$link = JRoute::_('index.php?view='.$view.'&id='.$id);
					} else {
						$link = JRoute::_('index.php');
					}
				} else {
					if ($settings->icons) {
						$image = JHTML::image("media/com_jem/images/archive_front.png",JText::_('COM_JEM_SHOW_ARCHIVE'));
					} else {
						$image = JText::_('COM_JEM_SHOW_ARCHIVE');
					}
					$overlib = JText::_('COM_JEM_SHOW_ARCHIVE_TIP');
					$title = JText::_('COM_JEM_SHOW_ARCHIVE');

					if ($id) {
						$link = JRoute::_('index.php?view='.$view.'&id='.$id.'&task=archive');
					} else {
						$link = JRoute::_('index.php?view='.$view.'&task=archive');
					}
				}

				if ($app->input->get('print','','int')) {
					//button in popup
				} else{
					return '<a href="'.$link.'" class="editlinktip hasTip" title="'.$title.'::'.$overlib.'">'.$image.'</a>';
				}
			}
		}
		return;
	}

	/**
	 * Creates the edit button
	 *
	 * @param int $Itemid
	 * @param int $id
	 * @param array $params
	 * @param int $allowedtoedit
	 * @param string $view
	 *
	 * Views:
	 * Event, Venue
	 */
	static function editbutton($Itemid, $id, &$params, $allowedtoedit, $view)
	{
		$app = JFactory::getApplication();
		$settings = JEMHelper::config();

		if ($allowedtoedit) {
			JHTML::_('behavior.tooltip');

			switch ($view)
			{
				case 'editevent':
					if ($settings->icons) {
						$image = JHTML::image("media/com_jem/images/calendar_edit.png",JText::_('COM_JEM_EDIT_EVENT'));
					} else {
						$image = JText::_('COM_JEM_EDIT_EVENT');
					}
					$overlib = JText::_('COM_JEM_EDIT_EVENT_TIP');
					$text = JText::_('COM_JEM_EDIT_EVENT');
					break;

				case 'editvenue':
					if ($settings->icons) {
						$image = JHTML::image("media/com_jem/images/calendar_edit.png",JText::_('COM_JEM_EDIT_EVENT'));
					} else {
						$image = JText::_('COM_JEM_EDIT_VENUE');
					}
					$overlib = JText::_('COM_JEM_EDIT_VENUE_TIP');
					$text = JText::_('COM_JEM_EDIT_VENUE');
					break;
			}

		if ($app->input->get('print','','int')) {
				//button in popup
			} else {
				$link = 'index.php?view='.$view.'&id='.$id.'&returnid='.$Itemid;
				return '<a href="'.JRoute::_($link).'" class="editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.'</a>';
			}
		}
		return;
	}

	/**
	 * Creates the print button
	 *
	 * @param string $print_link
	 * @param array $params
	 */
	static function printbutton($print_link, &$params)
	{
		$app = JFactory::getApplication();
		$settings = JEMHelper::config();
		if ($settings->show_print_icon) {
			JHTML::_('behavior.tooltip');

			$status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=640,height=480,directories=no,location=no';

			if ($settings->icons) {
				$image = JHTML::_('image','system/printButton.png', JText::_('JGLOBAL_PRINT'), NULL, true);
			} else {
				$image = JText::_('COM_JEM_PRINT');
			}

			if ($app->input->get('print','','int')) {
				//button in popup
				$overlib = JText::_('COM_JEM_PRINT_TIP');
				$text = JText::_('COM_JEM_PRINT');
				$title = 'title='.JText::_('JGLOBAL_PRINT');
				$pimage = JHTML::_('image','system/printButton.png', JText::_('JGLOBAL_PRINT'), $title, true);
				$output = '<a href="#" onclick="window.print();return false;">'.$pimage.'</a>';
			} else {
				//button in view
				$overlib = JText::_('COM_JEM_PRINT_TIP');
				$text = JText::_('COM_JEM_PRINT');
				$output	= '<a href="'. JRoute::_($print_link) .'" class="editlinktip hasTip" onclick="window.open(this.href,\'win2\',\''.$status.'\'); return false;" title="'.$text.'::'.$overlib.'">'.$image.'</a>';
			}

			return $output;
		}
		return;
	}

	/**
	 * Creates the email button
	 *
	 * @param object $slug
	 * @param $view
	 * @param array $params
	 *
	 * Views:
	 * Category, Event, Venue
	 */
	static function mailbutton($slug, $view, $params)
	{
		$app = JFactory::getApplication();
		$settings = JEMHelper::config();

		if ($settings->show_email_icon) {
			JHTML::_('behavior.tooltip');
			require_once JPATH_SITE . '/components/com_mailto/helpers/mailto.php';

			$uri = JURI::getInstance();
			$base = $uri->toString(array('scheme', 'host', 'port'));
			$template = JFactory::getApplication()->getTemplate();
			$link = $base.JRoute::_('index.php?view='.$view.'&id='.$slug, false);

			$url = 'index.php?option=com_mailto&tmpl=component&template='.$template.'&link='.MailToHelper::addLink($link);
			$status = 'width=400,height=300,menubar=yes,resizable=yes';

			if ($settings->icons) {
				$image = JHTML::_('image','system/emailButton.png', JText::_('JGLOBAL_EMAIL'), NULL, true);
			} else {
				$image = JText::_('COM_JEM_EMAIL');
			}

			if ($app->input->get('print','','int')) {
				//button in popup
			} else {
				//button in view
				$overlib = JText::_('COM_JEM_EMAIL_TIP');
				$text = JText::_('COM_JEM_EMAIL');
				return '<a href="'. JRoute::_($url) .'" class="editlinktip hasTip" onclick="window.open(this.href,\'win2\',\''.$status.'\'); return false;" title="'.$text.'::'.$overlib.'">'.$image.'</a>';
			}

		}
		return;
	}

	/**
	 * Creates the ical button
	 *
	 * @param object $slug
	 * @param array $params
	 */
	static function icalbutton($slug, $view)
	{
		$app = JFactory::getApplication();
		$settings = JEMHelper::config();
		if ($settings->events_ical == 1) {
			JHTML::_('behavior.tooltip');

			if ($settings->icons) {
				$image = JHTML::image("media/com_jem/images/iCal2.0.png",JText::_('COM_JEM_EXPORT_ICS'));
			} else {
				$image = JText::_('COM_JEM_EXPORT_ICS');
			}

			if ($app->input->get('print','','int')) {
				//button in popup
				$output = '';
			} else {
				//button in view
				$overlib = JText::_('COM_JEM_ICAL_TIP');
				$text = JText::_('COM_JEM_ICAL');

				$print_link = 'index.php?view='.$view.'&id='.$slug.'&format=raw&layout=ics';
				$output	= '<a href="'. JRoute::_($print_link) .'" class="editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.'</a>';
			}

			return $output;
		}
		return;
	}

	/**
	 * Creates the publish button
	 *
	 * View:
	 * Myevents
	 */
	static function publishbutton()
	{
		$app = JFactory::getApplication();
		$settings = JEMHelper::config();

		JHTML::_('behavior.tooltip');

		$image = JHTML::image("media/com_jem/images/publish.png",JText::_('COM_JEM_PUBLISH'));

		if ($app->input->get('print','','int')) {
			//button in popup
			$output = '';
		} else {
			//button in view
			$overlib = JText::_('COM_JEM_PUBLISH_DESC');
			$text = JText::_('COM_JEM_PUBLISH');

			$print_link = "javascript:void(Joomla.submitbutton('publish'));";
			$output	= '<a href="'. JRoute::_($print_link) .'" class="editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.'</a>';
		}

		return $output;
	}

	/**
	 * Creates the trash button
	 *
	 * View:
	 * Myevents
	 */
	static function trashbutton()
	{
		$app = JFactory::getApplication();
		$settings = JEMHelper::config();

		JHTML::_('behavior.tooltip');

		// checks template image directory for image, if none found default are loaded

		$image = JHTML::image("media/com_jem/images/trash.png",JText::_('COM_JEM_TRASH'));

		if ($app->input->get('print','','int')) {
			//button in popup
			$output = '';
		} else {
			//button in view
			$overlib = JText::_('COM_JEM_TRASH_DESC');
			$text = JText::_('COM_JEM_TRASH');

			$print_link = "javascript:void(Joomla.submitbutton('trash'));";
			$output	= '<a href="'. JRoute::_($print_link) .'" class="editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.'</a>';
		}

		return $output;
	}

	/**
	 * Creates the unpublish button
	 *
	 * View:
	 * Myevents
	 */
	static function unpublishbutton()
	{
		$app = JFactory::getApplication();
		$settings = JEMHelper::config();

		JHTML::_('behavior.tooltip');

		// checks template image directory for image, if none found default are loaded

		$image = JHTML::image("media/com_jem/images/unpublish.png",JText::_('COM_JEM_UNPUBLISH'));

		if ($app->input->get('print','','int')) {
			//button in popup
			$output = '';
		} else {
			//button in view
			$overlib = JText::_('COM_JEM_UNPUBLISH_DESC');
			$text = JText::_('COM_JEM_UNPUBLISH');

			$print_link = "javascript:void(Joomla.submitbutton('unpublish'));";
			$output	= '<a href="'. JRoute::_($print_link) .'" class="editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.'</a>';
		}

		return $output;
	}

	/**
	 * Creates the export button
	 *
	 * view:
	 * attendees
	 */
	static function exportbutton($eventid)
	{
		$app = JFactory::getApplication();
		$settings = JEMHelper::config();
		
		// Emailaddress
		$jinput = JFactory::getApplication()->input;
		$enableemailaddress = $jinput->get('em','','int');
		
		if ($enableemailaddress == 1)
		{
			$emailaddress = '&em='.$enableemailaddress;
		}else
		{
			$emailaddress = '';
		}

		JHTML::_('behavior.tooltip');

		// checks template image directory for image, if none found default are loaded

		$image = JHTML::image("media/com_jem/images/export_excel.png",JText::_('COM_JEM_EXPORT'));

		if ($app->input->get('print','','int')) {
			//button in popup
			$output = '';
		} else {
			//button in view
			$overlib = JText::_('COM_JEM_EXPORT_DESC');
			$text = JText::_('COM_JEM_EXPORT');

			$print_link = 'index.php?option=com_jem&amp;view=attendees&amp;task=attendeeexport&amp;tmpl=raw&amp;id='.$eventid.$emailaddress;
			$output	= '<a href="'. JRoute::_($print_link) .'" class="editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.'</a>';
		}

		return $output;
	}

	/**
	 * Creates the back button
	 *
	 * view:
	 * attendees
	 */
	static function backbutton($backlink, $view)
	{
		$app = JFactory::getApplication();
		$settings = JEMHelper::config();

		$id = JRequest::getInt('id');
		$fid = JRequest::getInt('Itemid');

		JHTML::_('behavior.tooltip');

		// checks template image directory for image, if none found default are loaded

		$image = JHTML::image("media/com_jem/images/icon-16-back.png",JText::_('COM_JEM_BACK'));

		if ($app->input->get('print','','int')) {
			//button in popup
			$output = '';
		} else {
			//button in view
			$overlib = JText::_('COM_JEM_BACK');
			$text = JText::_('COM_JEM_BACK');

			$link = 'index.php?option=com_jem&amp;view='.$view.'&amp;task=attendees.back&id='.$id.'&Itemid='.$fid;
			$output	= '<a href="'. JRoute::_($link) .'" class="editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.'</a>';
		}

		return $output;
	}

	/**
	 * Creates the map button
	 *
	 * @param obj $data
	 */
	static function mapicon($data)
	{
		$app = JFactory::getApplication();
		$jemsettings = JEMHelper::config();

		//Link to map
		$mapimage = JHTML::image("media/com_jem/images/map_icon.png",JText::_('COM_JEM_MAP'));

		//set var
		$output = null;
		$attributes = null;

		//stop if disabled
		if (!$data->map) {
			return $output;
		}

		$data->country = JString::strtoupper($data->country);

		if ($data->latitude == 0.000000) {
			$data->latitude = null;
		}
		if ($data->longitude == 0.000000) {
			$data->longitude = null;
		}
		$url1 = 'http://maps.google.'.$jemsettings->tld.'/maps?hl='.$jemsettings->lg.'&q='.str_replace(" ", "+", $data->street).', '.$data->postalCode.' '.str_replace(" ", "+", $data->city).', '.$data->country.'+ ('.$data->venue.')&ie=UTF8&z=15&iwloc=B&output=embed" ';

		//google map link or include
		switch ($jemsettings->showmapserv)
		{
			case 1:
				// link
				$url2 = 'http://maps.google.'.$jemsettings->tld.'/maps?hl='.$jemsettings->lg.'&q=loc:'.$data->latitude.',+'.$data->longitude.'&ie=UTF8&z=15&iwloc=B&output=embed';

				$url = ($data->latitude && $data->longitude) ? $url2 : $url1;

				$message = JText::_('COM_JEM_MAP').':';
				$attributes = ' rel="{handler: \'iframe\', size: {x: 800, y: 500}}" latitude="" longitude=""';
				$output = '<dt class="venue_mapicon">'.$message.'</dt><dd class="venue_mapicon"><a class="flyermodal" title="'.JText::_('COM_JEM_MAP').'" target="_blank" href="'.$url.'"'.$attributes.'>'.$mapimage.'</a></dd>';
				break;

			case 2:
				// include
				$url2 = 'https://maps.google.com/maps?q=loc:'.$data->latitude.',+'.$data->longitude.'&amp;ie=UTF8&amp;t=m&amp;z=14&amp;iwloc=B&amp;output=embed';

				$url = ($data->latitude && $data->longitude) ? $url2 : $url1;

				$output = '<div style="border: 1px solid #000;width:500px;" color="black"><iframe width="500" height="250" src="'.$url.'" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" ></iframe></div>';
				break;
		}

		return $output;
	}

	/**
	 * Creates the flyer
	 *
	 * @param obj $data
	 * @param array $image
	 * @param string $type
	 */
	static function flyer($data, $image, $type)
	{
		$settings = JEMHelper::config();

		switch($type) {
			case 'event':
				$folder = 'events';
				$imagefile = $data->datimage;
				$info = $data->title;
				break;

			case 'category':
				$folder = 'categories';
				$imagefile = $data->image;
				$info = $data->catname;
				break;

			case 'venue':
				$folder = 'venues';
				$imagefile = $data->locimage;
				$info = $data->venue;
				break;
		}

		// Do we have an image?
		if (empty($imagefile)) {
			return;
		}

		jimport('joomla.filesystem.file');

		// Does a thumbnail exist?
		if (JFile::exists(JPATH_SITE.'/images/jem/'.$folder.'/small/'.$imagefile)) {
			if ($settings->lightbox == 0) {
				$url = '#';
				$attributes = 'class="notmodal" onclick="window.open(\''.JURI::base().'/'.$image['original'].'\',\'Popup\',\'width='.$image['width'].',height='.$image['height'].',location=no,menubar=no,scrollbars=no,status=no,toolbar=no,resizable=no\')"';
			} else {
				JHTML::_('behavior.modal', 'a.flyermodal');
				$url = JURI::base().'/'.$image['original'];
				$attributes = 'class="flyermodal" title="'.$info.'"';
			}

			$icon = '<img src="'.JURI::base().'/'.$image['thumb'].'" width="'.$image['thumbwidth'].'" height="'.$image['thumbheight'].'" alt="'.$info.'" title="'.JText::_('COM_JEM_CLICK_TO_ENLARGE').'" />';
			$output = '<div class="flyerimage"><a href="'.$url.'" '.$attributes.'>'.$icon.'</a></div>';

			// Otherwise take the values for the original image specified in the settings
		} else {
			$output = '<img class="flyermodal" src="'.JURI::base().'/'.$image['original'].'" width="'.$image['width'].'" height="'.$image['height'].'" alt="'.$info.'" />';
		}

		return $output;
	}

	/**
	 * Creates the country flag
	 *
	 * @param string $country
	 */
	static function getFlag($country)
	{
		$country = JString::strtolower($country);

		jimport('joomla.filesystem.file');

		if (JFile::exists(JPATH_BASE.'/media/com_jem/images/flags/'.$country.'.gif')) {
			$countryimg = '<img src="'.JURI::base(true).'/media/com_jem/images/flags/'.$country.'.gif" alt="'.JText::_('COM_JEM_COUNTRY').': '.$country.'" width="16" height="11" />';

			return $countryimg;
		}

		return null;
	}

	/**
	 * Formats date
	 *
	 * @param string $date
	 * @param string $format
	 * @return string $formatdate
	 */
	static function formatdate($date, $format = "")
	{
		$settings = JEMHelper::config();

		jimport('joomla.utilities.date');
		$jdate = new JDate($date);
		if (!$format) {
			// If no format set, use long format  as standard
			$format = JText::_($settings->formatdate);
		}

		return $jdate->format($format);
	}

	/**
	 * Formats time
	 *
	 * @param string $time
	 * @return string $formattime
	 */
	static function formattime($time)
	{
		$settings = JEMHelper::config();

		if(!$time) {
			return;
		}

		//Format time
		$formattime = strftime($settings->formattime, strtotime($time));
		$formattime .= ' '.$settings->timename;

		return $formattime;
	}

	/**
	 * Formats the input dates and times to be used as a from-to string for
	 * events. Takes care of unset dates and or times.
	 *
	 * @param string $dateStart Start date of event
	 * @param string $timeStart Start time of event
	 * @param string $dateEnd End date of event
	 * @param string $timeEnd End time of event
	 * @param string $format Date Format
	 * @return string Formatted date and time string to print
	 */
	static function formatDateTime($dateStart, $timeStart, $dateEnd = "", $timeEnd = "", $format = "")
	{
		$settings = JEMHelper::config();
		$output = "";

		if(JEMHelper::isValidDate($dateStart)) {
			$output .= self::formatdate($dateStart, $format);

			if($settings->showtimedetails && $timeStart) {
				$output .= ', '.self::formattime($timeStart);
			}

			// Display end date only when it differs from start date
			$displayDateEnd = JEMHelper::isValidDate($dateEnd) && $dateEnd != $dateStart;
			if($displayDateEnd) {
				$output .= ' - '.self::formatdate($dateEnd, $format);
			}

			// Display end time only when both times are set
			if($settings->showtimedetails && $timeStart && $timeEnd) {
				$output .= $displayDateEnd ? ', ' : ' - ';
				$output .= self::formattime($timeEnd);
			}
		} else {
			$output .= JText::_('COM_JEM_OPEN_DATE');

			if($settings->showtimedetails) {
				if($timeStart) {
					$output .= ', '.self::formattime($timeStart);
				}
				// Display end time only when both times are set
				if($timeStart && $timeEnd) {
					$output .= ' - '.self::formattime($timeEnd);
				}
			}
		}

		return $output;
	}

	/**
	 * Formats the input dates and times to be used as a long from-to string for
	 * events. Takes care of unset dates and or times.
	 *
	 * @param string $dateStart Start date of event
	 * @param string $timeStart Start time of event
	 * @param string $dateEnd End date of event
	 * @param string $timeEnd End time of event
	 * @return string Formatted date and time string to print
	 */
	static function formatLongDateTime($dateStart, $timeStart, $dateEnd = "", $timeEnd = "")
	{
		return self::formatDateTime($dateStart, $timeStart, $dateEnd, $timeEnd);
	}

	/**
	 * Formats the input dates and times to be used as a short from-to string for
	 * events. Takes care of unset dates and or times.
	 *
	 * @param string $dateStart Start date of event
	 * @param string $timeStart Start time of event
	 * @param string $dateEnd End date of event
	 * @param string $timeEnd End time of event
	 * @return string Formatted date and time string to print
	 */
	static function formatShortDateTime($dateStart, $timeStart, $dateEnd = "", $timeEnd = "")
	{
		$settings = JEMHelper::config();

		// Use format saved in settings if specified or format in language file otherwise
		if(isset($settings->formatShortDate) && $settings->formatShortDate) {
			$format = $settings->formatShortDate;
		} else {
			$format = JText::_('COM_JEM_FORMAT_SHORT_DATE');
		}
		return self::formatDateTime($dateStart, $timeStart, $dateEnd, $timeEnd, $format);
	}

	/**
	 * Returns an array for ical formatting
	 * @param string date
	 * @param string time
	 * @return array
	 */
	static function getIcalDateArray($date, $time = null)
	{
		if ($time) {
			$sec = strtotime($date. ' ' .$time);
		} else {
			$sec = strtotime($date);
		}
		if (!$sec) {
			return false;
		}

		//Format date
		$parsed = strftime('%Y-%m-%d %H:%M:%S', $sec);

		$date = array('year' => (int) substr($parsed, 0, 4),
				'month' => (int) substr($parsed, 5, 2),
				'day' => (int) substr($parsed, 8, 2));

		//Format time
		if (substr($parsed, 11, 8) != '00:00:00')
		{
			$date['hour'] = substr($parsed, 11, 2);
			$date['min'] = substr($parsed, 14, 2);
			$date['sec'] = substr($parsed, 17, 2);
		}
		return $date;
	}
}
?>
