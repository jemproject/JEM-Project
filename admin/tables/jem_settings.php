<?php
/**
 * $Id$
 * @package Joomla
 * @subpackage Eventlist
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
 * Eventlist is maintained by the community located at
 * http://www.joomlaeventmanager.net
 *
 * Eventlist is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * Eventlist is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

defined('_JEXEC') or die;

/**
 * EventList settings table class
 *
 * @package Joomla
 * @subpackage EventList
 * @since 0.9
 */
class jem_settings extends JTable
{
	/**
	 * Unique Key
	 * @var int
	 */
	var $id					= "1";
	/** @var int */
	var $oldevent 			= "2";
	/** @var int */
	var $minus 				= "1";
	/** @var int */
	var $showtime 			= "0";
	/** @var int */
	var $showtitle 			= "1";
	/** @var int */
	var $showlocate 		= "1";
	/** @var int */
	var $showcity 			= "1";
	/** @var int */
	var $showmapserv 		= "0";
	/** @var string */
	var $map24id 			= null;
	/** @var int */
	var $gmapkey	 		= null;
	/** @var string */
	var $tablewidth 		= null;
	/** @var string */
	var $datewidth 			= null;
	/** @var string */
	var $titlewidth 		= null;
	/** @var string */
	var $infobuttonwidth 	= null;
	/** @var string */
	var $locationwidth 		= null;
	/** @var string */
	var $citywidth 			= null;
	/** @var string */
	var $datename 			= null;
	/** @var string */
	var $titlename 			= null;
	/** @var string */
	var $infobuttonname 	= null;
	/** @var string */
	var $locationname 		= null;
	/** @var string */
	var $cityname 			= null;
	/** @var string */
	var $formatdate 		= null;
	/** @var string */
	var $formattime 		= null;
	/** @var string */
	var $timename 			= null;
	/** @var int */
	var $showdetails 		= "1";
	/** @var int */
	var $showtimedetails 	= "1";
	/** @var int */
	var $showevdescription 	= "1";
	/** @var int */
	var $showdetailstitle 	= "1";
	/** @var int */
	var $showdetailsadress 	= "1";
	/** @var int */
	var $showlocdescription = "1";
	/** @var int */
	var $showlinkvenue 		= "1";
	/** @var int */
	var $showdetlinkvenue 	= "1";
	/** @var int */
	var $delivereventsyes 	= "-2";
	/** @var int */
	var $mailinform 		= "0";
	/** @var string */
	var $mailinformrec 		= null;
	/** @var string */
	var $mailinformuser 	= "0";
	/** @var int */
	var $datdesclimit 		= "1000";
	/** @var int */
	var $autopubl 			= "-2";
	/** @var int */
	var $deliverlocsyes 	= "-2";
	/** @var int */
	var $autopublocate 		= "-2";
	/** @var int */
	var $showcat 			= "0";
	/** @var int */
	var $catfrowidth 		= "";
	/** @var string */
	var $catfroname 		= null;
	/** @var int */
	var $evdelrec 			= "1";
	/** @var int */
	var $evpubrec 			= "1";
	/** @var int */
	var $locdelrec 			= "1";
	/** @var int */
	var $locpubrec 			= "1";
	/** @var int */
	var $sizelimit 			= "100";
	/** @var int */
	var $imagehight 		= "100";
	/** @var int */
	var $imagewidth 		= "100";
	/** @var int */
	var $gddisabled 		= "0";
	/** @var int */
	var $imageenabled 		= "1";
	/** @var int */
	var $comunsolution 		= "0";
	/** @var int */
	var $comunoption 		= "0";
	/** @var int */
	var $catlinklist 		= "0";
	/** @var int */
	var $showfroregistra 	= "0";
	/** @var int */
	var $showfrounregistra 	= "0";
	/** @var int */
	var $eventedit 			= "-2";
	/** @var int */
	var $eventeditrec 		= "1";
	/** @var int */
	var $eventowner 		= "0";
	/** @var int */
	var $venueedit 			= "-2";
	/** @var int */
	var $venueeditrec 		= "1";
	/** @var int */
	var $venueowner 		= "0";
	/** @var int */
	var $lightbox 			= "0";
	/** @var string */
	var $meta_keywords 		= null;
	/** @var string */
	var $meta_description 	= null;
	/** @var int */
	var $showstate 			= "0";
	/** @var string */
	var $statename 			= null;
	/** @var string */
	var $statewidth 		= null;
	/** @var int */
	var $regname	 		= null;
	/** @var int */
	var $storeip	 		= null;
	/** @var int */
	var $commentsystem		= 0;
	/** @var string */
	var $lastupdate 		= null;
	/** @var int */
	var $checked_out 		= 0;
	/** @var date */
	var $checked_out_time 	= 0;
	/** @var string */
	var $tld 	= 0;
	/**  */
    var $icslimit           = 0;
	/** @var int */
	
	 var $display_num    = 0;
    var $cat_num    = 0;
    var $filter    = 0;
var $display    = 0;
var $icons    = 0;
var $show_print_icon    = 0;
var $show_email_icon    = 0;
var $events_ical    = 0;

	
	
	
	

	function jem_settings(& $db) {
		parent::__construct('#__jem_settings', 'id', $db);
	}
}
?>