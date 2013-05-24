<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

// no direct access
defined( '_JEXEC' ) or die;
?>

<script type="text/javascript">
	window.addEvent('domready', function() { 
		var form = document.getElementById('adminForm');
		var map = $('map1');
		
		if(map && map.checked) {
			addrequired();
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
	
	function addrequired() {
		
		var form = document.getElementById('adminForm');
		
		$(form.street).addClass('required');
		$(form.plz).addClass('required');
		$(form.city).addClass('required');
		$(form.country).addClass('required');
	}
	
	function removerequired() {
		
		var form = document.getElementById('adminForm');
		
		$(form.street).removeClass('required');
		$(form.plz).removeClass('required');
		$(form.city).removeClass('required');
		$(form.country).removeClass('required');
	}

	function submitbutton( pressbutton ) {

		if (pressbutton == 'cancelvenue') {
			elsubmitform( pressbutton );
			return;
		}

		var form = document.getElementById('adminForm');
		var validator = document.formvalidator;
		var venue = form.venue.value;
		venue.replace(/\s/g,'');
		
		var map = $('map1');
		var streetcheck = $(form.street).hasClass('required');
	
		//workaround cause validate strict doesn't allow and operator
		//and ie doesn't understand CDATA properly
		if (map && map.checked) {
			var lat = $('latitude');
			var lon = $('longitude');
			if(lat.value == '') {  
				if(lon.value == '') {
					if(!streetcheck) {  
						addrequired();
					}
				}
			} else {
				//if coordinates are given remove check for address
				removerequired();
			}
		}

		if (map && !map.checked) {
			if(streetcheck) {  
				removerequired();
			}
		}

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
		} else if ( validator.validate(form.plz) === false) {
  			alert("<?php echo JText::_( 'COM_JEM_ADD_ZIP', true ); ?>");
  			validator.handleResponse(false,form.plz);
  			form.plz.focus();
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


	var tastendruck = false
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


<div id="jem" class="jem_editvenue">
    <form enctype="multipart/form-data" id="adminForm" action="<?php echo JRoute::_('index.php') ?>" method="post" class="form-validate">
       <div class="buttons">
  			<button type="button" class="positive" onclick="return submitbutton('savevenue')">
  				<?php echo JText::_('COM_JEM_SAVE'); ?>
  			</button>
  			<button type="reset" class="negative" onclick="return submitbutton('cancelvenue')">
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

            <div class="jem_plz floattext">
                <label for="plz"><?php echo JText::_( 'COM_JEM_ZIP' ).':'; ?></label>
                <input class="inputbox" type="text" name="plz" id="plz" value="<?php echo $this->row->plz; ?>" size="15" maxlength="10" />
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
            <?php endif; ?>

        </fieldset>

      	<?php	if (( $this->jemsettings->imageenabled == 2 ) || ($this->jemsettings->imageenabled == 1)) :	?>
      	<fieldset class="jem_fldst_image">

            <legend><?php echo JText::_('COM_JEM_IMAGE'); ?></legend>

    		<?php
            if ($this->row->locimage) :
    				echo JEMOutput::flyer( $this->row, $this->limage, 'venue' );
    		else :
      		    echo JHTML::_('image', 'media/com_jem/images/noimage.png', JText::_('COM_JEM_NO_IMAGE'));
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

        		<?php	endif; ?>

      	</fieldset>

      	<fieldset class="jem_fldst_meta">

          	<legend><?php echo JText::_('COM_JEM_METADATA_INFORMATION'); ?></legend>

            <div class="jem_box_left">
              	<label for="metadesc"><?php echo JText::_( 'COM_JEM_META_DESCRIPTION' ); ?></label>
          		<textarea class="inputbox" cols="40" rows="5" name="meta_description" id="metadesc" style="width:250px;"></textarea>
            </div>

            <div class="jem_box_right">
        		<label for="metakey"><?php echo JText::_( 'COM_JEM_META_KEYWORDS' ); ?></label>
        		<textarea class="inputbox" cols="40" rows="5" name="meta_keywords" id="metakey" style="width:250px;"></textarea>
            </div>

            <br class="clear" />
            
    		<input type="button" class="button jem_fright" value="<?php echo JText::_( 'COM_JEM_ADD_VENUE_CITY' ); ?>" onclick="f=document.getElementById('adminForm');f.metakey.value=f.venue.value+', '+f.city.value+f.metakey.value;" />

      	</fieldset>
      	
      	<?php echo $this->loadTemplate('attachments'); ?>
      	
<!--  removed to avoid double posts in ie7
      	<div class="jem_save_buttons floattext">
    		<button type="button" onclick="return submitbutton('savevenue')">
    			<?php echo JText::_('COM_JEM_SAVE'); ?>
    		</button>
    		<button type="reset" onclick="return submitbutton('cancelvenue')">
    			<?php echo JText::_('COM_JEM_CANCEL'); ?>
    		</button>
		</div>
-->		
		<p class="clear">
      	<input type="hidden" name="option" value="com_jem" />
      	<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
      	<input type="hidden" name="referer" value="<?php echo @$_SERVER['HTTP_REFERER']; ?>" />
      	<input type="hidden" name="created" value="<?php echo $this->row->created; ?>" />
      	<input type="hidden" name="curimage" value="<?php echo $this->row->locimage; ?>" />
      	<input type="hidden" name="version" value="<?php echo $this->row->version;?>" />
        <input type="hidden" name="mode" value="<?php echo $this->mode; ?>" />
      	<?php echo JHTML::_( 'form.token' ); ?>
      	<input type="hidden" name="task" value="" />
      	</p>

    </form>

    <p class="copyright">
        <?php echo JEMOutput::footer( ); ?>
    </p>

</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>