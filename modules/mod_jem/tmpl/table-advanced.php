<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @subpackage JEM Module
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

$highlight_featured = $params->get('highlight_featured');
$showtitloc = $params->get('showtitloc');
$linkloc = $params->get('linkloc');
$linkdet = $params->get('linkdet');
$showiconcountry = $params->get('showiconcountry');
$settings = JemHelper::config();

HTMLHelper::_('stylesheet', 'modules/mod_jem/tmpl/mod_jem_table-advanced.css');

?>

<div class="jemmodulebasic<?php echo $params->get('moduleclass_sfx')?>" id="jemmodulebasic-tableadvanced">
    <?php if (count($list)): ?>
        <table class="jemmod">
            <thead><th>Event</th><th>Date start</th><th>Time start</th><th>Date End</th><th>Time End</th><th>Link</th></thead>
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
                            <?php if ($showtitloc == 0 && $linkloc == 1) : ?>
                                <a href="<?php echo $item->venueurl; ?>">
                                    <?php echo $item->text; ?>
                                </a>
                            <?php elseif ($showtitloc == 1 && $linkdet == 2) : ?>
                                <a href="<?php echo $item->link; ?>">
                                    <?php echo $item->text; ?>
                                </a>
                            <?php
                            else :
                                echo $item->text;
                            endif;
                            ?>
                        </span>
                    </td>
                    <td>
                        <?php if($highlight_featured && $item->featured): ?>
                            <span class="event-title highlight_featured">
                        <?php else : ?>
                            <span class="event-title">
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
        </table>
    <?php else : ?>
        <?php echo Text::_('MOD_JEM_NO_EVENTS'); ?>
    <?php endif; ?>
</div>