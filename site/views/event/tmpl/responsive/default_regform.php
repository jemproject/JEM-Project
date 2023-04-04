<?php
/**
 * @version 2.3.17
 * @package JEM
 * @copyright (C) 2013-2023 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

// The user is not already attending -> display registration form.

if ($this->showRegForm && empty($this->print)) :

	if (($this->item->maxplaces > 0) && ($this->item->booked >= $this->item->maxplaces) && !$this->item->waitinglist && empty($this->registration->status)) :
	?>
		<?php echo JText::_( 'COM_JEM_EVENT_FULL_NOTICE' ); ?>

	<?php else : ?>

	<form id="JEM" action="<?php echo JRoute::_('index.php?option=com_jem&view=event&id=' . (int)$this->item->id); ?>"  name="adminForm" id="adminForm" method="post">
		<p>
			<?php
			if ($this->isregistered === false) :
				echo JText::_('COM_JEM_YOU_ARE_UNREGISTERED');
			else :
				switch ($this->isregistered) :
				case -1: echo JText::_('COM_JEM_YOU_ARE_NOT_ATTENDING');  break;
				case  0: echo JText::_('COM_JEM_YOU_ARE_INVITED');        break;
				case  1: echo JText::_('COM_JEM_YOU_ARE_ATTENDING');      break;
				case  2: echo JText::_('COM_JEM_YOU_ARE_ON_WAITINGLIST'); break;
				default: echo JText::_('COM_JEM_YOU_ARE_UNREGISTERED');   break;
				endswitch;
			endif;
			?>
		</p>
    <ul class="eventlist">
      <li class="jem-event" onclick="document.getElementById('jem_register_event').click();">
        <input id="jem_register_event" type="radio" name="reg_check" value="1" onclick="check(this, document.getElementById('jem_send_attend'));"
          <?php if ($this->isregistered >= 1) { echo 'checked="checked"'; } ?>
        />
        <i class="fa fa-check-circle-o fa-lg jem-registerbutton" aria-hidden="true"></i>
        <?php if ($this->item->maxplaces && ($this->item->booked >= $this->item->maxplaces) && ($this->isregistered != 1)) : // full event ?>
          <?php echo ' '.JText::_('COM_JEM_EVENT_FULL_REGISTER_TO_WAITING_LIST'); ?>
        <?php else : ?>
          <?php echo ' '.JText::_('COM_JEM_I_WILL_GO'); ?>
        <?php endif; ?>
      </li>
      
      <li class="jem-event" onclick="document.getElementById('jem_unregister_event').click();">
      <?php if ($this->allowAnnulation || ($this->isregistered != 1)) : ?>
        <input id="jem_unregister_event" type="radio" name="reg_check" value="-1" onclick="check(this, document.getElementById('jem_send_attend'));"
          <?php if ($this->isregistered == -1) { echo 'checked="checked"'; } ?>
        />
        <i class="fa fa-times-circle-o fa-lg jem-unregisterbutton" aria-hidden="true"></i>
        <?php echo ' '.JText::_('COM_JEM_I_WILL_NOT_GO'); ?>
      <?php else : ?>
        <input type="radio" name="reg_dummy" value="" disabled="disabled" />
        <?php echo ' '.JText::_('COM_JEM_NOT_ALLOWED_TO_ANNULATE'); ?>
      <?php endif; ?>
      </li>
      
      <?php if (!empty($this->jemsettings->regallowcomments)) : ?>
        <li class="jem-event jem-nopointer jem-nohover">
          <p><?php echo JText::_('COM_JEM_OPTIONAL_COMMENT') . ':'; ?></p>
          <div class="jem-regcomment">
            <textarea class="inputbox" name="reg_comment" id="reg_comment" rows="3" cols="30" maxlength="255"
              ><?php if (is_object($this->registration) && !empty($this->registration->comment)) { echo $this->registration->comment; }
              /* looks crazy, but required to prevent unwanted white spaces within textarea content! */
            ?></textarea>
          </div>
        </li>      
      <?php endif; ?>
    </ul>
    <input class="btn btn-sm btn-primary" type="submit" id="jem_send_attend" name="jem_send_attend" value="<?php echo JText::_('COM_JEM_REGISTER'); ?>" disabled="disabled" /> 

	
		
		<input type="hidden" name="rdid" value="<?php echo $this->item->did; ?>" />
		<input type="hidden" name="regid" value="<?php echo (is_object($this->registration) ? $this->registration->id : 0); ?>" />
		<input type="hidden" name="task" value="event.userregister" />
		<?php echo JHtml::_('form.token'); ?>
	</form>
	<?php
	endif; // full?

endif; // registra and not print
