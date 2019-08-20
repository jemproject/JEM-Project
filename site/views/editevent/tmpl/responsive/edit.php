<?php

/**
 * @version 2.3.0
 * @package JEM
 * @copyright (C) 2013-2019 joomlaeventmanager.net
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
	window.addEvent('domready', function() {
		checkmaxplaces();
	});

	function checkmaxplaces() {
		var maxplaces = $('jform_maxplaces');

		if (maxplaces != null) {
			$('jform_maxplaces').addEvent('change', function() {
				if ($('event-available')) {
					var val = parseInt($('jform_maxplaces').value);
					var booked = parseInt($('event-booked').value);
					$('event-available').value = (val - booked);
				}
			});

			$('jform_maxplaces').addEvent('keyup', function() {
				if ($('event-available')) {
					var val = parseInt($('jform_maxplaces').value);
					var booked = parseInt($('event-booked').value);
					$('event-available').value = (val - booked);
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
			alert('<?php echo $this->escape(JText::_('JGLOBAL_VALIDATION_FORM_FAILED')); ?>');
		}
	}
</script>
<script type="text/javascript">
	window.addEvent('domready', function() {
		$("jform_unregistra").addEvent('change', showUnregistraUntil);

		showUnregistraUntil();
	});

	function showUnregistraUntil() {
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

		<form enctype="multipart/form-data" action="<?php echo JRoute::_('index.php?option=com_jem&a_id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">
			<div class="buttons">
				<button type="button" class="positive btn btn-success" onclick="Joomla.submitbutton('event.save')"><?php echo JText::_('JSAVE') ?></button>
				<button type="button" class="negative btn" onclick="Joomla.submitbutton('event.cancel')"><?php echo JText::_('JCANCEL') ?></button>
			</div>

			<?php if ($this->item->recurrence_type > 0) : ?>
			<div class="description warningrecurrence" style="clear: both;">
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
			<?php echo JHtml::_('tabs.panel', JText::_('COM_JEM_EDITEVENT_INFO_TAB'), 'editevent-infotab'); ?>

			<fieldset class="adminform">
				<legend><?php echo JText::_('COM_JEM_EDITEVENT_DETAILS_LEGEND'); ?></legend>
				<dl class="jem-dl">
					<dt><?php echo $this->form->getLabel('title'); ?></dt>
					<dd><?php echo $this->form->getInput('title'); ?></dd>
					<?php if (is_null($this->item->id)) : ?>
					<dt><?php echo $this->form->getLabel('alias'); ?></dt>
					<dd><?php echo $this->form->getInput('alias'); ?></dd>
					<?php endif; ?>
					<dt><?php echo $this->form->getLabel('dates'); ?></dt>
					<dd><?php echo $this->form->getInput('dates'); ?></dd>
					<dt><?php echo $this->form->getLabel('enddates'); ?></dt>
					<dd><?php echo $this->form->getInput('enddates'); ?></dd>
					<dt><?php echo $this->form->getLabel('times'); ?></dt>
					<dd><?php echo $this->form->getInput('times'); ?></dd>
					<dt><?php echo $this->form->getLabel('endtimes'); ?></dt>
					<dd><?php echo $this->form->getInput('endtimes'); ?></dd>
					<dt><?php echo $this->form->getLabel('cats'); ?></dt>
					<dd><?php echo $this->form->getInput('cats'); ?></dd>
					<dt><?php echo $this->form->getLabel('locid'); ?></dt>
					<dd><?php echo $this->form->getInput('locid'); ?></dd>

				</dl>
				<div style="clear: both;"><br /></div>
				<div>
					<?php echo $this->form->getLabel('articletext'); ?>
					<?php echo $this->form->getInput('articletext'); ?>
				</div>
				<p>&nbsp;</p>
				<!-- IMAGE -->
				<?php if ($this->item->datimage || $this->jemsettings->imageenabled != 0) : ?>
				<fieldset class="jem_fldst_image">
					<legend><?php echo JText::_('COM_JEM_IMAGE'); ?></legend>
					<?php if ($this->jemsettings->imageenabled != 0) : ?>
					<dl class="adminformlist jem-dl">
						<dt><?php echo $this->form->getLabel('userfile'); ?></dt>
						<?php if ($this->item->datimage) : ?>
						<dd>
							<?php echo JEMOutput::flyer($this->item, $this->dimage, 'event', 'datimage'); ?>
							<input type="hidden" name="datimage" id="datimage" value="<?php echo $this->item->datimage; ?>" />
						</dd>
						<dt> </dt>
						<?php endif; ?>
						<dd><?php echo $this->form->getInput('userfile'); ?></dd>
						<dt> </dt>
						<dd><button type="button" class="button3 btn" onclick="document.getElementById('jform_userfile').value = ''"><?php echo JText::_('JSEARCH_FILTER_CLEAR') ?></button></dd>
						<?php if ($this->item->datimage) : ?>
						<dt><?php echo JText::_('COM_JEM_REMOVE_IMAGE'); ?></dt>
						<dd><?php
										echo JHtml::image('media/com_jem/images/publish_r.png', null, array('id' => 'userfile-remove', 'data-id' => $this->item->id, 'data-type' => 'events', 'title' => JText::_('COM_JEM_REMOVE_IMAGE'), 'class' => 'btn')); ?>
						</dd>
						<?php endif; ?>
						</li>
					</dl>
					<input type="hidden" name="removeimage" id="removeimage" value="0" />
					<?php endif; ?>
				</fieldset>
				<?php endif; ?>
			</fieldset>


			<!-- EXTENDED TAB -->
			<?php echo JHtml::_('tabs.panel', JText::_('COM_JEM_EDITEVENT_EXTENDED_TAB'), 'editevent-extendedtab'); ?>
			<?php echo $this->loadTemplate('extended'); ?>

			<!-- PUBLISH TAB -->
			<?php echo JHtml::_('tabs.panel', JText::_('COM_JEM_EDITEVENT_PUBLISH_TAB'), 'editevent-publishtab'); ?>
			<?php echo $this->loadTemplate('publish'); ?>

			<!-- ATTACHMENTS TAB -->
			<?php if (!empty($this->item->attachments) || ($this->jemsettings->attachmentenabled != 0)) : ?>
			<?php echo JHtml::_('tabs.panel', JText::_('COM_JEM_EVENT_ATTACHMENTS_TAB'), 'event-attachments'); ?>
			<?php echo $this->loadTemplate('attachments'); ?>
			<?php endif; ?>

			<!-- OTHER TAB -->
			<?php echo JHtml::_('tabs.panel', JText::_('COM_JEM_EVENT_OTHER_TAB'), 'event-other'); ?>
			<?php echo $this->loadTemplate('other'); ?>

			<?php echo JHtml::_('tabs.end'); ?>

			<input type="hidden" name="task" value="" />
			<input type="hidden" name="return" value="<?php echo $this->return_page; ?>" />
			<input type="hidden" name="author_ip" value="<?php echo $this->item->author_ip; ?>" />
			<?php if ($this->params->get('enable_category', 0) == 1) : ?>
			<input type="hidden" name="jform[catid]" value="<?php echo $this->params->get('catid', 1); ?>" />
			<?php endif; ?>
			<?php echo JHtml::_('form.token'); ?>
		</form>
	</div>

	<div class="copyright">
		<?php echo JemOutput::footer(); ?>
	</div>
</div>