<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2008 Christoph Lukes
 * @license GNU/GPL, see LICENCE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

defined('_JEXEC') or die('Restricted access');

/**
 * EventList table class
 *
 * @package Joomla
 * @subpackage EventList
 * @since 1.1
 */
class eventlist_cats_event_relations extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $catid 				= null;
	/**
	 * Primary Key
	 * @var int
	 */
	var $itemid				= null;
	/**
	 * Ordering
	 * @var int
	 * @todo implement
	 */
	var $ordering			= null;

	function eventlist_cats_event_relations(& $db) {
		parent::__construct('#__eventlist_cats_event_relations', 'catid', $db);
	}
	
	// overloaded check function
	function check()
	{
		return;
	}
}
?>