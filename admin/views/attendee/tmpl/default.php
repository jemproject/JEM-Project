<?php
/**
 * @version 1.0 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

defined('_JEXEC') or die;

JHTML::_('behavior.modal', 'a.usermodal');

$selectuser_link = JRoute::_('index.php?option=com_jem&controller=attendees&task=selectuser&tmpl=component');
?>

<script language="javascript" type="text/javascript">

	function elSelectUser(id, username)
	{
		$('uid').value = id;
		$('username').value = username;
		$('sbox-window').close();
	}


	function submitbutton(pressbutton)
	{
		var form = document.getElementById('adminForm');
		var validator = document.formvalidator;
			
		if (pressbutton == 'cancel') {
			submitform( pressbutton );
			return;
		}

		if ( validator.validate(form.uid) === false ) {
   			alert("<?php echo JText::_( 'COM_JEM_SELECT_AN_USER', true ); ?>");
   			return false;
   		} else {
			submitform( pressbutton );
   		}

	}
</script>


<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate">
	<fieldset><legend><?php echo JText::_('COM_JEM_DETAILS'); ?></legend>
	<table  class="admintable">
		<tr>
			<td class="key" width="150">
				<label for="uid">
					<?php echo JText::_( 'COM_JEM_USER' ).':'; ?>
				</label>
			</td>
			<td>
				<input type="text" name="username" id="username" readonly="readonly" value="<?php echo $this->row->username; ?>" />
				<input type="hidden" name="uid" id="uid" value="<?php echo $this->row->uid; ?>" />
				<a class="usermodal" title="<?php echo JText::_('COM_JEM_SELECT_USER'); ?>" href="<?php echo $selectuser_link; ?>" rel="{handler: 'iframe', size: {x: 800, y: 500}}">
					<span><?php echo JText::_('Select user')?></span>
        </a>
			</td>
		</tr>
		<?php if (!$this->row->id): ?>
		<tr>
			<td class="key" width="150">
				<label for="sendemail">
					<?php echo JText::_( 'Send registration notification email' ).':'; ?>
				</label>
			</td>
			<td>
				<input type="checkbox" name="sendemail" value="1" checked="checked"/>
			</td>
		</tr>
		<?php endif; ?>
	</table>
	</fieldset>

<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_jem" />
<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
<input type="hidden" name="event" value="<?php echo ($this->row->event ? $this->row->event : $this->event); ?>" />
<input type="hidden" name="controller" value="attendees" />
<input type="hidden" name="view" value="attendee" />
<input type="hidden" name="task" value="" />
</form>

<p class="copyright">
	<?php echo ELAdmin::footer( ); ?>
</p>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>