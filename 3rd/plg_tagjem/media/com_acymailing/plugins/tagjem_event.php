<?php
/**
 * @version    4.1.0
 * @JEM Tag Plugin for AcyMailing 5.x
 * @copyright  Copyright (C) 2014 Thamesmog.
 * @copyright  Copyright (C) 2013 - 2023 joomlaeventmanager.net. All rights reserved.
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 * Based on Eventlist tag and JEM specific code by JEM Community
 */
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

include_once(ACYMAILING_ROOT.'components/com_jem/helpers/route.php');

$result .= '<div class="acymailing_content" style="margin-top:12px">';
if (!empty($event->datimage)) {
	$imageFile = ACYMAILING_LIVE . $event->datimage;
	$result .= '<table cellspacing="5" cellpadding="0" border="0"><tr><td valign="top"><a style="text-decoration:none;border:0" target="_blank" href="'.$link.'" ><img src="'.$imageFile.'"/></a></td><td style="padding-left:5px" valign="top">';
} else {
	$result .= '<table cellspacing="5" cellpadding="0" border="0"><tr><td valign="top"></td><td style="padding-left:5px" valign="top">';
}
$result .= '<a style="text-decoration:none;" name="event-'.$event->id.'" target="_blank" href="'.$link.'"><h2 class="acymailing_title" style="margin-top:0">'.$event->title;
if (!empty($event->custom1)) {
	$result .= '<br/><em>'.$event->custom1.'</em>';
}
$result .= '</h2></a>';
$result .= '<p><span class="eventdate">'.$date.'</span></p>';
$result .= '<p>'.$event->venue.'</p>';

/* Kontakt */
if (!empty($event->conname)) {
//	$result .= '<div style="display:block;float:left;">';
	$result .= '<p>';
	$contact = $event->conname;
	$needle = 'index.php?option=com_contact&view=contact&id=' . $event->conid;
	$menu = JFactory::getApplication()->getMenu();
	$item = $menu->getItems('link', $needle, true);
	$cntlink2 = !empty($item) ? $needle . '&Itemid=' . $item->id : $needle;
	$result .= Text::_('PLG_TAGJEM_CONTACT').': <a href="'.$cntlink2.'">'.$contact.'</a>';
	//if (!empty($event->conemail_to)) {
	//	$result .= '<br/><a href="mailto:'.$event->conemail_to.'">'.$event->conemail_to.'</a>';
	//}
	//if (!empty($event->contelephone)) {
	//	$result .= '<br/>'.Text::_('PLG_TAGJEM_PHONE').': '.$event->contelephone;
	//}
	if (!empty($event->conmobile)) {
		$result .= '<br/>'.Text::_('PLG_TAGJEM_CELLPHONE').': '.$event->conmobile;
	}
	$result .= '</p>';
//	$result .= '</div>';
}
if (!empty($event->datimage)) {
	$result .= '</td></tr></table>';
} else {
	$result .= '</td></tr></table>';
}
$result .= '<hr style="clear:both"/>';
$result .= '</div>';
