<?php
/**
 * @version 2.1.6
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * JEM settings table class
 *
 * @package JEM
 *
 * @deprecated since version 2.1.6
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
	var $tablewidth 		= null;
	/** @var string */
	var $datewidth 			= null;
	/** @var int */
	var $datemode 			= "1";
	/** @var string */
	var $titlewidth 		= null;
	/** @var string */
	var $infobuttonwidth 	= null;
	/** @var string */
	var $locationwidth 		= null;
	/** @var string */
	var $citywidth 			= null;
	/** @var string */
	var $formatdate 		= null;
	/** @var string */
	var $formatShortDate	= null;
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
	var $statewidth 		= null;
	/** @var int */
	var $regname			= null;
	/** @var int */
	var $storeip			= null;
	/** @var string */
	var $lastupdate 		= null;
	/** @var int */
	var $checked_out 		= 0;
	/** @var date */
	var $checked_out_time 	= 0;
	/** @var string */
	var $tld 	= 0;
	/** @var int */
	var $display_num		= 0;
	var $cat_num			= 0;
	var $filter				= 0;
	var $display			= 0;
	var $icons				= 0;
	var $show_print_icon	= 0;
	var $show_email_icon	= 0;
	var $events_ical		= 0;

	/** @var string */
	var $defaultCountry		= null;


	/**
	 * @deprecated since version 2.1.6
	 */
	public function __construct(& $db) {
		parent::__construct('#__jem_settings', 'id', $db);
	}
}
?>