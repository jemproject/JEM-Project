<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;

/**
 * JEM settings table class
 *
 * @package JEM
 *
 * @deprecated since version 2.1.6
 */
class jem_settings extends Table
{
	/**
	 * Unique Key
	 * @var int
	 */
	public $id = '1';
	/** @var int */
	public $oldevent = '2';
	/** @var int */
	public $minus = '1';
	/** @var int */
	public $showtime = '0';
	/** @var int */
	public $showtitle = '1';
	/** @var int */
	public $showlocate = '1';
	/** @var int */
	public $showcity = '1';
	/** @var int */
	public $showmapserv = '0';
	/** @var string */
	public $tablewidth = null;
	/** @var string */
	public $datewidth = null;
	/** @var int */
	public $datemode = '1';
	/** @var string */
	public $titlewidth = null;
	/** @var string */
	public $infobuttonwidth = null;
	/** @var string */
	public $locationwidth = null;
	/** @var string */
	public $citywidth = null;
	/** @var string */
	public $formatdate = null;
	/** @var string */
	public $formatShortDate = null;
	/** @var string */
	public $formattime = null;
	/** @var string */
	public $timename = null;
	/** @var int */
	public $showdetails = '1';
	/** @var int */
	public $showtimedetails = '1';
	/** @var int */
	public $showevdescription = '1';
	/** @var int */
	public $showdetailstitle = '1';
	/** @var int */
	public $showdetailsadress = '1';
	/** @var int */
	public $showlocdescription = '1';
	/** @var int */
	public $showlinkvenue = '1';
	/** @var int */
	public $showdetlinkvenue = '1';
	/** @var int */
	public $delivereventsyes = '-2';
	/** @var int */
	public $datdesclimit = '1000';
	/** @var int */
	public $autopubl = '-2';
	/** @var int */
	public $deliverlocsyes = '-2';
	/** @var int */
	public $autopublocate = '-2';
	/** @var int */
	public $showcat = '0';
	/** @var int */
	public $catfrowidth = '';
	/** @var int */
	public $evdelrec = '1';
	/** @var int */
	public $evpubrec = '1';
	/** @var int */
	public $locdelrec = '1';
	/** @var int */
	public $locpubrec = '1';
	/** @var int */
	public $sizelimit = '100';
	/** @var int */
	public $imagehight = '100';
	/** @var int */
	public $imagewidth = '100';
	/** @var int */
	public $gddisabled = '0';
	/** @var int */
	public $imageenabled = '1';
	/** @var int */
	public $comunsolution = '0';
	/** @var int */
	public $comunoption = '0';
	/** @var int */
	public $catlinklist = '0';
	/** @var int */
	public $showfroregistra = '0';
	/** @var int */
	public $showfrounregistra = '0';
	/** @var int */
	public $eventedit = '-2';
	/** @var int */
	public $eventeditrec = '1';
	/** @var int */
	public $eventowner = '0';
	/** @var int */
	public $venueedit = '-2';
	/** @var int */
	public $venueeditrec = '1';
	/** @var int */
	public $venueowner = '0';
	/** @var int */
	public $lightbox = '0';
	/** @var string */
	public $meta_keywords = null;
	/** @var string */
	public $meta_description = null;
	/** @var int */
	public $showstate = '0';
	/** @var string */
	public $statewidth = null;
	/** @var int */
	public $regname = null;
	/** @var int */
	public $storeip = null;
	/** @var string */
	public $lastupdate = null;
	/** @var int */
	public $checked_out = null;
	/** @var date */
	public $checked_out_time = null;
	/** @var string */
	public $tld = 0;
	/** @var int */
	public $display_num = 0;
	public $cat_num = 0;
	public $filter = 0;
	public $display = 0;
	public $icons = 0;
	public $show_print_icon = 0;
	public $show_email_icon = 0;
	public $events_ical = 0;
	/** @var string */
	public $defaultCountry = null;


	/**
	 * @deprecated since version 2.1.6
	 */
	public function __construct(& $db)
	{
		parent::__construct('#__jem_settings', 'id', $db);
	}
}
?>
