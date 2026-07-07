<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$labels = array(
    'registration_id' => Text::_('COM_JEM_ATTENDEES_REGID'),
    'name' => Text::_('COM_JEM_NAME'),
    'username' => Text::_('COM_JEM_USERNAME'),
    'user_id' => Text::_('COM_JEM_USER_ID'),
    'email' => Text::_('COM_JEM_EMAIL'),
    'places' => Text::_('COM_JEM_TABLE_PLACES'),
    'uregdate' => Text::_('COM_JEM_ATTENDEE_REGISTRATION_DATE'),
    'event' => Text::_('COM_JEM_EVENT'),
    'event_date' => Text::_('COM_JEM_ATTENDEE_EVENT_DATE'),
    'venue' => Text::_('COM_JEM_VENUE'),
    'status' => Text::_('COM_JEM_STATUS'),
    'comment' => Text::_('COM_JEM_COMMENT'),
);

if (!function_exists('jem_attendeeregistrations_status_label')) {
    function jem_attendeeregistrations_status_label($row)
    {
        $status = (int) ($row->status ?? 0);

        if ($status === 1 && (int) ($row->waiting ?? 0) === 1) {
            return Text::_('COM_JEM_ATTENDEES_ON_WAITINGLIST');
        }

        switch ($status) {
            case 1:
                return Text::_('COM_JEM_ATTENDEES_ATTENDING');
            case 0:
                return Text::_('COM_JEM_ATTENDEES_INVITED');
            case -1:
                return Text::_('COM_JEM_ATTENDEES_NOT_ATTENDING');
        }

        return Text::_('COM_JEM_ATTENDEES_STATUS_UNKNOWN');
    }
}

if (!function_exists('jem_attendeeregistrations_cell')) {
    function jem_attendeeregistrations_cell($view, $row, $column)
    {
        switch ($column) {
            case 'registration_id':
                return (string) ($row->registration_id ?? '');
            case 'name':
                return $view->escape($row->name ?? '');
            case 'username':
                return $view->escape($row->username ?? '');
            case 'user_id':
                return (string) ($row->uid ?? '');
            case 'email':
                return $view->escape($row->email ?? '');
            case 'places':
                return (string) ($row->places ?? '');
            case 'uregdate':
                return $view->escape($row->uregdate ?? '');
            case 'event':
                $slug = !empty($row->event_alias) ? (int) $row->eventid . ':' . $row->event_alias : (int) ($row->eventid ?? 0);
                $title = $view->escape($row->event_title ?? '');

                return $slug ? '<a href="' . Route::_(JemHelperRoute::getEventRoute($slug)) . '">' . $title . '</a>' : $title;
            case 'event_date':
                return JemOutput::formatShortDateTime($row->dates ?? '', $row->times ?? '', $row->enddates ?? '', $row->endtimes ?? '');
            case 'venue':
                $slug = !empty($row->venue_alias) ? (int) $row->venue_id . ':' . $row->venue_alias : (int) ($row->venue_id ?? 0);
                $venue = $view->escape($row->venue ?? '');

                return $slug ? '<a href="' . Route::_(JemHelperRoute::getVenueRoute($slug)) . '">' . $venue . '</a>' : $venue;
            case 'status':
                if (empty($view->permissions->canManageAttendees)) {
                    return jem_attendeeregistrations_status_label($row);
                }

                $value = (int) ($row->status ?? 0);

                if ($value === 1 && (int) ($row->waiting ?? 0) === 1) {
                    $value = 2;
                }

                $options = array(
                    HTMLHelper::_('select.option', -1, Text::_('COM_JEM_ATTENDEES_NOT_ATTENDING')),
                    HTMLHelper::_('select.option', 0, Text::_('COM_JEM_ATTENDEES_INVITED')),
                    HTMLHelper::_('select.option', 1, Text::_('COM_JEM_ATTENDEES_ATTENDING')),
                    HTMLHelper::_('select.option', 2, Text::_('COM_JEM_ATTENDEES_ON_WAITINGLIST')),
                );

                return HTMLHelper::_(
                    'select.genericlist',
                    $options,
                    'registration_status_' . (int) ($row->registration_id ?? 0),
                    array(
                        'class' => 'form-select form-select-sm',
                        'onchange' => 'jemAttendeeRegistrationSetStatus(' . (int) ($row->registration_id ?? 0) . ', this.value);',
                    ),
                    'value',
                    'text',
                    $value
                );
            case 'comment':
                return nl2br($view->escape($row->comment ?? ''));
        }

        return '';
    }
}

if (!function_exists('jem_attendeeregistrations_renotify_button')) {
    function jem_attendeeregistrations_renotify_button($row)
    {
        if (empty($row->registration_id)) {
            return '';
        }

        $label = Text::_('COM_JEM_ATTENDEE_REGISTRATION_RENOTIFY');
        $tooltip = Text::_('COM_JEM_ATTENDEE_REGISTRATION_RENOTIFY_DESC');
        $icon = '<span class="fa fa-envelope" aria-hidden="true"></span><span class="fa fa-share" aria-hidden="true"></span><span class="visually-hidden">' . htmlspecialchars($label, ENT_COMPAT, 'UTF-8') . '</span>';

        return '<button type="button" class="btn btn-sm btn-outline-primary hasTooltip jem-attendee-registration-icon-button" title="' . htmlspecialchars($tooltip, ENT_COMPAT, 'UTF-8') . '" onclick="jemAttendeeRegistrationRenotify(' . (int) $row->registration_id . ');">' . $icon . '</button>';
    }
}

if (!function_exists('jem_attendeeregistrations_manage_button')) {
    function jem_attendeeregistrations_manage_button($view, $row)
    {
        $eventId = (int) ($row->eventid ?? 0);

        if ($eventId <= 0) {
            return '';
        }

        $query = array(
            'option' => 'com_jem',
            'view' => 'attendees',
            'id' => $eventId,
            'tmpl' => 'component',
        );

        $username = trim((string) ($row->username ?? ''));
        $name = trim((string) ($row->name ?? ''));

        if ($username !== '') {
            $query['filter'] = 2;
            $query['filter_search'] = $username;
        } elseif ($name !== '') {
            $query['filter'] = 1;
            $query['filter_search'] = $name;
        }

        $modalId = 'jem-attendee-registration-manage-' . (int) ($row->registration_id ?? $eventId);
        $url = 'index.php?' . http_build_query($query, '', '&');
        $label = Text::_('COM_JEM_ATTENDEE_REGISTRATION_MANAGE');
        $tooltip = Text::_('COM_JEM_ATTENDEE_REGISTRATION_MANAGE_DESC');
        $icon = '<span class="fa fa-users" aria-hidden="true"></span><span class="fa fa-external-link" aria-hidden="true"></span><span class="visually-hidden">' . htmlspecialchars($label, ENT_COMPAT, 'UTF-8') . '</span>';

        $output = HTMLHelper::_(
            'bootstrap.renderModal',
            $modalId,
            array(
                'url' => $url,
                'title' => $label,
                'width' => '100%',
                'height' => '100%',
                'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>',
            )
        );

        $output .= '<button type="button" class="btn btn-sm btn-outline-secondary hasTooltip jem-attendee-registration-icon-button" title="' . htmlspecialchars($tooltip, ENT_COMPAT, 'UTF-8') . '" data-bs-toggle="modal" data-bs-target="#' . htmlspecialchars($modalId, ENT_COMPAT, 'UTF-8') . '">' . $icon . '</button>';

        return $output;
    }
}
?>

<style>
    #jem .jem-attendee-registration-icon-button {
        align-items: center;
        display: inline-flex;
        gap: .35rem;
        justify-content: center;
        min-width: 2.75rem;
    }

    #jem .jem-attendee-registration-icon-button.btn-outline-primary {
        color: var(--bs-primary, #0d6efd) !important;
    }

    #jem .jem-attendee-registration-icon-button.btn-outline-secondary {
        color: var(--bs-secondary, #6c757d) !important;
    }

    #jem .jem-attendee-registration-icon-button.btn-outline-primary:hover,
    #jem .jem-attendee-registration-icon-button.btn-outline-primary:focus,
    #jem .jem-attendee-registration-icon-button.btn-outline-secondary:hover,
    #jem .jem-attendee-registration-icon-button.btn-outline-secondary:focus {
        color: #fff !important;
    }

    #jem .jem-attendee-registration-icon-button .fa {
        color: currentColor !important;
        line-height: 1;
    }

    #jem .jem-attendee-registration-icon-button .fa::before {
        color: currentColor !important;
    }

    div[id^="jem-attendee-registration-manage-"] .modal-dialog {
        height: calc(100vh - 2rem);
        margin: 1rem auto;
        max-width: calc(100vw - 2rem) !important;
        width: calc(100vw - 2rem) !important;
    }

    div[id^="jem-attendee-registration-manage-"] .modal-content {
        height: 100%;
        max-height: calc(100vh - 2rem);
        min-height: 32rem;
        min-width: 48rem;
        overflow: hidden;
        resize: both;
    }

    div[id^="jem-attendee-registration-manage-"] .modal-body {
        flex: 1 1 auto;
        height: auto;
        min-height: 0;
        overflow: hidden;
        padding: 1rem;
    }

    div[id^="jem-attendee-registration-manage-"] iframe {
        border: 0;
        height: 100% !important;
        min-height: 0;
        width: 100% !important;
    }

    @media (max-width: 800px) {
        div[id^="jem-attendee-registration-manage-"] .modal-dialog {
            height: 100vh;
            margin: 0;
            max-width: 100vw !important;
            width: 100vw !important;
        }

        div[id^="jem-attendee-registration-manage-"] .modal-content {
            border-radius: 0;
            min-height: 100vh;
            min-width: 0;
            resize: none;
        }
    }
</style>

<script>
    function jemAttendeeRegistrationSetStatus(id, status)
    {
        var form = document.getElementById('adminForm');

        if (!form) {
            return;
        }

        form.querySelector('[name="task"]').value = 'attendeeregistrations.setstatus';
        form.querySelector('[name="registration_id"]').value = id;
        form.querySelector('[name="registration_status"]').value = status;
        form.submit();
    }

    function jemAttendeeRegistrationRenotify(id)
    {
        var form = document.getElementById('adminForm');

        if (!form) {
            return;
        }

        form.querySelector('[name="task"]').value = 'attendeeregistrations.renotify';
        form.querySelector('[name="registration_id"]').value = id;
        form.submit();
    }

    function jemAttendeeRegistrationCheckAll(source)
    {
        var boxes = document.querySelectorAll('#adminForm input[name="registration_ids[]"]');

        boxes.forEach(function(box) {
            box.checked = source.checked;
        });
    }

    function jemAttendeeRegistrationRenotifySelected()
    {
        jemAttendeeRegistrationApplyBatch('renotify');
    }

    function jemAttendeeRegistrationApplySelected()
    {
        var select = document.getElementById('jem-attendee-registration-batch-action');

        if (!select || !select.value) {
            return;
        }

        jemAttendeeRegistrationApplyBatch(select.value);
    }

    function jemAttendeeRegistrationApplyBatch(action)
    {
        var form = document.getElementById('adminForm');

        if (!form) {
            return;
        }

        form.querySelector('[name="task"]').value = 'attendeeregistrations.batch';
        form.querySelector('[name="batch_action"]').value = action;
        form.submit();
    }
</script>

    <div id="jem" class="jem_attendeeregistrations<?php echo $this->pageclass_sfx; ?>">
    <div class="buttons">
        <?php echo JemOutput::createButtonBar($this->getName(), $this->permissions, array('print_link' => $this->print_link, 'pdf_link' => $this->pdf_link)); ?>
    </div>

    <?php if ($this->params->get('show_page_heading', 1)) : ?>
        <h1 class="componentheading"><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
    <?php endif; ?>

    <?php if ($this->params->get('showintrotext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('introtext'); ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($this->action, ENT_COMPAT, 'UTF-8'); ?>" method="post" id="adminForm" name="adminForm">
        <?php if ($this->settings->get('global_show_filter', 1) || $this->settings->get('global_display', 1)) : ?>
            <div id="jem_filter" class="floattext">
                <?php if ($this->settings->get('global_show_filter', 1)) : ?>
                    <div class="jem_fleft">
                        <label for="filter"><?php echo Text::_('COM_JEM_FILTER'); ?></label>
                        <?php echo $this->lists['filter']; ?>
                        <?php echo $this->lists['status']; ?>
                        <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="inputbox form-control" />
                        <button class="btn btn-primary" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                        <button class="btn btn-secondary" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
                    </div>
                <?php endif; ?>

                <?php if ($this->settings->get('global_display', 1)) : ?>
                    <div class="jem_fright">
                        <label for="limit"><?php echo Text::_('COM_JEM_DISPLAY_NUM'); ?></label>
                        <?php echo $this->pagination->getLimitBox(); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="eventtable table table-striped" style="width:<?php echo $this->jemsettings->tablewidth; ?>;">
                <thead>
                    <tr>
                        <?php if (!empty($this->permissions->canManageAttendees)) : ?>
                            <th class="sectiontableheader center" style="width:1%;">
                                <input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="jemAttendeeRegistrationCheckAll(this);" />
                            </th>
                        <?php endif; ?>
                        <?php foreach ($this->columns as $column) : ?>
                            <?php
                            $sort = array(
                                'registration_id' => 'r.id',
                                'name' => 'u.name',
                                'username' => 'u.username',
                                'user_id' => 'r.uid',
                                'places' => 'r.places',
                                'uregdate' => 'r.uregdate',
                                'event' => 'a.title',
                                'event_date' => 'a.dates',
                                'venue' => 'v.venue',
                                'status' => 'r.status',
                            );
                            ?>
                            <th class="sectiontableheader">
                                <?php echo isset($sort[$column]) ? HTMLHelper::_('grid.sort', $labels[$column], $sort[$column], $this->lists['order_Dir'], $this->lists['order']) : $labels[$column]; ?>
                            </th>
                        <?php endforeach; ?>
                        <?php if (!empty($this->permissions->canManageAttendees)) : ?>
                            <th class="sectiontableheader center"><?php echo Text::_('COM_JEM_ATTENDEE_REGISTRATION_RENOTIFY'); ?></th>
                            <th class="sectiontableheader center"><?php echo Text::_('COM_JEM_ATTENDEE_REGISTRATION_MANAGE'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($this->rows)) : ?>
                        <tr><td colspan="<?php echo count($this->columns) + (!empty($this->permissions->canManageAttendees) ? 3 : 0); ?>"><?php echo Text::_('COM_JEM_ATTENDEE_REGISTRATIONS_EMPTY'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($this->rows as $row) : ?>
                            <tr>
                                <?php if (!empty($this->permissions->canManageAttendees)) : ?>
                                    <td class="center"><input type="checkbox" name="registration_ids[]" value="<?php echo (int) ($row->registration_id ?? 0); ?>" /></td>
                                <?php endif; ?>
                                <?php foreach ($this->columns as $column) : ?>
                                    <td><?php echo jem_attendeeregistrations_cell($this, $row, $column); ?></td>
                                <?php endforeach; ?>
                                <?php if (!empty($this->permissions->canManageAttendees)) : ?>
                                    <td class="center"><?php echo jem_attendeeregistrations_renotify_button($row); ?></td>
                                    <td class="center"><?php echo jem_attendeeregistrations_manage_button($this, $row); ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
        <input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
        <input type="hidden" name="option" value="com_jem" />
        <input type="hidden" name="task" value="" />
        <input type="hidden" name="registration_id" value="0" />
        <input type="hidden" name="registration_status" value="0" />
        <?php if (!empty($this->permissions->canManageAttendees) && !empty($this->rows)) : ?>
        <div class="jem-attendee-registration-actions mt-3 d-flex gap-2 align-items-center flex-wrap">
            <select id="jem-attendee-registration-batch-action" class="form-select w-auto">
                <option value=""><?php echo Text::_('COM_JEM_ATTENDEE_REGISTRATION_BATCH_SELECT'); ?></option>
                <option value="renotify"><?php echo Text::_('COM_JEM_ATTENDEE_REGISTRATION_RENOTIFY_SELECTED'); ?></option>
                <option value="status:-1"><?php echo Text::_('COM_JEM_ATTENDEES_NOT_ATTENDING'); ?></option>
                <option value="status:0"><?php echo Text::_('COM_JEM_ATTENDEES_INVITED'); ?></option>
                <option value="status:1"><?php echo Text::_('COM_JEM_ATTENDEES_ATTENDING'); ?></option>
                <option value="status:2"><?php echo Text::_('COM_JEM_ATTENDEES_ON_WAITINGLIST'); ?></option>
            </select>
            <button type="button" class="btn btn-primary" onclick="jemAttendeeRegistrationApplySelected();">
                <?php echo Text::_('COM_JEM_ATTENDEE_REGISTRATION_BATCH_APPLY'); ?>
            </button>
        </div>
        <?php endif; ?>

        <input type="hidden" name="batch_action" value="" />
        <input type="hidden" name="Itemid" value="<?php echo (int) $this->itemid; ?>" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

    <div class="pagination">
        <?php echo $this->pagination->getPagesLinks(); ?>
    </div>

    <?php if ($this->params->get('showfootertext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('footertext'); ?>
        </div>
    <?php endif; ?>

    <div class="copyright">
        <?php JemOutput::footer(); ?>
    </div>
</div>
