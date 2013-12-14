<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;
JHtml::_('behavior.modal');
?>
<div id="jem" class="jem_category">
	<div class="buttons">
	<?php
	echo JEMOutput::submitbutton($this->dellink, $this->params);
	echo JEMOutput::archivebutton($this->params, $this->task, $this->category->slug);
	echo JEMOutput::mailbutton($this->category->slug, 'category', $this->params);
	echo JEMOutput::printbutton($this->print_link, $this->params);
	?>
</div>

<?php if ($this->params->def( 'show_page_title', 1 )) : ?>
	<h1 class='componentheading'>
		<?php echo $this->task == 'archive' ? $this->escape($this->category->catname.' - '.JText::_('COM_JEM_ARCHIVE')) : $this->escape($this->category->catname); ?>
	</h1>
<?php endif; ?>

<div class="floattext">
	<?php if ($this->jemsettings->discatheader) : ?>
		<div class="catimg">
		<?php 
		// flyer
		if (empty($this->category->image)) {
			$jemsettings = JEMHelper::config();
			$imgattribs['width'] = $jemsettings->imagewidth;
			$imgattribs['height'] = $jemsettings->imagehight;
			
			echo JHtml::_('image', 'com_jem/noimage.png', $this->category->catname, $imgattribs, true);
		}
		else {
			echo JEMOutput::flyer($this->category, $this->cimage, 'category');
		}
		?>
		</div>
	<?php endif; ?>

	<div class="description">
			<p><?php echo $this->description; ?></p>
		</div>
	</div>

	<!--subcategories-->
	<?php
	if (count($this->categories) && $this->category->id > 0) :
	// only show this part if subcategries are available	?>
	<?php echo $this->loadTemplate('subcategories'); ?>
	<?php endif; ?>

	<form action="<?php echo $this->action; ?>" method="post" id="adminForm">
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
	<?php echo JEMOutput::icalbutton($this->category->id, 'category'); ?>
	</div>
	<!-- copyright -->
	<div class="copyright">
	<?php echo JEMOutput::footer( ); ?>
</div>
</div>