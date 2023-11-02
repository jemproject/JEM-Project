<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

// no direct access
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
/**
 * HTMLHelper Class
 */
// abstract class HTMLHelperJemHtml
class JemHtml
{
	/**
	 *
	 * @param int $i
	 * @param int $value state value
	 */
	static public function featured($i, $value = 0, $canChange = true)
	{
		// Array of image, iconfont, task, title, action
		$states = array(
				0 => array(
						'disabled.png',
						// 'fa-star-o', //'fa-circle-o',
						'fas fa-star', //'fa-circle-o',
						'events.featured',
						'COM_JEM_EVENTS_UNFEATURED',
						'COM_JEM_EVENTS_TOGGLE_TO_FEATURE'
				),
				1 => array(
						'featured.png',
						'fa-star', //'fa-circle',
						'events.unfeatured',
						'COM_JEM_EVENTS_FEATURED',
						'COM_JEM_EVENTS_TOGGLE_TO_UNFEATURE'
				)
		);
		$state = \Joomla\Utilities\ArrayHelper::getValue($states, (int) $value, $states[1]);
		$no_iconfont = (bool)Factory::getApplication()->isClient('administrator'); // requires font and css loaded which isn't yet on backend
		$html = HTMLHelper::_('jemhtml.icon', 'com_jem/'.$state[0], 'fa fa-fw fa-lg '.$state[1].' jem-featured-'.$state[1], $state[3], null, $no_iconfont);
		if ($canChange) {
			$html = '<a href="#" onclick="return Joomla.listItemTask(\'cb' . $i . '\',\'' . $state[2] . '\')" title="' . Text::_($state[4]) . '">' . $html . '</a>';
		}

		return $html;
	}

	/**
	 *
	 * @param int $i
	 * @param int $value state value
	 * @deprecated since version 2.1.7
	 */
	static public function toggleStatus($i, $value = 0, $canChange = true)

	{
		if (class_exists('JemHelper')) {
			JemHelper::addLogEntry('Use of this function is deprecated. Use JemHekper::toggleAttendanceStatus() instead.', __METHOD__, JLog::WARNING);
		}

		// Array of image, iconfont, task, title, action
		$states = array(
				0 => array(
						'tick.png',
						'fa-check-circle',
						'attendees.OnWaitinglist',
						'COM_JEM_ATTENDING',
						'COM_JEM_ATTENDING'
				),
				1 => array(
						'publish_y.png',
						'fa-hourglass-half', //'fa-exclamation-circle',
						'attendees.OffWaitinglist',
						'COM_JEM_ON_WAITINGLIST',
						'COM_JEM_ON_WAITINGLIST'
				)
		);
		$state = \Joomla\Utilities\ArrayHelper::getValue($states, (int) $value, $states[1]);
		$no_iconfont = (bool)Factory::getApplication()->isClient('administrator'); // requires font and css loaded which isn't yet on backend
		$html = HTMLHelper::_('jemhtml.icon', 'com_jem/'.$state[0], 'fa fa-fw fa-lg '.$state[1].' jem-attendance-status-'.$state[1], $state[3], null, $no_iconfont);
		if ($canChange) {
			$html = '<a href="#" onclick="return Joomla.listItemTask(\'cb' . $i . '\',\'' . $state[2] . '\')" title="' . Text::_($state[4]) . '">' . $html . '</a>';
		}

		return $html;
	}

	/**
	 * Returns text of attendance status, maybe incl. hint to toggle this status.
	 *
	 * @param int  $i registration record id
	 * @param int  $value status value
	 * @param bool $canChange current user is allowed to modify the status
	 * @param bool $print if true show icon AND text for printing
	 * @return string The html snippet.
	 */
	static public function getAttendanceStatusText($i, $value = 0, $canChange = true, $print = false)

	{
		// Array of image, iconfont, task, alt-text, alt-text edit, tooltip
		$states = array(
				-99 => array( // fallback on wrong status value
						'disabled.png',
						'fa-circle-o',
						'',
						'COM_JEM_STATUS_UNKNOWN',
						'COM_JEM_STATUS_UNKNOWN',
						'COM_JEM_ATTENDEES_STATUS_UNKNOWN'
				),
				-1 => array( // not attending, no toggle
						'publish_r.png',
						'fa-times-circle',
						'',
						'COM_JEM_NOT_ATTENDING',
						'COM_JEM_NOT_ATTENDING',
						'COM_JEM_ATTENDEES_NOT_ATTENDING'
				),
				0 => array( // invited, no toggle
						'invited.png',
						'fa-question-circle',
						'',
						'COM_JEM_INVITED',
						'COM_JEM_INVITED',
						'COM_JEM_ATTENDEES_INVITED'
				),
				1 => array( // attending, toggle: waiting list
						'tick.png',
						'fa-check-circle',
						'attendees.OnWaitinglist',
						'COM_JEM_ATTENDING',
						'COM_JEM_ATTENDING_MOVE_TO_WAITINGLIST',
						'COM_JEM_ATTENDEES_ATTENDING'
				),
				2 => array( // on waiting list, toggle: list of attendees
						'publish_y.png',
						'fa-hourglass-half', //'fa-exclamation-circle',
						'attendees.OffWaitinglist',
						'COM_JEM_ON_WAITINGLIST',
						'COM_JEM_ON_WAITINGLIST_MOVE_TO_ATTENDING',
						'COM_JEM_ATTENDEES_ON_WAITINGLIST'
				)
		);

		$state   = \Joomla\Utilities\ArrayHelper::getValue($states, (int) $value, $states[-99]);

		if ($print) {
			$result = Text::_($state[5]);
		} else {
			$result = Text::_($state[$canChange ? 4 : 3]);
		}

		return $result;
	}

	/**
	 * Creates html code to show attendance status, maybe incl. link to toggle this status.
	 *
	 * @param int  $i registration record id
	 * @param int  $value status value
	 * @param bool $canChange current user is allowed to modify the status
	 * @param bool $print if true show icon AND text for printing
	 * @return string The html snippet.
	 */
	static public function toggleAttendanceStatus($i, $value = 0, $canChange = true, $print = false)

	{
		// Array of image, iconfont, task, alt-text, alt-text edit, tooltip
		$states = array(
				-99 => array( // fallback on wrong status value
						'disabled.png',
						'fa-circle',
						'',
						'COM_JEM_STATUS_UNKNOWN',
						'COM_JEM_STATUS_UNKNOWN',
						'COM_JEM_ATTENDEES_STATUS_UNKNOWN'
				),
				-1 => array( // not attending, no toggle
						'publish_r.png',
						'fa-times-circle',
						'',
						'COM_JEM_NOT_ATTENDING',
						'COM_JEM_NOT_ATTENDING',
						'COM_JEM_ATTENDEES_NOT_ATTENDING'
				),
				0 => array( // invited, no toggle
						'invited.png',
						'fa-question-circle',
						'',
						'COM_JEM_INVITED',
						'COM_JEM_INVITED',
						'COM_JEM_ATTENDEES_INVITED'
				),
				1 => array( // attending, toggle: waiting list
						'tick.png',
						'fa-check-circle',
						'attendees.OnWaitinglist',
						'COM_JEM_ATTENDING',
						'COM_JEM_ATTENDING_MOVE_TO_WAITINGLIST',
						'COM_JEM_ATTENDEES_ATTENDING'
				),
				2 => array( // on waiting list, toggle: list of attendees
						'publish_y.png',
						'fa-hourglass-half', //fa-exclamation-circle',
						'attendees.OffWaitinglist',
						'COM_JEM_ON_WAITINGLIST',
						'COM_JEM_ON_WAITINGLIST_MOVE_TO_ATTENDING',
						'COM_JEM_ATTENDEES_ON_WAITINGLIST'
				)
		);

		$backend = (bool)Factory::getApplication()->isClient('administrator');
		$state   = \Joomla\Utilities\ArrayHelper::getValue($states, (int) $value, $states[-99]);
		HTMLHelper::_('bootstrap.tooltip');
        $attr = 'class="hasTooltip" data-bs-toggle="tooltip" title="'.HTMLHelper::tooltipText(Text::_('COM_JEM_STATUS'), $canChange ? Text::_($state[4]) : str_replace(" ", "&nbsp",Text::_($state[3])), 0).'"';

		if ($print) {
			$html = jemhtml::icon('com_jem/'.$state[0], 'fa fa-fw fa-lg '.$state[1].' jem-attendance-status-'.$state[1], $state[3], 'class="icon-inline-left"', $backend);
			$html .= Text::_($state[5]);
		} elseif ($canChange && !empty($state[2])) {
			$html = jemhtml::icon('com_jem/'.$state[0], 'fa fa-fw fa-lg '.$state[1].' jem-attendance-status-'.$state[1], $state[3], null, $backend);
			if ($backend) {
				$attr .= ' onclick="return Joomla.listItemTask(\'cb' . $i . '\',\'' . $state[2] . '\')"';
				$url = '#';
			} else {
				$url = Route::_('index.php?option=com_jem&view=attendees&amp;task=attendees.attendeetoggle&id='.$i.'&'.Session::getFormToken().'=1');
			}
			$html = HTMLHelper::_('link', $url, $html, $attr);
		} else {
			$html = jemhtml::icon('com_jem/'.$state[0], 'fa fa-fw fa-lg '.$state[1].' jem-attendance-status-'.$state[1], $state[3], $attr, $backend);
		}
		//-------------start added for tooltips initialize-----------
		$html .= '<script>
			jQuery(document).ready(function(){
				var tooltipTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="tooltip"]\'))
				var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
				return new bootstrap.Tooltip(tooltipTriggerEl,{html:true})
				})
			});
		</script>';
		//-------------end added for tooltips initialize-----------
		return $html;
	}

	/**
	 * Creates html code to show an icon, using image or icon font depending on configuration.
	 * Call HTMLHelper::_('jemhtml.icon', $image, $icon, $alt, $attribs, $no_iconfont, $relative)
	 *
	 * @param string  $value status value
	 * @param  string        $image        The relative or absolute URL to use for the `<img>` src attribute.
	 * @param  string        $icon         The CSS class(es) to specify the icon in case icon font will be used.
	 * @param  string        $alt          The alt text.
	 * @param  array|string  $attribs      Attributes to be added to the `<img>` or `<span>` element
	 * @param  boolean       $no_iconfont  Flag if configuration should be ignored and images should be used always (e.g. on backend).
	 * @param  boolean       $relative     Flag if the path to the file is relative to the /media folder (and searches in template).
	 * @return string                      The html snippet.
	 */
	static public function icon($image, $icon, $alt, $attribs = null, $no_iconfont = false, $relative = true)
	{
		$useiconfont = !$no_iconfont && (JemHelper::config()->useiconfont == 1);

		if (!$useiconfont) {
			$html = HTMLHelper::_('image', $image, Text::_($alt), $attribs, $relative);
		} elseif (!empty($attribs)) {
			$html = '<span '.trim((is_array($attribs) ? \Joomla\Utilities\ArrayHelper::toString($attribs) : $attribs) . ' /').'><i class="'.$icon.'"></i></span>';
		} else {
			$html = '<i class="'.$icon.'" aria-hidden="true"></i>';
		}

		return $html;
	}
}
