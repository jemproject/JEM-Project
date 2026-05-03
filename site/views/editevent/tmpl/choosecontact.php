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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$app = Factory::getApplication();
$function = $app->input->getCmd('function', 'jSelectContact');

// Logic for pre-selecting checkboxes
$selectedParam = $app->input->getString('selected', '');
$currentSelectedIds = array();
if (!empty($selectedParam)) {
    $currentSelectedIds = array_map('trim', explode(',', $selectedParam));
}

// Get the current search field to keep it selected in the dropdown
$filter_type = $app->getUserStateFromRequest('com_jem.selectcontact.filter_type', 'filter_type', 0, 'int');
?>

<style>
    #jem_filter {
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        justify-content: space-between;
        align-items: center;
        gap: 4px;
        background-color: #f0a800;
        padding: 6px 10px;
        margin-bottom: 20px;
        width: 100%;
        box-sizing: border-box;
    }

    .jem_fleft {
        display: flex;
        flex-wrap: nowrap;
        align-items: stretch;
        gap: 4px;
        flex: 1 1 auto;
        margin-right: 20px;
    }

    .jem_fright {
        display: flex;
        align-items: center;
        gap: 4px;
        flex-shrink: 0;
    }

    .jem_fleft select,
    .jem_fleft input[type="text"],
    .jem_fleft button,
    .jem_fright select {
        height: 32px !important;
        padding: 0 6px;
        margin: 0 !important;
        font-size: 15px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        white-space: nowrap;
    }

    #filter_search {
        flex: 0 1 120px;
        min-width: 80px;
    }

    .jem_fright select {
        width: auto !important;
        min-width: 80px;
        padding-right: 20px;
    }

    .jem_fright label {
        margin: 0;
        line-height: 34px;
        font-weight: bold;
    }

    @media (max-width: 768px) {
        #jem_filter { flex-wrap: wrap; }
        .jem_fleft { flex-wrap: wrap; margin-right: 0; }
    }
</style>

<div id="jem" class="jem_select_contact">
    <h1 class='componentheading'>
        <?php echo Text::_('COM_JEM_SELECT_CONTACT'); ?>
    </h1>

    <div class="clr"></div>

    <form action="<?php echo Route::_('index.php?option=com_jem&view=editevent&layout=choosecontact&tmpl=component&function='.$this->escape($function).'&'.Session::getFormToken().'=1'); ?>" method="post" name="adminForm" id="adminForm">
        <div id="jem_filter">
            <div class="jem_fleft">
                <select name="filter_type" id="filter_type" class="inputbox" onchange="this.form.submit()">
                    <option value="1" <?php echo ($filter_type == '1' ? 'selected' : ''); ?>><?php echo Text::_('COM_JEM_NAME'); ?></option>
                    <option value="3" <?php echo ($filter_type == '3' ? 'selected' : ''); ?>><?php echo Text::_('COM_JEM_CITY'); ?></option>
                    <option value="4" <?php echo ($filter_type == '4' ? 'selected' : ''); ?>><?php echo Text::_('COM_JEM_STATE'); ?></option>
                    <option value="5" <?php echo ($filter_type == '5' ? 'selected' : ''); ?>><?php echo Text::_('COM_JEM_COUNTRY'); ?></option>
                    <option value="6" <?php echo ($filter_type == '6' ? 'selected' : ''); ?>><?php echo Text::_('JCATEGORY'); ?></option>
                </select>

                <input type="text" name="filter_search" id="filter_search" placeholder="Search..." value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox" onChange="document.adminForm.submit();" />

                <button type="submit" class="btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
                <button type="button" class="btn-save-selection" onclick="jemGetSelectedContacts();">
                    <?php echo Text::_('COM_JEM_SELECT_CHECKED'); ?>
                </button>
                <button type="button" class="btn btn-primary" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('', '<?php echo Text::_('COM_JEM_SELECT_CONTACT') ?>');"><?php echo Text::_('COM_JEM_NOCONTACT')?></button>
            </div>

            <div class="jem_fright">
                <label for="limit">Limit</label>
                <?php echo $this->pagination->getLimitBox(); ?>
            </div>
        </div>

        <table class="eventtable table table-striped" style="width:100%" summary="jem">
            <thead>
            <tr>
                <th style="width: 7px" class="sectiontableheader"><input type="checkbox" name="checkall-toggle" onclick="if (window.Joomla) { Joomla.checkAll(this); }" /></th>
                <th style="width: 7px" class="sectiontableheader"><?php echo Text::_('COM_JEM_NUM'); ?></th>
                <th style="text-align: left;" class="sectiontableheader"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_NAME', 'con.name', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
                <th style="text-align: left;" class="sectiontableheader"><?php echo HTMLHelper::_('grid.sort', 'JCATEGORY', 'c.title', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
                <th style="text-align: left;" class="sectiontableheader"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_CITY', 'con.suburb', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
                <th style="text-align: left;" class="sectiontableheader"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_COUNTRY', 'con.country', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($this->rows)) : ?>
                <tr style="text-align: center;"><td colspan="6"><?php echo Text::_('COM_JEM_NOCONTACTS'); ?></td></tr>
            <?php else :?>
                <?php foreach ($this->rows as $i => $row) : ?>
                    <?php
                    $isChecked = in_array((string)$row->id, $currentSelectedIds) ? ' checked="checked"' : '';
                    ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <td class="center"> <input type="checkbox" id="cb<?php echo $i; ?>" name="cid[]" value="<?php echo $row->id; ?>" data-name="<?php echo $this->escape(addslashes($row->name)); ?>" <?php echo $isChecked; ?> /></td>
                        <td class="center"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
                        <td style="text-align: left;">
                            <a class="pointer" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('<?php echo $row->id; ?>', '<?php echo $this->escape(addslashes($row->name)); ?>');"><?php echo $this->escape($row->name); ?></a>
                        </td>
                        <td style="text-align: left;"><?php echo $this->escape($row->category_title); ?></td>
                        <td style="text-align: left;"><?php echo $this->escape($row->suburb); ?></td>
                        <td style="text-align: left;"><?php echo $this->escape($row->country); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <input type="hidden" name="task" value="selectcontact" />
        <input type="hidden" name="option" value="com_jem" />
        <input type="hidden" name="tmpl" value="component" />
        <input type="hidden" name="function" value="<?php echo $this->escape($function); ?>" />
        <input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
        <input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
    </form>

    <div class="pagination">
        <?php echo $this->pagination->getPagesLinks(); ?>
    </div>
</div>

<script>
    function jemGetSelectedContacts() {
        var checkboxes = document.getElementsByName('cid[]');
        var ids = [], names = [];
        for (var i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].checked) {
                ids.push(checkboxes[i].value);
                names.push(checkboxes[i].getAttribute('data-name'));
            }
        }
        if (ids.length > 0 && window.parent) {
            window.parent.<?php echo $this->escape($function); ?>(ids.join(','), names.join(', '));
        } else {
            alert("<?php echo Text::_('JLIB_HTML_PLEASE_MAKE_A_SELECTION_FROM_THE_LIST'); ?>");
        }
    }

    function tableOrdering( order, dir, view )
    {
        var form = document.getElementById("adminForm");
        form.filter_order.value     = order;
        form.filter_order_Dir.value    = dir;
        form.submit( view );
    }
</script>