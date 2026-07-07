<?php
/**
 * @package    JEM
 * @subpackage JEM Banner Module
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

$imageWidthMax = (int) $params->get('imagewidthmax', 0);
$imageRatio    = preg_match('#^\d+\s*/\s*\d+$#', (string) $params->get('imageratio', '1 / 1')) ? (string) $params->get('imageratio', '1 / 1') : '1 / 1';
$noImageText   = Text::_('MOD_JEM_BANNER_NO_IMAGE');
$noImageText   = ($noImageText === 'MOD_JEM_BANNER_NO_IMAGE') ? 'No image' : $noImageText;
$showCategory  = ((int) $params->get('showcategory', 1) === 1) && !JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-nocats');
$showVenue     = ((int) $params->get('showvenue', 1) === 1) && !JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-novenue');

Factory::getApplication()->getDocument()->getWebAssetManager()->addInlineStyle('
    #jemmodulebanner_cards_places .jem-banner-card-image {
        aspect-ratio: ' . $imageRatio . ';
        ' . ($imageWidthMax > 0 ? 'max-height: ' . $imageWidthMax . 'px;' : '') . '
    }
');
?>

<div class="jemmodulebanner<?php echo $params->get('moduleclass_sfx'); ?>" id="jemmodulebanner_cards_places">
    <div class="jem-banner-cards-places">
        <?php if (count($list) > 0) : ?>
            <?php foreach ($list as $item) : ?>
                <?php
                $location = trim(implode(', ', array_filter(array($item->venue, $item->city))));
                $hasPlaces = ((int) $item->registra > 0) && ((int) $item->maxplaces > 0);
                ?>
                <article class="jem-banner-card-place event_id<?php echo (int) $item->eventid; ?>" itemscope itemtype="https://schema.org/Event">
                    <div class="jem-banner-card-image">
                        <?php if (!empty($item->eventlink)) : ?>
                            <a class="jem-banner-card-image-link" href="<?php echo $item->eventlink; ?>" aria-label="<?php echo $item->fulltitle; ?>">
                        <?php else : ?>
                            <div class="jem-banner-card-image-link">
                        <?php endif; ?>
                                <?php if (!empty($item->eventimageorig)) : ?>
                                    <img src="<?php echo $item->eventimageorig; ?>" alt="<?php echo $item->fulltitle; ?>" itemprop="image">
                                <?php else : ?>
                                    <span class="jem-banner-card-image-placeholder">
                                        <i class="far fa-image" aria-hidden="true"></i>
                                        <span><?php echo $noImageText; ?></span>
                                    </span>
                                <?php endif; ?>
                        <?php if (!empty($item->eventlink)) : ?>
                            </a>
                        <?php else : ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <section class="jem-banner-card-panel jem-banner-card-main">
                        <div class="jem-banner-card-title-row">
                            <span class="jem-banner-card-icon jem-banner-card-icon-primary" aria-hidden="true">
                                <i class="far fa-calendar-alt"></i>
                            </span>
                            <h3 class="jem-banner-card-title" itemprop="name">
                                <?php if (!empty($item->eventlink)) : ?>
                                    <a href="<?php echo $item->eventlink; ?>" itemprop="url"><?php echo $item->title; ?></a>
                                <?php else : ?>
                                    <?php echo $item->title; ?>
                                <?php endif; ?>
                            </h3>
                        </div>

                        <dl class="jem-banner-card-details">
                            <div>
                                <dt><i class="far fa-calendar-alt" aria-hidden="true"></i><span class="visually-hidden"><?php echo Text::_('COM_JEM_TABLE_DATE'); ?></span></dt>
                                <dd><?php echo $item->date; ?></dd>
                            </div>
                            <div>
                                <dt><i class="far fa-clock" aria-hidden="true"></i><span class="visually-hidden"><?php echo Text::_('COM_JEM_TIME'); ?></span></dt>
                                <dd><?php echo $item->time ?: Text::_('MOD_JEM_BANNER_ALL_DAY'); ?></dd>
                            </div>
                            <?php if ($showVenue && $location !== '') : ?>
                                <div itemprop="location" itemscope itemtype="https://schema.org/Place">
                                    <dt><i class="fas fa-map-marker-alt" aria-hidden="true"></i><span class="visually-hidden"><?php echo Text::_('COM_JEM_VENUE'); ?></span></dt>
                                    <dd itemprop="name"><?php echo $location; ?></dd>
                                </div>
                            <?php endif; ?>
                            <?php if ($showCategory && !empty($item->catname)) : ?>
                                <div>
                                    <dt><i class="fas fa-tag" aria-hidden="true"></i><span class="visually-hidden"><?php echo Text::_('COM_JEM_CATEGORY'); ?></span></dt>
                                    <dd><?php echo $item->catname; ?></dd>
                                </div>
                            <?php endif; ?>
                        </dl>

                        <?php echo $item->dateschema; ?>
                    </section>

                    <?php if ($hasPlaces) : ?>
                        <section class="jem-banner-card-panel jem-banner-card-places" aria-label="<?php echo Text::_('MOD_JEM_BANNER_AVAILABLE_PLACES'); ?>">
                            <div class="jem-banner-card-places-icon" aria-hidden="true">
                                <i class="fas fa-user-friends"></i>
                            </div>
                            <div class="jem-banner-card-places-body">
                                <div class="jem-banner-card-places-label"><?php echo Text::_('MOD_JEM_BANNER_AVAILABLE_PLACES'); ?></div>
                                <div class="jem-banner-card-places-count">
                                    <strong><?php echo (int) $item->availableplaces; ?></strong>
                                    <span>/ <?php echo (int) $item->maxplaces; ?></span>
                                </div>
                                <div class="jem-banner-card-progress" aria-hidden="true">
                                    <span style="width: <?php echo (int) $item->placespercent; ?>%;"></span>
                                </div>
                                <div class="jem-banner-card-places-left">
                                    <?php echo Text::sprintf('MOD_JEM_BANNER_PLACES_LEFT', (int) $item->availableplaces); ?>
                                </div>
                            </div>
                        </section>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="jem-no-events">
                <?php echo Text::_('MOD_JEM_BANNER_NO_EVENTS'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>
