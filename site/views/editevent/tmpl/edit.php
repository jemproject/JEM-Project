<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * 
 */

// no direct access
defined('_JEXEC') or die;

JHtml::_('behavior.keepalive');
JHtml::_('behavior.tooltip');
JHtml::_('behavior.calendar');
JHtml::_('behavior.formvalidation');

// Create shortcut to parameters.
$params		= $this->item->params;


//$images = json_decode($this->item->images);
//$urls = json_decode($this->item->urls);
$settings = json_decode($this->item->attribs);

// This checks if the editor config options have ever been saved. If they haven't they will fall back to the original settings.
$editoroptions = isset($params->show_publishing_options);
if (!$editoroptions):
	$params->show_urls_images_frontend = '0';
endif;
?>

<script type="text/javascript">
	window.addEvent('domready', function(){
	checkmaxplaces();
	});


	function checkmaxplaces()
	{

	var maxplaces = $('jform_maxplaces');

	if (maxplaces != null)
	{
		
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



<div id="jem" class="jem_editevent">

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
<div class="edit item-page<?php echo $this->pageclass_sfx; ?>">
<?php if ($params->get('show_page_heading')) : ?>
<h1>
	<?php echo $this->escape($params->get('page_heading')); ?>
</h1>
<?php endif; ?>

<form enctype="multipart/form-data" action="<?php echo JRoute::_('index.php?option=com_jem&a_id='.(int) $this->item->id); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">

<div class="buttons">
			<button type="button" class="positive" onclick="Joomla.submitbutton('event.save')">
				<?php echo JText::_('JSAVE') ?>
			</button>
			<button type="button" class="negative" onclick="Joomla.submitbutton('event.cancel')">
				<?php echo JText::_('JCANCEL') ?>
			</button>
			</div>

		<?php if ($this->params->def( 'show_page_title', 1 )) : ?>
		<h1 class="componentheading">
		<?php echo empty($this->item->id) ? JText::_('COM_JEM_EDITEVENT_ADD_EVENT') : JText::sprintf('COM_JEM_EDITEVENT_EDIT_EVENT', $this->item->title); ?>
		</h1>
		<?php endif; ?>

		<?php if ($this->params->get('showintrotext')) : ?>
		<div class="description no_space floattext">
			<?php echo $this->params->get('introtext'); ?>
		</div>
		<?php endif; ?>

		<p>&nbsp;</p>


<!-- START OF LEFT DIV -->
	<div class="width-55 fltlft">

<?php echo JHtml::_('tabs.start', 'det-pane'); ?>
		<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EDITEVENT_INFO_TAB'), 'editevent-infotab' ); ?>

<fieldset>
		<legend><?php echo JText::_('COM_JEM_EDITEVENT_DETAILS_LEGEND'); ?></legend>

		<div class="formelm">
			<?php echo $this->form->getLabel('title'); ?>
			<?php echo $this->form->getInput('title'); ?>
			</div>


		<?php if (is_null($this->item->id)):?>
		<div class="formelm">
			<?php echo $this->form->getLabel('alias'); ?>
			<?php echo $this->form->getInput('alias'); ?>
			</div>
		<?php endif; ?>

		
			
			<div class="formelm">
				<?php echo $this->form->getLabel('dates'); ?> 
				<?php echo $this->form->getInput('dates'); ?>
				</div>
			<div class="formelm">
				<?php echo $this->form->getLabel('enddates'); ?> 
				<?php echo $this->form->getInput('enddates'); ?>
				</div>
			<div class="formelm">
				<?php echo $this->form->getLabel('times'); ?> 
				<?php echo $this->form->getInput('times'); ?>
				</div>
			<div class="formelm">
				<?php echo $this->form->getLabel('endtimes'); ?> 
				<?php echo $this->form->getInput('endtimes'); ?>
				</div>	
			<div class="formelm">
				<?php echo $this->form->getLabel('cats'); ?> 
				<?php echo $this->form->getInput('cats'); ?>
				</div>
			<div class="formelm">	
				<?php echo $this->form->getLabel('featured'); ?>
				<?php echo $this->form->getInput('featured'); ?>
				</div>
				<div class="formelm">
				<?php echo $this->form->getLabel('published'); ?> 
				<?php echo $this->form->getInput('published'); ?>
				</div>
			
			
			
			
		<div class="clr"></div>
			<?php echo $this->form->getLabel('articletext'); ?>
			<div class="clr"><br></div>
			<?php echo $this->form->getInput('articletext'); ?>
		</fieldset>
		
		
		<fieldset class="adminform">
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('locid'); ?> <?php echo $this->form->getInput('locid'); ?>
				</li>
				<li><?php echo $this->form->getLabel('contactid'); ?> <?php echo $this->form->getInput('contactid'); ?>
				</li>
			</ul>
		</fieldset>
		
		
		<!--  START META FIELDSET -->
		<fieldset class="">
			<legend><?php echo JText::_('COM_JEM_META_HANDLING'); ?></legend>
			<div class="formelm-area">
					<input class="inputbox" type="button" onclick="insert_keyword('[title]')" value="<?php echo JText::_ ( 'COM_JEM_TITLE' );	?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[a_name]')" value="<?php	echo JText::_ ( 'COM_JEM_VENUE' );?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[categories]')" value="<?php	echo JText::_ ( 'COM_JEM_CATEGORIES' );?>" />
					<input class="inputbox" type="button" onclick="insert_keyword('[dates]')" value="<?php echo JText::_ ( 'COM_JEM_DATE' );?>" />
						<input class="inputbox" type="button" onclick="insert_keyword('[times]')" value="<?php echo JText::_ ( 'COM_JEM_TIME' );?>" />
						<input class="inputbox" type="button" onclick="insert_keyword('[enddates]')" value="<?php echo JText::_ ( 'COM_JEM_ENDDATE' );?>" />
						<input class="inputbox" type="button" onclick="insert_keyword('[endtimes]')" value="<?php echo JText::_ ( 'COM_JEM_ENDTIME' );?>" />
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
			</div>
			<div class="formelm-area">
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
				</div>
				<!-- include the metatags end-->
			<script type="text/javascript">
			<!--
				starter("<?php
				echo JText::_ ( 'COM_JEM_META_ERROR' );
				?>");	// window.onload is already in use, call the function manualy instead
			-->
			</script>

		</fieldset>
		<!--  END META FIELDSET -->
		
		
		
		<input type="hidden" name="task" value="" />
		<input type="hidden" name="return" value="<?php echo $this->return_page;?>" />
		<?php if($this->params->get('enable_category', 0) == 1) :?>
		<input type="hidden" name="jform[catid]" value="<?php echo $this->params->get('catid', 1);?>"/>
		<?php endif;?>
		<?php echo JHtml::_( 'form.token' ); ?>

	
	
	<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EVENT_ATTACHMENTS_TAB'), 'event-attachments' ); ?>
	<?php echo $this->loadTemplate('attachments'); ?>
	

	
	
	<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EVENT_OTHER_TAB'), 'event-other' ); ?>
	<?php echo $this->loadTemplate('other'); ?>
	
	
	
	<!-- END OF TABS -->
				<?php echo JHtml::_('tabs.end'); ?>
	<!-- END OF LEFT DIV -->
	</div>
	
</form>
</div>
</div>