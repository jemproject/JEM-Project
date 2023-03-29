<?php
/**
 * @version 2.3.12
 * @package JEM
 * @copyright (C) 2013-2023 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

$gdv = JEMImage::gdVersion();
?>

<div class="width-100" style="padding: 10px 1vw;">
    <fieldset class="options-form">
		<legend><?php echo JText::_( 'COM_JEM_IMAGE_HANDLING' ); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('image_filetypes'); ?> <?php echo $this->form->getInput('image_filetypes'); ?></li>

			<li><?php echo $this->form->getLabel('sizelimit'); ?> <?php echo $this->form->getInput('sizelimit'); ?></li>

			<li><?php echo $this->form->getLabel('imagehight'); ?> <?php echo $this->form->getInput('imagehight'); ?></li>

			<li><?php echo $this->form->getLabel('imagewidth'); ?><span <?php echo JEMOutput::tooltip(JText::_('COM_JEM_WARNING'), JText::_('COM_JEM_WARNING_MAX_IMAGEWIDTH'), 'error'); ?>>
					<?php echo $this->WarningIcon(); ?>
					<?php echo $this->form->getInput('imagewidth'); ?>
				</span>
			</li>

			<?php if ($gdv && $gdv >= 2) : //is the gd library installed on the server and its version > 2? ?>
				<li><?php echo $this->form->getLabel('gddisabled'); ?> <?php echo $this->form->getInput('gddisabled'); ?></li>
			<?php endif; ?>

			<li id="lb1" style="display:none"><?php echo $this->form->getLabel('lightbox'); ?> <?php echo $this->form->getInput('lightbox'); ?></li>
		</ul>
	</fieldset>
</div>
