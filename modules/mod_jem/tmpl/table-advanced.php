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
$displayorder       = (int) $params->get('display_order', 0);
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

$linkStyle = 'style="color: inherit; text-decoration: none; font-weight: inherit;"';

// --- PREPARE TABLE HEADERS ---
$headerTitle = '<th style="text-align: left;"><i class="fa-solid fa-calendar-days"></i> '.Text::_('COM_JEM_EVENT').'</th>';
$headerDate  = '<th><i class="fa-solid fa-calendar-check"></i> '.Text::_('COM_JEM_DATE').'</th>'; // Simplified Date Header
$headerVenue = '<th><i class="fa-solid fa-location-dot"></i> '.Text::_('COM_JEM_VENUE').'</th>';

function renderOrderedRow($order, $colT, $colD, $colV) {
    switch ($order) {
        case 1: return $colT . $colV . $colD;
        case 2: return $colV . $colT . $colD;
        case 3: return $colV . $colD . $colT;
        case 4: return $colD . $colT . $colV;
        case 5: return $colD . $colV . $colT;
        case 0:
        default: return $colT . $colD . $colV;
    }
}
?>

<div class="jemmodulebasic<?php echo $params->get('moduleclass_sfx')?>" id="jemmodulebasic-tableadvanced">
    <?php if (count($list)): ?>
        <table class="jemmod" style="width: 100%; border-collapse: collapse;">
            <thead>
            <tr>
                <?php echo renderOrderedRow($displayorder, $headerTitle, $headerDate, $headerVenue); ?>
                <th><i class="fa-solid fa-link"></i> <?php echo Text::_('COM_JEM_LINK'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($list as $item) :
                $isFeatured = $highlight_featured && $item->featured;
                $boldStyle  = $isFeatured ? 'font-weight: bold;' : 'font-weight: normal;';

                // Column: Title (includes Flag)
                $colTitle = '';
                $flagHtml = '';
                if (($showiconcountry == 1) && !empty($item->country)) {
                    $flagfile = $baseUri . $flagPath . strtolower($item->country) . '.' . $flagExt;
                    $flagHtml = '<img src="' . $flagfile . '" alt="' . $item->country . '" style="max-width: 25px; height: auto; margin-right: 8px; vertical-align: middle; display: inline-block;">';
                }
                $titleContent = ($linkdet == 2) ? '<a href="'.$item->link.'" '.$linkStyle.'>'.$item->title.'</a>' : $item->title;
                $colTitle = '<td style="padding: 8px; text-align: left; vertical-align: middle;">' . $flagHtml . '<span class="event-title">' . $titleContent . '</span></td>';

                // Column: Date (Combines start/end info to keep 3-column logic)
                $dateContent = ($linkdet == 1) ? '<a href="'.$item->link.'" '.$linkStyle.'>'.$item->dates . ' ' . $item->times . '</a>' : $item->dates . ' ' . $item->times;
                $colDate = '<td style="padding: 8px 4px; text-align: center;">' . $dateContent . '</td>';

                // Column: Venue
                $colVenue = '';
                if ($showvenue) {
                    $venueContent = ($linkloc == 1) ? '<a href="'.$item->venueurl.'" '.$linkStyle.'>'.$item->venue.'</a>' : $item->venue;
                    $colVenue = '<td style="padding: 8px 4px; text-align: center; font-style: italic;">' . $venueContent . '</td>';
                } else {
                    $colVenue = '<td></td>'; // Keeps table structure if venue is hidden but order is selected
                }
                ?>

                <tr class="event_id<?php echo $item->eventid; ?>" style="border-bottom: 1px solid #eee; <?php echo $boldStyle; ?>">

                    <?php echo renderOrderedRow($displayorder, $colTitle, $colDate, $colVenue); ?>

                    <td style="padding: 8px 4px; text-align: center;">
                        <?php if (!empty($item->link)) : ?>
                            <a href="<?php echo $item->link; ?>" title="<?php echo Text::_('COM_JEM_SHOW_DETAILS'); ?>">
                                <i class="far fa-eye"></i>
                            </a>
                        <?php else : ?>
                            <i class="fas fa-minus" title="<?php echo Text::_('MOD_JEM_NO_LINK'); ?>"></i>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php echo Text::_('MOD_JEM_NO_EVENTS'); ?></p>
    <?php endif; ?>
</div>