<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
?>

<?php //echo HTMLHelper::_('sliders.panel', Text::_('JGLOBAL_FIELDSET_PUBLISHING'), 'publishing-details'); ?>

	<fieldset class="panelform">
		<ul class="adminformlist">

			<li><div class="label-form"><?php echo $this->form->renderfield('created_user_id'); ?></div></li>

			<?php if (intval($this->item->created_time)) : ?>
				<li><div class="label-form"><?php echo $this->form->renderfield('created_time'); ?></div></li>
			<?php endif; ?>

			<?php if ($this->item->modified_user_id) : ?>
				<li><div class="label-form"><?php echo $this->form->renderfield('modified_user_id'); ?></div></li>
				<li><div class="label-form"><?php echo $this->form->renderfield('modified_time'); ?></div></li>
			<?php endif; ?>

		</ul>
	</fieldset>

<?php $fieldSets = $this->form->getFieldsets('params');

foreach ($fieldSets as $name => $fieldSet) :
	$label = !empty($fieldSet->label) ? $fieldSet->label : 'COM_CATEGORIES_'.$name.'_FIELDSET_LABEL';
	echo HTMLHelper::_('sliders.panel', Text::_($label), $name.'-options');
	if (isset($fieldSet->description) && trim($fieldSet->description)) :
		echo '<p class="tip">'.$this->escape(Text::_($fieldSet->description)).'</p>';
	endif;
	?>
	<fieldset class="panelform">
		<ul class="adminformlist">
			<?php foreach ($this->form->getFieldset($name) as $field) : ?>
				<li><?php echo $field->label; ?>
				<?php echo $field->input; ?></li>
			<?php endforeach; ?>

			<?php if ($name=='basic'):?>
				<li><?php echo $this->form->getLabel('note'); ?>
				<?php echo $this->form->getInput('note'); ?></li>
			<?php endif;?>
		</ul>
	</fieldset>
<?php endforeach; ?>
