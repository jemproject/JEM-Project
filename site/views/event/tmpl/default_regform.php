<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

//the user is not registered allready -> display registration form
?>
<?php
if ($this->item->registra == 1)
{

if ($this->print == 0) {


if ($this->item->maxplaces && count($this->registers) >= $this->item->maxplaces && !$this->item->waitinglist):
?>
	<p class="el-event-full">
		<?php echo JText::_( 'COM_JEM_EVENT_FULL_NOTICE' ); ?>
	</p>
<?php else: ?>
<form id="JEM" action="<?php echo JRoute::_('index.php?option=com_jem&view=event&id='.(int) $this->item->id); ?>"  name="adminForm" id="adminForm" method="post">
	<p>
		<?php if ($this->item->maxplaces && count($this->registers) >= $this->item->maxplaces): // full event ?>
			<?php echo JText::_( 'COM_JEM_EVENT_FULL_REGISTER_TO_WAITING_LIST' ).': '; ?>
		<?php else: ?>
			<?php echo JText::_( 'COM_JEM_I_WILL_GO' ).': '; ?>
		<?php endif; ?>
		<input type="checkbox" name="reg_check" onclick="check(this, document.getElementById('jem_send_attend'))" />
	</p>
<p>
	<input class="button1" type="submit" id="jem_send_attend" name="jem_send_attend" value="<?php echo JText::_( 'COM_JEM_REGISTER' ); ?>" disabled="disabled" />
</p>
<p>
	<input type="hidden" name="rdid" value="<?php echo $this->item->did; ?>" />
	<?php echo JHtml::_( 'form.token' ); ?>
	<input type="hidden" name="task" value="event.userregister" />
</p>
</form>
<?php endif;
}

}