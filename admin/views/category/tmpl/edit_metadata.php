<?php
/**
 * @version     2.0.0
 * @package     JEM
 * @copyright   Copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright   Copyright (C) 2005-2009 Christoph Lukes
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
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