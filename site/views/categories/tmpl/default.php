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

    <div class="clr"></div>

    <?php if ($this->params->get('showintrotext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('introtext'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($this->isTypeCategoryView) && empty($this->rows) && empty($this->missingTypeId)) : ?>
        <div class="alert alert-info">
            <?php echo !empty($this->categoryType) ? Text::_('COM_JEM_TYPECATEGORIES_NO_CATEGORIES') : Text::_('COM_JEM_TYPECATEGORIES_NO_TYPES'); ?>
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
            <?php if ($this->isTypeCategoryView && $this->model->getRequestedTypeId() > 0) : ?>
                <input type="hidden" name="typeid" value="<?php echo (int) $this->model->getRequestedTypeId(); ?>" />
            <?php endif; ?>
            <?php if ($this->task) : ?>
                <input type="hidden" name="task" value="<?php echo $this->escape($this->task); ?>" />
            <?php endif; ?>
        </form>
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
        <?php if ($this->params->get('show_category_type', 0) && !empty($row->type_id) && !empty($this->typeItems[(int) $row->type_id])) : ?>
            <div class="jem-category-type">
                <?php echo Text::_('COM_JEM_TYPE_FIELD_TYPE'); ?>:
                <?php echo $buildTypeBadge($this->typeItems[(int) $row->type_id]); ?>
            </div>
        <?php endif; ?>
                <?php if ($row->user_has_access_category) : ?>
        <div class="floattext">
            <?php if ($this->params->get('show_category_image', 1) && $this->jemsettings->discatheader) { ?>
                <div class="catimg">
                    <?php // flyer
                        if (empty($row->image)) {
                            $jemsettings = JemHelper::config();
                            $imgattribs['width'] = $jemsettings->imagewidth;
                            $imgattribs['height'] = $jemsettings->imagehight;

                            echo HTMLHelper::_('image', 'com_jem/noimage.webp', $row->catname, $imgattribs, true);
                        } else {
                            $cimage = JemImage::flyercreator($row->image, 'category');
                            echo JemOutput::flyer($row, $cimage, 'category');
                        }
                    ?>
                </div>
            <?php } ?>
            <div class="description cat<?php echo $row->id; ?>">
                <?php if (empty($this->isTypeCategoryView) && $this->params->get('show_category_description', 1)) : ?>
                    <?php echo $row->description; ?>
                <?php endif; ?>
                <p>
                    <?php echo HTMLHelper::_('link', Route::_($row->linktarget), $row->linktext); ?>
                    (<?php echo $row->assignedevents ? $row->assignedevents : '0'; ?>)
                </p>
            </div>
        </div>

        <?php if ($i = count($row->subcats)) : ?>
                        <?php
                        // has user access
                        $subcategoriesaccess = '';
                        if (!$row->user_has_access_category) {
                            // show a closed lock icon
                            $subcategoriesaccess = '<span class="icon-lock jem-lockicon" aria-hidden="true"></span>';
                        } ?>
            <div class="subcategories">
                <?php echo Text::_('COM_JEM_SUBCATEGORIES'); ?>
                            <?php echo $categoriesaccess; ?>
            </div>
            <div class="subcategorieslist">
                <?php foreach ($row->subcats as $sub) : ?>
                                <?php
                                // has user access
                                $eventsaccess = '';
                                if (!$sub->user_has_access_category ) {
                                    // show a closed lock icon
                                    $eventsaccess = '<span class="icon-lock jem-lockicon" aria-hidden="true"></span>';
                                } ?>
                    <strong>
                        <a href="<?php echo Route::_(JemHelperRoute::getCategoryRoute($sub->slug, $this->task)); ?>">
                            <?php echo $this->escape($sub->catname); ?></a>
                    </strong> <?php echo '(' . ($sub->assignedevents != null ? $sub->assignedevents : 0) . (--$i ? '),' : ')'); ?>
                                <?php echo $eventsaccess; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!--table-->
        <?php
            if ($this->params->get('show_category_events', 1) && $this->params->get('detcat_nr', 0) > 0) {
                $this->catrow = $row;
                echo $this->loadTemplate('table');
            }
        ?>
                <?php endif; ?>
        </div>
    <?php endforeach; ?>

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
<?php echo JemOutput::lightbox(); ?>
