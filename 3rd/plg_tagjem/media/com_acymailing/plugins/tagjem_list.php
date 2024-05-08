<?php
/**
 * @version    4.2.2
 * @JEM Tag Plugin for AcyMailing 5.x
 * @copyright  (C) 2014 Thamesmog.
 * @copyright  (C) 2013-2024 joomlaeventmanager.net. All rights reserved.
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 * Based on Eventlist tag and JEM specific code by JEM Community
 */
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

include_once(ACYMAILING_ROOT.'components/com_jem/helpers/route.php');

$result .= '<div class="acymailing_content">';
$result .= '<br/>';
$link = JemHelperRoute::getEventRoute($event->slug);
$result .= '<a href="'.acymailing_frontendLink($link).'" itemprop="url">';
$result .= '<h2><span itemprop="name">'.$event->title.'</span></h2></a>';
$result .= '<p>';
$result .= JemOutput::formatShortDateTime($event->dates, $event->times,
                                          $event->enddates, $event->endtimes);
//$result .= JemOutput::formatSchemaOrgDateTime($event->dates, $event->times,
//                                              $event->enddates, $event->endtimes);
$result .= '</p>';
//$result .= '<p>';
//$result .=  '<strong>Art der Veranstaltung:</strong> '.$event->custom1;

//$result .=  ' <strong>Organisiert durch:</strong> '.$event->custom2;
//$result .= '</p>';

/* Introtext */
//if (!empty($event->introtext)) {
//	$result .= '<p>';
//	$result .= '<br/>'.$event->introtext.'</p>';
//}

/* Veranstaltungsort */
if ($event->locid) {
//	$result .= '<div class="venue" style="display:block;float:left;width:200px;">';
	$result .= '<p>';
//	$result .= '<br/><strong>Ort:</strong> ';
	$link = JemHelperRoute::getVenueRoute($event->venueslug);
	$result .= $event->locid != 0 ? "<a href='".acymailing_frontendLink($link)."'>".$event->venue."</a>" : '';
/* Adresse */
//	$result .= !empty($event->street) ? '<br/>'.$event->street : '';
//	$result .= !empty($event->city) ? '<br/>'. $event->city : '';
//	$result .= !empty($event->postalcode) ? '<br/>'. $event->postalcode : '';
	$result .= '</p>';
//	$result .= '</div>';
}
/* Introtext */
//$result .= '<p>';
//$result .= '<br/>'.$event->introtext.'</p>';

/* Kontakt */
if (!empty($event->conname)) {
	//$result .= '<div class="contact" style="display:block;float:left;">';
	$result .= '<p>';
	$contact = $event->conname;
	$needle = 'index.php?option=com_contact&view=contact&id=' . $event->conid;
	$menu = JFactory::getApplication()->getMenu();
	$item = $menu->getItems('link', $needle, true);
	$cntlink2 = !empty($item) ? $needle . '&Itemid=' . $item->id : $needle;

	$result .= Text::_('PLG_TAGJEM_CONTACT').': <a href="'.$cntlink2.'">'.$contact.'</a>';
	if (!empty($event->conemail_to)) {
		$result .= '<br/><a href="mailto:'.$event->conemail_to.'">'.$event->conemail_to.'</a>';
	}
	//if (!empty($event->contelephone)) {
	//	$result .= '<br/>'.Text::_('PLG_TAGJEM_PHONE').': '.$event->contelephone;
	//}
	if (!empty($event->conmobile)) {
		$result .= '<br/>'.Text::_('PLG_TAGJEM_CELLPHONE').': '.$event->conmobile;
	}
	$result .= '</p>';
	//$result .= '</div>';
	}
//$result .= '<p>';
//$result .= $event->registra ? $event->registra : '-';
//$result .= '</p>';
$result .= '<hr style="clear:both"/>';
$result .= '</div>';
