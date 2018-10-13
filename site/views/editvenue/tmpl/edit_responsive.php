<?php
/**
 * @version 2.3.0-dev1
 * @package JEM
 * @copyright (C) 2013-2018 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

//JHtml::_('behavior.tooltip');
JHtml::_('behavior.formvalidation');
JHtml::_('behavior.keepalive');
jimport('joomla.html.html.tabs');

// Create shortcut to parameters.
$params		= $this->item->params;
//$settings = json_decode($this->item->attribs);

$options = array(
    'onActive' => 'function(title, description){
        description.setStyle("display", "block");
        title.addClass("open").removeClass("closed");
    }',
    'onBackground' => 'function(title, description){
        description.setStyle("display", "none");
        title.addClass("closed").removeClass("open");
    }',
    'startOffset' => 0,  // 0 starts on the first tab, 1 starts the second, etc...
    'useCookie' => true, // this must not be a string. Don't use quotes.
);

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
</script>
<script type="text/javascript">
	window.addEvent('domready', function(){
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

	function addrequired(){
		var form = document.getElementById('venue-form');

		$(form.jform_street).addClass('required');
		$(form.jform_postalCode).addClass('required');
		$(form.jform_city).addClass('required');
		$(form.jform_country).addClass('required');
	}

	function removerequired(){
		var form = document.getElementById('venue-form');

		$(form.jform_street).removeClass('required');
		$(form.jform_postalCode).removeClass('required');
		$(form.jform_city).removeClass('required');
		$(form.jform_country).removeClass('required');
	}

	jQuery(function(){
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
</script>

<div id="jem" class="jem_editvenue<?php echo $this->pageclass_sfx; ?>">
	<div class="edit item-page">
		<?php if ($params->get('show_page_heading')) : ?>
		<h1>
			<?php echo $this->escape($params->get('page_heading')); ?>
		</h1>
		<?php endif; ?>

		<form action="<?php echo JRoute::_('index.php?option=com_jem&a_id=' . (int)$this->item->id); ?>" class="form-validate" method="post" name="adminForm" id="venue-form" enctype="multipart/form-data">
			<div class="buttons btn-group">
				<button type="button" class="positive btn btn-success" onclick="Joomla.submitbutton('venue.save')"><?php echo JText::_('JSAVE') ?></button>
				<button type="button" class="negative btn" onclick="Joomla.submitbutton('venue.cancel')"><?php echo JText::_('JCANCEL') ?></button>
			</div>

			<?php if ($this->params->get('showintrotext')) : ?>
			<div class="description no_space floattext">
				<?php echo $this->params->get('introtext'); ?>
			</div>
			<?php endif; ?>

			<?php echo JHtml::_('tabs.start', 'venueTab', $options); ?>

			<!--  VENUE-DETAILS TAB -->
			<?php echo JHtml::_('tabs.panel', JText::_('COM_JEM_EDITVENUE_INFO_TAB'), 'venue-details'); ?>

			<fieldset>
				<legend><?php echo JText::_('COM_JEM_EDITVENUE_DETAILS_LEGEND'); ?></legend>
				<dl class="adminformlist jem-dl">
					<dt><?php echo $this->form->getLabel('venue'); ?></dt><dd><?php echo $this->form->getInput('venue'); ?></dd>
					<?php if (is_null($this->item->id)) : ?>
					<dt><?php echo $this->form->getLabel('alias'); ?></dt><dd><?php echo $this->form->getInput('alias'); ?></dd>
					<?php endif; ?>
					<dt><?php echo $this->form->getLabel('street'); ?></dt><dd><?php echo $this->form->getInput('street'); ?></dd>
					<dt><?php echo $this->form->getLabel('postalCode'); ?></dt><dd><?php echo $this->form->getInput('postalCode'); ?></dd>
					<dt><?php echo $this->form->getLabel('city'); ?></dt><dd><?php echo $this->form->getInput('city'); ?></dd>
					<dt><?php echo $this->form->getLabel('state'); ?></dt><dd><?php echo $this->form->getInput('state'); ?></dd>
					<dt><?php echo $this->form->getLabel('country'); ?></dt><dd><?php echo $this->form->getInput('country'); ?></dd>
					<dt><?php echo $this->form->getLabel('latitude'); ?></dt><dd><?php echo $this->form->getInput('latitude'); ?></dd>
					<dt><?php echo $this->form->getLabel('longitude'); ?></dt><dd><?php echo $this->form->getInput('longitude'); ?></dd>
					<dt><?php echo $this->form->getLabel('url'); ?></dt><dd><?php echo $this->form->getInput('url'); ?></dd>
					<dt><?php echo $this->form->getLabel('published'); ?></dt><dd><?php echo $this->form->getInput('published'); ?></dd>
				</dl>
        <div style="clear: both;"><br /></div>
        <div>
          <?php echo $this->form->getLabel('locdescription'); ?>        
          <?php echo $this->form->getInput('locdescription'); ?>
        </div>
			</fieldset>
      <p>&nbsp;</p>

			<!-- VENUE-GEODATA-->
			<fieldset class="adminform" id="geodata">
				<dl class="adminformlist jem-dl">
					<dt><?php echo $this->form->getLabel('map'); ?></dt><dd><?php echo $this->form->getInput('map'); ?></dd>
				</dl>
        <div style="clear: both;"></div>
        
				<div id="mapdiv">
        <div class="jem-row jem-justify-start">
					<div><input id="geocomplete" type="text" size="55" placeholder="<?php echo JText::_( 'COM_JEM_VENUE_ADDRPLACEHOLDER' ); ?>" value="" /></div>
					<div><input id="find-left" class="btn" type="button" value="<?php echo JText::_('COM_JEM_VENUE_ADDR_FINDVENUEDATA'); ?>" /></div>
        </div>
          
        <div class="map_canvas"></div>

        <dl class="adminformlist jem-dl">
          <dt><label><?php echo JText::_('COM_JEM_STREET'); ?></label></dt>
          <dd>
            <input type="text" disabled="disabled" class="readonly" id="tmp_form_street" readonly="readonly" />
            <input type="hidden" class="readonly" id="tmp_form_streetnumber" readonly="readonly" />
            <input type="hidden" class="readonly" id="tmp_form_route" readonly="readonly" />
          </dd>
          <dt><label><?php echo JText::_('COM_JEM_ZIP'); ?></label></dt>
          <dd><input type="text" disabled="disabled" class="readonly" id="tmp_form_postalCode" readonly="readonly" /></dd>
          <dt><label><?php echo JText::_('COM_JEM_CITY'); ?></label></dt>
          <dd><input type="text" disabled="disabled" class="readonly" id="tmp_form_city" readonly="readonly" /></dd>
          <dt><label><?php echo JText::_('COM_JEM_STATE'); ?></label></dt>
          <dd><input type="text" disabled="disabled" class="readonly" id="tmp_form_state" readonly="readonly" /></dd>
          <dt><label><?php echo JText::_('COM_JEM_VENUE'); ?></label></dt>
          <dd><input type="text" disabled="disabled" class="readonly" id="tmp_form_venue" readonly="readonly" /></dd>
          <dt><label><?php echo JText::_('COM_JEM_COUNTRY'); ?></label></dt>
          <dd><input type="text" disabled="disabled" class="readonly" id="tmp_form_country" readonly="readonly" /></dd>
          <dt><label><?php echo JText::_('COM_JEM_LATITUDE'); ?></label></dt>
          <dd><input type="text" disabled="disabled" class="readonly" id="tmp_form_latitude" readonly="readonly" /></dd>
          <dt><label><?php echo JText::_('COM_JEM_LONGITUDE'); ?></label></dt>
          <dd><input type="text" disabled="disabled" class="readonly" id="tmp_form_longitude" readonly="readonly" /></dd>
        </dl>

        <div style="clear: both;"><br /></div>
        <div class="jem-row jem-justify-start">
          <input id="cp-all"     class="btn" type="button" value="<?php echo JText::_('COM_JEM_VENUE_COPY_DATA'); ?>" />
          <input id="cp-address" class="btn" type="button" value="<?php echo JText::_('COM_JEM_VENUE_COPY_ADDRESS'); ?>" />
          <input id="cp-venue"   class="btn" type="button" value="<?php echo JText::_('COM_JEM_VENUE_COPY_VENUE'); ?>" />
          <input id="cp-latlong" class="btn" type="button" value="<?php echo JText::_('COM_JEM_VENUE_COPY_COORDINATES'); ?>" />
        </div>
				</div>
			</fieldset>
      <p>&nbsp;</p>

			<!-- META -->
			<fieldset class="">
				<legend><?php echo JText::_('COM_JEM_META_HANDLING'); ?></legend>
				<input type="button" class="button btn" value="<?php echo JText::_('COM_JEM_ADD_VENUE_CITY'); ?>" onclick="meta()" />
        <p>&nbsp;</p>
				<?php foreach ($this->form->getFieldset('meta') as $field) : ?>
					<dl class="jem-dl">
						<dt class="control-label"><?php echo $field->label; ?></dt>
						<dd class="controls"><?php echo $field->input; ?></dd>
					</dl>
				<?php endforeach; ?>
			</fieldset>

			<!-- ATTACHMENTS TAB -->
			<?php if (!empty($this->item->attachments) || ($this->jemsettings->attachmentenabled != 0)) : ?>
			<?php echo JHtml::_('tabs.panel', JText::_('COM_JEM_EDITVENUE_ATTACHMENTS_TAB'), 'venue-attachments'); ?>
			<?php echo $this->loadTemplate('attachments_responsive'); ?>
			<?php endif; ?>

			<!-- OTHER TAB -->
			<?php echo JHtml::_('tabs.panel', JText::_('COM_JEM_EDITVENUE_OTHER_TAB'), 'venue-other' ); ?>
			<?php echo $this->loadTemplate('other_responsive'); ?>

			<?php echo JHtml::_('tabs.end'); ?>

			<div class="clearfix"></div>
			<input type="hidden" name="country" id="country" geo-data="country_short" value="">
			<input type="hidden" name="author_ip" value="<?php echo $this->item->author_ip; ?>" />
			<input type="hidden" name="task" value="" />
			<input type="hidden" name="return" value="<?php echo $this->return_page; ?>" />
			<?php echo JHtml::_('form.token'); ?>
		</form>
	</div>

	<div class="copyright">
		<?php echo JemOutput::footer(); ?>
	</div>
</div>
