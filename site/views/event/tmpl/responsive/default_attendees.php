<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * @todo add check if CB does exists and if so perform action
 */

defined('_JEXEC') or die;

use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Component\ComponentHelper;

$linkreg = 'index.php?option=com_jem&amp;view=attendees&amp;id='.$this->item->id.($this->itemid ? '&Itemid='.$this->itemid : '');
?>

<div class="register">
	<dl class="jem-dl floattext">
    <?php $maxplaces      = (int)$this->item->maxplaces; ?>
    <?php $reservedplaces = (int)$this->item->reservedplaces; ?>
    <?php $minbookeduser  = (int)$this->item->minbookeduser; ?>
    <?php $maxbookeduser  = (int)$this->item->maxbookeduser; ?>
    <?php $booked         = (int)$this->item->booked; ?>
    <?php $waitinglist    = (int)$this->item->waitinglist; ?>

    <?php if($this->settings->get('event_show_registration_counters','1')) : ?>
        <?php if ($maxplaces > 0) : ?>
            <dt class="register max-places hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_MAX_PLACES'); ?>"><?php echo Text::_('COM_JEM_MAX_PLACES'); ?>:</dt>
            <dd class="register max-places"><?php echo $maxplaces; ?></dd>
        <?php endif; ?>
        <?php if (($maxplaces > 0) || ($reservedplaces > 0)) : ?>
            <dt class="register booked-places hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_RESERVED_PLACES'); ?>"><?php echo Text::_('COM_JEM_RESERVED_PLACES'); ?>:</dt>
            <dd class="register booked-places">
                <?php echo $reservedplaces; ?>
            </dd>
        <?php endif; ?>
        <?php if ($maxplaces > 0) : ?>
            <dt class="register booked-places hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_BOOKED_PLACES'); ?>"><?php echo Text::_('COM_JEM_BOOKED_PLACES'); ?>:</dt>
            <dd class="register booked-places"><?php echo $booked; ?></dd>
        <?php endif; ?>
        <?php if ($this->item->maxbookeduser > 0) : ?>
                <dt><?php echo Text::_('COM_JEM_MAXIMUM_BOOKED_PLACES_PER_USER') ?>:</dt>
                <dd><?php echo $this->item->maxbookeduser?></dd>
        <?php endif; ?>
        <?php if ($maxplaces > 0) : ?>
            <dt class="register available-places hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_AVAILABLE_PLACES'); ?>"><?php echo Text::_('COM_JEM_AVAILABLE_PLACES'); ?>:</dt>
            <dd class="register available-places"><?php echo ($maxplaces - $booked - $reservedplaces); ?></dd>
        <?php endif; ?>
        <?php if ($waitinglist > 0) : ?>
            <dt class="register waitinglist-places hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_WAITING_PLACES'); ?>"><?php echo Text::_('COM_JEM_WAITING_PLACES'); ?>:</dt>
            <dd class="register waitinglist-places"><?php echo $this->numWaitingPlaces; ?></dd>
        <?php endif; ?>
    <?php endif; /* Not show counters registration */ ?>

		<?php
		$this->registereduser = null;
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
                <hr/>

            <dt class="register registered-users hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_REGISTERED_USERS'); ?>"><?php echo Text::_('COM_JEM_REGISTERED_USERS'); ?>:</dt>
            <dd class="register registered-users">
                <ul class="fa-ul jem-registered-list">
					<?php
					if ($this->settings->get('event_comunsolution', '0') == 1) :
						if ($this->settings->get('event_comunoption', '0') == 1) :
							//$cparams = ComponentHelper::getParams('com_media');
							//$imgpath = $cparams->get('image_path'); // mostly 'images'
							$imgpath = 'images'; // CB does NOT respect path set in Media Manager, so we have to ignore this too
							if (File::exists(JPATH_ROOT . '/components/com_comprofiler/plugin/templates/default/images/avatar/tnnophoto_n.png')) {
								$noimg = 'components/com_comprofiler/plugin/templates/default/images/avatar/tnnophoto_n.png';
							} elseif (File::exists(JPATH_ROOT . '/components/com_comprofiler/images/english/tnnophoto.jpg')) {
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
				if ($this->settings->get('event_comunsolution', '0') == 1) :
					if ($this->settings->get('event_comunoption', '0') == 1) :
						// User has avatar
						if (!empty($register->avatar)) :
							if (File::exists(JPATH_ROOT . '/' . $imgpath . '/comprofiler/tn' . $register->avatar)) {
								$useravatar = HTMLHelper::image($imgpath . '/comprofiler/tn' . $register->avatar, $register->name);
							} elseif (File::exists(JPATH_ROOT . '/' . $imgpath . '/comprofiler/' . $register->avatar)) {
								$useravatar = HTMLHelper::image($imgpath . '/comprofiler/' . $register->avatar, $register->name);
							} else {
								$useravatar = empty($noimg) ? '' : HTMLHelper::image($noimg, $register->name);
							}
							echo '<a href="' . Route::_('index.php?option=com_comprofiler&task=userProfile&user=' . $register->uid) . '" title = "' . Text::_('COM_JEM_SHOW_USER_PROFILE') . '">' . $useravatar . ' <span class="username">' . $register->name . '</span></a>' . $registedplaces;

						// User has no avatar
						else :
							$nouseravatar = empty($noimg) ? '' : HTMLHelper::image($noimg, $register->name);
							echo '<a href="' . Route::_('index.php?option=com_comprofiler&task=userProfile&user=' . $register->uid) . '" title = "' . Text::_('COM_JEM_SHOW_USER_PROFILE') .'">' . $nouseravatar . ' <span class="username">' . $register->name . '</span></a>'. $registedplaces;
						endif;
					else :
						// only show the username with link to profile
						echo '<span class="username"><a href="' . Route::_('index.php?option=com_comprofiler&amp;task=userProfile&amp;user=' . $register->uid) . '">' . $register->name . '</a></span>' . $registedplaces;
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
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($this->permissions->canEditAttendees) : ?>
            <dt style="padding: 0;"></dt>
            <dd><a href="<?php echo $linkreg; ?>" title="<?php echo Text::_('COM_JEM_MYEVENT_MANAGEATTENDEES'); ?>"><?php echo Text::_('COM_JEM_MYEVENT_MANAGEATTENDEES') ?> <i class="icon-out-2" aria-hidden="true"></i></a></dd>
        <?php endif; ?>
    </dl>
	<hr />

	<?php if ($this->print == 0) : ?>
	<dl class="jem-dl floattext">
		<dt class="register registration hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_YOUR_REGISTRATION'); ?>"><?php echo Text::_('COM_JEM_YOUR_REGISTRATION'); ?>:</dt>
		<dd class="register registration">
			<?php
			if ($this->item->published != 1) {
				echo Text::_('COM_JEM_WRONG_STATE_FOR_REGISTER');
			} elseif (!$this->showRegForm) {
				echo Text::_('COM_JEM_NOT_ALLOWED_TO_REGISTER');
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
                <span class="badge bg-warning text-dark">
									<?php echo Text::_('COM_JEM_SEND_UNREGISTRATION');?>
						</span>
                <?php
                }
                $uri = Uri::getInstance();
                    $returnUrl = $uri->toString();
                    $urlLogin   = 'index.php?option=com_users&view=login&return='.base64_encode($returnUrl); ?>
                            <button class="btn btn-sm btn-warning" onclick="location.href='<?php echo $uri->root() . $urlLogin; ?>'"
                                    type="button"><?php echo Text::_('COM_JEM_LOGIN_FOR_REGISTER'); ?></button>

					<?php //insert Breezing Form hack here
					/*<input class="btn btn-secondary" type="button" value="<?php echo Text::_('COM_JEM_SIGNUPHERE_AS_GUEST'); ?>" onClick="window.location='/index.php?option=com_breezingforms&view=form&Itemid=6089&event=<?php echo $this->item->title; ?>&date=<?php echo $this->item->dates ?>&conemail=<?php echo $this->item->conemail ?>';"/>
					*/?>
                <?php
					break;
				case 3:
				case 4:
					echo $this->loadTemplate('regform');
					break;
				}
			}
			?>
		</dd>
	</dl>
	<?php endif; ?>
</div>
