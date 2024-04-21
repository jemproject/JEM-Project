<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

?>
<ul class="adminformlist">
	<li><?php echo $this->form->getLabel('meta_description'); ?>
	<?php echo $this->form->getInput('meta_description'); ?></li>

	<li><?php echo $this->form->getLabel('meta_keywords'); ?>
	<?php echo $this->form->getInput('meta_keywords'); ?></li>

	<?php foreach($this->form->getGroup('metadata') as $field): ?>
		<?php if ($field->hidden): ?>
			<li><?php echo $field->input; ?></li>
		<?php else: ?>
			<li><?php echo $field->label; ?>
			<?php echo $field->input; ?></li>
		<?php endif; ?>
	<?php endforeach; ?>
</ul>
