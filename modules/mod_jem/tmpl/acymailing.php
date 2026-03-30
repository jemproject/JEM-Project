<?php
/**
 * @package    JEM
 * @subpackage JEM Module
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

// Extract parameters
$highlight_featured = (int) $params->get('highlight_featured');
$showtitle          = (int) $params->get('showtitle');
$showvenue          = (int) $params->get('showvenue');
$linkloc            = (int) $params->get('linkloc');
$linkdet            = (int) $params->get('linkdet');
$showiconcountry    = (int) $params->get('showiconcountry');
?>

<div class="jem_acymailing">
    <?php if (!empty($list)) : ?>
        <?php foreach ($list as $item) :
            // Security: Escape data for email safety
            $title   = htmlspecialchars((string) ($item->title ?? ''), ENT_QUOTES, 'UTF-8');
            $venue   = htmlspecialchars((string) ($item->venue ?? ''), ENT_QUOTES, 'UTF-8');
            $country = htmlspecialchars((string) ($item->country ?? ''), ENT_QUOTES, 'UTF-8');
            $date    = (string) ($item->dateinfo ?? ''); // Keep HTML for dates

            // Links logic
            $eventLink = !empty($item->link) ? htmlspecialchars((string) $item->link, ENT_QUOTES, 'UTF-8') : '';
            $venueLink = !empty($item->venueurl) ? htmlspecialchars((string) $item->venueurl, ENT_QUOTES, 'UTF-8') : '';

            // Featured styling (Important for emails: use inline styles)
            $isFeatured = $highlight_featured && ($item->featured ?? false);
            $textStyle  = $isFeatured ? 'font-weight: bold; color: #000;' : 'font-weight: normal; color: #333;';
            ?>

            <div style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eeeeee;">

                <?php // Line 1: Title & Country Flag (Text based for Email) ?>
                <?php if ($showtitle) : ?>
                    <p style="<?php echo $textStyle; ?> margin: 0; font-size: 16px;">
                        <?php if ($showiconcountry === 1 && $country !== '') : ?>
                            <span style="color: #666; font-size: 0.8em;">[<?php echo $country; ?>]</span>
                        <?php endif; ?>

                        <?php if ($linkdet === 2 && $eventLink !== '') : ?>
                            <a href="<?php echo $eventLink; ?>" style="color: #007bff; text-decoration: underline; font-weight: inherit;">
                                <?php echo $title; ?>
                            </a>
                        <?php else : ?>
                            <?php echo $title; ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <?php // Line 2: Date (Email visible link) ?>
                <p style="<?php echo $textStyle; ?> margin: 4px 0; font-size: 14px;">
                    <?php if (($linkdet === 1) && $eventLink !== '') : ?>
                        <a href="<?php echo $eventLink; ?>" style="color: #007bff; text-decoration: none; font-weight: inherit;">
                            <?php echo $date; ?>
                        </a>
                    <?php else : ?>
                        <?php echo $date; ?>
                    <?php endif; ?>
                </p>

                <?php // Line 3: Venue (Added at the end) ?>
                <?php if ($showvenue && $venue !== '') : ?>
                    <p style="<?php echo $textStyle; ?> margin: 2px 0 0 0; font-size: 13px; font-style: italic; color: #555;">
                        <?php if ($linkloc === 1 && $venueLink !== '') : ?>
                            <a href="<?php echo $venueLink; ?>" style="color: #007bff; text-decoration: underline; font-weight: inherit;">
                                <?php echo $venue; ?>
                            </a>
                        <?php else : ?>
                            <?php echo $venue; ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

            </div>

        <?php endforeach; ?>
    <?php else : ?>
        <p><?php echo Text::_('MOD_JEM_NO_EVENTS'); ?></p>
    <?php endif; ?>
</div>