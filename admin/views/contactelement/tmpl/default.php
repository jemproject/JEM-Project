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

$app = Factory::getApplication();
$function = $app->input->getCmd('function', 'jSelectContact');

// Initialize the variable and prepare the array for the checkboxes
$selectedIds = $this->selection ? explode(',', $this->selection) : [];
$selectedIds = array_map('trim', $selectedIds);
?>

<form action="index.php?option=com_jem&amp;view=contactelement&amp;tmpl=component" method="post" name="adminForm" id="adminForm">

    <table class="adminform">
        <tr>
            <td style="width: 100%;">
                <?php echo Text::_('COM_JEM_SEARCH') . ' ' . $this->lists['filter']; ?>
                <input type="text" name="filter_search" id="filter_search"
                       value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>"
                       class="text_area" onChange="document.adminForm.submit();"/>
                <button class="buttonfilter" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                <button class="buttonfilter" type="button"
                        onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>

                <button class="buttonfilter" type="button" onclick="jemGetSelectedContacts();"
                        style="background-color: #28a745; color: white; font-weight: bold; margin-left: 10px;">
                    <i class="icon-check"></i> <?php echo Text::_('COM_JEM_SELECT_CHECKED'); ?>
                </button>

                <button class="buttonfilter" type="button"
                        onclick="if (window.parent) window.parent.<?php echo $this->escape($function); ?>('', '<?php echo Text::_('COM_JEM_SELECTCONTACT') ?>');"><?php echo Text::_('COM_JEM_NOCONTACT') ?></button>
            </td>
        </tr>
    </table>

    <table class="table table-striped" id="articleList">
        <thead>
        <tr>
            <th style="width: 20px" class="center">
                <input type="checkbox" name="checkall-toggle" value=""
                       title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>"
                       onclick="if (window.Joomla) { Joomla.checkAll(this); } else { var cbs = document.getElementsByName('cid[]'); for(var i=0; i<cbs.length; i++) { cbs[i].checked = this.checked; jemUpdateSelection(cbs[i]); } }"/>
            </th>
            <th style="width: 7px" class="center"><?php echo Text::_('COM_JEM_NUM'); ?></th>
            <th style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_NAME', 'con.name', $this->lists['order_Dir'], $this->lists['order']); ?></th>
            <th style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_CITY', 'con.suburb', $this->lists['order_Dir'], $this->lists['order']); ?></th>
            <th style="text-align: left;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_COUNTRY', 'con.country', $this->lists['order_Dir'], $this->lists['order']); ?></th>
            <th style="text-align: left;"><?php echo Text::_('COM_JEM_EMAIL'); ?></th>
        </tr>
        </thead>

        <tfoot>
        <tr>
            <td colspan="5">
                <?php echo(method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks() : $this->pagination->getListFooter()); ?>
            </td>
        </tr>
        </tfoot>

        <tbody>
        <?php foreach ($this->rows as $i => $row) :
            $checked = in_array((string)$row->id, $selectedIds) ? 'checked="checked"' : '';
            ?>
            <tr class="row<?php echo $i % 2; ?>">
                <td class="center">
                    <input type="checkbox" id="cb<?php echo $i; ?>" name="cid[]" value="<?php echo $row->id; ?>"
                           onclick="jemUpdateSelection(this);"
                           data-name="<?php echo $this->escape(addslashes($row->name)); ?>" <?php echo $checked; ?> />
                </td>
                <td class="center"><?php echo $this->pagination->getRowOffset($i); ?></td>
                <td style="text-align: left;">
                    <a style="cursor:pointer;" onclick="if (window.parent) window.parent.<?php echo $this->escape($function); ?>('<?php echo $row->id; ?>', '<?php echo $this->escape(addslashes($row->name)); ?>');">
                        <?php echo $this->escape($row->name); ?>
                    </a>
                </td>
                <td style="text-align: left;"><?php echo $this->escape($row->suburb); ?></td>
                <td style="text-align: left;"><?php echo $this->escape($row->country); ?></td>
                <td style="text-align: left;"><?php echo $this->escape($row->email_to); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <input type="hidden" name="task" value=""/>
    <input type="hidden" name="function" value="<?php echo $this->escape($function); ?>"/>
    <input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>"/>
    <input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>"/>
    <input type="hidden" id="selection_holder" name="selection" value="<?php echo htmlspecialchars($this->selection, ENT_QUOTES, 'UTF-8'); ?>"/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script type="text/javascript">
    /**
     * Updates the hidden input whenever a checkbox is clicked.
     * This ensures that searching or filtering doesn't reset the current session's choices.
     */
    function jemUpdateSelection(cb) {
        var holder = document.getElementById('selection_holder');
        var selected = holder.value ? holder.value.split(',') : [];
        selected = selected.map(function (item) {
            return item.trim();
        }).filter(function (item) {
            return item !== "";
        });

        if (cb.checked) {
            if (selected.indexOf(cb.value) === -1) {
                selected.push(cb.value);
            }
        } else {
            var index = selected.indexOf(cb.value);
            if (index > -1) {
                selected.splice(index, 1);
            }
        }
        holder.value = selected.join(',');
    }

    /**
     * Sends the final list back to the parent form
     */
    function jemGetSelectedContacts() {
        var holder = document.getElementById('selection_holder');
        var ids = holder.value;

        // We need names too, so we collect them from checked inputs in the CURRENT view
        var checkboxes = document.getElementsByName('cid[]');
        var names = [];

        // Note: This only gets names of visible checked items. 
        // For a perfect solution, the names should also be persisted, 
        // but for IDs this is sufficient.
        for (var i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].checked) {
                names.push(checkboxes[i].getAttribute('data-name'));
            }
        }

        if (ids !== "") {
            if (window.parent) {
                window.parent.<?php echo $this->escape($function); ?>(ids, names.join(', '));
            }
        } else {
            alert("<?php echo Text::_('JLIB_HTML_PLEASE_MAKE_A_SELECTION_FROM_THE_LIST'); ?>");
        }
    }
</script>
