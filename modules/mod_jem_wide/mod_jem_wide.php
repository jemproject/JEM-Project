<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @subpackage JEM Wide Module
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
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

// get module helper
require_once (dirname(__FILE__).'/helper.php');

//require needed component classes
require_once(JPATH_SITE.'/components/com_jem/helpers/helper.php');
require_once(JPATH_SITE.'/components/com_jem/helpers/route.php');
require_once(JPATH_SITE.'/components/com_jem/classes/image.class.php');

$list = modJEMwideHelper::getList($params);

$document 	=  JFactory::getDocument();
$document->addStyleSheet(JURI::base(true).'/modules/mod_jem_wide/tmpl/mod_jem_wide.css');

// check if any results returned
$items = count($list);
if (!$items) {
	return;
}
require(JModuleHelper::getLayoutPath('mod_jem_wide'));