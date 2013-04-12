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
 * Renders a JEM spacer element
 *
 * @package JEM
 * @since 1.1
 */

class JElementElspacer extends JElement
{
	/**
	* Element name
	*
	* @access	protected
	* @var		string
	*/
	var	$_name = 'Elspacer';

	function fetchTooltip($label, $description, &$node, $control_name, $name) {
		return '&nbsp;';
	}

	function fetchElement($name, $value, &$node, $control_name)
	{
		if ($value) {
			return '<div class="el-spacer">'.JText::_($value).'</div>';
		} else {
			return '<hr class="el-spacer"/>';
		}
	}
}
