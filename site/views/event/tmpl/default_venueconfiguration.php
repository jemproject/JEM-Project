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
    <?php if (!empty($this->venuePlans)) : ?>
        <details class="jem-event-plans my-3">
            <summary class="btn btn-outline-secondary"><?php echo Text::_('COM_JEM_EVENT_VIEW_PLAN'); ?></summary>
            <p class="text-muted mt-2"><?php echo Text::_('COM_JEM_EVENT_PLAN_INFORMATION'); ?></p>
            <?php foreach ($this->venuePlans as $plan) : ?>
                <?php $planUrl = \Joomla\CMS\Uri\Uri::root() . implode('/', array_map('rawurlencode', explode('/', $plan['path']))); ?>
                <figure class="my-3">
                    <figcaption class="mb-2"><strong><?php echo $this->escape($plan['space'] . ' — ' . $plan['layout']); ?></strong></figcaption>
                    <a href="<?php echo $this->escape($planUrl); ?>" target="_blank" rel="noopener">
                        <img src="<?php echo $this->escape($planUrl); ?>" loading="lazy" class="img-fluid"
                             style="max-width:100%;height:auto"
                             alt="<?php echo $this->escape($plan['alt'] ?: $plan['space'] . ' — ' . $plan['layout']); ?>">
                        <span class="d-block"><?php echo Text::_('COM_JEM_EVENT_PLAN_FULL_SIZE'); ?></span>
                    </a>
                </figure>
            <?php endforeach; ?>
        </details>
    <?php endif; ?>
</section>
