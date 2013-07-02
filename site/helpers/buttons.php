<?php
/**
* @version 1.9 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php

 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

defined('_JEXEC') or die;

/**
 * Holds the logic for attachments manipulation
 *
 * @package JEM
 */
class JButtonFrontend extends JButton {
	

	/**
	 * Button type
	 *
	 * @var    string
	 */
	protected $_name = 'Standard';
	
	
	
//Goes inside JButtonFrontend class definition.
public function fetchButton($type = 'Standard', $name = '', $text = '', $task = '', $list = true)
{
	$i18n_text = JText::_($text);
	$class = $this->fetchIconClass($name);
	$doTask = $this->_getCommand($text, $task, $list);

	$html = "<a href=\"javascript: void( $doTask);\" onclick=\"$doTask\" class=\"toolbar\">\n";
	$html .= "<span class=\"$class\">\n";
	$html .= "</span>\n";
	$html .= "$i18n_text\n";
	$html .= "</a>\n";

	return $html;
}

/**
 * Get the button CSS Id
 *
 * @param   string   $type      Unused string.
 * @param   string   $name      Name to be used as apart of the id
 * @param   string   $text      Button text
 * @param   string   $task      The task associated with the button
 * @param   boolean  $list      True to allow use of lists
 * @param   boolean  $hideMenu  True to hide the menu on click
 *
 * @return  string  Button CSS Id
 *
 * @since   11.1
 */
public function fetchId($type = 'Standard', $name = '', $text = '', $task = '', $list = true, $hideMenu = false)
{
	return $this->_parent->getName() . '-' . $name;
}

/**
 * Get the JavaScript command for the button
 *
 * @param   string   $name  The task name as seen by the user
 * @param   string   $task  The task used by the application
 * @param   boolean  $list  True is requires a list confirmation.
 *
 * @return  string   JavaScript command string
 *
 * @since   11.1
 */
protected function _getCommand($name, $task, $list)
{
	JHtml::_('behavior.framework');
	$message = JText::_('JLIB_HTML_PLEASE_MAKE_A_SELECTION_FROM_THE_LIST');
	$message = addslashes($message);

	if ($list)
	{
		$cmd = "if (document.adminForm.boxchecked.value==0){alert('$message');}else{ Joomla.submitbutton('$task')}";
	}
	else
	{
		$cmd = "Joomla.submitbutton('$task')";
	}

	return $cmd;
}

}
?>