<?php
/**
 * @version 2.1.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


JHtml::_('behavior.tooltip');
JHtml::_('behavior.formvalidation');
JHtml::_('behavior.keepalive');

// Create shortcut to parameters.
$params = $this->state->get('params');
$params = $params->toArray();

?>
<script type="text/javascript">
	window.addEvent('domready', function(){
	});

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

	function selectAll()
    {
        selectBox = document.getElementById("maintainers");

        for (var i = 0; i < selectBox.options.length; i++)
        {
             selectBox.options[i].selected = true;
        }
    }
</script>
<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		selectAll();
		if (task == 'group.cancel' || document.formvalidator.isValid(document.id('adminForm'))) {
			Joomla.submitform(task, document.getElementById('adminForm'));
		}
	}
</script>

<form
	action="<?php echo JRoute::_('index.php?option=com_jem&layout=edit&id='.(int) $this->item->id); ?>"
	class="form-validate" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

	<!-- START OF LEFT DIV -->
	<div class="width-55 fltlft">
		<?php echo JHtml::_('tabs.start', 'det-pane'); ?>
		<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_GROUP_INFO_TAB'), 'group-info' ); ?>
		<fieldset class="adminform">
			<legend>
				<?php echo empty($this->item->id) ? JText::_('COM_JEM_NEW_GROUP') : JText::sprintf('COM_JEM_GROUP_DETAILS', $this->item->id); ?>
			</legend>
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('name');?> <?php echo $this->form->getInput('name'); ?>
				</li>
				<li><?php echo $this->form->getLabel('id');?> <?php echo $this->form->getInput('id'); ?>
				</li>
				<li><?php echo $this->form->getLabel('maintainers2');?> <?php echo $this->form->getInput('maintainers2'); ?>
				</li>
			</ul>
		</fieldset>
		<fieldset class="adminform">
			<table class="adminform" style="width: 100%">
				<tr>
					<td><b><?php echo JText::_('COM_JEM_GROUP_AVAILABLE_USERS').':'; ?></b></td>
					<td>&nbsp;</td>
					<td><b><?php echo JText::_('COM_JEM_GROUP_MAINTAINERS').':'; ?></b></td>
				</tr>
				<tr>
					<td width="44%"><?php echo $this->lists['available_users']; ?></td>
					<td width="10%">
						<input style="width: 90%" type="button" name="right" value="&gt;" onClick="moveOptions(document.adminForm['available_users'], document.adminForm['maintainers[]'])" />
						<br /><br />
						<input style="width: 90%" type="button" name="left" value="&lt;" onClick="moveOptions(document.adminForm['maintainers[]'], document.adminForm['available_users'])" />
					</td>
					<td width="44%"><?php echo $this->lists['maintainers']; ?></td>
				</tr>
			</table>
		</fieldset>
			<fieldset class="adminform">
			<div>
				<?php echo $this->form->getLabel('description'); ?>
				<div class="clr"></div>
				<?php echo $this->form->getInput('description'); ?>
			</div>
		</fieldset>
		<!-- END OF LEFT DIV -->
	</div>

	<!--  START RIGHT DIV -->
	<div class="width-40 fltrt">
		<!-- START OF SLIDERS -->
		<?php echo JHtml::_('sliders.start', 'group-sliders-'.$this->item->id, array('useCookie'=>1)); ?>
		<!-- START OF PANEL PUBLISHING -->
		<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_GROUP_PERMISSIONS'), 'group-permission'); ?>
		<!-- RETRIEVING OF FIELDSET PUBLISHING -->
		<fieldset class="panelform">
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('addvenue'); ?>
				<?php echo $this->form->getInput('addvenue'); ?></li>
				<li><?php echo $this->form->getLabel('publishvenue'); ?>
				<?php echo $this->form->getInput('publishvenue'); ?></li>
				<li><?php echo $this->form->getLabel('editvenue'); ?>
				<?php echo $this->form->getInput('editvenue'); ?></li>
				<li><?php echo $this->form->getLabel('addevent'); ?>
				<?php echo $this->form->getInput('addevent'); ?></li>
				<li><?php echo $this->form->getLabel('publishevent'); ?>
				<?php echo $this->form->getInput('publishevent'); ?></li>
				<li><?php echo $this->form->getLabel('editevent'); ?>
				<?php echo $this->form->getInput('editevent'); ?></li>
			</ul>
		</fieldset>
	<?php echo JHtml::_('sliders.end'); ?>
		<input type="hidden" name="task" value="" />
				<!--  END RIGHT DIV -->
				<?php echo JHtml::_( 'form.token' ); ?>
				</div>
		<div class="clr"></div>
</form>