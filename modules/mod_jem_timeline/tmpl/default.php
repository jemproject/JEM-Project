<?php
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

?>

<div class="jem-timeline<?php echo $params->get('moduleclass_sfx'); ?>">
    <div class="timeline-line"></div>
    <div class="timeline-items">
        <?php foreach ($list as $i => $item) : 
            $side = ($i % 2 === 0) ? 'left' : 'right';
        ?>
        <div class="timeline-item timeline-<?php echo $side; ?>">
            <div class="timeline-card">
                <?php if ($item->eventimage): ?>
                    <div class="timeline-image">
                        <img src="<?php echo $item->eventimage; ?>" alt="<?php echo $item->title; ?>">
                    </div>
                <?php endif; ?>
                <div class="timeline-content">
                    <h3><?php echo $item->title; ?></h3>
                    <div class="timeline-date"><?php echo $item->date . ($item->time ? ' ' . $item->time : ''); ?></div>
                    <div class="timeline-venue"><?php echo $item->venue; ?></div>
                    <div class="timeline-category"><?php echo $item->catname; ?></div>
                    <a href="<?php echo $item->link; ?>" class="btn-readmore">Details</a>
            </div>
        </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.jem-timeline { position: relative; margin: 2rem 0; }
.timeline-line { position: absolute; left: 50%; top: 0; width: 4px; background: #ccc; height: 100%; transform: translateX(-50%); }
.timeline-items { display: flex; flex-direction: column; }
.timeline-item { position: relative; width: 50%; padding: 1rem 2rem; }
.timeline-left { left: 0; text-align: right; }
.timeline-right { left: 50%; text-align: left; }
.timeline-card { background: #f7f7f7; padding: 1rem; border-radius: 6px; border: 1px solid #ddd; }
.timeline-image img { max-width: 100%; display: block; margin-bottom: 0.5rem; }
@media(max-width: 768px){
    .timeline-item { width: 100%; text-align: left !important; left: 0 !important; padding-left: 3rem; }
    .timeline-line { left: 2rem; }
}
</style>
