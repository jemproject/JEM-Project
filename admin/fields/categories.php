<?php
/**
 * @version 1.1 $Id$
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

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die();

/**
 * Renders an Category element
 *
 * @package JEM
 * @since 0.9
 */

class JElementCategories extends JElement
{
   /**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
	var	$_name = 'Categories';

	function fetchElement($name, $value, &$node, $control_name)
	{
		$doc 		= JFactory::getDocument();
		$fieldName	= $control_name.'['.$name.']';

		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_jem'.DS.'tables');

		$category = JTable::getInstance('jem_categories', '');

		if ($value) {
			$category->load($value);
		} else {
			$category->catname = JText::_('COM_JEM_SELECT_CATEGORY');
		}

		$js = "
		function elSelectCategory(id, category) {
			document.getElementById('a_id').value = id;
			document.getElementById('a_name').value = category;
			document.getElementById('sbox-window').close();
		}
		
		function elCatReset() {
		  document.getElementById('a_id').value = 0;
      document.getElementById('a_name').value = '".htmlspecialchars(JText::_('COM_JEM_SELECT_CATEGORY'))."';
	  }
		";

		$link = 'index.php?option=com_jem&amp;view=categoryelement&amp;tmpl=component';
		$doc->addScriptDeclaration($js);

		JHTML::_('behavior.modal', 'a.modal');

		$html = "\n<div style=\"float: left;\"><input style=\"background: #ffffff;\" type=\"text\" id=\"a_name\" value=\"$category->catname\" disabled=\"disabled\" /></div>";
		$html .= "<div class=\"button2-left\"><div class=\"blank\"><a class=\"modal\" title=\"".JText::_('COM_JEM_SELECT')."\"  href=\"$link\" rel=\"{handler: 'iframe', size: {x: 650, y: 375}}\">".JText::_('COM_JEM_SELECT')."</a></div></div>\n";
    $html .= "<div class=\"button2-left\"><div class=\"blank\"><a title=\"".JText::_('COM_JEM_RESET')."\" onClick=\"elCatReset();return false;\" >".JText::_('COM_JEM_RESET')."</a></div></div>\n";
		$html .= "\n<input type=\"hidden\" id=\"a_id\" name=\"$fieldName\" value=\"$value\" />";

		return $html;
	}
}
?>