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
$displayorder       = $params->get('display_order', 0);
$showtitle          = (int) $params->get('showtitle');
$showvenue          = (int) $params->get('showvenue');
$linkloc            = (int) $params->get('linkloc');
$linkdet            = (int) $params->get('linkdet');
$showiconcountry    = (int) $params->get('showiconcountry');
?>

<div class="jem_acymailing">
    <?php if (!empty($list)) : ?>
        <?php foreach ($list as $item) :
            // Data Prep
            $title   = htmlspecialchars((string) ($item->title ?? ''), ENT_QUOTES, 'UTF-8');
            $venue   = htmlspecialchars((string) ($item->venue ?? ''), ENT_QUOTES, 'UTF-8');
            $country = htmlspecialchars((string) ($item->country ?? ''), ENT_QUOTES, 'UTF-8');
            $date    = (string) ($item->dateinfo ?? '');

            $eventLink = !empty($item->link) ? htmlspecialchars((string) $item->link, ENT_QUOTES, 'UTF-8') : '';
            $venueLink = !empty($item->venueurl) ? htmlspecialchars((string) $item->venueurl, ENT_QUOTES, 'UTF-8') : '';

            $isFeatured = $highlight_featured && ($item->featured ?? false);
            $textStyle  = $isFeatured ? 'font-weight: bold; color: #000;' : 'font-weight: normal; color: #333;';

            // Block Title
            $blockTitle = '';
            if ($showtitle) {
                $flag = ($showiconcountry === 1 && $country !== '') ? '<span style="color: #666; font-size: 0.8em;">[' . $country . '] </span>' : '';
                $titleContent = ($linkdet === 2 && $eventLink !== '') ? '<a href="'.$eventLink.'" style="color: #007bff; text-decoration: underline; font-weight: inherit;">'.$title.'</a>' : $title;
                $blockTitle = '<p style="'.$textStyle.' margin: 0; font-size: 16px;">' . $flag . $titleContent . '</p>';
            }

            // Block Date
            $dateContent = (($linkdet === 1) && $eventLink !== '') ? '<a href="'.$eventLink.'" style="color: #007bff; text-decoration: none; font-weight: inherit;">'.$date.'</a>' : $date;
            $blockDate = '<p style="'.$textStyle.' margin: 4px 0; font-size: 14px;">' . $dateContent . '</p>';

            // Block Venue
            $blockVenue = '';
            if ($showvenue && $venue !== '') {
                $venueContent = ($linkloc === 1 && $venueLink !== '') ? '<a href="'.$venueLink.'" style="color: #007bff; text-decoration: underline; font-weight: inherit;">'.$venue.'</a>' : $venue;
                $blockVenue = '<p style="'.$textStyle.' margin: 2px 0 0 0; font-size: 13px; font-style: italic; color: #555;">' . $venueContent . '</p>';
            }
            ?>

            <div style="margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eeeeee;">
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

        <?php endforeach; ?>
    <?php else : ?>
        <p><?php echo Text::_('MOD_JEM_NO_EVENTS'); ?></p>
    <?php endif; ?>
</div>