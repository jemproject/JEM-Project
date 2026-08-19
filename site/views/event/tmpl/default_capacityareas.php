<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

if (empty($this->capacityRegistration->enabled)) {
    return;
}
?>
<div class="jem-capacity-registration card my-3">
    <div class="card-body">
        <h3 class="h5"><?php echo Text::_('COM_JEM_CAPACITY_REGISTRATION_AREAS'); ?></h3>
        <div class="row g-3">
            <?php foreach ((array) $this->capacityRegistration->options as $option) : ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <label class="form-label" for="jem-capacity-<?php echo preg_replace('/[^a-z0-9_-]/i', '-', (string) $option['key']); ?>">
                        <strong><?php echo $this->escape((string) $option['area_name']); ?></strong>
                        <span class="d-block text-muted"><?php echo $this->escape((string) $option['space_name']); ?></span>
                    </label>
                    <div class="input-group">
                        <input class="form-control jem-capacity-area-quantity" type="number"
                            id="jem-capacity-<?php echo preg_replace('/[^a-z0-9_-]/i', '-', (string) $option['key']); ?>"
                            name="capacity_areas[<?php echo $this->escape((string) $option['key']); ?>]"
                            min="0" max="<?php echo (int) $option['remaining']; ?>"
                            value="<?php echo (int) $option['current_quantity']; ?>">
                        <span class="input-group-text"><?php echo Text::sprintf('COM_JEM_CAPACITY_REGISTRATION_AREA_AVAILABLE', (int) $option['remaining']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
