<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

if (empty($this->jedReviewAvailable)) {
    return;
}
$escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="jem-community-cards" data-jem-community-cards<?php echo !in_array(false, $this->communityCardDismissed, true) ? ' hidden' : ''; ?>>
    <?php foreach (JemJedReview::cards() as $key => $card) : ?>
        <section class="card" aria-labelledby="jem-community-title-<?php echo $key; ?>" data-jem-community-card="<?php echo $key; ?>"<?php echo $this->communityCardDismissed[$key] ? ' hidden' : ''; ?>>
            <form class="card-body d-flex flex-column" action="<?php echo Route::_('index.php?option=com_jem&task=jedreview.save'); ?>" method="post">
                <h2 class="h5 card-title" id="jem-community-title-<?php echo $key; ?>"><?php echo Text::_($card['title']); ?></h2>
                <div class="jem-community-intro">
                    <p class="card-text"><?php echo Text::_($card['description']); ?></p>
                    <?php if ($key === 'donate') : ?>
                        <?php echo HTMLHelper::_('image', 'com_jem/PayPal_DonateButton.webp', '', ['class' => 'jem-community-donate-image'], true); ?>
                    <?php elseif ($key === 'jed') : ?>
                        <?php echo HTMLHelper::_('image', 'com_jem/jem-review-banner.webp', Text::_('COM_JEM_JED_REVIEW_SLOGAN_FIRST') . ' ' . Text::_('COM_JEM_JED_REVIEW_SLOGAN_SECOND'), ['class' => 'jem-community-review-image', 'width' => 720, 'height' => 264], true); ?>
                    <?php endif; ?>
                </div>
                <?php if ($key === 'jed') : ?>
                    <p class="small"><?php echo Text::_('COM_JEM_JED_REVIEW_ACCOUNT'); ?></p>
                <?php endif; ?>
                <div class="d-flex flex-wrap gap-2 mt-auto">
                    <a class="btn btn-outline-primary" href="<?php echo $escape($card['url']); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo Text::_($card['button']); ?>
                        <span class="icon-external-link-alt" aria-hidden="true"></span>
                        <span class="visually-hidden"><?php echo Text::_('COM_JEM_JED_REVIEW_EXTERNAL'); ?></span>
                    </a>
                    <button class="btn btn-link" type="submit" name="review_action" value="dismiss"><?php echo Text::_('COM_JEM_COMMUNITY_AS_BUTTON'); ?></button>
                </div>
                <input type="hidden" name="community_card" value="<?php echo $key; ?>">
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        </section>
    <?php endforeach; ?>
</div>
<p class="small mt-2 mb-0" role="status" aria-live="polite" data-jem-community-status data-error="<?php echo $escape(Text::_('COM_JEM_COMMUNITY_ERROR')); ?>"></p>
