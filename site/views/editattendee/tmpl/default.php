<?php
/** @package JEM @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL */
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
$priced = !empty($this->pricing->is_priced);
$areas = !$priced && !empty($this->capacity->enabled);
?>
<div id="jem" class="jem-edit-attendee">
    <h1><?php echo Text::_('COM_JEM_EDIT_ATTENDEE'); ?></h1>
    <h2><?php echo $this->escape($this->row->eventtitle); ?></h2>
    <p><?php echo Text::_('COM_JEM_FE_RESERVATION_HELP'); ?></p>
    <dl class="row">
        <dt class="col-sm-3"><?php echo Text::_('COM_JEM_NAME'); ?></dt><dd class="col-sm-9"><?php echo $this->escape($this->row->username ?: $this->row->guest_name); ?></dd>
        <dt class="col-sm-3"><?php echo Text::_('COM_JEM_REGISTRATION_REFERENCE'); ?></dt><dd class="col-sm-9"><?php echo $this->escape($this->row->reference); ?></dd>
    </dl>
    <?php if ($this->commerceReadOnly) : ?><p class="alert alert-warning"><?php echo Text::_('COM_JEM_PRICED_REGISTRATION_COMMERCE_READ_ONLY'); ?></p><?php endif; ?>
    <form action="<?php echo Route::_('index.php?option=com_jem&task=editattendee.save'); ?>" method="post" id="adminForm" name="adminForm"
        data-relocation-controller="editattendee" data-relocation-endpoint="<?php echo $this->escape(Uri::root(true) . '/index.php?option=com_jem&format=json'); ?>">
        <fieldset <?php echo $this->commerceReadOnly ? 'disabled' : ''; ?>>
            <div class="mb-3"><label for="reg_status" class="form-label"><?php echo Text::_('COM_JEM_STATUS'); ?></label>
                <select class="form-select" name="status" id="reg_status">
                <?php foreach ([-1=>'COM_JEM_ATTENDEES_NOT_ATTENDING',0=>'COM_JEM_ATTENDEES_INVITED',1=>'COM_JEM_ATTENDEES_ATTENDING',2=>'COM_JEM_ATTENDEES_ON_WAITINGLIST'] as $value=>$label) : ?>
                    <option value="<?php echo $value; ?>" <?php echo (int) $this->row->status === $value ? 'selected' : ''; ?>><?php echo Text::_($label); ?></option>
                <?php endforeach; ?>
                </select>
            </div>
            <?php if ($priced || $areas) : ?>
            <div class="table-responsive mb-3">
                <table class="table table-striped align-middle"><thead><tr>
                    <th scope="col"><?php echo Text::_($priced ? 'COM_JEM_BOOKING_TICKET_TYPE' : 'COM_JEM_BOOKING_SPACE'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_JEM_RELOCATION_AREA'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_JEM_AVAILABLE'); ?></th>
                    <?php if ($priced) : ?><th scope="col"><?php echo Text::_('COM_JEM_PRICE'); ?></th><?php endif; ?>
                    <th scope="col"><?php echo Text::_('COM_JEM_PLACES'); ?></th>
                </tr></thead><tbody>
                <?php foreach ($priced ? $this->pricing->options : $this->capacity->options as $option) : ?>
                    <?php $key = $priced ? (string) $option->id : $option['key']; $qty = $priced ? $option->quantity : $option['current_quantity']; ?>
                    <tr>
                        <td><label for="quantity-<?php echo $this->escape(str_replace(':','-', $key)); ?>"><?php echo $this->escape($priced ? $option->name : $option['space_name']); ?></label></td>
                        <td><?php echo $this->escape($priced ? $option->pool_name : $option['area_name']); ?></td>
                        <td><?php echo $priced ? ($option->available === null ? '∞' : (int) $option->available) : (int) ($option['free_remaining'] ?? $option['remaining']); ?></td>
                        <?php if ($priced) : ?><td><?php echo $this->escape($option->unit_gross . ' ' . $this->pricing->currency); ?></td><?php endif; ?>
                        <td><input class="form-control" style="width:6rem" type="number" min="0" step="1" data-reservation-quantity
                            id="quantity-<?php echo $this->escape(str_replace(':','-', $key)); ?>"
                            name="<?php echo $priced ? 'admissions' : 'capacity_areas'; ?>[<?php echo $this->escape($key); ?>]" value="<?php echo (int) $qty; ?>"
                            <?php echo $priced && !$option->eligible ? 'readonly' : ''; ?>></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table>
                <p class="alert alert-danger" data-reservation-error role="alert" tabindex="-1" hidden></p>
            </div>
            <?php else : ?>
            <div class="mb-3"><label for="places" class="form-label"><?php echo Text::_('COM_JEM_PLACES'); ?></label>
                <input class="form-control" type="number" name="places" id="places" min="0" step="1" value="<?php echo (int) $this->row->places; ?>">
            </div>
            <?php endif; ?>
            <div class="mb-3"><label for="reg_comment" class="form-label"><?php echo Text::_('COM_JEM_COMMENT'); ?></label>
                <textarea class="form-control" name="comment" id="reg_comment" rows="3" maxlength="255"><?php echo $this->escape((string) $this->row->comment); ?></textarea>
            </div>
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="sendemail" name="sendemail" value="1" <?php echo !isset($this->formDraft['sendemail']) || $this->formDraft['sendemail'] ? 'checked' : ''; ?>>
                <label class="form-check-label" for="sendemail"><?php echo Text::_('COM_JEM_SEND_REGISTRATION_NOTIFICATION_EMAIL'); ?></label>
            </div>
            <?php require JPATH_ADMINISTRATOR . '/components/com_jem/views/attendee/tmpl/relocation.php'; ?>
            <button class="btn btn-primary" type="submit"><?php echo Text::_('JSAVE'); ?></button>
        </fieldset>
        <a class="btn btn-secondary mt-2" href="<?php echo $this->backlink; ?>"><?php echo Text::_('JCLOSE'); ?></a>
        <input type="hidden" name="id" value="<?php echo (int) $this->row->id; ?>">
        <input type="hidden" name="revision" value="<?php echo (int) $this->row->revision; ?>">
        <input type="hidden" name="task" value="editattendee.save">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
