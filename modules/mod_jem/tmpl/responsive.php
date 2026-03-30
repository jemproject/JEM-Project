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
use Joomla\CMS\Uri\Uri;

// Extract parameters and configuration
$highlight_featured = $params->get('highlight_featured');
$showtitle          = $params->get('showtitle');
$showvenue          = $params->get('showvenue');
$linkloc            = $params->get('linkloc');
$linkdet            = $params->get('linkdet');
$showiconcountry    = $params->get('showiconcountry');
$settings           = JemHelper::config();
$baseUri            = Uri::getInstance()->base();

// Prepare flag path and extension
$flagPathRaw = $settings->flagicons_path;
$flagPath    = $flagPathRaw . (str_ends_with($flagPathRaw, '/') ? '' : '/');
$flagExt     = substr($flagPath, strrpos($flagPath, "-") + 1, -1);
?>

<div class="jemmodulebasic<?php echo $params->get('moduleclass_sfx'); ?>" id="jemmodulebasic">
    <?php if (count($list) > 0) { ?>
        <ul class="jemmod" style="list-style: none; padding: 0;">
            <?php foreach ($list as $item) {
                // Determine if event is featured
                $isFeatured = $highlight_featured && $item->featured;
                $eventClass = 'event-info' . ($isFeatured ? ' highlight_featured' : '');
                $boldStyle  = $isFeatured ? 'font-weight: bold;' : 'font-weight: normal;';
                ?>
                <li class="event_id<?php echo $item->eventid; ?>" itemprop="event" itemscope itemtype="https://schema.org/Event" style="margin-bottom: 15px; display: flex; align-items: flex-start; gap: 10px;">

                    <?php // Layout Icon ?>
                    <div class="event-icon" style="padding-top: 4px;">
                        <i class="far fa-calendar-alt"></i>
                    </div>

                    <div class="jem-event-wrapper" style="display: flex; align-items: flex-start; gap: 12px; width: 100%;">

                        <?php // Flag Section ?>
                        <?php if ($showiconcountry == 1 && !empty($item->country)) {
                            $flagFile = $baseUri . $flagPath . strtolower($item->country) . '.' . $flagExt;
                            ?>
                            <div class="jem-flag" style="flex-shrink: 0;">
                                <img src="<?php echo $flagFile; ?>" alt="<?php echo $item->country . ' ' . Text::_('MOD_JEM_SHOW_FLAG_ICON'); ?>" style="display: block; max-width: 40px; height: auto;">
                            </div>
                        <?php } ?>

                        <?php // Content Section: 3 Lines layout ?>
                        <div class="jem-event-content <?php echo $eventClass; ?>" style="display: flex; flex-direction: column; line-height: 1.4; <?php echo $boldStyle; ?>">

                            <?php // Line 1: Title ?>
                            <?php if ($showtitle) { ?>
                                <div class="event-title">
                                    <?php if ($linkdet == 2) { ?>
                                        <a href="<?php echo $item->link; ?>" title="<?php echo strip_tags($item->title); ?>" style="font-weight: inherit;">
                                            <?php echo $item->title; ?>
                                        </a>
                                    <?php } else {
                                        echo $item->title;
                                    } ?>
                                </div>
                            <?php } ?>

                            <?php // Line 2: Date (Visible link) ?>
                            <div class="event-date" style="font-size: 0.95em;">
                                <?php if ($linkdet == 1) { ?>
                                    <a href="<?php echo $item->link; ?>" title="<?php echo strip_tags($item->dateinfo); ?>" style="font-weight: inherit;">
                                        <?php echo $item->dateinfo; ?>
                                    </a>
                                <?php } else {
                                    echo $item->dateinfo;
                                } ?>
                            </div>

                            <?php // Line 3: Venue (Visible link) ?>
                            <?php if ($showvenue) { ?>
                                <div class="event-venue" style="font-style: italic; font-size: 0.9em;">
                                    <?php if ($linkloc == 1) { ?>
                                        <a href="<?php echo $item->venueurl; ?>" style="font-weight: inherit;">
                                            <?php echo $item->venue; ?>
                                        </a>
                                    <?php } else {
                                        echo $item->venue;
                                    } ?>
                                </div>
                            <?php } ?>

                        </div>
                    </div>

                    <?php // Hidden SEO metadata ?>
                    <?php echo $item->dateschema; ?>
                    <meta itemprop="name" content="<?php echo htmlspecialchars($item->title, ENT_QUOTES, 'UTF-8'); ?>" />

                    <div itemprop="location" itemscope itemtype="https://schema.org/Place" style="display:none;">
                        <meta itemprop="name" content="<?php echo htmlspecialchars($item->venue, ENT_QUOTES, 'UTF-8'); ?>" />
                        <div itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
                            <meta itemprop="streetAddress" content="<?php echo $item->street; ?>" />
                            <meta itemprop="addressLocality" content="<?php echo $item->city; ?>" />
                            <meta itemprop="addressRegion" content="<?php echo $item->state; ?>" />
                            <meta itemprop="postalCode" content="<?php echo $item->postalCode; ?>" />
                        </div>
                    </div>
                </li>
            <?php } ?>
        </ul>
    <?php } else { ?>
        <p><?php echo Text::_('MOD_JEM_NO_EVENTS'); ?></p>
    <?php } ?>
</div>