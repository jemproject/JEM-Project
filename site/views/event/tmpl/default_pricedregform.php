<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$registration = is_object($this->registration) ? $this->registration : null;
$status = $registration ? (int) $registration->status : null;
$places = $registration ? max(0, (int) $registration->places) : 0;
$pricedReservation = $registration && in_array(
    (string) ($registration->pricing_mode ?? 'classic'),
    array('single', 'multiple', 'priced'),
    true
);
$hasReservation = $registration && in_array($status, array(0, 1, 2), true) && $places > 0;
$canCancel = $hasReservation && ($status === 0 || $this->allowAnnulation);
$canDecline = !$registration && !empty($this->item->requestanswer) && $this->allowRegistration;
?>
<p class="alert alert-info"><?php echo Text::_('COM_JEM_PRICED_REGISTRATION_REQUIRES_ORDER'); ?></p>
<?php if ($status === -1) : ?>
    <p><?php echo Text::_('COM_JEM_YOU_ARE_NOT_ATTENDING'); ?></p>
<?php elseif ($hasReservation) : ?>
    <p><?php echo Text::sprintf('COM_JEM_PRICED_RESERVATION_PLACES', $places); ?></p>
<?php endif; ?>
<?php if ($hasReservation && !$canCancel) : ?>
    <p><?php echo Text::_('COM_JEM_NOT_ALLOWED_TO_ANNULATE'); ?></p>
<?php endif; ?>
<?php if (!$canCancel && !$canDecline) {
    return;
} ?>
<form id="JEM" name="adminForm" method="post"
    action="<?php echo Route::_('index.php?option=com_jem&view=event&id=' . (int) $this->item->id); ?>">
    <p>
        <label for="jem_unregister_event">
            <input id="jem_unregister_event" type="radio" name="reg_check" value="-1" checked>
            <?php echo Text::_($canCancel ? ($pricedReservation ? 'COM_JEM_PRICED_CANCELLATION_ACTION' : 'COM_JEM_CANCELLATION_ACTION') : 'COM_JEM_I_WILL_NOT_GO'); ?>
        </label>
        <?php if ($canCancel && !$pricedReservation) : ?>
            <input id="cancelplaces" type="number" name="cancelplaces" value="<?php echo $places; ?>"
                min="1" max="<?php echo $places; ?>" aria-label="<?php echo $this->escape(Text::_('COM_JEM_PLACES')); ?>">
        <?php else : ?>
            <input id="cancelplaces" type="hidden" name="cancelplaces" value="<?php echo $canCancel ? $places : 0; ?>">
        <?php endif; ?>
    </p>
    <?php if ($canCancel && $pricedReservation) : ?>
        <p><?php echo Text::sprintf('COM_JEM_PRICED_CANCELLATION_HELP', $places); ?></p>
    <?php endif; ?>
    <?php if (!empty($this->item->seriesbooking)) : ?>
        <?php if (!empty($this->item->singlebooking)) : ?>
            <p><label>
                <input type="checkbox" name="reg_check_series" value="1" data-registration-action="-1">
                <?php echo Text::_('COM_JEM_I_WILL_NOT_GO_SERIES_2'); ?>
            </label></p>
        <?php else : ?>
            <p><?php echo Text::_('COM_JEM_I_WILL_NOT_GO_SERIES_3'); ?></p>
        <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($this->jemsettings->regallowcomments)) : ?>
        <p><label for="reg_comment"><?php echo Text::_('COM_JEM_OPTIONAL_COMMENT'); ?></label></p>
        <textarea class="inputbox" name="reg_comment" id="reg_comment" rows="3" cols="30" maxlength="255"><?php
            echo $this->escape((string) ($registration->comment ?? ''));
        ?></textarea>
    <?php endif; ?>
    <p><button class="btn btn-sm btn-primary" type="submit" id="jem_send_attend" name="jem_send_attend">
        <?php echo Text::_($canCancel ? ($pricedReservation ? 'COM_JEM_PRICED_CANCELLATION_ACTION' : 'COM_JEM_CANCELLATION_ACTION') : 'COM_JEM_SEND_REGISTER'); ?>
    </button></p>
    <?php echo $this->loadTemplate('cancellation'); ?>
    <input type="hidden" name="rdid" value="<?php echo (int) $this->item->did; ?>">
    <input type="hidden" name="regid" value="<?php echo (int) ($registration->id ?? 0); ?>">
    <input type="hidden" name="task" value="event.userregister">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
