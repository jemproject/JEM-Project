<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;

$bookedAreas = array_filter((array) $this->capacityRegistration->options, static function ($area) {
    return (int) $area['current_quantity'] > 0;
});
?>
<?php if (count($bookedAreas) > 1
    && !in_array((string) ($this->registration->pricing_mode ?? 'classic'), array('single', 'multiple', 'priced'), true)) : ?>
    <fieldset class="jem-cancellation-areas my-3" hidden disabled>
        <legend><?php echo Text::_('COM_JEM_CANCELLATION_AREAS'); ?></legend>
        <?php foreach ($bookedAreas as $area) : ?>
            <label class="d-block mb-2">
                <?php echo $this->escape($area['space_name'] . ' — ' . $area['area_name']); ?>
                <input type="number" name="cancel_capacity_areas[<?php echo $this->escape($area['key']); ?>]"
                    min="0" max="<?php echo (int) $area['current_quantity']; ?>"
                    value="<?php echo (int) $area['current_quantity']; ?>" class="form-control w-auto"
                    data-cancel-area>
            </label>
        <?php endforeach; ?>
    </fieldset>
<?php endif; ?>
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
