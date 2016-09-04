<?php
/**
 * @version 2.1.7
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die();

/**
 * JHtml Class
 */
abstract class JHtmlJemHtml
{
	/**
	 *
	 * @param int $value state value
	 * @param int $i
	 */
	static function featured($value = 0, $i, $canChange = true)
	{
		// Array of image, task, title, action
		$states = array(
				0 => array(
						'disabled.png',
						'events.featured',
						'COM_JEM_EVENTS_UNFEATURED',
						'COM_JEM_EVENTS_TOGGLE_TO_FEATURE'
				),
				1 => array(
						'featured.png',
						'events.unfeatured',
						'COM_JEM_EVENTS_FEATURED',
						'COM_JEM_EVENTS_TOGGLE_TO_UNFEATURE'
				)
		);
		$state = JArrayHelper::getValue($states, (int) $value, $states[1]);
		$html = JHtml::_('image', 'com_jem/' . $state[0], JText::_($state[2]), NULL, true);
		if ($canChange) {
			$html = '<a href="#" onclick="return listItemTask(\'cb' . $i . '\',\'' . $state[1] . '\')" title="' . JText::_($state[3]) . '">' . $html . '</a>';
		}

		return $html;
	}


	/**
	 *
	 * @param int $value state value
	 * @param int $i
	 * @deprecated since version 2.1.7
	 */
	static function toggleStatus($value = 0, $i, $canChange = true)
	{
		if (class_exists('JemHelper')) {
			JemHelper::addLogEntry('Use of this function is deprecated. Use JemHekper::toggleAttendanceStatus() instead.', __METHOD__, JLog::WARNING);
		}

		// Array of image, task, title, action
		$states = array(
				0 => array(
						'tick.png',
						'attendees.OnWaitinglist',
						'COM_JEM_ATTENDING',
						'COM_JEM_ATTENDING'
				),
				1 => array(
						'publish_y.png',
						'attendees.OffWaitinglist',
						'COM_JEM_ON_WAITINGLIST',
						'COM_JEM_ON_WAITINGLIST'
				)
		);
		$state = JArrayHelper::getValue($states, (int) $value, $states[1]);
		$html = JHtml::_('image', 'com_jem/' . $state[0], JText::_($state[2]), NULL, true);
		if ($canChange) {
			$html = '<a href="#" onclick="return listItemTask(\'cb' . $i . '\',\'' . $state[1] . '\')" title="' . JText::_($state[3]) . '">' . $html . '</a>';
		}

		return $html;
	}


	/**
	 * Creates html code to show attendance status, maybe incl. link to toggle this status.
	 *
	 * @param int  $value status value
	 * @param int  $i registration record id
	 * @param bool $canChange current user is allowed to modify the status
	 * @param bool $print if true show icon AND text for printing
	 * @return string The html snippet.
	 */
	static function toggleAttendanceStatus($value = 0, $i, $canChange = true, $print = false)
	{
		// Array of image, task, alt-text, tooltip
		$states = array(
				-99 => array( // fallback on wrong status value
						'disabled.png',
						'',
						'COM_JEM_STATUS_UNKNOWN',
						'COM_JEM_STATUS_UNKNOWN',
						'COM_JEM_ATTENDEES_STATUS_UNKNOWN'
				),
				-1 => array( // not attending, no toggle
						'publish_r.png',
						'',
						'COM_JEM_NOT_ATTENDING',
						'COM_JEM_NOT_ATTENDING',
						'COM_JEM_ATTENDEES_NOT_ATTENDING'
				),
				0 => array( // invited, no toggle
						'invited.png',
						'',
						'COM_JEM_INVITED',
						'COM_JEM_INVITED',
						'COM_JEM_ATTENDEES_INVITED'
				),
				1 => array( // attending, toggle: waiting list
						'tick.png',
						'attendees.OnWaitinglist',
						'COM_JEM_ATTENDING',
						'COM_JEM_ATTENDING_MOVE_TO_WAITINGLIST',
						'COM_JEM_ATTENDEES_ATTENDING'
				),
				2 => array( // on waiting list, toggle: list of attendees
						'publish_y.png',
						'attendees.OffWaitinglist',
						'COM_JEM_ON_WAITINGLIST',
						'COM_JEM_ON_WAITINGLIST_MOVE_TO_ATTENDING',
						'COM_JEM_ATTENDEES_ON_WAITINGLIST'
				)
		);

		$backend = (bool)JFactory::getApplication()->isAdmin();
		$state   = JArrayHelper::getValue($states, (int) $value, $states[-99]);

		if (version_compare(JVERSION, '3.3', 'lt')) {
			// on Joomla! 2.5/3.2 we use good old tooltips
			JHtml::_('behavior.tooltip');
			$attr = 'class="hasTip" title="'.JText::_('COM_JEM_STATUS').'::'.JText::_($state[$canChange ? 3 : 2]).'"';
		} else {
			// on Joomla! 3.3+ we must use the new tooltips
			JHtml::_('bootstrap.tooltip');
			$attr = 'class="hasTooltip" title="'.JHtml::tooltipText(JText::_('COM_JEM_STATUS'), JText::_($state[$canChange ? 3 : 2]), 0).'"';
		}

		if ($print) {
			$html  = JHtml::_('image', 'com_jem/' . $state[0], '', 'class="icon-inline-left"', true);
			$html .= JText::_($state[4]);
		} elseif ($canChange && !empty($state[1])) {
			$html = JHtml::_('image', 'com_jem/' . $state[0], JText::_($state[2]), NULL, true);
			if ($backend) {
				$attr .= ' onclick="return listItemTask(\'cb' . $i . '\',\'' . $state[1] . '\')"';
				$url = '#';
			} else {
				$url = JRoute::_('index.php?option=com_jem&view=attendees&amp;task=attendees.attendeetoggle&id='.$i.'&'.JSession::getFormToken().'=1');
			}
			$html = JHtml::_('link', $url, $html, $attr);
		} else {
			$html = JHtml::_('image', 'com_jem/' . $state[0], JText::_($state[2]), $attr, true);
		}

		return $html;
	}
}