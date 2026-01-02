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

JemHelper::loadModuleStyleSheet('mod_jem');

$highlight_featured = (int) $params->get('highlight_featured');
$showtitloc         = (int) $params->get('showtitloc');
$linkloc            = (int) $params->get('linkloc');
$linkdet            = (int) $params->get('linkdet');
$showiconcountry    = (int) $params->get('showiconcountry');
?>

<div class="jem_acymailing">
<?php if (!empty($list)) : ?>

<?php foreach ($list as $item) :

    // Titel / Venue escaped
    $title   = htmlspecialchars((string) ($item->title ?? ''), ENT_QUOTES, 'UTF-8');
    $venue   = htmlspecialchars((string) ($item->venue ?? ''), ENT_QUOTES, 'UTF-8');
    $country = htmlspecialchars((string) ($item->country ?? ''), ENT_QUOTES, 'UTF-8');

    // Leave date information with HTML
    $date    = (string) ($item->dateinfo ?? '');

    $eventLink = !empty($item->link) ? htmlspecialchars((string) $item->link, ENT_QUOTES, 'UTF-8') : '';
    $venueLink = !empty($item->venueurl) ? htmlspecialchars((string) $item->venueurl, ENT_QUOTES, 'UTF-8') : '';

    // Title / Location
    $mainText = '';
    $mainLink = '';

    if ($showtitloc === 0) {
        $mainText = $venue;
        if ($linkloc === 1) {
            $mainLink = $venueLink;
        }
    } else {
        $mainText = $title;
        if ($linkdet === 2) {
            $mainLink = $eventLink;
        }
    }

    // Date Link
    $dateLink = ($linkdet === 1) ? $eventLink : '';
?>

    <div style="margin-bottom:15px;">
        <p style="font-weight:bold; margin:0;">
            <?php
            if ($showiconcountry === 1 && $country !== '') {
                echo $country . ' ';
            }

            if ($mainLink !== '') {
                echo '<a href="' . $mainLink . '" style="text-decoration:none; color:#000;">' . $mainText . '</a>';
            } else {
                echo $mainText;
            }
            ?>
        </p>

        <p style="font-size:0.9em; color:#555; margin:2px 0 0 0;">
            <?php
            if ($dateLink !== '') {
                echo '<a href="' . $dateLink . '" style="text-decoration:none; color:#555;">' . $date . '</a>';
            } else {
                echo $date;
            }
            ?>
        </p>

        <div style="border-top:1px solid #ccc; margin:10px 0;"></div>
    </div>

<?php endforeach; ?>

<?php else : ?>
    <p><?php echo Text::_('MOD_JEM_NO_EVENTS'); ?></p>
<?php endif; ?>
</div>
