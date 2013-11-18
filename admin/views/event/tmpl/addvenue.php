<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * 
 * @todo add/change/remove this code
 */

defined('_JEXEC') or die;
?>

<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		var form = document.adminForm;

		if (form.venue.value == ""){
			alert( "<?php echo JText::_( 'COM_JEM_ADD_VENUE' ); ?>" );
			form.venue.focus();
		} else if (form.city.value == "" && form.map.value == "1"){
			alert( "<?php echo JText::_( 'COM_JEM_ADD_CITY' ); ?>" );
			form.city.focus();
		} else if (form.street.value == "" && form.map.value == "1"){
			alert( "<?php echo JText::_( 'COM_JEM_ADD_STREET' ); ?>" );
			form.street.focus();
		} else if (form.postalCode.value == "" && form.map.value == "1"){
			alert( "<?php echo JText::_( 'COM_JEM_ADD_ZIP' ); ?>" );
			form.postalCode.focus();
		} else if (form.country.value == "" && form.map.value == "1"){
			alert( "<?php echo JText::_( 'COM_JEM_ADD_COUNTRY' ); ?>" );
			form.country.focus();
		} else {
			<?php
			echo $this->editor->save( 'locdescription' );
			?>
			submitform( task );
		}
	}
</script>

<?php
//Set the info image
$infoimage = JHtml::_('image', 'com_jem/icon-16-hint.png', NULL, NULL, true);
?>

<form action="<?php echo $this->request_url; ?>" method="post" name="adminForm" id="adminForm">


<table class="adminform" style="width:100%">
	<tr>
		<td>
			<div style="float: left;">
				<label for="venue">
					<?php echo JText::_( 'COM_JEM_VENUE' ).':'; ?>
				</label>
				<input name="venue" value="" size="55" maxlength="50" />
					&nbsp;&nbsp;&nbsp;
			</div>

			<div style="float: right;">
				<button type="button" onclick="submitbutton('event.addvenue')">
					<?php echo JText::_( 'COM_JEM_SAVE' ); ?>
				</button>
				<button type="button" onclick="window.parent.close()">
					<?php echo JText::_( 'COM_JEM_CANCEL' ); ?>
				</button>
			</div>
		</td>
	</tr>
</table>

<br />

<fieldset>
	<legend><?php echo JText::_('COM_JEM_ADDRESS'); ?></legend>
	<table class="adminform" style="width:100%">
		<tr>
  			<td><?php echo JText::_( 'COM_JEM_STREET' ).':'; ?></td>
			<td><input name="street" value="" size="55" maxlength="50" /></td>
	 	</tr>
  		<tr>
  		  	<td><?php echo JText::_( 'COM_JEM_ZIP' ).':'; ?></td>
  		  	<td><input name="postalCode" value="" size="15" maxlength="10" /></td>
	  	</tr>
  		<tr>
  			<td><?php echo JText::_( 'COM_JEM_CITY' ).':'; ?></td>
  			<td><input name="city" value="" size="55" maxlength="50" />
			</td>
  		</tr>
  		<tr>
  			<td><?php echo JText::_( 'COM_JEM_STATE' ).':'; ?></td>
  			<td><input name="state" value="" size="55" maxlength="50" />
			</td>
  		</tr>
  		<tr>
  		  	<td><?php echo JText::_( 'COM_JEM_COUNTRY' ).':'; ?></td>
  		  	<td>
				<?php echo $this->lists['countries']; ?>
			</td>
		</tr>
  		<tr>
    		<td><?php echo JText::_( 'COM_JEM_WEBSITE' ).':'; ?></td>
    		<td>
    			<input name="url" value="" size="55" maxlength="50" />&nbsp;
    			<span class="editlinktip hasTip" title="<?php echo JText::_('COM_JEM_WEBSITE_HINT'); ?>::<?php echo JText::_( 'COM_JEM_WEBSITE_HINT' ); ?>">
					<?php echo $infoimage; ?>
				</span>
    		</td>
  		</tr>
  		<?php if ( $this->jemsettings->showmapserv != 0 ) { ?>
		<tr>
			<td>
				<label for="map">
					<?php echo JText::_( 'COM_JEM_ENABLE_MAP' ).':'; ?>
				</label>
			</td>
			<td>
				<?php
          			echo JHtml::_('select.booleanlist', 'map', 'class="inputbox"', 0 );
          		?>
          		&nbsp;
          		<span class="editlinktip hasTip" title="<?php echo JText::_('COM_JEM_ADDRESS_NOTICE'); ?>::<?php echo JText::_('COM_JEM_ADDRESS_NOTICE'); ?>">
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
        <input class="inputbox" name="latitude" id="latitude" value="" size="14" maxlength="25" />
              <span class="editlinktip hasTip" title="<?php echo JText::_('COM_JEM_LATITUDE_HINT'); ?>::<?php echo JText::_('COM_JEM_LATITUDE_HINT'); ?>">
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
        <input class="inputbox" name="longitude" id="longitude" value="" size="14" maxlength="25" />
              <span class="editlinktip hasTip" title="<?php echo JText::_('COM_JEM_LONGITUDE_HINT'); ?>::<?php echo JText::_('COM_JEM_LONGITUDE_HINT'); ?>">
          <?php echo $infoimage; ?>
        </span>
      </td>
    </tr>
		<?php } ?>
	</table>
</fieldset>

<br />

<fieldset>
<legend><?php echo JText::_('COM_JEM_VARIOUS'); ?></legend>
<table>
	<tr>
		<td>
			<label for="publish">
				<?php echo JText::_( 'COM_JEM_PUBLISHED' ).':'; ?>
			</label>
		</td>
		<td>
			<?php
			$html = JHtml::_('select.booleanlist', 'published', 'class="inputbox"', $this->published );
			echo $html;
			?>
		</td>
	</tr>
	<tr>
		<td>
			<label for="locimage">
				<?php echo JText::_( 'COM_JEM_CHOOSE_IMAGE' ).':'; ?>
			</label>
		</td>
		<td>
			<?php echo $this->imageselect; ?>
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
</fieldset>

<br />

<fieldset>
	<legend><?php echo JText::_('COM_JEM_DESCRIPTION'); ?></legend>
		<?php echo $this->editor->display('locdescription', '', '655', '400', '70', '15', false); ?>
</fieldset>

<fieldset>
	<table>
		<tr>
			<td valign="top">
				<label for="metadesc">
					<?php echo JText::_( 'COM_JEM_META_DESCRIPTION' ); ?>:
				</label>
				<br />
				<textarea class="inputbox" cols="40" rows="5" name="meta_description" id="metadesc" style="width:300px;"></textarea>
			</td>
			<td valign="top">
				<label for="metakey">
					<?php echo JText::_( 'COM_JEM_META_KEYWORDS' ); ?>:
				</label>
				<br />
				<textarea class="inputbox" cols="40" rows="5" name="meta_keywords" id="metakey" style="width:300px;"></textarea>
				<br />
				<input type="button" class="button" value="<?php echo JText::_( 'COM_JEM_ADD_VENUE_CITY' ); ?>" onclick="f=document.adminForm;f.metakey.value=f.venue.value+', '+f.city.value+f.metakey.value;" />
			</td>
		</tr>
	</table>
</fieldset>

<?php
if ( $this->jemsettings->showmapserv == 0 ) { ?>
	<input type="hidden" name="map" value="0" />
<?php
}
?>
<?php echo JHtml::_('form.token'); ?>
<input type="hidden" name="option" value="com_jem" />
<input type="hidden" name="id" value="" />
<input type="hidden" name="task" value="" />
</form>

<p class="copyright">
	<?php echo JEMAdmin::footer( ); ?>
</p>