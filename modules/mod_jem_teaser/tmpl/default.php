<?php
/**
 * @package    JEM
 * @subpackage JEM Teaser Module
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;

$showcalendar    = (int)$params->get('showcalendar', 1);

if ($params->get('use_modal', 0)) {
    echo JemOutput::lightbox();
    $modal = 'lightbox';
} else {
    $modal = 'notmodal';
}
?>

<div class="jemmoduleteaser<?php echo $params->get('moduleclass_sfx'); ?>" id="jemmoduleteaser">
    <div class="eventset" >
        <?php if (count($list)) : ?>
            <?php
            $titletag = '<h2 class="event-title" itemprop="name">';
            $titleendtag = '</h2>';
            if ($module->showtitle) {
                $titletag = '<h3 class="event-title" itemprop="name">';
                $titleendtag = '</h3>';
            }
            ?>
            <?php foreach ($list as $item) : ?>
                <?php
                // has user access
                $eventaccess = '';
                if (!$item->user_has_access_venue) {
                    // show a closed lock icon
                    $eventaccess = ' <span class="icon-lock jem-lockicon" aria-hidden="true"></span>';
                }
                ?>
                <div class="event_id<?php echo $item->eventid; ?>" itemprop="event" itemscope itemtype="https://schema.org/Event">
                    <?php echo $titletag; ?>
                    <?php if ($item->eventlink) : ?>
                        <a href="<?php echo $item->eventlink; ?>" title="<?php echo $item->fulltitle; ?>" itemprop="url"><?php echo $item->title; ?></a>
                    <?php else : ?>
                        <?php echo $item->title; ?>
                    <?php endif; ?>
                    <?php echo $eventaccess; ?>
                    <?php echo $titleendtag; ?>

                    <table>
                        <tr>
                            <td class="event-calendar">
                                <?php if ($showcalendar == 1) : ?>
                                <?php if ($item->colorclass === "category" || $item->colorclass === "alpha") : ?>
                                <div class="calendar<?php echo '-' . $item->colorclass; ?> jem-teaser-calendar" title="<?php echo strip_tags($item->dateinfo); ?>">
                                    <div class="color-bar" style="background-color:<?php echo !empty($item->color) ? $item->color : 'rgb(128,128,128)'; ?>"></div>
                                    <div class="lower-background"></div>
                                    <div class="background-image"></div>
                                    <?php elseif ($item->colorclass === "venue") : ?>
                                    <div class="calendar<?php echo '-' . $item->colorclass; ?> jem-teaser-calendar" title="<?php echo strip_tags($item->dateinfo); ?>">
                                        <div class="color-bar" style="background-color:<?php echo !empty($item->venuecolor) ? $item->venuecolor : (!empty($item->color) ? $item->color : 'rgb(128,128,128)'); ?>"></div>
                                        <div class="lower-background"></div>
                                        <div class="background-image"></div>
                                        <?php else : ?>
                                        <div class="calendar<?php echo '-' . $item->colorclass; ?> jem-teaser-calendar" title="<?php echo strip_tags($item->dateinfo); ?>">
                                            <?php endif; ?>
                                            <div class="monthteaser<?php echo isset($item->color_is_dark) ? ($item->color_is_dark === 1 ? ' monthcolor-light">' : ($item->color_is_dark === 0 ? ' monthcolor-dark">' : '">')) : '">'; echo $item->startdate['month']; ?></div>
                                        <div class="dayteaser">
                                            <?php echo $item->startdate['weekday']; ?>
                                        </div>
                                        <div class="daynumteaser"><?php echo $item->startdate['day']; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                            </td>
                            <td class="event-info">
                                <div class="teaser-jem"><div>
                                        <?php if ($item->showimageevent) : ?>
                                            <?php if (strpos($item->eventimage, '/media/com_jem/images/blank.webp') === false) : ?>
                                                <a href="<?php echo $item->eventimageorig; ?>" class="teaser-flyerimage" rel="<?php echo $modal; ?>" data-lightbox="teaser-flyerimage-<?php echo $item->eventid; ?>" title="<?php echo Text::_(
                                                    'COM_JEM_CLICK_TO_ENLARGE'
                                                ); ?>" data-title="<?php echo Text::_('COM_JEM_EVENT') . ': ' . $item->fulltitle; ?>"><img class="float_right image-preview" style="height:auto" src="<?php echo $item->eventimage; ?>" alt="<?php echo $item->title; ?>" itemprop="image" /></a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if ($item->showimagevenue) : ?>
                                            <?php if (strpos($item->venueimage, '/media/com_jem/images/blank.webp') === false) : ?>
                                                <?php if (!empty($item->venueimage)) : ?>
                                                    <a href="<?php echo $item->venueimageorig; ?>" class="teaser-flyerimage" rel="<?php echo $modal; ?>" data-lightbox="teaser-flyerimage-<?php echo $item->eventid; ?>" title="<?php echo Text::_(
                                                        'COM_JEM_CLICK_TO_ENLARGE'
                                                    ); ?>" data-title="<?php echo Text::_('COM_JEM_VENUE') . ': ' . $item->venue; ?>"><img class="float_right image-preview" style="height:auto" src="<?php echo $item->venueimage; ?>" alt="<?php echo $item->venue; ?>" /></a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <?php if ($item->showdescriptionevent) : ?>
                                            <div class="jem-description-teaser" itemprop="description">
                                                <?php echo $item->eventdescription;
                                                if (isset($item->link) && $item->readmore != 0 && $params->get('readmore')):
                                                    echo '<a class="readmore" style="padding-left: 10px;" href="' . $item->link . '">' . $item->linkText . '</a>';
                                                endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td class="event-datetime">
                                <?php if ($item->date && $params->get('datemethod', 1) == 2) : ?>
                                    <div class="date" title="<?php echo strip_tags($item->dateinfo); ?>">
                                        <small><?php echo $item->date; ?></small>
                                    </div>
                                <?php endif; ?>
                                <?php if ($item->time && $params->get('datemethod', 1) == 1) : ?>
                                    <div class="time" title="<?php echo strip_tags($item->time); ?>">
                                        <small><?php echo $item->time; ?></small>
                                    </div>
                                <?php endif;
                                echo $item->dateschema; ?>
                                <div itemprop="location" itemscope itemtype="https://schema.org/Place" style="display:none;">
                                    <meta itemprop="name" content="<?php echo $item->venue; ?>" />
                                    <div itemprop="address" itemscope itemtype="https://schema.org/PostalAddress" style="display:none;">
                                        <meta itemprop="streetAddress" content="<?php echo $item->street; ?>" />
                                        <meta itemprop="addressLocality" content="<?php echo $item->city; ?>" />
                                        <meta itemprop="addressRegion" content="<?php echo $item->state; ?>" />
                                        <meta itemprop="postalCode" content="<?php echo $item->postalCode; ?>" />
                                    </div>
                                </div>
                            </td>
                            <?php if ($item->user_has_access_venue) : ?>
                                <td class="event-vencat" style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
                                    <?php if (!empty($item->venue)) : ?>
                                        <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-novenue')) : ?>
                                            <div class="venue-title" title="<?php echo Text::_('COM_JEM_TABLE_LOCATION').': '.strip_tags($item->venue); ?>">
                                                <?php echo $item->venuename; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-nocats')) : ?>
                                        <div class="category" title="<?php echo Text::_('COM_JEM_TABLE_CATEGORY').': '.strip_tags($item->catname); ?>">
                                            <?php echo $item->catname; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <?php echo Text::_('MOD_JEM_TEASER_NO_EVENTS'); ?>
        <?php endif; ?>
    </div>
</div>