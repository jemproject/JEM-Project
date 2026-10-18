<?php
/**
 * @package JEM
 * @copyright (C) 2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

if (empty($this->jedReviewAvailable)) {
    return;
}
$escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<?php foreach (JemJedReview::cards() as $key => $card) : ?>
    <div data-jem-community-button="<?php echo $key; ?>"<?php echo $this->communityCardDismissed[$key] ? '' : ' hidden'; ?>>
        <div class="icon">
            <a href="<?php echo $escape($card['url']); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo HTMLHelper::_('image', 'com_jem/' . $card['icon'], '', ['aria-hidden' => 'true'], true); ?>
                <span><?php echo Text::_($key === 'jed' ? 'COM_JEM_COMMUNITY_JED_BUTTON' : $card['button']); ?></span>
                <span class="visually-hidden"><?php echo Text::_('COM_JEM_JED_REVIEW_EXTERNAL'); ?></span>
            </a>
        </div>
    </div>
<?php endforeach; ?>
