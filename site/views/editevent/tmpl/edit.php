<?php
/**
 * @version 2.3.1
 * @package JEM
 * @copyright (C) 2013-2021 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

JHtml::_('behavior.keepalive');
JHtml::_('behavior.tooltip');
JHtml::_('behavior.calendar');
JHtml::_('behavior.formvalidation');

// Create shortcut to parameters.
$params		= $this->params;
$settings	= json_decode($this->item->attribs);
?>

<script type="text/javascript">
window.addEvent('domready', function(){
	checkmaxplaces();
});

	function checkmaxplaces(){
		var maxplaces = $('jform_maxplaces');

		if (maxplaces != null){
			$('jform_maxplaces').addEvent('change', function(){
				if ($('event-available')) {
					var val = parseInt($('jform_maxplaces').value);
					var booked = parseInt($('event-booked').value);
					$('event-available').value = (val-booked);
				}
			});

			$('jform_maxplaces').addEvent('keyup', function(){
				if ($('event-available')) {
					var val = parseInt($('jform_maxplaces').value);
					var booked = parseInt($('event-booked').value);
					$('event-available').value = (val-booked);
				}
			});
		}
	}
</script>
<script type="text/javascript">
	Joomla.submitbutton = function(task) {
		if (task == 'event.cancel' || document.formvalidator.isValid(document.id('adminForm'))) {
			<?php echo $this->form->getField('articletext')->save(); ?>
			Joomla.submitform(task);
		} else {
			alert('<?php echo $this->escape(JText::_('JGLOBAL_VALIDATION_FORM_FAILED'));?>');
		}
	}
</script>
<script type="text/javascript">
window.addEvent('domready', function(){
	$("jform_unregistra").addEvent('change', showUnregistraUntil);

	showUnregistraUntil();
});

function showUnregistraUntil()
{
	var unregistra = $("jform_unregistra");
	var unregistramode = unregistra.options[unregistra.selectedIndex].value;

	if (unregistramode == 2) {
		document.getElementById('jform_unregistra_until').style.display = '';
		document.getElementById('jform_unregistra_until2').style.display = '';
	} else {
		document.getElementById('jform_unregistra_until').style.display = 'none';
		document.getElementById('jform_unregistra_until2').style.display = 'none';
	}
}
</script>

<div id="jem" class="jem_editevent<?php echo $this->pageclass_sfx; ?>">
	<div class="edit item-page">
		<?php if ($params->get('show_page_heading')) : ?>
		<h1>
			<?php echo $this->escape($params->get('page_heading')); ?>
		</h1>
		<?php endif; ?>

		<form enctype="multipart/form-data" action="<?php echo JRoute::_('index.php?option=com_jem&a_id='.(int) $this->item->id); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">

				<button type="submit" class="btn btn-primary" onclick="Joomla.submitbutton('event.save')"><?php echo JText::_('JSAVE') ?></button>
				<button type="cancel" class="btn btn-secondary" onclick="Joomla.submitbutton('event.cancel')"><?php echo JText::_('JCANCEL') ?></button>


			<?php if ($this->item->recurrence_type > 0) : ?>
			<div class="description">
				<div style="float:left;">
					<?php echo JemOutput::recurrenceicon($this->item, false, false); ?>
				</div>
				<div class="floattext" style="margin-left:36px;">
					<strong><?php echo JText::_('COM_JEM_EDITEVENT_WARN_RECURRENCE_TITLE'); ?></strong>
					<br>
					<?php
						if (!empty($this->item->recurrence_type) && empty($this->item->recurrence_first_id)) {
							echo nl2br(JText::_('COM_JEM_EDITEVENT_WARN_RECURRENCE_FIRST_TEXT'));
						} else {
							echo nl2br(JText::_('COM_JEM_EDITEVENT_WARN_RECURRENCE_TEXT'));
						}
					?>
				</div>
			</div>
			<?php endif; ?>

			<?php if ($this->params->get('showintrotext')) : ?>
			<div class="description no_space floattext">
				<?php echo $this->params->get('introtext'); ?>
			</div>
			<?php endif; ?>

			<?php echo JHtml::_('tabs.start', 'det-pane'); ?>

			<!-- DETAILS TAB -->
			<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EDITEVENT_INFO_TAB'), 'editevent-infotab' ); ?>

			<fieldset>
				<legend><?php echo JText::_('COM_JEM_EDITEVENT_DETAILS_LEGEND'); ?></legend>
					<ul class="adminformlist">
					<li><?php echo $this->form->getLabel('title'); ?><?php echo $this->form->getInput('title'); ?></li>
					<?php if (is_null($this->item->id)):?>
					<li><?php echo $this->form->getLabel('alias'); ?><?php echo $this->form->getInput('alias'); ?></li>
					<?php endif; ?>
					<li><?php echo $this->form->getLabel('dates'); ?><?php echo $this->form->getInput('dates'); ?></li>
					<li><?php echo $this->form->getLabel('enddates'); ?><?php echo $this->form->getInput('enddates'); ?></li>
					<li><?php echo $this->form->getLabel('times'); ?><?php echo $this->form->getInput('times'); ?></li>
					<li><?php echo $this->form->getLabel('endtimes'); ?><?php echo $this->form->getInput('endtimes'); ?></li>
					<li><?php echo $this->form->getLabel('cats'); ?><?php echo $this->form->getInput('cats'); ?></li>
					<li><?php echo $this->form->getLabel('locid'); ?> <?php echo $this->form->getInput('locid'); ?></li>

				</ul>
			</fieldset>
			<fieldset>
				<legend><?php echo JText::_('COM_JEM_EVENT_DESCRIPTION'); ?></legend>
			
				<div class="clr"></div>
				<?php echo $this->form->getLabel('articletext'); ?>
				<div class="clr"><br></div>
				<?php echo $this->form->getInput('articletext'); ?>
			</fieldset>
	<!-- IMAGE -->
	<?php if ($this->item->datimage || $this->jemsettings->imageenabled != 0) : ?>
	<fieldset class="jem_fldst_image">
		<legend><?php echo JText::_('COM_JEM_IMAGE'); ?></legend>
		<?php
		if ($this->item->datimage) :
			echo JemOutput::flyer($this->item, $this->dimage, 'event', 'datimage');
			?><input type="hidden" name="datimage" id="datimage" value="<?php echo $this->item->datimage; ?>" /><?php
		endif;
		?>
		<?php if ($this->jemsettings->imageenabled != 0) : ?>
		<ul class="adminformlist">
			<li>
				<?php /* We get field with id 'jform_userfile' and name 'jform[userfile]' */ ?>
				<?php echo $this->form->getLabel('userfile'); ?> <?php echo $this->form->getInput('userfile'); ?>
			</li>
			<li>							   
				<button type="button" class="button3" onclick="document.getElementById('jform_userfile').value = ''"><?php echo JText::_('JSEARCH_FILTER_CLEAR') ?></button>
</li>	
	<?php
				if ($this->item->datimage) :
					echo JHtml::image('media/com_jem/images/publish_r.png', null, array('id' => 'userfile-remove', 'data-id' => $this->item->id, 'data-type' => 'events', 'title' => JText::_('COM_JEM_REMOVE_IMAGE')));

				endif;
				?>
		<input type="hidden" name="removeimage" id="removeimage" value="0" />
				
			</li>
		</ul>
		<?php endif; ?>
	</fieldset>
	<?php endif; ?>

			<!-- EXTENDED TAB -->
			<?php echo JHtml::_('tabs.panel', JText::_('COM_JEM_EDITEVENT_EXTENDED_TAB'), 'editevent-extendedtab'); ?>
			<?php echo $this->loadTemplate('extended'); ?>

			<!-- PUBLISH TAB -->
			
			<?php  echo JHtml::_('tabs.panel', JText::_('COM_JEM_EDITEVENT_PUBLISH_TAB'), 'editevent-publishtab'); ?>
			<?php echo $this->loadTemplate('publish'); ?>

			<!-- ATTACHMENTS TAB -->
			<?php if (!empty($this->item->attachments) || ($this->jemsettings->attachmentenabled != 0)) : ?>
			<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EVENT_ATTACHMENTS_TAB'), 'event-attachments' ); ?>
			<?php echo $this->loadTemplate('attachments'); ?>
			<?php endif; ?>

			<!-- OTHER TAB -->
			<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EVENT_OTHER_TAB'), 'event-other' ); ?>
			<?php echo $this->loadTemplate('other'); ?>

			<?php echo JHtml::_('tabs.end'); ?>

			<input type="hidden" name="task" value="" />
			<input type="hidden" name="return" value="<?php echo $this->return_page;?>" />
			<input type="hidden" name="author_ip" value="<?php echo $this->item->author_ip; ?>" />
			<?php if($this->params->get('enable_category', 0) == 1) :?>
			<input type="hidden" name="jform[catid]" value="<?php echo $this->params->get('catid', 1);?>"/>
			<?php endif;?>
			<?php echo JHtml::_('form.token'); ?>
		</form>
	</div>

	<div class="copyright">
		<?php echo JemOutput::footer(); ?>
	</div>
</div>
