<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

// Load necessary behaviors and scripts
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('bootstrap.tooltip');

$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('table.columns');

$user      = JemFactory::getUser();
$userId    = $user->get('id');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$saveOrder = $listOrder == 'a.ordering';

if ($saveOrder) {
    $saveOrderingUrl = 'index.php?option=com_jem&task=events.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
    HTMLHelper::_('draggablelist.draggable');
}
?>

<form action="<?php echo Route::_('index.php?option=com_jem&view=events'); ?>" method="post" name="adminForm" id="adminForm">
    
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php
                // Search tools - modern Joomla filter bar
                echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]);
                ?>
                
                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span>
                        <span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table table-striped" id="eventList">
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col" class="w-1 text-center d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', '', 'a.ordering', $listDirn, $listOrder, null, 'asc', 'JGRID_HEADING_ORDERING', 'icon-sort'); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_DATE', 'a.dates', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_STARTTIME_SHORT', 'a.times', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_EVENT_TITLE', 'a.title', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_VENUE', 'loc.venue', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo Text::_('COM_JEM_CATEGORY'); ?>
                                </th>
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JFEATURED', 'a.featured', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo Text::_('JAUTHOR'); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_HITS', 'a.hits', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell text-center">
                                    <?php echo Text::_('COM_JEM_REGISTERED'); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ACCESS', 'a.access', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody <?php if ($saveOrder) : ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" data-nested="false"<?php endif; ?>>
                            <?php 
                            foreach ($this->items as $i => $item) :
                                $canEdit    = $user->can('edit', 'event', $item->id, $item->created_by);
                                $canCheckin = $user->authorise('core.manage', 'com_checkin') || $item->checked_out == $userId || $item->checked_out == 0;
                                $canEditOwn = $user->authorise('core.edit.own', 'com_jem') && $item->created_by == $userId;
                                $canChange  = $user->authorise('core.edit.state', 'com_jem') && $canCheckin;
                                $canEditVenue = $user->authorise('core.edit', 'com_jem.venue.' . $item->locid);
                                
                                // Check if event is recurring
                                $isRecurring = ($item->recurrence_type > 0) || ($item->recurrence_first_id > 0);
                            ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                                </td>
                                <td class="text-center d-none d-md-table-cell">
                                    <?php
                                    $iconClass = '';
                                    if (!$canChange) {
                                        $iconClass = ' inactive';
                                    } elseif (!$saveOrder) {
                                        $iconClass = ' inactive" title="' . Text::_('JORDERINGDISABLED');
                                    }
                                    ?>
                                    <span class="sortable-handler<?php echo $iconClass ?>">
                                        <span class="icon-ellipsis-v" aria-hidden="true"></span>
                                    </span>
                                    <?php if ($canChange && $saveOrder) : ?>
                                        <input type="text" name="order[]" size="5" value="<?php echo $item->ordering; ?>" class="width-20 text-area-order hidden">
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if ($item->dates) : ?>
                                        <?php if ($canEdit || $canEditOwn) : ?>
                                            <a href="<?php echo Route::_('index.php?option=com_jem&task=event.edit&id=' . (int) $item->id); ?>" 
                                               title="<?php echo Text::sprintf('COM_JEM_EDIT_EVENT', $this->escape($item->title)); ?>" 
                                               class="hasTooltip">
                                                <?php echo HTMLHelper::_('date', $item->dates, Text::_('DATE_FORMAT_LC4')); ?>
                                            </a>
                                        <?php else : ?>
                                            <?php echo HTMLHelper::_('date', $item->dates, Text::_('DATE_FORMAT_LC4')); ?>
                                        <?php endif; ?>
                                        <?php if ($item->enddates && $item->enddates != $item->dates) : ?>
                                            <div class="small text-muted">
                                                <?php echo ' - ' . HTMLHelper::_('date', $item->enddates, Text::_('DATE_FORMAT_LC4')); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="badge bg-secondary"><?php echo Text::_('COM_JEM_NO_DATE'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if ($item->times) : ?>
                                        <?php echo substr($item->times, 0, 5); ?>
                                        <?php if ($item->endtimes) : ?>
                                            <div class="small text-muted">
                                                <?php echo substr($item->endtimes, 0, 5); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="break-word">
                                        <?php if ($item->checked_out) : ?>
                                            <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'events.', $canCheckin); ?>
                                        <?php endif; ?>
                                        <?php if ($canEdit || $canEditOwn) : ?>
                                            <a href="<?php echo Route::_('index.php?option=com_jem&task=event.edit&id=' . (int) $item->id); ?>" 
                                               title="<?php echo Text::sprintf('COM_JEM_EDIT_EVENT', $this->escape($item->title)); ?>" 
                                               class="hasTooltip">
                                                <?php echo $this->escape($item->title); ?>
                                            </a>
                                        <?php else : ?>
                                            <?php echo $this->escape($item->title); ?>
                                        <?php endif; ?>
                                        <?php if ($isRecurring) : ?>
                                            <span class="hasTooltip" title="<?php echo Text::_('COM_JEM_RECURRENCE'); ?>">
                                                <span class="icon-loop" style="color: #0066cc;" aria-hidden="true"></span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if ($item->locid && $item->venue) : ?>
                                        <div class="break-word">
                                            <?php if ($canEditVenue) : ?>
                                                <a href="<?php echo Route::_('index.php?option=com_jem&task=venue.edit&id=' . (int) $item->locid); ?>" 
                                                   title="<?php echo Text::sprintf('COM_JEM_EDIT_VENUE', $this->escape($item->venue)); ?>" 
                                                   class="hasTooltip">
                                                    <?php echo $this->escape($item->venue); ?>
                                                </a>
                                            <?php else : ?>
                                                <?php echo $this->escape($item->venue); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($item->city) : ?>
                                            <div class="small text-muted">
                                                <span class="icon-location" aria-hidden="true"></span>
                                                <?php echo $this->escape($item->city); ?>
                                                <?php if ($item->state) : ?>
                                                    <?php echo ', ' . $this->escape($item->state); ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="badge bg-secondary"><?php echo Text::_('COM_JEM_NO_VENUE'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if (!empty($item->categories)) : ?>
                                        <div class="break-word">
                                            <?php 
                                            $catCount = count($item->categories);
                                            foreach ($item->categories as $j => $category) : 
                                                $canEditCat = $user->authorise('core.edit', 'com_jem.category.' . $category->id);
                                            ?>
                                                <?php if ($j > 0) : ?>, <?php endif; ?>
                                                <?php if ($canEditCat) : ?>
                                                    <a href="<?php echo Route::_('index.php?option=com_jem&task=category.edit&id=' . (int) $category->id); ?>" 
                                                       title="<?php echo Text::sprintf('COM_JEM_EDIT_CATEGORY', $this->escape($category->catname)); ?>" 
                                                       class="hasTooltip">
                                                        <span class="badge bg-secondary" style="<?php echo !empty($category->color) ? 'background-color: ' . $category->color . ' !important;' : ''; ?>">
                                                            <?php echo $this->escape($category->catname); ?>
                                                        </span>
                                                    </a>
                                                <?php else : ?>
                                                    <span class="badge bg-secondary" style="<?php echo !empty($category->color) ? 'background-color: ' . $category->color . ' !important;' : ''; ?>">
                                                        <?php echo $this->escape($category->catname); ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else : ?>
                                        <span class="badge bg-warning text-dark"><?php echo Text::_('JUNCATEGORISED'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    // Featured status - clickable
                                    if ($canChange) :
                                        $featured_task = $item->featured ? 'events.unfeatured' : 'events.featured';
                                        $featured_title = $item->featured ? '<strong>' . Text::_('JFEATURED') . '</strong><br>' : '<strong>' .  Text::_('JUNFEATURED') . '</strong><br>';
                                        ?>
                                        <a href="javascript:void(0);" 
                                           onclick="return Joomla.listItemTask('cb<?php echo $i; ?>','<?php echo $featured_task; ?>')"
                                           class="tbody-icon hasTooltip"
                                           title="<?php echo htmlspecialchars($featured_title . ': ' . Text::_('JGLOBAL_TOGGLE_FEATURED'), ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="<?php echo $item->featured ? 'icon-color-featured icon-star' : 'icon-unfeatured' ?>" aria-hidden="true"></span>
                                        </a>
                                    <?php else : ?>
                                        <span class="tbody-icon disabled hasTooltip" title="<?php echo $item->featured ? Text::_('JFEATURED') : Text::_('JUNFEATURED'); ?>">
                                            <span class="<?php echo $item->featured ? 'icon-color-featured icon-star' : 'icon-unfeatured' ?>" aria-hidden="true"></span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    // Published status - clickable with detailed tooltip
                                    if ($canChange) :
                                        // Determine icon, task and tooltip based on published state
                                        if ($item->published == 1) {
                                            $pub_icon = 'publish';
                                            $pub_task = 'events.unpublish';
                                            $pub_title_strong = Text::_('JPUBLISHED');
                                            
                                            // Check if item is current based on publish dates
                                            $now = Factory::getDate()->toSql();
                                            $is_pending = false;
                                            $is_expired = false;
                                            
                                            if (!empty($item->publish_up) && $item->publish_up > $now) {
                                                $is_pending = true;
                                                $pub_title_strong = Text::_('COM_JEM_PENDING');
                                            } elseif (!empty($item->publish_down) && $item->publish_down < $now && $item->publish_down != '0000-00-00 00:00:00') {
                                                $is_expired = true;
                                                $pub_title_strong = Text::_('COM_JEM_EXPIRED');
                                            } else {
                                                $pub_title_strong = Text::_('JLIB_HTML_PUBLISHED_ITEM');
                                            }
                                            
                                            // Build detailed tooltip
                                            $pub_tooltip = '<strong>' . $pub_title_strong . '</strong>';
                                            if (!empty($item->publish_up) && $item->publish_up != '0000-00-00 00:00:00') {
                                                $pub_tooltip .= '<br>' . Text::_('JLIB_HTML_START') . ': ' . HTMLHelper::_('date', $item->publish_up, 'Y-m-d H:i');
                                            }
                                            if (!empty($item->publish_down) && $item->publish_down != '0000-00-00 00:00:00') {
                                                $pub_tooltip .= '<br>' . Text::_('JPUBLISHED_FINISH') . ': ' . HTMLHelper::_('date', $item->publish_down, 'Y-m-d H:i');
                                            }
                                            $pub_tooltip .= '<br>' . Text::_('JLIB_HTML_PUBLISHED_UNPUBLISH');
                                            
                                        } elseif ($item->published == 0) {
                                            $pub_icon = 'unpublish';
                                            $pub_task = 'events.publish';
                                            $pub_tooltip = '<strong>' . Text::_('JUNPUBLISHED') . '</strong><br>' . Text::_('COM_JEM_PUBLISH_ITEM');
                                            
                                        } elseif ($item->published == 2) {
                                            $pub_icon = 'archive';
                                            $pub_task = 'events.publish';
                                            $pub_tooltip = '<strong>' . Text::_('JARCHIVED') . '</strong><br>' . Text::_('COM_JEM_UNPUBLISH_ITEM');
                                            
                                        } elseif ($item->published == -2) {
                                            $pub_icon = 'trash';
                                            $pub_task = 'events.publish';
                                            $pub_tooltip = '<strong>' . Text::_('JTRASHED') . '</strong><br>' . Text::_('COM_JEM_PUBLISH_ITEM');
                                            
                                        } else {
                                            $pub_icon = 'unpublish';
                                            $pub_task = 'events.publish';
                                            $pub_tooltip = '<strong>' . Text::_('JUNPUBLISHED') . '</strong><br>' . Text::_('COM_JEM_PUBLISH_ITEM');
                                        }
                                        ?>
                                        <a href="javascript:void(0);" 
                                           onclick="return Joomla.listItemTask('cb<?php echo $i; ?>','<?php echo $pub_task; ?>')"
                                           class="tbody-icon hasTooltip"
                                           data-bs-html="true"
                                           title="<?php echo htmlspecialchars($pub_tooltip, ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="icon-<?php echo $pub_icon; ?>" aria-hidden="true"></span>
                                        </a>
                                    <?php else : 
                                        // Display only, not clickable
                                        if ($item->published == 1) {
                                            $pub_icon = 'publish';
                                            $pub_title_strong = Text::_('JPUBLISHED');
                                            
                                            $now = Factory::getDate()->toSql();
                                            if (!empty($item->publish_up) && $item->publish_up > $now) {
                                                $pub_title_strong = Text::_('COM_JEM_PENDING');
                                            } elseif (!empty($item->publish_down) && $item->publish_down < $now && $item->publish_down != '0000-00-00 00:00:00') {
                                                $pub_title_strong = Text::_('COM_JEM_EXPIRED');
                                            } else {
                                                $pub_title_strong = Text::_('JLIB_HTML_PUBLISHED_ITEM');
                                            }
                                            
                                            $pub_tooltip = '<strong>' . $pub_title_strong . '</strong>';
                                            if (!empty($item->publish_up) && $item->publish_up != '0000-00-00 00:00:00') {
                                                $pub_tooltip .= '<br>' . Text::_('JLIB_HTML_START') . ': ' . HTMLHelper::_('date', $item->publish_up, 'Y-m-d H:i');
                                            }
                                            if (!empty($item->publish_down) && $item->publish_down != '0000-00-00 00:00:00') {
                                                $pub_tooltip .= '<br>' . Text::_('JPUBLISHED_FINISH') . ': ' . HTMLHelper::_('date', $item->publish_down, 'Y-m-d H:i');
                                            }
                                            
                                        } elseif ($item->published == 0) {
                                            $pub_icon = 'unpublish';
                                            $pub_tooltip = '<strong>' . Text::_('JUNPUBLISHED') . '</strong>';
                                            
                                        } elseif ($item->published == 2) {
                                            $pub_icon = 'archive';
                                            $pub_tooltip = '<strong>' . Text::_('JARCHIVED') . '</strong>';
                                            
                                        } elseif ($item->published == -2) {
                                            $pub_icon = 'trash';
                                            $pub_tooltip = '<strong>' . Text::_('JTRASHED') . '</strong>';
                                            
                                        } else {
                                            $pub_icon = 'unpublish';
                                            $pub_tooltip = '<strong>' . Text::_('JUNPUBLISHED') . '</strong>';
                                        }
                                        ?>
                                        <span class="tbody-icon disabled hasTooltip" 
                                              data-bs-html="true"
                                              title="<?php echo htmlspecialchars($pub_tooltip, ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="icon-<?php echo $pub_icon; ?>" aria-hidden="true"></span>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if (!empty($item->author)) : ?>
                                        <div class="break-word">
                                            <?php echo $this->escape($item->author); ?>
                                        </div>
                                    <?php else : ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                    <?php if (!empty($item->created_by_alias)) : ?>
                                        <div class="small text-muted">
                                            <?php echo $this->escape($item->created_by_alias); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell text-center">
                                    <span class="badge bg-info">
                                        <?php echo (int) $item->hits; ?>
                                    </span>
                                </td>
                                <td class="d-none d-md-table-cell text-center">
                                    <?php if (isset($item->registered) && $item->maxplaces > 0) : ?>
                                        <span class="badge <?php echo ($item->registered >= $item->maxplaces) ? 'bg-danger' : 'bg-success'; ?>" 
                                              title="<?php echo Text::sprintf('COM_JEM_REGISTERED_USERS', (int)$item->registered, (int)$item->maxplaces); ?>">
                                            <?php echo (int)$item->registered . ' / ' . (int)$item->maxplaces; ?>
                                        </span>
                                    <?php elseif (isset($item->registered)) : ?>
                                        <span class="badge bg-info" title="<?php echo Text::sprintf('COM_JEM_REGISTERED_USERS_UNLIMITED', (int)$item->registered); ?>">
                                            <?php echo (int)$item->registered; ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small d-none d-md-table-cell">
                                    <?php echo $this->escape($item->access_level); ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo (int) $item->id; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <input type="hidden" name="task" value="" />
    <input type="hidden" name="boxchecked" value="0" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>