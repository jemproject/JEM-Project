<?php
/**
 * @version 1.9.5
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
	 */
	static function footer()
	{
		$app = JFactory::getApplication();

		if ($app->input->get('print','','int')) {
			return;
		} else {
			echo '<font color="grey">Powered by <a href="http://www.joomlaeventmanager.net" target="_blank">JEM</a></font>';
		}
	}

	/**
	 * Writes Event submission button
	 *
	 * @param int $dellink Access of user
	 * @param array $params needed params
	 **/
	static function submitbutton($dellink, $params)
	{	
		if ($dellink)
		{
			$settings = JEMHelper::globalattribs();
			$settings2 = JEMHelper::config();
			
			$uri = JFactory::getURI();
			$app = JFactory::getApplication();

			if ($app->input->get('print','','int')) {
				return;
			}

			JHtml::_('behavior.tooltip');

			if ($settings->get('global_show_icons',1)) {
				$image = JHtml::_('image', 'com_jem/submitevent.png', JText::_('COM_JEM_DELIVER_NEW_EVENT'), NULL, true);
			} else {
				$image = JText::_('COM_JEM_DELIVER_NEW_EVENT');
			}

			$url = 'index.php?option=com_jem&task=event.add&return='.base64_encode(urlencode($uri)).'&a_id=0';
			$overlib = JText::_('COM_JEM_SUBMIT_EVENT_DESC');
			$button = JHtml::_('link', JRoute::_($url), $image);
			$output = '<span class="hasTip" title="'.JText::_('COM_JEM_DELIVER_NEW_EVENT').' :: '.$overlib.'">'.$button.'</span>';

			return $output;
		}
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
	static function addvenuebutton($addvenuelink, $params, $settings2)
	{
		if ($addvenuelink) {
			$app 		= JFactory::getApplication();
			$settings 	= JEMHelper::globalattribs();

			if ($app->input->get('print','','int')) {
				return;
			}

			JHtml::_('behavior.tooltip');

			if ($settings->get('global_show_icons',1)) {
				$image = JHtml::_('image', 'com_jem/addvenue.png', JText::_('COM_JEM_DELIVER_NEW_VENUE'), NULL, true);
			} else {
				$image = JText::_('COM_JEM_DELIVER_NEW_VENUE');
			}

			$url = 'index.php?option=com_jem&view=editvenue';
			$overlib = JText::_('COM_JEM_DELIVER_NEW_VENUE_DESC');
			$button = JHtml::_('link', JRoute::_($url), $image);
			$output = '<span class="hasTip" title="'.JText::_('COM_JEM_DELIVER_NEW_VENUE').' :: '.$overlib.'">'.$button.'</span>';

			return $output;
		}
	}

	/**
	 * Writes Archivebutton
	 *
	 * @param array $params needed params
	 * @param string $task The current task (optional)
	 * @param int $id id of category/event/venue if useful (optional)
	 *
	 * Views:
	 * Categories, Categoriesdetailed, Category, Eventslist, Search, Venue, Venues
	 */
	static function archivebutton($params, $task = NULL, $id = NULL)
	{
		$settings = JEMHelper::globalattribs();
		$settings2 = JEMHelper::config();
		$app = JFactory::getApplication();
		
		if ($settings->get('global_show_archive_icon',1)) {
			if ($app->input->get('print','','int')) {
				return;
			}

			if ($settings2->oldevent == 2) {
				JHtml::_('behavior.tooltip');
				$view = JRequest::getWord('view');

				if (empty($view)) {
					return; // there must be a view - just to be sure...
				}

				if ($task == 'archive') {
					if ($settings->get('global_show_icons',1)) {
						$image = JHtml::_('image', 'com_jem/el.png', JText::_('COM_JEM_SHOW_EVENTS'), NULL, true);
					} else {
						$image = JText::_('COM_JEM_SHOW_EVENTS');
					}

					$overlib = JText::_('COM_JEM_SHOW_EVENTS_DESC');
					$title = JText::_('COM_JEM_SHOW_EVENTS');

					if ($id) {
						$url = 'index.php?option=com_jem&view='.$view.'&id='.$id;
					} else {
						$url = 'index.php';
					}
				} else {
					if ($settings->get('global_show_icons',1)) {
						$image = JHtml::_('image', 'com_jem/archive_front.png', JText::_('COM_JEM_SHOW_ARCHIVE'), NULL, true);
					} else {
						$image = JText::_('COM_JEM_SHOW_ARCHIVE');
					}

					$overlib = JText::_('COM_JEM_SHOW_ARCHIVE_DESC');
					$title = JText::_('COM_JEM_SHOW_ARCHIVE');

					if ($id) {
						$url = 'index.php?option=com_jem&view='.$view.'&id='.$id.'&task=archive';
					} else {
						$url = 'index.php?option=com_jem&view='.$view.'&task=archive';
					}
				}

				$button = JHtml::_('link', JRoute::_($url), $image);
				$output = '<span class="hasTip" title="'.$title.' :: '.$overlib.'">'.$button.'</span>';

				return $output;
			}
		}
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
	static function editbutton($item, $params, $attribs, $allowedtoedit, $view)
	{
		if ($allowedtoedit) {
			$app = JFactory::getApplication();

			if ($app->input->get('print','','int')) {
				return;
			}
			
			// Ignore if the state is negative (trashed).
			if ($item->published < 0) {
				return;
			}

			// Initialise variables.
			$user	= JFactory::getUser();
			$app = JFactory::getApplication();
			$userId	= $user->get('id');
			$uri	= JFactory::getURI();

			$settings = JEMHelper::globalattribs();
			JHtml::_('behavior.tooltip');

			switch ($view)
			{
				case 'editevent':
					if ($settings->get('global_show_icons',1)) {
						$image = JHtml::_('image', 'com_jem/calendar_edit.png', JText::_('COM_JEM_EDIT_EVENT'), NULL, true);
					} else {
						$image = JText::_('COM_JEM_EDIT_EVENT');
					}
					$id = $item->did;
					$overlib = JText::_('COM_JEM_EDIT_EVENT_DESC');
					$text = JText::_('COM_JEM_EDIT_EVENT');
					$url = 'index.php?option=com_jem&task=event.edit&a_id='.$id.'&return='.base64_encode(urlencode($uri));
					break;

				case 'editvenue':
					if ($settings->get('global_show_icons',1)) {
						$image = JHtml::_('image', 'com_jem/calendar_edit.png', JText::_('COM_JEM_EDIT_VENUE'), NULL, true);
					} else {
						$image = JText::_('COM_JEM_EDIT_VENUE');
					}
					$id = $item->locid;
					$overlib = JText::_('COM_JEM_EDIT_VENUE_DESC');
					$text = JText::_('COM_JEM_EDIT_VENUE');
					$url = 'index.php?option=com_jem&view='.$view.'&id='.$id;
					break;
					
				case 'venue':
					if ($settings->get('global_show_icons',1)) {
						$image = JHtml::_('image', 'com_jem/calendar_edit.png', JText::_('COM_JEM_EDIT_VENUE'), NULL, true);
					} else {
						$image = JText::_('COM_JEM_EDIT_VENUE');
					}
					$id = $item->id;
					$overlib = JText::_('COM_JEM_EDIT_VENUE_DESC');
					$text = JText::_('COM_JEM_EDIT_VENUE');
					$url = 'index.php?option=com_jem&view=editvenue&id='.$id;
					break;
			}
			
			if (!url) {
				return; // we need at least url to generate useful output
			}
		
			$button = JHtml::_('link', JRoute::_($url), $image);
			$output = '<span class="hasTip" title="'.$text.' :: '.$overlib.'">'.$button.'</span>';

			return $output;
		}
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
		$settings = JEMHelper::globalattribs();

		if ($settings->get('global_show_print_icon',0)) {
			JHtml::_('behavior.tooltip');

			$status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=640,height=480,directories=no,location=no';

			if ($settings->get('global_show_icons',1)) {
				$image = JHtml::_('image','system/printButton.png', JText::_('JGLOBAL_PRINT'), NULL, true);
			} else {
				$image = JText::_('COM_JEM_PRINT');
			}

			if ($app->input->get('print','','int')) {
				//button in popup
				$overlib = JText::_('COM_JEM_PRINT_DESC');
				$text = JText::_('COM_JEM_PRINT');
				$title = 'title='.JText::_('JGLOBAL_PRINT');
				$pimage = JHtml::_('image','system/printButton.png', JText::_('JGLOBAL_PRINT'), $title, true);
				$output = '<a href="#" onclick="window.print();return false;">'.$pimage.'</a>';
			} else {
				//button in view
				$overlib = JText::_('COM_JEM_PRINT_DESC');
				$text = JText::_('COM_JEM_PRINT');
				$output	= '<a href="'. JRoute::_($print_link) .'" class="hasTip" onclick="window.open(this.href,\'win2\',\''.$status.'\'); return false;" title="'.$text.'::'.$overlib.'">'.$image.'</a>';
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
		$settings = JEMHelper::globalattribs();

		if ($settings->get('global_show_email_icon')) {
			if ($app->input->get('print','','int')) {
				return;
			}

			JHtml::_('behavior.tooltip');
			require_once JPATH_SITE . '/components/com_mailto/helpers/mailto.php';

			$uri = JURI::getInstance();
			$base = $uri->toString(array('scheme', 'host', 'port'));
			$template = JFactory::getApplication()->getTemplate();
			$link = $base.JRoute::_('index.php?option=com_jem&view='.$view.'&id='.$slug, false);

			$url = 'index.php?option=com_mailto&tmpl=component&template='.$template.'&link='.MailToHelper::addLink($link);
			$status = 'width=400,height=350,menubar=yes,resizable=yes';

			if ($settings->get('global_show_icons')) {
				$image = JHtml::_('image','system/emailButton.png', JText::_('JGLOBAL_EMAIL'), NULL, true);
			} else {
				$image = JText::_('COM_JEM_EMAIL');
			}

			$overlib = JText::_('COM_JEM_EMAIL_DESC');
			$text = JText::_('COM_JEM_EMAIL');

			$output = '<a href="'. JRoute::_($url) .'" class="hasTip" onclick="window.open(this.href,\'win2\',\''.$status.'\'); return false;" title="'.$text.'::'.$overlib.'">'.$image.'</a>';
			return $output;
		}
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
		$settings = JEMHelper::globalattribs();
		
		if ($settings->get('global_show_ical_icon','0')==1) {
			if ($app->input->get('print','','int')) {
				return;
			}
			
			JHtml::_('behavior.tooltip');

			if ($settings->get('global_show_icons','0')==1) {
				$image = JHtml::_('image', 'com_jem/iCal2.0.png', JText::_('COM_JEM_EXPORT_ICS'), NULL, true);
			} else {
				$image = JText::_('COM_JEM_EXPORT_ICS');
			}

			$overlib = JText::_('COM_JEM_ICAL_DESC');
			$text = JText::_('COM_JEM_ICAL');

			$url = 'index.php?option=com_jem&view='.$view.'&id='.$slug.'&format=raw&layout=ics';
			$button = JHtml::_('link', JRoute::_($url), $image);
			$output = '<span class="hasTip" title="'.$text.' :: '.$overlib.'">'.$button.'</span>';

			return $output;
		}
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

		JHtml::_('behavior.tooltip');

		$image = JHtml::_('image', 'com_jem/publish.png', JText::_('COM_JEM_PUBLISH'), NULL, true);

		if ($app->input->get('print','','int')) {
			//button in popup
			$output = '';
		} else {
			//button in view
			$overlib = JText::_('COM_JEM_PUBLISH_DESC');
			$text = JText::_('COM_JEM_PUBLISH');

			$print_link = "javascript:void(Joomla.submitbutton('myevents.publish'));";
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

		JHtml::_('behavior.tooltip');

		$image = JHtml::_('image', 'com_jem/trash.png', JText::_('COM_JEM_TRASH'), NULL, true);

		if ($app->input->get('print','','int')) {
			//button in popup
			$output = '';
		} else {
			//button in view
			$overlib = JText::_('COM_JEM_TRASH_DESC');
			$text = JText::_('COM_JEM_TRASH');

			$print_link = "javascript:void(Joomla.submitbutton('myevents.trash'));";
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

		JHtml::_('behavior.tooltip');

		$image = JHtml::_('image', 'com_jem/unpublish.png', JText::_('COM_JEM_UNPUBLISH'), NULL, true);

		if ($app->input->get('print','','int')) {
			//button in popup
			$output = '';
		} else {
			//button in view
			$overlib = JText::_('COM_JEM_UNPUBLISH_DESC');
			$text = JText::_('COM_JEM_UNPUBLISH');

			$print_link = "javascript:void(Joomla.submitbutton('myevents.unpublish'));";
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

		// Emailaddress
		$jinput = JFactory::getApplication()->input;
		$enableemailaddress = $jinput->get('em','','int');

		if ($enableemailaddress == 1) {
			$emailaddress = '&em='.$enableemailaddress;
		} else {
			$emailaddress = '';
		}

		JHtml::_('behavior.tooltip');

		$image = JHtml::_('image', 'com_jem/export_excel.png', JText::_('COM_JEM_EXPORT'), NULL, true);

		if ($app->input->get('print','','int')) {
			//button in popup
			$output = '';
		} else {
			//button in view
			$overlib = JText::_('COM_JEM_EXPORT_DESC');
			$text = JText::_('COM_JEM_EXPORT');

			$print_link = 'index.php?option=com_jem&amp;view=attendees&amp;task=attendees.export&amp;tmpl=raw&amp;id='.$eventid.$emailaddress;
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

		$id = JRequest::getInt('id');
		$fid = JRequest::getInt('Itemid');

		JHtml::_('behavior.tooltip');

		$image = JHtml::_('image', 'com_jem/icon-16-back.png', JText::_('COM_JEM_BACK'), NULL, true);

		if ($app->input->get('print','','int')) {
			//button in popup
			$output = '';
		} else {
			//button in view
			$overlib = JText::_('COM_JEM_BACK');
			$text = JText::_('COM_JEM_BACK');

			$link = 'index.php?option=com_jem&amp;view='.$view.'&id='.$id.'&Itemid='.$fid.'&amp;task=attendees.back';
			$output	= '<a href="'. JRoute::_($link) .'" class="editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.'</a>';
		}

		return $output;
	}

	/**
	 * Creates the map button
	 *
	 * @param obj $data
	 */
	static function mapicon($data,$view=false)
	{
		$settings = JEMHelper::globalattribs();

		//stop if disabled
		if (!$data->map) {
			return;
		}
		
		if ($view == 'event')
		{
			$tld		= 'event_tld';
			$lg			= 'event_lg';
			$mapserv	= 'event_show_mapserv';
		} else {
			$tld		= 'global_tld';
			$lg			= 'global_lg';
			$mapserv	= 'global_show_mapserv';
		}
		
		//Link to map
		$mapimage = JHtml::_('image', 'com_jem/map_icon.png', JText::_('COM_JEM_MAP'), NULL, true);

		//set var
		$output = null;
		$attributes = null;

		$data->country = JString::strtoupper($data->country);

		if ($data->latitude == 0.000000) {
			$data->latitude = null;
		}
		if ($data->longitude == 0.000000) {
			$data->longitude = null;
		}

		$url1 = 'http://maps.google.'.$settings->get($tld).'/maps?hl='.$settings->get($lg).'&q='.str_replace(" ", "+", $data->street).', '.$data->postalCode.' '.str_replace(" ", "+", $data->city).', '.$data->country.'+ ('.mb_ereg_replace("&", "+", $data->venue).')&ie=UTF8&z=15&iwloc=B&output=embed" ';

		//google map link or include
		switch ($settings->get($mapserv))
		{
			case 1:
				// link
				$url2 = 'http://maps.google.'.$settings->get($tld).'/maps?hl='.$settings->get($lg).'&q=loc:'.$data->latitude.',+'.$data->longitude.'&ie=UTF8&z=15&iwloc=B&output=embed';

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
				JHtml::_('behavior.modal', 'a.flyermodal');
				$url = JURI::base().'/'.$image['original'];
				$attributes = 'class="flyermodal" title="'.$info.'"';
			}

			$icon = '<img src="'.JURI::base().'/'.$image['thumb'].'" width="'.$image['thumbwidth'].'" height="'.$image['thumbheight'].'" alt="'.$info.'" title="'.JText::_('COM_JEM_CLICK_TO_ENLARGE').'" />';
			$output = '<div class="flyerimage"><a href="'.$url.'" '.$attributes.'>'.$icon.'</a></div>';

			// Otherwise take the values for the original image specified in the settings
		} else {
			$output = '<img class="notmodal" src="'.JURI::base().'/'.$image['original'].'" width="'.$image['width'].'" height="'.$image['height'].'" alt="'.$info.'" />';
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

		$check = JEMHelper::isValidDate($date);

		if ($check == true) {
			jimport('joomla.utilities.date');
			$jdate = new JDate($date);
			if (!$format) {
				// If no format set, use long format as standard
				$format = JText::_($settings->formatdate);
			}

			return $jdate->format($format);
		} else {
			return false;
		}

	}

	/**
	 * Formats time
	 *
	 * @param string $time
	 * @return string $formattime
	 */
	static function formattime($time, $format = "", $addSuffix = true)
	{
		$settings = JEMHelper::config();
		
		$check = JEMHelper::isValidTime($time);
		
		if (!$check)
		{
			return;
		}
		
		if(!$format) {
			// If no format set, use settings format as standard
			$format = $settings->formattime;
		}

		$formattedTime = strftime($format, strtotime($time));

		if($addSuffix) {
			$formattedTime .= ' '.$settings->timename;
		}

		return $formattedTime;
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
		$settings = JEMHelper::globalattribs();
		$output = "";

		if(JEMHelper::isValidDate($dateStart)) {
			$output .= self::formatdate($dateStart, $format);

			if($settings->get('global_show_timedetails','1') && JEMHelper::isValidTime($timeStart)) {
				$output .= ', '.self::formattime($timeStart);
			}

			// Display end date only when it differs from start date
			$displayDateEnd = JEMHelper::isValidDate($dateEnd) && $dateEnd != $dateStart;
			if($displayDateEnd) {
				$output .= ' - '.self::formatdate($dateEnd, $format);
			}

			// Display end time only when both times are set
			if($settings->get('global_show_timedetails','1') && JEMHelper::isValidTime($timeStart) && JEMHelper::isValidTime($timeEnd)) 
			{				
				$output .= $displayDateEnd ? ', ' : ' - ';
				$output .= self::formattime($timeEnd);
			}
		} else {
			$output .= JText::_('COM_JEM_OPEN_DATE');

			if($settings->get('global_show_timedetails','1')) {
				if(JEMHelper::isValidTime($timeStart)) {
					$output .= ', '.self::formattime($timeStart);
				}
				// Display end time only when both times are set
				if(JEMHelper::isValidTime($timeStart) && JEMHelper::isValidTime($timeEnd)) {
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

	static function formatSchemaOrgDateTime($dateStart, $timeStart, $dateEnd = "", $timeEnd = "") {
		$settings = JEMHelper::globalattribs();
		$output = "";
		$formatD = "Y-m-d";
		$formatT = "%H:%M";

		if(JEMHelper::isValidDate($dateStart)) {
			$content = self::formatdate($dateStart, $formatD);

			if($settings->get('global_show_timedetails','1') && $timeStart) {
				$content .= 'T'.self::formattime($timeStart, $formatT, false);
			}
			$output .= '<meta itemprop="startDate" content="'.$content.'" />';

			if(JEMHelper::isValidDate($dateEnd)) {
				$content = self::formatdate($dateEnd, $formatD);

				if($settings->get('global_show_timedetails','1') && $timeEnd) {
					$content .= 'T'.self::formattime($timeEnd, $formatT, false);
				}
				$output .= '<meta itemprop="endDate" content="'.$content.'" />';
			}
		} else {
			// Open date

			if($settings->get('global_show_timedetails','1')) {
				if($timeStart) {
					$content = self::formattime($timeStart, $formatT, false);
					$output .= '<meta itemprop="startDate" content="'.$content.'" />';
				}
				// Display end time only when both times are set
				if($timeStart && $timeEnd) {
					$content .= self::formattime($timeEnd, $formatT, false);
					$output .= '<meta itemprop="endDate" content="'.$content.'" />';
				}
			}
		}
		return $output;
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

	/**
	 * Get a category names list
	 * @param unknown $categories Category List
	 * @param boolean $doLink Link the categories to the respective Category View
	 * @return string|multitype:
	 */
	static function getCategoryList($categories, $doLink) {
		$output = array_map(
			function ($category) use ($doLink) {
				if ($doLink) {
					$value = '<a href="'.JRoute::_(JEMHelperRoute::getCategoryRoute($category->catslug)).'">'.
						$category->catname.'</a>';
				} else {
					$value = $category->catname;
				}
				return $value;
			},
			$categories);

		return $output;
	}
}
?>
