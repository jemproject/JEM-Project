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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

?>
<div id="jem" class="jem_categories<?php echo $this->pageclass_sfx;?>">
	<div class="buttons">
		<?php
		$btn_params = array('id' => $this->id, 'task' => $this->task, 'print_link' => $this->print_link, 'archive_link' => $this->archive_link);
		echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
		?>
	</div>

	<?php if ($this->params->get('show_page_heading', 1)) : ?>
		<h1 class="componentheading">
		<?php echo $this->escape($this->params->get('page_heading')); ?>
		</h1>
	<?php endif; ?>

	<div class="clr"></div>

	<?php foreach ($this->rows as $row) : ?>
		<h2 class="jem cat<?php echo $row->id; ?>">
			<?php echo HTMLHelper::_('link', Route::_($row->linktarget), $this->escape($row->catname)); ?>
		</h2>

		<div class="floattext">
			<?php if ($this->jemsettings->discatheader) { ?>
				<div class="catimg">
					<?php // flyer
						if (empty($row->image)) {
							$jemsettings = JemHelper::config();
							$imgattribs['width'] = $jemsettings->imagewidth;
							$imgattribs['height'] = $jemsettings->imagehight;

							echo HTMLHelper::_('image', 'com_jem/noimage.png', $row->catname, $imgattribs, true);
						} else {
							$cimage = JemImage::flyercreator($row->image, 'category');
							echo JemOutput::flyer($row, $cimage, 'category');
						}
					?>
				</div>
			<?php } ?>
			<div class="description cat<?php echo $row->id; ?>">
				<?php echo $row->description; ?>
				<p>
					<?php echo HTMLHelper::_('link', Route::_($row->linktarget), $row->linktext); ?>
					(<?php echo $row->assignedevents ? $row->assignedevents : '0'; ?>)
				</p>
			</div>
		</div>

		<?php if ($i = count($row->subcats)) : ?>
			<div class="subcategories">
				<?php echo Text::_('COM_JEM_SUBCATEGORIES'); ?>
			</div>
			<div class="subcategorieslist">
				<?php foreach ($row->subcats as $sub) : ?>
					<strong>
						<a href="<?php echo Route::_(JemHelperRoute::getCategoryRoute($sub->slug, $this->task)); ?>">
							<?php echo $this->escape($sub->catname); ?></a>
					</strong> <?php echo '(' . ($sub->assignedevents != null ? $sub->assignedevents : 0) . (--$i ? '),' : ')'); ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<!--table-->
		<?php
			if ($this->params->get('detcat_nr', 0) > 0) {
				$this->catrow = $row;
				echo $this->loadTemplate('table');
			}
		?>
	<?php endforeach; ?>

	<!--pagination-->
	<div class="pagination">
		<?php echo $this->pagination->getPagesLinks(); ?>
	</div>

	<!--copyright-->
	<div class="copyright">
		<?php echo JemOutput::footer( ); ?>
	</div>
</div>
<?php echo JemOutput::lightbox(); ?>