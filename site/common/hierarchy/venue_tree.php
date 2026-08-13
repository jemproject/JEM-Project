<?php
defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$venue = $this->venue;
$hierarchy = $this->venueHierarchy ?? array(
    'parent_venue_id' => (int) ($venue->parent_venue_id ?? 0),
    'parent_venue_name' => (string) ($venue->parent_venue_name ?? ''),
    'parent_venue_alias' => (string) ($venue->parent_venue_alias ?? ''),
    'child_venues' => (array) ($venue->child_venues ?? array()),
);

if (!empty($hierarchy['parent_venue_id']) && !empty($hierarchy['parent_venue_name'])) :
    $parentSlug = (int) $hierarchy['parent_venue_id'] . (!empty($hierarchy['parent_venue_alias']) ? ':' . $hierarchy['parent_venue_alias'] : '');
?>
    <nav class="jem-venue-parent" aria-label="<?php echo htmlspecialchars(Text::_('COM_JEM_PARENT_VENUE'), ENT_QUOTES, 'UTF-8'); ?>">
        <strong><?php echo Text::_('COM_JEM_PARENT_VENUE'); ?>:</strong>
        <a href="<?php echo Route::_(JemHelperRoute::getVenueRoute($parentSlug)); ?>">
            <?php echo htmlspecialchars($hierarchy['parent_venue_name'], ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </nav>
<?php endif; ?>

<?php if (!empty($hierarchy['child_venues'])) : ?>
    <section class="jem-subvenues" aria-labelledby="jem-subvenues-title">
        <h2 id="jem-subvenues-title" class="jem"><?php echo Text::_('COM_JEM_SUBVENUES'); ?></h2>
        <ul class="jem-subvenues-list">
            <?php foreach ($hierarchy['child_venues'] as $childVenue) : ?>
                <li>
                    <a href="<?php echo Route::_(JemHelperRoute::getVenueRoute($childVenue->slug)); ?>">
                        <?php echo htmlspecialchars($childVenue->venue, ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                    <?php if (!empty($childVenue->level)) : ?>
                        <span class="jem-subvenue-level"><?php echo htmlspecialchars($childVenue->level, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($childVenue->capacity)) : ?>
                        <span class="jem-subvenue-capacity"><?php echo (int) $childVenue->capacity; ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
