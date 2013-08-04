<?php
/**
 * @version 1.9.1
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



<script
	src="http://api.mygeoposition.com/api/geopicker/api.js"
	type="text/javascript"></script>
<script type="text/javascript">
        function lookupGeoData15() {            
            myGeoPositionGeoPicker({
                returnFieldMap            : {
                                          'jform_latitude'        :    '<LAT>',
                                          'jform_longitude'        :    '<LNG>',
                                          'jform_street'        :    '<STREET> <STREETNUMBER>',
                                          'jform_plz'        :    '<POSTALCODE>',
                                          'jform_city'        :    '<CITY>',
                                          'jform_state' : '<STATE_LONG>',
                                          'jform_country' : '<COUNTRY>'
                                          },
            });
        }
    </script>

<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		if (task == 'venue.cancel' || document.formvalidator.isValid(document.id('venue-form'))) {
			Joomla.submitform(task, document.getElementById('venue-form'));
		}
	}
</script>


<script type="text/javascript">
	window.addEvent('domready', function(){
		var form = document.getElementById('venue-form');
		var map = $('jform_map');
		test();

		if(map && map.checked == true) {
			addrequired();
		}	

		if(map && map.checked == false) {
			removerequired();
		}

	});



function meta()
{

	f=document.getElementById('venue-form');
	f.jform_meta_keywords.value=f.jform_venue.value+', '+f.jform_city.value+f.jform_meta_keywords.value;
}
	

function test()
{
	 var handler = function(e) {

		 var form = document.getElementById('venue-form');
			var map = $('jform_map');

			var streetcheck = $(form.jform_street).hasClass('required');
		 
		 if(map && map.checked == true) {
			 var lat = $('jform_latitude');
				var lon = $('jform_longitude');
				if(lat.value == ('' || 0.000000) || lon.value == ('' || 0.000000)) {
						if(!streetcheck) {  
							addrequired();
						}
					
			} else {
				if(lat.value != ('' || 0.000000) && lon.value != ('' || 0.000000) ) {  
					
				removerequired();
					}
				}
			}
		 	
		 
		 
			if(map && map.checked == false) {
				removerequired();
			}
	    };
	    document.getElementById('jform_map').onchange = handler; 
	    document.getElementById('jform_map').onkeyup = handler;
	    document.getElementById('jform_latitude').onchange = handler; 
	    document.getElementById('jform_latitude').onkeyup = handler;
	    document.getElementById('jform_longitude').onchange = handler; 
	    document.getElementById('jform_longitude').onkeyup = handler;
}
	
	function addrequired() {
		
		var form = document.getElementById('venue-form');
		
		$(form.jform_street).addClass('required');
		$(form.jform_plz).addClass('required');
		$(form.jform_city).addClass('required');
		$(form.jform_country).addClass('required');
	}
	
	function removerequired() {
		
		var form = document.getElementById('venue-form');
		
		$(form.jform_street).removeClass('required');
		$(form.jform_plz).removeClass('required');
		$(form.jform_city).removeClass('required');
		$(form.jform_country).removeClass('required');
	}

	</script>



<form
	action="<?php echo JRoute::_('index.php?option=com_jem&layout=edit&id='.(int) $this->item->id); ?>"
	class="form-validate" method="post" name="adminForm" id="venue-form" enctype="multipart/form-data">


	<!-- START OF LEFT DIV -->
	<div class="width-55 fltlft">
	
<?php echo JHtml::_('tabs.start', 'det-pane'); ?>
		<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_VENUE_INFO_TAB'), 'info' ); ?>
		
		<!-- START OF LEFT FIELDSET -->
		<fieldset class="adminform">
			<legend>
				<?php echo empty($this->item->id) ? JText::_('COM_JEM_NEW_VENUE') : JText::sprintf('COM_JEM_VENUE_DETAILS', $this->item->id); ?>
			</legend>

			<ul class="adminformlist">

				<li><?php echo $this->form->getLabel('venue');?> <?php echo $this->form->getInput('venue'); ?>
				</li>
				
				<li><?php echo $this->form->getLabel('alias'); ?> <?php echo $this->form->getInput('alias'); ?>
				</li>
				
				<li><?php echo $this->form->getLabel('street'); ?> <?php echo $this->form->getInput('street'); ?>
				</li>
				
				<li><?php echo $this->form->getLabel('plz'); ?> <?php echo $this->form->getInput('plz'); ?>
				</li>
				
				<li><?php echo $this->form->getLabel('city'); ?> <?php echo $this->form->getInput('city'); ?>
				</li>

				<li><?php echo $this->form->getLabel('state'); ?> <?php echo $this->form->getInput('state'); ?>
				</li>
				
				<li><?php echo $this->form->getLabel('country'); ?> <?php echo $this->form->getInput('country'); ?>
				</li>

				<li><?php echo $this->form->getLabel('url'); ?> <?php echo $this->form->getInput('url'); ?>
				</li>

				

				
</fieldset>

<fieldset class="adminform">
<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('latitude'); ?> <?php echo $this->form->getInput('latitude'); ?>
				</li>
				<li><?php echo $this->form->getLabel('longitude'); ?> <?php echo $this->form->getInput('longitude'); ?>
				</li>
				<li><?php echo $this->form->getLabel('map'); ?> <?php echo $this->form->getInput('map'); ?>
				</li>
				<li><?php echo $this->form->getLabel('id'); ?> <?php echo $this->form->getInput('id'); ?>
				</li>
				<li><?php echo $this->form->getLabel('published'); ?> <?php echo $this->form->getInput('published'); ?>
				</li>
				
				<li>
				<label><?php echo JText::_( 'COM_JEM_ADDRESS_FINDER' );?></label>
				<button type="button" onclick="lookupGeoData15();">GeoPicker</button>
				</li>
				</ul>
				
			<div class="clr"></div>
			</fieldset>
			
			<fieldset class="adminform">
			
			<div>
				<?php echo $this->form->getLabel('locdescription'); ?>
				<div class="clr"></div>
				<?php echo $this->form->getInput('locdescription'); ?>
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
				<?php foreach($this->form->getFieldset('publish') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?>
				</li>
				<?php endforeach; ?>
			</ul>
		</fieldset>

		
		
		
		<!-- START OF PANEL IMAGE -->
		<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_IMAGE'), 'image-event'); ?>

		
		<fieldset class="panelform">
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('locimage'); ?> <?php echo $this->form->getInput('locimage'); ?>
				</li>
			</ul>
		</fieldset>
		
		
		
		
		
		<!-- START OF PANEL META -->
		<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_METADATA_INFORMATION'), 'meta-event'); ?>


		<!-- RETRIEVING OF FIELDSET META -->
		<fieldset class="panelform">
		<input type="button" class="button" value="<?php echo JText::_( 'COM_JEM_ADD_VENUE_CITY' ); ?>" onclick="meta()" />
			<ul class="adminformlist">
				<?php foreach($this->form->getFieldset('meta') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?>
				</li>
				<?php endforeach; ?>
			</ul>
		</fieldset>

		
		

	<?php echo JHtml::_('sliders.end'); ?>
	
<input type="hidden" name="task" value="" />	
<input type="hidden" name="author_ip" value="<?php echo $this->item->author_ip; ?>" />
				</li>
				
							
				<!--  END RIGHT DIV -->
				<?php echo JHTML::_( 'form.token' ); ?>
				</div>
			
			
				
		<div class="clr"></div>
		
</form>


        