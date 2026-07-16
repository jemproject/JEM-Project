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
$venueCustomFieldsRows = JemCustomFields::renderDetailRows('venue', $this->venue, 'COM_JEM_VENUE_CUSTOM_FIELD', 'custom', true);
$renderVenueCustomFieldsBlock = function () use ($venueCustomFieldsRows) {
    if ($venueCustomFieldsRows === '') {
        return '';
    }

    return '<div class="jem-custom-fields jem-venue-custom-fields">'
        . '<dl class="jem-dl">' . $venueCustomFieldsRows . '</dl>'
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
$venueMapDisplay = (string) $this->params->get('venue_map_display', 'link_button');
if ($venueMapDisplay === 'hide') {
    $venueMapDisplay = 'none';
} elseif ($venueMapDisplay === 'global' || $venueMapDisplay === 'link') {
    $venueMapDisplay = 'link_button';
}
if (!in_array($venueMapDisplay, array('none', 'link_text', 'link_button', 'map'), true)) {
    $venueMapDisplay = 'link_button';
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

$venueMapEmbedUrl = '';
$venueMapExternalUrl = '';
if (is_numeric($this->venue->latitude ?? null) && is_numeric($this->venue->longitude ?? null)) {
    $lat = (float) $this->venue->latitude;
    $lon = (float) $this->venue->longitude;
    $bbox = ($lon - 0.005) . ',' . ($lat - 0.003) . ',' . ($lon + 0.005) . ',' . ($lat + 0.003);
    $venueMapEmbedUrl = 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode($bbox) . '&layer=mapnik&marker=' . rawurlencode($lat . ',' . $lon);
    $venueMapExternalUrl = 'https://www.openstreetmap.org/?mlat=' . rawurlencode((string) $lat) . '&mlon=' . rawurlencode((string) $lon) . '#map=16/' . rawurlencode((string) $lat) . '/' . rawurlencode((string) $lon);
}
$renderVenueMapLink = function ($mode = 'button') use ($venueMapEmbedUrl, $venueMapExternalUrl) {
    if ($venueMapExternalUrl === '') {
        return '';
    }

    $modalId = 'jem-venue-map-' . (int) $this->venue->id;
    $title = Text::_('COM_JEM_MAP') . ': ' . $this->escape($this->venue->venue);
    $modal = $venueMapEmbedUrl !== '' ? HTMLHelper::_(
            'bootstrap.renderModal',
            $modalId,
            array(
                'url'    => $venueMapEmbedUrl,
                'title'  => $title,
                'width'  => '900px',
                'height' => '560px',
                'footer' => '<a class="btn btn-primary" href="' . htmlspecialchars($venueMapExternalUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . Text::_('COM_JEM_OPEN_MAP') . '</a>'
                    . '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>',
            )
        ) : '';

    $label = '<i class="fa fa-map-marker" aria-hidden="true"></i> ' . Text::_('COM_JEM_VIEW_MAP');
    if ($mode === 'text' || $venueMapEmbedUrl === '') {
        $linkAttrs = $venueMapEmbedUrl !== ''
            ? 'href="#" data-bs-toggle="modal" data-bs-target="#' . htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') . '"'
            : 'href="' . htmlspecialchars($venueMapExternalUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener"';

        return $modal . '<a class="jem-venue-map-text-link" ' . $linkAttrs . '>' . $label . '</a>';
    }

    return $modal . '<button type="button" class="jem-venue-map-button btn btn-primary" data-bs-toggle="modal" data-bs-target="#' . htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') . '">' . $label . '</button>';
};

?>
<div id="jem" class="jem_venue<?php echo $this->pageclass_sfx . ' venue_id' . (int) $this->venue->id; ?>" itemscope="itemscope" itemtype="https://schema.org/Place">
    <div class="buttons">
        <?php
        $btn_params = array('id' => $this->venue->slug, 'slug' => $this->venue->slug, 'task' => $this->task, 'print_link' => $this->print_link, 'pdf_link' => $this->pdf_link, 'archive_link' => $this->archive_link);
        echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
        ?>
    </div>
    <style>
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

        #jem.jem_venue .jem-venue-overview-panel {
            align-items: center;
            background: #f8fafc;
            border: 1px solid #d6dde8;
            border-radius: 6px;
            padding: 1rem;
        }

        #jem.jem_venue .jem-venue-overview-details .jem-dl {
            display: grid;
            grid-template-columns: minmax(8rem, 18%) 1fr;
            gap: .8rem 1rem;
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

        #jem.jem_venue .jem-venue-map-section iframe {
            width: 100%;
            max-width: 100%;
            height: 350px;
            min-height: 350px;
            border: 0;
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

            #jem.jem_venue .jem-venue-overview-details .jem-dl {
                grid-template-columns: minmax(7rem, 34%) 1fr;
                gap: .65rem .75rem;
            }
        }
    </style>

    <?php if ($this->escape($this->params->get('show_page_heading', 1))) : ?>
    <h1 class="componentheading">
        <span><?php echo $this->escape($this->params->get('page_heading')); ?></span>
        <?php echo JemOutput::editbutton($this->venue, $this->params, NULL, $this->permissions->canEditVenue, 'venue'); ?>
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
    </h2>
  <?php if ($this->settings->get('global_show_detailsadress',1)) : ?>
  <div class="jem-row jem-venue-overview-panel">
    <div class="jem-info jem-venue-overview-details">
        <dl class="jem-dl" itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
          <dt class="title hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_TITLE'); ?>"><?php echo Text::_('COM_JEM_TITLE').':'; ?></dt>
          <dd class="title" itemprop="name">
            <?php echo $this->escape($this->venue->venue);?>
            <?php echo JemOutput::typedEntityBadge($this->venue, 'type_', 'venue'); ?>
          </dd>

          <?php if ($this->settings->get('global_show_detailsadress',1)) : ?>
          <?php if ($this->venue->street) : ?>
          <dt class="venue_street hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_STREET'); ?>"><?php echo Text::_('COM_JEM_STREET'); ?>:</dt>
          <dd class="venue_street" itemprop="streetAddress">
            <?php echo $this->escape($this->venue->street); ?>
          </dd>
          <?php endif; ?>

          <?php if ($this->venue->postalCode) : ?>
          <dt class="venue_postalCode hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_ZIP'); ?>"><?php echo Text::_('COM_JEM_ZIP'); ?>:</dt>
          <dd class="venue_postalCode" itemprop="postalCode">
            <?php echo $this->escape($this->venue->postalCode); ?>
          </dd>
          <?php endif; ?>

          <?php if ($this->venue->city) : ?>
          <dt class="venue_city hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_CITY'); ?>"><?php echo Text::_('COM_JEM_CITY'); ?>:</dt>
          <dd class="venue_city" itemprop="addressLocality">
            <?php echo $this->escape($this->venue->city); ?>
          </dd>
          <?php endif; ?>

          <?php if ($this->venue->district) : ?>
          <dt class="venue_district hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_DISTRICT'); ?>"><?php echo Text::_('COM_JEM_DISTRICT'); ?>:</dt>
          <dd class="venue_district">
            <?php echo $this->escape($this->venue->district); ?>
          </dd>
          <?php endif; ?>

          <?php if ($this->venue->level) : ?>
          <dt class="venue_level hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_VENUE_LEVEL'); ?>"><?php echo Text::_('COM_JEM_VENUE_LEVEL'); ?>:</dt>
          <dd class="venue_level">
            <?php echo $this->escape($this->venue->level); ?>
          </dd>
          <?php endif; ?>

          <?php if ((int) $this->venue->capacity > 0) : ?>
          <dt class="venue_capacity hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_VENUE_CAPACITY'); ?>"><?php echo Text::_('COM_JEM_VENUE_CAPACITY'); ?>:</dt>
          <dd class="venue_capacity">
            <?php echo number_format((int) $this->venue->capacity, 0, Text::_('DECIMALS_SEPARATOR'), Text::_('THOUSANDS_SEPARATOR')); ?>
          </dd>
          <?php endif; ?>

          <?php if ($this->venue->state) : ?>
          <dt class="venue_state hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_STATE'); ?>"><?php echo Text::_('COM_JEM_STATE'); ?>:</dt>
          <dd class="venue_state" itemprop="addressRegion">
            <?php echo $this->escape($this->venue->state); ?>
          </dd>
          <?php endif; ?>

          <?php if ($this->venue->country) : ?>
          <dt class="venue_country hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_COUNTRY'); ?>"><?php echo Text::_('COM_JEM_COUNTRY'); ?>:</dt>
          <dd class="venue_country">
            <?php echo $this->venue->countryimg ? $this->venue->countryimg : $this->escape($this->venue->country); ?>
            <meta itemprop="addressCountry" content="<?php echo $this->escape($this->venue->country); ?>" />
          </dd>
          <?php endif; ?>

          <?php if ($this->venue->email) : ?>
          <dt class="venue_email hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_VENUE_EMAIL'); ?>"><?php echo Text::_('COM_JEM_VENUE_EMAIL'); ?>:</dt>
          <dd class="venue_email" itemprop="email">
            <a href="mailto:<?php echo $this->escape($this->venue->email); ?>"><?php echo $this->escape($this->venue->email); ?></a>
          </dd>
          <?php endif; ?>

          <?php if ($this->venue->phone) : ?>
          <dt class="venue_phone hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_VENUE_PHONE'); ?>"><?php echo Text::_('COM_JEM_VENUE_PHONE'); ?>:</dt>
          <dd class="venue_phone" itemprop="telephone">
            <a href="tel:<?php echo $this->escape(preg_replace('/[^0-9+*#,;(). -]/', '', $this->venue->phone)); ?>"><?php echo $this->escape($this->venue->phone); ?></a>
          </dd>
          <?php endif; ?>

          <?php if ($this->venue->mobile) : ?>
          <dt class="venue_mobile hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_VENUE_MOBILE'); ?>"><?php echo Text::_('COM_JEM_VENUE_MOBILE'); ?>:</dt>
          <dd class="venue_mobile">
            <a href="tel:<?php echo $this->escape(preg_replace('/[^0-9+*#,;(). -]/', '', $this->venue->mobile)); ?>"><?php echo $this->escape($this->venue->mobile); ?></a>
          </dd>
          <?php endif; ?>

          <?php if ($venueShowMapLinkInDetails) : ?>
          <?php $venueMapLinkHtml = $renderVenueMapLink($venueMapDisplay === 'link_text' ? 'text' : 'button'); ?>
          <?php if ($venueMapLinkHtml !== '') : ?>
          <dt class="venue_mapicon hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_MAP'); ?>"><?php echo Text::_('COM_JEM_MAP'); ?>:</dt>
          <dd class="venue_mapicon"><?php echo $venueMapLinkHtml; ?></dd>
          <?php endif; ?>
          <?php endif; ?>

          <!-- PUBLISHING STATE -->
          <?php if (isset($this->venue->published) && !empty($this->show_status) && $venueShowStatus) : ?>
          <dt class="published hasTooltip" data-original-title="<?php echo Text::_('JSTATUS'); ?>"><?php echo Text::_('JSTATUS'); ?>:</dt>
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
          ?>
          <?php endif; ?>

          <?php if (($this->settings->get('global_show_detlinkvenue', 1)) && (!empty($this->venue->url))) : ?>
          <dt class="venue hasTooltip" data-original-title="<?php echo Text::_('COM_JEM_WEBSITE'); ?>"><?php echo Text::_('COM_JEM_WEBSITE'); ?>:</dt>
          <dd class="venue">
            <a href="<?php echo $this->escape($this->venue->url); ?>" target="_blank" rel="noopener"><?php echo $this->escape($this->venue->urlclean); ?></a>
          </dd>
          <?php endif; ?>

        </dl>
    </div>


    <?php if ($venueShowImage) : ?>
    <style>
      .jem-img {
        flex-basis: <?php echo $this->jemsettings->imagewidth; ?>px;
      }
    </style>
    <div class="jem-img jem-venue-overview-media">
      <?php echo JemOutput::flyer($this->venue, $this->limage, 'venue'); ?>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($venueShowDescription || $venueShowMapSection || $venueShowEvents) : ?>
    <div class="jem-venue-section-separator"></div>
  <?php endif; ?>
    
    <?php endif; ?>

    <?php if ($venueCustomFieldsPosition === 'before_description') : ?>
        <?php echo $renderVenueCustomFieldsBlock(); ?>
    <?php endif; ?>

    <?php if ($venueShowDescription) : ?>

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
            <?php if ($venueShowMapBlock && $venueMapEmbedUrl !== '') : ?>
                <iframe title="<?php echo $this->escape(Text::_('COM_JEM_MAP') . ': ' . $this->venue->venue); ?>" src="<?php echo htmlspecialchars($venueMapEmbedUrl, ENT_QUOTES, 'UTF-8'); ?>" loading="lazy"></iframe>
            <?php endif; ?>

            <?php if ($venueShowMapBlock && $venueMapEmbedUrl === '' && $venueMapExternalUrl !== '') : ?>
                <p><a class="jem-venue-map-text-link" href="<?php echo htmlspecialchars($venueMapExternalUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo Text::_('COM_JEM_VIEW_MAP'); ?></a></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($venueCustomFieldsPosition === 'after_links') : ?>
        <?php echo $renderVenueCustomFieldsBlock(); ?>
    <?php endif; ?>

    <?php $this->attachments = $this->venue->attachments; ?>
    <?php echo $this->loadTemplate('attachments'); ?>

    <?php if ($venueShowEvents) : ?>
        <div class="jem-venue-section-separator"></div>
        <!--table-->
      <h2 class="jem">
            <?php echo Text::_('COM_JEM_EVENTS'); ?>
        </h2>
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
