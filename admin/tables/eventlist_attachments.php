<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
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
 * EventList attachments table class
 *
 * @package Joomla
 * @subpackage EventList
 * @since 1.1
 */
class eventlist_attachments extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id 				= null;
	/** @var int */
	var $file				= '';
	/** @var int */
	var $object				= '';
	/** @var string */
	var $name 		= null;
	/** @var string */
	var $description 		= null;
	/** @var string */
	var $icon 		= null;
	/** @var int */
	var $frontend		= 1;
	/** @var int */
	var $access 		= 0;
	/** @var int */
	var $ordering 		= 0;
	/** @var string */
	var $added 		= '';
	/** @var int */
	var $added_by 		= 0;

	function eventlist_attachments(& $db) {
		parent::__construct('#__eventlist_attachments', 'id', $db);
	}

	// overloaded check function
	function check()
	{
		return true;
	}
}
?>