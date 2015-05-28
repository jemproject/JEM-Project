<?php
/**
 * @version 2.1.4
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

JHtml::_('behavior.modal', 'a.usermodal');

$selectuser_link = JRoute::_('index.php?option=com_jem&task=attendee.selectuser&tmpl=component');
?>

<script type="text/javascript">

	function modalSelectUser(id, username)
	{
		$('uid').value = id;
		$('username').value = username;
		window.parent.SqueezeBox.close();
	}

	Joomla.submitbutton = function(task)
	{
		if (task == 'attendee.cancel' || document.formvalidator.isValid(document.id('adminForm'))) {
			if (task == 'attendee.cancel' || document.getElementById('adminForm').uid.value != 0) {
				Joomla.submitform(task, document.getElementById('adminForm'));
			} else {
				alert("<?php echo JText::_('COM_JEM_SELECT_AN_USER', true); ?>");
				return false;
			}
		} else {
			alert('<?php echo $this->escape(JText::_('JGLOBAL_VALIDATION_FORM_FAILED'));?>');
		}
	}
</script>


<form action="<?php echo JRoute::_('index.php?option=com_jem&view=attendee'); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">
	<fieldset><legend><?php echo JText::_('COM_JEM_DETAILS'); ?></legend>
		<?php if (!empty($this->row->id)) : ?>
		<p>
			<?php echo JText::_('COM_JEM_EDITATTENDEE_NOTICE'); ?>
		</p>
		<?php endif; ?>
		<table  class="admintable">
			<tr>
				<td class="key" width="150">
					<label for="eventtitle">
						<?php echo JText::_('COM_JEM_EVENT').':'; ?>
					</label>
				</td>
				<td>
					<input type="text" name="eventtitle" id="eventtitle" readonly="readonly"
					       value="<?php echo !empty($this->row->eventtitle) ? $this->row->eventtitle : '?'; ?>"
					/>
				</td>
			</tr>
			<tr>
				<td class="key" width="150">
					<label for="uid">
						<?php echo JText::_('COM_JEM_USER').':'; ?>
					</label>
				</td>
				<td>
					<input type="text" name="username" id="username" readonly="readonly" value="<?php echo $this->row->username; ?>" />
					<input type="hidden" name="uid" id="uid" value="<?php echo $this->row->uid; ?>" />
					<a class="usermodal" title="<?php echo JText::_('COM_JEM_SELECT_USER'); ?>" href="<?php echo $selectuser_link; ?>" rel="{handler: 'iframe', size: {x: 800, y: 500}}">
						<span><?php echo JText::_('COM_JEM_SELECT_USER')?></span>
					</a>
				</td>
			</tr>
			<?php if (1/*!$this->row->id*/): ?>
			<tr>
				<td class="key" width="150">
					<label for="sendemail">
						<?php echo JText::_('COM_JEM_SEND_REGISTRATION_NOTIFICATION_EMAIL').':'; ?>
					</label>
				</td>
				<td>
					<input type="checkbox" name="sendemail" value="1" checked="checked"/>
				</td>
			</tr>
			<?php endif; ?>
		</table>
	</fieldset>

	<?php echo JHtml::_('form.token'); ?>
	<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
	<input type="hidden" name="event" value="<?php echo ($this->row->event ? $this->row->event : $this->event); ?>" />
	<input type="hidden" name="task" value="" />
</form>


<?php
//keep session alive while editing
JHtml::_('behavior.keepalive');
?>