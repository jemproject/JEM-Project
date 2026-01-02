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

JemHelper::loadModuleStyleSheet('mod_jem');
$highlight_featured = $params->get('highlight_featured');
$showtitloc = $params->get('showtitloc');
$linkloc = $params->get('linkloc');
$linkdet = $params->get('linkdet');
$showiconcountry = $params->get('showiconcountry');
$settings = JemHelper::config();
?>

<div class="jemmodulebasic<?php echo $params->get('moduleclass_sfx')?>" id="jemmodulebasic">
<?php if (count($list)): ?>
    <ul class="jemmod">
        <?php foreach ($list as $item) : ?>
        <li class="event_id<?php echo $item->eventid; ?>" itemprop="event" itemscope itemtype="https://schema.org/Event">
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
              <?php echo $item->venue; ?>
                </a>
                    <?php elseif ($showtitloc == 1 && $linkdet == 2) : ?>
            <a href="<?php echo $item->link; ?>" title="<?php echo strip_tags($item->title); ?>">
              <?php echo $item->title; ?>
                </a>
          <?php elseif ($showtitloc == 1 && $linkdet == 1) :
              echo $item->title;

          elseif ($showtitloc == 0 && $linkdet == 1) :
              echo $item->venue;
        endif; ?>

            </span>
            <br>
            <?php if($highlight_featured && $item->featured): ?>
                <span class="event-title highlight_featured">
            <?php else : ?>
                <span class="event-title">
            <?php endif; ?>
            <?php if ($linkdet == 1) : ?>
        <a href="<?php echo $item->link; ?>" title="<?php echo strip_tags($item->dateinfo); ?>">
                <?php echo $item->dateinfo; ?>
            </a>
            <?php else :
                echo $item->dateinfo;
        endif; ?>
            </span>
        <?php echo $item->dateschema; ?>
        <meta itemprop="name" content="<?php echo $item->title; ?>" />
       <div itemprop="location" itemscope itemtype="https://schema.org/Place" style="display:none;">
           <meta itemprop="name" content="<?php echo $item->venue; ?>" />
           <div itemprop="address" itemscope itemtype="https://schema.org/PostalAddress" style="display:none;">
            <meta itemprop="streetAddress" content="<?php echo $item->street; ?>" />
            <meta itemprop="addressLocality" content="<?php echo $item->city; ?>" />
            <meta itemprop="addressRegion" content="<?php echo $item->state; ?>" />
            <meta itemprop="postalCode" content="<?php echo $item->postalCode; ?>" />
        </div>
        </div>

        </li>
        <?php endforeach; ?>
    </ul>
<?php else : ?>
    <?php echo Text::_('MOD_JEM_NO_EVENTS'); ?>
<?php endif; ?>
</div>