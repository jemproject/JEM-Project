<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\Registry\Registry;

$user      = JemFactory::getUser();
$userId    = $user->get('id');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$canOrder  = $user->authorise('core.edit.state', 'com_jem');
$saveOrder = $listOrder == 'a.ordering';
$params        = (isset($this->state->params)) ? $this->state->params : new Registry();
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('table.columns');
?>

<form action="<?php echo Route::_('index.php?option=com_jem&view=groups'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

        <table class="table table-striped itemList" id="groupList">
            <thead>
            <tr>
                    <th style="width: 5px" class="center">
                        <input type="checkbox" name="checkall-toggle" value=""
                            title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>"
                            onclick="Joomla.checkAll(this)" />
                    </th>
                    <th style="width: 30%" class="title">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_GROUP_NAME', 'a.name', $listDirn, $listOrder); ?>
                    </th>
                    <th>
                        <?php echo Text::_('COM_JEM_DESCRIPTION'); ?>
                    </th>
                    <th style="width: 8%" class="center">
                        <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?>
                    </th>
                    <th style="width: 1%" class="title">
                        <?php echo HTMLHelper::_('searchtools.sort', 'COM_JEM_ID', 'a.id', $listDirn, $listOrder); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->items as $i => $row) :
                    $ordering   = ($listOrder == 'ordering');
                    $canCreate  = $user->authorise('core.create');
                    $canEdit    = $user->authorise('core.edit');
                    $canCheckin = $user->authorise('core.manage', 'com_checkin') || $row->checked_out == $userId || $row->checked_out == 0;
                    $canChange  = $user->authorise('core.edit.state') && $canCheckin;
                    $link       = 'index.php?option=com_jem&amp;task=group.edit&amp;id='.$row->id;
                    $published  = HTMLHelper::_('jgrid.published', $row->published, $i, 'groups.', $canChange, 'cb');
                ?>
                <tr class="row<?php echo $i % 2; ?>">
                    <td class="center"><?php echo HTMLHelper::_('grid.id', $i, $row->id); ?></td>
                    <td>
                        <?php if ($row->checked_out) : ?>
                            <?php echo HTMLHelper::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'groups.', $canCheckin); ?>
                        <?php endif; ?>
                        <?php if ($canEdit) : ?>
                            <a href="<?php echo $link; ?>">
                                <?php echo $this->escape($row->name); ?>
                            </a>
                        <?php else : ?>
                                <?php echo $this->escape($row->name); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                            $desc = $row->description;
                            $descoutput = strip_tags($desc);
                            echo $this->escape($descoutput);
                        ?>
                    </td>
                    <td class="center"><?php echo $published; ?></td>
                    <td class="center"><?php echo $row->id; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="ms-auto mb-4 me-0">
            <?php echo  (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks(null) : $this->pagination->getListFooter()); ?>
        </div>
    </div>

    <div>
        <input type="hidden" name="task" value="" />
        <input type="hidden" name="boxchecked" value="0" />
        <?php echo $this->filterForm->renderControlFields(); ?>

        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
