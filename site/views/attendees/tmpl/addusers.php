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
use Joomla\CMS\Form\Form;

$function = Factory::getApplication()->input->getCmd('function', 'jSelectUsers');
$checked = 0;

HTMLHelper::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.'/helpers/html');

// Get the form.
Form::addFormPath(JPATH_COMPONENT . '/models/forms');
$form = Form::getInstance('com_jem.addusers', 'addusers');

if (empty($form)) {
    return false;
}
?>

<script>
    function tableOrdering( order, dir, view )
    {
        var form = document.getElementById("adminForm");

        form.filter_order.value     = order;
        form.filter_order_Dir.value    = dir;
        form.submit( view );
    }
</script>
<script>
    function checkList(form)
    {
        var r='', i, n, e;
        for (i=0, n=form.elements.length; i<n; i++)
        {
            e = form.elements[i];
            if (e.type == 'checkbox' && e.id.indexOf('cb') === 0 && e.checked)
            {
                if (r) { r += ','; }
                r += e.value;
            }
        }
        return r;
    }

    function jemCheckAllUsers(source)
    {
        var form = source.form || document.adminForm,
            boxes,
            checked = 0,
            i;

        if (!form) {
            return;
        }

        boxes = form.querySelectorAll('input[name="cid[]"]');

        for (i = 0; i < boxes.length; i++) {
            if (boxes[i].disabled) {
                continue;
            }

            boxes[i].checked = source.checked;

            if (boxes[i].checked) {
                checked++;
            }
        }

        if (form.boxchecked) {
            form.boxchecked.value = checked;
        }
    }

    function checkPlaces(form)
    {
        var result = [],
            boxes = form.querySelectorAll('input[name="cid[]"]'),
            field,
            i;

        for (i = 0; i < boxes.length; i++) {
            if (!boxes[i].checked || boxes[i].disabled) {
                continue;
            }

            field = form.querySelector('[data-user-places="' + boxes[i].value + '"]');
            result.push(boxes[i].value + ':' + (field ? field.value : 0));
        }

        return result.join(',');
    }
</script>

<?php
if (!function_exists('jem_addusers_account_status')) {
    function jem_addusers_account_status($row)
    {
        if (!empty($row->block)) {
            return '<span class="jem-user-account-state jem-user-account-blocked" title="' . Text::_('JDISABLED') . '"><i class="fa fa-ban" aria-hidden="true"></i><span class="visually-hidden">' . Text::_('JDISABLED') . '</span></span>';
        }

        if (!empty($row->activation)) {
            return '<span class="jem-user-account-state jem-user-account-unpublished" title="' . Text::_('JUNPUBLISHED') . '"><i class="fa fa-eye-slash" aria-hidden="true"></i><span class="visually-hidden">' . Text::_('JUNPUBLISHED') . '</span></span>';
        }

        return '<span class="jem-user-account-state jem-user-account-active" title="' . Text::_('JENABLED') . '"><i class="fa fa-check" aria-hidden="true"></i><span class="visually-hidden">' . Text::_('JENABLED') . '</span></span>';
    }
}
?>

<style>
    #jem.jem_select_users {
        padding: 1rem;
    }

    #jem.jem_select_users #jem_filter {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .5rem;
        margin: 0 0 1rem;
        padding: .75rem;
        border-radius: .25rem;
    }

    #jem.jem_select_users #jem_filter .jem_fleft,
    #jem.jem_select_users #jem_filter .jem_fright {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .5rem;
        float: none;
    }

    #jem.jem_select_users #jem_filter input,
    #jem.jem_select_users #jem_filter select {
        max-width: 18rem;
    }

    #jem.jem_select_users .jem-users-select,
    #jem.jem_select_users .jem-users-number,
    #jem.jem_select_users .jem-users-status {
        width: 1%;
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
    }

    #jem.jem_select_users .jem-users-booked,
    #jem.jem_select_users .jem-users-places {
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
    }

    #jem.jem_select_users .jem-users-name {
        text-align: left;
        vertical-align: middle;
    }

    #jem.jem_select_users .jem-addusers-options {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .75rem 1rem;
        margin: 1rem 0;
    }

    #jem.jem_select_users .eventtable {
        table-layout: auto;
    }

    #jem.jem_select_users .eventtable th,
    #jem.jem_select_users .eventtable td {
        padding-left: .75rem;
        padding-right: .75rem;
    }

    #jem.jem_select_users .jem-users-status {
        min-width: 5rem;
    }

    #jem.jem_select_users .jem-users-booked {
        min-width: 8.5rem;
    }

    #jem.jem_select_users .jem-users-places {
        min-width: 6.75rem;
    }

    #jem.jem_select_users .jem-user-account-state {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.5rem;
        height: 1.5rem;
        border-radius: 50%;
    }

    #jem.jem_select_users .jem-user-account-active {
        color: #198754;
    }

    #jem.jem_select_users .jem-user-account-blocked {
        color: #b02a37;
    }

    #jem.jem_select_users .jem-user-account-unpublished {
        color: #6c757d;
    }

    #jem.jem_select_users .jem-user-places-input {
        width: 4.5rem;
        text-align: center;
    }
</style>

<div id="jem" class="jem_select_users">
    <h1 class='componentheading'>
        <?php echo Text::_('COM_JEM_SELECT_USERS_AND_STATUS'); ?>
    </h1>



    <div class="clr"></div>

    <form action="<?php echo Route::_('index.php?option=com_jem&view=attendees&layout=addusers&tmpl=component&function='.$this->escape($function).'&id='.$this->event->id.'&'.Session::getFormToken().'=1'); ?>" method="post" name="adminForm" id="adminForm">

        <?php if(1) : ?>
        <div id="jem_filter" class="floattext">
            <div class="jem_fleft">
                <?php
                echo '<label for="filter_type">'.Text::_('COM_JEM_FILTER').'</label>&nbsp;';
                echo $this->searchfilter.'&nbsp;';
                ?>
                <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox" onChange="document.adminForm.submit();" />
                <button type="submit" class="pointer btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                <button type="button" class="pointer btn btn-secondary" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
            </div>
            <div class="jem_fright">
                <?php
                echo '<label for="limit">'.Text::_('COM_JEM_DISPLAY_NUM').'</label>&nbsp;';
                echo $this->pagination->getLimitBox();
                ?>
            </div>
        </div>
        <?php endif; ?>

        <table class="eventtable table table-striped" style="width:100%" summary="jem">
            <thead>
                <tr>
                    <th style="width: 1%" class="jem-users-select"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="jemCheckAllUsers(this)" /></th>
                    <th style="width: 1%" class="sectiontableheader jem-users-number"><?php echo Text::_('COM_JEM_NUM'); ?></th>
                    <th class="sectiontableheader jem-users-name"><?php echo Text::_('COM_JEM_NAME'); ?></th>
                    <th style="width: 10%" class="jem-users-status"><?php echo Text::_('COM_JEM_STATUS'); ?></th>
                    <th style="width: 10%" class="jem-users-booked"><?php echo Text::_('COM_JEM_BOOKED_PLACES'); ?></th>
                    <th style="width: 10%" class="jem-users-places"><?php echo Text::_('COM_JEM_PLACES'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($this->rows)) : ?>
                    <tr style="text-align: center;"><td colspan="6"><?php echo Text::_('COM_JEM_NOUSERS'); ?></td></tr>
                <?php else :?>
                    <?php foreach ($this->rows as $i => $row) : ?>
                    <?php $canSelectUser = empty($row->block) && empty($row->activation) && ($row->places_max === '' || (int) $row->places_max > 0); ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <td class="jem-users-select">
                            <input type="checkbox" id="cb<?php echo $i; ?>" name="cid[]" value="<?php echo (int) $row->id; ?>" <?php echo $canSelectUser ? '' : 'disabled="disabled"'; ?> />
                        </td>
                        <td class="jem-users-number"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
                        <td class="jem-users-name"><?php echo $this->escape($row->name); ?></td>
                        <td class="jem-users-status"><?php echo jem_addusers_account_status($row); ?></td>
                        <td class="jem-users-booked"><?php echo (int) $row->booked_places; ?></td>
                        <td class="jem-users-places">
                            <input class="jem-user-places-input" type="number" data-user-places="<?php echo (int) $row->id; ?>" value="<?php echo (int) $row->places_default; ?>" min="<?php echo (int) $row->places_min; ?>" max="<?php echo $this->escape((string) $row->places_max); ?>" <?php echo $canSelectUser ? '' : 'disabled="disabled"'; ?> />
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php
        if($this->event->maxbookeduser != 0)
        {
            $placesavailableuser = $this->event->maxbookeduser;
        }else{
            $placesavailableuser= null;
        }
        ?>

        <div class="jem-addusers-options">
            <div class="choose-status">
                <?php echo Text::_('COM_JEM_SELECT');?> <?php echo $form->getLabel('status'); ?> <?php echo $form->getInput('status'); ?>
            </div>
            <div class="choose-places">
                <?php echo Text::_('COM_JEM_SELECT');?> <?php echo Text::_('COM_JEM_PLACES'); ?>
            </div>
            <div style="padding-right:10px;">
                <input id="places" name="places" type="number" style="text-align: center; width:auto;" value="<?php echo $this->event->minbookeduser; ?>" max="<?php echo ($placesavailableuser > 0 ?  $placesavailableuser : ($placesavailableuser ?? '')); ?>" min="<?php echo $this->event->minbookeduser; ?>">
            </div>
            <?php if ($this->event->recurrence_type && $this->event->seriesbooking): ?>
                <div class="choose-places">
                    <?php echo Text::_('COM_JEM_SERIES_BOOKED').':'; ?>
                    <input type="checkbox" id="seriesbooking" name="seriesbooking" />
                </div>
            <?php else : ?>
                <input type="hidden" name="seriesbooking" value=-1 />
            <?php endif; ?>
        </div>

        <input type="hidden" name="task" value="selectusers" />
        <input type="hidden" name="option" value="com_jem" />
        <input type="hidden" name="tmpl" value="component" />
        <input type="hidden" name="function" value="<?php echo $this->escape($function); ?>" />
        <input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
        <input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
        <input type="hidden" name="boxchecked" value="<?php echo $checked; ?>" />
    </form>
    <div class="jem_fright">
        <button type="button" class="pointer btn btn-primary" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>_newusers(checkList(document.adminForm), document.adminForm.boxchecked.value, document.adminForm.status.value, checkPlaces(document.adminForm), <?php echo $this->event->id; ?>, document.adminForm.seriesbooking.value, '<?php echo Session::getFormToken(); ?>');">
            <?php echo Text::_('COM_JEM_SAVE'); ?>
        </button>
    </div>
    <div class="pagination">
        <?php echo $this->pagination->getPagesLinks(); ?>
    </div>
</div>
