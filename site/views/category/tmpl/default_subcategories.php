<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>

<div class="subcategories">
<?php echo JText::_('COM_JEM_SUBCATEGORIES'); ?>
</div>
<?php
$n = count($this->categories);
$i = 0;
?>
<div class="subcategorieslist">
	<?php foreach ($this->categories as $sub) : ?>
	<?php if ($this->params->get('show_empty', 1) || $sub->assignedevents != null): ?>
			<strong><a href="<?php echo JRoute::_(JEMHelperRoute::getCategoryRoute($sub->slug)); ?>"><?php echo $this->escape($sub->catname); ?></a></strong> (<?php echo $sub->assignedevents != null ? $sub->assignedevents : 0; ?>)
			<?php
			$i++;
			if ($i != $n) :
				echo ',';
			endif;
		endif;
	endforeach; ?>
</div>