<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');


JHtml::_('behavior.tooltip');
JHtml::_('behavior.formvalidation');
JHTML::_('behavior.keepalive');

// Create shortcut to parameters.
$params = $this->state->get('params');
$params = $params->toArray();

?>

<script type="text/javascript">
	window.addEvent('domready', function(){
		var form = document.getElementById('event-form');
		var metakeywords = $('jform_meta_keywords');
		

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

	});
</script>



<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		if (task == 'event.cancel' || document.formvalidator.isValid(document.id('event-form'))) {
			Joomla.submitform(task, document.getElementById('event-form'));

			$("meta_keywords").value = $keywords;
			$("meta_description").value = $description;
		}
	}
</script>




<form
	action="<?php echo JRoute::_('index.php?option=com_jem&layout=edit&id='.(int) $this->item->id); ?>"
	class="form-validate" method="post" name="adminForm" id="event-form" enctype="multipart/form-data">


	<!-- START OF LEFT DIV -->
	<div class="width-55 fltlft">
	
<?php echo JHtml::_('tabs.start', 'det-pane'); ?>
		<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EVENT_INFO_TAB'), 'info' ); ?>
		
		<!-- START OF LEFT FIELDSET -->
		<fieldset class="adminform">
			<legend>
				<?php echo empty($this->item->id) ? JText::_('COM_JEM_NEW_EVENT') : JText::sprintf('COM_JEM_EVENT_DETAILS', $this->item->id); ?>
			</legend>

			<ul class="adminformlist">

				<li><?php echo $this->form->getLabel('title');?> <?php echo $this->form->getInput('title'); ?>
				</li>
				
				<li><?php echo $this->form->getLabel('alias'); ?> <?php echo $this->form->getInput('alias'); ?>
				</li>
				
				
				
				<li><?php echo $this->form->getLabel('dates'); ?> <?php echo $this->form->getInput('dates'); ?>
				</li>
				
				<li><?php echo $this->form->getLabel('enddates'); ?> <?php echo $this->form->getInput('enddates'); ?>
				</li>
				
				<li><?php echo $this->form->getLabel('times'); ?> <?php echo $this->form->getInput('times'); ?>
				</li>	
				
				<li><?php echo $this->form->getLabel('endtimes'); ?> <?php echo $this->form->getInput('endtimes'); ?>
				</li>
				
				<li><?php echo $this->form->getLabel('cats'); ?> <?php echo $this->form->getInput('cats'); ?>
				</li>
				
				
				
			</ul>
				
</fieldset>




<fieldset class="adminform">
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('locid'); ?> <?php echo $this->form->getInput('locid'); ?>
				</li>
				<li><?php echo $this->form->getLabel('contactid'); ?> <?php echo $this->form->getInput('contactid'); ?>
				</li>
				<li><?php echo $this->form->getLabel('published'); ?> <?php echo $this->form->getInput('published'); ?>
				</li>
			</ul>
		</fieldset>

			
			<fieldset class="adminform">
			
			<div>
				<?php echo $this->form->getLabel('datdescription'); ?>
				<div class="clr"></div>
				<?php echo $this->form->getInput('datdescription'); ?>
			</div>

			<!-- END OF FIELDSET -->
		</fieldset>

<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EVENT_ATTACHMENTS_TAB'), 'attachments' ); ?>
				<?php echo $this->loadTemplate('attachments'); ?>
				<?php echo JHtml::_('tabs.end'); ?>
		<!-- END OF LEFT DIV -->
	</div>




	<!--  START RIGHT DIV -->
	<div class="width-40 fltrt">

		<!-- START OF SLIDERS -->
		<?php echo JHtml::_('sliders.start', 'venue-sliders-'.$this->item->id, array('useCookie'=>1)); ?>

		
		
		
		<!-- START OF PANEL PUBLISHING -->
		<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_FIELDSET_PUBLISHING'), 'publishing-details'); ?>


		<!-- RETRIEVING OF FIELDSET PUBLISHING -->
		<fieldset class="panelform">
			<ul class="adminformlist">
				
				<li><?php echo $this->form->getLabel('id'); ?> <?php echo $this->form->getInput('id'); ?>
				</li>
				
				<li><?php echo $this->form->getLabel('created_by'); ?> <?php echo $this->form->getInput('created_by'); ?>
				</li>
				
				<li><label><?php echo JText::_ ( 'COM_JEM_HITS' );	?></label>
				<input class="inputbox" name="hits" value="<?php echo $this->item->hits; ?>" size="10" maxlength="10" id="a_hits" />
				<?php echo $this->resethits; ?>
				</li>
				
				<li><?php echo $this->form->getLabel('created'); ?> <?php echo $this->form->getInput('created'); ?>
				</li>
			
				<li><?php echo $this->form->getLabel('modified'); ?> <?php echo $this->form->getInput('modified'); ?>
				</li>
				
				<li><?php echo $this->form->getLabel('version'); ?> <?php echo $this->form->getInput('version'); ?>
				</li>
			
			</ul>
		</fieldset>

		<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_CUSTOMFIELDS'), 'custom'); ?>
		<fieldset class="panelform">
			<ul class="adminformlist">
				<?php foreach($this->form->getFieldset('custom') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?>
				</li>
				<?php endforeach; ?>
			</ul>
		</fieldset>
		
		<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_REGISTRATION'), 'registra'); ?>
		<fieldset class="panelform">
			<ul class="adminformlist">
			
				<li><?php echo $this->form->getLabel('registra'); ?> <?php echo $this->form->getInput('registra'); ?>
				</li>
				<li><?php echo $this->form->getLabel('unregistra'); ?> <?php echo $this->form->getInput('unregistra'); ?>
				</li>
				<li><?php echo $this->form->getLabel('maxplaces'); ?> <?php echo $this->form->getInput('maxplaces'); ?>
				</li>
				
				<li><label><?php echo JText::_ ( 'COM_JEM_BOOKED_PLACES' ) . ':';?></label><input id="event-booked" class="readonly" type="text"  value="<?php echo $this->item->booked; ?>" />
				</li>
				
				<?php if ($this->item->maxplaces): ?>
				<li><label><?php echo JText::_ ( 'COM_JEM_AVAILABLE_PLACES' ) . ':';?></label><input id="event-available" class="readonly" type="text"  value="<?php echo ($this->item->maxplaces-$this->item->booked); ?>" />
				</li>
				<?php 
				endif;				 
				?>
				
				<li><?php echo $this->form->getLabel('waitinglist'); ?> <?php echo $this->form->getInput('waitinglist'); ?>
				</li>
			</ul>
		</fieldset>
		
	
		
		<!-- START OF PANEL IMAGE -->
		<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_IMAGE'), 'image-event'); ?>

		
		<fieldset class="panelform">
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('datimage'); ?> <?php echo $this->form->getInput('datimage'); ?>
				</li>
			</ul>
		</fieldset>
		
		
		
		
		
		
		
		
		
		
		<!-- START OF PANEL META -->
		<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_METADATA_INFORMATION'), 'meta-event'); ?>


		<!-- RETRIEVING OF FIELDSET META -->
		<fieldset class="panelform">
		
		
					<input class="inputbox" type="button" onclick="insert_keyword('[title]')" value="<?php echo JText::_ ( 'COM_JEM_EVENT_TITLE' );	?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[a_name]')" value="<?php	echo JText::_ ( 'COM_JEM_VENUE' );?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[categories]')" value="<?php	echo JText::_ ( 'COM_JEM_CATEGORIES' );?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[dates]')" value="<?php echo JText::_ ( 'COM_JEM_DATE' );?>" />

					<p>
						<input class="inputbox" type="button" onclick="insert_keyword('[times]')" value="<?php echo JText::_ ( 'COM_JEM_EVENT_TIME' );?>" />
						<input class="inputbox" type="button" onclick="insert_keyword('[enddates]')" value="<?php echo JText::_ ( 'COM_JEM_ENDDATE' );?>" />
						<input class="inputbox" type="button" onclick="insert_keyword('[endtimes]')" value="<?php echo JText::_ ( 'COM_JEM_END_TIME' );?>" />
					</p>
					<br />
			
			<br />
						<label for="meta_keywords">
						<?php echo JText::_ ( 'COM_JEM_META_KEYWORDS' ) . ':';?>
					</label>
					<br />
						<?php
						if (! empty ( $this->item->meta_keywords )) {
							$meta_keywords = $this->item->meta_keywords;
						} else {
							$meta_keywords = $this->jemsettings->meta_keywords;
						}
						?>
					<textarea class="inputbox" name="meta_keywords" id="meta_keywords" rows="5" cols="40" maxlength="150" onfocus="get_inputbox('meta_keywords')" onblur="change_metatags()"><?php echo $meta_keywords; ?></textarea>
			
			
			
			<label for="meta_description">
						<?php echo JText::_ ( 'COM_JEM_META_DESCRIPTION' ) . ':';?>
					</label>
					<br />
					<?php
					if (! empty ( $this->item->meta_description )) {
						$meta_description = $this->item->meta_description;
					} else {
						$meta_description = $this->jemsettings->meta_description;
					}
					?>
					<textarea class="inputbox" name="meta_description" id="meta_description" rows="5" cols="40" maxlength="200"	onfocus="get_inputbox('meta_description')" onblur="change_metatags()"><?php echo $meta_description;?></textarea>
		</fieldset>

		<script type="text/javascript">
		<!--
			starter("<?php
			echo JText::_ ( 'COM_JEM_META_ERROR' );
			?>");	// window.onload is already in use, call the function manualy instead
		-->
		</script>
		

	<?php echo JHtml::_('sliders.end'); ?>
	
<input type="hidden" name="task" value="" />	
<input type="hidden" name="author_ip" value="<?php echo $this->item->author_ip; ?>" />
				</li>
				
							
				<!--  END RIGHT DIV -->
				<?php echo JHTML::_( 'form.token' ); ?>
				</div>
			
			
				
		<div class="clr"></div>
		
</form>


        