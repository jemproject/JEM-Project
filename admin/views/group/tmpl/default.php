<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>

<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		var form = document.getElementById('adminForm');
		var validator = document.formvalidator;
			
		if (task == 'group.cancel') {
			submitform( task );
			return;
		}

		if ( validator.validate(form.name) === false ) {
   			alert("<?php echo JText::_( 'COM_JEM_ADD_GROUP_NAME', true ); ?>");
   			validator.handleResponse(false,form.name);
   			form.name.focus();
   			return false;
		} else {
			allSelected(document.adminForm['maintainers[]']);
			submitform( task );
		}
	}

	// moves elements from one select box to another one
	function moveOptions(from,to) {
		// Move them over
		for (var i=0; i<from.options.length; i++) {
			var o = from.options[i];
			if (o.selected) {
			  to.options[to.options.length] = new Option( o.text, o.value, false, false);
			}
		}

		// Delete them from original
		for (var i=(from.options.length-1); i>=0; i--) {
			var o = from.options[i];
			if (o.selected) {
			  from.options[i] = null;
			}
		}
		from.selectedIndex = -1;
		to.selectedIndex = -1;
	}

	function allSelected(element) {

		for (var i=0; i<element.options.length; i++) {
			var o = element.options[i];
			o.selected = true;
		}
	}
</script>

<form action="<?php echo JRoute::_('index.php?option=com_jem&view=group'); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">

<table style="width:100%">
	<tr>
		<td valign="top">

			<table class="adminform">
				<tr>
					<td>
						<label for="name">
							<?php echo JText::_( 'COM_JEM_GROUP_NAME' ).':'; ?>
						</label>
					</td>
					<td>
						<input name="name" class="inputbox required" value="<?php echo $this->row->name; ?>" size="50" maxlength="60" id="name" />
					</td>
				</tr>
			</table>

			<table class="adminform">
				<tr>
					<td><b><?php echo JText::_( 'COM_JEM_AVAILABLE_USERS' ).':'; ?></b></td>
					<td>&nbsp;</td>
					<td><b><?php echo JText::_( 'COM_JEM_MAINTAINERS' ).':'; ?></b></td>
				</tr>
				<tr>
					<td width="260px"><?php echo $this->lists['available_users']; ?></td>
					<td width="110px">
						<input style="width: 50px" type="button" name="right" value="&gt;" onClick="moveOptions(document.adminForm['available_users'], document.adminForm['maintainers[]'])" />
						<br /><br />
						<input style="width: 50px" type="button" name="left" value="&lt;" onClick="moveOptions(document.adminForm['maintainers[]'], document.adminForm['available_users'])" />
					</td>
					<td width="260px"><?php echo $this->lists['maintainers']; ?></td>
				</tr>
			</table>

		</td>
		<td valign="top" width="320px" style="padding: 7px 0 0 5px">
			<?php
			echo JHtml::_('sliders.start'); 
			$title = JText::_( 'COM_JEM_DESCRIPTION' );
			echo JHtml::_('sliders.panel', $title, 'desc' );
			?>
			<table>
				<tr>
					<td>
						<textarea wrap="soft" rows="10" cols="40" name="description" class="inputbox"><?php echo $this->row->description; ?></textarea>
					</td>
				</tr>
			</table>
			<?php
				$title2 = JText::_( 'COM_JEM_GROUP_PERMISSIONS' );
				echo JHtml::_('sliders.panel', $title2, 'group');
				?>
				<table>
					<tr>
						<td>
						<span class="hasTip" title="<?php echo JText::_( 'COM_JEM_GROUP_ADDVENUE' ); ?>::<?php echo JText::_('COM_JEM_GROUP_ADDVENUE_DESC'); ?> "> <?php echo JText::_( 'COM_JEM_GROUP_ADDVENUE' ); ?></span>
						</td>
						<td>
						<?php 
				echo JHTML::_('select.booleanlist', 'addvenue', 'class="inputbox"', $this->row->addvenue, 'JYES', 'JNO' );
						?>
						</td>
					</tr>
					<tr>
						<td>
						<span class="hasTip" title="<?php echo JText::_( 'COM_JEM_GROUP_PUBLISHVENUE' ); ?>::<?php echo JText::_('COM_JEM_GROUP_PUBLISHVENUE_DESC'); ?> "> <?php echo JText::_( 'COM_JEM_GROUP_PUBLISHVENUE' ); ?></span>
						</td>
						<td>
						<?php 
				echo JHTML::_('select.booleanlist', 'publishvenue', 'class="inputbox"', $this->row->publishvenue, 'JYES', 'JNO' );
						?>
						</td>
					</tr>
					<tr>
						<td>
						<span class="hasTip" title="<?php echo JText::_( 'COM_JEM_GROUP_EDITVENUE' ); ?>::<?php echo JText::_('COM_JEM_GROUP_EDITVENUE_DESC'); ?> "> <?php echo JText::_( 'COM_JEM_GROUP_EDITVENUE' ); ?></span>
						</td>
						<td>
						<?php 
				echo JHTML::_('select.booleanlist', 'editvenue', 'class="inputbox"', $this->row->editvenue, 'JYES', 'JNO' );
						?>
						</td>
					</tr>
				</table>
			
			
			<?php
			echo JHtml::_('sliders.end');
			?>
		</td>
	</tr>
</table>

<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
<input type="hidden" name="task" value="" />
</form>

<p class="copyright">
	<?php echo JEMAdmin::footer( ); ?>
</p>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>