<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

require_once JPATH_SITE . '/components/com_jem/classes/customfields.class.php';

$venueCustomFieldsPosition = (string) $this->settings->get('global_venue_custom_fields_position', 'details');
if (!in_array($venueCustomFieldsPosition, array('details', 'before_description', 'after_description', 'after_links'), true)) {
    $venueCustomFieldsPosition = 'details';
}
$venueCustomFieldsRows = JemCustomFields::renderDetailRows('venue', $this->venue, 'COM_JEM_VENUE_CUSTOM_FIELD', 'custom');
$renderVenueCustomFieldsBlock = function () use ($venueCustomFieldsRows) {
    if ($venueCustomFieldsRows === '') {
        return '';
    }

    return '<div class="jem-custom-fields jem-venue-custom-fields">'
        . '<dl class="location">' . $venueCustomFieldsRows . '</dl>'
        . '</div>';
};

$venueHeadingDisplay = (string) $this->params->get('venue_heading_display', 'label_name');
if (!in_array($venueHeadingDisplay, array('label', 'label_name', 'name'), true)) {
    $venueHeadingDisplay = 'label_name';
}
$renderVenueHeading = function () use ($venueHeadingDisplay) {
    $label = Text::_('COM_JEM_VENUE');
    $name = $this->escape($this->venue->venue);

    if ($venueHeadingDisplay === 'name') {
        return $name;
    }

    if ($venueHeadingDisplay === 'label_name') {
        return $label . ' - ' . $name;
    }

    return $label;
};
$venueShowStatus = (int) $this->params->get('venue_show_status', 1) === 1;
$venueMapConfiguration = JemOutput::resolveVenueMapConfiguration(
    $this->params->get('venue_map_display', 'global'),
    $this->settings->get('global_show_mapserv', 0),
    JemHelper::isActiveMenuView('venue', (int) $this->venue->id)
);
$venueMapDisplay = $venueMapConfiguration['display'];
$venueMapService = $venueMapConfiguration['service'];
$venueMapSettings = $this->settings;
if ($venueMapService !== (int) $this->settings->get('global_show_mapserv', 0)) {
    $venueMapSettings = clone $this->settings;
    $venueMapSettings->set('global_show_mapserv', $venueMapService);
}
$venueShowMapLinkInDetails = in_array($venueMapDisplay, array('link_text', 'link_button'), true);
$venueShowMapBlock = $venueMapDisplay === 'map';
$venueShowMapSection = $venueShowMapLinkInDetails || $venueShowMapBlock;
$venueShowImage = (int) $this->params->get('venue_show_image', 1) === 1;
$venueShowDescription = (int) $this->params->get('venue_show_description', 1) === 1
    && $this->settings->get('global_show_locdescription', 1)
    && trim((string) $this->venuedescription) !== ''
    && trim((string) $this->venuedescription) !== '<br>';
$venueShowEvents = (int) $this->params->get('venue_show_events', 1) === 1
    && $this->settings->get('global_show_listevents', 1);

?>

<div id="jem" class="jem_venue<?php echo $this->pageclass_sfx . ' venue_id' . (int) $this->venue->id; ?>" itemscope="itemscope" itemtype="https://schema.org/Place">
    <style>
        #jem.jem_venue > .flyerimage {
            float: right !important;
            display: block;
            max-width: 100%;
            height: auto;
            margin: 0 0 1rem 1rem;
        }

        #jem.jem_venue > .flyerimage img,
        #jem.jem_venue > .flyerimage a {
            max-width: 100%;
            height: auto;
        }

        #jem.jem_venue .jem-venue-overview-panel {
            align-items: center;
            background: #f8fafc;
            border: 1px solid #d6dde8;
            border-radius: 6px;
            padding: 1rem;
        }

        #jem.jem_venue .jem-venue-overview-details dl.location {
            display: grid;
            grid-template-columns: minmax(8rem, 16%) 1fr;
            gap: .85rem 1rem;
            margin: 0;
        }

        #jem.jem_venue .jem-venue-overview-details dt,
        #jem.jem_venue .jem-venue-overview-details dd {
            margin: 0;
        }

        #jem.jem_venue .jem-venue-overview-media {
            align-self: center;
            text-align: right;
        }

        #jem.jem_venue .jem-venue-overview-media .flyerimage {
            float: none !important;
            margin: 0;
        }

        #jem.jem_venue .jem-venue-map-button {
            color: #fff;
            background-color: #1f5b99;
            border-color: #1f5b99;
            text-decoration: none;
            line-height: 1.2;
        }

        #jem.jem_venue .jem-venue-map-button:hover,
        #jem.jem_venue .jem-venue-map-button:focus {
            color: #fff;
            background-color: #174a7f;
            border-color: #174a7f;
        }

        #jem.jem_venue .jem-venue-map-section .jem-osm-map {
            width: 100%;
            max-width: 100%;
            height: 350px;
            min-height: 350px;
            border: 0;
        }

        #jem.jem_venue .jem-venue-description-break {
            clear: both;
        }

        #jem.jem_venue #jem_filter.jem-row {
            display: flex !important;
            flex-wrap: nowrap;
            align-items: center;
            gap: 0.5rem;
        }

        #jem.jem_venue #jem_filter.jem-row > .jem-row,
        #jem.jem_venue #jem_filter.jem-row > .jem-limit-smallest {
            display: flex !important;
            flex: 0 1 auto;
            flex-wrap: nowrap;
            align-items: center;
            width: auto !important;
            margin-bottom: 0;
        }

        #jem.jem_venue #jem_filter.jem-row > .jem-row:first-child {
            flex: 1 1 auto;
        }

        #jem.jem_venue #jem_filter.jem-row input#filter_search {
            flex: 1 1 14rem;
            width: auto !important;
            min-width: 10rem;
            max-width: 18rem;
        }

        #jem.jem_venue #jem_filter.jem-row input#filter_month {
            width: 13rem !important;
        }

        #jem.jem_venue #jem_filter.jem-row .jem-limit-smallest {
            margin-left: auto;
        }

        @media (max-width: 60rem) {
            #jem.jem_venue #jem_filter.jem-row {
                flex-wrap: wrap;
                align-items: stretch;
            }

            #jem.jem_venue #jem_filter.jem-row > .jem-row,
            #jem.jem_venue #jem_filter.jem-row > .jem-limit-smallest {
                flex: 1 1 100%;
            }

            #jem.jem_venue #jem_filter.jem-row input#filter_search {
                max-width: none;
            }

            #jem.jem_venue #jem_filter.jem-row .jem-limit-smallest {
                margin-left: 0;
            }

            #jem.jem_venue .jem-venue-overview-panel {
                display: flex;
                flex-direction: column;
                align-items: stretch;
            }

            #jem.jem_venue .jem-venue-overview-media {
                order: -1;
                width: 100%;
                margin-bottom: 1rem;
                text-align: center;
            }

            #jem.jem_venue .jem-venue-overview-media .flyerimage img,
            #jem.jem_venue .jem-venue-overview-media .flyerimage a,
            #jem.jem_venue .jem-venue-overview-media img {
                display: block;
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                object-fit: contain;
            }

            #jem.jem_venue .jem-venue-overview-details dl.location {
                grid-template-columns: minmax(7rem, 34%) 1fr;
                gap: .65rem .75rem;
            }
        }
    </style>
    <div class="buttons">
        <?php
        $btn_params = array('id' => $this->venue->slug, 'slug' => $this->venue->slug, 'task' => $this->task, 'print_link' => $this->print_link, 'pdf_link' => $this->pdf_link, 'archive_link' => $this->archive_link);
        echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
        ?>
    </div>

    <?php if ($this->escape($this->params->get('show_page_heading', 1))) : ?>
    <h1 class="componentheading">
        <span><?php echo $this->escape($this->params->get('page_heading')); ?></span>
    </h1>
    <?php endif; ?>

    <?php if ($this->params->get('showintrotext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('introtext'); ?>
        </div>
        <p> </p>
    <?php endif; ?>

    <!--Venue-->
    <h2 class="jem">
        <?php echo $renderVenueHeading(); ?>
        <?php echo JemOutput::editbutton($this->venue, $this->params, null, $this->permissions->canEditVenue, 'venue'); ?>
    </h2>

    <div class="jem-venue-overview-panel">
        <div class="jem-venue-overview-details">
        <dl class="location floattext" itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
            <dt class="title"><?php echo Text::_('COM_JEM_TITLE'); ?>:</dt>
            <dd class="title" itemprop="name">
                <?php echo $this->escape($this->venue->venue); ?>
                <?php echo JemOutput::typedEntityBadge($this->venue, 'type_', 'venue'); ?>
            </dd>

    <?php if ($this->settings->get('global_show_detailsadress', 1)) : ?>
            <?php if ($this->venue->street) : ?>
            <dt class="venue_street"><?php echo Text::_('COM_JEM_STREET'); ?>:</dt>
            <dd class="venue_street" itemprop="streetAddress">
                <?php echo $this->escape($this->venue->street); ?>
            </dd>
            <?php endif; ?>

            <?php if ($this->venue->postalCode) : ?>
            <dt class="venue_postalCode"><?php echo Text::_('COM_JEM_ZIP'); ?>:</dt>
            <dd class="venue_postalCode" itemprop="postalCode">
                <?php echo $this->escape($this->venue->postalCode); ?>
            </dd>
            <?php endif; ?>

            <?php if ($this->venue->city) : ?>
            <dt class="venue_city"><?php echo Text::_('COM_JEM_CITY'); ?>:</dt>
            <dd class="venue_city" itemprop="addressLocality">
                <?php echo $this->escape($this->venue->city); ?>
            </dd>
            <?php endif; ?>

            <?php if ($this->venue->district) : ?>
            <dt class="venue_district"><?php echo Text::_('COM_JEM_DISTRICT'); ?>:</dt>
            <dd class="venue_district">
                <?php echo $this->escape($this->venue->district); ?>
            </dd>
            <?php endif; ?>

            <?php if ($this->venue->level) : ?>
            <dt class="venue_level"><?php echo Text::_('COM_JEM_VENUE_LEVEL'); ?>:</dt>
            <dd class="venue_level">
                <?php echo $this->escape($this->venue->level); ?>
            </dd>
            <?php endif; ?>

            <?php if ((int) $this->venue->capacity > 0) : ?>
            <dt class="venue_capacity"><?php echo Text::_('COM_JEM_VENUE_CAPACITY'); ?>:</dt>
            <dd class="venue_capacity">
                <?php echo number_format((int) $this->venue->capacity, 0, Text::_('DECIMALS_SEPARATOR'), Text::_('THOUSANDS_SEPARATOR')); ?>
            </dd>
            <?php endif; ?>

            <?php if ($this->venue->state) : ?>
            <dt class="venue_state"><?php echo Text::_('COM_JEM_STATE'); ?>:</dt>
            <dd class="venue_state" itemprop="addressRegion">
                <?php echo $this->escape($this->venue->state); ?>
            </dd>
            <?php endif; ?>

            <?php if ($this->venue->country) : ?>
            <dt class="venue_country"><?php echo Text::_('COM_JEM_COUNTRY'); ?>:</dt>
            <dd class="venue_country">
                <?php echo $this->venue->countryimg ? $this->venue->countryimg : $this->escape($this->venue->country); ?>
                <meta itemprop="addressCountry" content="<?php echo $this->escape($this->venue->country); ?>" />
            </dd>
            <?php endif; ?>

            <?php if ($this->venue->timezone) : ?>
            <dt class="venue_timezone"><?php echo Text::_('COM_JEM_VENUE_TIMEZONE'); ?>:</dt>
            <dd class="venue_timezone"><?php echo $this->escape($this->venue->timezone); ?></dd>
            <?php endif; ?>

            <?php if ($this->venue->email) : ?>
            <dt class="venue_email"><?php echo Text::_('COM_JEM_VENUE_EMAIL'); ?>:</dt>
            <dd class="venue_email" itemprop="email">
                <a href="mailto:<?php echo $this->escape($this->venue->email); ?>"><?php echo $this->escape($this->venue->email); ?></a>
            </dd>
            <?php endif; ?>

            <?php if ($this->venue->phone) : ?>
            <dt class="venue_phone"><?php echo Text::_('COM_JEM_VENUE_PHONE'); ?>:</dt>
            <dd class="venue_phone" itemprop="telephone">
                <a href="tel:<?php echo $this->escape(preg_replace('/[^0-9+*#,;(). -]/', '', $this->venue->phone)); ?>"><?php echo $this->escape($this->venue->phone); ?></a>
            </dd>
            <?php endif; ?>

            <?php if ($this->venue->mobile) : ?>
            <dt class="venue_mobile"><?php echo Text::_('COM_JEM_VENUE_MOBILE'); ?>:</dt>
            <dd class="venue_mobile">
                <a href="tel:<?php echo $this->escape(preg_replace('/[^0-9+*#,;(). -]/', '', $this->venue->mobile)); ?>"><?php echo $this->escape($this->venue->mobile); ?></a>
            </dd>
            <?php endif; ?>

            <?php if ($venueShowMapLinkInDetails) : ?>
            <?php $venueMapLinkHtml = JemOutput::mapicon($this->venue, null, $venueMapSettings, $venueMapDisplay); ?>
            <?php if (!empty($venueMapLinkHtml)) : ?>
            <dt class="venue_mapicon"><?php echo Text::_('COM_JEM_MAP'); ?>:</dt>
            <dd class="venue_mapicon"><?php echo $venueMapLinkHtml; ?></dd>
            <?php endif; ?>
            <?php endif; ?>

            <!-- PUBLISHING STATE -->
            <?php if (isset($this->venue->published) && !empty($this->show_status) && $venueShowStatus) : ?>
            <dt class="published"><?php echo Text::_('JSTATUS'); ?>:</dt>
            <dd class="published">
                <?php switch ($this->venue->published) {
                case  1: echo Text::_('JPUBLISHED');   break;
                case  0: echo Text::_('JUNPUBLISHED'); break;
                case  2: echo Text::_('JARCHIVED');    break;
                case -2: echo Text::_('JTRASHED');     break;
                } ?>
            </dd>
            <?php endif; ?>

            <?php
            if ($venueCustomFieldsPosition === 'details') {
                echo $venueCustomFieldsRows;
            }
            endif; ?>

            <?php if (($this->settings->get('global_show_detlinkvenue', 1)) && (!empty($this->venue->url))) : ?>
            <dt class="venue"><?php echo Text::_('COM_JEM_WEBSITE'); ?>:</dt>
            <dd class="venue">
                <a href="<?php echo $this->escape($this->venue->url); ?>" target="_blank" rel="noopener"><?php echo $this->escape($this->venue->urlclean); ?></a>
            </dd>
            <?php endif; ?>
        </dl>
        </div>
        <?php if ($venueShowImage) : ?>
        <div class="jem-venue-overview-media">
            <?php echo JemOutput::flyer($this->venue, $this->limage, 'venue'); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($venueShowDescription || $venueShowMapSection || $venueShowEvents) : ?>
        <div class="jem-venue-section-separator"></div>
    <?php endif; ?>

    <?php if ($venueCustomFieldsPosition === 'before_description') : ?>
        <?php echo $renderVenueCustomFieldsBlock(); ?>
    <?php endif; ?>

    <?php if ($venueShowDescription) : ?>

        <div class="jem-venue-description-break"></div>
        <h2 class="description"><?php echo Text::_('COM_JEM_VENUE_DESCRIPTION'); ?></h2>
        <div class="description no_space floattext" itemprop="description">
            <?php echo $this->venuedescription; ?>
        </div>
    <?php endif; ?>

    <?php if ($venueCustomFieldsPosition === 'after_description') : ?>
        <?php echo $renderVenueCustomFieldsBlock(); ?>
    <?php endif; ?>

    <?php if ($venueShowMapSection) : ?>
        <div class="jem-venue-map-section">
            <?php if ($venueShowMapBlock && in_array($venueMapService, array(2, 5), true)) : ?>
                <?php echo JemOutput::mapicon($this->venue, null, $venueMapSettings); ?>
            <?php endif; ?>

            <?php if ($venueShowMapBlock && $venueMapService === 3) : ?>
                <input type="hidden" id="latitude" value="<?php echo $this->escape($this->venue->latitude); ?>">
                <input type="hidden" id="longitude" value="<?php echo $this->escape($this->venue->longitude); ?>">
                <input type="hidden" id="venue" value="<?php echo $this->escape($this->venue->venue); ?>">
                <input type="hidden" id="street" value="<?php echo $this->escape($this->venue->street); ?>">
                <input type="hidden" id="city" value="<?php echo $this->escape($this->venue->city); ?>">
                <input type="hidden" id="state" value="<?php echo $this->escape($this->venue->state); ?>">
                <input type="hidden" id="postalCode" value="<?php echo $this->escape($this->venue->postalCode); ?>">
                <?php echo JemOutput::mapicon($this->venue, null, $venueMapSettings); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($venueCustomFieldsPosition === 'after_links') : ?>
        <?php echo $renderVenueCustomFieldsBlock(); ?>
    <?php endif; ?>

    <?php $this->attachments = $this->venue->attachments; ?>
    <?php require JPATH_COMPONENT . '/common/hierarchy/venue_tree.php'; ?>
    <?php echo $this->loadTemplate('attachments'); ?>

    <?php if ($venueShowEvents) : ?>
        <div class="jem-venue-section-separator"></div>
        <!--table-->
        <form action="<?php echo htmlspecialchars($this->action); ?>" method="post" id="adminForm">
            <?php echo $this->loadTemplate('events_table'); ?>
            <input type="hidden" name="option" value="com_jem" />
            <input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
            <input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
            <input type="hidden" name="view" value="venue" />
            <input type="hidden" name="id" value="<?php echo (int) $this->venue->id; ?>" />
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>

        <!--pagination-->
        <div class="pagination">
            <?php echo $this->pagination->getPagesLinks(); ?>
        </div>

    <?php endif; ?>

    <!--copyright-->
        <?php if ($this->params->get('showfootertext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('footertext'); ?>
        </div>
    <?php endif; ?>
    <div class="copyright">
        <?php echo JemOutput::footer(); ?>
    </div>
</div>

<?php echo JemOutput::lightbox(); ?>
