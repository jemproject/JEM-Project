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

defined('_JEXEC') or die;


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
	window.addEvent('domready', function(){
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

	Joomla.submitbutton = function(task)
	{
		var form = document.getElementById('adminForm');
		var locdescription = <?php echo $this->editor->getContent( 'locdescription' ); ?>
		var validator = document.formvalidator;

		if (task == 'cancel') {
			submitform( task );
			return;
		}

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

		if ( validator.validate(form.venue) === false ) {
	   		alert("<?php echo JText::_( 'COM_JEM_ADD_VENUE', true ); ?>");
	   		validator.handleResponse(false,form.venue);
	   		form.venue.focus();
	   		return false;
   		} else if ( validator.validate(form.street) === false) {
   			alert("<?php echo JText::_( 'COM_JEM_ADD_STREET', true ); ?>");
   			validator.handleResponse(false,form.street);
   			form.street.focus();
   			return false;
		} else if ( validator.validate(form.city) === false) {
  			alert("<?php echo JText::_( 'COM_JEM_ADD_CITY', true ); ?>");
  			validator.handleResponse(false,form.city);
  			form.city.focus();
  			return false;
  			} else if ( validator.validate(form.plz) === false) {
  			alert("<?php echo JText::_( 'COM_JEM_ADD_ZIP', true ); ?>");
  			validator.handleResponse(false,form.plz);
  			form.plz.focus();
  			return false;
		} else if ( validator.validate(form.country) === false) {
   			alert("<?php echo JText::_( 'COM_JEM_ADD_COUNTRY', true ); ?>");
   			validator.handleResponse(false,form.country);
   			form.country.focus();
   			return false;
		} else if ( validator.validate(form.url) === false) {
   			alert("<?php echo JText::_( 'COM_JEM_WRONG_URL_FORMAT', true ); ?>");
//   			validator.handleResponse(false,form.url);
   			return false;
		} else {
			<?php
			echo $this->editor->save( 'locdescription' );
			?>
			submitform( task );
		}
	}
</script>

<form action="<?php echo JRoute::_('index.php?option=com_jem&view=venue'); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

<table style="width:100%">
	<tr>
		<td valign="top">
		<?php echo JHtml::_('tabs.start', 'det-pane', $options); ?>
		<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_VENUE_INFO_TAB'), 'info' ); ?>
		&nbsp;<!-- this is a trick for IE7... otherwise the first table inside the tab is shifted right ! -->

	<table  class="adminform">
		<tr>
			<td>
				<label for="venue">
					<?php echo JText::_( 'COM_JEM_VENUE' ).':'; ?>
				</label>
			</td>
			<td>
				<input class="inputbox required" name="venue" id= "venue" value="<?php echo $this->row->venue; ?>" size="40" maxlength="100" />
			</td>
			<td>
				<label for="published">
					<?php echo JText::_( 'COM_JEM_PUBLISHED' ).':'; ?>
				</label>
			</td>
			<td>
				<?php
				$html = JHTML::_('select.booleanlist', 'published', 'class="inputbox"', $this->row->published );
				echo $html;
				?>
			</td>
		</tr>
		<tr>
			<td>
				<label for="alias">
					<?php echo JText::_( 'COM_JEM_ALIAS' ).':'; ?>
				</label>
			</td>
			<td colspan="3">
				<input class="inputbox" type="text" name="alias" id="alias" size="40" maxlength="100" value="<?php echo $this->row->alias; ?>" />
			</td>
		</tr>
	</table>
			<table class="adminform">
				<tr>
					<td>
						<?php
						echo $this->editor->display( 'locdescription',  $this->row->locdescription, '100%;', '550', '75', '20', array('pagebreak', 'readmore') ) ;
						?>
					</td>
				</tr>
				</table>
				
				<?php echo JHtml::_('tabs.panel',JText::_('COM_JEM_EVENT_ATTACHMENTS_TAB'), 'attachments' ); ?>
				<?php echo $this->loadTemplate('attachments'); ?>
				<?php echo JHtml::_('tabs.end'); ?>
				
			</td>
	
	<td valign="top" width="320px" style="padding: 7px 0 0 5px">
			<?php
			$title = JText::_( 'COM_JEM_DETAILS' );
		echo JHtml::_('sliders.start', 'det-pane', $options);
		echo JHtml::_('sliders.panel', $title, 'details');
			?>
		<table width="100%"	style="border: 1px dashed silver; padding: 5px; margin-bottom: 10px;">
			<?php if ($this->row->id) { ?>
			<tr>
				<td>
					<strong><?php echo JText::_ ( 'COM_JEM_ID' ); ?>:</strong>
				</td>
				<td>
					<?php echo $this->row->id; ?>
				</td>
			</tr>
			<?php } ?>
			<tr>
				<td>
					<strong><?php echo JText::_ ( 'COM_JEM_STATE' ); ?></strong>
				</td>
				<td>
					<?php
					echo $this->row->published > 0 ? JText::_ ( 'COM_JEM_PUBLISHED' ) : ($this->row->published < 0 ? JText::_ ( 'COM_JEM_ARCHIVED' ) : JText::_ ( 'COM_JEM_DRAFT_UNPUBLISHED' ));
					?>
				</td>
			</tr>
			<tr>
				<td>
					<strong><?php echo JText::_ ( 'COM_JEM_REVISED' ); ?></strong>
				</td>
				<td>
					<?php echo $this->row->version . ' ' . JText::_ ( 'COM_JEM_TIMES' ); ?>
				</td>
			</tr>
			<tr>
				<td>
					<strong><?php echo JText::_ ( 'COM_JEM_CREATED_AT' );?></strong>
				</td>
				<td>
					<?php
					if ($this->row->created == $this->nullDate) {
						echo JText::_ ( 'COM_JEM_NEW_EVENT' );
					} else {
						echo JHTML::_ ( 'date', $this->row->created, JText::_ ( 'DATE_FORMAT_LC2' ) );
					}
					?>
				</td>
			</tr>
			
			<tr>
				<td>
					<strong><?php echo JText::_ ( 'COM_JEM_OWNER' );?></strong>
				</td>
				<td>
					<?php
						echo JHTML::_('list.users', 'created_by', $this->row->created_by, 0, NULL, 'name', 0);
					?>
				</td>
			</tr>
			
			<tr>
				<td>
					<strong><?php echo JText::_ ( 'COM_JEM_EDITED_AT' ); ?></strong>
				</td>
				<td>
					<?php
					if ($this->row->modified == $this->nullDate) {
						echo JText::_ ( 'COM_JEM_NOT_MODIFIED' );
					} else {
						echo JHTML::_ ( 'date', $this->row->modified, JText::_ ( 'DATE_FORMAT_LC2' ) );
					}
					?>
				</td>
			</tr>
		</table>
		
		<?php
		$title = JText::_( 'COM_JEM_ADDRESS' );
		echo JHtml::_('sliders.panel', $title, 'address');

		//Set the info image
		$infoimage = JHTML::image('media/com_jem/images/icon-16-hint.png', JText::_( 'COM_JEM_NOTES' ) );
		?>
	<table>
		<tr>
			<td>
				<label for="street">
					<?php echo JText::_( 'COM_JEM_STREET' ).':'; ?>
				</label>
			</td>
			<td>
				<input class="inputbox" name="street" id="street" value="<?php echo $this->row->street; ?>" size="35" maxlength="50" />
			</td>
		</tr>
		<tr>
			<td>
				<label for="plz">
					<?php echo JText::_( 'COM_JEM_ZIP' ).':'; ?>
				</label>
			</td>
			<td>
				<input class="inputbox" name="plz" id="plz" value="<?php echo $this->row->plz; ?>" size="15" maxlength="10" />
			</td>
		</tr>
		<tr>
			<td>
				<label for="city">
					<?php echo JText::_( 'COM_JEM_CITY' ).':'; ?>
				</label>
			</td>
			<td>
				<input class="inputbox" name="city" id="city" value="<?php echo $this->row->city; ?>" size="35" maxlength="50" />
			</td>
		</tr>
		<tr>
			<td>
				<label for="state">
					<?php echo JText::_( 'COM_JEM_STATE' ).':'; ?>
				</label>
			</td>
			<td>
				<input class="inputbox" name="state" id="state" value="<?php echo $this->row->state; ?>" size="35" maxlength="50" />
			</td>
		</tr>
		<tr>
			<td>
				<label for="country">
					<?php echo JText::_( 'COM_JEM_COUNTRY' ).':'; ?>
				</label>
			</td>
			<td>
				<?php echo $this->lists['countries']; ?>
			</td>
		</tr>
		<tr>
			<td>
				<label for="url">
					<?php echo JText::_( 'COM_JEM_WEBSITE' ).':'; ?>
				</label>
			</td>
			<td>
				<input class="inputbox validate-url" name="url" id="url" value="<?php echo $this->row->url; ?>" size="30" maxlength="199" />&nbsp;

				<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NOTES' ); ?>::<?php echo JText::_('COM_JEM_WEBSITE_HINT'); ?>">
					<?php echo $infoimage; ?>
				</span>
			</td>
		</tr>
		<?php if ( $this->settings->showmapserv != 0 ) { ?>
		<tr>
			<td>
				<label for="map">
					<?php echo JText::_( 'COM_JEM_ENABLE_MAP' ).':'; ?>
				</label>
			</td>
			<td>
				 <label for="map0"><?php echo JText::_( 'JNO' ); ?></label>
                <input type="radio" name="map" id="map0" onchange="removerequired();" value="0" <?php echo $this->row->map == 0 ? 'checked="checked"' : ''; ?> class="inputbox" />

              	<label for="map1"><?php echo JText::_( 'JYES' ); ?></label>
              	<input type="radio" name="map" id="map1" onchange="addrequired();" value="1" <?php echo $this->row->map == 1 ? 'checked="checked"' : ''; ?> class="inputbox" />
          		&nbsp;
          		<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NOTES' ); ?>::<?php echo JText::_('COM_JEM_ADDRESS_NOTICE'); ?>">
					<?php echo $infoimage; ?>
				</span>
			</td>
		</tr>
    <tr>
      <td>
        <label for="latitude">
          <?php echo JText::_( 'COM_JEM_LATITUDE' ).':'; ?>
        </label>
      </td>
      <td>
        <input class="inputbox" name="latitude" id="latitude" onchange="removerequired();" value="<?php echo $this->row->latitude; ?>" size="14" maxlength="25" />
              <span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NOTES' ); ?>::<?php echo JText::_('COM_JEM_LATITUDE_HINT'); ?>">
          <?php echo $infoimage; ?>
        </span>
      </td>
    </tr>
    <tr>
      <td>
        <label for="longitude">
          <?php echo JText::_( 'COM_JEM_LONGITUDE' ).':'; ?>
        </label>
      </td>
      <td>
        <input class="inputbox" name="longitude" id="longitude" onchange="removerequired();" value="<?php echo $this->row->longitude; ?>" size="14" maxlength="25" />
              <span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NOTES' ); ?>::<?php echo JText::_('COM_JEM_LONGITUDE_HINT'); ?>">
          <?php echo $infoimage; ?>
        </span>
      </td>
    </tr>
		<?php } ?>
	</table>
	<?php
	$title2 = JText::_( 'COM_JEM_IMAGE' );
	echo JHtml::_('sliders.panel', $title2, 'image');
	?>
	<table>
		<tr>
			<td>
				<label for="locimage">
					<?php echo JText::_( 'COM_JEM_CHOOSE_IMAGE' ).':'; ?>
				</label>
			</td>
			<td>
				<?php
					echo $this->imageselect;
				?>
			</td>
		</tr>
		<tr>
			<td>
			</td>
			<td>
				<img src="../media/system/images/blank.png" name="imagelib" id="imagelib" width="80" height="80" border="2" alt="Preview" />
				<script type="text/javascript">
				if (document.forms[0].a_imagename.value!=''){
					var imname = document.forms[0].a_imagename.value;
					jsimg='../images/jem/venues/' + imname;
					document.getElementById('imagelib').src= jsimg;
				}
				</script>
				<br />
				<br />
			</td>
		</tr>
	</table>
	<?php
	$title3 = JText::_( 'COM_JEM_METADATA_INFORMATION' );
	echo JHtml::_('sliders.panel', $title3, 'metadata');
	?>
	<table>
		<tr>
			<td>
				<label for="metadesc">
					<?php echo JText::_( 'COM_JEM_META_DESCRIPTION' ); ?>:
				</label>
				<br />
				<textarea class="inputbox" cols="40" rows="5" name="meta_description" id="metadesc" style="width:300px;"><?php echo str_replace('&','&amp;',$this->row->meta_description); ?></textarea>
			</td>
		</tr>
		<tr>
			<td>
				<label for="metakey">
					<?php echo JText::_( 'COM_JEM_META_KEYWORDS' ); ?>:
				</label>
				<br />
				<textarea class="inputbox" cols="40" rows="5" name="meta_keywords" id="metakey" style="width:300px;"><?php echo str_replace('&','&amp;',$this->row->meta_keywords); ?></textarea>
			</td>
		</tr>
		<tr>
			<td>
				<input type="button" class="button" value="<?php echo JText::_( 'COM_JEM_ADD_VENUE_CITY' ); ?>" onclick="f=document.adminForm;f.metakey.value=f.venue.value+', '+f.city.value+f.metakey.value;" />
			</td>
		</tr>
	</table>

		<?php
		echo JHtml::_('sliders.end');
		?>
		</td>
	</tr>
</table>

<?php
if ( $this->settings->showmapserv == 0 ) { ?>
	<input type="hidden" name="map" value="0" />
<?php
}
?>
	<?php echo JHTML::_( 'form.token' ); ?>
	<input type="hidden" name="option" value="com_jem" />
<input type="hidden" name="controller" value="venues" />
<input type="hidden" name="view" value="venue" />
<input type="hidden" name="task" value="" />
<?php if ($this->task == 'copy') {?>
	<input type="hidden" name="id" value="" />
	<input type="hidden" name="created" value="" />
	<input type="hidden" name="author_ip" value="" />
	<input type="hidden" name="created_by" value="" />
	<input type="hidden" name="version" value="" />
<?php } else {	?>
	<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
	<input type="hidden" name="created" value="<?php echo $this->row->created; ?>" />
	<input type="hidden" name="author_ip" value="<?php echo $this->row->author_ip; ?>" />
	<input type="hidden" name="version" value="<?php echo $this->row->version;?>" />
<?php } ?>
</form>

<p class="copyright">
	<?php echo JEMAdmin::footer( ); ?>
</p>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>