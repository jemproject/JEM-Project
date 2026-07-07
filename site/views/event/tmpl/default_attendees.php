<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * @todo add check if CB does exists and if so perform action
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;

$linkreg = 'index.php?option=com_jem&amp;view=attendees&amp;id='.$this->item->id.($this->itemid ? '&Itemid='.$this->itemid : '');
$eventLayout = (string) $this->params->get('event_details_layout', '');
if ($eventLayout === '') {
    $eventLayout = (string) $this->settings->get('event_details_layout', 'details');
}
$layoutOverride = Factory::getApplication()->input->getCmd('jem_layout', '');
if (in_array($layoutOverride, array('details', 'compact'), true)) {
    $eventLayout = $layoutOverride;
}

$renderRegistrationLoginActions = function ($urlLogin, $urlRegister) {
    $allowUserRegistration = (bool) ComponentHelper::getParams('com_users')->get('allowUserRegistration', 0);
    $urlLogin = htmlspecialchars($urlLogin, ENT_QUOTES, 'UTF-8');
    $urlRegister = htmlspecialchars($urlRegister, ENT_QUOTES, 'UTF-8');

    echo '<div class="jem-registration-login-prompt">';
    echo '<p class="jem-registration-message">'
        . Text::_($allowUserRegistration ? 'COM_JEM_LOGIN_OR_CREATE_ACCOUNT_FOR_REGISTER' : 'COM_JEM_LOGIN_REQUIRED_FOR_REGISTER')
        . '</p>';
    echo '<div class="jem-registration-buttons">';
    echo '<button class="btn btn-sm btn-warning text-white px-4 py-2" type="button" onclick="location.href=\''
        . $urlLogin
        . '\'">'
        . Text::_('COM_JEM_LOGIN')
        . '</button>';

    if ($allowUserRegistration) {
        echo '<button class="btn btn-sm btn-secondary px-4 py-2" type="button" onclick="location.href=\''
            . $urlRegister
            . '\'">'
            . Text::_('COM_JEM_CREATE_ACCOUNT')
            . '</button>';
    }

    echo '</div>';
    echo '</div>';
};
$registrationIntro = trim((string) $this->item->params->get('registration_intro', ''));
$registrationFooter = trim((string) $this->item->params->get('registration_footer', ''));
?>

<div class="register<?php echo $eventLayout === 'compact' ? ' jem-registration-compact' : ''; ?>">
    <?php if ($registrationIntro !== '' && trim(strip_tags($registrationIntro)) !== '') : ?>
        <div class="jem-registration-text jem-registration-text-intro">
            <?php echo $registrationIntro; ?>
        </div>
    <?php endif; ?>

    <dl class="jem-dl floattext jem-registration-summary">
        <?php $maxplaces        = (int)$this->item->maxplaces; ?>
        <?php $reservedplaces   = (int)$this->item->reservedplaces; ?>
        <?php $minbookeduser    = (int)$this->item->minbookeduser; ?>
        <?php $maxbookeduser    = (int)$this->item->maxbookeduser; ?>
        <?php $booked           = (int)$this->item->booked; ?>
        <?php $waitinglist      = (int)$this->item->waitinglist; ?>
        <?php $seriesbooking    = (int)$this->item->seriesbooking; ?>
        <?php
        $this->registereduser = null;
        if ($this->registers) {
            foreach ($this->registers as $k => $register) {
                if ((int) $register->uid === (int) $this->user->id) {
                    $this->registereduser = $k;
                    break;
                }
            }
        }

        $isGuest = (bool) $this->user->get('guest');
        $canManageRegistration = !empty($this->permissions->canEditAttendees) || $this->user->authorise('core.manage', 'com_jem');
        $availableplaces = $maxplaces > 0 ? max(0, $maxplaces - $booked - $reservedplaces) : 0;
        ?>

        <?php if($this->settings->get('event_show_registration_counters','1')) : ?>
            <?php if ($maxplaces > 0) : ?>
                <?php if ($canManageRegistration) : ?>
                    <dt class="register max-places hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_MAX_PLACES'); ?>"><?php echo Text::_('COM_JEM_MAX_PLACES'); ?>:</dt>
                    <dd class="register max-places"><?php echo $maxplaces; ?></dd>
                    <dt class="register booked-places hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_RESERVED_PLACES'); ?>"><?php echo Text::_('COM_JEM_RESERVED_PLACES'); ?>:</dt>
                    <dd class="register booked-places"><?php echo $reservedplaces; ?></dd>
                    <dt class="register booked-places hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_BOOKED_PLACES'); ?>"><?php echo Text::_('COM_JEM_BOOKED_PLACES'); ?>:</dt>
                    <dd class="register booked-places"><?php echo $booked; ?></dd>
                    <?php if ($this->item->maxbookeduser > 0) : ?>
                        <dt><?php echo Text::_('COM_JEM_MAXIMUM_BOOKED_PLACES_PER_USER') ?>:</dt>
                        <dd><?php echo $this->item->maxbookeduser?></dd>
                    <?php endif; ?>
                <?php elseif (!$isGuest && $this->item->maxbookeduser > 0) : ?>
                    <dt><?php echo Text::_('COM_JEM_MAXIMUM_BOOKED_PLACES_PER_USER') ?>:</dt>
                    <dd><?php echo $this->item->maxbookeduser?></dd>
                <?php endif; ?>
                <dt class="register available-places hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_AVAILABLE_PLACES'); ?>"><?php echo Text::_('COM_JEM_AVAILABLE_PLACES'); ?>:</dt>
                <dd class="register available-places"><?php echo $availableplaces; ?></dd>
            <?php endif; ?>
            <?php if ($waitinglist > 0) : ?>
                <dt class="register waitinglist-places hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_WAITING_PLACES'); ?>"><?php echo Text::_('COM_JEM_WAITING_PLACES'); ?>:</dt>
                <dd class="register waitinglist-places"><?php echo $canManageRegistration ? $this->numWaitingPlaces : Text::_('COM_JEM_AVAILABLE'); ?></dd>
            <?php endif; ?>

        <?php endif; /* Not show counters registration */ ?>

        <?php
        $useCommunityBuilder = JemHelper::isCommunityBuilderEnabled() && ((int) $this->settings->get('event_comunsolution', '0') === 1);
        $showCommunityBuilderAvatar = $useCommunityBuilder && ((int) $this->settings->get('event_comunoption', '0') === 1);
        // only set style info if users already have registered for event and user is allowed to see it
        if ($this->registers) :
            $showAttendenenames = $this->settings->get('event_show_attendeenames', 2);
            switch ($showAttendenenames) {
                case 1: // show to admins
                    if (!$this->user->authorise('core.manage', 'com_jem')) {
                        $showAttendenenames = 0;
                    }
                    break;
                case 2: // show to registered
                    if ($this->user->get('guest')) {
                        $showAttendenenames = 0;
                    }
                    break;
                case 3: // show to all
                    break;
                case 4: // show only to user
                    break;
                case 0: // show to none
                default:
                    $showAttendenenames = 0;
            }
            if ($showAttendenenames) : ?>
            </dl>
            <hr/>
            <dl class="jem-dl floattext">

                <dt class="register registered-users hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_REGISTERED_USERS'); ?>"><?php echo Text::_('COM_JEM_REGISTERED_USERS'); ?>:</dt>
                <dd class="register registered-users">
                    <ul class="fa-ul jem-registered-list">
                        <?php
                        if ($useCommunityBuilder) :
                            if ($showCommunityBuilderAvatar) :
                                //$cparams = ComponentHelper::getParams('com_media');
                                //$imgpath = $cparams->get('image_path'); // mostly 'images'
                                $imgpath = 'images'; // CB does NOT respect path set in Media Manager, so we have to ignore this too
                                if (is_file(JPATH_ROOT . '/components/com_comprofiler/plugin/templates/default/images/avatar/tnnophoto_n.png')) {
                                    $noimg = 'components/com_comprofiler/plugin/templates/default/images/avatar/tnnophoto_n.png';
                                } elseif (is_file(JPATH_ROOT . '/components/com_comprofiler/images/english/tnnophoto.jpg')) {
                                    $noimg = 'components/com_comprofiler/images/english/tnnophoto.jpg';
                                } else {
                                    $noimg = '';
                                }
                            endif;
                        endif;

                        if(!function_exists("jem_getStatusIcon")) {
                            if ($this->settings->get('event_show_more_attendeedetails', '0')) {
                                function jem_getStatusIcon($status) {
                                    switch($status) {
                                        case 2:  // waiting list
                                            return ' <i class="fa fa-li fa-hourglass-half jem-attendance-status-fa-hourglass-half hasTooltip" title="'.Text::_('COM_JEM_ATTENDEES_ON_WAITINGLIST').'"></i>';
                                            break;
                                        case 1:  // attending
                                            return ' <i class="fa fa-li fa-check-circle jem-attendance-status-fa-check-circle hasTooltip" title="'.Text::_('COM_JEM_ATTENDEES_ATTENDING').'"></i>';
                                            break;
                                        case 0:  // invited
                                            return ' <i class="fa fa-li fa-question-circle jem-attendance-status-fa-question-circle hasTooltip" title="'.Text::_('COM_JEM_ATTENDEES_INVITED').'"></i>';
                                            break;
                                        case -1: // not attending
                                            return ' <i class="fa fa-li fa-times-circle jem-attendance-status-fa-times-circle hasTooltip" title="'.Text::_('COM_JEM_ATTENDEES_NOT_ATTENDING').'"></i>';
                                            break;
                                        default:
                                            return $status;
                                    }
                                }
                            } else {
                                function jem_getStatusIcon($status) {
                                    return ' <i class="fa fa-li fa-check-circle jem-attendance-status-fa-check-circle hasTooltip" title="'.Text::_('COM_JEM_ATTENDEES_ATTENDING').'"></i>';
                                }
                            }
                        }

                        foreach ($this->registers as $k => $register) :
                            if($showAttendenenames==4){
                                if($this->user->id != $register->uid){
                                    continue;
                                }
                            } else if ($showAttendenenames==2) {
                                if($register->status==2){
                                    continue;
                                }
                            }
                            echo '<li class="' . ($this->user->id==$register->uid? 'jem-registered-user-owner':'jem-registered-user') . '">' . jem_getStatusIcon($register->status);
                            $text = '';
                            $registedplaces = '';
                            // is a plugin catching this ?
                            if ($res = $this->dispatcher->triggerEvent('onAttendeeDisplay', array($register->uid, &$text))) :
                                echo $text;
                            endif;

                            //Registered user in the event
                            if($register->uid == $this->user->id) {
                                $this->registereduser = $k;
                            }
                            if($register->status==1 && $register->places>1){
                                $registedplaces =  ' + ' . $register->places-1 . ' '. ($register->places-1>1? Text::_('COM_JEM_BOOKED_PLACES'): Text::_('COM_JEM_BOOKED_PLACE'));
                            }else if($register->status==-1 && $register->places>1){
                                $registedplaces =  '';
                            }else if($register->status==0 && $register->places>1){
                                $registedplaces =  ' + ' . $register->places-1 . ' '. ($register->places-1>1? Text::_('COM_JEM_INVITED_PLACES'): Text::_('COM_JEM_INVITED_PLACE'));
                            }else if($register->status==2 && $register->places>1){
                                $registedplaces =  ' + ' . $register->places-1 . ' '. ($register->places-1>1? Text::_('COM_JEM_WAITING_PLACES'): Text::_('COM_JEM_WAITING_PLACE'));
                            }

                            // if CB
                            if ($useCommunityBuilder) :
                                $needle = 'index.php?option=com_comprofiler&view=userprofile';
                                $menu = Factory::getApplication()->getMenu();
                                $item = $menu->getItems('link', $needle, true);
                                $userId = isset($register->uid) ? (int)$register->uid : 0;
                                $cntlink = $needle . '&user=' . $userId;
                                if (!empty($item) && isset($item->id)) {
                                    $cntlink .= '&Itemid=' . $item->id;
                                }
                                if ($showCommunityBuilderAvatar) :
                                    // User has avatar
                                    if (!empty($register->avatar)) :
                                        if (is_file(JPATH_ROOT . '/' . $imgpath . '/comprofiler/tn' . $register->avatar)) {
                                            $useravatar = HTMLHelper::image($imgpath . '/comprofiler/tn' . $register->avatar, $register->name);
                                        } elseif (is_file(JPATH_ROOT . '/' . $imgpath . '/comprofiler/' . $register->avatar)) {
                                            $useravatar = HTMLHelper::image($imgpath . '/comprofiler/' . $register->avatar, $register->name);
                                        } else {
                                            $useravatar = empty($noimg) ? '' : HTMLHelper::image($noimg, $register->name);
                                        }
                                        echo '<a style="text-decoration: none;" href="' . Route::_($cntlink) . '" title = "' . Text::_('COM_JEM_SHOW_USER_PROFILE') . '">' . $useravatar . ' <span class="username">' . $register->name . '</span></a>' . $registedplaces;

                                    // User has no avatar
                                    else :
                                        $nouseravatar = empty($noimg) ? '' : HTMLHelper::image($noimg, $register->name);
                                        echo '<a style="text-decoration: none;" href="' . Route::_($cntlink) . '" title = "' . Text::_('COM_JEM_SHOW_USER_PROFILE') .'">' . $nouseravatar . ' <span class="username">' . $register->name . '</span></a>'. $registedplaces;
                                    endif;
                                else :
                                    // only show the username with link to profile
                                    echo '<span class="username"><a style="text-decoration: none;" href="' . Route::_($cntlink) . '">' . $register->name . '</a></span>' . $registedplaces;
                                endif;
                            // if CB end - if not CB than only name
                            else :
                                // no communitycomponent is set so only show the username
                                echo '<span class="username">' . $register->name . '</span>' . $registedplaces;
                            endif;

                            echo '</li>';
                            // end loop through attendees
                        endforeach;
                        ?>
                    </ul>
                </dd>
            <?php else : ?>
                <?php
                // get the postion in the register array for the user
                foreach ($this->registers as $k => $register) :
                    //Registered user in the event
                    if($register->uid == $this->user->id) {
                        $this->registereduser = $k;
                        break;
                    }
                endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($this->permissions->canEditAttendees) : ?>
            <dt></dt>
            <dd><a href="<?php echo $linkreg; ?>" title="<?php echo Text::_('COM_JEM_MYEVENT_MANAGEATTENDEES'); ?>"><?php echo Text::_('COM_JEM_MYEVENT_MANAGEATTENDEES') ?> <i class="icon-out-2" aria-hidden="true"></i></a></dd>
        <?php endif; ?>
    </dl>
    <?php if ($eventLayout !== 'compact') : ?>
        <hr />
    <?php endif; ?>

    <?php if ($this->print == 0) : ?>
        <dl class="jem-dl floattext jem-registration-action">
            <?php $registrationLabel = $isGuest ? Text::_('COM_JEM_REGISTER') : Text::_('COM_JEM_YOUR_REGISTRATION'); ?>
            <dt class="register registration jem-registration-action-label hasTooltip" data-original-title="<?php echo $registrationLabel; ?>"><?php echo $registrationLabel; ?>:</dt>
            <dd class="register registration">
                <?php
                $uri = Uri::getInstance();
                $returnUrl = $uri->toString();
                $urlLogin   = Route::_($uri->root() . 'index.php?option=com_users&view=login&return='.base64_encode($returnUrl));
                $urlRegister = Route::_('index.php?option=com_users&view=registration');

                if ($this->item->published != 1) {
                    echo Text::_('COM_JEM_WRONG_STATE_FOR_REGISTER');
                } elseif (!$this->showRegForm) {
                    if (!$this->user->id) {
                        $renderRegistrationLoginActions($urlLogin, $urlRegister);
                    } else {
                        echo Text::_('COM_JEM_NOT_ALLOWED_TO_REGISTER');
                    }
                } else {
                    switch ($this->formhandler) {
                        case 0:
                            echo Text::_('COM_JEM_TOO_LATE_UNREGISTER');
                            break;
                        case 1:
                            echo Text::_('COM_JEM_TOO_LATE_REGISTER');
                            break;
                        case 2:
                            if ($this->item->requestanswer) { ?>
                                <span class="badge rounded-pill text-light bg-secondary">
                                    <?php echo Text::_('COM_JEM_SEND_UNREGISTRATION');?>
                                </span>
                            <?php } ?>

                            <?php $renderRegistrationLoginActions($urlLogin, $urlRegister); ?>
                            <?php //insert Breezing Form hack here
                            /*<input class="btn btn-secondary" type="button" value="<?php echo Text::_('COM_JEM_SIGNUPHERE_AS_GUEST'); ?>" onClick="window.location='/index.php?option=com_breezingforms&view=form&Itemid=6089&event=<?php echo $this->item->title; ?>&date=<?php echo $this->item->dates ?>&conemail=<?php echo $this->item->conemail ?>';"/>
                            */?>
                            <?php
                            break;
                        case 3:
                            if($this->item->reginvitedonly == 1){
                                if($this->isregistered === 0){
                                    echo $this->loadTemplate('regform');
                                }  else {
                                    echo Text::_('COM_JEM_INVITED_USERS_ONLY') . '.<br>' . Text::_('COM_JEM_NOT_INVITED') . '.';
                                }
                            }
                            break;
                        case 4:
                        case 5:
                            echo $this->loadTemplate('regform');
                            break;
                    }
                }
                ?>
            </dd>
        </dl>
    <?php endif; ?>

    <?php if ($registrationFooter !== '' && trim(strip_tags($registrationFooter)) !== '') : ?>
        <div class="jem-registration-text jem-registration-text-footer">
            <?php echo $registrationFooter; ?>
        </div>
    <?php endif; ?>
</div>
