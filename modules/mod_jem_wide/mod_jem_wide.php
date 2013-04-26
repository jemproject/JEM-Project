<?php
/**
 * @version 0.9 $Id$
 * @package Joomla
 * @subpackage EventList Wide Module
 * @copyright (C) 2005 - 2007 Christoph Lukes
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

// no direct access
defined('_JEXEC') or die('Restricted access');

// get module helper
require_once (dirname(__FILE__).DS.'helper.php');

//require needed component classes
require_once(JPATH_SITE.DS.'components'.DS.'com_eventlist'.DS.'helpers'.DS.'helper.php');
require_once(JPATH_SITE.DS.'components'.DS.'com_eventlist'.DS.'helpers'.DS.'route.php');
require_once (JPATH_SITE.DS.'components'.DS.'com_eventlist'.DS.'classes'.DS.'image.class.php');

$list = modEventListwideHelper::getList($params);

$document 	= & JFactory::getDocument();
$document->addStyleSheet(JURI::base(true).'/modules/mod_eventlist_wide/tmpl/mod_eventlist_wide.css');

// check if any results returned
$items = count($list);
if (!$items) {
	return;
}
require(JModuleHelper::getLayoutPath('mod_eventlist_wide'));