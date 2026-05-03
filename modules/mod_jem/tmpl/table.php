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

<div class="jemmodulebasic<?php echo $params->get('moduleclass_sfx'); ?>" id="jemmodulebasic">
    <?php if (count($list) > 0) : ?>
        <table class="jemmod_table" style="width: 100%; border-collapse: collapse;">
            <tbody>
            <?php foreach ($list as $item) :
                $isFeatured = $highlight_featured && $item->featured;
                $boldStyle  = $isFeatured ? 'font-weight: bold;' : 'font-weight: normal;';

                // Column: Title
                $colTitle = '';
                if ($showtitle) {
                    $contentTitle = ($linkdet == 2) ? '<a href="'.$item->link.'" title="'.strip_tags($item->title).'" '.$linkStyle.'>'.$item->title.'</a>' : $item->title;
                    $colTitle = '<td style="padding: 10px 5px; vertical-align: middle;"><div class="event-title">' . $contentTitle . '</div></td>';
                }

                // Column: Date
                $contentDate = ($linkdet == 1) ? '<a href="'.$item->link.'" title="'.strip_tags($item->dateinfo).'" '.$linkStyle.'>'.$item->dateinfo.'</a>' : $item->dateinfo;
                $colDate = '<td style="padding: 10px 5px; vertical-align: middle; font-size: 0.9em;">' . $contentDate . '</td>';

                // Column: Venue
                $colVenue = '';
                if ($showvenue) {
                    $contentVenue = ($linkloc == 1) ? '<a href="'.$item->venueurl.'" '.$linkStyle.'>'.$item->venue.'</a>' : $item->venue;
                    $colVenue = '<td style="padding: 10px 5px; vertical-align: middle; font-size: 0.9em; font-style: italic;">' . $contentVenue . '</td>';
                }
                ?>

                <tr class="event_id<?php echo $item->eventid; ?>" itemprop="event" itemscope itemtype="https://schema.org/Event" style="border-bottom: 1px solid #eee; <?php echo $boldStyle; ?>">

                    <td style="padding: 10px 5px; vertical-align: middle; width: 50px; text-align: center;">
                        <?php if ($showiconcountry == 1 && !empty($item->country)) :
                            $flagFile = $baseUri . $flagPath . strtolower($item->country) . '.' . $flagExt; ?>
                            <img src="<?php echo $flagFile; ?>" alt="<?php echo $item->country; ?>" style="max-width: 30px; height: auto; display: inline-block;">
                        <?php else : ?>
                            <i class="far fa-calendar-alt"></i>
                        <?php endif; ?>
                    </td>

                    <?php
                    switch ($displayorder) {
                        case 1:
                            echo $colTitle . $colVenue . $colDate;
                            break;
                        case 2:
                            echo $colVenue . $colTitle . $colDate;
                            break;
                        case 3:
                            echo $colVenue . $colDate . $colTitle;
                            break;
                        case 4:
                            echo $colDate . $colTitle . $colVenue;
                            break;
                        case 5:
                            echo $colDate . $colVenue . $colTitle;
                            break;
                        case 0:
                        default:
                            echo $colTitle . $colDate . $colVenue;
                            break;
                    }
                    ?>
                </tr>

                <tr style="display:none;">
                    <td colspan="4">
                        <?php echo $item->dateschema; ?>
                        <meta itemprop="name" content="<?php echo htmlspecialchars($item->title, ENT_QUOTES, 'UTF-8'); ?>" />
                        <div itemprop="location" itemscope itemtype="https://schema.org/Place">
                            <meta itemprop="name" content="<?php echo htmlspecialchars($item->venue, ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php echo Text::_('MOD_JEM_NO_EVENTS'); ?></p>
    <?php endif; ?>
</div>