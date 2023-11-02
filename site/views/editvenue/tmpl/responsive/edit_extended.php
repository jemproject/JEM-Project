<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>


<!-- IMAGE -->
<?php if ($this->item->locimage || $this->jemsettings->imageenabled != 0) : ?>
<fieldset class="jem_fldst_image">
	<legend><?php echo Text::_('COM_JEM_EDITVENUE_IMAGE_LEGEND'); ?></legend>
	<?php if ($this->jemsettings->imageenabled != 0) : ?>
	<dl class="adminformlist jem-dl">
		<dt><?php echo $this->form->getLabel('userfile'); ?></dt>
		<?php if ($this->item->locimage) : ?>
		<dd>
			<?php echo JEMOutput::flyer($this->item, $this->limage, 'venue', 'locimage'); ?>
			<input type="hidden" name="locimage" id="locimage" value="<?php echo $this->item->locimage; ?>" />
		</dd>
		<dt> </dt>
		<?php endif; ?>
		<dd><?php echo $this->form->getInput('userfile'); ?></dd>
		<dt> </dt>
		<dd><button type="button" class="button3 btn" onclick="document.getElementById('jform_userfile').value = ''"><?php echo Text::_('JSEARCH_FILTER_CLEAR') ?></button></dd>
		<?php if ($this->item->locimage) : ?>
		<dt><?php echo Text::_('COM_JEM_REMOVE_IMAGE'); ?></dt>
		<dd><?php
            echo JHtml::image('media/com_jem/images/publish_r.png', null, array('id' => 'userfile-remove', 'data-id' => $this->item->id, 'data-type' => 'venues', 'title' => Text::_('COM_JEM_REMOVE_IMAGE'), 'class' =>'btn')); ?>
		</dd>
		<?php endif; ?>
	</dl>
	<input type="hidden" name="removeimage" id="removeimage" value="0" />
	<?php endif; ?>
</fieldset>
<?php endif; ?>

<fieldset>
	<legend><?php echo Text::_('COM_JEM_EDITVENUE_URL_LEGEND'); ?></legend>
	<dl class="adminformlist jem-dl">
		<dt><?php echo $this->form->getLabel('url'); ?></dt>
		<dd><?php echo $this->form->getInput('url'); ?></dd>
	</dl>
</fieldset>
