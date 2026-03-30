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
        <table class="jemmod_table" style="width: 100%; border-collapse: collapse;">
            <tbody>
            <?php foreach ($list as $item) {
                // Determine if event is featured for bold styling
                $isFeatured = $highlight_featured && $item->featured;
                $boldStyle  = $isFeatured ? 'font-weight: bold;' : 'font-weight: normal;';
                ?>
                <tr class="event_id<?php echo $item->eventid; ?>" itemprop="event" itemscope itemtype="https://schema.org/Event" style="border-bottom: 1px solid #eee; <?php echo $boldStyle; ?>">

                    <td style="padding: 10px 5px; vertical-align: middle; width: 50px;">
                        <?php if ($showiconcountry == 1 && !empty($item->country)) {
                            $flagFile = $baseUri . $flagPath . strtolower($item->country) . '.' . $flagExt;
                            ?>
                            <img src="<?php echo $flagFile; ?>" alt="<?php echo $item->country; ?>" style="max-width: 30px; height: auto; display: block;">
                        <?php } else { ?>
                            <i class="far fa-calendar-alt"></i>
                        <?php } ?>
                    </td>

                    <td style="padding: 10px 5px; vertical-align: middle;">
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
                    </td>

                    <td style="padding: 10px 5px; vertical-align: middle; font-size: 0.9em;">
                        <?php if ($linkdet == 1) { ?>
                            <a href="<?php echo $item->link; ?>" title="<?php echo strip_tags($item->dateinfo); ?>" style="font-weight: inherit;">
                                <?php echo $item->dateinfo; ?>
                            </a>
                        <?php } else {
                            echo $item->dateinfo;
                        } ?>
                    </td>

                    <td style="padding: 10px 5px; vertical-align: middle; font-size: 0.9em; font-style: italic;">
                        <?php if ($showvenue) { ?>
                            <?php if ($linkloc == 1) { ?>
                                <a href="<?php echo $item->venueurl; ?>" style="font-weight: inherit;">
                                    <?php echo $item->venue; ?>
                                </a>
                            <?php } else {
                                echo $item->venue;
                            } ?>
                        <?php } ?>
                    </td>
                </tr>

                <tr style="display:none;">
                    <td colspan="4">
                        <?php echo $item->dateschema; ?>
                        <meta itemprop="name" content="<?php echo htmlspecialchars($item->title, ENT_QUOTES, 'UTF-8'); ?>" />
                        <div itemprop="location" itemscope itemtype="https://schema.org/Place">
                            <meta itemprop="name" content="<?php echo htmlspecialchars($item->venue, ENT_QUOTES, 'UTF-8'); ?>" />
                            <div itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
                                <meta itemprop="streetAddress" content="<?php echo $item->street; ?>" />
                                <meta itemprop="addressLocality" content="<?php echo $item->city; ?>" />
                                <meta itemprop="addressRegion" content="<?php echo $item->state; ?>" />
                                <meta itemprop="postalCode" content="<?php echo $item->postalCode; ?>" />
                            </div>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    <?php } else { ?>
        <p><?php echo Text::_('MOD_JEM_NO_EVENTS'); ?></p>
    <?php } ?>
</div>