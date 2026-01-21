<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

$function = Factory::getApplication()->input->getCmd('function', 'jSelectVenue');
?>

<form action="index.php?option=com_jem&amp;view=venueelement&amp;tmpl=component" method="post" name="adminForm" id="adminForm">

<table class="adminform">
    <tr>
        <td style="width: 100%;"><div class="input-group">
            <?php echo $this->lists['filter']; ?>&nbsp;
            <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="text_area form-control" onChange="document.adminForm.submit();" />&nbsp;
            <button type="submit" class="filter-search-bar__button btn btn-primary"><span class="filter-search-bar__button-icon icon-search" aria-hidden="true"></span></button>&nbsp;
            <button type="button" class="filter-search-bar__button btn btn-success" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>&nbsp;
            <button type="button" class="filter-search-bar__button btn btn-danger"" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('', '<?php echo Text::_('COM_JEM_SELECTVENUE') ?>');"><?php echo Text::_('COM_JEM_NOVENUE')?></button>
            </div>
        </td>
    </tr>
</table>

<table class="table table-striped" id="articleList">
    <thead>
        <tr>
            <th class="center" style="width: 7px;"><?php echo Text::_('COM_JEM_NUM'); ?></th>
            <th style="text-align: left;" class="title"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_VENUE', 'l.venue', $this->lists['order_Dir'], $this->lists['order'], 'venueelement' ); ?></th>
            <th style="text-align: center;" class="title"><?php echo Text::_('COM_JEM_COLOR') ?></th>
            <th style="text-align: left;" class="title"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order'], 'venueelement' ); ?></th>
            <th style="text-align: left;" class="title"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_STATE', 'l.state', $this->lists['order_Dir'], $this->lists['order']); ?></th>
            <th style="text-align: left;" class="title center"><?php echo Text::_('COM_JEM_COUNTRY'); ?></th>
        </tr>
    </thead>

    <tfoot>
        <tr>
            <td colspan="6">
                <?php echo (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks() : $this->pagination->getListFooter()); ?>
            </td>
        </tr>
    </tfoot>

    <tbody>
        <?php foreach ($this->rows as $i => $row) : ?>
         <tr class="row<?php echo $i % 2; ?>">
            <td class="center"><?php echo $this->pagination->getRowOffset($i); ?></td>
            <td style="text-align: left;">
                 <a class="pointer" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('<?php echo $row->id; ?>', '<?php echo $this->escape(addslashes($row->venue)); ?>');"><?php echo $this->escape($row->venue); ?></a>
            </td>
            <td class="center">
            <div class="colorpreview<?php echo ($this->escape($row->color) == '') ? ' transparent-color" title="transparent"' : '" style="background-color:' . $this->escape($row->color) . '"' ?> aria-labelledby="color-desc-<?php echo $this->escape($row->id); ?>"></div></td>
            <td style="text-align: left;"><?php echo $this->escape($row->city); ?></td>
            <td style="text-align: left;"><?php echo $this->escape($row->state); ?></td>
            <td class="center"><?php echo $this->escape($row->country); ?></td>
        </tr>

        <?php endforeach; ?>

    </tbody>

</table>

<p class="copyright">
    <?php echo JEMAdmin::footer( ); ?>
</p>

<input type="hidden" name="task" value="" />
<input type="hidden" name="function" value="<?php echo $this->escape($function); ?>" />
<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>
