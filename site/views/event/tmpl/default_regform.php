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
if ($this->row->registra == 1)
{

if ($this->print == 0) {


if ($this->row->maxplaces && count($this->registers) >= $this->row->maxplaces && !$this->row->waitinglist):
?>
	<p class="el-event-full">
		<?php echo JText::_('COM_JEM_EVENT_FULL_NOTICE'); ?>
	</p>
<?php else: ?>
<form id="JEM" action="<?php echo JRoute::_('index.php'); ?>" method="post">
	<p>
		<?php if ($this->row->maxplaces && count($this->registers) >= $this->row->maxplaces): // full event ?>
			<?php echo JText::_('COM_JEM_EVENT_FULL_REGISTER_TO_WAITING_LIST').': '; ?>
		<?php else: ?>
			<?php echo JText::_('COM_JEM_I_WILL_GO').': '; ?>
		<?php endif; ?>
		<input type="checkbox" name="reg_check" onclick="check(this, document.getElementById('jem_send_attend'))" />
	</p>
<p>
	<input class="button1" type="submit" id="jem_send_attend" name="jem_send_attend" value="<?php echo JText::_('COM_JEM_REGISTER'); ?>" disabled="disabled" />
</p>
<p>
	<input type="hidden" name="rdid" value="<?php echo $this->row->did; ?>" />
	<?php echo JHTML::_( 'form.token' ); ?>
	<input type="hidden" name="task" value="event.userregister" />
</p>
</form>
<?php endif;
}

}