<?php
/**
 * @package    JEM
 * @subpackage JEM Wide Module
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;


use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

?>

<div class="jemmodulewide<?= $params->get('moduleclass_sfx')?>" id="jemmodulewide">

    <?php if (count($list)) : ?>
        <table class="eventset">

            <colgroup>
                <col style="width:30%" class="jemmodw_col_title" />
                <col style="width:20%" class="jemmodw_col_category" />
                <col style="width:20%" class="jemmodw_col_venue" />
                <col style="width:15%" class="jemmodw_col_eventimage" />
                <col style="width:15%" class="jemmodw_col_venueimage" />
            </colgroup>

            <?php foreach ($list as $item) : ?>
                <tr class="event_id<?= $item->eventid; ?>" itemprop="event" itemscope itemtype="https://schema.org/Event">
                    <td>
                        <span itemprop="name" class="event-title <?= ($highlight_featured && $item->featured) ? 'highlight_featured' : '' ?>">

                        <?php if ($item->eventlink) : ?>
                            <a href="<?= $item->eventlink; ?>" itemprop="url" title="<?= $item->fulltitle; ?>"><?= $item->title; ?></a></span>
                        <?php else : ?>
                            <?= $item->title; ?></span>
                        <?php endif; ?>
                        <br>
                        <span class="date" title="<?= strip_tags($item->dateinfo); ?>"><?= $item->date; ?></span>
                        <?php if ($item->time && $params->get('datemethod', 1) == 1) :
                            ?>
                            <span class="time" title="<?= strip_tags($item->dateinfo); ?>"><?= $item->time; ?></span>
                        <?php endif;
                        echo $item->dateschema; ?>
                    </td>

                    <td>
                        <?php if (!empty($item->catname)) : ?>
                            <span class="category"><?= $item->catname; ?></span>
                        <?php endif; ?>
                    </td>

                    <td itemprop="location" itemscope itemtype="https://schema.org/Place">
                        <?php if (!empty($item->venue)) : ?>
                            <?php if ($item->venuelink) : ?>
                                <span class="venue-title" itemprop="name"><a href="<?= $item->venuelink; ?>" title="<?= $item->venue; ?>" itemprop="url"><?= $item->venue; ?></a></span>
                            <?php else : ?>
                                <span class="venue-title" itemprop="name"><?= $item->venue; ?></span>
                            <?php endif; ?>
                            <div class="address" itemprop="address" itemscope itemtype="https://schema.org/PostalAddress" style="display:none;">
                                <meta itemprop="streetAddress" content="<?= $item->street; ?>" />
                                <meta itemprop="addressLocality" content="<?= $item->city; ?>" />
                                <meta itemprop="addressRegion" content="<?= $item->state; ?>" />
                                <meta itemprop="postalCode" content="<?= $item->postalCode; ?>" />
                            </div>
                        <?php endif; ?>
                    </td>

                    <td class="event-image-cell">
                        <?php if ($params->get('use_modal')) : ?>
                    <?php if ($item->eventimageorig) {
                        $image = $item->eventimageorig;
                        $document = Factory::getDocument();
                        $document->addStyleSheet(Uri::base() .'media/com_jem/css/lightbox.min.css');
                        $document->addScript(Uri::base() . 'media/com_jem/js/lightbox.min.js');
                        echo '<script>lightbox.option({
                            \'showImageNumberLabel\': false,
                            })
                            </script>';
                    } else {
                        $image = '';
                    } ?>

                        <a href="<?= $image; ?>" class="flyermodal" rel="lightbox" data-lightbox="wide-flyerimage-<?= $item->eventid ?>"  data-title="<?= Text::_('COM_JEM_EVENT') .': ' . $item->title; ?>">
                            <?php endif; ?>
                            <img src="<?= $item->eventimage; ?>" alt="<?= $item->title; ?>" class="image-preview" title="<?= Text::_('COM_JEM_CLICK_TO_ENLARGE'); ?>" itemprop="image" />
                            <?php if ($params->get('use_modal')) : ?>
                        </a>
                    <?php endif; ?>
                    </td>

                    <td class="event-image-cell">
                        <?php if ($params->get('use_modal')) : ?>
                        <a href="<?= $item->venueimageorig; ?>" class="flyermodal" rel="lightbox" data-lightbox="wide-flyerimage-<?= $item->eventid ?>" title="<?= $item->venue; ?>" data-title="<?= Text::_('COM_JEM_VENUE') .': ' . $item->venue; ?>">
                            <?php endif; ?>
                            <img src="<?= $item->venueimage; ?>" alt="<?= $item->venue; ?>" class="image-preview" title="<?= Text::_('COM_JEM_CLICK_TO_ENLARGE'); ?>" />
                            <?php if ($params->get('use_modal')) : ?>
                        </a>
                    <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else : ?>
        <?= Text::_('MOD_JEM_WIDE_NO_EVENTS'); ?>
    <?php endif; ?>
</div>
