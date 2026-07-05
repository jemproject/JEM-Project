<?php
/**
 * @version    4.2.2
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
$showcategory       = ((int) $params->get('showcategory', 0) === 1);
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
?>

<div class="jemmodulebasic<?php echo $params->get('moduleclass_sfx')?>" id="jemmodulebasic-tablestyle">
    <?php if (count($list)): ?>
        <table class="jemmod" style="width: 100%; border-collapse: collapse;">
            <?php foreach ($list as $item) :
                $isFeatured = $highlight_featured && $item->featured;
                $boldStyle  = $isFeatured ? 'font-weight: bold;' : 'font-weight: normal;';

                // Column: Title (includes Flag)
                $colTitle = '';
                if ($showtitle) {
                    $flagHtml = '';
                    if (($showiconcountry == 1) && !empty($item->country)) {
                        $flagfile = $baseUri . $flagPath . strtolower($item->country) . '.' . $flagExt;
                        $flagHtml = '<img src="' . $flagfile . '" alt="' . $item->country . ' ' . Text::_('MOD_JEM_SHOW_FLAG_ICON') . '" style="max-width: 25px; margin-right: 5px; vertical-align: middle;">';
                    }
                    $titleContent = ($linkdet == 2) ? '<a href="'.$item->link.'" '.$linkStyle.'>'.$item->title.'</a>' : $item->title;
                    $colTitle = '<td style="padding: 8px 4px; vertical-align: middle;"><span class="event-title">' . $flagHtml . $titleContent . '</span></td>';
                }

                // Column: Date
                $dateContent = ($linkdet == 1) ? '<a href="'.$item->link.'" '.$linkStyle.'>'.$item->dateinfo.'</a>' : $item->dateinfo;
                $colDate = '<td style="padding: 8px 4px; vertical-align: middle;"><span class="event-date">' . $dateContent . '</span></td>';

                // Column: Venue
                $colVenue = '';
                if ($showvenue) {
                    $venueContent = ($linkloc == 1) ? '<a href="'.$item->venueurl.'" '.$linkStyle.'>'.$item->venue.'</a>' : $item->venue;
                    $colVenue = '<td style="padding: 8px 4px; vertical-align: middle;"><span class="event-venue" style="font-style: italic;">' . $venueContent . '</span></td>';
                }

                // Column: Category
                $colCategory = '';
                if ($showcategory) {
                    $colCategory = '<td style="padding: 8px 4px; vertical-align: middle;"><span class="event-category">' . $item->catname . '</span></td>';
                }
                ?>
                <tr class="event_id<?php echo $item->eventid; ?>" style="border-bottom: 1px solid #eee; <?php echo $boldStyle; ?>">
                    <?php
                    switch ($displayorder) {
                        case 1:
                            echo $colTitle . $colVenue . $colDate . $colCategory;
                            break;
                        case 2:
                            echo $colVenue . $colTitle . $colDate . $colCategory;
                            break;
                        case 3:
                            echo $colVenue . $colDate . $colTitle . $colCategory;
                            break;
                        case 4:
                            echo $colDate . $colTitle . $colVenue . $colCategory;
                            break;
                        case 5:
                            echo $colDate . $colVenue . $colTitle . $colCategory;
                            break;
                        case 0:
                        default:
                            echo $colTitle . $colDate . $colVenue . $colCategory;
                            break;
                    }
                    ?>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else : ?>
        <p><?php echo Text::_('MOD_JEM_NO_EVENTS'); ?></p>
    <?php endif; ?>
</div>
