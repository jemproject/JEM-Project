<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
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
		setAttribute();
		test();

		if(map && map.checked == true) {
			addrequired();
		}

		if(map && map.checked == false) {
			removerequired();
		}

	});


	function setAttribute()
	{

		var attribute = document.createAttribute("geo-data");
	    attribute.nodeValue = "postal_code"
	    document.getElementById("jform_postalCode").setAttributeNode(attribute);

	    var attribute = document.createAttribute("geo-data");
	    attribute.nodeValue = "locality"
	    document.getElementById("jform_city").setAttributeNode(attribute);

	    var attribute = document.createAttribute("geo-data");
	    attribute.nodeValue = "administrative_area_level_1"
	    document.getElementById("jform_state").setAttributeNode(attribute);

	    var attribute = document.createAttribute("geo-data");
	    attribute.nodeValue = "street_address"
	    document.getElementById("jform_street").setAttributeNode(attribute);

	    var attribute = document.createAttribute("geo-data");
	    attribute.nodeValue = "lat"
	    document.getElementById("jform_latitude").setAttributeNode(attribute);

	    var attribute = document.createAttribute("geo-data");
	    attribute.nodeValue = "lng"
	    document.getElementById("jform_longitude").setAttributeNode(attribute);



	}




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
		$(form.jform_postalCode).addClass('required');
		$(form.jform_city).addClass('required');
		$(form.jform_country).addClass('required');
	}

	function removerequired() {

		var form = document.getElementById('venue-form');

		$(form.jform_street).removeClass('required');
		$(form.jform_postalCode).removeClass('required');
		$(form.jform_city).removeClass('required');
		$(form.jform_country).removeClass('required');
	}

	</script>


    <script>
      jQuery(function(){
    	  jQuery("#geocomplete").geocomplete({
          map: ".map_canvas",
          /* location: "default address", */
          details: "form ",
          detailsAttribute: "geo-data",
          types: ['establishment', 'geocode'],
          markerOptions: {
            draggable: true
          }
        });

    	  jQuery("#geocomplete").bind("geocode:dragged", function(event, latLng){
    		  jQuery("input[id=jform_latitude]").val(latLng.lat());
    		  jQuery("input[id=jform_longitude]").val(latLng.lng());
    		  jQuery("#geocomplete").geocomplete("find", latLng.toString());
    		 /* option to show the reset-link */
    		 /* jQuery("#reset").show();*/
        });



    	  jQuery("#geocomplete").bind("geocode:result", function(event, result){
    	  		var country = document.getElementById("country").value;
    	  		document.getElementById("jform_country").value = country;
        });


        /* option to attach a reset function to the reset-link
    	  jQuery("#reset").click(function(){
   		  jQuery("#geocomplete").geocomplete("resetMarker");
   		  jQuery("#reset").hide();
          return false;
        });
     	*/

    	  jQuery("#find").click(function(){
    		  jQuery("#geocomplete").trigger("geocode");
        }).click();
      });
    </script>

<form
	action="<?php echo JRoute::_('index.php?option=com_jem&layout=edit&id='.(int) $this->item->id); ?>"
	class="form-validate" method="post" name="adminForm" id="venue-form" enctype="multipart/form-data">


	<!-- START OF LEFT DIV -->
	<div class="width-55 fltlft">
	
	<?php echo JHtml::_('tabs.start', 'det-pane'); ?>
	<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_VENUE_INFO_TAB'), 'info' ); ?>
		<fieldset class="adminform">
			<legend>
				<?php echo empty($this->item->id) ? JText::_('COM_JEM_NEW_VENUE') : JText::sprintf('COM_JEM_VENUE_DETAILS', $this->item->id); ?>
			</legend>

			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('venue');?> 
				<?php echo $this->form->getInput('venue'); ?></li>

				<li><?php echo $this->form->getLabel('alias'); ?> 
				<?php echo $this->form->getInput('alias'); ?></li>

				<li><?php echo $this->form->getLabel('street'); ?> 
				<?php echo $this->form->getInput('street'); ?></li>

				<li><?php echo $this->form->getLabel('postalCode'); ?> 
				<?php echo $this->form->getInput('postalCode'); ?></li>

				<li><?php echo $this->form->getLabel('city'); ?> 
				<?php echo $this->form->getInput('city'); ?></li>

				<li><?php echo $this->form->getLabel('state'); ?> 
				<?php echo $this->form->getInput('state'); ?></li>

				<li><?php echo $this->form->getLabel('country'); ?> 
				<?php echo $this->form->getInput('country'); ?></li>

				<li><?php echo $this->form->getLabel('url'); ?> 
				<?php echo $this->form->getInput('url'); ?></li>
			</ul>
				<div>
				<?php echo $this->form->getLabel('locdescription'); ?>
				<div class="clr"></div>
				<?php echo $this->form->getInput('locdescription'); ?>
				</div>
		</fieldset>
	<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EVENT_ATTACHMENTS_TAB'), 'attachments' ); ?>
	<?php echo $this->loadTemplate('attachments'); ?>
	<?php echo JHtml::_('tabs.end'); ?>
		
	<!-- END OF LEFT DIV -->
	</div>

	<!--  START RIGHT DIV -->
	<div class="width-40 fltrt">

	<?php echo JHtml::_('sliders.start', 'venue-sliders-'.$this->item->id, array('useCookie'=>1)); ?>
	<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_FIELDSET_PUBLISHING'), 'publishing-details'); ?>
		<fieldset class="panelform">
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('id'); ?> 
				<?php echo $this->form->getInput('id'); ?></li>
				
				<li><?php echo $this->form->getLabel('published'); ?> 
				<?php echo $this->form->getInput('published'); ?></li>
				
				<?php foreach($this->form->getFieldset('publish') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
				<?php endforeach; ?>
			</ul>
		</fieldset>
	<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_CUSTOMFIELDS'), 'venue-custom'); ?>
		<fieldset class="panelform">
			<ul class="adminformlist">
				<?php foreach($this->form->getFieldset('custom') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?>
				</li>
				<?php endforeach; ?>
			</ul>
		</fieldset>
	<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_IMAGE'), 'image-event'); ?>
		<fieldset class="panelform">
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('locimage'); ?> 
				<?php echo $this->form->getInput('locimage'); ?></li>
			</ul>
		</fieldset>
	<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_METADATA_INFORMATION'), 'meta-event'); ?>
		<fieldset class="panelform">
			<input type="button" class="button" value="<?php echo JText::_( 'COM_JEM_ADD_VENUE_CITY' ); ?>" onclick="meta()" />
			<ul class="adminformlist">
				<?php foreach($this->form->getFieldset('meta') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?>
				</li>
				<?php endforeach; ?>
			</ul>
		</fieldset>
	<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_FIELDSET_GEODATA'), 'venue-geodata'); ?>
		<fieldset class="adminform" id="geodata">
			<input id="geocomplete" type="text" placeholder="<?php echo JText::_( 'COM_JEM_VENUE_ADDRPLACEHOLDER' ); ?>" value="" />
      		<input id="find" type="button" value="find" />
      		<br><br>
 			<div class="map_canvas"></div>
      		<a id="reset" href="#" style="display:none;">Reset Marker</a>
		</fieldset>
		<fieldset class="adminform">
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('latitude'); ?> 
				<?php echo $this->form->getInput('latitude'); ?></li>
				
				<li><?php echo $this->form->getLabel('longitude'); ?> 
				<?php echo $this->form->getInput('longitude'); ?></li>
				
				<li><?php echo $this->form->getLabel('map'); ?> 
				<?php echo $this->form->getInput('map'); ?></li>
			</ul>
			<div class="clr"></div>
		</fieldset>
	<?php echo JHtml::_('sliders.end'); ?>

		<input type="hidden" name="task" value="" />
		<input type="hidden" name="author_ip" value="<?php echo $this->item->author_ip; ?>" />


	<!--  END RIGHT DIV -->
	<?php echo JHtml::_( 'form.token' ); ?>
	</div>
	<div class="clr"></div>
       <input id="country" name="country" geo-data="country_short" type="hidden" value="">
</form>