<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>


<script type="text/javascript">
	window.addEvent('domready', function() {
		var form = document.getElementById('adminForm');
		var map = $('map1');
		setAttribute();
		test();

		if(map.checked == true) {
			addrequired();
		}

		if(map.checked == false) {
			removerequired();
		}

		document.formvalidator.setHandler('url',
			function (value) {
				if(value=="") {
					return true;
				} else {
					regexp = new RegExp('^(http|https|ftp)\:\/\/[a-z0-9\-\.]+\.[a-z]{2,3}(:[a-z0-9]*)?\/?([a-z0-9\-\._\?\,\'\/\\\+&amp;%\$#\=~])*$','i');
					return regexp.test(value);
				}
			}
		);
	});

	function setAttribute()
	{

		var attribute = document.createAttribute("geo-data");
		attribute.nodeValue = "postal_code"
		document.getElementById("postalCode").setAttributeNode(attribute);

		var attribute = document.createAttribute("geo-data");
		attribute.nodeValue = "locality"
		document.getElementById("city").setAttributeNode(attribute);

		var attribute = document.createAttribute("geo-data");
		attribute.nodeValue = "administrative_area_level_1"
		document.getElementById("state").setAttributeNode(attribute);

		var attribute = document.createAttribute("geo-data");
		attribute.nodeValue = "street_address"
		document.getElementById("street").setAttributeNode(attribute);

		var attribute = document.createAttribute("geo-data");
		attribute.nodeValue = "country_short"
		document.getElementById("country").setAttributeNode(attribute);

		var attribute = document.createAttribute("geo-data");
		attribute.nodeValue = "lat"
		document.getElementById("latitude").setAttributeNode(attribute);

		var attribute = document.createAttribute("geo-data");
		attribute.nodeValue = "lng"
		document.getElementById("longitude").setAttributeNode(attribute);
	}

	function test()
	{
		 var handler = function(e) {
			 var form = document.getElementById('adminForm');
			var map = $('map1');

			var streetcheck = $(form.street).hasClass('required');

			if(map.checked == true) {
				var lat = $('latitude');
				var lon = $('longitude');
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

			if(map.checked == false) {
				removerequired();
			}
		};

		document.getElementById('map1').onchange = handler;
		document.getElementById('map1').onkeyup = handler;
		document.getElementById('latitude').onchange = handler;
		document.getElementById('latitude').onkeyup = handler;
		document.getElementById('longitude').onchange = handler;
		document.getElementById('longitude').onkeyup = handler;
	}

	function addrequired() {
		var form = document.getElementById('adminForm');

		$(form.street).addClass('required');
		$(form.postalCode).addClass('required');
		$(form.city).addClass('required');
		$(form.country).addClass('required');
	}

	function removerequired() {
		var form = document.getElementById('adminForm');

		$(form.street).removeClass('required');
		$(form.postalCode).removeClass('required');
		$(form.city).removeClass('required');
		$(form.country).removeClass('required');
	}

	function submitbutton( pressbutton ) {
		if (pressbutton == 'editvenue.cancelvenue') {
			elsubmitform( pressbutton );
			return;
		}

		var form = document.getElementById('adminForm');
		var validator = document.formvalidator;
		var venue = form.venue.value;
		venue.replace(/\s/g,'');

		if ( venue.length==0 ) {
			alert("<?php echo JText::_( 'COM_JEM_ERROR_ADD_VENUE', true ); ?>");
			validator.handleResponse(false,form.venue);
			form.venue.focus();
			return false;
		} else if ( validator.validate(form.street) === false) {
			alert("<?php echo JText::_( 'COM_JEM_ERROR_ADD_STREET', true ); ?>");
			validator.handleResponse(false,form.street);
			form.street.focus();
			return false;
		} else if ( validator.validate(form.city) === false) {
			alert("<?php echo JText::_( 'COM_JEM_ERROR_ADD_CITY', true ); ?>");
			validator.handleResponse(false,form.city);
			form.city.focus();
			return false;
		} else if ( validator.validate(form.postalCode) === false) {
			alert("<?php echo JText::_( 'COM_JEM_ADD_ZIP', true ); ?>");
			validator.handleResponse(false,form.postalCode);
			form.postalCode.focus();
			return false;
		} else if ( validator.validate(form.country) === false) {
			alert("<?php echo JText::_( 'COM_JEM_ERROR_ADD_COUNTRY', true ); ?>");
			validator.handleResponse(false,form.country);
			form.country.focus();
			return false;
		} else if ( validator.validate(form.url) === false) {
			alert("<?php echo JText::_( 'COM_JEM_WRONG_URL_FORMAT', true ); ?>");
			return false;
		} else {
			<?php if ($this->editoruser):
				// JavaScript for extracting editor text
				echo $this->editor->save( 'locdescription' );
			endif;
			?>
			elsubmitform(pressbutton);

			return true;
		}
	}

	//joomla submitform needs form name
	function elsubmitform(pressbutton){
			var form = document.getElementById('adminForm');
			if (pressbutton) {
				form.task.value=pressbutton;
			}
			if (typeof form.onsubmit == "function") {
				form.onsubmit();
			}
			form.submit();
		}

	var tastendruck = false;

	function rechne(restzeichen)
	{
		maximum = <?php echo $this->jemsettings->datdesclimit; ?>

		if (restzeichen.locdescription.value.length > maximum) {
			restzeichen.locdescription.value = restzeichen.locdescription.value.substring(0, maximum)
			links = 0
		} else {
			links = maximum - restzeichen.locdescription.value.length
		}
		restzeichen.zeige.value = links
	}

	function berechne(restzeichen)
	{
		tastendruck = true
		rechne(restzeichen)
	}
</script>

<script>
	jQuery(function(){
		jQuery("#geocomplete").geocomplete({
			map: ".map_canvas",
			details: "form ",
			detailsAttribute: "geo-data",
			types: ['establishment', 'geocode'],
			markerOptions: {
				draggable: true
			}
		});

		jQuery("#geocomplete").bind("geocode:dragged", function(event, latLng){
			jQuery("input[id=latitude]").val(latLng.lat());
			jQuery("input[id=longitude]").val(latLng.lng());
			jQuery("#geocomplete").geocomplete("find", latLng.toString());
			/* option to show the reset-link */
			/* jQuery("#reset").show();*/
		});

		jQuery("#geocomplete").bind("geocode:result", function(event, result){
			//var country = document.getElementById("country").value;
			//document.getElementById("country").value = country;
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
	<form enctype="multipart/form-data" id="adminForm" action="<?php echo JRoute::_('index.php') ?>" method="post" class="form-validate">
		<div class="buttons">
			<button type="button" class="positive" onclick="return submitbutton('editvenue.savevenue')">
				<?php echo JText::_('COM_JEM_SAVE'); ?>
			</button>
			<button type="reset" class="negative" onclick="return submitbutton('editvenue.cancelvenue')">
				<?php echo JText::_('COM_JEM_CANCEL'); ?>
			</button>
		</div>

		<?php if ($this->params->def( 'show_page_title', 1 )) : ?>
		<h1 class="componentheading">
			<?php echo $this->title; ?>
		</h1>
		<?php endif; ?>

		<?php if ($this->params->get('showintrotext')) : ?>
			<div class="description no_space floattext">
				<?php echo $this->params->get('introtext'); ?>
			</div>
		<?php endif; ?>

		<p>&nbsp;</p>
		<p class="clear"></p>

		<fieldset class="jem_fldst_address">
			<legend><?php echo JText::_('COM_JEM_ADDRESS'); ?></legend>

			<div class="jem_venue floattext">
				<label for="venue"><?php echo JText::_( 'COM_JEM_VENUE' ).':'; ?></label>
				<input class="inputbox required" type="text" name="venue" id="venue" value="<?php echo $this->row->venue; ?>" size="55" maxlength="50" />
			</div>

			<div class="jem_street floattext">
				<label for="street"><?php echo JText::_( 'COM_JEM_STREET' ).':'; ?></label>
				<input class="inputbox" type="text" name="street" id="street" value="<?php echo $this->row->street; ?>" size="55" maxlength="50" />
			</div>

			<div class="jem_postalCode floattext">
				<label for="postalCode"><?php echo JText::_( 'COM_JEM_ZIP' ).':'; ?></label>
				<input class="inputbox" type="text" name="postalCode" id="postalCode" value="<?php echo $this->row->postalCode; ?>" size="15" maxlength="10" />
			</div>

			<div class="jem_city floattext">
				<label for="city"><?php echo JText::_( 'COM_JEM_CITY' ).':'; ?></label>
				<input class="inputbox" type="text" name="city" id="city" value="<?php echo $this->row->city; ?>" size="55" maxlength="50" />
			</div>

			<div class="jem_state floattext">
				<label for="state"><?php echo JText::_( 'COM_JEM_STATE' ).':'; ?></label>
				<input class="inputbox" type="text" name="state" id="state" value="<?php echo $this->row->state; ?>" size="55" maxlength="50" />
			</div>

			<div class="jem_country floattext">
				<label for="country"><?php echo JText::_( 'COM_JEM_COUNTRY' ).':'; ?></label>
				<?php echo $this->lists['countries']; ?>
			</div>

			<div class="jem_url floattext">
				<label for="url"><?php echo JText::_( 'COM_JEM_WEBSITE' ).':'; ?></label>
				<input class="inputbox validate-url" name="url" id="url" type="text" value="<?php echo $this->row->url; ?>" size="55" maxlength="199" />&nbsp;
				<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NOTES' ); ?>::<?php echo JText::_('COM_JEM_WEBSITE_HINT'); ?>">
					<?php echo $this->infoimage; ?>
				</span>
			</div>

			<?php if ( $this->jemsettings->showmapserv != 0 ) : ?>
			<div class="jem_map floattext">
				<p>
					<br /><strong><?php echo JText::_( 'COM_JEM_ENABLE_MAP' ).':'; ?></strong>
					<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NOTES' ); ?>::<?php echo JText::_('COM_JEM_ADDRESS_NOTICE'); ?>">
						<?php echo $this->infoimage; ?>
					</span>
				</p>

				<label for="map0"><?php echo JText::_( 'JNO' ); ?></label>
				<input type="radio" name="map" id="map0" onchange="removerequired();" value="0" <?php echo $this->row->map == 0 ? 'checked="checked"' : ''; ?> class="inputbox" />
				<br class="clear" />
				<label for="map1"><?php echo JText::_( 'JYES' ); ?></label>
				<input type="radio" name="map" id="map1" onchange="addrequired();" value="1" <?php echo $this->row->map == 1 ? 'checked="checked"' : ''; ?> class="inputbox" />
			</div>
			<?php endif; ?>
		</fieldset>

		<fieldset class="adminform" id="geodata">
			<legend>Geodata</legend>

			<input id="geocomplete" type="text" placeholder="Type in an address" value="" />
			<input id="find" type="button" value="find" />
			<br><br>
			<div class="map_canvas"></div>

			<a id="reset" href="#" style="display:none;">Reset Marker</a>

			<div class="jem_latitude floattext">
				<label for="latitude"><?php echo JText::_( 'COM_JEM_LATITUDE' ).':'; ?></label>
				<input class="inputbox" name="latitude" id="latitude" type="text" onchange="removerequired();" value="<?php echo $this->row->latitude; ?>" size="15" maxlength="25" />&nbsp;
				<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NOTES' ); ?>::<?php echo JText::_('COM_JEM_LATITUDE_HINT'); ?>">
					<?php echo $this->infoimage; ?>
				</span>
			</div>
			<div class="jem_longitude floattext">
				<label for="longitude"><?php echo JText::_( 'COM_JEM_LONGITUDE' ).':'; ?></label>
				<input class="inputbox" name="longitude" id="longitude" type="text" onchange="removerequired();" value="<?php echo $this->row->longitude; ?>" size="15" maxlength="25" />&nbsp;
				<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NOTES' ); ?>::<?php echo JText::_('COM_JEM_LONGITUDE_HINT'); ?>">
					<?php echo $this->infoimage; ?>
				</span>
			</div>
		</fieldset>

		<?php if (( $this->jemsettings->imageenabled == 2 ) || ($this->jemsettings->imageenabled == 1)) : ?>
		<fieldset class="jem_fldst_image">
			<legend><?php echo JText::_('COM_JEM_IMAGE'); ?></legend>

			<?php
			if ($this->row->locimage) :
				echo JEMOutput::flyer( $this->row, $this->limage, 'venue' );
			else :
				echo JHtml::_('image', 'com_jem/noimage.png', JText::_('COM_JEM_NO_IMAGE'));
			endif;
			?>

			<label for="userfile"><?php echo JText::_('COM_JEM_IMAGE'); ?></label>
			<input class="inputbox <?php echo $this->jemsettings->imageenabled == 2 ? 'required' : ''; ?>" name="userfile" id="userfile" type="file" />
			<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NOTES' ); ?>::<?php echo JText::_('COM_JEM_MAX_IMAGE_FILE_SIZE').' '.$this->jemsettings->sizelimit.' kb'; ?>">
				<?php echo $this->infoimage; ?>
			</span>

			<!--<?php echo JText::_( 'COM_JEM_CURRENT_IMAGE' );	?>
			<?php echo JText::_( 'COM_JEM_SELECTED_IMAGE' ); ?>-->
		</fieldset>
		<?php endif; ?>


		<!-- CUSTOM FIELDS -->
		<fieldset>
			<legend><?php echo JText::_('COM_JEM_CUSTOM_FIELDS'); ?></legend>

			<?php
			for($cr = 1; $cr <= 10; $cr++) {
				$currentRow = $this->row->{'custom'.$cr};
			?>
				<div class="jem_custom<?php echo $cr; ?> floattext">
					<label for="custom<?php echo $cr; ?>">
						<?php echo JText::_('COM_JEM_VENUE_CUSTOM_FIELD'.$cr).':'; ?>
					</label>
					<input type="text" class="inputbox" id="custom<?php echo $cr; ?>" name="custom<?php echo $cr; ?>" value="<?php echo $this->escape($currentRow); ?>" size="65" maxlength="60" />
				</div>
			<?php
			}
			?>
		</fieldset>



		<fieldset class="jem_fldst_description">
			<legend><?php echo JText::_('COM_JEM_DESCRIPTION'); ?></legend>

			<?php
			//wenn usertyp min editor wird editor ausgegeben ansonsten textfeld
			if ( $this->editoruser ) :
				echo $this->editor->display('locdescription', $this->row->locdescription, '655', '400', '70', '15', array('pagebreak', 'readmore') );
			else :
			?>
			<textarea style="width:100%;" rows="10" name="locdescription" class="inputbox" wrap="virtual" onkeyup="berechne(this.form)"></textarea><br />
			<?php echo JText::_('COM_JEM_NO_HTML'); ?><br />
			<input disabled="disabled" value="<?php echo $this->jemsettings->datdesclimit; ?>" size="4" name="zeige" /><?php echo JText::_('COM_JEM_AVAILABLE')." "; ?><br />
			<a href="javascript:rechne(document.adminForm);"><?php echo JText::_('COM_JEM_REFRESH'); ?></a>
			<?php endif; ?>
		</fieldset>

		<fieldset class="jem_fldst_meta">
			<legend><?php echo JText::_('COM_JEM_METADATA_INFORMATION'); ?></legend>

			<div class="jem_box_left">
				<label for="meta_description"><?php echo JText::_( 'COM_JEM_META_DESCRIPTION' ); ?></label>
				<br />
					<?php
					if (! empty ( $this->row->meta_description )) {
						$meta_description = $this->row->meta_description;
					} else {
						$meta_description = $this->jemsettings->meta_description;
					}
					?>
				<textarea class="inputbox" cols="40" rows="5" name="meta_description" id="meta_description" style="width:250px;"><?php echo $meta_description;?></textarea>
			</div>

			<div class="jem_box_right">
				<label for="meta_keywords"><?php echo JText::_( 'COM_JEM_META_KEYWORDS' ); ?></label>
				<br />
				<?php
				if (! empty ( $this->row->meta_keywords )) {
					$meta_keywords = $this->row->meta_keywords;
				} else {
					$meta_keywords = $this->jemsettings->meta_keywords;
				}
				?>

				<textarea class="inputbox" cols="40" rows="5" name="meta_keywords" id="meta_keywords" style="width:250px;"><?php echo $meta_keywords; ?></textarea>
			</div>

			<br class="clear" />

			<input type="button" class="button jem_fright" value="<?php echo JText::_( 'COM_JEM_ADD_VENUE_CITY' ); ?>" onclick="f=document.getElementById('adminForm');f.meta_keywords.value=f.venue.value+', '+f.city.value+f.meta_keywords.value;" />
		</fieldset>

		<?php echo $this->loadTemplate('attachments_edit'); ?>

		<p class="clear">
		<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
		<input type="hidden" name="referer" value="<?php echo @$_SERVER['HTTP_REFERER']; ?>" />
		<input type="hidden" name="created" value="<?php echo $this->row->created; ?>" />
		<input type="hidden" name="curimage" value="<?php echo $this->row->locimage; ?>" />
		<input type="hidden" name="version" value="<?php echo $this->row->version;?>" />
		<input type="hidden" name="mode" value="<?php echo $this->mode; ?>" />
		<?php echo JHtml::_('form.token'); ?>
		<input type="hidden" name="task" value="" />
		<input type="hidden" name="view" value="editvenue">
		</p>
	</form>

	<div class="copyright">
		<?php echo JEMOutput::footer( ); ?>
	</div>
</div>

<?php
//keep session alive while editing
JHtml::_('behavior.keepalive');
?>