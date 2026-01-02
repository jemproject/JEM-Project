<?php
/**
 * @package    JEM
 * @subpackage JEM Banner Module
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

JemHelper::loadModuleStyleSheet('mod_jem_banner', 'mod_jem_banner_cards');

$app = Factory::getApplication();
$wa  = $app->getDocument()->getWebAssetManager();

$datemethod      = (int)$params->get('datemethod', 1);
$showcalendar    = (int)$params->get('showcalendar', 1);
$showflyer       = (int)$params->get('showflyer', 1);
$flyer_link_type = (int)$params->get('flyer_link_type', 0);
$imagewidthmax   = (int)$params->get('imagewidthmax', 0);

if ($flyer_link_type == 1) {
    echo JemOutput::lightbox();
    $modal = 'lightbox';
} elseif ($flyer_link_type == 0) {
    $modal = 'notmodal';
} else {
    $modal = '';
}

$uri = Uri::getInstance();

$banneralignment = "jem-vertical-banner";
if (JemHelper::jemStringContains($params->get('moduleclass_sfx'), "jem-horizontal")){
    $banneralignment = "jem-horizontal-banner";
}

$imagewidth = '100%';
$imagewidthstring = 'jem-imagewidth';
if (JemHelper::jemStringContains($params->get('moduleclass_sfx'), $imagewidthstring)) {
    $pageclass_sfx = $params->get('moduleclass_sfx');
    $imagewidthpos = strpos($pageclass_sfx, $imagewidthstring);
    $spacepos = strpos($pageclass_sfx, ' ', $imagewidthpos);
    if ($spacepos === false) {
        $spacepos = strlen($pageclass_sfx);
    }
    $startpos = $imagewidthpos + strlen($imagewidthstring);
    $endpos = $spacepos - $startpos;
    $imagewidth = substr($pageclass_sfx, $startpos, $endpos);
}

$imageheight = 'auto';
$imageheigthstring = 'jem-imageheight';
if (JemHelper::jemStringContains($params->get('moduleclass_sfx'), $imageheigthstring)) {
    $pageclass_sfx = $params->get('moduleclass_sfx');
    $imageheightpos = strpos($pageclass_sfx, $imageheigthstring);
    $spacepos = strpos($pageclass_sfx, ' ', $imageheightpos);
    if ($spacepos === false) {
        $spacepos = strlen($pageclass_sfx);
    }
    $startpos = $imageheightpos + strlen($imageheigthstring);
    $endpos = $spacepos - $startpos;
    $imageheight = substr($pageclass_sfx, $startpos, $endpos);
}

$document = Factory::getDocument();
$additionalCSS = '';
if (JemHelper::jemStringContains($params->get('moduleclass_sfx'), "jem-imagetop")) {
    $additionalCSS = 'order: -1;';
}

$widthStyle = $imagewidthmax ? 'width:' . $imagewidthmax . 'px' : 'max-width:' . $imagewidth;
$heightStyle = $imagewidthmax ? 'auto' : $imageheight;

$css = '
    #jemmodulebanner .jem-eventimg-banner {
        width: ' . $imagewidth . ';
        ' . $additionalCSS . '
    }
    #jemmodulebanner .jem-eventimg-banner img {
        ' . $widthStyle . ';
        height: ' . $heightStyle . ';
    }

    @media not print {
        @media only all and (max-width: 47.938rem) {
            #jemmodulebanner .jem-eventimg-banner {
            }
            #jemmodulebanner .jem-eventimg-banner img {
                   width: ' . $imagewidth . ';
                height: ' . $imageheight . ';
            }
        }
        
    @media (max-width: 768px) {
        .events-grid {
            grid-template-columns: 1fr;
        }
        
        .event-date-container {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.8rem;
        }
    }
';
$wa->addInlineStyle($css);
?>

<div class="jemmodulebanner<?php echo $params->get('moduleclass_sfx')?>" id="jemmodulebanner_cards">
    <div class="events-grid">
        <?php if (count($list) > 0) : ?>
            <?php foreach ($list as $item) : ?>
                <div class="event-card event_id<?php echo $item->eventid; ?>" itemprop="event" itemscope itemtype="https://schema.org/Event">
                    <?php if (($showflyer == 1) && !empty($item->eventimage)) : ?>
                        <div class="event-media">
                            <img src="<?php echo $item->eventimageorig; ?>" alt="<?php echo $item->title; ?>">
                            <div class="event-badge"><?php echo $item->catname; ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="event-content">
                        <h3 class="event-title">
                        <?php echo $item->eventlink ? '<a href="'.$item->eventlink.'" style="text-decoration:none;color:inherit;">'.$item->title.'</a>' : $item->title; ?>
                        </h3>

                        <div class="event-date-container">
                            <div class="date-box" style="--event-specific-color: <?php echo (isset($item->color) ? $item->color : $item->colorclass); ?>;">
                                <div class="date-day"><?php echo $item->startdate['day']; ?></div>
                                <div class="date-month"><?php echo substr($item->startdate['month'], 0, 3); ?></div>
                            </div>
                            <div class="date-time">
                                <div class="date-weekday"><?php echo $item->startdate['weekday']; ?></div>
                                <div class="date-hours"><?php echo $item->time ?: Text::_('MOD_JEM_BANNER_ALL_DAY'); ?></div>
                            </div>
                        </div>

                        <div class="event-meta">
                            <?php if (($params->get('showvenue', 1) == 1) && (!empty($item->venue))) : ?>
                                <div class="meta-item">
                                    <div class="meta-icon" style="--event-specific-color: <?php echo (isset($item->color) ? $item->color : $item->colorclass); ?>;"><i class="fas fa-map-marker-alt"></i></div>
                                    <div class="meta-text"><?php echo $item->venue; ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if (($params->get('showcategory', 1) == 1) && !empty($item->catname)) : ?>
                                <div class="meta-item">
                                    <div class="meta-icon" style="--event-specific-color: <?php echo (isset($item->color) ? $item->color : $item->colorclass); ?>;"><i class="fas fa-tag"></i></div>
                                    <div class="meta-text"><?php echo $item->catname; ?></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($params->get('showdesc', 1) == 1) : ?>
                            <div class="event-description">
                                <?php echo strip_tags(substr($item->eventdescription, 0, 150)); ?>...
                            </div>
                        <?php endif; ?>

                        <div class="event-actions">
                            <?php if (isset($item->link) && ($item->readmore != 0 || $params->get('readmore'))) : ?>
                                <a href="<?php echo $item->link; ?>" class="btn btn-primary"><i class="far fa-calendar-plus"></i><?php echo Text::_('MOD_JEM_BANNER_READMORE'); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="jem-no-events">
                <?php echo Text::_('MOD_JEM_BANNER_NO_EVENTS'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>