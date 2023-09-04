<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// HTMLHelper::_('behavior.tooltip');
// HTMLHelper::_('behavior.formvalidation');
// HTMLHelper::_('behavior.keepalive');

$wa = $this->document->getWebAssetManager();
		$wa->useStyle('jem.geostyle')
			->useScript('keepalive')
			->useScript('form.validate')
			->useScript('jem.attachments')
			->useScript('inlinehelp')
			->useScript('jem.geocomplete');

// Create shortcut to parameters.
$params = $this->state->get('params');
$params = $params->toArray();

# defining values for centering default-map
$location = JemHelper::defineCenterMap($this->form);
?>

<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		if (task == 'venue.cancel' || document.formvalidator.isValid(document.getElementById('venue-form'))) {
			Joomla.submitform(task, document.getElementById('venue-form'));
		}
	}

	// window.addEvent('domready', function() {
	window.onload = function() {		
		setAttribute();
		test();
	}

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

	function test() {
		var form = document.getElementById('venue-form');
		var map = $('#jform_map');
		var streetcheck = $(form.jform_street).hasClass('required');
		// if (map && map.checked == true) {
		if (map && map.is(":checked")) {
			var lat = $('#jform_latitude');
			var lon = $('#jform_longitude');

			if (lat.val() == ('' || 0.000000) || lon.val() == ('' || 0.000000)) {
				if (!streetcheck) {
					addrequired();
				}
			} else {
				if (lat.val() != ('' || 0.000000) && lon.val() != ('' || 0.000000)) {
					removerequired();
				}
			}
			$('#mapdiv').show();
		}

		// if (map && map.checked == false) {
		if (map && !map.is(":checked")) {
			removerequired();
			$('#mapdiv').hide();
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


	// jQuery(function() {
	jQuery(document).ready(function() {
		
			
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
	action="<?php echo Route::_('index.php?option=com_jem&layout=edit&id='.(int) $this->item->id); ?>"
	class="form-validate" method="post" name="adminForm" id="venue-form" enctype="multipart/form-data">

	<div class="row">
		<div class="col-md-7">
			<!-- START OF LEFT DIV -->
			<!-- <div class="width-55 fltlft"> -->

				<?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'info', 'recall' => true, 'breakpoint' => 768]); ?>
				<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'info', Text::_('COM_JEM_VENUE_INFO_TAB')); ?>
					<fieldset class="adminform">
						<legend>
							<?php echo empty($this->item->id) ? Text::_('COM_JEM_NEW_VENUE') : Text::sprintf('COM_JEM_VENUE_DETAILS', $this->item->id); ?>
						</legend>

						<ul class="adminformlist">
							<li><div class="label-form"><?php echo $this->form->renderfield('venue'); ?></div></li>
							<li><div class="label-form"><?php echo $this->form->renderfield('alias'); ?></div></li>
							<li><div class="label-form"><?php echo $this->form->renderfield('street'); ?></div></li>
							<li><div class="label-form"><?php echo $this->form->renderfield('postalCode'); ?></div></li>
							<li><div class="label-form"><?php echo $this->form->renderfield('city'); ?></div></li>
							<li><div class="label-form"><?php echo $this->form->renderfield('state'); ?></div></li>
							<li><div class="label-form"><?php echo $this->form->renderfield('country'); ?></div></li>
							<li><div class="label-form"><?php echo $this->form->renderfield('latitude'); ?></div></li>
							<li><div class="label-form"><?php echo $this->form->renderfield('longitude'); ?></div></li>
							<li><div class="label-form"><?php echo $this->form->renderfield('url'); ?></div></li>
						</ul>
						<div class="clr"></div>
						<div>
							<?php echo $this->form->getLabel('locdescription'); ?>
							<div class="clr"></div>
							<?php echo $this->form->getInput('locdescription'); ?>
						</div>
					</fieldset>
					<?php echo HTMLHelper::_('uitab.endTab'); ?>
					<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'attachments', Text::_('COM_JEM_EVENT_ATTACHMENTS_TAB')); ?>

					<?php echo $this->loadTemplate('attachments'); ?>
				<?php echo HTMLHelper::_('uitab.endTab'); ?>

				<!-- END OF LEFT DIV -->
			<!-- </div> -->
		</div>
		<div class="col-md-5">
			<!-- START RIGHT DIV -->
			<!-- <div class="width-40 fltrt"> -->

				<?php //echo HTMLHelper::_('sliders.start', 'venue-sliders-'.$this->item->id, array('useCookie'=>1)); ?>
				<?php //echo HTMLHelper::_('sliders.panel', Text::_('COM_JEM_FIELDSET_PUBLISHING'), 'publishing-details'); ?>
				<div class="accordion" id="accordionVenueForm">
					<div class="accordion-item">
						<h2 class="accordion-header" id="publishing-details-header">
						<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#publishing-details" aria-expanded="true" aria-controls="publishing-details">
							<?php echo Text::_('COM_JEM_FIELDSET_PUBLISHING'); ?>
						</button>
						</h2>
						<div id="publishing-details" class="accordion-collapse collapse show" aria-labelledby="publishing-details-header" data-bs-parent="#accordionVenueForm">
							<div class="accordion-body">
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
							</div>
						</div>
					</div>
					<div class="accordion-item">
						<h2 class="accordion-header" id="venue-custom-header">
						<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#venue-custom" aria-expanded="true" aria-controls="venue-custom">
							<?php echo Text::_('COM_JEM_CUSTOMFIELDS'); ?>
						</button>
						</h2>
						<div id="venue-custom" class="accordion-collapse collapse" aria-labelledby="venue-custom-header" data-bs-parent="#accordionVenueForm">
							<div class="accordion-body">
								<fieldset class="panelform">
									<ul class="adminformlist">
										<?php foreach($this->form->getFieldset('custom') as $field): ?>
											<li><?php echo $field->label; ?>
												<?php echo $field->input; ?></li>
										<?php endforeach; ?>
									</ul>
								</fieldset>
							</div>
						</div>
					</div>
					<div class="accordion-item">
						<h2 class="accordion-header" id="image-event-header">
						<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#image-event" aria-expanded="true" aria-controls="image-event">
							<?php echo Text::_('COM_JEM_IMAGE'); ?>
						</button>
						</h2>
						<div id="image-event" class="accordion-collapse collapse" aria-labelledby="image-event-header" data-bs-parent="#accordionVenueForm">
							<div class="accordion-body">
								<fieldset class="panelform">
									<ul class="adminformlist">
										<li><?php echo $this->form->getLabel('locimage'); ?>
											<?php echo $this->form->getInput('locimage'); ?>
										</li>
									</ul>
								</fieldset>
							</div>
						</div>
					</div>
					<div class="accordion-item">
						<h2 class="accordion-header" id="meta-event-header">
						<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#meta-event" aria-expanded="true" aria-controls="meta-event">
							<?php echo Text::_('COM_JEM_METADATA_INFORMATION'); ?>
						</button>
						</h2>
						<div id="meta-event" class="accordion-collapse collapse" aria-labelledby="meta-event-header" data-bs-parent="#accordionVenueForm">
							<div class="accordion-body">
								<fieldset class="panelform">
									<input type="button" class="btn btn-primary" value="<?php echo Text::_( 'COM_JEM_ADD_VENUE_CITY' ); ?>" onclick="meta()" />
									<ul class="adminformlist">
										<?php foreach($this->form->getFieldset('meta') as $field): ?>
											<li><?php echo $field->label; ?>
												<?php echo $field->input; ?></li>
										<?php endforeach; ?>
									</ul>
								</fieldset>
							</div>
						</div>
					</div>
					<div class="accordion-item">
						<h2 class="accordion-header" id="venue-geodata-header">
						<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#venue-geodata" aria-expanded="true" aria-controls="venue-geodata">
							<?php echo Text::_('COM_JEM_FIELDSET_GEODATA'); ?>
						</button>
						</h2>
						<div id="venue-geodata" class="accordion-collapse collapse" aria-labelledby="venue-geodata-header" data-bs-parent="#accordionVenueForm">
							<div class="accordion-body">
								<fieldset class="adminform" id="geodata">
									<ul class="adminformlist">
										<li><?php echo $this->form->getLabel('map'); ?>
											<?php echo $this->form->getInput('map'); ?></li>
									</ul>
									<div class="clr"></div>
									<div id="mapdiv">
										<input id="geocomplete" class="readonly form-control valid" type="text" size="55" placeholder="<?php echo Text::_( 'COM_JEM_VENUE_ADDRPLACEHOLDER' ); ?>" value="" />
										<input id="find-left" class="geobutton btn btn-primary btn-margin" type="button" value="<?php echo Text::_('COM_JEM_VENUE_ADDR_FINDVENUEDATA');?>" />
										<div class="clr"></div>
										<div class="map_canvas"></div>
										<ul class="adminformlist label-button-line">
											<li><label><?php echo Text::_('COM_JEM_STREET'); ?></label>
											<div class="geodata-info">
												<input type="text" disabled="disabled" class="readonly form-control valid" id="tmp_form_street" />
												<input type="hidden" class="readonly" id="tmp_form_streetnumber" readonly="readonly" />
												<input type="hidden" class="readonly form-control valid" id="tmp_form_route" readonly="readonly" />
											</div>
												</li>
											<li><label><?php echo Text::_('COM_JEM_ZIP'); ?></label>
											<div class="geodata-info">
												<input type="text" disabled="disabled" class="readonly form-control valid" id="tmp_form_postalCode" /></div>
											</li>
											<li><label><?php echo Text::_('COM_JEM_CITY'); ?></label>
											<div class="geodata-info">
												<input type="text" disabled="disabled" class="readonly form-control valid" id="tmp_form_city"/></div>
											</li>
											<li><label><?php echo Text::_('COM_JEM_STATE'); ?></label>
											<div class="geodata-info">
												<input type="text" disabled="disabled" class="readonly form-control valid" id="tmp_form_state" /></div>
											</li>
											<li><label><?php echo Text::_('COM_JEM_VENUE'); ?></label>
											<div class="geodata-info">
												<input type="text" disabled="disabled" class="readonly form-control valid" id="tmp_form_venue" /></div>
											</li>
											<li><label><?php echo Text::_('COM_JEM_COUNTRY'); ?></label>
											<div class="geodata-info">
												<input type="text" disabled="disabled" class="readonly form-control valid" id="tmp_form_country" /></div>
											</li>
											<li><label><?php echo Text::_('COM_JEM_LATITUDE'); ?></label>
											<div class="geodata-info">
												<input type="text" disabled="disabled" class="readonly form-control valid" id="tmp_form_latitude" /></div>
											</li>
											<li><label><?php echo Text::_('COM_JEM_LONGITUDE'); ?></label>
											<div class="geodata-info">
												<input type="text" disabled="disabled" class="readonly form-control valid" id="tmp_form_longitude" /></li>
											 </div>
										</ul>
										<div class="clr"></div>
										<input id="cp-all" class="geobutton btn btn-primary btn-margin" type="button" value="<?php echo Text::_('COM_JEM_VENUE_COPY_DATA'); ?>" style="margin-right: 3em;" />
										<input id="cp-address" class="geobutton btn btn-primary btn-margin" type="button" value="<?php echo Text::_('COM_JEM_VENUE_COPY_ADDRESS'); ?>" />
										<input id="cp-venue" class="geobutton btn btn-primary btn-margin" type="button" value="<?php echo Text::_('COM_JEM_VENUE_COPY_VENUE'); ?>" />
										<input id="cp-latlong" class="geobutton btn btn-primary btn-margin" type="button" value="<?php echo Text::_('COM_JEM_VENUE_COPY_COORDINATES'); ?>" />
									</div>
								</fieldset>
							</div>
						</div>
					</div>
				</div>
				<input type="hidden" name="task" value="" />
				<input type="hidden" name="author_ip" value="<?php echo $this->item->author_ip; ?>" />

				<!-- END RIGHT DIV -->
				<?php echo HTMLHelper::_( 'form.token' ); ?>
			<!-- </div> -->
		</div>
	</div>
	<div class="clr"></div>
</form>
