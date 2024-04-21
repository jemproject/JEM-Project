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

// HTMLHelper::_('behavior.modal');
?>
<div id="jem" class="jem_category<?php echo $this->pageclass_sfx;?>">
	<div class="buttons">
		<?php
		$btn_params = array('id' => $this->category->slug, 'slug' => $this->category->slug, 'task' => $this->task, 'print_link' => $this->print_link);
		echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
		?>
	</div>

	<?php if ($this->params->get('show_page_heading', 1)) : ?>
	<h1 class='componentheading'>
		<?php echo $this->escape($this->params->get('page_heading')); ?>
	</h1>
	<?php endif; ?>
  
  <?php if ($this->escape($this->params->get('page_heading')) != $this->escape($this->category->title)) : ?>
    <?php if ($this->params->get('show_page_heading', 1)) : ?>
      <h2 class="jem-category-title">
        <?php echo $this->escape($this->category->title);?>
      </h2>
    <?php else : ?>
      <h1 class="jem-category-title">
        <?php echo $this->escape($this->category->title);?>
      </h1>
    <?php endif; ?>
	<?php endif; ?>
  
  <style>
    .jem-catimg {
      flex-basis: <?php echo $this->jemsettings->imagewidth; ?>px;
    }
  </style>
    
  <?php if (($this->jemsettings->discatheader) && (!empty($this->category->image))) : ?>
  <div class="jem-catimg">
    <?php	echo JemOutput::flyer($this->category, $this->cimage, 'category'); ?>
  </div>
  <?php endif; ?>
    
  <div class="description">
    <p><?php echo $this->description; ?></p>
  </div>
  
  <div class="jem-clear">
  </div>

	<!--subcategories-->
	<?php
	if ($this->showsubcats && $this->maxLevel != 0 && !empty($this->category->id) && !empty($this->children[$this->category->id])) :
		$countsubcats = 0;
		foreach ($this->children[$this->category->id] as $id => $child) :
			// Do we have any non-empty subcategory or should generally show empty subcategories?
			// Note: We also show empty subcategories if they have at least one non-empty subsubcategory.
			if ($this->showemptysubcats || ($child->getNumItems(true) > 0)) :
				++$countsubcats;
			endif;
		endforeach;
		if ($countsubcats) :
		?>
		<div class="cat-children">
			<?php if ($this->params->get('show_category_heading_title_text', 1) == 1) : ?>
			<h2>
				<?php echo Text::_('COM_JEM_SUBCATEGORIES'); ?>
			</h2>
			<?php endif; ?>
			<?php echo $this->loadTemplate('subcategories'); ?>
		</div>
		<?php endif; ?>
	<?php endif; ?>

  <h2>
    <?php echo Text::_('COM_JEM_EVENTS'); ?>
  </h2>
	<form action="<?php echo htmlspecialchars($this->action); ?>" method="post" id="adminForm">
	<!--table-->
		<?php echo $this->loadTemplate('events_table'); ?>
		<input type="hidden" name="option" value="com_jem" />
		<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
		<input type="hidden" name="view" value="category" />
		<input type="hidden" name="task" value="<?php echo $this->task; ?>" />
		<input type="hidden" name="id" value="<?php echo $this->category->id; ?>" />
	</form>

	<!--pagination-->
	<div class="pagination">
		<?php echo $this->pagination->getPagesLinks(); ?>
	</div>

	<!-- iCal -->
	<div id="iCal" class="iCal">
		<?php echo JemOutput::icalbutton($this->category->id, 'category'); ?>
	</div>

	<!-- copyright -->
	<div class="copyright">
		<?php echo JemOutput::footer(); ?>
	</div>
</div>

<?php echo JemOutput::lightbox(); ?>
