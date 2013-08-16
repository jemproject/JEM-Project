<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>

<div class="width-100">
<fieldset class="adminform">
	<legend><?php echo JText::_( 'COM_JEM_IMAGE_HANDLING' ); ?></legend>

	<ul class="adminformlist">
		<li><?php echo $this->form->getLabel('sizelimit'); ?> <?php echo $this->form->getInput('sizelimit'); ?></li>
		<li><?php echo $this->form->getLabel('imagehight'); ?> <?php echo $this->form->getInput('imagehight'); ?></li>
		<li><?php echo $this->form->getLabel('imagewidth'); ?> <?php echo $this->form->getInput('imagewidth'); ?>
			<span class="error hasTip" title="<?php echo JText::_('COM_JEM_WARNING');?>::<?php echo JText::_('COM_JEM_WARNING_MAX_IMAGEWIDTH'); ?>">
				<?php echo $this->WarningIcon(); ?>
			</span>
		</li>
	</ul>

</fieldset>
</div>




		<fieldset class="adminform">
			<legend><?php echo JText::_( 'COM_JEM_IMAGE_HANDLING' ); ?></legend>
				<table class="admintable">
				<tbody>


					<tr>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_GD_LIBRARY' ); ?>::<?php echo JText::_('COM_JEM_GD_LIBRARY_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_GD_LIBRARY' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$mode = 0;
							if ($this->data->gddisabled == 1) {
								$mode = 1;
							} // if

							//is the gd library installed on the server running JEM?
							if ($gdv = JEMImage::gdVersion()) {

								//is it Version two or higher? If yes let the user the choice
								if ($gdv >= 2) {
								?>
									<input type="radio" id="gddisabled0" name="gddisabled" value="0" onclick="changegdMode(0)"<?php if (!$mode) echo ' checked="checked"'; ?>/><?php echo JText::_( 'JNO' ); ?>
									<input type="radio" id="gddisabled1" name="gddisabled" value="1" onclick="changegdMode(1)"<?php if ($mode) echo ' checked="checked"'; ?>/><?php echo JText::_( 'JYES' ); ?>
								<?php
									$note	= JText::_( 'COM_JEM_GD_VERSION_TWO' );
									$color	= 'green';

								//No it is version one...disable thumbnailing
								} else {
								?>
								<input type="hidden" name="gddisabled" value="0" />
								<?php
								$note	= JText::_( 'COM_JEM_GD_VERSION_ONE' );
								$color	= 'red';
								}

							//the gd library is not available on this server...disable thumbnailing
							} else {
							?>
								<input type="hidden" name="gddisabled" value="0" />
							<?php
								$note	= JText::_( 'COM_JEM_NO_GD_LIBRARY' );
								$color	= 'red';
							}
							?>
							<br />
							<strong><?php echo JText::_( 'COM_JEM_STATUS' ).':'; ?></strong>
							<font color="<?php echo $color; ?>"><?php echo $note; ?></font>
						</td>
					</tr>
					<tr id="gd1"<?php if (!$mode) echo ' style="display:none"'; ?>>
						<td width="300" class="key">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_IMAGE_LIGHTBOX' ); ?>::<?php echo JText::_('COM_JEM_IMAGE_LIGHTBOX_TIP'); ?>">
								<?php echo JText::_( 'COM_JEM_IMAGE_LIGHTBOX' ); ?>
							</span>
						</td>
						<td valign="top">
							<?php
							$html = JHTML::_('select.booleanlist', 'lightbox', 'class="inputbox"', $this->data->lightbox );
							echo $html;
							?>
						</td>
					</tr>
				</tbody>
			</table>
		</fieldset>



