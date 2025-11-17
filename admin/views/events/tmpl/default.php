<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
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

// Define state arrays for jgrid.state helper
$publishedStates = [
    1  => ['events.unpublish', 'JPUBLISHED', 'JPUBLISHED', '', true, 'publish', 'publish'],
    0  => ['events.publish', 'JUNPUBLISHED', 'JUNPUBLISHED', '', true, 'unpublish', 'unpublish'],
    -2 => ['events.publish', 'JTRASHED', 'JTRASHED', '', true, 'trash', 'trash'],
];

$featuredStates = [
    1 => ['events.unfeatured', 'JFEATURED', '', 'JUNFEATURED', false, 'featured', 'featured'],
    0 => ['events.featured', 'JUNFEATURED', '', 'JFEATURED', false, 'unfeatured', 'unfeatured'],
];
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
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JFEATURED', 'a.featured', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_EVENT_TITLE', 'a.title', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_VENUE', 'loc.venue', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_DATE', 'a.dates', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo Text::_('COM_JEM_CATEGORY'); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ACCESS', 'a.access', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10 d-none d-md-table-cell">
                                    <?php echo Text::_('COM_JEM_REGISTERED'); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody <?php if ($saveOrder) : ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" data-nested="false"<?php endif; ?>>
                            <?php foreach ($this->items as $i => $item) :
                                $canEdit    = $user->can('edit', 'event', $item->id, $item->created_by);
                                $canCheckin = $user->authorise('core.manage', 'com_checkin') || $item->checked_out == $userId || $item->checked_out == 0;
                                $canEditOwn = $user->authorise('core.edit.own', 'com_jem') && $item->created_by == $userId;
                                $canChange  = $user->authorise('core.edit.state', 'com_jem') && $canCheckin;
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
                                <td class="text-center">
                                    <?php 
                                    // Best Practice: Use jgrid.state for published status
                                    echo HTMLHelper::_('jgrid.state', $publishedStates, $item->published, $i, 'events.', $canChange, true);
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?php 
                                    // Best Practice: Use jgrid.state for featured status
                                    echo HTMLHelper::_('jgrid.state', $featuredStates, $item->featured, $i, 'events.', $canChange, true, 'cb');
                                    ?>
                                </td>
                                <td>
                                    <div class="break-word">
                                        <?php if ($item->checked_out) : ?>
                                            <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'events.', $canCheckin); ?>
                                        <?php endif; ?>
                                        <?php if ($canEdit || $canEditOwn) : ?>
                                            <a href="<?php echo Route::_('index.php?option=com_jem&task=event.edit&id=' . (int) $item->id); ?>" title="<?php echo Text::_('JACTION_EDIT'); ?>" class="hasTooltip">
                                                <?php echo $this->escape($item->title); ?>
                                            </a>
                                        <?php else : ?>
                                            <span title="<?php echo Text::sprintf('JFIELD_ALIAS_LABEL', $this->escape($item->alias)); ?>">
                                                <?php echo $this->escape($item->title); ?>
                                            </span>
                                        <?php endif; ?>
                                        <div class="small">
                                            <?php echo Text::sprintf('JGLOBAL_LIST_ALIAS', $this->escape($item->alias)); ?>
                                        </div>
                                        <?php if (!empty($item->author)) : ?>
                                            <div class="small">
                                                <?php echo Text::_('JAUTHOR') . ': ' . $this->escape($item->author); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if ($item->venue) : ?>
                                        <div class="break-word">
                                            <?php echo $this->escape($item->venue); ?>
                                        </div>
                                    <?php else : ?>
                                        <span class="badge bg-secondary"><?php echo Text::_('COM_JEM_NO_VENUE'); ?></span>
                                    <?php endif; ?>
                                    <?php if ($item->city) : ?>
                                        <div class="small text-muted">
                                            <span class="icon-location" aria-hidden="true"></span>
                                            <?php echo $this->escape($item->city); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if ($item->dates) : ?>
                                        <div>
                                            <?php echo HTMLHelper::_('date', $item->dates, Text::_('DATE_FORMAT_LC4')); ?>
                                        </div>
                                        <?php if ($item->times) : ?>
                                            <div class="small text-muted">
                                                <span class="icon-clock" aria-hidden="true"></span>
                                                <?php echo substr($item->times, 0, 5); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="badge bg-secondary"><?php echo Text::_('COM_JEM_NO_DATE'); ?></span>
                                    <?php endif; ?>
                                    <?php if ($item->enddates && $item->enddates != $item->dates) : ?>
                                        <div class="small text-muted">
                                            <?php echo Text::_('COM_JEM_TO') . ' ' . HTMLHelper::_('date', $item->enddates, Text::_('DATE_FORMAT_LC4')); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if (!empty($item->categories)) : ?>
                                        <div class="break-word">
                                            <?php foreach ($item->categories as $j => $category) : ?>
                                                <?php if ($j > 0) echo ', '; ?>
                                                <span class="badge bg-secondary" style="<?php echo !empty($category->color) ? 'background-color: ' . $category->color . ' !important;' : ''; ?>">
                                                    <?php echo $this->escape($category->catname); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else : ?>
                                        <span class="badge bg-warning text-dark"><?php echo Text::_('Juncategorised'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="small d-none d-md-table-cell">
                                    <?php echo $this->escape($item->access_level); ?>
                                </td>
                                <td class="d-none d-md-table-cell text-center">
                                    <?php if (isset($item->registered) && $item->maxplaces > 0) : ?>
                                        <span class="badge <?php echo ($item->registered >= $item->maxplaces) ? 'bg-danger' : 'bg-success'; ?>">
                                            <?php echo (int)$item->registered . ' / ' . (int)$item->maxplaces; ?>
                                        </span>
                                    <?php elseif (isset($item->registered)) : ?>
                                        <span class="badge bg-info">
                                            <?php echo (int)$item->registered; ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo (int) $item->id; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php // Load the pagination. ?>
                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <input type="hidden" name="task" value="" />
    <input type="hidden" name="boxchecked" value="0" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>