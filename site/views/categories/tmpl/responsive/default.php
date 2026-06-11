<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$buildCategoryEventsLink = static function ($category) {
    return Route::_(JemHelperRoute::getCategoryRoute($category->slug) . '&layout=default');
};
?>
<div id="jem" class="jem_categories<?php echo $this->pageclass_sfx;?>">
    <div class="buttons">
        <?php
        $btn_params = array('id' => $this->id, 'task' => $this->task, 'print_link' => $this->print_link, 'archive_link' => $this->archive_link);
        echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
        ?>
    </div>

    <?php if ($this->params->get('show_page_heading', 1) && empty($this->isTypeCategoryView)) : ?>
        <h1 class="componentheading">
            <?php echo $this->escape($this->params->get('page_heading')); ?>
        </h1>
    <?php endif; ?>

    <?php if (!empty($this->categoryType)) : ?>
        <div class="jem-type-header mb-3">
            <h1 class="componentheading">
                <?php if ($this->categoryType->icon) : ?>
                    <span class="<?php echo htmlspecialchars($this->categoryType->icon, ENT_QUOTES, 'UTF-8'); ?>"></span>
                <?php endif; ?>
                <?php echo htmlspecialchars($this->categoryType->name, ENT_QUOTES, 'UTF-8'); ?>
            </h1>
            <?php if ($this->categoryType->description) : ?>
                <div class="jem-type-description"><?php echo htmlspecialchars($this->categoryType->description, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($this->missingTypeId)) : ?>
        <div class="alert alert-info">
            <?php echo Text::sprintf('COM_JEM_TYPECATEGORIES_TYPE_NOT_FOUND', (int) $this->missingTypeId); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($this->isTypeCategoryView) && empty($this->rows) && empty($this->missingTypeId)) : ?>
        <div class="alert alert-info">
            <?php echo !empty($this->categoryType) ? Text::_('COM_JEM_TYPECATEGORIES_NO_CATEGORIES') : Text::_('COM_JEM_TYPECATEGORIES_NO_TYPES'); ?>
        </div>
    <?php endif; ?>

    <?php foreach ($this->rows as $row) : ?>
        <?php
        // has user access
        $categoriesaccess = '';
        if (!$row->user_has_access_category) {
            // show a closed lock icon
            $categoriesaccess = '<span class="icon-lock jem-lockicon" aria-hidden="true"></span>';
        } ?>
        <div class="jem cat_id<?php echo $row->id; ?>">
            <h2>
                <?php echo HTMLHelper::_('link', Route::_($row->linktarget), $this->escape($row->catname)); ?>
                <?php echo $categoriesaccess; ?>
            </h2>

            <?php if ($row->user_has_access_category) : ?>
                <?php if (($this->jemsettings->discatheader) && (!empty($row->image))) : ?>
                    <div class="jem-catimg">
                        <?php $cimage = JemImage::flyercreator($row->image, 'category'); ?>
                        <?php    echo JemOutput::flyer($row, $cimage, 'category'); ?>
                    </div>
                <?php endif; ?>

                <div class="description">
                    <?php echo $row->description; ?>
                    <?php if ($i = count($row->subcats)) : ?>
                        <h3 class="subcategories">
                            <?php echo Text::_('COM_JEM_SUBCATEGORIES'); ?>
                            <?php echo $categoriesaccess; ?>
                        </h3>
                        <div class="subcategorieslist">
                            <?php foreach ($row->subcats as $sub) : ?>
                                <?php
                                // has user access
                                $subcategoriesaccess = '';
                                if (!$sub->user_has_access_category) {
                                    // show a closed lock icon
                                    $subcategoriesaccess = '<span class="icon-lock jem-lockicon" aria-hidden="true"></span>';
                                } ?>
                                <strong>
                                    <a href="<?php echo Route::_(JemHelperRoute::getCategoryRoute($sub->slug, $this->task)); ?>">
                                        <?php echo $this->escape($sub->catname); ?></a>
                                </strong> <?php echo '(' . ($sub->assignedevents != null ? $sub->assignedevents : 0) . (--$i ? '),' : ')'); ?>
                                <?php echo $subcategoriesaccess; ?>
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
                    <a href="<?php echo $buildCategoryEventsLink($row); ?>" title="<?php echo Text::_('COM_JEM_CALENDAR_SHOWALL'); ?>">
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
            <?php endif; ?>
        </div>

        <?php
        if ($row !== end($this->rows)) :
            echo '<hr class="jem-hr">';
        endif;

    endforeach; ?>

    <!--pagination-->
    <div class="pagination">
        <?php echo $this->pagination->getPagesLinks(); ?>
    </div>

    <!--copyright-->
        <?php if ($this->params->get('showfootertext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('footertext'); ?>
        </div>
    <?php endif; ?>
    <div class="copyright">
        <?php echo JemOutput::footer( ); ?>
    </div>
</div>
