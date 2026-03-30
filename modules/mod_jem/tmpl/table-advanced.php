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
$showtitle          = $params->get('showtitle');
$showvenue          = $params->get('showvenue');
$linkloc            = $params->get('linkloc');
$linkdet            = $params->get('linkdet');
$showiconcountry    = $params->get('showiconcountry');
$settings           = JemHelper::config();
$baseUri            = Uri::getInstance()->base();

// Prepare flag resources
$flagPathRaw = $settings->flagicons_path;
$flagPath    = $flagPathRaw . (str_ends_with($flagPathRaw, '/') ? '' : '/');
$flagExt     = substr($flagPath, strrpos($flagPath, "-") + 1, -1);
?>

<div class="jemmodulebasic<?php echo $params->get('moduleclass_sfx')?>" id="jemmodulebasic-tableadvanced">
    <?php if (count($list)): ?>
        <table class="jemmod" style="width: 100%; border-collapse: collapse;">
            <thead>
            <tr>
                <th style="text-align: left;"><i class="fa-solid fa-calendar-days"></i> <?php echo Text::_('COM_JEM_EVENT'); ?></th>
                <th><i class="fa-solid fa-calendar-check"></i> <?php echo Text::_('COM_JEM_STARTDATE'); ?></th>
                <th><i class="fa-solid fa-hourglass-start"></i> <?php echo Text::_('COM_JEM_STARTTIME'); ?></th>
                <th><i class="fa-solid fa-calendar-xmark"></i> <?php echo Text::_('COM_JEM_ENDDATE'); ?></th>
                <th><i class="fa-solid fa-hourglass-end"></i> <?php echo Text::_('COM_JEM_ENDTIME'); ?></th>
                <th><i class="fa-solid fa-location-dot"></i> <?php echo Text::_('COM_JEM_VENUE'); ?></th>
                <th><i class="fa-solid fa-link"></i> <?php echo Text::_('COM_JEM_LINK'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($list as $item) :
                $isFeatured = $highlight_featured && $item->featured;
                $boldStyle  = $isFeatured ? 'font-weight: bold;' : 'font-weight: normal;';
                ?>
                <tr class="event_id<?php echo $item->eventid; ?>" style="border-bottom: 1px solid #eee; <?php echo $boldStyle; ?>">

                    <td style="padding: 8px; text-align: left; vertical-align: middle;">
                        <?php if (($showiconcountry == 1) && !empty($item->country)) :
                            $flagfile = $baseUri . $flagPath . strtolower($item->country) . '.' . $flagExt;
                            echo '<img src="' . $flagfile . '" alt="' . $item->country . '" style="max-width: 30px; height: auto; margin-right: 8px; vertical-align: middle; display: inline-block;">';
                        endif; ?>

                        <span class="event-title">
                            <?php if ($linkdet == 2) : ?>
                                <a href="<?php echo $item->link; ?>" style="font-weight: inherit;"><?php echo $item->title; ?></a>
                            <?php else : ?>
                                <?php echo $item->title; ?>
                            <?php endif; ?>
                        </span>
                    </td>

                    <td style="padding: 8px 4px; text-align: center;"><?php echo $item->dates; ?></td>
                    <td style="padding: 8px 4px; text-align: center;"><?php echo $item->times; ?></td>
                    <td style="padding: 8px 4px; text-align: center;"><?php echo $item->enddates; ?></td>
                    <td style="padding: 8px 4px; text-align: center;"><?php echo $item->endtimes; ?></td>

                    <td style="padding: 8px 4px; text-align: center; font-style: italic;">
                        <?php if ($showvenue) : ?>
                            <?php if ($linkloc == 1) : ?>
                                <a href="<?php echo $item->venueurl; ?>" style="font-weight: inherit;"><?php echo $item->venue; ?></a>
                            <?php else : ?>
                                <?php echo $item->venue; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>

                    <td style="padding: 8px 4px; text-align: center;">
                        <?php if ($linkdet == 1 || $linkdet == 2) : ?>
                            <a href="<?php echo $item->link; ?>" title="<?php echo Text::_('COM_JEM_SHOW_DETAILS'); ?>">
                                <i class="far fa-eye"></i>
                            </a>
                        <?php else : ?>
                            <?php echo $item->dateinfo; ?>
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