<?php
/**
 * @version    4.2.0
 * @JEM Tag Plugin for AcyMailing 5.x
 * @copyright  (C) 2014 Thamesmog.
 * @copyright  (C) 2013 - 2023 joomlaeventmanager.net. All rights reserved.
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 * Based on Eventlist tag and JEM specific code by JEM Community
 */
defined('_JEXEC') or die;

include_once(ACYMAILING_ROOT.'components/com_jem/helpers/route.php');

$result .= '<div class="acymailing_content">';
$result .= '<p>';
$result .= JemOutput::formatShortDateTime($event->dates, $event->times,
                                          $event->enddates, $event->endtimes);
$result .= JemOutput::formatSchemaOrgDateTime($event->dates, $event->times,
                                              $event->enddates, $event->endtimes);
$result .= '</p>';

$link = JemHelperRoute::getEventRoute($event->slug);
$result .= '<a href="'.acymailing_frontendLink($link).'" itemprop="url">';
$result .= '<h2><span itemprop="name">'.$event->title.'</span></h2></a>';

//$result .= '<p>';
//$link = JemHelperRoute::getEventRoute($event->slug);
//$result .= '<a href="'.acymailing_frontendLink($link).'" itemprop="url">';
//$result .= '<span itemprop="name">'.$event->title.'</span></a>';
//$result .= '<p>';
//$result .=  '<strong>event type:</strong> '.$event->custom1;
//
//$result .=  ' <strong>Event Organised By:</strong> '.$event->custom2;
//$result .= '</p>';
//$result .= '<p>';
//
//$result .= '<br/>'.$event->introtext.'</p>';
//if ( $event->locid ) {
//	$result .= '<div class="venue" style="display:block;float:left;width:200px;">';
//	$result .= '<p>';
//	$result .= '<br/><strong>Venue:</strong> ';
//	$result .='&nbsp;';
//	$link = JemHelperRoute::getVenueRoute($event->venueslug);
//	$result .= $event->locid != 0 ? "<a href='".acymailing_frontendLink($link)."'>".$event->venue."</a>" : '';
//	$result .= !empty($event->street) ? '<br/>'.$event->street : '';
//	$result .= !empty($event->city) ? '<br/>'. $event->city : '';
//	$result .= !empty($event->postalcode) ? '<br/>'. $event->postalcode : '';
//	$result .= '</p>';
//	$result .= '</div>';
//}
//$result .= '<div class="contact" style="display:block;float:left;">';
//$result .= '<p>';
//$contact = $event->conname;
//$needle = 'index.php?option=com_contact&view=contact&id=' . $event->conid;
//$menu = JFactory::getApplication()->getMenu();
//$item = $menu->getItems('link', $needle, true);
//$cntlink2 = !empty($item) ? $needle . '&Itemid=' . $item->id : $needle;
//
//$result .= '<br/><strong>Contact: </strong><a href="'.$cntlink2.'">'.$contact.'</a>';
//if (!empty($event->conemail_to)) {
//	$result .= '<br/><a href="mailto:'.$event->conemail_to.'">'.$event->conemail_to.'</a>';
//}
//if (!empty($event->contelephone)) {
//	$result .= '<br/>tel:'.$event->contelephone;
//}
//if (!empty($event->conmobile)) {
//	$result .= '<br/>mob:'.$event->conmobile;
//}
//$result .= '</p>';
//$result .= '</div>';
////$result .= '<p>';
////$result .= $event->registra ? $event->registra : '-';
////$result .= '</p>';
$result .= '<hr style="clear:both"/>';
$result .= '</div>';
