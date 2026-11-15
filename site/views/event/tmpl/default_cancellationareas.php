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
            <label class="jem-cancellation-area">
                <span><?php echo $this->escape($area['space_name'] . ' — ' . $area['area_name']); ?></span>
                <input type="number" name="cancel_capacity_areas[<?php echo $this->escape($area['key']); ?>]"
                    min="0" max="<?php echo (int) $area['current_quantity']; ?>"
                    value="<?php echo (int) $area['current_quantity']; ?>" class="form-control w-auto"
                    data-cancel-area>
            </label>
        <?php endforeach; ?>
    </fieldset>
<?php endif; ?>
