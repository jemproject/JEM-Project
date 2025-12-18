<?php
/**
 * @package    JEM
 * @subpackage JEM Module
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
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

$moduleClass = htmlspecialchars((string) $params->get('moduleclass_sfx'), ENT_QUOTES, 'UTF-8');
?>

<div class="jemmodulebasic<?php echo $moduleClass; ?>">
<?php if (!empty($list)) : ?>
    <div class="jemmod">

<?php foreach ($list as $item) :

    // Titel / Venue escaped
    $title   = htmlspecialchars((string) ($item->title ?? ''), ENT_QUOTES, 'UTF-8');
    $venue   = htmlspecialchars((string) ($item->venue ?? ''), ENT_QUOTES, 'UTF-8');
    $country = htmlspecialchars((string) ($item->country ?? ''), ENT_QUOTES, 'UTF-8');

    // Datuminfo mit HTML belassen
    $date    = (string) ($item->dateinfo ?? '');

    $eventLink = !empty($item->link) ? htmlspecialchars((string) $item->link, ENT_QUOTES, 'UTF-8') : '';
    $venueLink = !empty($item->venueurl) ? htmlspecialchars((string) $item->venueurl, ENT_QUOTES, 'UTF-8') : '';

    // CSS-Klasse für Titel
    $titleClass = 'event-title';
    if ($highlight_featured && !empty($item->featured)) {
        $titleClass .= ' highlight_featured';
    }

    // Titel / Location entscheiden
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

    // Datum-Link entscheiden
    $dateLink = ($linkdet === 1) ? $eventLink : '';
?>

        <article class="event-item event_id<?php echo (int) $item->eventid; ?>"
                 itemscope
                 itemtype="https://schema.org/Event">

            <span class="<?php echo $titleClass; ?>">
                <?php
                if ($showiconcountry === 1 && $country !== '') {
                    echo $country . ' ';
                }

                if ($mainLink !== '') {
                    echo '<a href="' . $mainLink . '">' . $mainText . '</a>';
                } else {
                    echo $mainText;
                }
                ?>
            </span>

            <br />

            <span class="<?php echo $titleClass; ?>" itemprop="startDate">
                <?php
                if ($dateLink !== '') {
                    echo '<a href="' . $dateLink . '">' . $date . '</a>';
                } else {
                    echo $date;
                }
                ?>
            </span>

            <hr class="jem-hr" />
        </article>

<?php endforeach; ?>

    </div>
<?php else : ?>
    <?php echo Text::_('MOD_JEM_NO_EVENTS'); ?>
<?php endif; ?>
</div>
