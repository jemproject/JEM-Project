<?php
/**
 * @version 4.0b4
 * @package JEM
 * @copyright (C) 2013-2023 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

// The user is not already attending -> display registration form.

if ($this->showRegForm && empty($this->print)) :

	if (($this->item->maxplaces > 0) && (($this->item->booked + $this->item->reservedplaces) >= $this->item->maxplaces) && !$this->item->waitinglist && empty($this->registration->status)) :
	?>
	<?php echo Text::_( 'COM_JEM_EVENT_FULL_NOTICE' ); ?>

	<?php else :
		if($this->registereduser!==null) {
			$placesavailableuser = $this->item->maxbookeduser - $this->registers[$this->registereduser]->places;
		}else{
			$placesavailableuser = $this->item->maxbookeduser;
		}
		$placesavailableevent = $this->item->maxplaces - $this->item->booked - $this->item->reservedplaces;
        ?>

	<form id="JEM" action="<?php echo JRoute::_('index.php?option=com_jem&view=event&id=' . (int)$this->item->id); ?>"  name="adminForm" id="adminForm" method="post">
		<p>
			<?php
			if ($this->isregistered === false) :
				echo Text::_('COM_JEM_YOU_ARE_UNREGISTERED');
			else :
				switch ($this->isregistered) :
				case -1: echo Text::_('COM_JEM_YOU_ARE_NOT_ATTENDING');  break;
				case  0: echo Text::_('COM_JEM_YOU_ARE_INVITED');        break;
				case  1: echo Text::_('COM_JEM_YOU_ARE_ATTENDING');      break;
				case  2: echo Text::_('COM_JEM_YOU_ARE_ON_WAITINGLIST'); break;
				default: echo Text::_('COM_JEM_YOU_ARE_UNREGISTERED');   break;
				endswitch;
			endif;
			?>
		</p>
		<p>
			<input type="radio" name="reg_check" value="1" onclick="check(this, document.getElementById('jem_send_attend'))"
						<?php if ($this->isregistered >= 1 && $placesavailableevent) {
                            echo 'checked="checked"';
                        } else {
							echo 'disabled="disabled"';
                        } ?>
			/>
			<?php if ($this->item->maxplaces && (($this->item->booked + $this->item->reservedplaces) >= $this->item->maxplaces) && ($this->isregistered != 1)) : // full event ?>
				<?php echo ' '.Text::_('COM_JEM_EVENT_FULL_REGISTER_TO_WAITING_LIST'); ?>
			<?php else :

                if($placesavailableuser>0 && ($placesavailableuser > $placesavailableevent)){
					$placesavailableuser = $placesavailableevent;
                }
				if(!$this->registers[$this->registereduser]->places) {
					echo ' ' . Text::_('COM_JEM_I_WILL_GO');
				}
				if($placesavailableuser) {
					echo ' ' . Text::_('COM_JEM_I_WILL_GO_2');
					echo ' <input id="addplaces" style="text-align: center;" type="number" name="addplaces" value="' . $placesavailableuser . '" max="' . $placesavailableuser . '" min="0">';
                    if($this->registers[$this->registereduser]->places) {
						echo ' ' . Text::_('COM_JEM_I_WILL_GO_3');
					}else{
						echo ' ' . Text::_('COM_JEM_PLACES');
                    }
				}else{
                    echo ' ' . Text::_('COM_JEM_NOT_AVAILABLE_PLACES');
				}
				?>
			<?php endif; ?>
		</p>
		<p>
		<?php if ($this->allowAnnulation || ($this->isregistered != 1)) : ?>
			<input type="radio" name="reg_check" value="-1" onclick="check(this, document.getElementById('jem_send_attend'))"
				<?php if ($this->isregistered == -1) { echo 'checked="checked"'; } ?>
			/>
			<?php echo ' ' . Text::_('COM_JEM_I_WILL_NOT_GO');
			if($this->registereduser!==null) {
				if ($this->registers[$this->registereduser]->places) {
					echo ' ' . Text::_('COM_JEM_I_WILL_NOT_GO_2');
                    echo ' <input id="cancelplaces" style="text-align: center;" type="number" name="cancelplaces" value="' . $this->registers[$this->registereduser]->places . '" max="' . $this->registers[$this->registereduser]->places . '" min="1">' . ' ' . Text::_('COM_JEM_I_WILL_NOT_GO_3');
				}
			}
            ?>
		<?php else : ?>
			<input type="radio" name="reg_dummy" value="" disabled="disabled" />
			<?php echo ' '.Text::_('COM_JEM_NOT_ALLOWED_TO_ANNULATE'); ?>
		<?php endif; ?>
		</p>
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
			<input class="btn btn-sm btn-primary" type="submit" id="jem_send_attend" name="jem_send_attend" value="<?php echo Text::_('COM_JEM_REGISTER'); ?>" <?php echo (!$this->isregistered ? 'disabled="disabled"':'')?> />
		</p>
		<input type="hidden" name="rdid" value="<?php echo $this->item->did; ?>" />
		<input type="hidden" name="regid" value="<?php echo (is_object($this->registration) ? $this->registration->id : 0); ?>" />
		<input type="hidden" name="task" value="event.userregister" />
		<?php echo JHtml::_('form.token'); ?>
	</form>
	<?php
	endif; // full?

endif; // registra and not print
