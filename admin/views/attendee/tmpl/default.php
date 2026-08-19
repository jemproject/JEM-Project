<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;

$app = Factory::getApplication();
$document = $app->getDocument();
$wa = $document->getWebAssetManager();
        $wa->useScript('keepalive')
           ->useScript('form.validate');

$userModalId = 'jem-attendee-user-modal';
$isPriced = !empty($this->pricing->is_priced);
$isCommerceReadOnly = !empty($this->commerceReadOnly);
$isPricedEditable = $isPriced && !$isCommerceReadOnly;
$isAreaCapacity = !$isPriced && !empty($this->capacity->enabled);
$pricedUserLocked = $isPriced && !empty($this->row->id);
$pricingEndpoint = Route::_(
    'index.php?option=com_jem&task=attendee.pricingOptions&format=json&event=' . (int) ($this->row->event ?: $this->event)
    . '&id=' . (int) $this->row->id . '&' . Session::getFormToken() . '=1',
    false
);

$selectuser_link = Route::_('index.php?option=com_jem&task=attendee.selectuser&tmpl=component');
echo HTMLHelper::_(
    'bootstrap.renderModal',
    $userModalId,
    array(
        'url'    => $selectuser_link.'&amp;'.Session::getFormToken().'=1',
        'title'  => Text::_('COM_JEM_SELECT'),
        'width'  => '800px',
        'height' => '450px',
        'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
    )
);
?>

<script>
function modalSelectUser(id, username)
{
        document.getElementById('uid').value = id;
        document.getElementById('username').value = username;
        if (window.jemLoadAdmissionOptions) {
            window.jemLoadAdmissionOptions(id);
        }

        const modal = document.getElementById('<?php echo $userModalId; ?>');
        if (modal && window.bootstrap && bootstrap.Modal) {
            const instance = bootstrap.Modal.getInstance(modal);
            if (instance) {
                instance.hide();
            }
        }
}
Joomla.submitbutton = function(task)
    {
        const status = parseInt(document.getElementById('reg_status').value, 10);
        if (task !== 'attendee.cancel' && <?php echo $isPricedEditable ? 'true' : 'false'; ?> && (status === 1 || status === 2)) {
            const quantities = Array.from(document.querySelectorAll('#jem-admission-options input[type="number"]'));
            const total = quantities.reduce((sum, input) => sum + Math.max(0, parseInt(input.value || '0', 10)), 0);
            if (!window.jemPricingLoaded || total < 1) {
                alert(<?php echo json_encode(Text::_('COM_JEM_PRICED_REGISTRATION_SELECTION_REQUIRED')); ?>);
                return false;
            }
        }
        if (task !== 'attendee.cancel' && <?php echo $isAreaCapacity ? 'true' : 'false'; ?> && (status === 1 || status === 2)) {
            const total = Array.from(document.querySelectorAll('.jem-capacity-area-quantity'))
                .reduce((sum, input) => sum + Math.max(0, parseInt(input.value || '0', 10)), 0);
            if (total < 1) {
                alert(<?php echo json_encode(Text::_('COM_JEM_CAPACITY_REGISTRATION_SELECTION_REQUIRED')); ?>);
                return false;
            }
        }
        if (task == 'attendee.cancel' || document.formvalidator.isValid(document.getElementById('adminForm'))) {
            if (task == 'attendee.cancel' || document.getElementById('adminForm').uid.value != 0) {
                Joomla.submitform(task, document.getElementById('adminForm'));
            } else {
                alert("<?php echo Text::_('COM_JEM_SELECT_AN_USER', true); ?>");
                return false;
            }
        } else {
            alert('<?php echo $this->escape(Text::_('JGLOBAL_VALIDATION_FORM_FAILED'));?>');
        }
    }

const jemInitialPricing = <?php echo json_encode($this->pricing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
window.jemPricingLoaded = <?php echo $isPriced && !empty($this->row->uid) ? 'true' : 'false'; ?>;

function jemFormatMoney(amount, currency) {
    return currency + ' ' + Number(amount || 0).toFixed(2);
}

function jemRenderAdmissionOptions(pricing) {
    const body = document.getElementById('jem-admission-options');
    if (!body) return;
    body.replaceChildren();
    const currency = pricing.currency || '';
    (pricing.options || []).forEach(function(option) {
        const row = document.createElement('tr');
        const admission = document.createElement('td');
        const name = document.createElement('strong');
        name.textContent = option.name;
        admission.appendChild(name);
        if (option.code) {
            const code = document.createElement('small');
            code.className = 'd-block text-muted';
            code.textContent = option.code;
            admission.appendChild(code);
        }
        if (option.locked) {
            const locked = document.createElement('span');
            locked.className = 'badge bg-info text-dark ms-2';
            locked.textContent = <?php echo json_encode(Text::_('COM_JEM_PRICED_REGISTRATION_LOCKED')); ?>;
            name.appendChild(locked);
        }
        const pool = document.createElement('td');
        pool.textContent = option.pool_name || <?php echo json_encode(Text::_('COM_JEM_PRICED_REGISTRATION_EVENT_CAPACITY')); ?>;
        const available = document.createElement('td');
        available.className = 'text-center';
        available.textContent = option.available === null ? '\u221e' : option.available;
        const price = document.createElement('td');
        price.className = 'text-end';
        price.textContent = jemFormatMoney(option.unit_gross, currency);
        const quantity = document.createElement('td');
        const input = document.createElement('input');
        input.type = 'number';
        input.name = 'admissions[' + option.id + ']';
        input.value = option.quantity || 0;
        input.min = '0';
        const availableMaximum = option.available === null ? null : parseInt(option.available, 10);
        const priceMaximum = option.max_quantity === null ? null : parseInt(option.max_quantity, 10);
        const maximum = availableMaximum === null ? priceMaximum
            : (priceMaximum === null ? availableMaximum : Math.min(availableMaximum, priceMaximum));
        if (maximum !== null) input.max = String(maximum);
        input.className = 'form-control form-control-sm jem-admission-quantity';
        input.style.maxWidth = '7rem';
        input.disabled = <?php echo $isCommerceReadOnly ? 'true' : 'false'; ?> || !option.eligible;
        input.dataset.unitGross = option.unit_gross;
        input.addEventListener('input', jemUpdateAdmissionTotal);
        quantity.appendChild(input);
        if (!option.eligible) {
            const unavailable = document.createElement('small');
            unavailable.className = 'd-block text-danger';
            unavailable.textContent = <?php echo json_encode(Text::_('COM_JEM_PRICED_REGISTRATION_NOT_ELIGIBLE')); ?>;
            quantity.appendChild(unavailable);
        }
        row.append(admission, pool, available, price, quantity);
        body.appendChild(row);
    });
    document.getElementById('jem-event-remaining').textContent = pricing.event_remaining === null
        ? '\u221e'
        : pricing.event_remaining;
    const userHelp = document.getElementById('jem-admission-user-help');
    if (userHelp) userHelp.hidden = true;
    const pools = document.getElementById('jem-pool-summary');
    pools.replaceChildren();
    (pricing.pools || []).forEach(function(pool) {
        const badge = document.createElement('span');
        badge.className = 'badge bg-light text-dark border me-2 mb-1';
        badge.textContent = pool.name + ': ' + pool.remaining + ' / ' + pool.capacity;
        pools.appendChild(badge);
    });
    window.jemPricingLoaded = true;
    jemUpdateAdmissionTotal();
}

function jemUpdateAdmissionTotal() {
    let quantity = 0;
    let total = 0;
    document.querySelectorAll('.jem-admission-quantity').forEach(function(input) {
        const value = Math.max(0, parseInt(input.value || '0', 10));
        quantity += value;
        total += value * Number(input.dataset.unitGross || 0);
    });
    document.getElementById('jem-admission-total-places').textContent = quantity;
    document.getElementById('jem-admission-total-price').textContent = jemFormatMoney(total, jemInitialPricing.currency || '');
}

window.jemLoadAdmissionOptions = function(userId) {
    if (!<?php echo $isPricedEditable ? 'true' : 'false'; ?> || !userId) return;
    window.jemPricingLoaded = false;
    fetch(<?php echo json_encode($pricingEndpoint); ?> + '&uid=' + encodeURIComponent(userId), {
        credentials: 'same-origin',
        headers: {'Accept': 'application/json'}
    }).then(response => response.json()).then(function(response) {
        if (!response.success) throw new Error(response.message || 'Pricing request failed');
        jemRenderAdmissionOptions(response.data);
    }).catch(function(error) {
        const body = document.getElementById('jem-admission-options');
        body.innerHTML = '<tr><td colspan="5" class="text-danger"></td></tr>';
        body.querySelector('td').textContent = error.message;
    });
};

function jemToggleRegistrationMode() {
    const block = document.getElementById('jem-priced-admissions-row');
    if (!block) return;
    const status = parseInt(document.getElementById('reg_status').value, 10);
    block.hidden = status !== 1 && status !== 2;
}

document.addEventListener('DOMContentLoaded', function() {
    const status = document.getElementById('reg_status');
    status.addEventListener('change', jemToggleRegistrationMode);
    jemToggleRegistrationMode();
    if (<?php echo $isPriced && !empty($this->row->uid) ? 'true' : 'false'; ?>) {
        jemRenderAdmissionOptions(jemInitialPricing);
    }
});
</script>


<form action="<?php echo Route::_('index.php?option=com_jem&view=attendee'); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">
    <?php if ($isCommerceReadOnly) : ?>
        <div class="alert alert-info" role="status">
            <?php echo Text::_('COM_JEM_PRICED_REGISTRATION_COMMERCE_READ_ONLY'); ?>
        </div>
    <?php endif; ?>
    <fieldset<?php echo $isCommerceReadOnly ? ' disabled="disabled"' : ''; ?>>
        <h3><?php echo Text::_('COM_JEM_DETAILS'); ?></h3>
        <?php if (!empty($this->row->id)) : ?>
        <p>
            <?php echo Text::_('COM_JEM_EDITATTENDEE_NOTICE'); ?>
        </p>
        <?php endif; ?>

        <table  class="admintable">
            <tr>
                <td class="key">
                    <label for="eventtitle" <?php echo JemOutput::tooltip(Text::_('COM_JEM_EVENT'), Text::_('COM_JEM_EVENT_DESC')); ?>>
                        <?php echo Text::_('COM_JEM_EVENT').':'; ?>
                    </label>
                </td>
                <td>
                    <input type="text" name="eventtitle" id="eventtitle" class="form-control inputbox required valid form-control-success" readonly="readonly"
                           value="<?php echo !empty($this->row->eventtitle) ? $this->row->eventtitle : '?'; ?>"
                    />
                </td>
            </tr>
            <tr>
                <td class="key">
                    <label for="username" <?php echo JemOutput::tooltip(Text::_('COM_JEM_USER'), Text::_('COM_JEM_USER_DESC')); ?>>
                        <?php echo Text::_('COM_JEM_USER').':'; ?>
                    </label>
                </td>
                <td>
                    <div class="input-group">
                        <input type="text" name="username" id="username" class="form-control inputbox required valid form-control-success" readonly="readonly" value="<?php echo $this->escape($this->row->username); ?>" />
                        <?php if (!$pricedUserLocked) : ?>
                        <button type="button" class="btn btn-primary usermodal" data-bs-toggle="modal" data-bs-target="#<?php echo $userModalId; ?>">
                            <span class="icon-user" aria-hidden="true"></span> <?php echo Text::_('COM_JEM_SELECT_USER')?>
                        </button>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="uid" id="uid" value="<?php echo (int) $this->row->uid; ?>" />
                </td>
            </tr>
            <tr>
                <td class="key">
                    <label for="reg_status" <?php echo JemOutput::tooltip(Text::_('COM_JEM_STATUS'), Text::_('COM_JEM_STATUS_DESC')); ?>>
                        <?php echo Text::_('COM_JEM_STATUS').':'; ?>
                    </label>
                </td>
                <td>
                    <?php
                    $options = array(HTMLHelper::_('select.option',  0, Text::_('COM_JEM_ATTENDEES_INVITED')),
                                     HTMLHelper::_('select.option', -1, Text::_('COM_JEM_ATTENDEES_NOT_ATTENDING')),
                                     HTMLHelper::_('select.option',  1, Text::_('COM_JEM_ATTENDEES_ATTENDING')),
                                     HTMLHelper::_('select.option',  2, Text::_('COM_JEM_ATTENDEES_ON_WAITINGLIST'), array('disable' => empty($this->row->waitinglist))));

                    $selectOptions = array('class' => 'form-select');
                    $selectedStatus = JemRegistrationTransition::logicalStatus($this->row);
                    echo HTMLHelper::_('select.genericlist', $options, 'status', $selectOptions, 'value', 'text', $selectedStatus, 'reg_status');
                    ?>
                </td>
            </tr>
            <?php if ($isPriced) : ?>
            <tr id="jem-priced-admissions-row">
                <td class="key align-top">
                    <label><?php echo Text::_('COM_JEM_PRICED_REGISTRATION_ADMISSIONS'); ?>:</label>
                </td>
                <td>
                    <div class="card border-0 bg-light">
                        <div class="card-body p-3">
                            <div class="d-flex flex-wrap gap-3 mb-3">
                                <span><strong><?php echo Text::_('COM_JEM_PRICED_REGISTRATION_EVENT_AVAILABLE'); ?>:</strong> <span id="jem-event-remaining"></span></span>
                                <span id="jem-pool-summary"></span>
                            </div>
                            <?php if (empty($this->row->uid)) : ?>
                                <p class="text-muted" id="jem-admission-user-help"><?php echo Text::_('COM_JEM_PRICED_REGISTRATION_SELECT_USER_FIRST'); ?></p>
                            <?php endif; ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-2">
                                    <thead><tr>
                                        <th><?php echo Text::_('COM_JEM_PRICED_REGISTRATION_ADMISSION'); ?></th>
                                        <th><?php echo Text::_('COM_JEM_PRICED_REGISTRATION_AREA_POOL'); ?></th>
                                        <th class="text-center"><?php echo Text::_('COM_JEM_AVAILABLE'); ?></th>
                                        <th class="text-end"><?php echo Text::_('COM_JEM_PRICE'); ?></th>
                                        <th><?php echo Text::_('COM_JEM_PRICED_REGISTRATION_QUANTITY'); ?></th>
                                    </tr></thead>
                                    <tbody id="jem-admission-options"></tbody>
                                    <tfoot><tr class="fw-bold">
                                        <td colspan="2"><?php echo Text::_('COM_JEM_PRICED_REGISTRATION_TOTAL'); ?></td>
                                        <td class="text-center" id="jem-admission-total-places">0</td>
                                        <td class="text-end" id="jem-admission-total-price"></td>
                                        <td></td>
                                    </tr></tfoot>
                                </table>
                            </div>
                            <small class="text-muted"><?php echo Text::_('COM_JEM_PRICED_REGISTRATION_ADMIN_HELP'); ?></small>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ($isAreaCapacity) : ?>
            <tr id="jem-capacity-areas-row">
                <td class="key align-top">
                    <label><?php echo Text::_('COM_JEM_CAPACITY_REGISTRATION_AREAS'); ?>:</label>
                </td>
                <td>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-1">
                            <thead><tr>
                                <th><?php echo Text::_('COM_JEM_VENUE_CAPACITY_SPACE'); ?></th>
                                <th><?php echo Text::_('COM_JEM_VENUE_CAPACITY_AREA_NAME'); ?></th>
                                <th class="text-center"><?php echo Text::_('COM_JEM_AVAILABLE'); ?></th>
                                <th style="width:8rem"><?php echo Text::_('COM_JEM_ATTENDEES_PLACES'); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ((array) $this->capacity->options as $option) : ?>
                                <tr>
                                    <td><?php echo $this->escape((string) $option['space_name']); ?></td>
                                    <td><?php echo $this->escape((string) $option['area_name']); ?></td>
                                    <td class="text-center"><?php echo (int) $option['remaining']; ?></td>
                                    <td><input class="form-control form-control-sm jem-capacity-area-quantity" type="number"
                                        name="capacity_areas[<?php echo $this->escape((string) $option['key']); ?>]"
                                        min="0" max="<?php echo (int) $option['remaining']; ?>"
                                        value="<?php echo (int) $option['current_quantity']; ?>"></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted"><?php echo Text::_('COM_JEM_CAPACITY_REGISTRATION_HELP'); ?></small>
                </td>
            </tr>
            <?php elseif (!$isPriced) : ?>
            <tr>
                <td class="key">
                    <label for="eventtitle" <?php echo JemOutput::tooltip(Text::_('COM_JEM_ATTENDEES_PLACES'), Text::_('COM_JEM_ATTENDEES_PLACES_DESC')); ?>>
                        <?php echo Text::_('COM_JEM_ATTENDEES_PLACES').':'; ?>
                    </label>
                </td>
                <td>
                    <input type="number" name="places" id="places" class="form-control inputbox" min="<?php echo $this->row->minbookeduser; ?>" max="<?php echo $this->row->maxbookeduser; ?>"
                           value="<?php echo !empty($this->row->places) ? $this->row->places : $this->row->minbookeduser; ?>"
                    />
                </td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($this->jemsettings->regallowcomments)): ?>
            <tr>
                <td class="key" style="vertical-align: baseline;">
                    <label for="comment" <?php echo JemOutput::tooltip(Text::_('COM_JEM_COMMENT'), Text::_('COM_JEM_COMMENT_DESC')); ?>>
                        <?php echo Text::_('COM_JEM_COMMENT').':'; ?>
                    </label>
                </td>
                <td>
                    <textarea name="comment" id="reg_comment" rows="3" cols="30" maxlength="255"
                        ><?php if (!empty($this->row->comment)) { echo $this->row->comment; }
                        /* looks crazy, but required to prevent unwanted white spaces within textarea content! */
                    ?></textarea>
                </td>
            </tr>
            <?php endif; ?>
            <?php if (!$isPriced && ($this->row->recurrence_type || !empty($this->row->series_id)) && $this->row->seriesbooking): ?>
            <tr>
                <td class="key">
                    <label for="seriesbooking" <?php echo JemOutput::tooltip(Text::_('COM_JEM_EDITEVENT_FIELD_BOOKED_SERIES'), Text::_('COM_JEM_EDITEVENT_FIELD_BOOKED_SERIES')); ?>>
                        <?php echo Text::_('COM_JEM_EDITEVENT_FIELD_BOOKED_SERIES').':'; ?>
                    </label>
                </td>
                <td>
                    <input type="checkbox" id="seriesbooking" name="seriesbooking" value="1"/>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <td class="key">
                    <label for="sendemail" <?php echo JemOutput::tooltip(Text::_('COM_JEM_SEND_REGISTRATION_NOTIFICATION_EMAIL'), Text::_('COM_JEM_SEND_REGISTRATION_NOTIFICATION_EMAIL_DESC')); ?>>
                        <?php echo Text::_('COM_JEM_SEND_REGISTRATION_NOTIFICATION_EMAIL').':'; ?>
                    </label>
                </td>
                <td>
                    <input type="checkbox" id="sendemail" name="sendemail" value="1" checked="checked"/>
                </td>
            </tr>

        </table>
    </fieldset>

    <?php echo HTMLHelper::_('form.token'); ?>
    <input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
    <input type="hidden" name="event" value="<?php echo ($this->row->event ? $this->row->event : $this->event); ?>" />
    <input type="hidden" name="task" value="" />
</form>

<?php if (!empty($this->row->id) && ($this->registrationChanges || $this->notifications)) : ?>
<div class="card mt-4">
    <div class="card-header"><strong><?php echo Text::_('COM_JEM_REGISTRATION_AUDIT'); ?></strong></div>
    <div class="card-body">
        <h4><?php echo Text::_('COM_JEM_REGISTRATION_CHANGES'); ?></h4>
        <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_OCCURRED'); ?></th><th><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_ACTION'); ?></th><th><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_CHANGE'); ?></th><th><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_ACTOR'); ?></th></tr></thead><tbody>
        <?php if (!$this->registrationChanges) : ?><tr><td colspan="4"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td></tr><?php endif; ?>
        <?php foreach ($this->registrationChanges as $change) : ?><tr><td><?php echo HTMLHelper::_('date', $change->occurred, Text::_('DATE_FORMAT_LC5')); ?></td><td><?php echo $this->escape($change->action); ?></td><td><?php echo $this->escape((string) $change->old_status . ' → ' . (string) $change->new_status . ' / ' . (string) $change->old_places . ' → ' . (string) $change->new_places); ?></td><td><?php echo $this->escape($change->actor_name ?: Text::_('COM_JEM_REGISTRATION_HISTORY_SYSTEM')); ?></td></tr><?php endforeach; ?>
        </tbody></table></div>

        <h4 class="mt-4"><?php echo Text::_('COM_JEM_NOTIFICATION_HISTORY'); ?></h4>
        <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th><?php echo Text::_('COM_JEM_NOTIFICATION_CREATED'); ?></th><th><?php echo Text::_('COM_JEM_NOTIFICATION_STATE'); ?></th><th><?php echo Text::_('COM_JEM_NOTIFICATION_RECIPIENT'); ?></th><th><?php echo Text::_('COM_JEM_NOTIFICATION_MESSAGE'); ?></th><th><?php echo Text::_('COM_JEM_NOTIFICATION_ACTIONS'); ?></th></tr></thead><tbody>
        <?php if (!$this->notifications) : ?><tr><td colspan="5"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td></tr><?php endif; ?>
        <?php foreach ($this->notifications as $notification) : ?><tr><td><?php echo HTMLHelper::_('date', $notification->created, Text::_('DATE_FORMAT_LC5')); ?></td><td><?php echo $this->escape($notification->state); ?> (<?php echo (int) $notification->attempts_total; ?>)<br><small><?php echo Text::_('COM_JEM_REGISTRATION_HISTORY_REVISION'); ?> <?php echo (int) $notification->registration_revision; ?></small><?php if (!empty($notification->last_error)) : ?><br><small class="text-danger"><?php echo $this->escape($notification->last_error); ?></small><?php endif; ?></td><td><?php echo $this->escape($notification->recipient_email); ?><br><small><?php echo $this->escape($notification->resolved_language); ?></small></td><td><details><summary><?php echo $this->escape($notification->subject); ?></summary><pre class="text-wrap"><?php echo $this->escape($notification->body); ?></pre></details></td><td><?php if ($this->canNotificationResend && in_array($notification->state, array('queued', 'failed'), true) && $notification->attempt_count < $notification->max_attempts) : ?><button class="btn btn-sm btn-warning" type="submit" form="attendee-notification-<?php echo (int) $notification->id; ?>" name="task" value="notification.retry"><?php echo Text::_('COM_JEM_NOTIFICATION_RETRY'); ?></button><?php elseif ($this->canNotificationResend && $notification->state === 'sent') : ?><button class="btn btn-sm btn-primary" type="submit" form="attendee-notification-<?php echo (int) $notification->id; ?>" name="task" value="notification.resend"><?php echo Text::_('COM_JEM_NOTIFICATION_RESEND'); ?></button><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div>
</div>
<?php foreach ($this->notifications as $notification) : ?><form id="attendee-notification-<?php echo (int) $notification->id; ?>" method="post" action="<?php echo Route::_('index.php?option=com_jem'); ?>"><input type="hidden" name="notification_id" value="<?php echo (int) $notification->id; ?>"><input type="hidden" name="return_view" value="attendee"><input type="hidden" name="registration_id" value="<?php echo (int) $this->row->id; ?>"><input type="hidden" name="event_id" value="<?php echo (int) $this->row->event; ?>"><?php echo HTMLHelper::_('form.token'); ?></form><?php endforeach; ?>
<?php endif; ?>
