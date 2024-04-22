<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

// The user is not already attending -> display registration form.

if ($this->showRegForm && empty($this->print)) :

    if (($this->item->maxplaces > 0) && (($this->item->booked + $this->item->reservedplaces) >= $this->item->maxplaces) && !$this->item->waitinglist && empty($this->registration->status)) :
        ?>
        <?php echo Text::_( 'COM_JEM_EVENT_FULL_NOTICE' ); ?>

    <?php else :

        //USER
        $waitingPlacesUser = 0;
        $placesBookedUser = 0;
        $placesRegisteredUser = 0;
        $statusRegistrationUser = -1;

        if ($this->item->maxbookeduser != 0) {
            $placesavailableuser = $this->item->maxbookeduser;
            if ($this->registereduser !== null) {
                $placesavailableuser = $this->item->maxbookeduser - ($this->registers[$this->registereduser]->status > 0 ? $this->registers[$this->registereduser]->places : 0);
            } else if ($this->item->waitinglist && $this->registration != null) {
                if ($this->registration->status == 2) {
                    $placesavailableuser = $this->item->maxbookeduser - $this->registration->places;
                    $waitingPlacesUser = $this->registration->places;
                    $statusRegistrationUser = $this->registration->status;
                }
            }
        } else {
            $placesavailableuser = null;
        }

        //EVENT
        if ($this->item->maxplaces) {
            $placesavailableevent = $this->item->maxplaces - $this->item->booked - $this->item->reservedplaces;
            if ($placesavailableuser === null) {
                $placesavailableuser = $placesavailableevent;
            }
        } else {
            $placesavailableevent = false;
        }
        if ($placesavailableevent != false) {
            if ($placesavailableuser > 0 && ($placesavailableuser > $placesavailableevent)) {
                $placesavailableuser = $placesavailableevent;
            }
        }

        //BOOKED PLACES BY USER
        if ($this->registereduser !== null) {
            $statusRegistrationUser = $this->registers[$this->registereduser]->status;
            if ($statusRegistrationUser == 1) {
                $placesBookedUser = $this->registers[$this->registereduser]->places;
            } else {
                $placesBookedUser = 0;
            }
            $placesRegisteredUser = $this->registers[$this->registereduser]->places;
        }
        ?>

        <form id="JEM" action="<?php echo Route::_('index.php?option=com_jem&view=event&id=' . (int)$this->item->id); ?>"  name="adminForm" id="adminForm" method="post">
            <p>
                <?php
                if ($this->isregistered === false) {
                    if ($this->item->requestanswer) {
                        echo Text::_('COM_JEM_SEND_UNREGISTRATION');
                    }
                    echo Text::_('COM_JEM_YOU_ARE_UNREGISTERED');
                } else {
                    switch ($this->isregistered) :
                        case -1:
							//You are NOT attending
                            echo Text::_('COM_JEM_YOU_ARE_NOT_ATTENDING');
                            break;
                        case  0:
							//You're invited
                            echo Text::_('COM_JEM_YOU_ARE_INVITED');
                            break;
                        case  1:
							//You're attending
                            if ($this->allowAnnulation) {
                                echo Text::_('COM_JEM_YOU_ARE_ATTENDING');
                            } else {
                                echo substr(Text::_('COM_JEM_YOU_ARE_ATTENDING'), 0,strpos(Text::_('COM_JEM_YOU_ARE_ATTENDING'), "<br>"));
                            }
                            break;
                        case  2:
							//You're on Waitinglist
                            echo Text::_('COM_JEM_YOU_ARE_ON_WAITINGLIST');
                            break;
                        default:
							//You didn't answer!
                            echo Text::_('COM_JEM_YOU_ARE_UNREGISTERED');
                            break;
                    endswitch;
                }
                ?>
            </p>
            <p>
                <input type="radio" name="reg_check" value="1" onclick="check(this, document.getElementById('jem_send_attend'))"
                    <?php if ($this->isregistered !== false
                        && ($placesavailableevent === 0 || ($placesavailableuser === 0 && $statusRegistrationUser != 0))
                        && (!$this->item->waitinglist || ($this->item->waitinglist && ($placesBookedUser || $placesavailableuser === 0)))) {
                        echo 'disabled="disabled"';
                    } else {
                        echo 'checked="checked"';
                    } ?>
                />
                <i class="fa fa-check-circle-o fa-lg jem-registerbutton" aria-hidden="true"></i>
                <?php

                //FULL AND WAITLIST
                if ($this->item->maxplaces && (($this->item->booked + $this->item->reservedplaces) >= $this->item->maxplaces)) {
                    if ($this->item->waitinglist) {
                        if ($placesBookedUser) {
                            $placesavailableuser = 0;
                            echo Text::_('COM_JEM_EVENT_FULL_USER_REGISTERED_NO_WAITING_LIST');
                        } else {
                            echo Text::_('COM_JEM_EVENT_FULL_REGISTER_TO_WAITING_LIST');
                        }
                    } else {
                        if ($placesavailableevent === 0) {
                            echo Text::_('COM_JEM_NOT_AVAILABLE_PLACES_EVENT');
                            $placesavailableuser = 0;
                        }
                    }
                } else {
						//Option: I will attend
                    if ($this->registereduser !== null) {
                        if (!$placesBookedUser) {
                            echo Text::_('COM_JEM_I_WILL_GO');
                        }
                    } else {
                        echo Text::_('COM_JEM_I_WILL_GO');
                    }
                }
				// for this user no additional places
                if ($placesavailableuser === 0) { ?>
					<span class="badge bg-warning text-dark" role="alert">
                    <?php echo ' ' . Text::_('COM_JEM_NOT_AVAILABLE_PLACES_USER'); ?>
					</span>
				<?php
                } else {
						// Booking places
                    if ($this->item->maxbookeduser > 1) {
                        echo ' ' . Text::_('COM_JEM_I_WILL_GO_2');
                        echo ' <input id="addplaces" style="text-align: center; width:auto;" type="number" name="addplaces" '
                            . 'value="' . ($placesavailableuser > 0 ? ($this->item->maxbookeduser - $placesBookedUser < $placesavailableuser ? $this->item->minbookeduser - $placesBookedUser : 1) : ($placesavailableuser ?? 1))
                            . '" max="' . ($placesavailableuser > 0 ? ($this->item->maxbookeduser - $placesBookedUser < $placesavailableuser ? $this->item->maxbookeduser - $placesBookedUser : $placesavailableuser) : ($placesavailableuser ?? ''))
                            . '" min="' . ($placesavailableuser > 0 ? ($placesBookedUser - $this->item->minbookeduser >= 0 ? 1 : $this->item->minbookeduser - $placesBookedUser) : 0) . '">';
                        if ($this->registereduser != null) {
								//Places
                            if ($placesBookedUser && $statusRegistrationUser == 1) {
                                echo ' ' . Text::_('COM_JEM_I_WILL_GO_3');
                            } else {
								//Place
                                echo ' ' . Text::_('COM_JEM_PLACES_REG') . '.';
                            }
                        } else {
								//Place
                            if ($this->item->maxbookeduser == $placesavailableuser) {
                                echo ' ' . Text::_('COM_JEM_PLACES_REG') . '.';
                            } else {
								//Places
                                echo ' ' . Text::_('COM_JEM_I_WILL_GO_3');
                            }
                        }
                    } else {
                        echo ' <input id="addplaces" style="text-align: center; width:auto;" type="hidden" name="addplaces" value="1">';
                    }
                }
                ?>
            </p>
            <?php if ($this->item->requestanswer || $placesRegisteredUser || $waitingPlacesUser) {?>
            	<p>

                    <?php if ($this->allowAnnulation || ($this->isregistered != 1) || $waitingPlacesUser) : ?>
                        <input id="jem_unregister_event" type="radio" name="reg_check" value="-1" onclick="check(this, document.getElementById('jem_send_attend'));"
                            <?php if ($this->isregistered !== false && $statusRegistrationUser>0  && $placesavailableuser==0) { echo 'checked="checked"'; } ?>
                        />
                        <i class="fa fa-times-circle-o fa-lg jem-unregisterbutton" aria-hidden="true"></i>
                        <?php
						//Option: I don't attend
                        echo ' ' . Text::_('COM_JEM_I_WILL_NOT_GO');
                        if ($this->registereduser !== null || $waitingPlacesUser) {
                            if ($placesRegisteredUser || $waitingPlacesUser) {
                                if ($statusRegistrationUser == 1) {
									//Booked places..Booked place
                                    $cancelplaces = ($placesRegisteredUser - 1 > 1 ? Text::_('COM_JEM_BOOKED_PLACES') : Text::_('COM_JEM_BOOKED_PLACE'));
                                } else if ($statusRegistrationUser == -1) {
                                    $cancelplaces = '';
									//Booked places for invited users
                                } else if ($statusRegistrationUser == 0) {
                                    $cancelplaces = ($placesRegisteredUser - 1 > 1 ? Text::_('COM_JEM_INVITED_PLACES') : Text::_('COM_JEM_INVITED_PLACE'));
									//Booked places for waiting users
                                } else if ($statusRegistrationUser == 2) {
                                    $cancelplaces = ($waitingPlacesUser - 1 > 1 ? Text::_('COM_JEM_WAITING_PLACES') : Text::_('COM_JEM_WAITING_PLACE'));
                                }
									//Canceling...
                                echo ' ' . Text::_('COM_JEM_I_WILL_NOT_GO_2');
                                echo ' <input id="cancelplaces" style="text-align: center;" type="number" name="cancelplaces" value="' . ($placesRegisteredUser ? $placesRegisteredUser : $waitingPlacesUser) . '" max="' . ($placesRegisteredUser ? $placesRegisteredUser : $waitingPlacesUser) . '" min="1">' . ' ' . $cancelplaces;
                            }
                        } else {
								//...booked places
                            $cancelplaces = Text::_('COM_JEM_I_WILL_NOT_GO_3');
                        }
                        ?>
                        <?php else : 
							//Unregistration is not possible?>
                        <input type="radio" name="reg_dummy" value="" disabled="disabled" />
                        <?php echo ' '.Text::_('COM_JEM_NOT_ALLOWED_TO_ANNULATE'); ?>
                    <?php endif; ?>
            </p>
            <?php } 
			//Comment?>
            <?php if (!empty($this->jemsettings->regallowcomments)) : ?>
                <p><?php echo Text::_('COM_JEM_OPTIONAL_COMMENT') . ':'; ?></p>
                <p>
			<textarea class="inputbox" name="reg_comment" id="reg_comment" rows="3" cols="30" maxlength="255"
            ><?php if (is_object($this->registration) && !empty($this->registration->comment)) { echo $this->registration->comment; }
                /* looks crazy, but required to prevent unwanted white spaces within textarea content! */
                ?></textarea>
                </p>
            <?php endif; ?>
            <p>
                <input class="btn btn-sm btn-primary" type="submit" id="jem_send_attend" name="jem_send_attend" value="<?php echo ($placesRegisteredUser ? Text::_('COM_JEM_SEND_REGISTER') : Text::_('COM_JEM_REGISTER')); ?>"  />
            </p>

            <input type="hidden" name="rdid" value="<?php echo $this->item->did; ?>" />
            <input type="hidden" name="regid" value="<?php echo (is_object($this->registration) ? $this->registration->id : 0); ?>" />
            <input type="hidden" name="task" value="event.userregister"/>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    <?php
    endif; // full?

endif; // registra and not print
