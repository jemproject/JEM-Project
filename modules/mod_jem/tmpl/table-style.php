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

<div class="jemmodulebasic<?php echo $params->get('moduleclass_sfx')?>" id="jemmodulebasic-tablestyle">
    <?php if (count($list)): ?>
        <table class="jemmod" style="width: 100%; border-collapse: collapse;">
            <?php foreach ($list as $item) :
                // Determine if event is featured for bold styling
                $isFeatured = $highlight_featured && $item->featured;
                $boldStyle  = $isFeatured ? 'font-weight: bold;' : 'font-weight: normal;';
                ?>
                <tr class="event_id<?php echo $item->eventid; ?>" style="border-bottom: 1px solid #eee; <?php echo $boldStyle; ?>">

                    <td style="padding: 8px 4px; vertical-align: middle;">
                        <span class="event-title">
                            <?php if (($showiconcountry == 1) && !empty($item->country)) :
                                $flagfile = $baseUri . $flagPath . strtolower($item->country) . '.' . $flagExt;
                                echo '<img src="' . $flagfile . '" alt="' . $item->country . ' ' . Text::_('MOD_JEM_SHOW_FLAG_ICON') . '" style="max-width: 25px; margin-right: 5px; vertical-align: middle;">';
                            endif; ?>

                            <?php if ($showtitle) : ?>
                                <?php if ($linkdet == 2) : ?>
                                    <a href="<?php echo $item->link; ?>" style="font-weight: inherit;">
                                        <?php echo $item->title; ?>
                                    </a>
                                <?php else :
                                    echo $item->title;
                                endif; ?>
                            <?php endif; ?>
                        </span>
                    </td>

                    <td style="padding: 8px 4px; vertical-align: middle;">
                        <span class="event-date">
                            <?php if ($linkdet == 1) : ?>
                                <a href="<?php echo $item->link; ?>" style="font-weight: inherit;">
                                    <?php echo $item->dateinfo; ?>
                                </a>
                            <?php else :
                                echo $item->dateinfo;
                            endif; ?>
                        </span>
                    </td>

                    <td style="padding: 8px 4px; vertical-align: middle;">
                        <?php if ($showvenue) : ?>
                            <span class="event-venue" style="font-style: italic;">
                                <?php if ($linkloc == 1) : ?>
                                    <a href="<?php echo $item->venueurl; ?>" style="font-weight: inherit;">
                                        <?php echo $item->venue; ?>
                                    </a>
                                <?php else :
                                    echo $item->venue;
                                endif; ?>
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else : ?>
        <p><?php echo Text::_('MOD_JEM_NO_EVENTS'); ?></p>
    <?php endif; ?>
</div>