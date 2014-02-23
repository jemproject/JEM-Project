<?php
/**
 * @version 1.9.6
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

//JHtml::_('behavior.tooltip');
JHtml::_('behavior.formvalidation');
JHtml::_('behavior.keepalive');
jimport( 'joomla.html.html.tabs' );

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


	function setAttribute(){
	    var postalCode = document.getElementById("jform_postalCode");
	    postalCode.setAttribute("geo-data", "postal_code");

	    var locality = document.getElementById("jform_city");
	    locality.setAttribute("geo-data", "locality");

	    var state = document.getElementById("jform_state");
	    state.setAttribute("geo-data", "administrative_area_level_1");

	    var street = document.getElementById("jform_street");
	    street.setAttribute("geo-data", "street_address");

	    var lat = document.getElementById("jform_latitude");
	    lat.setAttribute("geo-data", "lat");

	    var lng = document.getElementById("jform_longitude");
	    lng.setAttribute("geo-data", "lng");
	}

	function meta(){
		f=document.getElementById('venue-form');
		f.jform_meta_keywords.value=f.jform_venue.value+', '+f.jform_city.value+f.jform_meta_keywords.value;
	}

	function test(){
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

<div id="jem" class="jem_editvenue">
	<div class="edit item-page<?php echo $this->pageclass_sfx; ?>">
		<?php if ($params->get('show_page_heading')) : ?>
	<h1>
		<?php echo $this->escape($params->get('page_heading')); ?>
	</h1>
		<?php endif; ?>

<form action="<?php echo JRoute::_('index.php?option=com_jem&a_id='.(int) $this->item->id); ?>" class="form-validate" method="post" name="adminForm" id="venue-form" enctype="multipart/form-data">
	<div class="buttons btn-group">
		<button type="button" class="positive" onclick="Joomla.submitbutton('venue.save')">
			<?php echo JText::_('JSAVE') ?>
		</button>
		<button type="button" class="negative" onclick="Joomla.submitbutton('venue.cancel')">
			<?php echo JText::_('JCANCEL') ?>
		</button>
	</div>

	<?php if ($this->params->def( 'show_page_title', 1 )) : ?>
	<h1 class="componentheading">
	<?php echo empty($this->item->id) ? JText::_('COM_JEM_EDITVENUE_VENUE_ADD') : JText::sprintf('COM_JEM_EDITVENUE_VENUE_EDIT', $this->item->venue); ?>
	</h1>
	<?php endif; ?>

	<?php if ($this->params->get('showintrotext')) : ?>
	<div class="description no_space floattext">
	<?php echo $this->params->get('introtext'); ?>
	</div>
	<?php endif; ?>
	<p>&nbsp;</p>

	<?php echo JHtml::_('tabs.start', 'venueTab', $options); ?>
	<!--  VENUE-DETAILS TAB -->
	<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EDITVENUE_INFO_TAB'), 'venue-details'); ?>

	<fieldset>
	<legend><?php echo JText::_('COM_JEM_EDITVENUE_DETAILS_LEGEND'); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('venue');?><?php echo $this->form->getInput('venue'); ?></li>
			<?php if (is_null($this->item->id)):?>
			<li><?php echo $this->form->getLabel('alias'); ?><?php echo $this->form->getInput('alias'); ?></li>
			<?php endif; ?>
			<li><?php echo $this->form->getLabel('street'); ?><?php echo $this->form->getInput('street'); ?></li>
			<li><?php echo $this->form->getLabel('postalCode'); ?><?php echo $this->form->getInput('postalCode'); ?></li>
			<li><?php echo $this->form->getLabel('city'); ?><?php echo $this->form->getInput('city'); ?></li>
			<li><?php echo $this->form->getLabel('state'); ?><?php echo $this->form->getInput('state'); ?></li>
			<li><?php echo $this->form->getLabel('country'); ?><?php echo $this->form->getInput('country'); ?></li>
			<li><?php echo $this->form->getLabel('url'); ?><?php echo $this->form->getInput('url'); ?></li>
		</ul>
		<div class="clr"></div>
			<?php echo $this->form->getLabel('locdescription'); ?>
		<div class="clr"><br /></div>
			<?php echo $this->form->getInput('locdescription'); ?>
	</fieldset>

	<!-- VENUE-GEODATA-->
	<fieldset class="adminform" id="geodata">
	<legend><?php echo JText::_('COM_JEM_GEODATA'); ?></legend>
		<input id="geocomplete" type="text" placeholder="<?php echo JText::_( 'COM_JEM_VENUE_ADDRPLACEHOLDER' ); ?>" value="" />
      	<input id="find" type="button" value="find" />
      	<br><br>
 		<div class="map_canvas"></div>
      	<a id="reset" href="#" style="display:none;">Reset Marker</a>
	</fieldset>
	<fieldset class="adminform">
		<div class="control-group">
		<div class="control-label"><?php echo $this->form->getLabel('latitude'); ?></div>
		<div class="controls"><?php echo $this->form->getInput('latitude'); ?></div>
		</div>

		<div class="control-group">
		<div class="control-label"><?php echo $this->form->getLabel('longitude'); ?></div>
		<div class="controls"><?php echo $this->form->getInput('longitude'); ?></div>
		</div>

		<div class="control-group">
		<div class="control-label"><?php echo $this->form->getLabel('map'); ?></div>
		<div class="controls"><?php echo $this->form->getInput('map'); ?></div>
		</div>
	</fieldset>

	<!-- META -->
	<fieldset class="">
	<legend><?php echo JText::_('COM_JEM_META_HANDLING'); ?></legend>
			<input type="button" class="button" value="<?php echo JText::_( 'COM_JEM_ADD_VENUE_CITY' ); ?>" onclick="meta()" />
			<?php foreach($this->form->getFieldset('meta') as $field): ?>
			<div class="control-group">
				<div class="control-label"><?php echo $field->label; ?></div>
				<div class="controls"><?php echo $field->input; ?></div>
			</div>
			<?php endforeach; ?>
	</fieldset>

	<!-- ATTACHMENTS TAB -->
	<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EDITVENUE_ATTACHMENTS_TAB'), 'venue-attachments'); ?>
	<?php echo $this->loadTemplate('attachments'); ?>
	<!-- OTHER TAB -->
	<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EDITVENUE_OTHER_TAB'), 'venue-other' ); ?>
	<?php echo $this->loadTemplate('other'); ?>
	<?php echo JHtml::_('tabs.end'); ?>

	<?php echo JHtml::_('form.token'); ?>

	<div class="clearfix"></div>
		<input id="country" name="country" geo-data="country_short" type="hidden" value="">
		<input type="hidden" name="author_ip" value="<?php echo $this->item->author_ip; ?>" />
		<input type="hidden" name="task" value="" />
		<input type="hidden" name="return" value="<?php echo $this->return_page;?>" />
		<?php echo JHtml::_('form.token'); ?>
</form>
</div>
</div>