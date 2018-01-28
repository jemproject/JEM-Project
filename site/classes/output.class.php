<?php
/**
 * @version 2.2.3
 * @package JEM
 * @copyright (C) 2013-2018 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

// ensure JemFactory is loaded (because this class is used by modules or plugins too)
require_once(JPATH_SITE.'/components/com_jem/factory.php');

/**
 * Holds the logic for all output related things
 */
class JemOutput
{
	/**
	 * Writes footer.
	 */
	static public function footer()
	{
		$app = JFactory::getApplication();

		if ($app->input->get('print','','int')) {
			return;
		} else {
			echo '<font color="grey">Powered by <a href="http://www.joomlaeventmanager.net" target="_blank">JEM</a></font>';
		}
	}

	/**
	 * Creates the button bar shown on frontend view's top right corner.
	 *
	 * @param  string $view        Name of the view
	 *                             ('attendees', 'calendar', 'categories', 'category', 'category-cal', 'day',
	 *                              'editevent', 'editvenue', 'event', 'eventslist', 'myattendances', 'myevents', 'myvenues',
	 *                              'search', 'venue', 'venue-cal', 'venues', 'weekcal')
	 * @param  object $permissions Object holding relevant permissions
	 *                             (canAddEvent, canAddVenue, canPublishEvent, canPublishVenue)
	 * @param  object $params      Object containing other relevant parameters
	 *                             (id: for '&id=', for Archive and Export button,
	 *                              slug: for '&id=', for Mail and iCal button,
	 *                              task: e.g. 'archive', for Archive button,
	 *                              print_link: for Print button
	 *                              show, hide: to override button visibility; array of one or more of
	 *                              'addEvent', 'addVenue', 'addUsers'
	 *                              'archive' 'mail', 'print', 'ical', ('export', 'back',)
	 *                              'publish', 'unpublish', 'trash' - note: some buttons may not work or need additional changes)
	 *
	 * @return string              Resulting HTML code.
	 */
	static public function createButtonBar($view, $permissions, $params)
	{
		foreach (array('canAddEvent', 'canAddVenue', 'canAddUsers', 'canPublishEvent', 'canPublishVenue') as $key) {
			${$key} = isset($permissions->$key) ? $permissions->$key: null;
		}
		if (is_object($params)) {
			foreach (array('id', 'slug', 'task', 'print_link', 'show', 'hide') as $key) {
				${$key} = isset($params->$key) ? $params->$key : null;
			}
		} elseif (is_array($params)) {
			foreach (array('id', 'slug', 'task', 'print_link', 'show', 'hide') as $key) {
				${$key} = key_exists($key, $params) ? $params[$key] : null;
			}
		} else {
			foreach (array('id', 'slug', 'task', 'print_link') as $key) {
				${$key} = null;
			}
		}

		$btns_show = isset($show) ? (array)$show : array();
		$btns_hide = isset($hide) ? (array)$hide : array();
		$archive = !empty($task) && ($task == 'archive');
		$buttons = array();
		$idx = 0;

		# Left block ------------------

		if (!$archive) {
			if (in_array('addEvent', $btns_show) || (!in_array('addEvent', $btns_hide) && in_array($view, array('categories', 'category', 'day', 'event', 'eventslist', 'myevents', 'myvenues', 'venue', 'venues')))) {
				$buttons[$idx][] = JemOutput::submitbutton(!empty($canAddEvent), null);
			}
			if (in_array('addVenue', $btns_show) || (!in_array('addVenue', $btns_hide) && in_array($view, array('categories', 'category', 'day', 'event', 'eventslist', 'myevents', 'myvenues', 'venue', 'venues')))) {
				$buttons[$idx][] = JemOutput::addvenuebutton(!empty($canAddVenue), null, null);
			}
			if (in_array('addUsers', $btns_show) || (!in_array('addUsers', $btns_hide) && in_array($view, array('attendees')))) {
				$buttons[$idx][] = JemOutput::addusersbutton(!empty($canAddUsers), $id);
			}
		}

		++$idx;

		# Middle block ----------------

		if (in_array('archive', $btns_show) || (!in_array('archive', $btns_hide) && in_array($view, array('categories', 'category', 'eventslist', 'myattendances', 'myevents', 'venue')))) {
			$buttons[$idx][] = JemOutput::archivebutton(null, $task, $id); // task: archive, id: for '&id='
		}
		if (in_array('mail', $btns_show) || (!in_array('mail', $btns_hide) && in_array($view, array('category', 'event', 'venue')))) {
			$buttons[$idx][] = JemOutput::mailbutton($slug, $view, null); // slug: for '&id='
		}
		if (in_array('print', $btns_show) || (!in_array('print', $btns_hide) && in_array($view, array('attendees', 'calendar', 'categories', 'category', 'category-cal', 'day', 'event', 'eventslist', 'myattendances', 'myevents', 'myvenues', 'venue', 'venue-cal', 'venues', 'weekcal')))) {
			$buttons[$idx][] = JemOutput::printbutton($print_link, null);
		}
		if (in_array('ical', $btns_show) || (!in_array('ical', $btns_hide) && in_array($view, array('event')))) {
			$buttons[$idx][] = JemOutput::icalbutton($slug, $view); // slug: for '&id='
		}
		if (in_array('export', $btns_show) || (!in_array('export', $btns_hide) && in_array($view, array('attendees')))) {
			$buttons[$idx][] = JemOutput::exportbutton($id); // id: for '&id='
		}
		if (in_array('back', $btns_show) || (!in_array('back', $btns_hide) && in_array($view, array('attendees')))) {
			$buttons[$idx][] = JemOutput::backbutton(null, $view);
		}

		++$idx;

		# Right block -----------------

		if (!empty($canPublishEvent) || !empty($canPublishVenue)) {
			if (in_array('publish', $btns_show) || (!in_array('publish', $btns_hide) && in_array($view, array('myevents', 'myvenues')))) {
				$buttons[$idx][] = JemOutput::publishbutton($view);
			}
			if (in_array('unpublish', $btns_show) || (!in_array('unpublish', $btns_hide) && in_array($view, array('myevents', 'myvenues')))) {
				$buttons[$idx][] = JemOutput::unpublishbutton($view);
			}
			if (in_array('trash', $btns_show) || (!in_array('trash', $btns_hide) && in_array($view, array('myevents')))) {
				$buttons[$idx][] = JemOutput::trashbutton($view);
			}
		}

		# -----------------------------

		foreach ($buttons as $i => $btns) {
			$buttons[$i] = implode('', array_filter($btns));
		}
		$result = implode('<span class="gap">&nbsp;</span>', array_filter($buttons));
		return $result;
	}

	/**
	 * Writes Event submission button
	 *
	 * @param int $dellink Access of user
	 * @param array $params needed params
	 **/
	static public function submitbutton($dellink, $params)
	{
		if ($dellink)
		{
			$settings  = JemHelper::globalattribs();
			$settings2 = JemHelper::config();

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

			$url = 'index.php?option=com_jem&task=event.add&return='.base64_encode($uri).'&a_id=0';
			$overlib = JText::_('COM_JEM_SUBMIT_EVENT_DESC');
			$output = JHtml::_('link', JRoute::_($url), $image, self::tooltip(JText::_('COM_JEM_DELIVER_NEW_EVENT'), $overlib, '', 'bottom'));

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
	static public function addvenuebutton($addvenuelink, $params, $settings2)
	{
		if ($addvenuelink) {
			$app      = JFactory::getApplication();
			$settings = JemHelper::globalattribs();
			$uri      = JFactory::getURI();

			if ($app->input->get('print','','int')) {
				return;
			}

			JHtml::_('behavior.tooltip');

			if ($settings->get('global_show_icons',1)) {
				$image = JHtml::_('image', 'com_jem/addvenue.png', JText::_('COM_JEM_DELIVER_NEW_VENUE'), NULL, true);
			} else {
				$image = JText::_('COM_JEM_DELIVER_NEW_VENUE');
			}

			$url = 'index.php?option=com_jem&task=venue.add&return='.base64_encode($uri).'&a_id=0';
			$overlib = JText::_('COM_JEM_DELIVER_NEW_VENUE_DESC');
			$output = JHtml::_('link', JRoute::_($url), $image, self::tooltip(JText::_('COM_JEM_DELIVER_NEW_VENUE'), $overlib, '', 'bottom'));

			return $output;
		}
	}

	/**
	 * Writes addvenuebutton
	 *
	 * @param int $addvenuelink Access of user
	 * @param int $eventid id of corresponding event
	 * @param array $params needed params
	 * @param $settings, retrieved from settings-table
	 *
	 * Active in views:
	 * venue, venues
	 **/
	static public function addusersbutton($adduserslink, $eventid)
	{
		if ($adduserslink) {
			$app      = JFactory::getApplication();
			$settings = JemHelper::globalattribs();
			$uri      = JFactory::getURI();

			if ($app->input->get('print','','int')) {
				return;
			}

			JHtml::_('behavior.tooltip');

			if ($settings->get('global_show_icons',1)) {
				$image = JHtml::_('image', 'com_jem/icon-16-new.png', JText::_('COM_JEM_ADD_USER_REGISTRATIONS'), NULL, true);
			} else {
				$image = JText::_('COM_JEM_ADD_USER_REGISTRATIONS');
			}

			$url = 'index.php?option=com_jem&view=attendees&layout=addusers&tmpl=component&return='.base64_encode($uri).'&id='.$eventid.'&'.JSession::getFormToken().'=1';
			$overlib = JText::_('COM_JEM_ADD_USER_REGISTRATIONS_DESC');
			$output = JHtml::_('link', JRoute::_($url), $image, self::tooltip(JText::_('COM_JEM_ADD_USER_REGISTRATIONS'), $overlib, 'flyermodal', 'bottom').' rel="{handler: \'iframe\', size: {x:800, y:450}}"');

			return $output;
		}
	}

	/**
	 * Prepares addeventbutton for calendar days.
	 *
	 * @param string $urlparams additional url oarams, e.g. 'locid=123'
	 *
	 * Active in views:
	 * all calendar views
	 **/
	static public function prepareAddEventButton($urlparams = '')
	{
		$uri = JFactory::getURI();
		$image = JHtml::_('image', 'com_jem/icon-16-new.png', JText::_('COM_JEM_DELIVER_NEW_EVENT'), NULL, true);
		$url   = 'index.php?option=com_jem&task=event.add&a_id=0&date={date}&return='.base64_encode($uri);
		if (!empty($urlparams) && preg_match('/^[a-z]+=\w+$/i', $urlparams)) {
			$url .= '&'.$urlparams;
		}
		$html  = '<div class="inline-button-right">';
		$html .= JHtml::_('link', JRoute::_($url), $image, self::tooltip(JText::_('COM_JEM_DELIVER_NEW_EVENT'), JText::_('COM_JEM_SUBMIT_EVENT_DESC'), '', 'bottom'));
		$html .= '</div>';

		return $html;
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
	static public function archivebutton($params, $task = NULL, $id = NULL)
	{
		$settings  = JemHelper::globalattribs();
		$settings2 = JemHelper::config();
		$app       = JFactory::getApplication();

		if ($settings->get('global_show_archive_icon',1)) {
			if ($app->input->get('print','','int')) {
				return;
			}

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
					$url = 'index.php?option=com_jem&view='.$view.'&id='.$id.'&filter_reset=1';
				} else {
					$url = 'index.php?option=com_jem&view='.$view.'&filter_reset=1';
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
					$url = 'index.php?option=com_jem&view='.$view.'&id='.$id.'&task=archive'.'&filter_reset=1';
				} else {
					$url = 'index.php?option=com_jem&view='.$view.'&task=archive'.'&filter_reset=1';
				}
			}

			$output = JHtml::_('link', JRoute::_($url), $image, self::tooltip($title, $overlib, '', 'bottom'));

			return $output;
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
	static public function editbutton($item, $params, $attribs, $allowedtoedit, $view)
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
			$user   = JemFactory::getUser();
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
						return '<span ' . self::tooltip(JText::_('JLIB_HTML_CHECKED_OUT'), htmlspecialchars(JText::sprintf('COM_JEM_GLOBAL_CHECKED_OUT_BY', $checkoutUser->name) . ' <br /> ' . $date, ENT_COMPAT, 'UTF-8')) . '>' . $button . '</span>';
					}

					if ($showIcon) {
						$image = JHtml::_('image', 'com_jem/calendar_edit.png', JText::_('COM_JEM_EDIT_EVENT'), NULL, true);
					} else {
						$image = JText::_('COM_JEM_EDIT_EVENT');
					}
					$id = isset($item->did) ? $item->did : $item->id;
					$overlib = JText::_('COM_JEM_EDIT_EVENT_DESC');
					$text = JText::_('COM_JEM_EDIT_EVENT');
					$url = 'index.php?option=com_jem&task=event.edit&a_id='.$id.'&return='.base64_encode($uri);
					break;

				case 'editvenue':
					if (property_exists($item, 'vChecked_out') && property_exists($item, 'vChecked_out_time') && $item->vChecked_out > 0 && $item->vChecked_out != $userId) {
						$checkoutUser = JFactory::getUser($item->vChecked_out);
						$button = JHtml::_('image', 'system/checked_out.png', NULL, NULL, true);
						$date = JHtml::_('date', $item->vChecked_out_time);
						return '<span ' . self::tooltip(JText::_('JLIB_HTML_CHECKED_OUT'), htmlspecialchars(JText::sprintf('COM_JEM_GLOBAL_CHECKED_OUT_BY', $checkoutUser->name) . ' <br /> ' . $date, ENT_COMPAT, 'UTF-8')) . '>' . $button . '</span>';
					}

					if ($showIcon) {
						$image = JHtml::_('image', 'com_jem/calendar_edit.png', JText::_('COM_JEM_EDIT_VENUE'), NULL, true);
					} else {
						$image = JText::_('COM_JEM_EDIT_VENUE');
					}
					$id = $item->locid;
					$overlib = JText::_('COM_JEM_EDIT_VENUE_DESC');
					$text = JText::_('COM_JEM_EDIT_VENUE');
					$url = 'index.php?option=com_jem&task=venue.edit&a_id='.$id.'&return='.base64_encode($uri);
					break;

				case 'venue':
					if (property_exists($item, 'vChecked_out') && property_exists($item, 'vChecked_out_time') && $item->vChecked_out > 0 && $item->vChecked_out != $userId) {
						$checkoutUser = JFactory::getUser($item->vChecked_out);
						$button = JHtml::_('image', 'system/checked_out.png', NULL, NULL, true);
						$date = JHtml::_('date', $item->vChecked_out_time);
						return '<span ' . self::tooltip(JText::_('JLIB_HTML_CHECKED_OUT'), htmlspecialchars(JText::sprintf('COM_JEM_GLOBAL_CHECKED_OUT_BY', $checkoutUser->name) . ' <br /> ' . $date, ENT_COMPAT, 'UTF-8')) . '>' . $button . '</span>';
					}

					if ($showIcon) {
						$image = JHtml::_('image', 'com_jem/calendar_edit.png', JText::_('COM_JEM_EDIT_VENUE'), NULL, true);
					} else {
						$image = JText::_('COM_JEM_EDIT_VENUE');
					}
					$id = $item->id;
					$overlib = JText::_('COM_JEM_EDIT_VENUE_DESC');
					$text = JText::_('COM_JEM_EDIT_VENUE');
					$url = 'index.php?option=com_jem&task=venue.edit&a_id='.$id.'&return='.base64_encode($uri);
					break;
			}

			if (!$url) {
				return; // we need at least url to generate useful output
			}

			$output = JHtml::_('link', JRoute::_($url), $image, self::tooltip($text, $overlib));

			return $output;
		}
	}

	/**
	 * Creates a copy button
	 *
	 * @param object $item
	 * @param array $params
	 * @param int $allowedtoadd
	 * @param string $view
	 *
	 * Views:
	 * Event, Venue
	 */
	static public function copybutton($item, $params, $attribs, $allowedtoadd, $view)
	{
		if ($allowedtoadd) {
			$app = JFactory::getApplication();

			if ($app->input->get('print','','int')) {
				return;
			}

			// Initialise variables.
			$user   = JemFactory::getUser();
			$userId = $user->get('id');
			$uri    = JFactory::getURI();
			$settings = JemHelper::globalattribs();

			JHtml::_('behavior.tooltip');

			// On Joomla Edit icon is always used regardless if "Show icons" is set to Yes or No.
			$showIcon = 1; //$settings->get('global_show_icons', 1);

			switch ($view)
			{
				case 'editevent':
					if ($showIcon) {
						$image = JHtml::_('image', 'com_jem/calendar_copy.png', JText::_('COM_JEM_COPY_EVENT'), NULL, true);
					} else {
						$image = JText::_('COM_JEM_COPY_EVENT');
					}
					$id = isset($item->did) ? $item->did : $item->id;
					$overlib = JText::_('COM_JEM_COPY_EVENT_DESC');
					$text = JText::_('COM_JEM_COPY_EVENT');
					$url = 'index.php?option=com_jem&task=event.copy&a_id='.$id.'&return='.base64_encode($uri);
					break;

				case 'editvenue':
					if ($showIcon) {
						$image = JHtml::_('image', 'com_jem/calendar_copy.png', JText::_('COM_JEM_COPY_VENUE'), NULL, true);
					} else {
						$image = JText::_('COM_JEM_COPY_VENUE');
					}
					$id = $item->locid;
					$overlib = JText::_('COM_JEM_COPY_VENUE_DESC');
					$text = JText::_('COM_JEM_COPY_VENUE');
					$url = 'index.php?option=com_jem&task=venue.copy&a_id='.$id.'&return='.base64_encode($uri);
					break;

				case 'venue':
					if ($showIcon) {
						$image = JHtml::_('image', 'com_jem/calendar_copy.png', JText::_('COM_JEM_COPY_VENUE'), NULL, true);
					} else {
						$image = JText::_('COM_JEM_COPY_VENUE');
					}
					$id = $item->id;
					$overlib = JText::_('COM_JEM_COPY_VENUE_DESC');
					$text = JText::_('COM_JEM_COPY_VENUE');
					$url = 'index.php?option=com_jem&task=venue.copy&a_id='.$id.'&return='.base64_encode($uri);
					break;
			}

			if (!$url) {
				return; // we need at least url to generate useful output
			}

			$output = JHtml::_('link', JRoute::_($url), $image, self::tooltip($text, $overlib));

			return $output;
		}
	}

	/**
	 * Creates the print button
	 *
	 * @param string $print_link
	 * @param array $params
	 */
	static public function printbutton($print_link, $params)
	{
		$app      = JFactory::getApplication();
		$settings = JemHelper::globalattribs();

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
				$output = '<a href="' . JRoute::_($print_link) . '" ' . self::tooltip($text, $overlib, 'editlinktip', 'bottom')
				        . ' onclick="window.open(this.href,\'win2\',\'' . $status . '\'); return false;">' . $image . '</a>';
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
	static public function mailbutton($slug, $view, $params)
	{
		$app 		= JFactory::getApplication();
		$settings	= JemHelper::globalattribs();

		if ($settings->get('global_show_email_icon')) {
			if ($app->input->get('print','','int')) {
				return;
			}

			JHtml::_('behavior.tooltip');
			require_once JPATH_SITE . '/components/com_mailto/helpers/mailto.php';

			$uri = JUri::getInstance();
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
			$output = '<a href="' . JRoute::_($url) . '" ' . self::tooltip($text, $overlib, '', 'bottom')
			        . ' onclick="window.open(this.href,\'win2\',\'' . $status . '\'); return false;">' . $image . '</a>';

			return $output;
		}
	}

	/**
	 * Creates the ical button
	 *
	 * @param object $slug
	 * @param array $params
	 */
	static public function icalbutton($slug, $view)
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
			$output = JHtml::_('link', JRoute::_($url), $image, self::tooltip($text, $overlib, '', 'bottom'));

			return $output;
		}
	}

	/**
	 * Creates the publish button
	 *
	 * View:
	 * Myevents, Myvenues
	 */
	static public function publishbutton($prefix)
	{
		$app = JFactory::getApplication();

		if (empty($prefix) || $app->input->get('print','','int')) {
			// button in popup or wrong call
			$output = '';
		} else {
			// button in view
			JHtml::_('behavior.tooltip');

			$image = JHtml::_('image', 'com_jem/publish.png', JText::_('COM_JEM_PUBLISH'), NULL, true);
			$overlib = JText::_('COM_JEM_PUBLISH_DESC');
			$text = JText::_('COM_JEM_PUBLISH');

			$print_link = "javascript:void(Joomla.submitbutton('" . $prefix . ".publish'));";
			$output = '<a href="' . JRoute::_($print_link) . '" ' . self::tooltip($text, $overlib, 'editlinktip', 'bottom') . '>' . $image . '</a>';
		}

		return $output;
	}

	/**
	 * Creates the trash button
	 *
	 * View:
	 * Myevents, Myvenues
	 */
	static public function trashbutton($prefix)
	{
		$app = JFactory::getApplication();

		if (empty($prefix) || $app->input->get('print','','int')) {
			// button in popup or wrong call
			$output = '';
		} else {
			// button in view
			JHtml::_('behavior.tooltip');

			$image = JHtml::_('image', 'com_jem/trash.png', JText::_('COM_JEM_TRASH'), NULL, true);
			$overlib = JText::_('COM_JEM_TRASH_DESC');
			$text = JText::_('COM_JEM_TRASH');

			$print_link = "javascript:void(Joomla.submitbutton('" . $prefix . ".trash'));";
			$output = '<a href="' . JRoute::_($print_link) . '" ' . self::tooltip($text, $overlib, 'editlinktip', 'bottom') . '>' . $image . '</a>';
		}

		return $output;
	}

	/**
	 * Creates the unpublish button
	 *
	 * View:
	 * Myevents, Myvenues
	 */
	static public function unpublishbutton($prefix)
	{
		$app = JFactory::getApplication();

		if (empty($prefix) || $app->input->get('print','','int')) {
			// button in popup or wrong call
			$output = '';
		} else {
			// button in view
			JHtml::_('behavior.tooltip');

			$image = JHtml::_('image', 'com_jem/unpublish.png', JText::_('COM_JEM_UNPUBLISH'), NULL, true);
			$overlib = JText::_('COM_JEM_UNPUBLISH_DESC');
			$text = JText::_('COM_JEM_UNPUBLISH');

			$print_link = "javascript:void(Joomla.submitbutton('" . $prefix . ".unpublish'));";
			$output = '<a href="' . JRoute::_($print_link) . '" ' . self::tooltip($text, $overlib, 'editlinktip', 'bottom') . '>' . $image . '</a>';
		}

		return $output;
	}

	/**
	 * Creates the export button
	 *
	 * view:
	 * attendees
	 */
	static public function exportbutton($eventid)
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

			$print_link = 'index.php?option=com_jem&view=attendees&task=attendees.export&tmpl=raw&id=' . $eventid . '&' . JSession::getFormToken() . '=1';
			$output = '<a href="' . JRoute::_($print_link) . '" ' . self::tooltip($text, $overlib, 'editlinktip', 'bottom') . '>' . $image . '</a>';
		}

		return $output;
	}

	/**
	 * Creates the back button
	 *
	 * view:
	 * attendees
	 */
	static public function backbutton($backlink, $view)
	{
		$app = JFactory::getApplication();
		$id  = $app->input->getInt('id');
		$fid = $app->input->getInt('Itemid');

		JHtml::_('behavior.tooltip');

		$image = JHtml::_('image', 'com_jem/icon-16-back.png', JText::_('COM_JEM_BACK'), NULL, true);

		if ($app->input->get('print','','int')) {
			//button in popup
			$output = '';
		} else {
			//button in view
			$overlib = JText::_('COM_JEM_BACK');
			$text = JText::_('COM_JEM_BACK');

			$link = 'index.php?option=com_jem&view='.$view.'&id='.$id.'&Itemid='.$fid.'&task='.$view.'.back';
			$output = '<a href="' . JRoute::_($link) . '" ' . self::tooltip($text, $overlib, 'editlinktip', 'bottom') . '>' . $image . '</a>';
		}

		return $output;
	}

	/**
	 * Creates attributes for a tooltip depending on Joomla version
	 *
	 * @param  string  $title   translated title of the tooltip
	 * @param  string  $text    translated text of the tooltip
	 * @param  string  $classes additional css classes (optional)
	 *
	 * @return string  attributes in form 'class="..." title="..."'
	 */
	static public function tooltip($title, $text, $classes = '', $position = '')
	{
		$result = array();
		if (version_compare(JVERSION, '3.3', 'lt')) {
			// on Joomla! 2.5/3.2 we use good old tooltips
			JHtml::_('behavior.tooltip');
			$result = 'class="'.$classes.' hasTip" title="'.$title.'::'.$text.'"';
		} else {
			// on Joomla! 3.3+ we must use the new tooltips
			JHtml::_('bootstrap.tooltip');
			$result = 'class="'.$classes.' hasTooltip" title="'.JHtml::tooltipText($title, $text, 0).'"';
			if (!empty($position) && (array_search($position, array('top', 'bottom', 'left', 'right')) !== false)) {
				$result .= ' data-placement="'.$position.'"';
			}
		}
		return $result;
	}

	/**
	 * Creates the map button
	 *
	 * @param obj $data
	 */
	static public function mapicon($data, $view = false, $params)
	{
		$settings = JemHelper::globalattribs();

		//stop if disabled
		if (!$data->map) {
			return;
		}

		if ($view == 'event') {
			$tld     = 'event_tld';
			$lg      = 'event_lg';
			$mapserv = $params->get('event_show_mapserv');
		} else if ($view == 'venues') {
			$tld     = 'global_tld';
			$lg      = 'global_lg';
			$mapserv = ($mapserv == 3) ? 0 : $params->get('global_show_mapserv');
		} else {
			$tld     = 'global_tld';
			$lg      = 'global_lg';
			$mapserv = $params->get('global_show_mapserv');
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

		$url = 'https://maps.google.'.$params->get($tld,'com').'/maps?hl='.$params->get($lg,'com').'&q='.urlencode($data->street.', '.$data->postalCode.' '.$data->city.', '.$data->country.'+ ('.$data->venue.')').'&ie=UTF8&z=15&iwloc=B&output=embed" ';

		// google map link or include
		switch ($mapserv)
		{
			case 1:
				// link
				if($data->latitude && $data->longitude) {
					$url = 'https://maps.google.'.$params->get($tld).'/maps?hl='.$params->get($lg).'&q=loc:'.$data->latitude.',+'.$data->longitude.'&ie=UTF8&z=15&iwloc=B&output=embed';
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
					$document->addScript('https://maps.googleapis.com/maps/api/js?client='.$clientid.'&sensor=false&v=3.15');
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
	static public function recurrenceicon($event, $showinline = true, $showtitle = true)
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
	 * Creates the unpublished icon
	 *
	 * @param mixed $item         mixed Object with attribute 'published' or plain value containing the state (well known -2, 0, 1, 2)
	 * @param array $ignorestates States to ignore (returning empty string), defaults to trashed (-2), published (1) and archived (2)
	 * @param bool  $showinline   Add css class to scale icon to fit text height
	 * @param bool  $showtitle    Add title (tooltip)
	 */
	static public function publishstateicon($item, $ignorestates = array(-2, 1, 2), $showinline = true, $showtitle = true)
	{
		//$settings = JemHelper::globalattribs();  /// @todo use global setting to influence visibility of publish state icon?

		// early return
		if (is_object($item)) {
			if (!isset($item->published) || in_array($item->published, $ignorestates)) {
				return '';
			}
		} else {
			if (in_array($item, $ignorestates)) {
				return '';
			}
		}

		$published = is_object($item) ? $item->published : $item;
		switch ($published) {
		case -2: // trashed
			$image = 'com_jem/trash.png';
			$alt   = JText::_('JTRASHED');
			break;
		case  0: // unpublished
			$image = 'com_jem/publish_x.png';
			$alt   = JText::_('JUNPUBLISHED');
			break;
		case  1: // published
			$image = 'com_jem/publish.png';
			$alt   = JText::_('JPUBLISHED');
			break;
		case  2: // archived
			$image = 'com_jem/archive_front.png';
			$alt   = JText::_('JARCHIVED');
			break;
		default: // unknown state - abort!
			return '';
		}

		// additional attributes
		$attributes = array();
		if ($showinline) {
			$attributes['class'] = 'icon-inline';
		}
		if ($showtitle) {
			$attributes['title'] = $alt;
		}

		$output = JHtml::_('image', $image, $alt, $attributes, true);

		return $output;
	}

	/**
	 * Creates the flyer
	 *
	 * @param obj $data
	 * @param array $image
	 * @param string $type
	 */
	static public function flyer($data, $image, $type, $id = null)
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
		if (empty($imagefile) || empty($image)) {
			return;
		}

		jimport('joomla.filesystem.file');

		// Does a thumbnail exist?
		if (JFile::exists(JPATH_SITE.'/images/jem/'.$folder.'/small/'.$imagefile)) {
			if ($settings->lightbox == 0) {
				//$url = '#';  // Hoffi, 2014-06-07: '#' doesn't work, it opend "Add event" page - don't use <a, onclick works fine with <img :-)
				$attributes = $id_attr.' class="flyerimage" onclick="window.open(\''.JUri::base().$image['original'].'\',\'Popup\',\'width='.$image['width'].',height='.$image['height'].',location=no,menubar=no,scrollbars=no,status=no,toolbar=no,resizable=no\')"';

				$icon = '<img '.$attributes.' src="'.JUri::base().$image['thumb'].'" width="'.$image['thumbwidth'].'" height="'.$image['thumbheight'].'" alt="'.$info.'" title="'.JText::_('COM_JEM_CLICK_TO_ENLARGE').'" />';
				$output = '<div class="flyerimage">'.$icon.'</div>';
			} else {
				JHtml::_('behavior.modal', 'a.flyermodal');
				$url = JUri::base().$image['original'];
				$attributes = $id_attr.' class="flyermodal flyerimage" title="'.$info.'"';

				$icon = '<img src="'.JUri::base().$image['thumb'].'" width="'.$image['thumbwidth'].'" height="'.$image['thumbheight'].'" alt="'.$info.'" title="'.JText::_('COM_JEM_CLICK_TO_ENLARGE').'" />';
				$output = '<div class="flyerimage"><a href="'.$url.'" '.$attributes.'>'.$icon.'</a></div>';
			}
		// Otherwise take the values for the original image specified in the settings
		} else {
			$output = '<img '.$id_attr.' class="notmodal" src="'.JUri::base().$image['original'].'" width="'.$image['width'].'" height="'.$image['height'].'" alt="'.$info.'" />';
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
	static public function formatdate($date, $format = "")
	{
		$settings = JemHelper::config();
		$check    = JemHelper::isValidDate($date);
		//$timezone = JemHelper::getTimeZoneName();
		$timezone = null;

		if ($check) {
			$jdate = new JDate($date, $timezone);
			if (!$format) {
				// If no format set, use long format as standard
				$format = $settings->formatdate;
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
	static public function formattime($time, $format = "", $addSuffix = true)
	{
		$settings = JemHelper::config();
		$check    = JemHelper::isValidTime($time);

		if (!$check)
		{
			return;
		}

		if(!$format) {
			// If no format set, use settings format as standard
			$format = $settings->formattime;
		}

		$formattedTime = strftime($format, strtotime($time));

		if ($addSuffix && !empty($settings->timename)) {
			$formattedTime .= ' '.$settings->timename;
		}

		return $formattedTime;
	}

	/**
	 * Formats the input dates and times to be used as a from-to string for
	 * events. Takes care of unset dates and or times.
	 * Values can be styled using css classes jem_date-1 and jem_time-1.
	 *
	 * @param  mixed  $dateStart Start date of event or an associative array with keys contained in
	 *                           {'dateStart','timeStart','dateEnd','timeEnd','dateFormat','timeFormat','addSuffix','showTime'}
	 *                           and values corresponding to parameters of the same name.
	 * @param  string $timeStart Start time of event
	 * @param  string $dateEnd End date of event
	 * @param  string $timeEnd End time of event
	 * @param  string $dateFormat Date Format
	 * @param  string $timeFormat Time Format
	 * @param  bool   $addSuffix if true add suffix specified in settings
	 * @param  bool   $showTime global setting to respect
	 * @param  bool   $showDayLink if true date will be shown as link to day view
	 * @return string Formatted date and time string to print
	 */
	static public function formatDateTime($dateStart, $timeStart ='', $dateEnd = '', $timeEnd = '', $dateFormat = '', $timeFormat = '', $addSuffix = true, $showTime = true, $showDayLink = false)
	{
		if (is_array($dateStart)) {
			foreach (array('timeStart','dateEnd','timeEnd','dateFormat','timeFormat','addSuffix','showTime', 'showDayLink') as $param) {
				if (isset($dateStart[$param])) {
					$$param = $dateStart[$param];
				}
			}
			$dateStart = isset($dateStart['dateStart']) ? $dateStart['dateStart'] : '';
		}

		$output = '';

		if (JemHelper::isValidDate($dateStart)) {
			$output .= '<span class="jem_date-1">';
			if ($showDayLink) {
				$output .= '<a href="'.JRoute::_(JemHelperRoute::getRoute(str_replace('-', '', $dateStart), 'day')).'">';
			}
			$output .= self::formatdate($dateStart, $dateFormat);
			if ($showDayLink) {
				$output .= '</a>';
			}
			$output .= '</span>';

			if ($showTime && JemHelper::isValidTime($timeStart)) {
				$output .= ', <span class="jem_time-1">'.self::formattime($timeStart, $timeFormat, $addSuffix).'</span>';
			}

			// Display end date only when it differs from start date
			$displayDateEnd = JemHelper::isValidDate($dateEnd) && $dateEnd != $dateStart;
			if ($displayDateEnd) {
				$output .= ' - <span class="jem_date-1">';
				if ($showDayLink) {
					$output .= '<a href="'.JRoute::_(JemHelperRoute::getRoute(str_replace('-', '', $dateEnd), 'day')).'">';
				}
				$output .= self::formatdate($dateEnd, $dateFormat);
				if ($showDayLink) {
					$output .= '</a>';
				}
				$output .= '</span>';
			}

			// Display end time only when both times are set
			if ($showTime && JemHelper::isValidTime($timeStart) && JemHelper::isValidTime($timeEnd))
			{
				$output .= $displayDateEnd ? ', ' : ' - ';
				$output .= '<span class="jem_time-1">'.self::formattime($timeEnd, $timeFormat, $addSuffix).'</span>';
			}
		} else {
			$output .= '<span class="jem_date-1">'.JText::_('COM_JEM_OPEN_DATE').'</span>';

			if ($showTime) {
				if (JemHelper::isValidTime($timeStart)) {
					$output .= ', <span class="jem_time-1">'.self::formattime($timeStart, $timeFormat, $addSuffix).'</span>';

					// Display end time only when both times are set
					if (JemHelper::isValidTime($timeEnd)) {
						$output .= ' - <span class="jem_time-1">'.self::formattime($timeEnd, $timeFormat, $addSuffix).'</span>';
					}
				}
			}
		}

		return $output;
	}

	/**
	 * Formats the input dates and times to be used as a from-to string for
	 * events. Takes care of unset dates and or times.
	 * First line is for (short) date, second line for time values.
	 * Lines can be styled using css classes jem_date-2 and jem_time-2.
	 *
	 * @param  mixed  $dateStart Start date of event or an associative array with keys contained in
	 *                           {'dateStart','timeStart','dateEnd','timeEnd','dateFormat','timeFormat','addSuffix','showTime'}
	 *                           and values corresponding to parameters of the same name.
	 * @param  string $timeStart Start time of event
	 * @param  string $dateEnd End date of event
	 * @param  string $timeEnd End time of event
	 * @param  string $dateFormat Date Format
	 * @param  string $timeFormat Time Format
	 * @param  bool   $addSuffix if true add suffix specified in settings
	 * @param  bool   $showTime global setting to respect
	 * @return string Formatted date and time string to print
	 */
	static public function formatDateTime2Lines($dateStart, $timeStart = '', $dateEnd = '', $timeEnd = '', $dateFormat = '', $timeFormat = '', $addSuffix = true, $showTime = true)
	{
		if (is_array($dateStart)) {
			foreach (array('timeStart','dateEnd','timeEnd','dateFormat','timeFormat','addSuffix','showTime') as $param) {
				if (isset($dateStart[$param])) {
					$$param = $dateStart[$param];
				}
			}
			$dateStart = isset($dateStart['dateStart']) ? $dateStart['dateStart'] : '';
		}

		$output = '';
		$jemconfig = JemHelper::config();

		if (empty($dateFormat)) {
			// Use format saved in settings if specified or format in language file otherwise
			$dateFormat = empty($jemconfig->formatShortDate) ? JText::_('COM_JEM_FORMAT_SHORT_DATE') : $jemconfig->formatShortDate;
		}

		if (JemHelper::isValidDate($dateStart)) {
			$outDate = self::formatdate($dateStart, $dateFormat);

			if (JemHelper::isValidDate($dateEnd) && ($dateEnd != $dateStart)) {
				$outDate .= ' - ' . self::formatdate($dateEnd, $dateFormat);
			}
		} else {
			$outDate = JText::_('COM_JEM_OPEN_DATE');
		}

		if ($showTime && JemHelper::isValidTime($timeStart)) {
			$outTime = self::formattime($timeStart, $timeFormat, $addSuffix);

			if (JemHelper::isValidTime($timeEnd)) {
				$outTime .= ' - ' . self::formattime($timeEnd, $timeFormat, $addSuffix);
			}
		}

		$output = '<span class="jem_date-2">' . $outDate . '</span>';
		if (!empty($outTime)) {
			$output .= '<br class="jem_break-2"><span class="jem_time-2">' . $outTime . '</span>';
		}
		return $output;
	}

	/**
	 * Formats the input dates and times to be used as a long from-to string for
	 * events. Takes care of unset dates and or times.
	 *
	 * @param  string $dateStart Start date of event or an associative array with keys contained in
	 *                           {'dateStart','timeStart','dateEnd','timeEnd','showTime'}
	 *                           and values corresponding to parameters of the same name.
	 * @param  mixed  $timeStart Start time of event
	 * @param  string $dateEnd End date of event
	 * @param  string $timeEnd End time of event
	 * @param  bool   $showTime global setting to respect
	 * @return string Formatted date and time string to print
	 */
	static public function formatLongDateTime($dateStart, $timeStart = '', $dateEnd = '', $timeEnd = '', $showTime = true)
	{
		return self::formatDateTime(is_array($dateStart) ? $dateStart : array('dateStart' => $dateStart, 'timeStart' => $timeStart, 'dateEnd' => $dateEnd, 'timeEnd' => $timeEnd, 'addSuffix' => true, 'showTime' => $showTime));
	}

	/**
	 * Formats the input dates and times to be used as a short from-to string for
	 * events. Takes care of unset dates and or times.
	 *
	 * @param  string $dateStart Start date of event or an associative array with keys contained in
	 *                           {'dateStart','timeStart','dateEnd','timeEnd','showTime'}
	 *                           and values corresponding to parameters of the same name.
	 * @param  mixed  $timeStart Start time of event
	 * @param  string $dateEnd End date of event
	 * @param  string $timeEnd End time of event
	 * @param  bool   $showTime global setting to respect
	 * @return string Formatted date and time string to print
	 */
	static public function formatShortDateTime($dateStart, $timeStart = '', $dateEnd = '', $timeEnd = '', $showTime = true)
	{
		$settings = JemHelper::config();

		$params = is_array($dateStart) ? $dateStart : array('dateStart' => $dateStart, 'timeStart' => $timeStart, 'dateEnd' => $dateEnd, 'timeEnd' => $timeEnd, 'showTime' => $showTime);
		$params['addSuffix'] = true;
		// Use format saved in settings if specified or format in language file otherwise
		$params['dateFormat'] = (isset($settings->formatShortDate) && $settings->formatShortDate) ? $settings->formatShortDate : JText::_('COM_JEM_FORMAT_SHORT_DATE');

		if (isset($settings->datemode) && ($settings->datemode == 2)) {
			return self::formatDateTime2Lines($params);
		} else {
			return self::formatDateTime($params);
		}
	}

	static public function formatSchemaOrgDateTime($dateStart, $timeStart = '', $dateEnd = '', $timeEnd = '', $showTime = true)
	{
		if (is_array($dateStart)) {
			foreach (array('timeStart','dateEnd','timeEnd','showTime') as $param) {
				if (isset($dateStart[$param])) {
					$$param = $dateStart[$param];
				}
			}
			$dateStart = isset($dateStart['dateStart']) ? $dateStart['dateStart'] : '';
		}

		$output  = '';
		$formatD = 'Y-m-d';
		$formatT = '%H:%M';

		if (JemHelper::isValidDate($dateStart)) {
			$content = self::formatdate($dateStart, $formatD);

			if ($showTime && $timeStart) {
				$content .= 'T'.self::formattime($timeStart, $formatT, false);
			}
			$output .= '<meta itemprop="startDate" content="'.$content.'" />';

			if (JemHelper::isValidDate($dateEnd)) {
				$content = self::formatdate($dateEnd, $formatD);

				if ($showTime && $timeEnd) {
					$content .= 'T'.self::formattime($timeEnd, $formatT, false);
				}
				$output .= '<meta itemprop="endDate" content="'.$content.'" />';
			}
		} else {
			// Open date

			if ($showTime) {
				if ($timeStart) {
					$content = self::formattime($timeStart, $formatT, false);
					$output .= '<meta itemprop="startDate" content="'.$content.'" />';
				}
				// Display end time only when both times are set
				if ($timeStart && $timeEnd) {
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
	static public function getIcalDateArray($date, $time = null)
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

		$date = array('year'  => (int) substr($parsed, 0, 4),
		              'month' => (int) substr($parsed, 5, 2),
		              'day'   => (int) substr($parsed, 8, 2));

		//Format time
		if (substr($parsed, 11, 8) != '00:00:00')
		{
			$date['hour'] = substr($parsed, 11, 2);
			$date['min']  = substr($parsed, 14, 2);
			$date['sec']  = substr($parsed, 17, 2);
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
	static public function getCategoryList($categories, $doLink, $backend = false)
	{
		$output = array_map(
			function ($category) use ($doLink, $backend) {
				if ($doLink) {
					if ($backend) {
						$path = $category->path;
						$path = str_replace('/', ' &#187; ', $path);
						$value  = '<span ' . self::tooltip(JText::_('COM_JEM_EDIT_CATEGORY'), $path, 'editlinktip') . '>';
						$value .= '<a href="index.php?option=com_jem&amp;task=category.edit&amp;id=' . $category->id . '">' .
						              $category->catname . '</a>';
						$value .= '</span>';
					} else {
						$value  = '<a href="' . JRoute::_(JemHelperRoute::getCategoryRoute($category->catslug)) . '">' .
						              $category->catname . '</a>';
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
