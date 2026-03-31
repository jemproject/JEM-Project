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

// Extract parameters
$highlight_featured = $params->get('highlight_featured');
$displayorder       = $params->get('display_order', 0);
$showtitle          = $params->get('showtitle');
$showvenue          = $params->get('showvenue');
$linkloc            = $params->get('linkloc');
$linkdet            = $params->get('linkdet');
$showiconcountry    = $params->get('showiconcountry');
$settings           = JemHelper::config();
$baseUri            = Uri::getInstance()->base();

$flagPathRaw = $settings->flagicons_path;
$flagPath    = $flagPathRaw . (str_ends_with($flagPathRaw, '/') ? '' : '/');
$flagExt     = substr($flagPath, strrpos($flagPath, "-") + 1, -1);
?>

<div class="jemmodulebasic<?php echo $params->get('moduleclass_sfx'); ?>" id="jemmodulebasic">
    <?php if (count($list) > 0) : ?>
        <ul class="jemmod" style="list-style: none; padding: 0;">
            <?php foreach ($list as $item) :
                $isFeatured = $highlight_featured && $item->featured;
                $eventClass = 'event-info' . ($isFeatured ? ' highlight_featured' : '');
                $boldStyle  = $isFeatured ? 'font-weight: bold;' : 'font-weight: normal;';

                // Block T: Title
                $blockTitle = '';
                if ($showtitle) {
                    $linkAttr = 'style="color: inherit; text-decoration: none; font-weight: inherit;"';
                    $content  = ($linkdet == 2) ? '<a href="'.$item->link.'" title="'.strip_tags($item->title).'" '.$linkAttr.'>'.$item->title.'</a>' : $item->title;
                    $blockTitle = '<div class="event-title">' . $content . '</div>';
                }

                // Block D: Date
                $linkAttrDate = 'style="color: inherit; text-decoration: none; font-weight: inherit;"';
                $contentDate  = ($linkdet == 1) ? '<a href="'.$item->link.'" title="'.strip_tags($item->dateinfo).'" '.$linkAttrDate.'>'.$item->dateinfo.'</a>' : $item->dateinfo;
                $blockDate    = '<div class="event-date" style="font-size: 0.95em;">' . $contentDate . '</div>';

                // Block V: Venue
                $blockVenue = '';
                if ($showvenue && !empty($item->venue)) {
                    $linkAttrVenue = 'style="color: inherit; text-decoration: none; font-weight: inherit;"';
                    $contentVenue  = ($linkloc == 1) ? '<a href="'.$item->venueurl.'" '.$linkAttrVenue.'>'.$item->venue.'</a>' : $item->venue;
                    $blockVenue    = '<div class="event-venue" style="font-style: italic; font-size: 0.9em;">' . $contentVenue . '</div>';
                }
                ?>

                <li class="event_id<?php echo $item->eventid; ?>" itemprop="event" itemscope itemtype="https://schema.org/Event" style="margin-bottom: 15px;">
                    <div class="jem-event-wrapper" style="display: flex; align-items: flex-start; gap: 12px;">

                        <?php // Flag Section ?>
                        <?php if ($showiconcountry == 1 && !empty($item->country)) :
                            $flagFile = $baseUri . $flagPath . strtolower($item->country) . '.' . $flagExt; ?>
                            <div class="jem-flag" style="flex-shrink: 0;">
                                <img src="<?php echo $flagFile; ?>" alt="<?php echo $item->country; ?>" style="display: block; max-width: 40px; height: auto; padding-top: 5px;">
                            </div>
                        <?php endif; ?>

                        <?php // Content Section with Dynamic Order ?>
                        <div class="jem-event-content <?php echo $eventClass; ?>" style="display: flex; flex-direction: column; line-height: 1.4; <?php echo $boldStyle; ?>">
                            <?php
                            switch ($displayorder) {
                                case 1:
                                    echo $blockTitle . $blockVenue . $blockDate;
                                    break;
                                case 2:
                                    echo $blockVenue . $blockTitle . $blockDate;
                                    break;
                                case 3:
                                    echo $blockVenue . $blockDate . $blockTitle;
                                    break;
                                case 4:
                                    echo $blockDate . $blockTitle . $blockVenue;
                                    break;
                                case 5:
                                    echo $blockDate . $blockVenue . $blockTitle;
                                    break;
                                case 0:
                                default:
                                    echo $blockTitle . $blockDate . $blockVenue;
                                    break;
                            }
                            ?>
                        </div>
                    </div>

                    <?php echo $item->dateschema; ?>
                    <meta itemprop="name" content="<?php echo htmlspecialchars($item->title, ENT_QUOTES, 'UTF-8'); ?>" />
                    <div itemprop="location" itemscope itemtype="https://schema.org/Place" style="display:none;">
                        <meta itemprop="name" content="<?php echo htmlspecialchars($item->venue, ENT_QUOTES, 'UTF-8'); ?>" />
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else : ?>
        <p><?php echo Text::_('MOD_JEM_NO_EVENTS'); ?></p>
    <?php endif; ?>
</div>