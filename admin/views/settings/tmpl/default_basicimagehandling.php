<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$gdv = JEMImage::gdVersion();
?>

<div class="width-100" style="padding: 10px 1vw;">
    <fieldset class="options-form">
		<legend><?php echo Text::_( 'COM_JEM_IMAGE_HANDLING' ); ?></legend>
		<ul class="adminformlist">
			<li><div class="label-form"><?php echo $this->form->renderfield('image_filetypes'); ?></div></li>

			<li><div class="label-form"><?php echo $this->form->renderfield('sizelimit'); ?></div></li>

			<li><div class="label-form"><?php echo $this->form->renderfield('imagehight'); ?></div></li>

			<li><div class="label-form"><?php echo $this->form->renderfield('imagewidth'); ?></div></li>

			<?php if ($gdv && $gdv >= 2) : //is the gd library installed on the server and its version > 2? ?>
				<li><div class="label-form"><?php echo $this->form->renderfield('gddisabled'); ?></div></li>
			<?php endif; ?>

			<li><div class="label-form"><?php echo $this->form->renderfield('lightbox'); ?></div></li>

            <li><div class="label-form"><?php echo $this->form->renderfield('flyer'); ?></div></li>
		</ul>
	</fieldset>
</div>
