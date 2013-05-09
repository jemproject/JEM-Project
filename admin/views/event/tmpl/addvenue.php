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
		} else if (form.plz.value == "" && form.map.value == "1"){
			alert( "<?php echo JText::_( 'COM_JEM_ADD_ZIP' ); ?>" );
			form.plz.focus();
		} else if (form.country.value == "" && form.map.value == "1"){
			alert( "<?php echo JText::_( 'COM_JEM_ADD_COUNTRY' ); ?>" );
			form.country.focus();
		} else {
			<?php
			echo $this->editor->save( 'locdescription' );
			?>
			submitform( task );
			//window.parent.close();
		}
	}
</script>

<?php
//Set the info image
$infoimage = JHTML::image(JURI::root().'media/com_jem/images/icon-16-hint.png', JText::_( 'COM_JEM_NOTES' ) );
?>

<form action="<?php echo $this->request_url; ?>" method="post" name="adminForm" id="adminForm">


<table class="adminform" width="100%">
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
				<button type="button" onclick="submitbutton('addvenue')">
					<?php echo JText::_( 'COM_JEM_SAVE' ); ?>
				</button>
				<button type="button" onclick="window.parent.close()" />
					<?php echo JText::_( 'COM_JEM_CANCEL' ); ?>
				</button>
			</div>
		</td>
	</tr>
</table>

<br />

<fieldset>
	<legend><?php echo JText::_('COM_JEM_ADDRESS'); ?></legend>
	<table class="adminform" width="100%">
		<tr>
  			<td><?php echo JText::_( 'COM_JEM_STREET' ).':'; ?></td>
			<td><input name="street" value="" size="55" maxlength="50" /></td>
	 	</tr>
  		<tr>
  		  	<td><?php echo JText::_( 'COM_JEM_ZIP' ).':'; ?></td>
  		  	<td><input name="plz" value="" size="15" maxlength="10" /></td>
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
    			<span class="editlinktip hasTip" title="<?php echo JText::_('COM_JEM_NOTES'); ?>::<?php echo JText::_( 'COM_JEM_WEBSITE_HINT' ); ?>">
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
          			echo JHTML::_('select.booleanlist', 'map', 'class="inputbox"', 0 );
          		?>
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
        <input class="inputbox" name="latitude" id="latitude" value="" size="14" maxlength="25" />
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
        <input class="inputbox" name="longitude" id="longitude" value="" size="14" maxlength="25" />
              <span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_NOTES' ); ?>::<?php echo JText::_('COM_JEM_LONGITUDE_HINT'); ?>">
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
			$html = JHTML::_('select.booleanlist', 'published', 'class="inputbox"', $this->published );
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
<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_jem" />
<input type="hidden" name="controller" value="venues" />
<input type="hidden" name="id" value="" />
<input type="hidden" name="task" value="" />
</form>

<p class="copyright">
	<?php echo JEMAdmin::footer( ); ?>
</p>