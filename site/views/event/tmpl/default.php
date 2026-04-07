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

// Add expiration date, if old events will be archived or removed
if ($jemsettings->oldevent > 0) {
    $enddate = strtotime($this->item->enddates?:($this->item->dates?:date("Y-m-d")));
    $expDate = date("D, d M Y H:i:s", strtotime('+1 day', $enddate));
    $document->addCustomTag('<meta http-equiv="expires" content="' . $expDate . '"/>');
}

?>

<style>
    .jem-flyer-event,
    .jem-flyer-venue,
    #jem img[align="right"],
    #jem img[align="left"] {
        display: block;
        margin: 0 auto 15px auto;
        float: none !important; /* Prevents text wrapping */
        max-width: 100%;
        height: auto;
    }

    .jem-contact-legacy { margin-top: 20px; width: 100%; }

    .contact-category-title {
        padding: 10px 0;
        font-size: 1.3em;
        border-bottom: 2px solid #d1d6ad;
        margin-bottom: 20px;
        color: #333;
        font-weight: bold;
    }

    .contact-item-wrapper {
        display: flex;
        flex-wrap: wrap;
        border-bottom: 1px solid #eee;
        padding-bottom: 15px;
        margin-bottom: 15px;
    }

    .con-main-info { flex: 1; min-width: 200px; margin: 0; }
    .con-name { font-size: 1.1em; font-weight: bold; display: block; }
    .con-position { font-style: italic; color: #555; display: block; margin-top: 2px; font-weight: normal; }

    .con-details {
        flex: 0 0 auto;
        margin: 0;
        font-size: 0.9em;
        color: #333;
        text-align: right;
    }
    .con-details span { display: block; margin-bottom: 4px; }

    .con_description {
        flex: 1 0 100%;
        margin: 12px 0 0 0;
        color: #555;
        font-size: 0.95em;
        line-height: 1.5;
        padding: 0 5px;
    }

    @media (max-width: 767px) {

        .jem-flyer-event, .jem-flyer-venue,
        .event_id img, .venue_id img,
        .venue_id1 div.flyerimage,
        .venue_id1 div.flyerimage a.flyermodal,
        .venue_id1 div.flyerimage img {
            display: block !important;
            float: none !important;
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 auto 20px auto !important;
            height: auto !important;
            clear: both !important;
        }

        .venue_id1 dl.location {
            display: block !important;
            width: 100% !important;
            clear: both !important;
            overflow: hidden;
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }

        .venue_id1 dt {
            float: left !important;
            width: 35% !important; /* Label width */
            font-weight: bold;
            clear: left !important; /* Force new line for each pair */
            margin: 5px 0 !important;
        }

        .venue_id1 dd {
            float: left !important;
            width: 65% !important; /* Data width */
            margin: 5px 0 !important;
        }

        .contact-item-wrapper { flex-direction: column; align-items: flex-start; }

        .con-main-info, .con-details,
        .contact-info, .product-owner-section, .contact-details {
            width: 100% !important;
            text-align: left !important;
            float: none !important;
            margin-bottom: 10px;
        }

        .con-details span, .contact-details a, .contact-details span {
            display: block !important;
            width: 100%;
            margin-bottom: 8px;
        }

        img.venue_country_flag {
            display: inline-block !important;
            width: auto !important;
            max-width: 32px !important;
            vertical-align: middle !important;
            float: none !important;
        }

        .event_info, .location { clear: both; display: block; }
    }
</style>

<?php if ($params->get('access-view')) { /* This will show nothings otherwise - ??? */ ?>
    <div id="jem" class="event_id<?php echo $this->item->did; ?> jem_event<?php echo $this->pageclass_sfx;?>"
         itemscope="itemscope" itemtype="https://schema.org/Event">

        <meta itemprop="url" content="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getEventRoute($this->item->slug)); ?>" />
        <meta itemprop="identifier" content="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getEventRoute($this->item->slug)); ?>" />

        <div class="buttons">
            <?php
            $btn_params = array('slug' => $this->item->slug, 'print_link' => $this->print_link);
            echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
            ?>
        </div>

        <div class="clr"> </div>

        <?php if ($this->params->get('show_page_heading', 1)) : ?>
            <h1 class="componentheading">
                <?php echo $this->escape($this->params->get('page_heading')); ?>
            </h1>
        <?php endif; ?>

        <div class="clr"> </div>

        <!-- Event -->
        <h2 class="jem">
        <span style="white-space: nowrap;">
            <?php
            echo Text::_('COM_JEM_EVENT') . JemOutput::recurrenceicon($this->item) .' ';
            if($this->item_root) {
                echo JemOutput::editbutton($this->item_root, $params, $attribs, $this->permissions->canEditEvent, 'editevent') . ' ';
            }
            if(!$this->item_root || ($this->item_root && $this->item->recurrence_first_id)) {
                echo JemOutput::editbutton($this->item, $params, $attribs, $this->permissions->canEditEvent, 'editevent') . ' ';
            }
            echo JemOutput::copybutton($this->item, $params, $attribs, $this->permissions->canAddEvent, 'editevent');
            ?>
        </span>
        </h2>

        <?php echo JemOutput::flyer($this->item, $this->dimage, 'event'); ?>

        <dl class="event_info floattext">
            <?php if ($params->get('event_show_detailstitle',1)) : ?>
                <dt class="title"><?php echo Text::_('COM_JEM_TITLE'); ?>:</dt>
                <dd class="title" itemprop="name"><?php echo $this->escape($this->item->title); ?></dd>
            <?php else : ?>
                <meta itemprop="name" content="<?php echo $this->escape($this->item->title); ?>" />
            <?php endif; ?>
            <dt class="when"><?php echo Text::_('COM_JEM_WHEN'); ?>:</dt>
            <dd class="when">
                <?php
                echo JemOutput::formatLongDateTime($this->item->dates, $this->item->times,$this->item->enddates, $this->item->endtimes);
                echo JemOutput::formatSchemaOrgDateTime($this->item->dates, $this->item->times,$this->item->enddates, $this->item->endtimes);
                ?>
            </dd>
            <?php if (($this->item->locid != 0) && ($params->get('event_show_venue_name') == 1)) : ?>
                <dt class="where"><?php echo Text::_('COM_JEM_WHERE'); ?>:</dt>
                <dd class="where"><?php
                    if (($params->get('event_show_detlinkvenue') == 1) && (!empty($this->item->url))) :
                        ?><a target="_blank" href="<?php echo $this->item->url; ?>"><?php echo $this->escape($this->item->venue); ?></a><?php
                    elseif (($params->get('event_show_detlinkvenue') == 2) && (!empty($this->item->venueslug))) :
                        ?><a href="<?php echo Route::_(JemHelperRoute::getVenueRoute($this->item->venueslug)); ?>"><?php echo $this->item->venue; ?></a><?php
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
            <?php endif;

            if (empty($this->item->locid)) : ?>
                <div itemtype="https://schema.org/Place" itemscope itemprop="location" style="display: none;">
                    <meta itemprop="name" content="None"/>
                </div>

            <?php else : ?>
                <div itemtype="https://schema.org/Place" itemscope itemprop="location" style="display: none;">
                    <meta itemprop="name" content="<?php echo $this->escape($this->item->venue); ?>" />
                    <div itemprop="address" itemscope itemtype="https://schema.org/PostalAddress" style="display: none;">
                        <?php if ($this->item->street) : ?>
                            <meta itemprop="streetAddress" content="<?php echo $this->escape($this->item->street); ?>">
                        <?php endif; ?>
                        <?php if ($this->item->postalCode) : ?>
                            <meta itemprop="postalCode" content="<?php echo $this->escape($this->item->postalCode); ?>">
                        <?php endif; ?>
                        <?php if ($this->item->city) : ?>
                            <meta itemprop="addressLocality" content="<?php echo $this->escape($this->item->city); ?>">
                        <?php endif; ?>
                        <?php if ($this->item->state) : ?>
                            <meta itemprop="addressRegion" content="<?php echo $this->escape($this->item->state); ?>">
                        <?php endif; ?>
                        <?php if ($this->item->country) : ?>
                            <meta itemprop="addressCountry" content="<?php echo $this->escape($this->item->country); ?>">
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif;

            $n = is_array($this->categories) ? count($this->categories) : 0;
            if ($params->get('event_show_category') == 1) : ?>

            <dt class="category"><?php echo $n < 2 ? Text::_('COM_JEM_CATEGORY') : Text::_('COM_JEM_CATEGORIES'); ?>:</dt>
            <dd class="category">
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

                for ($cr = 1; $cr <= 10; $cr++) {
                $currentRow = $this->item->{'custom'.$cr};
                if (preg_match('%^http(s)?://%', $currentRow)) {
                    $currentRow = '<a href="'.$this->escape($currentRow).'" target="_blank">'.$this->escape($currentRow).'</a>';
                }
                if ($currentRow) {
                ?>
            <dt class="custom<?php echo $cr; ?>"><?php echo Text::_('COM_JEM_EVENT_CUSTOM_FIELD'.$cr); ?>:</dt>
            <dd class="custom<?php echo $cr; ?>"><?php echo $currentRow; ?></dd>
        <?php
        }
        }
        ?>

            <?php if ($params->get('event_show_hits')) : ?>
                <dt class="hits"><?php echo Text::_('COM_JEM_EVENT_HITS_LABEL'); ?>:</dt>
                <dd class="hits"><?php echo Text::sprintf('COM_JEM_EVENT_HITS', $this->item->hits); ?></dd>
            <?php endif; ?>


            <!-- AUTHOR -->
            <?php if ($params->get('event_show_author') && !empty($this->item->author)) : ?>
                <dt class="createdby"><?php echo Text::_('COM_JEM_EVENT_CREATED_BY_LABEL'); ?>:</dt>
                <dd class="createdby">
                    <?php $author = $this->item->created_by_alias ? $this->item->created_by_alias : $this->item->author; ?>
                    <?php if (!empty($this->item->contactid2) && $params->get('event_link_author') == true) :
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
            <?php if (!empty($this->showeventstate) && isset($this->item->published)) : ?>
                <dt class="published"><?php echo Text::_('JSTATUS'); ?>:</dt>
                <dd class="published">
                    <?php switch ($this->item->published) {
                        case  1: echo Text::_('JPUBLISHED');   break;
                        case  0: echo Text::_('JUNPUBLISHED'); break;
                        case  2: echo Text::_('JARCHIVED');    break;
                        case -2: echo Text::_('JTRASHED');     break;
                    } ?>
                </dd>
            <?php endif; ?>
        </dl>

        <!-- DESCRIPTION -->
        <?php if ($params->get('event_show_description','1') && ($this->item->fulltext != '' && $this->item->fulltext != '<br>' || $this->item->introtext != '' && $this->item->introtext != '<br>')) { ?>
            <h2 class="description"><?php echo Text::_('COM_JEM_EVENT_DESCRIPTION'); ?></h2>
            <div class="description event_desc" itemprop="description">

                <?php
                if ($params->get('access-view')) {
                    if (!$params->get('event_show_intro') && $this->item->fulltext != null) {
                        echo $this->item->fulltext;
                    } else {
                        echo $this->item->text;
                    }
					
                   if (!empty($this->event_links)) : ?>
                        <div class="jem-event-links mt-3 mb-3">
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($this->event_links as $link) : ?>
                                    <?php
                                        $target = $link->target ?? '_blank';
                                        $rel    = ($target === '_blank') ? 'rel="noopener noreferrer"' : '';
                                    ?>
                                    <a href="<?php echo $link->url; ?>"
                                       target="<?php echo $target; ?>"
                                       class="<?php echo $link->custom_class ?: 'btn btn-outline-primary'; ?>"
                                       <?php echo $rel; ?>>

                                        <?php if (!empty($link->image)) : ?>
                                            <img src="<?php echo $link->image; ?>" alt="" style="height: 1.2em;" class="me-1" />
                                        <?php elseif (!empty($link->icon)) : ?>
                                            <span class="<?php echo $link->icon; ?> me-1" aria-hidden="true"></span>
                                        <?php endif; ?>

                                        <?php echo htmlspecialchars($link->title); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
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

        <!-- CONTACTS -->
        <?php
        $showContactCategory = $params->get('event_show_contact_category');
        $showContactDesc     = $params->get('event_show_contact_description');

        $rawFields = $params->get('contact_fields', ['position', 'website', 'country']);
        $selectedFields = array_map('trim', is_string($rawFields) ? explode(',', $rawFields) : (array) $rawFields);

        if ($params->get('event_show_contact') && !empty($this->contacts)) :
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

            <div class="jem-contact-legacy">
                <?php foreach ($displayGroups as $categoryTitle => $contactList) : ?>

                    <div class="contact-group">
                        <?php if ($showContactCategory && $categoryTitle !== 'NO_CAT_HEADER') : ?>
                            <h3 class="contact-category-title">
                                <i class="icon-users"></i> <?php echo $this->escape($categoryTitle); ?>
                            </h3>
                        <?php endif; ?>

                        <?php foreach ($contactList as $contact) : ?>
                            <dl class="contact-item-wrapper">
                                <dt class="con-main-info">
                                    <span class="con-name">
                                        <?php if ($params->get('event_show_contact_link', 0) && !empty($contact->conid)) : ?>
                                            <a href="<?php echo Route::_('index.php?option=com_contact&view=contact&id=' . $contact->conid); ?>"
                                               title="<?php echo Text::_('COM_JEM_EVENT_CONTACT_SEND_MESSAGE'); ?>">
                                                <?php echo $this->escape($contact->conname); ?>
                                                <i class="icon-out-2" style="font-size: 0.8em; margin-left: 5px;"></i>
                                            </a>
                                        <?php else : ?>
                                            <?php echo $this->escape($contact->conname); ?>
                                        <?php endif; ?>
                                    </span>
                                    <?php if (in_array('position', $selectedFields) && !empty($contact->conposition)) : ?>
                                         <span class="con-position"><i class="fas fa-briefcase"></i> <?php echo $this->escape($contact->conposition); ?></span>
                                    <?php endif; ?>
                                </dt>

                                <dd class="con-details">
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
                                </dd>
                            </dl>

                            <?php if ($showContactDesc && !empty($contact->condescription)) : ?>
                                <div class="contact-item-wrapper">
                                    <div class="con_description">
                                        <?php echo $contact->condescription; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>


        <?php $this->attachments = $this->item->attachments; ?>
        <?php echo $this->loadTemplate('attachments'); ?>

        <!--      Venue  -->
        <?php if (($this->item->locid != 0) && !empty($this->item->venue) && $params->get('event_show_venue', '1')) : ?>
            <p></p>
            <hr />
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
            <h2 class="location">
                <?php
                echo Text::_('COM_JEM_VENUE') ;
                $itemid = $this->item ? $this->item->id : 0 ;
                echo JemOutput::editbutton($this->item, $params, $attribs, $this->permissions->canEditVenue, 'editvenue');
                echo JemOutput::copybutton($this->item, $params, $attribs, $this->permissions->canAddVenue, 'editvenue');
                ?>
            </h2>
            <?php echo JemOutput::flyer($this->item, $this->limage, 'venue'); ?>

            <dl class="location">
                <dt class="venue"><?php echo Text::_('COM_JEM_LOCATION'); ?>:</dt>
                <dd class="venue">
                    <?php
                    if (!empty($this->item->venueslug)) :
                        echo '<a href="' . Route::_(JemHelperRoute::getVenueRoute($this->item->venueslug)) . '">' . $this->escape($this->item->venue) . '</a>';
                    else :
                        echo $this->escape($this->item->venue);
                    endif;
                    if (!empty($this->item->url)) :
                        echo '&nbsp;-&nbsp;<a target="_blank" href="' . $this->item->url . '">' . Text::_('COM_JEM_WEBSITE') . '</a>';
                    endif;
                    echo $venueaccess;
                    ?>
                </dd>
            </dl>
            <?php if($this->item->user_has_access_venue) : ?>
                <?php if ($params->get('event_show_detailsadress', '1')) : ?>
                    <dl class="location floattext" itemprop="address" itemscope
                        itemtype="https://schema.org/PostalAddress">
                        <?php if ($this->item->street) : ?>
                            <dt class="venue_street"><?php echo Text::_('COM_JEM_STREET'); ?>:</dt>
                            <dd class="venue_street" itemprop="streetAddress">
                                <?php echo $this->escape($this->item->street); ?>
                            </dd>
                        <?php endif; ?>

                        <?php if ($this->item->postalCode) : ?>
                            <dt class="venue_postalCode"><?php echo Text::_('COM_JEM_ZIP'); ?>:</dt>
                            <dd class="venue_postalCode" itemprop="postalCode">
                                <?php echo $this->escape($this->item->postalCode); ?>
                            </dd>
                        <?php endif; ?>

                        <?php if ($this->item->city) : ?>
                            <dt class="venue_city"><?php echo Text::_('COM_JEM_CITY'); ?>:</dt>
                            <dd class="venue_city" itemprop="addressLocality">
                                <?php echo $this->escape($this->item->city); ?>
                            </dd>
                        <?php endif; ?>

                        <?php if ($this->item->state) : ?>
                            <dt class="venue_state"><?php echo Text::_('COM_JEM_STATE'); ?>:</dt>
                            <dd class="venue_state" itemprop="addressRegion">
                                <?php echo $this->escape($this->item->state); ?>
                            </dd>
                        <?php endif; ?>

                        <?php if ($this->item->country) : ?>
                            <dt class="venue_country"><?php echo Text::_('COM_JEM_COUNTRY'); ?>:</dt>
                            <dd class="venue_country">
                                <?php echo $this->item->countryimg ? $this->item->countryimg : $this->item->country; ?>
                                <meta itemprop="addressCountry" content="<?php echo $this->item->country; ?>" />
                            </dd>
                        <?php endif; ?>

                        <!-- PUBLISHING STATE -->
                        <?php if (!empty($this->showvenuestate) && isset($this->item->locpublished)) : ?>
                            <dt class="venue_published"><?php echo Text::_('JSTATUS'); ?>:</dt>
                            <dd class="venue_published">
                                <?php switch ($this->item->locpublished) {
                                    case  1: echo Text::_('JPUBLISHED');   break;
                                    case  0: echo Text::_('JUNPUBLISHED'); break;
                                    case  2: echo Text::_('JARCHIVED');    break;
                                    case -2: echo Text::_('JTRASHED');     break;
                                } ?>
                            </dd>
                        <?php endif; ?>

                        <?php
                        for ($cr = 1; $cr <= 10; $cr++) {
                            $currentRow = $this->item->{'venue'.$cr};
                            if (preg_match('%^http(s)?://%', $currentRow)) {
                                $currentRow = '<a href="' . $this->escape($currentRow) . '" target="_blank">' . $this->escape($currentRow) . '</a>';
                            }
                            if ($currentRow) {
                                ?>
                                <dt class="custom<?php echo $cr; ?>"><?php echo Text::_('COM_JEM_VENUE_CUSTOM_FIELD'.$cr); ?>:</dt>
                                <dd class="custom<?php echo $cr; ?>"><?php echo $currentRow; ?></dd>
                                <?php
                            }
                        }
                        ?>

                        <?php if ($params->get('event_show_mapserv') == 1 || $params->get('event_show_mapserv') == 4) : ?>
                            <?php echo JemOutput::mapicon($this->item, 'event', $params); ?>
                        <?php endif; ?>
                    </dl>

                    <?php if ($params->get('event_show_mapserv') == 2 || $params->get('event_show_mapserv') == 5) : ?>
                        <div class="jem-map">
                            <?php echo JemOutput::mapicon($this->item, 'event', $params); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($params->get('event_show_mapserv') == 3) : ?>
                        <input type="hidden" id="latitude" value="<?php echo $this->item->latitude; ?>">
                        <input type="hidden" id="longitude" value="<?php echo $this->item->longitude; ?>">
                        <input type="hidden" id="venue" value="<?php echo $this->item->venue; ?>">
                        <input type="hidden" id="street" value="<?php echo $this->item->street; ?>">
                        <input type="hidden" id="city" value="<?php echo $this->item->city; ?>">
                        <input type="hidden" id="state" value="<?php echo $this->item->state; ?>">
                        <input type="hidden" id="postalCode" value="<?php echo $this->item->postalCode; ?>">

                        <?php echo JemOutput::mapicon($this->item, 'event', $params); ?>
                    <?php endif; ?>
                <?php endif; /* event_show_detailsadress */ ?>

                <?php if ($params->get('event_show_locdescription', '1') && $this->item->locdescription != ''
                    && $this->item->locdescription != '<br>') : ?>
                    <h2 class="location_desc"><?php echo Text::_('COM_JEM_VENUE_DESCRIPTION'); ?></h2>
                    <div class="description location_desc" itemprop="description">
                        <?php echo $this->item->locdescription; ?>
                    </div>
                <?php endif; ?>

                <?php $this->attachments = $this->item->vattachments; ?>
                <?php echo $this->loadTemplate('attachments'); ?>

                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Registration -->
        <?php if ($this->showAttendees && $params->get('event_show_registration', '1')) : ?>
            <hr class="jem-hr">

            <?php
            $timeNow = time();

            switch ($this->e_reg) {
                case 0:
                    //Event without registration (NO)
                    break;
                case 1:
                    //Event with registration (YES with or witout UNTIL)
                    echo '<h2 class="register">' . Text::_('COM_JEM_REGISTRATION') . '</h2>';
                    echo $this->loadTemplate('attendees');
                    if($this->dateUnregistationUntil) {
                        echo ($this->allowAnnulation? Text::_('COM_JEM_EVENT_ANNULATION_NOTWILLBE_FROM') : Text::_('COM_JEM_EVENT_ANNULATION_ISNOT_FROM')) . ' ' . HTMLHelper::_('date', $this->dateUnregistationUntil, Text::_('DATE_FORMAT_LC2'));
                    }
                    break;
                case 2:
                    //Event with date starting registration (FROM with or witout UNTIL)
                    echo '<h2 class="register">' . Text::_('COM_JEM_REGISTRATION') . '</h2>';
                    if($this->dateRegistationFrom > $timeNow) {
                        echo Text::_('COM_JEM_EVENT_REGISTRATION_WILLBE_FROM') . ' ' . HTMLHelper::_('date', $this->dateRegistationFrom, Text::_('DATE_FORMAT_LC2'));
                    }else if ($this->allowRegistration) {
                        echo Text::_('COM_JEM_EVENT_REGISTRATION_IS_FROM') . ' ' . HTMLHelper::_('date', $this->dateRegistationFrom, Text::_('DATE_FORMAT_LC2'));
                        if($this->dateRegistationUntil){
                            echo " " . mb_strtolower(Text::_('COM_JEM_UNTIL')) . ' ' . HTMLHelper::_('date', $this->dateRegistationUntil, Text::_('DATE_FORMAT_LC2'));
                        }
                        echo $this->loadTemplate('attendees');

                        //Event with date starting annulation
                        if($this->dateUnregistationUntil) {
                            echo "<br>" . ($this->allowAnnulation? Text::_('COM_JEM_EVENT_ANNULATION_NOTWILLBE_FROM') : Text::_('COM_JEM_EVENT_ANNULATION_ISNOT_FROM')) . ' ' . HTMLHelper::_('date', $this->dateUnregistationUntil, Text::_('DATE_FORMAT_LC2'));
                        }
                    }else if($this->dateRegistationUntil !== false && $this->dateRegistationUntil < $timeNow) {
                        echo Text::_('COM_JEM_EVENT_REGISTRATION_WAS_UNTIL') . ' ' . HTMLHelper::_('date', $this->dateRegistationUntil, Text::_('DATE_FORMAT_LC2'));
                        echo $this->loadTemplate('attendees');

                        //Event with date starting annulation
                        if($this->dateUnregistationUntil) {
                            echo ($this->allowAnnulation? Text::_('COM_JEM_EVENT_ANNULATION_NOTWILLBE_FROM') : Text::_('COM_JEM_EVENT_ANNULATION_ISNOT_FROM')) . ' ' . HTMLHelper::_('date', $this->dateUnregistationUntil, Text::_('DATE_FORMAT_LC2'));
                        }
                    } else {
                        // open registration to the end of event
                        if($this->item->enddates){
                            $endDateEvent = strtotime($this->item->enddates . ' ' . ($this->item->endtimes ? $this->item->endtimes : '23:59:59'));
                            if($timeNow <= $endDateEvent){
                                echo Text::_('COM_JEM_EVENT_REGISTRATION_IS_UNTIL');
                            } else {
                                echo Text::_('COM_JEM_EVENT_REGISTRATION_WAS_UNTIL');
                            }
                            echo ' ' . HTMLHelper::_('date', $endDateEvent, Text::_('DATE_FORMAT_LC2'));
                            echo $this->loadTemplate('attendees');
                        }else{
                            if(!empty($this->item->dates)) {
                                $endDateEvent = strtotime($this->item->dates . ' ' . ($this->item->times ? $this->item->times : '23:59:59'));
                                if($timeNow <= $endDateEvent){
                                    echo Text::_('COM_JEM_EVENT_REGISTRATION_IS_UNTIL');
                                } else {
                                    echo Text::_('COM_JEM_EVENT_REGISTRATION_WAS_UNTIL');
                                }
                                echo ' ' . HTMLHelper::_('date', $endDateEvent, Text::_('DATE_FORMAT_LC2'));
                                echo $this->loadTemplate('attendees');
                            }
                        }
                    }
                    break;
            }?>
        <?php endif; ?>

        <?php if (!empty($this->item->pluginevent->onEventEnd)) : ?>
            <hr class="jem-hr">
            <?php echo $this->item->pluginevent->onEventEnd; ?>
        <?php endif; ?>

        <div class="copyright">
            <?php echo JemOutput::footer(); ?>
        </div>
    </div>

<?php }

echo JemOutput::lightbox();
?>
