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

JemHelper::loadModuleStyleSheet('mod_jem_banner', 'mod_jem_banner_table-advanced');

$app = Factory::getApplication();
$wa  = $app->getDocument()->getWebAssetManager();

$datemethod = (int)$params->get('datemethod', 1);
$showcalendar = (int)$params->get('showcalendar', 1);
$showflyer = (int)$params->get('showflyer', 1);
$flyer_link_type = (int)$params->get('flyer_link_type', 0);
$imagewidthmax = (int)$params->get('imagewidthmax', 0);

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
if (JemHelper::jemStringContains($params->get('moduleclass_sfx'), "jem-horizontal")) {
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
    }';
$wa->addInlineStyle($css);
?>

<div class="jemmodulebanner<?php echo $params->get('moduleclass_sfx')?>" id="jemmodulebanner">
    <div class="events-grid">
        <?php if (count($list) > 0) : ?>
            <?php foreach ($list as $item) : ?>
            <div class="event-card" itemprop="event" itemscope itemtype="https://schema.org/Event">
                <div class="event-header">
                    <?php if ($showcalendar == 1) : ?>
                    <div class="event-date">
                        <div class="event-month"><?php echo $item->startdate['month']; ?></div>
                        <div class="event-day"><?php echo $item->startdate['day']; ?></div>
                        <div class="event-weekday"><?php echo $item->startdate['weekday']; ?></div>
                        <?php if ($item->time && $datemethod == 1) : ?>
                        <div class="event-time"><?php echo $item->time; ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <h3 class="event-title" itemprop="name">
                        <?php echo $item->eventlink ? '<a href="'.$item->eventlink.'" title="'.$item->fulltitle.'" itemprop="url">'.$item->title.'</a>' : $item->title; ?>
                    </h3>
                </div>

                <div class="event-body">
                    <div class="event-meta">
                        <?php if ($showcalendar == 0) : ?>
                            <?php if ($item->date && $datemethod == 2) :?>
                                <div class="event-meta-item">
                                    <i class="icon-calendar"></i>
                                    <span><?php echo $item->date; ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if ($item->date && $datemethod == 1) :?>
                                <div class="event-meta-item">
                                    <i class="icon-calendar"></i>
                                    <span><?php echo $item->date; ?></span>
                                </div>
                                <?php if ($item->time && $datemethod == 1) :?>
                                <div class="event-meta-item">
                                    <i class="icon-clock"></i>
                                    <span><?php echo $item->time; ?></span>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (($params->get('showvenue', 1) == 1) && (!empty($item->venue))) :?>
                            <div class="event-meta-item">
                                <i class="icon-location"></i>
                                <span><?php echo $item->venuelink ? '<a href="'.$item->venuelink.'">'.$item->venue.'</a>' : $item->venue; ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if (($params->get('showcategory', 1) == 1) && !empty($item->catname)) :?>
                            <div class="event-meta-item">
                                <i class="icon-tag"></i>
                                <span><?php echo $item->catname; ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (($showflyer == 1) && !empty($item->eventimage)) : ?>
                    <div class="event-image">
                        <a href="<?php echo ($flyer_link_type == 2) ? $item->eventlink : $item->eventimageorig; ?>" class="flyermodal" rel="<?php echo $modal;?>"
                           title="<?php echo ($flyer_link_type == 2) ? $item->fulltitle : Text::_('COM_JEM_CLICK_TO_ENLARGE'); ?>">
                            <img src="<?php echo $item->eventimageorig; ?>" alt="<?php echo $item->title; ?>" itemprop="image" />
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if ($params->get('showdesc', 1) == 1) :?>
                    <div class="event-description" itemprop="description">
                        <?php echo $item->eventdescription; ?>
                        <?php if (isset($item->link) && $item->readmore != 0 && $params->get('readmore')) : ?>
                        <a href="<?php echo $item->link ?>" class="read-more"><?php echo Text::_('COM_JEM_EVENT_READ_MORE_TITLE'); ?></a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="no-events"><?php echo Text::_('MOD_JEM_BANNER_NO_EVENTS'); ?></div>
        <?php endif; ?>
    </div>
</div>