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
    .jem-toolbar {
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        justify-content: space-between;
        align-items: center;
        gap: 4px;
        background: #f8f9fa;
        padding: 6px 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-bottom: 15px;
        width: 100%;
        box-sizing: border-box;
    }

    .jem-toolbar .inputbox,
    .jem-toolbar select,
    .jem-toolbar button,
    .jem-toolbar .btn {
        height: 32px !important;
        line-height: 1 !important;
        padding: 0 6px !important;
        margin: 0 !important;
        font-size: 14px !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-sizing: border-box;
        border: 1px solid #ccc;
        flex-shrink: 0;
    }

    .jem-toolbar-group { display: flex; align-items: center; gap: 4px; }

    .btn-save-selection {
        background: #397039 !important;
        color: #fff !important;
        border-color: #218838 !important;
        font-weight: bold;
    }
    .btn-save-selection i { margin-right: 5px; }

    #filter_search { width: 100px !important; }

    .jem-limit-group {
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 3px;
        min-width: 85px;
		margin-right: 10px;
    }
    .jem-limit-group select { width: 100% !important; min-width: 55px !important; }

    .jem-selected-row { background-color: #f0fff4 !important; border-left: 4px solid #28a745; }
</style>

<div id="jem" class="jem_select_contact">
    <h1 class='componentheading'>
        <?php echo Text::_('COM_JEM_SELECT_CONTACT'); ?>
    </h1>

    <div class="clr"></div>

    <form action="<?php echo Route::_('index.php?option=com_jem&view=editevent&layout=choosecontact&tmpl=component&function='.$this->escape($function).'&'.Session::getFormToken().'=1'); ?>" method="post" name="adminForm" id="adminForm">

        <div id="jem_filter" class="jem-toolbar floattext">
            <div class="jem-toolbar-group">
                <select name="filter_type" id="filter_type  " class="inputbox" onchange="this.form.submit()">
                    <option value="1" <?php echo ($filter_type == 'con.name' ? 'selected' : ''); ?>><?php echo Text::_('COM_JEM_NAME'); ?></option>
                    <option value="3" <?php echo ($filter_type == '3' ? 'selected' : ''); ?>><?php echo Text::_('COM_JEM_CITY'); ?></option>
                    <option value="4" <?php echo ($filter_type == '4' ? 'selected' : ''); ?>><?php echo Text::_('COM_JEM_STATE'); ?></option>
                    <option value="5" <?php echo ($filter_type == '5' ? 'selected' : ''); ?>><?php echo Text::_('COM_JEM_COUNTRY'); ?></option>
                    <option value="6" <?php echo ($filter_type == '6' ? 'selected' : ''); ?>><?php echo Text::_('JCATEGORY'); ?></option>
                </select>

                <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox" onChange="document.adminForm.submit();" />
                <button type="submit" class="btn btn-primary" style="background-color: #1a2a4e; color: white;"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                <button type="button" class="btn btn-default" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
            </div>

            <div class="jem-toolbar-group" style="border-left: 1px solid #ddd; padding-left: 6px;">
                <button class="btn-save-selection" type="button" onclick="jemGetSelectedContacts();">
                    <i class="icon-check"></i> <?php echo Text::_('COM_JEM_SELECT_CHECKED'); ?>
                </button>
                <button type="button" class="btn btn-default" style="color: #a94442; border-color: #ebccd1;" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('', '<?php echo Text::_('COM_JEM_SELECT_CONTACT') ?>');">None</button>
            </div>

            <div class="jem-limit-group">
                <span style="font-size: 12px; font-weight: bold;">#</span>
                <?php echo $this->pagination->getLimitBox(); ?>
            </div>
        </div>

        <div class="jem-sort">
            <div style="display: flex; font-weight: bold; background: #f2f2f2; padding: 10px 0; border-bottom: 2px solid #ddd; font-size: 13px; align-items: center;">
                <div style="width: 40px; text-align: center;"><input type="checkbox" name="checkall-toggle" onclick="if (window.Joomla) { Joomla.checkAll(this); }" /></div>
                <div style="width: 35px;">#</div>
                <div style="flex: 2; padding-left:10px;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_NAME', 'con.name', $this->lists['order_Dir'], $this->lists['order'] ); ?></div>
                <div style="flex: 1.2;"><?php echo HTMLHelper::_('grid.sort', 'JCATEGORY', 'c.title', $this->lists['order_Dir'], $this->lists['order'] ); ?></div>
                <div style="flex: 1;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_CITY', 'con.suburb', $this->lists['order_Dir'], $this->lists['order'] ); ?></div>
                <div style="flex: 1;"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_COUNTRY', 'con.country', $this->lists['order_Dir'], $this->lists['order'] ); ?></div>
            </div>
        </div>

        <ul style="list-style: none; padding: 0; margin: 0; font-size: 14px;">
			<?php if (empty($this->rows)) : ?>
                <li style="text-align: center;"><div><?php echo Text::_('COM_JEM_NOCONTACTS'); ?></div></li>
            <?php else :?>
	            <?php foreach ($this->rows as $i => $row) : ?>
	                <?php
	                $isChecked = in_array((string)$row->id, $currentSelectedIds) ? ' checked="checked"' : '';
	                $rowClass = !empty($isChecked) ? ' jem-selected-row' : '';
	                ?>
	                <li class="<?php echo ($i % 2 == 0 ? 'row0' : 'row1') . $rowClass; ?>" style="display: flex; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee;">
	                    <div style="width: 40px; text-align: center;">
	                        <input type="checkbox" id="cb<?php echo $i; ?>" name="cid[]" value="<?php echo $row->id; ?>" data-name="<?php echo $this->escape(addslashes($row->name)); ?>" <?php echo $isChecked; ?> />
	                    </div>
	                    <div style="width: 35px; font-size: 12px; color: #999;"><?php echo $this->pagination->getRowOffset($i); ?></div>
	                    <div style="flex: 2; padding-left:10px;">
	                        <a style="cursor:pointer; text-decoration: none; color: #337ab7; font-weight: 500;" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('<?php echo $row->id; ?>', '<?php echo $this->escape(addslashes($row->name)); ?>');">
	                            <?php echo $this->escape($row->name); ?>
	                        </a>
	                    </div>
	                    <div style="flex: 1.2; font-size: 13px; color: #666;"><?php echo $this->escape($row->category_title); ?></div>
	                    <div style="flex: 1; font-size: 13px; color: #888;"><?php echo $this->escape($row->suburb); ?></div>
	                    <div style="flex: 1; font-size: 13px; color: #888; font-style: italic;"><?php echo $this->escape($row->country); ?></div>
	                </li>
	            <?php endforeach; ?>
            <?php endif; ?>
        </ul>

        <input type="hidden" name="task" value="selectcontact" />
        <input type="hidden" name="option" value="com_jem" />
        <input type="hidden" name="tmpl" value="component" />
        <input type="hidden" name="function" value="<?php echo $this->escape($function); ?>" />
        <input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
        <input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
    </form>

    <div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px;">
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
</script>