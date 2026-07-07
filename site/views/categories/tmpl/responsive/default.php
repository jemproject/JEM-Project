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

$buildTypeBadge = static function ($type) {
    $label = htmlspecialchars($type->name, ENT_QUOTES, 'UTF-8');
    $color = trim((string) ($type->color ?? ''));

    if (!preg_match('/^#?[0-9a-fA-F]{6}$/', $color)) {
        return '<span class="badge jem-type-badge">' . $label . '</span>';
    }

    $background = '#' . ltrim($color, '#');
    $red = hexdec(substr($background, 1, 2));
    $green = hexdec(substr($background, 3, 2));
    $blue = hexdec(substr($background, 5, 2));
    $textColor = (($red * 299 + $green * 587 + $blue * 114) / 1000) > 145 ? '#000000' : '#ffffff';

    return '<span class="badge jem-type-badge" style="background-color: ' . $background . '; color: ' . $textColor . ';">' . $label . '</span>';
};

$renderTypeSectionHeader = function ($type) use ($buildTypeBadge) {
    if ($type) {
        return '<div class="jem-type-section-header"><h2 class="jem-type-section-title">' . $buildTypeBadge($type) . '</h2></div>';
    }

    return '<div class="jem-type-section-header"><h2 class="jem-type-section-title">' . Text::_('COM_JEM_TYPECATEGORIES_UNASSIGNED') . '</h2></div>';
};
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

    <?php if (!empty($this->categoryType)) : ?>
        <div class="jem-type-header mb-3">
            <h2 class="jem-type-title">
                <?php if ($this->categoryType->icon) : ?>
                    <span class="<?php echo htmlspecialchars($this->categoryType->icon, ENT_QUOTES, 'UTF-8'); ?>"></span>
                <?php endif; ?>
                <?php echo htmlspecialchars($this->categoryType->name, ENT_QUOTES, 'UTF-8'); ?>
            </h2>
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
            <?php echo Text::_('COM_JEM_TYPECATEGORIES_NO_CATEGORIES'); ?>
        </div>
    <?php endif; ?>

    <?php if ($this->params->get('show_categories_filter', 1)) : ?>
        <form action="<?php echo Route::_('index.php?option=com_jem&view=categories&id=' . (int) $this->id); ?>" method="get" name="adminForm" id="adminForm">
            <div id="jem_filter" class="floattext">
                <div class="jem_fleft">
                    <label for="filter_search"><?php echo Text::_('COM_JEM_FILTER'); ?></label>
                    <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox form-control" onchange="document.adminForm.submit();" />
                    <?php if ($this->params->get('show_categories_type_filter', 1) && empty($this->isTypeCategoryView)) : ?>
                        <?php echo $this->lists['type']; ?>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
                </div>
                <div class="jem_fright">
                    <label for="limit"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>
                    <?php echo $this->pagination->getLimitBox(); ?>
                </div>
            </div>
            <input type="hidden" name="option" value="com_jem" />
            <input type="hidden" name="view" value="categories" />
            <input type="hidden" name="id" value="<?php echo (int) $this->id; ?>" />
            <?php if ($this->isTypeCategoryView) : ?>
                <input type="hidden" name="typeid" value="<?php echo (int) $this->model->getRequestedTypeId(); ?>" />
            <?php endif; ?>
            <?php if ($this->task) : ?>
                <input type="hidden" name="task" value="<?php echo $this->escape($this->task); ?>" />
            <?php endif; ?>
        </form>
    <?php endif; ?>

    <?php $currentTypeId = null; ?>
    <?php foreach ($this->rows as $row) : ?>
        <?php if (!empty($this->isGroupedTypeCategoryView)) : ?>
            <?php $rowTypeId = (int) ($row->type_id ?? 0); ?>
            <?php if ($currentTypeId !== $rowTypeId) : ?>
                <?php $currentTypeId = $rowTypeId; ?>
                <?php echo $renderTypeSectionHeader($this->typeItems[$rowTypeId] ?? null); ?>
            <?php endif; ?>
        <?php endif; ?>
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
            <?php if ($this->params->get('show_category_type', 0) && !empty($row->type_id) && !empty($this->typeItems[(int) $row->type_id])) : ?>
                <div class="jem-category-type">
                    <?php echo Text::_('COM_JEM_TYPE_FIELD_TYPE'); ?>:
                    <?php echo $buildTypeBadge($this->typeItems[(int) $row->type_id]); ?>
                </div>
            <?php endif; ?>

            <?php if ($row->user_has_access_category) : ?>
                <?php if ($this->params->get('show_category_image', 1) && ($this->jemsettings->discatheader) && (!empty($row->image))) : ?>
                    <div class="jem-catimg">
                        <?php $cimage = JemImage::flyercreator($row->image, 'category'); ?>
                        <?php    echo JemOutput::flyer($row, $cimage, 'category'); ?>
                    </div>
                <?php endif; ?>

                <div class="description">
                    <?php if ($this->params->get('show_category_description', 1)) : ?>
                        <?php echo $row->description; ?>
                    <?php endif; ?>
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
                if ($this->params->get('show_category_events', 1) && $this->params->get('detcat_nr', 3) > 0) {
                    $this->catrow = $row;
                    echo '<h3>'.TEXT::_('COM_JEM_EVENTS').'</h3>';
                    if (empty($this->jemsettings->tablewidth)) :
                        echo $this->loadTemplate('jem_eventslist'); // The new layout
                    else :
                        echo $this->loadTemplate('jem_eventslist_small'); // Similar to the old table-layout
                    endif;
                }
                ?>
                <?php if ($this->params->get('show_category_events', 1)) : ?>
                    <div class="jem-readmore">
                        <a class="btn btn-secondary jem-category-events-button" href="<?php echo $buildCategoryEventsLink($row); ?>" title="<?php echo Text::_('COM_JEM_CALENDAR_SHOWALL'); ?>" role="button">
                            <?php echo Text::_('COM_JEM_CALENDAR_SHOWALL') ?>
                            <?php if ($row->assignedevents > 1) :
                                echo ' - '.$row->assignedevents.' '.TEXT::_('COM_JEM_EVENTS');
                            elseif ($row->assignedevents == 1) :
                                echo ' - '.$row->assignedevents.' '.TEXT::_('COM_JEM_EVENT');
                            else :
                                echo ' - 0 '.TEXT::_('COM_JEM_EVENTS');
                            endif;
                            ?>
                        </a>
                    </div>
                <?php endif; ?>
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
