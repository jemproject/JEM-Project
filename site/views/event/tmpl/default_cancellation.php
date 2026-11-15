<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;

?>
<input type="hidden" name="jem_cancel_confirm" value="0">
<input type="hidden" name="jem_cancel_fingerprint" value="">
<dialog class="jem-cancellation-dialog" aria-labelledby="jem-cancellation-heading">
    <h3 id="jem-cancellation-heading"><?php echo Text::_('COM_JEM_CANCELLATION_CONFIRM_TITLE'); ?></h3>
    <p data-cancel-message></p>
    <ul data-cancel-events></ul>
    <div class="d-flex gap-2 justify-content-end">
        <button type="button" class="btn btn-secondary" data-cancel-back autofocus><?php echo Text::_('COM_JEM_CANCELLATION_BACK'); ?></button>
        <button type="button" class="btn btn-danger" data-cancel-proceed><?php echo Text::_('COM_JEM_CANCELLATION_CONFIRM_BUTTON'); ?></button>
    </div>
</dialog>
<p class="alert alert-warning mt-2" role="alert" data-cancel-error hidden></p>
