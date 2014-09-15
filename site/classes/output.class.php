<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

/**
 * Holds the logic for all output related things
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
			$settings 	= JemHelper::globalattribs();
			$settings2	= JemHelper::config();

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
			$settings 	= JemHelper::globalattribs();
			$uri 		= JFactory::getURI();

			if ($app->input->get('print','','int')) {
				return;
			}

			JHtml::_('behavior.tooltip');

			if ($settings->get('global_show_icons',1)) {
				$image = JHtml::_('image', 'com_jem/addvenue.png', JText::_('COM_JEM_DELIVER_NEW_VENUE'), NULL, true);
			} else {
				$image = JText::_('COM_JEM_DELIVER_NEW_VENUE');
			}

			$url = 'index.php?option=com_jem&task=venue.add&return='.base64_encode(urlencode($uri)).'&a_id=0';
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
		$settings	= JemHelper::globalattribs();
		$settings2	= JemHelper::config();
		$app		= JFactory::getApplication();

		if ($settings->get('global_show_archive_icon',1)) {
			if ($app->input->get('print','','int')) {
				return;
			}

			if ($settings2->oldevent == 2) {
				JHtml::_('behavior.tooltip');

				$view = $app->input->getWord('view');

				if (empty($view)) {
					return; // there must be a view - just to be sure...
				}

				if ($task == 'archive') {
					if ($settings->get('global_show_icons',1)) {
						$image = JHtml::_('image', 'com_jem/el.png', JText::_('COM_JEM_SHOW_EVENTS'), NULL, true);
					} else {
						$image = JText::_('COM_JEM_SHOW_EVENTS');
					}

					// TODO: Title and overlib just fit to events view
					$overlib = JText::_('COM_JEM_SHOW_EVENTS_DESC');
					$title = JText::_('COM_JEM_SHOW_EVENTS');

					if ($id) {
						$url = 'index.php?option=com_jem&view='.$view.'&id='.$id;
					} else {
						$url = 'index.php?option=com_jem&view='.$view;
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
			$user   = JFactory::getUser();
			$app    = JFactory::getApplication();
			$userId = $user->get('id');
			$uri    = JFactory::getURI();

			$settings = JemHelper::globalattribs();
			JHtml::_('behavior.tooltip');

			// On Joomla Edit icon is always used regardless if "Show icons" is set to Yes or No.
			$showIcon = 1; //$settings->get('global_show_icons', 1);

			switch ($view)
			{
				case 'editevent':
					if (property_exists($item, 'checked_out') && property_exists($item, 'checked_out_time') && $item->checked_out > 0 && $item->checked_out != $userId) {
						$checkoutUser = JFactory::getUser($item->checked_out);
						$button = JHtml::_('image', 'system/checked_out.png', NULL, NULL, true);
						$date = JHtml::_('date', $item->checked_out_time);
						$tooltip = JText::_('JLIB_HTML_CHECKED_OUT').' :: '.JText::sprintf('COM_JEM_GLOBAL_CHECKED_OUT_BY', $checkoutUser->name).' <br /> '.$date;
						return '<span class="hasTip" title="'.htmlspecialchars($tooltip, ENT_COMPAT, 'UTF-8').'">'.$button.'</span>';
					}

					if ($showIcon) {
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
					if (property_exists($item, 'vChecked_out') && property_exists($item, 'vChecked_out_time') && $item->vChecked_out > 0 && $item->vChecked_out != $userId) {
						$checkoutUser = JFactory::getUser($item->vChecked_out);
						$button = JHtml::_('image', 'system/checked_out.png', NULL, NULL, true);
						$date = JHtml::_('date', $item->vChecked_out_time);
						$tooltip = JText::_('JLIB_HTML_CHECKED_OUT').' :: '.JText::sprintf('COM_JEM_GLOBAL_CHECKED_OUT_BY', $checkoutUser->name).' <br /> '.$date;
						return '<span class="hasTip" title="'.htmlspecialchars($tooltip, ENT_COMPAT, 'UTF-8').'">'.$button.'</span>';
					}

					if ($showIcon) {
						$image = JHtml::_('image', 'com_jem/calendar_edit.png', JText::_('COM_JEM_EDIT_VENUE'), NULL, true);
					} else {
						$image = JText::_('COM_JEM_EDIT_VENUE');
					}
					$id = $item->locid;
					$overlib = JText::_('COM_JEM_EDIT_VENUE_DESC');
					$text = JText::_('COM_JEM_EDIT_VENUE');
					$url = 'index.php?option=com_jem&task=venue.edit&a_id='.$id.'&return='.base64_encode(urlencode($uri));
					break;

				case 'venue':
					if (property_exists($item, 'vChecked_out') && property_exists($item, 'vChecked_out_time') && $item->vChecked_out > 0 && $item->vChecked_out != $userId) {
						$checkoutUser = JFactory::getUser($item->vChecked_out);
						$button = JHtml::_('image', 'system/checked_out.png', NULL, NULL, true);
						$date = JHtml::_('date', $item->vChecked_out_time);
						$tooltip = JText::_('JLIB_HTML_CHECKED_OUT').' :: '.JText::sprintf('COM_JEM_GLOBAL_CHECKED_OUT_BY', $checkoutUser->name).' <br /> '.$date;
						return '<span class="hasTip" title="'.htmlspecialchars($tooltip, ENT_COMPAT, 'UTF-8').'">'.$button.'</span>';
					}

					if ($showIcon) {
						$image = JHtml::_('image', 'com_jem/calendar_edit.png', JText::_('COM_JEM_EDIT_VENUE'), NULL, true);
					} else {
						$image = JText::_('COM_JEM_EDIT_VENUE');
					}
					$id = $item->id;
					$overlib = JText::_('COM_JEM_EDIT_VENUE_DESC');
					$text = JText::_('COM_JEM_EDIT_VENUE');
					$url = 'index.php?option=com_jem&task=venue.edit&a_id='.$id.'&return='.base64_encode(urlencode($uri));
					break;
			}

			if (!$url) {
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
		$app 		= JFactory::getApplication();
		$settings	= JemHelper::globalattribs();

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
		$app 		= JFactory::getApplication();
		$settings	= JemHelper::globalattribs();

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
		$settings = JemHelper::globalattribs();

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

		JHtml::_('behavior.tooltip');

		$image = JHtml::_('image', 'com_jem/export_excel.png', JText::_('COM_JEM_EXPORT'), NULL, true);

		if ($app->input->get('print','','int')) {
			//button in popup
			$output = '';
		} else {
			//button in view
			$overlib = JText::_('COM_JEM_EXPORT_DESC');
			$text = JText::_('COM_JEM_EXPORT');

			$print_link = 'index.php?option=com_jem&view=attendees&task=attendees.export&tmpl=raw&id='.$eventid;
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
		$app 	= JFactory::getApplication();
		$jinput = $app->input;

		$id 	= $jinput->getInt('id');
		$fid 	= $jinput->getInt('Itemid');

		JHtml::_('behavior.tooltip');

		$image = JHtml::_('image', 'com_jem/icon-16-back.png', JText::_('COM_JEM_BACK'), NULL, true);

		if ($jinput->get('print','','int')) {
			//button in popup
			$output = '';
		} else {
			//button in view
			$overlib = JText::_('COM_JEM_BACK');
			$text = JText::_('COM_JEM_BACK');

			$link = 'index.php?option=com_jem&view='.$view.'&id='.$id.'&Itemid='.$fid.'&task=attendees.back';
			$output	= '<a href="'. JRoute::_($link) .'" class="editlinktip hasTip" title="'.$text.'::'.$overlib.'">'.$image.'</a>';
		}

		return $output;
	}

	/**
	 * Creates the map button
	 *
	 * @param obj $data
	 */
	static function mapicon($data, $view = false, $params)
	{
		$global = JemHelper::globalattribs();

		//stop if disabled
		if (!$data->map) {
			return;
		}

		if ($view == 'event') {
			$tld		= 'event_tld';
			$lg			= 'event_lg';
			$mapserv	= $params->get('event_show_mapserv');
		} else if ($view == 'venues') {
			$tld		= 'global_tld';
			$lg			= 'global_lg';
			$mapserv	= $params->get('global_show_mapserv');
			if ($mapserv == 3) {
				$mapserv = 0;
			}
		} else {
			$tld		= 'global_tld';
			$lg			= 'global_lg';
			$mapserv	= $params->get('global_show_mapserv');
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

		$url = 'http://maps.google.'.$params->get($tld,'com').'/maps?hl='.$params->get($lg,'com').'&q='.urlencode($data->street.', '.$data->postalCode.' '.$data->city.', '.$data->country.'+ ('.$data->venue.')').'&ie=UTF8&z=15&iwloc=B&output=embed" ';

		// google map link or include
		switch ($mapserv)
		{
			case 1:
				// link
				if($data->latitude && $data->longitude) {
					$url = 'http://maps.google.'.$params->get($tld).'/maps?hl='.$params->get($lg).'&q=loc:'.$data->latitude.',+'.$data->longitude.'&ie=UTF8&z=15&iwloc=B&output=embed';
				}

				$message = JText::_('COM_JEM_MAP').':';
				$attributes = ' rel="{handler: \'iframe\', size: {x: 800, y: 500}}" latitude="" longitude=""';
				$output = '<dt class="venue_mapicon">'.$message.'</dt><dd class="venue_mapicon"><a class="flyermodal mapicon" title="'.JText::_('COM_JEM_MAP').'" target="_blank" href="'.$url.'"'.$attributes.'>'.$mapimage.'</a></dd>';
				break;

			case 2:
				// include iframe
				if($data->latitude && $data->longitude) {
					$url = 'https://maps.google.com/maps?q=loc:'.$data->latitude.',+'.$data->longitude.'&amp;ie=UTF8&amp;t=m&amp;z=14&amp;iwloc=B&amp;output=embed';
				}

				$output = '<div style="border: 1px solid #000;width:500px;"><iframe width="500" height="250" src="'.$url.'" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" ></iframe></div>';
				break;

			case 3:
				// include - Google API3
				# https://developers.google.com/maps/documentation/javascript/tutorial
				$api		= trim($params->get('global_googleapi'));
				$clientid	= trim($params->get('global_googleclientid'));

				$document 	= JFactory::getDocument();

				# do we have a client-ID?
				if ($clientid) {
					$document->addScript('http://maps.googleapis.com/maps/api/js?client='.$clientid.'&sensor=false&v=3.15');
				} else {
					# do we have an api-key?
					if ($api) {
						$document->addScript('https://maps.googleapis.com/maps/api/js?key='.$api.'&sensor=false');
					} else {
						$document->addScript('https://maps.googleapis.com/maps/api/js?sensor=false');
					}
				}

				JemHelper::loadCss('googlemap');
				JHtml::_('script', 'com_jem/infobox.js', false, true);
				JHtml::_('script', 'com_jem/googlemap.js', false, true);

				$output = '<div id="map-canvas" class="map_canvas"/></div>';
				break;
		}

		return $output;
	}

	/**
	 * Creates the recurrence icon
	 *
	 * @param obj  $event
	 * @param bool $showinline Add css class to scale icon to fit text height
	 * @param bool $showtitle  Add title (tooltip)
	 */
	static function recurrenceicon($event, $showinline = true, $showtitle = true)
	{
		$settings = JemHelper::globalattribs();
		$item = empty($event->recurr_bak) ? $event : $event->recurr_bak;

		//stop if disabled
		if (empty($item->recurrence_number) && empty($item->recurrence_type)) {
			return;
		}

		$first = !empty($item->recurrence_type) && empty($item->recurrence_first_id);
		$image = $first ? 'com_jem/icon-32-recurrence-first.png' : 'com_jem/icon-32-recurrence.png';
		$attr_class = $showinline ? ('class="icon-inline" ') : '';
		$attr_title = $showtitle  ? ('title="' . JText::_($first ? 'COM_JEM_RECURRING_FIRST_EVENT_DESC' : 'COM_JEM_RECURRING_EVENT_DESC') . '"') : '';
		$output = JHtml::_('image', $image, JText::_('COM_JEM_RECURRING_EVENT'), $attr_class . $attr_title, true);

		return $output;
	}

	/**
	 * Creates the flyer
	 *
	 * @param obj $data
	 * @param array $image
	 * @param string $type
	 */
	static function flyer($data, $image, $type, $id = null)
	{
		$id_attr = $id ? 'id="'.$id.'"' : '';

		$settings = JemHelper::config();

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
				//$url = '#';  // Hoffi, 2014-06-07: '#' doesn't work, it opend "Add event" page - don't use <a, onclick works fine with <img :-)
				$attributes = $id_attr.' class="flyerimage" onclick="window.open(\''.JURI::base().'/'.$image['original'].'\',\'Popup\',\'width='.$image['width'].',height='.$image['height'].',location=no,menubar=no,scrollbars=no,status=no,toolbar=no,resizable=no\')"';

				$icon = '<img '.$attributes.' src="'.JURI::base().'/'.$image['thumb'].'" width="'.$image['thumbwidth'].'" height="'.$image['thumbheight'].'" alt="'.$info.'" title="'.JText::_('COM_JEM_CLICK_TO_ENLARGE').'" />';
				$output = '<div class="flyerimage">'.$icon.'</div>';
			} else {
				JHtml::_('behavior.modal', 'a.flyermodal');
				$url = JURI::base().'/'.$image['original'];
				$attributes = $id_attr.' class="flyermodal flyerimage" title="'.$info.'"';

				$icon = '<img src="'.JURI::base().'/'.$image['thumb'].'" width="'.$image['thumbwidth'].'" height="'.$image['thumbheight'].'" alt="'.$info.'" title="'.JText::_('COM_JEM_CLICK_TO_ENLARGE').'" />';
				$output = '<div class="flyerimage"><a href="'.$url.'" '.$attributes.'>'.$icon.'</a></div>';
			}
		// Otherwise take the values for the original image specified in the settings
		} else {
			$output = '<img '.$id_attr.' class="notmodal" src="'.JURI::base().'/'.$image['original'].'" width="'.$image['width'].'" height="'.$image['height'].'" alt="'.$info.'" />';
		}

		return $output;
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
		$settings 	= JemHelper::config();
		$check 		= JemHelper::isValidDate($date);
		//$timezone	= JemHelper::getTimeZoneName();
		$timezone	= null;

		if ($check == true) {
			$jdate = new JDate($date,$timezone);
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
		$settings	= JemHelper::config();
		$check 		= JemHelper::isValidTime($time);

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
	 * @param string $dateFormat Date Format
	 * @param string $timeFormat Time Format
	 * @param bool   $addSuffix if true add suffix specified in settings
	 * @return string Formatted date and time string to print
	 */
	static function formatDateTime($dateStart, $timeStart, $dateEnd = "", $timeEnd = "", $dateFormat = "", $timeFormat = "", $addSuffix = true)
	{
		$settings = JemHelper::globalattribs();
		$output = "";

		if (JemHelper::isValidDate($dateStart)) {
			$output .= self::formatdate($dateStart, $dateFormat);

			if ($settings->get('global_show_timedetails','1') && JemHelper::isValidTime($timeStart)) {
				$output .= ', '.self::formattime($timeStart, $timeFormat, $addSuffix);
			}

			// Display end date only when it differs from start date
			$displayDateEnd = JemHelper::isValidDate($dateEnd) && $dateEnd != $dateStart;
			if ($displayDateEnd) {
				$output .= ' - '.self::formatdate($dateEnd, $dateFormat);
			}

			// Display end time only when both times are set
			if ($settings->get('global_show_timedetails','1') && JemHelper::isValidTime($timeStart) && JemHelper::isValidTime($timeEnd))
			{
				$output .= $displayDateEnd ? ', ' : ' - ';
				$output .= self::formattime($timeEnd, $timeFormat, $addSuffix);
			}
		} else {
			$output .= JText::_('COM_JEM_OPEN_DATE');

			if ($settings->get('global_show_timedetails','1')) {
				if (JemHelper::isValidTime($timeStart)) {
					$output .= ', '.self::formattime($timeStart, $timeFormat, $addSuffix);

					// Display end time only when both times are set
					if (JemHelper::isValidTime($timeEnd)) {
						$output .= ' - '.self::formattime($timeEnd, $timeFormat, $addSuffix);
					}
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
		$settings = JemHelper::config();

		// Use format saved in settings if specified or format in language file otherwise
		if(isset($settings->formatShortDate) && $settings->formatShortDate) {
			$format = $settings->formatShortDate;
		} else {
			$format = JText::_('COM_JEM_FORMAT_SHORT_DATE');
		}
		return self::formatDateTime($dateStart, $timeStart, $dateEnd, $timeEnd, $format);
	}

	static function formatSchemaOrgDateTime($dateStart, $timeStart, $dateEnd = "", $timeEnd = "") {
		$settings = JemHelper::globalattribs();
		$output = "";
		$formatD = "Y-m-d";
		$formatT = "%H:%M";

		if(JemHelper::isValidDate($dateStart)) {
			$content = self::formatdate($dateStart, $formatD);

			if($settings->get('global_show_timedetails','1') && $timeStart) {
				$content .= 'T'.self::formattime($timeStart, $formatT, false);
			}
			$output .= '<meta itemprop="startDate" content="'.$content.'" />';

			if(JemHelper::isValidDate($dateEnd)) {
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
	 * @todo alter, where is this used for?
	 *
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
	 * @param boolean $backend Used for backend (true) or frontend (false, default)
	 * @return string|multitype:
	 */
	static function getCategoryList($categories, $doLink, $backend = false) {
		$output = array_map(
			function ($category) use ($doLink, $backend) {
				if ($doLink) {
					if ($backend) {
						$path = $category->path;
						$path = str_replace('/', ' &#187; ', $path);
						$value  = '<span class="editlinktip hasTip" title="'.JText::_( 'COM_JEM_EDIT_CATEGORY' ).'::'.$path.'">';
						$value .= '<a href="index.php?option=com_jem&amp;task=category.edit&amp;id='. $category->id.'">'.
						              $category->catname.'</a>';
						$value .= '</span>';
					} else {
						$value  = '<a href="'.JRoute::_(JemHelperRoute::getCategoryRoute($category->catslug)).'">'.
						              $category->catname.'</a>';
					}
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
