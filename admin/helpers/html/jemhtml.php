<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
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
		$html = JHtml::_('image', 'admin/' . $state[0], JText::_($state[2]), NULL, true);
		if ($canChange) {
			$html = '<a href="#" onclick="return listItemTask(\'cb' . $i . '\',\'' . $state[1] . '\')" title="' . JText::_($state[3]) . '">' . $html . '</a>';
		}
		
		return $html;
	}
}
