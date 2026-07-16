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
            <th><i class="fa-solid fa-calendar-days"></i><?php echo Text::_('COM_JEM_EVENT'); ?></th>
            <th><i class="fa-solid fa-calendar-check"></i><?php echo Text::_('COM_JEM_STARTDATE'); ?></th>
            <th><i class="fa-solid fa-hourglass-start"></i><?php echo Text::_('COM_JEM_STARTTIME'); ?></th>
            <th><i class="fa-solid fa-calendar-xmark"></i><?php echo Text::_('COM_JEM_ENDDATE'); ?></th>
            <th><i class="fa-solid fa-hourglass-end"></i><?php echo Text::_('COM_JEM_ENDTIME'); ?></th>
            <?php if ($showcategory) : ?>
                <th><i class="fa-solid fa-tag"></i><?php echo Text::_('COM_JEM_CATEGORY'); ?></th>
            <?php endif; ?>
            <th><i class="fa-solid fa-link"></i><?php echo Text::_('COM_JEM_LINK'); ?></th>
            </thead>
            <?php foreach ($list as $item) : ?>
                <tr>
                    <td>
                        <?php if($highlight_featured && $item->featured): ?>
                        <span class="event-title highlight_featured">
                        <?php else : ?>
                            <span class="event-title">
                        <?php endif; ?>

                                <?php if (($showiconcountry == 1) && !empty($item->country)) : ?>
                                    <?php $flagpath = $settings->flagicons_path . (str_ends_with($settings->flagicons_path, '/')?'':'/');
                                    $flagext = substr($flagpath, strrpos($flagpath,"-")+1,-1) ;
                                    $flagfile = Uri::getInstance()->base() . $flagpath . strtolower($item->country) . '.' . $flagext;
                                    echo '<img src="' . $flagfile . '" alt="' . $item->country . ' ' , Text::_('MOD_JEM_SHOW_FLAG_ICON') . '">' ?>
                                <?php endif; ?>
                                <?php if ($showtitle) : ?>
                                    <?php if ($linkdet == 2) : ?>
                                        <a href="<?php echo $item->link; ?>">
                                            <?php echo $item->title; ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo $item->title; ?>
                                    <?php endif; ?>
                                <?php elseif ($showvenue && !empty($item->venue)) : ?>
                                    <?php if ($linkloc == 1 && !empty($item->venueurl)) : ?>
                                        <a href="<?php echo $item->venueurl; ?>">
                                            <?php echo $item->venue; ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo $item->venue; ?>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <i class="fas fa-minus" title="<?php echo Text::_('MOD_JEM_NO_LINK'); ?>"></i>
                                <?php endif; ?>
                        </span>
                    </td>
                    <td>
                        <?php if($highlight_featured && $item->featured): ?>
							<span class="event-title highlight_featured">
                        <?php else : ?>
                            <i class="fas fa-minus" title="<?php echo Text::_('MOD_JEM_NO_LINK'); ?>"></i>
                        <?php endif; ?>
                        <?php echo $item->dates; ?>
                        </span>
                    </td>
                    <td>
                        <?php if($highlight_featured && $item->featured): ?>
                        <span class="event-title highlight_featured">
                        <?php else : ?>
                            <span class="event-title">
                        <?php endif; ?>
                        <?php echo $item->times; ?>
                        </span>
                    </td>
                    <td>
                        <?php if($highlight_featured && $item->featured): ?>
                        <span class="event-title highlight_featured">
                        <?php else : ?>
                            <span class="event-title">
                        <?php endif; ?>
                        <?php echo $item->enddates; ?>
                        </span>
                    </td>
                    <td>
                        <?php if($highlight_featured && $item->featured): ?>
                        <span class="event-title highlight_featured">
                        <?php else : ?>
                            <span class="event-title">
                        <?php endif; ?>
                        <?php echo $item->endtimes; ?>
                        </span>
                    </td>
                    <?php if ($showcategory) : ?>
                        <td><span class="event-category"><?php echo $item->catname; ?></span></td>
                    <?php endif; ?>
                    <td>
                        <?php if ($params->get('linkdet') == 1) : ?>
                            <a href="<?php echo $item->link; ?>"><div style="text-align: center;"><i class="far fa-eye"></i></div></a>
                        <?php else :
                            echo $item->dateinfo;
                        endif;
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php echo Text::_('MOD_JEM_NO_EVENTS'); ?></p>
    <?php endif; ?>
</div>
