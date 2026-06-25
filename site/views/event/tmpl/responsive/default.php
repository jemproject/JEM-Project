<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Date\Date;

require_once JPATH_SITE . '/components/com_jem/classes/customfields.class.php';

HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers');

// Create shortcuts to some parameters.
$params      = $this->item->params;
$images      = json_decode($this->item->datimage);
$attribs     = json_decode($this->item->attribs);
$user        = JemFactory::getUser();
$jemsettings = JemHelper::config();
$app         = Factory::getApplication();
$document    = $app->getDocument();
$uri         = Uri::getInstance();

$eventStatusOptions = array(
    'scheduled'    => array('label' => 'COM_JEM_EVENT_STATUS_SCHEDULED', 'class' => 'jem-event-state-badge--scheduled', 'schema' => 'https://schema.org/EventScheduled'),
    'cancelled'    => array('label' => 'COM_JEM_EVENT_STATUS_CANCELLED', 'class' => 'jem-event-state-badge--cancelled', 'schema' => 'https://schema.org/EventCancelled'),
    'postponed'    => array('label' => 'COM_JEM_EVENT_STATUS_POSTPONED', 'class' => 'jem-event-state-badge--postponed', 'schema' => 'https://schema.org/EventPostponed'),
    'rescheduled'  => array('label' => 'COM_JEM_EVENT_STATUS_RESCHEDULED', 'class' => 'jem-event-state-badge--rescheduled', 'schema' => 'https://schema.org/EventRescheduled'),
    'moved_online' => array('label' => 'COM_JEM_EVENT_STATUS_MOVED_ONLINE', 'class' => 'jem-event-state-badge--moved-online', 'schema' => 'https://schema.org/EventMovedOnline'),
);
$ticketAvailabilityOptions = array(
    'instock'  => array('label' => 'COM_JEM_EVENT_AVAILABILITY_INSTOCK', 'class' => 'jem-event-state-badge--available', 'schema' => 'https://schema.org/InStock'),
    'preorder' => array('label' => 'COM_JEM_EVENT_AVAILABILITY_PREORDER', 'class' => 'jem-event-state-badge--preorder', 'schema' => 'https://schema.org/PreOrder'),
    'soldout'  => array('label' => 'COM_JEM_EVENT_AVAILABILITY_SOLDOUT', 'class' => 'jem-event-state-badge--soldout', 'schema' => 'https://schema.org/SoldOut'),
    'waitinglist' => array('label' => 'COM_JEM_EVENT_AVAILABILITY_WAITINGLIST', 'class' => 'jem-event-state-badge--waitinglist', 'schema' => 'https://schema.org/SoldOut'),
);
$eventStatus = !empty($this->item->event_status) && isset($eventStatusOptions[$this->item->event_status]) ? $this->item->event_status : 'scheduled';
$eventStatusOption = $eventStatusOptions[$eventStatus];
$eventStatusText = Text::_($eventStatusOption['label']);
$showEventStatusBadge = $eventStatus !== 'scheduled';
$ticketAvailability = JemOutput::getEffectiveTicketAvailability($this->item);
$ticketAvailabilityOption = $ticketAvailabilityOptions[$ticketAvailability];
$ticketAvailabilityText = Text::_($ticketAvailabilityOption['label']);
$showTicketAvailabilityText = (bool) $params->get('event_show_availability', 0);
$showTicketAvailabilityBadge = $showTicketAvailabilityText && $ticketAvailability !== 'instock';
$eventImageRibbonText = '';
$eventImageRibbonClass = '';
if ($showEventStatusBadge) {
    $eventImageRibbonText = $eventStatusText;
    $eventImageRibbonClass = $eventStatusOption['class'];
} elseif ($showTicketAvailabilityBadge) {
    $eventImageRibbonText = $ticketAvailabilityText;
    $eventImageRibbonClass = $ticketAvailabilityOption['class'];
}

// Add expiration date, if old events will be archived or removed
if ($jemsettings->oldevent > 0) {
    $enddate = strtotime($this->item->enddates?:($this->item->dates?:date("Y-m-d")));
    $expDate = date("D, d M Y H:i:s", strtotime('+1 day', $enddate));
    $document->addCustomTag('<meta http-equiv="expires" content="' . $expDate . '"/>');
}

$eventCustomFieldsPosition = (string) $params->get('event_custom_fields_position', $this->settings->get('event_custom_fields_position', 'details'));
if (!in_array($eventCustomFieldsPosition, array('details', 'before_description', 'after_description', 'after_links'), true)) {
    $eventCustomFieldsPosition = 'details';
}
$venueCustomFieldsPosition = (string) $this->settings->get('global_venue_custom_fields_position', 'details');
if (!in_array($venueCustomFieldsPosition, array('details', 'before_description', 'after_description', 'after_links'), true)) {
    $venueCustomFieldsPosition = 'details';
}
$venueLayout = (string) $params->get('event_venue_layout', '');
if ($venueLayout === '') {
    $venueLayout = (string) $this->settings->get('event_venue_layout', 'details');
}
if (!in_array($venueLayout, array('details', 'compact'), true)) {
    $venueLayout = 'details';
}
$eventLayout = (string) $params->get('event_details_layout', '');
if ($eventLayout === '') {
    $eventLayout = (string) $this->settings->get('event_details_layout', 'details');
}
if (!in_array($eventLayout, array('details', 'compact'), true)) {
    $eventLayout = 'details';
}
$layoutOverride = $app->input->getCmd('jem_layout', '');
if (in_array($layoutOverride, array('details', 'compact'), true)) {
    $eventLayout = $layoutOverride;
    $venueLayout = $layoutOverride;
}
$eventLayoutOverride = $app->input->getCmd('jem_event_layout', '');
if (in_array($eventLayoutOverride, array('details', 'compact'), true)) {
    $eventLayout = $eventLayoutOverride;
}
$venueLayoutOverride = $app->input->getCmd('jem_venue_layout', '');
if (in_array($venueLayoutOverride, array('details', 'compact'), true)) {
    $venueLayout = $venueLayoutOverride;
}
$layoutToggleTarget = ($eventLayout === 'compact' && $venueLayout === 'compact') ? 'details' : 'compact';
$layoutToggleUri = clone $uri;
$layoutToggleUri->setVar('jem_layout', $layoutToggleTarget);
$layoutToggleUrl = Route::_($layoutToggleUri->toString());
$layoutToggleText = $layoutToggleTarget === 'details' ? Text::_('COM_JEM_SHOW_FULL_VIEW') : Text::_('COM_JEM_SHOW_COMPACT_VIEW');
$layoutToggleIcon = $layoutToggleTarget === 'details' ? 'fa-expand' : 'fa-compress';
$detailsLayoutUri = clone $uri;
$detailsLayoutUri->setVar('jem_layout', 'details');
$detailsLayoutUrl = Route::_($detailsLayoutUri->toString());
$renderCompactReadmore = function ($url) {
    return '<p class="readmore"><a href="' . $this->escape($url) . '">'
        . Text::_('COM_JEM_EVENT_READ_MORE_TITLE') . '</a></p>';
};
$splitReadmoreText = function ($text) {
    $text = (string) $text;
    $pattern = '~(?:<hr\s+id=("|\')system-readmore\1\s*/?>|&lt;hr\s+id=(?:&quot;|&#039;|"|\')system-readmore(?:&quot;|&#039;|"|\')\s*/?&gt;)~i';
    $parts = preg_split($pattern, $text, 2);

    return (object) array(
        'intro' => trim($parts[0] ?? ''),
        'full'  => trim($parts[1] ?? ''),
    );
};
$eventCustomFieldsRows = JemCustomFields::renderDetailRows('event', $this->item, 'COM_JEM_EVENT_CUSTOM_FIELD', 'jem-custom', true);
$renderEventCustomFieldsBlock = function () use ($eventCustomFieldsRows) {
    if ($eventCustomFieldsRows === '') {
        return '';
    }

    return '<div class="jem-custom-fields jem-event-custom-fields">'
        . '<dl class="jem-dl">' . $eventCustomFieldsRows . '</dl>'
        . '</div>';
};
$eventVenueCustomFieldsRows = '';
foreach (JemCustomFields::getOrderedFields('venue', 'detail') as $fieldName) {
    $cr = (int) substr($fieldName, 6);
    $currentRow = JemCustomFields::renderValue('venue', $fieldName, $this->item->{'venue'.$cr});
    if ($currentRow) {
        $fieldLabel = JemCustomFields::getLabel('venue', $fieldName, Text::_('COM_JEM_VENUE_CUSTOM_FIELD'.$cr));
        $eventVenueCustomFieldsRows .= '<dt class="custom' . $cr . ' hasTooltip" data-original-title="' . $this->escape($fieldLabel) . '">' . $this->escape($fieldLabel) . ':</dt>'
            . '<dd class="custom' . $cr . '">' . $currentRow . '</dd>';
    }
}
$renderEventVenueCustomFieldsBlock = function () use ($eventVenueCustomFieldsRows) {
    if ($eventVenueCustomFieldsRows === '') {
        return '';
    }

    return '<div class="jem-custom-fields jem-venue-custom-fields">'
        . '<dl class="jem-dl">' . $eventVenueCustomFieldsRows . '</dl>'
        . '</div>';
};
$renderEventCategoryLinks = function () use ($params) {
    $categoryLinks = array();

    foreach ((array) $this->categories as $category) {
        if ($params->get('event_link_category') == 1) {
            $categoryLinks[] = '<a href="' . Route::_(JemHelperRoute::getCategoryRoute($category->catslug)) . '">' . $this->escape($category->catname) . '</a>';
        } else {
            $categoryLinks[] = $this->escape($category->catname);
        }
    }

    return implode(', ', $categoryLinks);
};
$renderVenueName = function () use ($params) {
    if (($params->get('event_show_detlinkvenue') == 1) && !empty($this->item->url)) {
        return '<a target="_blank" href="' . $this->escape($this->item->url) . '">' . $this->escape($this->item->venue) . '</a>';
    }

    if (($params->get('event_show_detlinkvenue') == 2) && !empty($this->item->venueslug)) {
        return '<a href="' . Route::_(JemHelperRoute::getVenueRoute($this->item->venueslug)) . '">' . $this->escape($this->item->venue) . '</a>';
    }

    return $this->escape($this->item->venue);
};
$renderVenueCompact = function ($venueaccess, $includeAddress = true) use ($params) {
    $locality = array_filter(array(
        trim((string) $this->item->postalCode),
        trim((string) $this->item->city),
    ));
    $addressParts = array_filter(array(
        trim((string) $this->item->street),
        implode(' ', $locality),
        trim((string) $this->item->state),
        trim((string) $this->item->country),
    ));

    if (!empty($this->item->venueslug)) {
        $venueName = '<a href="' . Route::_(JemHelperRoute::getVenueRoute($this->item->venueslug)) . '">' . $this->escape($this->item->venue) . '</a>';
    } else {
        $venueName = $this->escape($this->item->venue);
    }

    $html = '<div class="jem-venue-compact">';
    $html .= '<div class="jem-venue-compact-name">' . $venueName . $venueaccess . '</div>';

    if ($includeAddress && $addressParts) {
        $html .= '<div class="jem-venue-compact-address" itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">';

        if (!empty($this->item->street)) {
            $html .= '<span itemprop="streetAddress">' . $this->escape($this->item->street) . '</span>';
        }

        if ($locality) {
            $html .= '<span class="jem-venue-compact-locality">';

            if (!empty($this->item->postalCode)) {
                $html .= '<span itemprop="postalCode">' . $this->escape($this->item->postalCode) . '</span>';
            }

            if (!empty($this->item->city)) {
                $html .= '<span itemprop="addressLocality">' . $this->escape($this->item->city) . '</span>';
            }

            $html .= '</span>';
        }

        if (!empty($this->item->state)) {
            $html .= '<span itemprop="addressRegion">' . $this->escape($this->item->state) . '</span>';
        }

        if (trim((string) $this->item->country) !== '') {
            $html .= '<span class="jem-venue-compact-country">' . $this->escape($this->item->country);
            if ($this->item->countryimg) {
                $html .= ' ' . $this->item->countryimg;
            }
            $html .= '<meta itemprop="addressCountry" content="' . $this->escape($this->item->country) . '" /></span>';
        }

        $html .= '</div>';
    }

    $links = array();
    $mapService = (int) $params->get('event_show_mapserv');
    $hasCoordinates = !empty($this->item->latitude) && !empty($this->item->longitude)
        && (float) $this->item->latitude !== 0.0 && (float) $this->item->longitude !== 0.0;

    if ($this->item->user_has_access_venue && in_array($mapService, array(1, 2, 3, 4, 5), true) && ($hasCoordinates || $addressParts)) {
        if (in_array($mapService, array(4, 5), true)) {
            if ($hasCoordinates) {
                $mapUrl = 'https://www.openstreetmap.org/?mlat=' . urlencode($this->item->latitude) . '&mlon=' . urlencode($this->item->longitude)
                    . '&zoom=15#map=15/' . urlencode($this->item->latitude) . '/' . urlencode($this->item->longitude);
            } else {
                $mapUrl = 'https://nominatim.openstreetmap.org/ui/search.html?q=' . urlencode(implode(', ', $addressParts));
            }
        } else {
            if ($hasCoordinates) {
                $mapUrl = 'https://maps.google.' . $params->get('event_tld', 'com') . '/maps?hl=' . $params->get('event_lg', 'en')
                    . '&q=loc:' . urlencode($this->item->latitude) . ',+' . urlencode($this->item->longitude);
            } else {
                $mapUrl = 'https://www.google.' . $params->get('event_tld', 'com') . '/maps/place/' . urlencode(implode(', ', $addressParts))
                    . '?hl=' . $params->get('event_lg', 'en');
            }
        }

        $links[] = '<a class="venue_mapicon" target="_blank" href="' . $this->escape($mapUrl) . '">' . Text::_('COM_JEM_MAP_LINK') . '</a>';
    }

    if (!empty($this->item->url)) {
        $links[] = '<a class="venue_weblink" target="_blank" href="' . $this->escape($this->item->url) . '">' . Text::_('COM_JEM_WEBSITE') . '</a>';
    }

    if (!empty($this->item->email)) {
        $links[] = '<a class="venue_email" href="mailto:' . $this->escape($this->item->email) . '">' . Text::_('COM_JEM_EMAIL') . '</a>';
    }

    if ($links) {
        $html .= '<div class="jem-venue-compact-links">' . implode('<span class="jem-venue-compact-link-separator" aria-hidden="true">|</span>', $links) . '</div>';
    }

    $html .= '</div>';

    return $html;
};

?>
<style>
    .col-category {
        letter-spacing: 0.5px;
        color: #444 !important;
    }

    .con_name a {
        color: #0d6efd;
        transition: color 0.2s;
    }

    .con_name a:hover {
        color: #0056b3;
        text-decoration: underline;
    }

    .jem-contact-grouped-container i {
        color: #999;
    }

    .contact-group-row:last-child {
        border-bottom: none;
    }

</style>
<?php

$catclasses = '';
foreach ((array)$this->categories as $category) {
    $catclasses .= ' cat_id' . $this->escape($category->id);
}

if ($params->get('access-view')) { /* This will show nothings otherwise - ??? */ ?>

    <div id="jem" class="event_id<?php
    echo $this->escape($this->item->did);
    if (!empty($this->item->locid)) {
        echo ' venue_id' . $this->escape($this->item->locid);
    }
    if (!empty($catclasses)) {
        echo $this->escape($catclasses);
    }
    ?> jem_event<?php echo $this->escape($this->pageclass_sfx); ?>"
         itemscope="itemscope" itemtype="https://schema.org/Event">

        <?php if ($this->params->get('showintrotext')) : ?>
            <div class="description no_space floattext">
                <?php echo $this->params->get('introtext'); ?>
            </div>
        <?php endif; ?>

        <meta itemprop="url" content="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getEventRoute($this->item->slug)); ?>" />
        <meta itemprop="identifier" content="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getEventRoute($this->item->slug)); ?>" />
        <meta itemprop="eventStatus" content="<?php echo $eventStatusOption['schema']; ?>" />
        <div itemprop="offers" itemscope itemtype="https://schema.org/Offer" hidden>
            <link itemprop="url" href="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getEventRoute($this->item->slug)); ?>" />
            <link itemprop="availability" href="<?php echo $ticketAvailabilityOption['schema']; ?>" />
        </div>

        <div class="buttons jem-event-toolbar">
            <?php
            $btn_params = array('slug' => $this->item->slug, 'print_link' => $this->print_link, 'pdf_link' => $this->pdf_link);
            echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
            ?>
            <a class="jem-layout-toggle" href="<?php echo $this->escape($layoutToggleUrl); ?>" title="<?php echo $this->escape($layoutToggleText); ?>" aria-label="<?php echo $this->escape($layoutToggleText); ?>" data-jem-layout-target="<?php echo $this->escape($layoutToggleTarget); ?>">
                <span class="fa fa-fw fa-lg <?php echo $this->escape($layoutToggleIcon); ?> jem-layoutbutton" aria-hidden="true"></span>
            </a>
        </div>

        <?php if ($this->params->get('show_page_heading', 1)) : ?>
            <h1 class="componentheading">
                <?php echo $this->escape($this->params->get('page_heading')); ?>
            </h1>
        <?php else : ?>
            <h1 class="componentheading">
                <?php echo $this->escape($this->item->title); ?>
                <?php if ($eventLayout !== 'compact' && ($showEventStatusBadge || $showTicketAvailabilityBadge)) : ?>
                    <span class="jem-event-badges">
                        <?php if ($showEventStatusBadge) : ?>
                            <span class="jem-event-state-badge <?php echo $eventStatusOption['class']; ?>"><?php echo $this->escape($eventStatusText); ?></span>
                        <?php endif; ?>
                        <?php if ($showTicketAvailabilityBadge) : ?>
                            <span class="jem-event-state-badge <?php echo $ticketAvailabilityOption['class']; ?>"><?php echo $this->escape($ticketAvailabilityText); ?></span>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
                <?php if ($eventLayout !== 'compact') : ?>
                    <?php echo JemOutput::typeBadge($this->item); ?>
                <?php endif; ?>
            </h1>
        <?php endif; ?>
        <!-- Event -->
        <?php if ($eventLayout !== 'compact') : ?>
            <h2 class="jem">
                <?php
                echo Text::_('COM_JEM_EVENT') . JemOutput::recurrenceicon($this->item) . ' ';
                if($this->item_root) {
                    echo JemOutput::editbutton($this->item_root, $params, $attribs, $this->permissions->canEditEvent, 'editevent') . ' ';
                }
                if(!$this->item_root || ($this->item_root && $this->item->recurrence_first_id)) {
                    echo JemOutput::editbutton($this->item, $params, $attribs, $this->permissions->canEditEvent, 'editevent') .' ';
                }
                echo JemOutput::copybutton($this->item, $params, $attribs, $this->permissions->canAddEvent, 'editevent');
                ?>
            </h2>
        <?php endif; ?>
        <div class="jem-row jem-event-main-responsive">
            <div class="jem-info">
                <?php if ($eventLayout === 'compact') : ?>
                    <meta itemprop="name" content="<?php echo $this->escape($this->item->title); ?>" />
                    <div class="jem-event-compact">
                        <?php $eventCategories = $renderEventCategoryLinks(); ?>
                        <?php if (($params->get('event_show_category') == 1 && $eventCategories !== '') || JemOutput::typeBadge($this->item)) : ?>
                            <div class="jem-event-compact-meta">
                                <?php if ($params->get('event_show_category') == 1 && $eventCategories !== '') : ?>
                                    <span class="jem-event-compact-categories"><?php echo $eventCategories; ?></span>
                                <?php endif; ?>
                                <?php echo JemOutput::typeBadge($this->item); ?>
                            </div>
                        <?php endif; ?>
                        <div class="jem-event-compact-when">
                            <?php
                            echo JemOutput::formatLongDateTime($this->item->dates, $this->item->times, $this->item->enddates, $this->item->endtimes);
                            echo JemOutput::formatSchemaOrgDateTime($this->item->dates, $this->item->times, $this->item->enddates, $this->item->endtimes);
                            ?>
                        </div>
                        <?php if ($eventCustomFieldsPosition === 'details') : ?>
                            <?php echo $renderEventCustomFieldsBlock(); ?>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                <dl class="jem-dl jem-event-info-list">
                    <?php if ($params->get('event_show_detailstitle',1)) : ?>
                        <dt class="jem-title hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_TITLE'); ?>"><?php echo Text::_('COM_JEM_TITLE'); ?>:</dt>
                        <dd class="jem-title" itemprop="name">
                            <?php echo $this->escape($this->item->title); ?>
                            <?php if ($showEventStatusBadge || $showTicketAvailabilityBadge) : ?>
                                <span class="jem-event-badges">
                                    <?php if ($showEventStatusBadge) : ?>
                                        <span class="jem-event-state-badge <?php echo $eventStatusOption['class']; ?>"><?php echo $this->escape($eventStatusText); ?></span>
                                    <?php endif; ?>
                                    <?php if ($showTicketAvailabilityBadge) : ?>
                                        <span class="jem-event-state-badge <?php echo $ticketAvailabilityOption['class']; ?>"><?php echo $this->escape($ticketAvailabilityText); ?></span>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                            <?php echo JemOutput::typeBadge($this->item); ?>
                        </dd>
                    <?php else : ?>
                        <meta itemprop="name" content="<?php echo $this->escape($this->item->title); ?>" />
                    <?php endif; ?>
                    <dt class="jem-when hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_WHEN'); ?>"><?php echo Text::_('COM_JEM_WHEN'); ?>:</dt>
                    <dd class="jem-when">
            <span style="white-space: nowrap;">
              <?php
              echo JemOutput::formatLongDateTime($this->item->dates, $this->item->times,$this->item->enddates, $this->item->endtimes);
              echo JemOutput::formatSchemaOrgDateTime($this->item->dates, $this->item->times,$this->item->enddates, $this->item->endtimes);
              ?>
            </span>
                    </dd>
                    <?php if ((!empty($this->item->locid)) && ($params->get('event_show_venue_name') == 1)) : ?>
                        <dt class="jem-where hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_WHERE'); ?>"><?php echo Text::_('COM_JEM_WHERE'); ?>:</dt>
                        <dd class="jem-where"><?php
                            if (($params->get('event_show_detlinkvenue') == 1) && (!empty($this->item->url))) :
                                ?><a target="_blank" href="<?php echo $this->item->url; ?>"><?php echo $this->escape($this->item->venue); ?></a><?php
                            elseif (($params->get('event_show_detlinkvenue') == 2) && (!empty($this->item->venueslug))) :
                                ?><a href="<?php echo $this->escape(Route::_(JemHelperRoute::getVenueRoute($this->item->venueslug))); ?>"><?php echo $this->escape($this->item->venue); ?></a><?php
                            else :
                                echo $this->escape($this->item->venue);
                            endif;

                            # will show "venue" or "venue - city" or "venue - city, state" or "venue, state"
                            $city  = $this->escape($this->item->city);
                            $state = $this->escape($this->item->state);
                            if ($city)  { echo ' - ' . $city; }
                            if ($state) { echo ', ' . $state; }
                            ?>
                        </dd>
                    <?php
                    endif;
                    $n = is_array($this->categories) ? count($this->categories) : 0;
                    if ($params->get('event_show_category') == 1) : ?>

                    <dt class="jem-category hasTooltip" data-original-title="<?php echo $n < 2 ? Text::_('COM_JEM_CATEGORY') : Text::_('COM_JEM_CATEGORIES'); ?>">
                        <?php echo $n < 2 ? Text::_('COM_JEM_CATEGORY') : Text::_('COM_JEM_CATEGORIES'); ?>:
                    </dt>
                    <dd class="jem-category">
                        <?php
                        foreach ((array)$this->categories as $i => $category) {
                            if ($i > 0) {
                                echo ', ';
                            }
                            if ($params->get('event_link_category') == 1) {
                                echo '<a href="' . Route::_(JemHelperRoute::getCategoryRoute($category->catslug)) . '">' . $this->escape($category->catname) . '</a>';
                            } else {
                                echo $this->escape($category->catname);
                            }
                        }
                        echo '</dd>';
                        endif;

                        if ($eventCustomFieldsPosition === 'details') {
                            echo $eventCustomFieldsRows;
                        }
                ?>

                    <?php if ($params->get('event_show_hits')) : ?>
                        <dt class="jem-hits hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_EVENT_HITS_LABEL'); ?>"><?php echo Text::_('COM_JEM_EVENT_HITS_LABEL'); ?>:</dt>
                        <dd class="jem-hits"><?php echo Text::sprintf('COM_JEM_EVENT_HITS', $this->item->hits); ?></dd>
                    <?php endif; ?>

                    <?php if ($showEventStatusBadge) : ?>
                        <dt class="jem-event-status hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_EVENT_FIELD_EVENT_STATUS_LABEL'); ?>"><?php echo Text::_('COM_JEM_EVENT_FIELD_EVENT_STATUS_LABEL'); ?>:</dt>
                        <dd class="jem-event-status">
                            <span class="jem-event-state-badge <?php echo $eventStatusOption['class']; ?>"><?php echo $this->escape($eventStatusText); ?></span>
                        </dd>
                    <?php endif; ?>

                    <?php if ($showTicketAvailabilityBadge) : ?>
                        <dt class="jem-ticket-availability hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_EVENT_FIELD_TICKET_AVAILABILITY_LABEL'); ?>"><?php echo Text::_('COM_JEM_EVENT_FIELD_TICKET_AVAILABILITY_LABEL'); ?>:</dt>
                        <dd class="jem-ticket-availability">
                            <span class="jem-event-state-badge <?php echo $ticketAvailabilityOption['class']; ?>"><?php echo $this->escape($ticketAvailabilityText); ?></span>
                        </dd>
                    <?php endif; ?>


                    <!-- AUTHOR -->
                    <?php if ($params->get('event_show_author') && !empty($this->item->author)) : ?>
                        <dt class="createdby hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_EVENT_CREATED_BY_LABEL'); ?>"><?php echo Text::_('COM_JEM_EVENT_CREATED_BY_LABEL'); ?>:</dt>
                        <dd class="createdby">
                            <?php $author = $this->item->created_by_alias ? $this->item->created_by_alias : $this->item->author; ?>
                            <?php if (JemHelper::isContactComponentEnabled() && !empty($this->item->contactid2) && $params->get('event_link_author') == true) :
                                $concatid = null;

                                if ($params->get('event_link_author')) {
                                    $db    = Factory::getContainer()->get('DatabaseDriver');
                                    $query = $db->getQuery(true)
                                        ->select($db->quoteName('catid'))
                                        ->from($db->quoteName('#__contact_details'))
                                        ->where($db->quoteName('id') . ' = ' . $this->item->contactid2)
                                        ->where($db->quoteName('published') . ' = 1');
                                    $db->setQuery($query);
                                    $concatid = $db->loadResult();
                                }

                                if ($concatid) {
                                    $needle = 'index.php?option=com_contact&view=contact&id=' . $this->item->contactid2 . '&catid=' . $concatid;
                                    $menu = Factory::getApplication()->getMenu();
                                    $mItem = $menu->getItems('link', $needle, true);
                                    $link = Route::_($needle . (!empty($mItem) ? '&Itemid=' . $mItem->id : ''));
                                    ?>
                                    <a href="<?php echo $link; ?>" title="<?php echo Text::_('COM_JEM_EVENT_CONTACT_SEND_MESSAGE'); ?>">
                                        <?php echo $author; ?> <i class="fas fa-external-link-alt" style="font-size: 0.8em;"></i>
                                    </a>
                                <?php } else {
                                    echo Text::sprintf('COM_JEM_EVENT_CREATED_BY', $author);
                                }
                            else :
                                echo Text::sprintf('COM_JEM_EVENT_CREATED_BY', $author);
                            endif;
                            ?>
                        </dd>
                    <?php endif; ?>

                    <!-- PUBLISHING STATE -->
                    <?php if (!empty($this->showeventstate) && (int) $params->get('event_show_publish_state', 0) === 1 && isset($this->item->published)) : ?>
                        <dt class="jem-published hasTooltip" data-original-title="<?php echo Text::_('JSTATUS'); ?>"><?php echo Text::_('JSTATUS'); ?>:</dt>
                        <dd class="jem-published">
                            <?php switch ($this->item->published) {
                                case  1: echo Text::_('JPUBLISHED');   break;
                                case  0: echo Text::_('JUNPUBLISHED'); break;
                                case  2: echo Text::_('JARCHIVED');    break;
                                case -2: echo Text::_('JTRASHED');     break;
                            } ?>
                        </dd>
                    <?php endif; ?>
                </dl>
                <?php endif; ?>
            </div>
            <style>
                .jem-event-main-responsive > .jem-img {
                    flex-basis: <?php echo $this->jemsettings->imagewidth; ?>px;
                }
            </style>
            <div class="jem-img">
                <?php if ($eventImageRibbonText) : ?>
                    <div class="jem-event-image-ribbon-wrap">
                        <?php echo JemOutput::flyer($this->item, $this->dimage, 'event'); ?>
                        <span class="jem-event-image-ribbon <?php echo $eventImageRibbonClass; ?>"><?php echo $this->escape($eventImageRibbonText); ?></span>
                    </div>
                <?php else : ?>
                    <?php echo JemOutput::flyer($this->item, $this->dimage, 'event'); ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($eventCustomFieldsPosition === 'before_description') : ?>
            <?php echo $renderEventCustomFieldsBlock(); ?>
        <?php endif; ?>

        <!-- DESCRIPTION -->
        <?php $hasDescription = ($this->item->fulltext != '' && $this->item->fulltext != '<br>') || ($this->item->introtext != '' && $this->item->introtext != '<br>'); ?>
        <?php
        $onlineMeetingUrl = JemHelper::getOnlineMeetingUrl($this->item);
        $showOnlineMeeting = $onlineMeetingUrl !== '' && (int) $params->get('event_show_online_meeting', '1') === 1;
        $moreInformationDisplay = JemHelper::getMoreInformationDisplay($params->get('event_show_more_information', '1'));
        $moreInformationText = trim((string) $params->get('event_more_information_text', ''));
        if ($moreInformationText === '') {
            $moreInformationText = Text::_('COM_JEM_EVENT_MORE_INFORMATION');
        } elseif (strtoupper($moreInformationText) === $moreInformationText) {
            $moreInformationText = Text::_($moreInformationText);
        }
        $showMoreInformation = $params->get('access-view') && $moreInformationDisplay !== '' && !empty($this->item->articlelink);
        $eventIntroHtml = trim((string) $this->item->introtext);
        $eventFullHtml = trim((string) $this->item->fulltext);
        $eventCompactHasMore = $eventLayout === 'compact'
            && $eventFullHtml !== ''
            && $eventFullHtml !== '<br>';
        $eventDescriptionHtml = '';

        if ($eventLayout === 'compact') {
            $eventDescriptionHtml = $eventIntroHtml;
        } elseif ($eventFullHtml !== '' && $eventFullHtml !== '<br>') {
            $eventDescriptionHtml = $params->get('event_show_intro')
                ? trim($eventIntroHtml . $eventFullHtml)
                : $eventFullHtml;
        } else {
            $eventDescriptionHtml = $this->item->text;
        }

        $showEventDescription = $params->get('event_show_description','1')
            && ($eventDescriptionHtml !== '' && $eventDescriptionHtml !== '<br>' || $eventCompactHasMore)
            && $hasDescription;
        ?>
        <?php if ($showEventDescription || $showOnlineMeeting || !empty($this->event_links)) { ?>
            <?php if ($showEventDescription) : ?>
                <h2 class="description"><?php echo Text::_('COM_JEM_EVENT_DESCRIPTION'); ?></h2>
            <?php endif; ?>
            <div class="description event_desc" itemprop="description">

                <?php
                if ($params->get('access-view')) {
                    if ($showEventDescription) {
                        if ($eventDescriptionHtml !== '' && $eventDescriptionHtml !== '<br>') {
                            echo $eventDescriptionHtml;
                        }

                        if ($eventLayout === 'compact' && $eventCompactHasMore) {
                            echo $renderCompactReadmore($detailsLayoutUrl);
                        }
                    }

                    if ($showEventDescription && $eventCustomFieldsPosition === 'after_description') {
                        echo $renderEventCustomFieldsBlock();
                    }

                    if ($showOnlineMeeting) :
                        $onlineMeetingLabel = JemHelper::getOnlineMeetingLabel($this->item);
                        $onlineMeetingPlatform = JemHelper::getOnlineMeetingPlatform($onlineMeetingUrl);
                        $onlineMeetingPlatformKey = preg_replace('/[^a-z0-9_-]/i', '', $onlineMeetingPlatform['key']);
                        ?>
                        <fieldset id="jem-event-online-meeting-<?php echo (int) $this->item->id; ?>"
                                  aria-labelledby="jem-event-online-meeting-title-<?php echo (int) $this->item->id; ?>"
                                  class="jem-online-meeting jem-online-meeting-<?php echo $this->escape($onlineMeetingPlatformKey); ?>">
                            <legend class="visually-hidden"><?php echo Text::_('COM_JEM_ONLINE_MEETING'); ?></legend>
                            <h2 id="jem-event-online-meeting-title-<?php echo (int) $this->item->id; ?>"
                                class="description jem-online-meeting-title"><?php echo Text::_('COM_JEM_ONLINE_MEETING'); ?></h2>
                            <a id="jem-event-online-meeting-link-<?php echo (int) $this->item->id; ?>"
                               class="jem-online-meeting-link btn btn-primary"
                               href="<?php echo $this->escape($onlineMeetingUrl); ?>"
                               target="_blank"
                               title="<?php echo $this->escape($onlineMeetingPlatform['label']); ?>"
                               rel="noopener noreferrer">
                                <span class="jem-online-meeting-icon <?php echo $this->escape($onlineMeetingPlatform['icon']); ?>" aria-hidden="true"></span>
                                <span class="visually-hidden"><?php echo $this->escape($onlineMeetingPlatform['label']); ?>: </span>
                                <?php echo $this->escape($onlineMeetingLabel); ?>
                            </a>
                        </fieldset>
                    <?php endif; ?>

                    <?php if (!empty($this->event_links)) : ?>
                        <?php
                        // Default icons by action type.
                        $defaultLinkTypes = array(
                            'info'       => array('icon' => 'fa fa-info-circle', 'label' => 'COM_JEM_EVENT_LINK_TXT_INFO'),
                            'online'     => array('icon' => 'fa fa-globe', 'label' => 'COM_JEM_EVENT_LINK_TXT_ONLINE'),
                            'request'    => array('icon' => 'fa fa-ticket', 'label' => 'COM_JEM_EVENT_LINK_TXT_REQUEST'),
                            'pay'        => array('icon' => 'fa fa-credit-card', 'label' => 'COM_JEM_EVENT_LINK_TXT_PAY'),
                            'price'      => array('icon' => 'fa fa-tag', 'label' => 'COM_JEM_EVENT_LINK_TXT_PRICE'),
                            'speaker'    => array('icon' => 'fa fa-microphone', 'label' => 'COM_JEM_EVENT_LINK_TXT_SPEAKER'),
                            'workshop'   => array('icon' => 'fa fa-tools', 'label' => 'COM_JEM_EVENT_LINK_TXT_WORKSHOP'),
                            'location'   => array('icon' => 'fa fa-map-marker-alt', 'label' => 'COM_JEM_EVENT_LINK_TXT_LOCATION'),
                            'calendar'   => array('icon' => 'fa fa-calendar', 'label' => 'COM_JEM_EVENT_LINK_TXT_CALENDAR'),
                            'document'   => array('icon' => 'fa fa-file-alt', 'label' => 'COM_JEM_EVENT_LINK_TXT_DOCUMENT'),
                            'sponsor'    => array('icon' => 'fa fa-handshake', 'label' => 'COM_JEM_EVENT_LINK_TXT_SPONSOR'),
                            'networking' => array('icon' => 'fa fa-users', 'label' => 'COM_JEM_EVENT_LINK_TXT_NETWORKING')
                        );
                        ?>

                        <?php
                        // Read event links layout from event attribs merged into params.
                        $linksLayout = 'row';

                        if (!empty($this->item->params) && is_object($this->item->params) && method_exists($this->item->params, 'get')) {
                            $linksLayout = (string) $this->item->params->get('links_layout', 'row');
                        }
                        if (!in_array($linksLayout, array('row', 'row_full', 'row_uniform', 'column', 'column_full', 'column_uniform'), true)) {
                            $linksLayout = 'row';
                        }

                        $params = !empty($this->item->params) ? $this->item->params : $this->params;

                        $linksOrder = $params->get('links_order', 'image_icon_text');

                        $linksOrderMap = [
                            'image_icon_text' => ['image', 'icon', 'text'],
                            'image_text_icon' => ['image', 'text', 'icon'],
                            'icon_text_image' => ['icon', 'text', 'image'],
                            'icon_image_text' => ['icon', 'image', 'text'],
                            'text_image_icon' => ['text', 'image', 'icon'],
                            'text_icon_image' => ['text', 'icon', 'image'],
                        ];

                        if (!isset($linksOrderMap[$linksOrder])) {
                            $linksOrder = 'image_icon_text';
                        }

                        $orderClass = 'jem-links-order-' . str_replace('_', '-', $linksOrder);
                        ?>

                        <div class="jem-event-links jem-event-links-<?php echo $this->escape($linksLayout); ?> <?php echo $this->escape($orderClass); ?>">
                            <?php foreach ($this->event_links as $link) : ?>
                                <?php
                                // Read link values safely.
                                $url = !empty($link->url) ? trim((string) $link->url) : '';
                                $type      = !empty($link->type) ? trim((string) $link->type) : 'info';
                                $target    = !empty($link->target) ? trim((string) $link->target) : '_blank';
                                $label     = !empty($link->title) ? trim((string) $link->title) : '';
                                $description = !empty($link->description) ? trim((string) $link->description) : '';
                                $image     = !empty($link->image) ? trim((string) $link->image) : '';
                                $icon      = !empty($link->icon) ? trim((string) $link->icon) : '';
                                $color     = !empty($link->color) ? trim((string) $link->color) : '';
                                $maxWidth  = !empty($link->max_width) ? (int) $link->max_width : 120;
                                $maxHeight = !empty($link->max_height) ? (int) $link->max_height : 60;
                                $frameValue = isset($link->frame) ? $link->frame : 0;
                                $frame = in_array((string) $frameValue, array('1', 'true', 'yes', 'on'), true) ? 1 : 0;

                                if ($url === '' && $label === '' && $description === '' && $image === '' && $icon === '') {
                                    continue;
                                }

                                $hasLink = !in_array($url, array('', '#'), true);

                                // "noicon" type suppresses all icons; otherwise fall back to the type's default.
                                if ($type === 'noicon') {
                                    $icon = '';
                                } elseif ($icon === '' && isset($defaultLinkTypes[$type]['icon'])) {
                                    $icon = $defaultLinkTypes[$type]['icon'];
                                }

                                // Remove Joomla media metadata from the image URL if present.
                                if ($image !== '' && strpos($image, '#') !== false) {
                                    $imageParts = explode('#', $image, 2);
                                    $image = $imageParts[0];
                                }

                                $target = in_array($target, ['_blank', '_self'], true) ? $target : '_blank';
                                $rel = ($target === '_blank') ? ' rel="noopener noreferrer"' : '';

                                $safeType = preg_replace('/[^a-z0-9_-]/i', '', $type);
                                $linkTypeLabel = isset($defaultLinkTypes[$type]['label'])
                                    ? Text::_($defaultLinkTypes[$type]['label'])
                                    : ucwords(str_replace(array('-', '_'), ' ', $safeType));

                                $linkClasses = array(
                                    'jem-event-link',
                                    'jem-event-link-' . $safeType
                                );

                                if ($frame) {
                                    $linkClasses[] = 'jem-event-link-has-frame';
                                }

                                if (!$hasLink) {
                                    $linkClasses[] = 'jem-event-link-no-link';
                                }

                                if ($image !== '') {
                                    $linkClasses[] = 'jem-event-link-has-image';
                                }

                                if ($label !== '') {
                                    $linkClasses[] = 'jem-event-link-has-label';
                                }
                                if ($description !== '') {
                                    $linkClasses[] = 'jem-event-link-has-description';
                                }

                                $linkStyle = array();

                                if ($color !== '' && preg_match('/^#[0-9a-f]{3,8}$/i', $color)) {
                                    if (!$frame && $hasLink) {
                                        $linkStyle[] = '--jem-event-link-hover-color: ' . $color;
                                    } else {
                                        $linkStyle[] = 'color: ' . $color;
                                    }
                                }

                                $linkStyleAttr = !empty($linkStyle) ? ' style="' . implode('; ', $linkStyle) . '"' : '';

                                $imageStyle = array();

                                if ($maxWidth > 0) {
                                    $imageStyle[] = 'max-width: ' . $maxWidth . 'px !important';
                                }

                                if ($maxHeight > 0) {
                                    $imageStyle[] = 'max-height: ' . $maxHeight . 'px !important';
                                }

                                $imageStyle[] = 'width: auto !important';
                                $imageStyle[] = 'height: auto !important';
                                $imageStyle[] = 'object-fit: contain';

                                $imageStyleAttr = ' style="' . implode('; ', $imageStyle) . '"';
                                $displayImage = $image !== '' ? JemImage::linkThumbnail($image, $maxWidth, $maxHeight, true) : '';

                                $imageOrder = array_search('image', $linksOrderMap[$linksOrder], true) + 1;
                                $iconOrder  = array_search('icon', $linksOrderMap[$linksOrder], true) + 1;
                                $textOrder  = array_search('text', $linksOrderMap[$linksOrder], true) + 1;

                                $imageOrderStyle = ' style="order: ' . (int) $imageOrder . ' !important"';
                                $iconOrderStyle  = ' style="order: ' . (int) $iconOrder . ' !important"';
                                $textOrderStyle  = ' style="order: ' . (int) $textOrder . ' !important"';

                                $linkParts = array();

                                if ($image !== '') {
                                    $linkParts['image'] = '<span class="jem-event-link-image"' . $imageOrderStyle . '>'
                                        . '<img src="' . $this->escape($displayImage) . '" alt="' . $this->escape($label) . '" loading="lazy"' . $imageStyleAttr . '>'
                                        . '</span>';
                                }

                                if ($icon !== '') {
                                    $linkParts['icon'] = '<span class="jem-event-link-icon hasTooltip ' . $this->escape($icon) . '" role="img" aria-label="' . $this->escape($linkTypeLabel) . '" title="' . $this->escape($linkTypeLabel) . '" data-bs-toggle="tooltip"' . $iconOrderStyle . '></span>';
                                }

                                if ($label !== '' || $description !== '') {
                                    $textHtml = '<span class="jem-event-link-text"' . $textOrderStyle . '>';

                                    if ($label !== '') {
                                        $textHtml .= '<strong class="jem-event-link-label">' . $this->escape($label) . '</strong>';
                                    }

                                    if ($description !== '') {
                                        $textHtml .= '<span class="jem-event-link-description">' . nl2br($this->escape($description)) . '</span>';
                                    }

                                    $textHtml .= '</span>';
                                    $linkParts['text'] = $textHtml;
                                }

                                $partOrder = $linksOrderMap[$linksOrder];

                                $tagName = $hasLink ? 'a' : 'span';
                                ?>

                                <<?php echo $tagName; ?>
                                        class="<?php echo $this->escape(implode(' ', $linkClasses)); ?>"
                                    <?php if ($hasLink) : ?>
                                            href="<?php echo $this->escape($url); ?>"
                                            target="<?php echo $this->escape($target); ?>"
                                        <?php echo $rel; ?>
                                    <?php endif; ?>
                                    <?php echo $linkStyleAttr; ?>
                                >
                                    <?php foreach ($partOrder as $partName) : ?>
                                        <?php echo $linkParts[$partName] ?? ''; ?>
                                    <?php endforeach; ?>
                            </<?php echo $tagName; ?>>

                            <?php endforeach; ?>
                        </div>
                    <?php endif;

                }
                /* optional teaser intro text for guests - NOT SUPPORTED YET */
                elseif (0 /*$params->get('event_show_noauth') == true and  $user->get('guest')*/ ) {
                    echo $this->item->introtext;
                    // Optional link to let them register to see the whole event.
                    if ($params->get('event_show_readmore') && $this->item->fulltext != null) {
                        $link1 = Route::_('index.php?option=com_users&view=login');
                        $link = new Uri($link1);
                        echo '<p class="readmore">';
                        echo '<a href="'.$link.'">';
                        if ($params->get('event_alternative_readmore') == false) {
                            echo Text::_('COM_JEM_EVENT_REGISTER_TO_READ_MORE');
                        } elseif ($readmore = $params->get('alternative_readmore')) {
                            echo $readmore;
                        }

                        if ($params->get('event_show_readmore_title', 0) != 0) {
                            echo HTMLHelper::_('string.truncate', ($this->item->title), $params->get('event_readmore_limit'));
                        } elseif ($params->get('event_show_readmore_title', 0) == 0) {
                        } else {
                            echo HTMLHelper::_('string.truncate', ($this->item->title), $params->get('event_readmore_limit'));
                        } ?>
                        </a>
                        </p>
                        <?php
                    }
                } /* access_view / show_noauth */
                ?>
            </div>
        <?php } ?>

        <?php if ($showMoreInformation) : ?>
            <div class="jem-more-information jem-event-more-information">
                <a id="jem-event-more-information-<?php echo (int) $this->item->id; ?>"
                   href="<?php echo htmlspecialchars($this->item->articlelink, ENT_QUOTES, 'UTF-8'); ?>"
                   class="<?php echo JemHelper::getMoreInformationClass($moreInformationDisplay, 'jem-more-information-link jem-event__more-information'); ?>">
                    <?php echo $this->escape($moreInformationText); ?>
                </a>
                <?php if (!empty($this->item->caneditarticle) && !empty($this->item->articleeditlink)) : ?>
                    <a id="jem-event-edit-associated-article-<?php echo (int) $this->item->id; ?>"
                       href="<?php echo htmlspecialchars($this->item->articleeditlink, ENT_QUOTES, 'UTF-8'); ?>"
                       class="jem-associated-article-edit-link btn btn-secondary btn-sm"
                       title="<?php echo Text::_('COM_JEM_EDIT_ASSOCIATED_ARTICLE'); ?>">
                        <span class="icon-edit" aria-hidden="true"></span>
                        <span class="visually-hidden"><?php echo Text::_('COM_JEM_EDIT_ASSOCIATED_ARTICLE'); ?></span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($eventCustomFieldsPosition === 'after_links') : ?>
            <?php echo $renderEventCustomFieldsBlock(); ?>
        <?php endif; ?>

        <!-- CONTACTS -->
        <?php
        $showContactCategory = $params->get('event_show_contact_category');
        $showContactDesc     = $params->get('event_show_contact_description');
        $selectedFields      = $params->get('contact_fields', ['position', 'website', 'country']);

        if (JemHelper::isContactComponentEnabled() && $params->get('event_show_contact') && !empty($this->contacts)) :

            $displayGroups = array();
            if ($showContactCategory) {
                foreach ($this->contacts as $contact) {
                    $catName = !empty($contact->category_name) ? $contact->category_name : Text::_('COM_JEM_NO_CATEGORY');
                    $displayGroups[$catName][] = $contact;
                }
            } else {
                $displayGroups['NO_CAT_HEADER'] = $this->contacts;
            }
            ?>

            <h2 class="jem-contact"><?php echo Text::_('COM_JEM_CONTACT_INFO'); ?></h2>

            <div class="jem-contact-responsive">
                <?php foreach ($displayGroups as $categoryTitle => $contactList) : ?>

                    <div class="contact-group-row d-flex flex-column flex-md-row mb-4" style="border-bottom: 1px solid #eee; padding-bottom: 15px;">

                        <?php if ($showContactCategory) : ?>
                            <div class="category-label mb-2 mb-md-0" style="flex: 0 0 25%; font-weight: bold; text-transform: uppercase; color: #444;">
                                <i class="icon-users"></i> <?php echo $this->escape($categoryTitle); ?>
                            </div>
                        <?php endif; ?>

                        <div class="contacts-list" style="flex: 1;">
                            <?php foreach ($contactList as $contact) : ?>
                                <div class="contact-entry mb-4" style="display: flex; flex-direction: column; border-bottom: 1px dashed #ddd; padding-bottom: 15px;">

                                    <div class="con_name" style="font-weight: 600; font-size: 1.1em; margin-bottom: 4px;">
                                        <?php if ($params->get('event_show_contact_link', 0) && !empty($contact->conid)) : ?>
                                            <?php $link = Route::_('index.php?option=com_contact&view=contact&id=' . $contact->conid); ?>
                                            <a href="<?php echo $link; ?>" title="<?php echo Text::_('COM_JEM_EVENT_CONTACT_SEND_MESSAGE'); ?>">
                                                <?php echo $this->escape($contact->conname); ?> <i class="fas fa-external-link-alt" style="font-size: 0.8em;"></i>
                                            </a>
                                        <?php else : ?>
                                            <?php echo $this->escape($contact->conname); ?>
                                        <?php endif; ?>
                                    </div>

                                    <div class="con_details d-flex flex-wrap" style="font-size: 0.9em; color: #666; gap: 10px 20px;">

                                        <?php if (in_array('position', $selectedFields) && !empty($contact->conposition)) : ?>
                                            <span class="con-position"><i class="fas fa-briefcase"></i> <?php echo $this->escape($contact->conposition); ?></span>
                                        <?php endif; ?>

                                        <?php if (in_array('phone', $selectedFields) && !empty($contact->contelephone)) : ?>
                                            <span><i class="icon-phone"></i> <?php echo $this->escape($contact->contelephone); ?></span>
                                        <?php endif; ?>

                                        <?php if (in_array('mobile', $selectedFields) && !empty($contact->conmobile)) : ?>
                                            <span><i class="fas fa-mobile-alt"></i> <?php echo $this->escape($contact->conmobile); ?></span>
                                        <?php endif; ?>

                                        <?php if (in_array('email', $selectedFields) && !empty($contact->conemail)) : ?>
                                            <span><i class="icon-envelope"></i> <?php echo HTMLHelper::_('email.cloak', $contact->conemail); ?></span>
                                        <?php endif; ?>

                                        <?php if (in_array('website', $selectedFields) && !empty($contact->conwebsite)) : ?>
                                            <span><i class="fas fa-globe"></i> <a href="<?php echo $this->escape($contact->conwebsite); ?>" target="_blank" rel="noopener"><?php echo Text::_('COM_JEM_CONTACT_FIELD_WEB'); ?></a></span>
                                        <?php endif; ?>

                                        <?php if (in_array('address', $selectedFields) && !empty($contact->conaddress)) : ?>
                                            <span><i class="fas fa-map-marker-alt"></i> <?php echo $this->escape($contact->conaddress); ?></span>
                                        <?php endif; ?>

                                        <?php if (in_array('city', $selectedFields) && !empty($contact->concity)) : ?>
                                            <span><i class="fas fa-city"></i> <?php echo $this->escape($contact->concity); ?></span>
                                        <?php endif; ?>

                                        <?php if (in_array('state', $selectedFields) && !empty($contact->constate)) : ?>
                                            <span><i class="fas fa-map"></i> <?php echo $this->escape($contact->constate); ?></span>
                                        <?php endif; ?>

                                        <?php if (in_array('country', $selectedFields) && !empty($contact->concountry)) : ?>
                                            <span><i class="fas fa-flag"></i> <?php echo $this->escape($contact->concountry); ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($showContactDesc && !empty($contact->condescription)) : ?>
                                        <div class="con_description" style="font-size: 0.95em; color: #555; line-height: 1.5; padding: 10px; background: #fdfdfd; border-left: 3px solid #eee; margin-top: 12px;">
                                            <?php echo $contact->condescription; ?>
                                        </div>
                                    <?php endif; ?>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php $this->attachments = $this->item->attachments; ?>
        <?php echo $this->loadTemplate('attachments'); ?>

        <!-- Venue -->
        <?php if ((!empty($this->item->locid)) && !empty($this->item->venue) && $params->get('event_show_venue', '1')) : ?>
            <p></p>
            <hr class="jem-hr">
            <?php
            // has user access
            $venueaccess = '';
            if (!$this->item->user_has_access_venue) {
                // show a closed lock icon
                $venueaccess = ' <span class="icon-lock jem-lockicon" aria-hidden="true"></span>';
            }
            ?>

            <div class="venue_id<?php echo $this->item->locid; ?>" itemprop="location" itemscope="itemscope" itemtype="https://schema.org/Place">
                <meta itemprop="name" content="<?php echo $this->escape($this->item->venue); ?>" />
                <?php $itemid = $this->item ? $this->item->id : 0 ; ?>
                <?php if ($venueLayout !== 'compact') : ?>
                    <h2 class="jem-location">
                        <?php
                        echo Text::_('COM_JEM_VENUE').' '.JemOutput::editbutton($this->item, $params, $attribs, $this->permissions->canEditVenue, 'editvenue').' '.JemOutput::copybutton($this->item, $params, $attribs, $this->permissions->canAddVenue, 'editvenue');
                        ?>
                    </h2>
                <?php endif; ?>

                <?php if ($venueLayout === 'compact') : ?>
                    <div class="jem-row jem-wrap-reverse jem-venue-compact-row">
                        <div class="jem-grow-2">
                            <?php echo $renderVenueCompact($venueaccess, $this->item->user_has_access_venue && (bool) $params->get('event_show_detailsadress', '1')); ?>
                        </div>

                        <div class="jem-img">
                            <?php echo JemOutput::flyer($this->item, $this->limage, 'venue'); ?>
                        </div>
                    </div>
                <?php else : ?>
                <div class="jem-row jem-wrap-reverse">
                    <?php if ($params->get('event_show_detailsadress', '1')) : ?>
                        <div class="jem-grow-2">
                            <dl class="jem-dl" itemprop="address" itemscope
                                itemtype="https://schema.org/PostalAddress">
                                <dt class="venue hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_LOCATION'); ?>"><?php echo Text::_('COM_JEM_LOCATION'); ?>:</dt>
                                <dd class="venue">
                                    <?php
                                    if (($params->get('event_show_detlinkvenue') == 1) && (!empty($this->item->url))) :
                                        echo '<a target="_blank" href="' . $this->item->url . '">' . $this->escape($this->item->venue) . '</a>';
                                    elseif (($params->get('event_show_detlinkvenue') == 2) && (!empty($this->item->venueslug))) :
                                        echo '<a href="' . Route::_(JemHelperRoute::getVenueRoute($this->item->venueslug)) . '">' . $this->escape($this->item->venue) . '</a>';
                                    else :
                                        echo $this->escape($this->item->venue);
                                    endif;
                                    echo $venueaccess;
                                    ?>
                                </dd>
                                <?php if($this->item->user_has_access_venue) : ?>
                                    <?php if ($this->item->street) : ?>
                                        <dt class="venue_street hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_STREET'); ?>"><?php echo Text::_('COM_JEM_STREET'); ?>:</dt>
                                        <dd class="venue_street" itemprop="streetAddress">
                                            <?php echo $this->escape($this->item->street); ?>
                                        </dd>
                                    <?php endif; ?>

                                    <?php if ($this->item->postalCode) : ?>
                                        <dt class="venue_postalCode hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_ZIP'); ?>"><?php echo Text::_('COM_JEM_ZIP'); ?>:</dt>
                                        <dd class="venue_postalCode" itemprop="postalCode">
                                            <?php echo $this->escape($this->item->postalCode); ?>
                                        </dd>
                                    <?php endif; ?>

                                    <?php if ($this->item->city) : ?>
                                        <dt class="venue_city hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_CITY'); ?>"><?php echo Text::_('COM_JEM_CITY'); ?>:</dt>
                                        <dd class="venue_city" itemprop="addressLocality">
                                            <?php echo $this->escape($this->item->city); ?>
                                        </dd>
                                    <?php endif; ?>

                                    <?php if ($this->item->state) : ?>
                                        <dt class="venue_state hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_STATE'); ?>"><?php echo Text::_('COM_JEM_STATE'); ?>:</dt>
                                        <dd class="venue_state" itemprop="addressRegion">
                                            <?php echo $this->escape($this->item->state); ?>
                                        </dd>
                                    <?php endif; ?>

                                    <?php if ($this->item->country) : ?>
                                        <dt class="venue_country hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_COUNTRY'); ?>"><?php echo Text::_('COM_JEM_COUNTRY'); ?>:</dt>
                                        <dd class="venue_country">
                                            <?php echo $this->item->countryimg ? $this->item->countryimg : $this->item->country; ?>
                                            <meta itemprop="addressCountry" content="<?php echo $this->item->country; ?>" />
                                        </dd>
                                    <?php endif; ?>

                                    <!-- PUBLISHING STATE -->
                                    <?php if (!empty($this->showvenuestate) && isset($this->item->locpublished)) : ?>
                                        <dt class="venue_published hasTooltip" data-original-title="<?php echo Text::_('JSTATUS'); ?>"><?php echo Text::_('JSTATUS'); ?>:</dt>
                                        <dd class="venue_published">
                                            <?php switch ($this->item->locpublished) {
                                                case  1: echo Text::_('JPUBLISHED');   break;
                                                case  0: echo Text::_('JUNPUBLISHED'); break;
                                                case  2: echo Text::_('JARCHIVED');    break;
                                                case -2: echo Text::_('JTRASHED');     break;
                                            } ?>
                                        </dd>
                                    <?php endif; ?>

                                    <?php if ($venueCustomFieldsPosition === 'details') : ?>
                                        <?php echo $eventVenueCustomFieldsRows; ?>
                                    <?php endif; ?>
                                    <?php if ($params->get('event_show_mapserv') == 1 || $params->get('event_show_mapserv') == 4) : ?>
                                        <?php echo JemOutput::mapicon($this->item, 'event', $params); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </dl>
                        </div>

                        <div class="jem-img">
                            <?php echo JemOutput::flyer($this->item, $this->limage, 'venue'); ?>
                        </div>
                    <?php else : // $params->get('event_show_detailsadress', '1') == 0 ?>
                        <div class="jem-grow-2">
                            <dl class="jem-dl" itemprop="address" itemscope
                                itemtype="https://schema.org/PostalAddress">
                                <dt class="venue hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_LOCATION'); ?>"><?php echo Text::_('COM_JEM_LOCATION'); ?>:</dt>
                                <dd class="venue">
                                    <?php
                                    if (($params->get('event_show_detlinkvenue') == 1) && (!empty($this->item->url))) :
                                        echo '<a target="_blank" href="' . $this->item->url . '">' . $this->escape($this->item->venue) . '</a>';
                                    elseif (($params->get('event_show_detlinkvenue') == 2) && (!empty($this->item->venueslug))) :
                                        echo '<a href="' . Route::_(JemHelperRoute::getVenueRoute($this->item->venueslug)) . '">' . $this->escape($this->item->venue) . '</a>';
                                    else :
                                        echo $this->escape($this->item->venue);
                                    endif;
                                    ?>
                                </dd>
                            </dl>
                        </div>

                        <div class="jem-img">
                            <?php echo JemOutput::flyer($this->item, $this->limage, 'venue'); ?>
                        </div>
                    <?php endif; /* event_show_detailsadress */ ?>
                </div>
                <?php endif; ?>
                <?php if($this->item->user_has_access_venue) :
                    $event_show_mapserv = $params->get('event_show_mapserv');
                    if ($venueLayout === 'compact' && $venueCustomFieldsPosition === 'details') : ?>
                        <?php echo $renderEventVenueCustomFieldsBlock(); ?>
                    <?php endif; ?>
                    <?php if ($venueLayout !== 'compact' && ($params->get('event_show_mapserv') == 2 || $params->get('event_show_mapserv') == 5)) : ?>
                        <div class="jem-map">
                            <?php echo JemOutput::mapicon($this->item, 'event', $params); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($venueLayout !== 'compact' && $event_show_mapserv == 3) : ?>
                    <div class="jem-map">
                        <input type="hidden" id="latitude" value="<?php echo $this->escape($this->item->latitude); ?>">
                        <input type="hidden" id="longitude" value="<?php echo $this->escape($this->item->longitude); ?>">
                        <input type="hidden" id="venue" value="<?php echo $this->escape($this->item->venue); ?>">
                        <input type="hidden" id="street" value="<?php echo $this->escape($this->item->street); ?>">
                        <input type="hidden" id="city" value="<?php echo $this->escape($this->item->city); ?>">
                        <input type="hidden" id="state" value="<?php echo $this->escape($this->item->state); ?>">
                        <input type="hidden" id="postalCode" value="<?php echo $this->escape($this->item->postalCode); ?>">
                        <?php echo JemOutput::mapicon($this->item, 'event', $params); ?>
                    </div>
                <?php endif; ?>

                    <?php if ($venueCustomFieldsPosition === 'before_description') : ?>
                        <?php echo $renderEventVenueCustomFieldsBlock(); ?>
                    <?php endif; ?>

                <?php
                $venueDescriptionParts = $splitReadmoreText($this->item->locdescription);
                $venueDescriptionHtml = $venueLayout === 'compact'
                    ? $venueDescriptionParts->intro
                    : trim($venueDescriptionParts->intro . $venueDescriptionParts->full);
                $venueCompactHasMore = $venueLayout === 'compact'
                    && $venueDescriptionParts->full !== ''
                    && $venueDescriptionParts->full !== '<br>';
                ?>
                <?php if ($params->get('event_show_locdescription', '1') && ($venueDescriptionHtml !== ''
                    && $venueDescriptionHtml !== '<br>' || $venueCompactHasMore)) : ?>
                    <h2 class="location_desc"><?php echo Text::_('COM_JEM_VENUE_DESCRIPTION'); ?></h2>
                    <div class="description location_desc" itemprop="description">
                        <?php echo $venueDescriptionHtml; ?>
                        <?php
                        if ($venueCompactHasMore) {
                            echo $renderCompactReadmore($detailsLayoutUrl);
                        }
                        ?>
                    </div>
                <?php endif; ?>

                    <?php if ($venueCustomFieldsPosition === 'after_description' || $venueCustomFieldsPosition === 'after_links') : ?>
                        <?php echo $renderEventVenueCustomFieldsBlock(); ?>
                    <?php endif; ?>
                <?php endif; ?>

                <?php $this->attachments = $this->item->vattachments; ?>
                <?php $this->attachmentParams = $this->item->venue_params ?? null; ?>
                <?php echo $this->loadTemplate('attachments'); ?>
                <?php unset($this->attachmentParams); ?>

            </div>

        <?php endif; ?>

        <!-- Registration -->
        <?php if ($this->showAttendees && $params->get('event_show_registration', $this->settings->get('event_show_registration', '1'))) { ?>
            <hr class="jem-hr">
            <dl class="jem-dl floattext">
                <?php
                $timeNow = time();
                $showCancellationInfo = (!$this->user->get('guest') && $this->isregistered !== false) || !empty($this->permissions->canEditAttendees);

                switch ($this->e_reg) {
                    case 0:
                        //Event without registration (NO)
                        break;
                    case 1:
                        //Event with registration (YES with or witout UNTIL)
                        if ($eventLayout !== 'compact') {
                            echo '<h2 class="register">' . Text::_('COM_JEM_REGISTRATION') . '</h2>';
                        }
                        echo $this->loadTemplate('attendees');
                        if($showCancellationInfo && $this->dateUnregistationUntil) {
                            echo '<dt>' . ($this->allowAnnulation? Text::_('COM_JEM_EVENT_ANNULATION_NOTWILLBE_FROM') : Text::_('COM_JEM_EVENT_ANNULATION_ISNOT_FROM')) . '</dt><dd>' . HTMLHelper::_('date', $this->dateUnregistationUntil, Text::_('DATE_FORMAT_LC2')) . '</dd>';
                        }
                        break;
                    case 2:
                        //Event with date starting registration (FROM with or witout UNTIL)
                        if ($eventLayout !== 'compact') {
                            echo '<h2 class="register">' . Text::_('COM_JEM_REGISTRATION') . '</h2>';
                        }
                        if($this->dateRegistationFrom > $timeNow) {
                            echo '<dt>' . Text::_('COM_JEM_EVENT_REGISTRATION_WILLBE_FROM') . '</dt><dd>' . HTMLHelper::_('date', $this->dateRegistationFrom, Text::_('DATE_FORMAT_LC2'));
                        }else if ($this->allowRegistration) {
                            echo '<dt>' . Text::_('COM_JEM_EVENT_REGISTRATION_IS_FROM') . '</dt><dd>' . HTMLHelper::_('date', $this->dateRegistationFrom, Text::_('DATE_FORMAT_LC2'));
                            if($this->dateRegistationUntil){
                                echo " " . mb_strtolower(Text::_('COM_JEM_UNTIL')) . ' ' . HTMLHelper::_('date', $this->dateRegistationUntil, Text::_('DATE_FORMAT_LC2'));
                            }
                            echo "</dd>";
                            echo $this->loadTemplate('attendees');

                            //Event with date starting annulation
                            if($showCancellationInfo && $this->dateUnregistationUntil) {
                                echo '<dt>' . ($this->allowAnnulation? Text::_('COM_JEM_EVENT_ANNULATION_NOTWILLBE_FROM') : Text::_('COM_JEM_EVENT_ANNULATION_ISNOT_FROM')) . '</dt><dd>' . HTMLHelper::_('date', $this->dateUnregistationUntil, Text::_('DATE_FORMAT_LC2')) . '</dd>';
                            }
                        }else if($this->dateRegistationUntil !== false && $this->dateRegistationUntil < $timeNow) {
                            echo '<dt>' . Text::_('COM_JEM_EVENT_REGISTRATION_WAS_UNTIL') . '</dt><dd>' . HTMLHelper::_('date', $this->dateRegistationUntil, Text::_('DATE_FORMAT_LC2')) .  '</dd>';
                            echo $this->loadTemplate('attendees');

                            //Event with date starting annulation
                            if($showCancellationInfo && $this->dateUnregistationUntil) {
                                echo '<dt>' . ($this->allowAnnulation? Text::_('COM_JEM_EVENT_ANNULATION_NOTWILLBE_FROM') : Text::_('COM_JEM_EVENT_ANNULATION_ISNOT_FROM')) . '</dt><dd>' . HTMLHelper::_('date', $this->dateUnregistationUntil, Text::_('DATE_FORMAT_LC2')) . '</dd>';
                            }
                        } else {
                            // open registration to the end of event
                            if($this->item->enddates){
                                $endDateEvent = strtotime($this->item->enddates . ' ' . ($this->item->endtimes ? $this->item->endtimes : '23:59:59'));
                                if($timeNow <= $endDateEvent){
                                    echo '<dt>' . Text::_('COM_JEM_EVENT_REGISTRATION_IS_UNTIL');
                                } else {
                                    echo '<dt>' . Text::_('COM_JEM_EVENT_REGISTRATION_WAS_UNTIL');
                                }
                                echo '</dt><dd>' . HTMLHelper::_('date', $endDateEvent, Text::_('DATE_FORMAT_LC2')) . '</dd>';
                                echo $this->loadTemplate('attendees');
                            }else{
                                if(!empty($this->item->dates)) {
                                    $endDateEvent = strtotime($this->item->dates . ' ' . ($this->item->times ? $this->item->times : '23:59:59'));
                                    if($timeNow <= $endDateEvent){
                                        echo '<dt>' . Text::_('COM_JEM_EVENT_REGISTRATION_IS_UNTIL');
                                    } else {
                                        echo '<dt>' . Text::_('COM_JEM_EVENT_REGISTRATION_WAS_UNTIL');
                                    }
                                    echo '</dt><dd>' . HTMLHelper::_('date', $endDateEvent, Text::_('DATE_FORMAT_LC2')) . '</dd>';
                                    echo $this->loadTemplate('attendees');
                                }
                            }
                        }
                        break;
                } ?>
            </dl>
        <?php } ?>

        <?php if (!empty($this->item->pluginevent->onEventEnd)) : ?>
            <hr class="jem-hr">
            <?php echo $this->item->pluginevent->onEventEnd; ?>
        <?php endif; ?>

            <?php if ($this->params->get('showfootertext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('footertext'); ?>
        </div>
    <?php endif; ?>
    <div class="copyright">
            <?php echo JemOutput::footer(); ?>
        </div>
    </div>

<?php }

echo JemOutput::lightbox();
?>
