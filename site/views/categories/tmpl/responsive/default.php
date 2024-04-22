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

	<?php foreach ($this->rows as $row) : ?>
		<h2 class="jem cat<?php echo $row->id; ?>">
			<?php echo HTMLHelper::_('link', Route::_($row->linktarget), $this->escape($row->catname)); ?>
		</h2>
    
    <?php if (($this->jemsettings->discatheader) && (!empty($row->image))) : ?>
      <div class="jem-catimg">
        <?php $cimage = JemImage::flyercreator($row->image, 'category'); ?>
        <?php	echo JemOutput::flyer($row, $cimage, 'category'); ?>
      </div>
    <?php endif; ?>
    
    <div class="description">
      <?php echo $row->description; ?>
      <?php if ($i = count($row->subcats)) : ?>
        <h3 class="subcategories">
          <?php echo Text::_('COM_JEM_SUBCATEGORIES'); ?>
        </h3>
        <div class="subcategorieslist">
          <?php foreach ($row->subcats as $sub) : ?>
            <strong>
              <a href="<?php echo Route::_(JemHelperRoute::getCategoryRoute($sub->slug, $this->task)); ?>">
                <?php echo $this->escape($sub->catname); ?></a>
            </strong> <?php echo '(' . ($sub->assignedevents != null ? $sub->assignedevents : 0) . (--$i ? '),' : ')'); ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    
    <div class="jem-clear">
    </div>
    
		<!--table-->
		<?php
			if ($this->params->get('detcat_nr', 0) > 0) {
				$this->catrow = $row;
        echo '<h3>'.TEXT::_('COM_JEM_EVENTS').'</h3>';
				if (empty($this->jemsettings->tablewidth)) :
          echo $this->loadTemplate('jem_eventslist'); // The new layout
        else :
          echo $this->loadTemplate('jem_eventslist_small'); // Similar to the old table-layout
        endif;
			}
		?>
    <div class="jem-readmore">
      <a href="<?php echo Route::_($row->linktarget); ?>" title="<?php echo Text::_('COM_JEM_CALENDAR_SHOWALL'); ?>">
        <button class="buttonfilter btn">
          <?php echo Text::_('COM_JEM_CALENDAR_SHOWALL') ?>
          <?php if ($row->assignedevents > 1) :
              echo ' - '.$row->assignedevents.' '.TEXT::_('COM_JEM_EVENTS');
            elseif ($row->assignedevents == 1) :
              echo ' - '.$row->assignedevents.' '.TEXT::_('COM_JEM_EVENT');
            else : 
              echo '- 0 '.TEXT::_('COM_JEM_EVENTS');
            endif;
          ?>
        </button>
      </a>
    </div>    
    
    <?php 
    if ($row !== end($this->rows)) :
        echo '<hr class="jem-hr">';
    endif;
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
