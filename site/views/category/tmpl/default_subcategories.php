<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

$class = ' class="first"';
?>

<?php /*
<div class="subcategories">
<?php //echo JText::_('COM_JEM_SUBCATEGORIES'); ?>
</div>
 */ ?>

<?php if (count($this->children[$this->category->id]) > 0) : ?>

	<ul>
	<?php foreach($this->children[$this->category->id] as $id => $child) : ?>

		<?php
		//if ($this->params->get('show_empty_categories') || $child->getNumItems(true) || count($child->getChildren())) :
		if (!isset($this->children[$this->category->id][$id + 1])) :
			$class = ' class="last"';
		endif;
		?>

		<li<?php echo $class; ?>>
			<?php $class = ''; ?>
			<span class="item-title">
				<a href="<?php echo JRoute::_(JEMHelperRoute::getCategoryRoute($child->id)); ?>">
					<?php echo $this->escape($child->catname); ?>
				</a>
			</span>
			<?php if ($this->params->get('show_subcat_desc') == 1) : ?>
				<?php if ($child->description) : ?>
				<div class="category-desc">
					<?php echo JHtml::_('content.prepare', $child->description, '', 'com_content.category'); ?>
				</div>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( $this->params->get('show_cat_num_articles', 0)) : ?>
			<dl>
				<dt>
					<?php echo JText::_('COM_CONTENT_NUM_ITEMS') ; ?>
				</dt>
				<dd>
					<?php echo $child->getNumItems(false); ?>
				</dd>
			</dl>
			<?php endif ; ?>

			<?php if (count($child->getChildren()) > 0 ) :
				$this->children[$child->id] = $child->getChildren();
				$this->category = $child;
				$this->maxLevel--;
				if ($this->maxLevel != 0) :
					echo $this->loadTemplate('subcategories');
				endif;
				$this->category = $child->getParent();
				$this->maxLevel++;
			endif; ?>
		</li>
		<?php // endif; ?>
	<?php endforeach; ?>
	</ul>
<?php endif; ?>