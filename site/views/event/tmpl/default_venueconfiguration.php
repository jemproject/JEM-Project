<?php
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

if (empty($this->venueConfiguration)) {
    return;
}
?>
<section class="jem-event-venue-configuration" aria-labelledby="jem-event-venue-configuration-title">
    <h2 id="jem-event-venue-configuration-title" class="jem"><?php echo Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION'); ?></h2>
    <ul class="list-unstyled">
        <?php foreach ($this->venueConfiguration as $line) : ?>
            <li class="mb-2">
                <strong><?php echo $this->escape($line['space']); ?></strong>
                <?php if ($line['layout'] !== '') : ?><span> &middot; <?php echo $this->escape($line['layout']); ?></span><?php endif; ?>
                <?php if ($line['capacity'] > 0) : ?><span class="badge bg-light text-dark border"><?php echo (int) $line['capacity']; ?></span><?php endif; ?>
                <?php if ($line['areas']) : ?>
                    <span class="d-block text-muted">
                        <?php echo implode(' · ', array_map(function ($area) {
                            return $this->escape($area['name']) . ' (' . (int) $area['capacity'] . ')';
                        }, $line['areas'])); ?>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
