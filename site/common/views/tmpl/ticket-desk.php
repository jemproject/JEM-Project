<?php
/** Shared private cashier and admission dialog. @package JEM */
defined('_JEXEC') or die;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
$ticketEndpoint = Uri::base(true) . '/index.php';
?>
<?php if (!empty($report['ticketEvents'])) : ?>
<div class="jem-ticket-desk" data-ticket-desk>
    <button type="button" class="btn btn-primary" data-ticket-open><span class="icon-qrcode" aria-hidden="true"></span> <?php echo Text::_('COM_JEM_TK_OPEN'); ?></button>
    <dialog class="jem-ticket-dialog" aria-labelledby="jem-ticket-title">
        <header><h2 id="jem-ticket-title"><?php echo Text::_('COM_JEM_TK_OPEN'); ?></h2><button type="button" class="btn btn-secondary" data-ticket-close><?php echo Text::_('JCLOSE'); ?></button></header>
        <p><?php echo Text::_('COM_JEM_TK_LOOKUP_NOTICE'); ?></p>
        <form data-ticket-lookup method="post" action="<?php echo $escape($ticketEndpoint); ?>?option=com_jem&amp;task=tickets.lookup&amp;format=raw">
            <label for="ticket-event"><?php echo Text::_('COM_JEM_RS_EVENT'); ?></label>
            <select id="ticket-event" name="event_id" class="form-select" required>
                <option value=""><?php echo Text::_('COM_JEM_TK_CHOOSE_EVENT'); ?></option>
                <?php foreach ($report['ticketEvents'] as $eventId => $title) : ?><option value="<?php echo (int) $eventId; ?>"><?php echo $escape($title); ?></option><?php endforeach; ?>
            </select>
            <label for="ticket-reference"><?php echo Text::_('COM_JEM_TK_REFERENCE'); ?></label>
            <input id="ticket-reference" name="reference" class="form-control" type="text" maxlength="28" autocomplete="off" spellcheck="false" required>
            <?php echo HTMLHelper::_('form.token'); ?>
            <div class="tk-actions">
                <button type="submit" class="btn btn-primary"><?php echo Text::_('COM_JEM_TK_LOOKUP'); ?></button>
                <button type="button" class="btn btn-secondary" data-ticket-camera><?php echo Text::_('COM_JEM_TK_CAMERA'); ?></button>
            </div>
        </form>
        <div data-ticket-video-box hidden>
            <label for="ticket-camera"><?php echo Text::_('COM_JEM_TK_CAMERA_DEVICE'); ?></label>
            <select id="ticket-camera" class="form-select"></select>
            <video playsinline muted></video>
            <button type="button" class="btn btn-secondary" data-ticket-stop><?php echo Text::_('COM_JEM_TK_STOP_CAMERA'); ?></button>
        </div>
        <p data-ticket-message role="status" aria-live="polite"></p>
        <section data-ticket-result hidden>
            <h3 data-ticket-name></h3>
            <p data-ticket-summary></p><p data-ticket-payment></p><p data-ticket-block></p>
            <form method="post" target="_blank" action="<?php echo $escape($ticketEndpoint); ?>?option=com_jem&amp;task=tickets.download&amp;format=raw" data-ticket-download>
                <?php echo HTMLHelper::_('form.token'); ?>
                <input type="hidden" name="event_id"><input type="hidden" name="reference">
                <button type="submit" class="btn btn-secondary"><?php echo Text::_('COM_JEM_TK_DOWNLOAD'); ?></button>
            </form>
            <fieldset data-ticket-pay hidden>
                <legend><?php echo Text::_('COM_JEM_TK_CASHIER'); ?></legend>
                <p><?php echo Text::_('COM_JEM_TK_PAYMENT_NOTICE'); ?></p>
                <label for="ticket-amount"><?php echo Text::_('COM_JEM_TK_AMOUNT'); ?></label>
                <input id="ticket-amount" class="form-control" inputmode="decimal" type="text" autocomplete="off">
                <label for="ticket-method"><?php echo Text::_('COM_JEM_TK_METHOD'); ?></label>
                <select id="ticket-method" class="form-select"><option value="cash"><?php echo Text::_('COM_JEM_TK_CASH'); ?></option><option value="bank_transfer"><?php echo Text::_('COM_JEM_TK_TRANSFER'); ?></option></select>
                <label for="ticket-receipt"><?php echo Text::_('COM_JEM_TK_RECEIPT'); ?></label><input id="ticket-receipt" class="form-control" type="text" maxlength="255">
                <button type="button" class="btn btn-primary" data-ticket-payment-save><?php echo Text::_('COM_JEM_TK_CONFIRM_PAYMENT'); ?></button>
            </fieldset>
            <fieldset data-ticket-admit hidden>
                <legend><?php echo Text::_('COM_JEM_TK_GATE'); ?></legend>
                <label for="ticket-pool"><?php echo Text::_('COM_JEM_TK_AREA'); ?></label><select id="ticket-pool" class="form-select"></select>
                <label for="ticket-quantity"><?php echo Text::_('COM_JEM_TK_QUANTITY'); ?></label><input id="ticket-quantity" type="number" class="form-control" min="1" step="1" value="1">
                <button type="button" class="btn btn-primary" data-ticket-admission-save><?php echo Text::_('COM_JEM_TK_CONFIRM_ADMISSION'); ?></button>
            </fieldset>
            <details><summary><?php echo Text::_('COM_JEM_TK_AUDIT'); ?></summary><ul data-ticket-audit></ul></details>
        </section>
    </dialog>
</div>
<?php endif; ?>
