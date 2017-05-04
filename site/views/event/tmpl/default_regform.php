<?php
/**
 * @version 2.2.1
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

// The user is not already attending -> display registration form.

if ($this->showRegForm && empty($this->print)) :

	if (($this->item->maxplaces > 0) && ($this->item->booked >= $this->item->maxplaces) && !$this->item->waitinglist && empty($this->registration->status)) :
	?>
	<p class="el-event-full">
		<?php echo JText::_( 'COM_JEM_EVENT_FULL_NOTICE' ); ?>
	</p>

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
		<p>
			<input type="radio" name="reg_check" value="1" onclick="check(this, document.getElementById('jem_send_attend'))"
				<?php if ($this->isregistered >= 1) { echo 'checked="checked"'; } ?>
			/>
			<?php if ($this->item->maxplaces && ($this->item->booked >= $this->item->maxplaces) && ($this->isregistered != 1)) : // full event ?>
				<?php echo ' '.JText::_('COM_JEM_EVENT_FULL_REGISTER_TO_WAITING_LIST'); ?>
			<?php else : ?>
				<?php echo ' '.JText::_('COM_JEM_I_WILL_GO'); ?>
			<?php endif; ?>
		</p>
		<p>
		<?php if ($this->allowAnnulation || ($this->isregistered != 1)) : ?>
			<input type="radio" name="reg_check" value="-1" onclick="check(this, document.getElementById('jem_send_attend'))"
				<?php if ($this->isregistered == -1) { echo 'checked="checked"'; } ?>
			/>
			<?php echo ' '.JText::_('COM_JEM_I_WILL_NOT_GO'); ?>
		<?php else : ?>
			<input type="radio" name="reg_dummy" value="" disabled="disabled" />
			<?php echo ' '.JText::_('COM_JEM_NOT_ALLOWED_TO_ANNULATE'); ?>
		<?php endif; ?>
		</p>
		<?php if (!empty($this->jemsettings->regallowcomments)) : ?>
		<p><?php echo JText::_('COM_JEM_OPTIONAL_COMMENT') . ':'; ?></p>
		<p>
			<textarea class="inputbox" name="reg_comment" id="reg_comment" rows="3" cols="30" maxlength="255"
				><?php if (is_object($this->registration) && !empty($this->registration->comment)) { echo $this->registration->comment; }
				/* looks crazy, but required to prevent unwanted white spaces within textarea content! */
			?></textarea>
		</p>
		<?php endif; ?>
		<p>
			<input class="button1" type="submit" id="jem_send_attend" name="jem_send_attend" value="<?php echo JText::_('COM_JEM_REGISTER'); ?>" disabled="disabled" />
		</p>
		<input type="hidden" name="rdid" value="<?php echo $this->item->did; ?>" />
		<input type="hidden" name="regid" value="<?php echo (is_object($this->registration) ? $this->registration->id : 0); ?>" />
		<input type="hidden" name="task" value="event.userregister" />
		<?php echo JHtml::_('form.token'); ?>
	</form>
	<?php
	endif; // full?

endif; // registra and not print
