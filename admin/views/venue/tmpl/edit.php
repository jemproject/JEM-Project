<?php
/**
 * @version 2.1.6
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
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

# defining values for centering default-map
$location = JemHelper::defineCenterMap($this->form);
?>

<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		if (task == 'venue.cancel' || document.formvalidator.isValid(document.id('venue-form'))) {
			Joomla.submitform(task, document.getElementById('venue-form'));
		}
	}

	window.addEvent('domready', function() {		
		setAttribute();
		test();
	});

	function setAttribute(){
		document.getElementById("tmp_form_postalCode").setAttribute("geo-data", "postal_code");
		document.getElementById("tmp_form_city").setAttribute("geo-data", "locality");
		document.getElementById("tmp_form_state").setAttribute("geo-data", "administrative_area_level_1");
		document.getElementById("tmp_form_street").setAttribute("geo-data", "street_address");
		document.getElementById("tmp_form_route").setAttribute("geo-data", "route");
		document.getElementById("tmp_form_streetnumber").setAttribute("geo-data", "street_number");
		document.getElementById("tmp_form_country").setAttribute("geo-data", "country_short");
		document.getElementById("tmp_form_latitude").setAttribute("geo-data", "lat");
		document.getElementById("tmp_form_longitude").setAttribute("geo-data", "lng");
		document.getElementById("tmp_form_venue").setAttribute("geo-data", "name");	
	}

	function meta(){
		var f = document.getElementById('venue-form');
		if(f.jform_meta_keywords.value != "") f.jform_meta_keywords.value += ", ";
		f.jform_meta_keywords.value += f.jform_venue.value+', ' + f.jform_city.value;
	}

	function test(){			
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
				$('mapdiv').show();
			}

			if(map && map.checked == false) {
				removerequired();
				$('mapdiv').hide();
			}
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


	jQuery(function() {
		jQuery("#geocomplete").geocomplete({
			map: ".map_canvas",
			<?php echo $location; ?>
			details: "form ",
			detailsAttribute: "geo-data",
			types: ['establishment', 'geocode'],
			mapOptions: {
			      zoom: 16,
			      mapTypeId: "hybrid"
			    },
			markerOptions: {
				draggable: true
			}
			
		});

		jQuery("#geocomplete").bind('geocode:result', function(){
				var street = jQuery("#tmp_form_street").val();
				var route  = jQuery("#tmp_form_route").val();
				
				if (route) {
					/* something to add */
				} else {
					jQuery("#tmp_form_street").val('');
				}
		});

		jQuery("#geocomplete").bind("geocode:dragged", function(event, latLng){
			jQuery("#tmp_form_latitude").val(latLng.lat());
			jQuery("#tmp_form_longitude").val(latLng.lng());
		});

		/* option to attach a reset function to the reset-link
			jQuery("#reset").click(function(){
			jQuery("#geocomplete").geocomplete("resetMarker");
			jQuery("#reset").hide();
			return false;
		});
		*/

		jQuery("#find-left").click(function() {
			jQuery("#geocomplete").val(jQuery("#jform_street").val() + ", " + jQuery("#jform_postalCode").val() + " " + jQuery("#jform_city").val());
			jQuery("#geocomplete").trigger("geocode");
		});

		jQuery("#cp-latlong").click(function() {
			document.getElementById("jform_latitude").value = document.getElementById("tmp_form_latitude").value;
			document.getElementById("jform_longitude").value = document.getElementById("tmp_form_longitude").value;
			test();
		});

		jQuery("#cp-address").click(function() {
			document.getElementById("jform_street").value = document.getElementById("tmp_form_street").value;
			document.getElementById("jform_postalCode").value = document.getElementById("tmp_form_postalCode").value;
			document.getElementById("jform_city").value = document.getElementById("tmp_form_city").value;
			document.getElementById("jform_state").value = document.getElementById("tmp_form_state").value;	
			document.getElementById("jform_country").value = document.getElementById("tmp_form_country").value;
		});

		jQuery("#cp-venue").click(function() {
			var venue = document.getElementById("tmp_form_venue").value;
			if (venue) {
				document.getElementById("jform_venue").value = venue;
			}
		});

		jQuery("#cp-all").click(function() {
			jQuery("#cp-address").click();
			jQuery("#cp-latlong").click();
			jQuery("#cp-venue").click();
		});	

		jQuery('#jform_map').on('keyup keypress blur change', function() {
		    test();
		});

		jQuery('#jform_latitude').on('keyup keypress blur change', function() {
		    test();
		});

		jQuery('#jform_longitude').on('keyup keypress blur change', function() {
		    test();
		});
	});

	jQuery(document).ready(function() {
		jQuery("#venue-geodata").on("click", function() {
			if (jQuery("#venue-geodata").hasClass("pane-toggler-down")) {
				var map = jQuery("#geocomplete").geocomplete("map");
				zoom = map.getZoom();
				center = map.getCenter();
				google.maps.event.trigger(map, 'resize');
				map.setZoom(zoom);
				map.setCenter(center);
			}
		});
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

				<li><?php echo $this->form->getLabel('latitude'); ?>
					<?php echo $this->form->getInput('latitude'); ?></li>

				<li><?php echo $this->form->getLabel('longitude'); ?>
					<?php echo $this->form->getInput('longitude'); ?></li>

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

	<!-- START RIGHT DIV -->
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
					<li><?php echo $field->label; ?>
						<?php echo $field->input; ?></li>
				<?php endforeach; ?>
			</ul>
		</fieldset>
	<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_CUSTOMFIELDS'), 'venue-custom'); ?>
		<fieldset class="panelform">
			<ul class="adminformlist">
				<?php foreach($this->form->getFieldset('custom') as $field): ?>
					<li><?php echo $field->label; ?>
						<?php echo $field->input; ?></li>
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
					<li><?php echo $field->label; ?>
						<?php echo $field->input; ?></li>
				<?php endforeach; ?>
			</ul>
		</fieldset>
	<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_FIELDSET_GEODATA'), 'venue-geodata'); ?>
		<fieldset class="adminform" id="geodata">
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('map'); ?>
					<?php echo $this->form->getInput('map'); ?></li>
			</ul>
			<div class="clr"></div>
			<div id="mapdiv">
				<input id="geocomplete" type="text" size="55" placeholder="<?php echo JText::_( 'COM_JEM_VENUE_ADDRPLACEHOLDER' ); ?>" value="" />
				<input id="find-left" class="geobutton" type="button" value="<?php echo JText::_('COM_JEM_VENUE_ADDR_FINDVENUEDATA');?>" />
				<div class="clr"></div>
				<div class="map_canvas"></div>

				<ul class="adminformlist">
					<li><label><?php echo JText::_('COM_JEM_STREET'); ?></label>
						<input type="text" disabled="disabled" class="readonly" id="tmp_form_street" />
					
						<input type="hidden" class="readonly" id="tmp_form_streetnumber" readonly="readonly" />
						<input type="hidden" class="readonly" id="tmp_form_route" readonly="readonly" />
						</li>

					<li><label><?php echo JText::_('COM_JEM_ZIP'); ?></label>
						<input type="text" disabled="disabled" class="readonly" id="tmp_form_postalCode" /></li>

					<li><label><?php echo JText::_('COM_JEM_CITY'); ?></label>
						<input type="text" disabled="disabled" class="readonly" id="tmp_form_city"/></li>

					<li><label><?php echo JText::_('COM_JEM_STATE'); ?></label>
						<input type="text" disabled="disabled" class="readonly" id="tmp_form_state" /></li>
						
					<li><label><?php echo JText::_('COM_JEM_VENUE'); ?></label>
						<input type="text" disabled="disabled" class="readonly" id="tmp_form_venue" /></li>

					<li><label><?php echo JText::_('COM_JEM_COUNTRY'); ?></label>
						<input type="text" disabled="disabled" class="readonly" id="tmp_form_country" /></li>

					<li><label><?php echo JText::_('COM_JEM_LATITUDE'); ?></label>
						<input type="text" disabled="disabled" class="readonly" id="tmp_form_latitude" /></li>

					<li><label><?php echo JText::_('COM_JEM_LONGITUDE'); ?></label>
						<input type="text" disabled="disabled" class="readonly" id="tmp_form_longitude" /></li>
				</ul>
				<div class="clr"></div>
				<input id="cp-all" class="geobutton" type="button" value="<?php echo JText::_('COM_JEM_VENUE_COPY_DATA'); ?>" style="margin-right: 3em;" />
				<input id="cp-address" class="geobutton" type="button" value="<?php echo JText::_('COM_JEM_VENUE_COPY_ADDRESS'); ?>" />
				<input id="cp-venue" class="geobutton" type="button" value="<?php echo JText::_('COM_JEM_VENUE_COPY_VENUE'); ?>" />
				<input id="cp-latlong" class="geobutton" type="button" value="<?php echo JText::_('COM_JEM_VENUE_COPY_COORDINATES'); ?>" />
			</div>
		</fieldset>
	<?php echo JHtml::_('sliders.end'); ?>
		<input type="hidden" name="task" value="" />
		<input type="hidden" name="author_ip" value="<?php echo $this->item->author_ip; ?>" />

	<!-- END RIGHT DIV -->
	<?php echo JHtml::_( 'form.token' ); ?>
	</div>
	<div class="clr"></div>
</form>
