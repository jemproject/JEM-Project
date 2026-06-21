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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$options = array(
    'onActive' => 'function(title, description){
        description.setStyle("display", "block");
        title.addClass("open").removeClass("closed");
    }',
    'onBackground' => 'function(title, description){
        description.setStyle("display", "none");
        title.addClass("closed").removeClass("open");
    }',
    'startOffset' => 0,  // 0 starts on the first tab, 1 starts the second, etc...
    'useCookie' => true, // this must not be a string. Don't use quotes.
);

$stateLinks = array(
    'events' => array(
        'published' => Route::_('index.php?option=com_jem&view=events&filter_state=1'),
        'unpublished' => Route::_('index.php?option=com_jem&view=events&filter_state=0'),
        'archived' => Route::_('index.php?option=com_jem&view=events&filter_state=2'),
        'trashed' => Route::_('index.php?option=com_jem&view=events&filter_state=-2'),
        'total' => Route::_('index.php?option=com_jem&view=events'),
    ),
    'venues' => array(
        'published' => Route::_('index.php?option=com_jem&view=venues&filter_state=1'),
        'unpublished' => Route::_('index.php?option=com_jem&view=venues&filter_state=0'),
        'archived' => Route::_('index.php?option=com_jem&view=venues&filter_state=2'),
        'trashed' => Route::_('index.php?option=com_jem&view=venues&filter_state=-2'),
        'total' => Route::_('index.php?option=com_jem&view=venues'),
    ),
    'categories' => array(
        'published' => Route::_('index.php?option=com_jem&view=categories&filter_state=1'),
        'unpublished' => Route::_('index.php?option=com_jem&view=categories&filter_state=0'),
        'archived' => Route::_('index.php?option=com_jem&view=categories&filter_state=2'),
        'trashed' => Route::_('index.php?option=com_jem&view=categories&filter_state=-2'),
        'total' => Route::_('index.php?option=com_jem&view=categories'),
    ),
    'types' => array(
        'published' => Route::_('index.php?option=com_jem&view=types&filter_state=1'),
        'unpublished' => Route::_('index.php?option=com_jem&view=types&filter_state=0'),
        'archived' => Route::_('index.php?option=com_jem&view=types&filter_state=2'),
        'trashed' => Route::_('index.php?option=com_jem&view=types&filter_state=-2'),
        'total' => Route::_('index.php?option=com_jem&view=types'),
    ),
);

$typeEntityLinks = array(
    'event' => Route::_('index.php?option=com_jem&view=types&filter_entity=1'),
    'category' => Route::_('index.php?option=com_jem&view=types&filter_entity=2'),
    'venue' => Route::_('index.php?option=com_jem&view=types&filter_entity=3'),
);

$imageLinks = array(
    'events' => Route::_('index.php?option=com_jem&view=events'),
    'venues' => Route::_('index.php?option=com_jem&view=venues'),
    'categories' => Route::_('index.php?option=com_jem&view=categories'),
    'types' => Route::_('index.php?option=com_jem&view=types'),
);

$attachmentLinks = array(
    'events' => Route::_('index.php?option=com_jem&view=attachments&filter_type=event'),
    'venues' => Route::_('index.php?option=com_jem&view=attachments&filter_type=venue'),
    'categories' => Route::_('index.php?option=com_jem&view=attachments&filter_type=category'),
    'total' => Route::_('index.php?option=com_jem&view=attachments'),
);

$registrationLinks = array(
    'events' => Route::_('index.php?option=com_jem&view=events'),
);

$renderStatRow = static function ($label, $value, $link = null, $isTotal = false) {
    $value = (int) $value;
    $class = $isTotal ? 'fw-bold' : '';
    $valueHtml = $link
        ? '<a href="' . $link . '" class="badge bg-light text-dark border text-decoration-none">' . $value . '</a>'
        : '<span class="badge bg-light text-dark border">' . $value . '</span>';

    return '<tr class="' . $class . '"><td>' . $label . '</td><td class="text-end">' . $valueHtml . '</td></tr>';
};

?>
<style>
    .jem-wei-menus .card{
        min-height: 126px;
    }
    .jem-wei-menus .card-body div:first-child{
        float:none !important;
    }
    .jem-wei-menus .icon{
        text-align:center;
    }
    .jem-wei-menus .icon a {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .jem-wei-menus .icon a img {
        width: 65px;
    }
    .jem-main-stats {
        width: 100%;
    }
    .jem-main-stats td {
        padding: 0.15rem 0;
    }
    .jem-main-stats td:first-child {
        padding-right: 0.75rem;
    }
</style>
<form action="<?php echo Route::_('index.php?option=com_jem');?>" id="application-form" method="post" name="adminForm" class="form-validate">
    <div id="j-main-container" class="j-main-container">
        <table style="width:100%">
            <tr>
                <td style="vertical-align: top">
                    <table>
                        <tr>
                            <td>
                                <div class="cpanel jem-wei-menus">
                                    <?php
                                        $link = 'index.php?option=com_jem&amp;view=events';
                                        $this->quickiconButton($link, 'icon-48-events.svg', Text::_('COM_JEM_EVENTS'));

                                        $link = 'index.php?option=com_jem&amp;task=event.add';
                                        $this->quickiconButton($link, 'icon-48-eventedit.svg', Text::_('COM_JEM_ADD_EVENT'));

                                        $link = 'index.php?option=com_jem&amp;view=venues';
                                        $this->quickiconButton($link, 'icon-48-venues.svg', Text::_('COM_JEM_VENUES'));

                                        $link = 'index.php?option=com_jem&task=venue.add';
                                        $this->quickiconButton($link, 'icon-48-venuesedit.svg', Text::_('COM_JEM_ADD_VENUE'));

                                        $link = 'index.php?option=com_jem&amp;view=categories';
                                        $this->quickiconButton($link, 'icon-48-categories.svg', Text::_('COM_JEM_CATEGORIES'));

                                        $link = 'index.php?option=com_jem&amp;task=category.add';
                                        $this->quickiconButton($link, 'icon-48-categoriesedit.svg', Text::_('COM_JEM_ADD_CATEGORY'));

                                        $link = 'index.php?option=com_jem&amp;view=groups';
                                        $this->quickiconButton($link, 'icon-48-groups.svg', Text::_('COM_JEM_GROUPS'));

                                        $link = 'index.php?option=com_jem&amp;task=group.add';
                                        $this->quickiconButton($link, 'icon-48-groupedit.svg', Text::_('COM_JEM_GROUP_ADD'));

                                        $link = 'index.php?option=com_jem&amp;view=attachments';
                                        $this->quickiconButton($link, 'icon-48-attachments.svg', Text::_('COM_JEM_ATTACHMENTS'));

                                        $link = 'index.php?option=com_jem&amp;view=types';
                                        $this->quickiconButton($link, 'icon-48-types.svg', Text::_('COM_JEM_TYPES'));

                                        $link = 'index.php?option=com_jem&amp;task=type.add';
                                        $this->quickiconButton($link, 'icon-48-typesedit.svg', Text::_('COM_JEM_ADD_TYPE'));

                                        $link = 'index.php?option=com_jem&amp;view=specialdays';
                                        $this->quickiconButton($link, 'icon-48-events.svg', Text::_('COM_JEM_SPECIAL_DAYS'));

                                        $link = 'index.php?option=com_jem&amp;task=plugins.plugins';
                                        $this->quickiconButton($link, 'icon-48-plugins.svg', Text::_('COM_JEM_MANAGE_PLUGINS'));

                                        //only admins should be able to see these items
                                        if (JemFactory::getUser()->authorise('core.manage', 'com_jem')) {
                                            $link = 'index.php?option=com_jem&amp;view=settings';
                                            $this->quickiconButton($link, 'icon-48-settings.svg', Text::_('COM_JEM_SETTINGS_TITLE'));

                                            $link = 'index.php?option=com_jem&amp;view=housekeeping';
                                            $this->quickiconButton($link, 'icon-48-housekeeping.svg', Text::_('COM_JEM_HOUSEKEEPING'));

                                            $link = 'index.php?option=com_jem&amp;task=sampledata.load&amp;' . Session::getFormToken() . '=1';
                                            $this->quickiconButton($link, 'icon-48-sampledata.svg', Text::_('COM_JEM_MAIN_LOAD_SAMPLE_DATA'));

                                            $link = 'index.php?option=com_jem&amp;task=frontendmenu.create&amp;' . Session::getFormToken() . '=1';
                                            $this->quickiconButton($link, 'icon-48-frontendmenu.svg', Text::_('COM_JEM_MAIN_CREATE_FRONTEND_MENU'));

                                            $link = 'index.php?option=com_jem&amp;view=updatecheck';
                                            $icon = 'icon-48-update.svg';

                                            // If an update is available, use a different icon
                                            if (
                                                !empty($this->updatedata)
                                                && isset($this->updatedata->current)
                                                && (int) $this->updatedata->current === -1
                                            ) {
                                                $icon = 'icon-48-update-y.svg';
                                            }
                                            $this->quickiconButton($link, $icon, Text::_('COM_JEM_UPDATECHECK_TITLE'));

                                            $link = 'index.php?option=com_jem&amp;view=import';
                                            $this->quickiconButton($link, 'icon-48-tableimport.svg', Text::_('COM_JEM_IMPORT_DATA'));

                                            $link = 'index.php?option=com_jem&amp;view=export';
                                            $this->quickiconButton($link, 'icon-48-tableexport.svg', Text::_('COM_JEM_EXPORT_DATA'));

                                            $link = 'index.php?option=com_jem&amp;view=cssmanager';
                                            $this->quickiconButton( $link, 'icon-48-cssmanager.svg', Text::_( 'COM_JEM_CSSMANAGER_TITLE' ) );
                                        }

                                        $link = 'index.php?option=com_jem&amp;view=help';
                                        $this->quickiconButton($link, 'icon-48-help.svg', Text::_('COM_JEM_HELP'));
                                    ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
                <td style="vertical-align: top; width: 320px; padding: 7px 0 0 18px">

                    <div class="accordion" id="accordion_jem">
                        <?php //echo HTMLHelper::_('sliders.start','stat-pane',$options); ?>
                        <?php //echo HTMLHelper::_('sliders.panel', Text::_('COM_JEM_MAIN_EVENT_STATS'),'events'); ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="clsp_events_header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#clsp_events" aria-expanded="true" aria-controls="clsp_events">
                                    <?php  echo Text::_('COM_JEM_MAIN_EVENT_STATS'); ?>
                                </button>
                            </h2>
                            <div id="clsp_events" class="accordion-collapse collapse show" aria-labelledby="clsp_events_header" data-bs-parent="#accordion_jem">
                                <div class="accordion-body">
                                    <table class="jem-main-stats">
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_EVENTS_PUBLISHED'), $this->events->published ?? 0, $stateLinks['events']['published']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_EVENTS_UNPUBLISHED'), $this->events->unpublished ?? 0, $stateLinks['events']['unpublished']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_EVENTS_ARCHIVED'), $this->events->archived ?? 0, $stateLinks['events']['archived']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_EVENTS_TRASHED'), $this->events->trashed ?? 0, $stateLinks['events']['trashed']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_EVENTS_TOTAL'), $this->events->total ?? 0, $stateLinks['events']['total'], true); ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="clsp_venues_header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#clsp_venues" aria-expanded="true" aria-controls="clsp_venues">
                                    <?php  echo Text::_('COM_JEM_MAIN_VENUE_STATS'); ?>
                                </button>
                            </h2>
                            <div id="clsp_venues" class="accordion-collapse collapse" aria-labelledby="clsp_venues_header" data-bs-parent="#accordion_jem">
                                <div class="accordion-body">
                                    <table class="jem-main-stats">
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_VENUES_PUBLISHED'), $this->venue->published ?? 0, $stateLinks['venues']['published']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_VENUES_UNPUBLISHED'), $this->venue->unpublished ?? 0, $stateLinks['venues']['unpublished']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_VENUES_ARCHIVED'), $this->venue->archived ?? 0, $stateLinks['venues']['archived']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_VENUES_TRASHED'), $this->venue->trashed ?? 0, $stateLinks['venues']['trashed']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_VENUES_TOTAL'), $this->venue->total ?? 0, $stateLinks['venues']['total'], true); ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="clsp_categories_header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#clsp_categories" aria-expanded="true" aria-controls="clsp_categories">
                                    <?php  echo Text::_('COM_JEM_MAIN_CATEGORY_STATS'); ?>
                                </button>
                            </h2>
                            <div id="clsp_categories" class="accordion-collapse collapse" aria-labelledby="clsp_categories_header" data-bs-parent="#accordion_jem">
                                <div class="accordion-body">
                                    <table class="jem-main-stats">
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_CATEGORIES_PUBLISHED'), $this->category->published ?? 0, $stateLinks['categories']['published']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_CATEGORIES_UNPUBLISHED'), $this->category->unpublished ?? 0, $stateLinks['categories']['unpublished']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_CATEGORIES_ARCHIVED'), $this->category->archived ?? 0, $stateLinks['categories']['archived']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_CATEGORIES_TRASHED'), $this->category->trashed ?? 0, $stateLinks['categories']['trashed']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_CATEGORIES_TOTAL'), $this->category->total ?? 0, $stateLinks['categories']['total'], true); ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="clsp_types_header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#clsp_types" aria-expanded="true" aria-controls="clsp_types">
                                    <?php  echo Text::_('COM_JEM_MAIN_TYPE_STATS'); ?>
                                </button>
                            </h2>
                            <div id="clsp_types" class="accordion-collapse collapse" aria-labelledby="clsp_types_header" data-bs-parent="#accordion_jem">
                                <div class="accordion-body">
                                    <table class="jem-main-stats">
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_TYPES_PUBLISHED'), $this->types->published ?? 0, $stateLinks['types']['published']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_TYPES_UNPUBLISHED'), $this->types->unpublished ?? 0, $stateLinks['types']['unpublished']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_TYPES_ARCHIVED'), $this->types->archived ?? 0, $stateLinks['types']['archived']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_TYPES_TRASHED'), $this->types->trashed ?? 0, $stateLinks['types']['trashed']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_TYPES_EVENT'), $this->typeEntities->event ?? 0, $typeEntityLinks['event']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_TYPES_CATEGORY'), $this->typeEntities->category ?? 0, $typeEntityLinks['category']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_TYPES_VENUE'), $this->typeEntities->venue ?? 0, $typeEntityLinks['venue']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_TYPES_TOTAL'), $this->types->total ?? 0, $stateLinks['types']['total'], true); ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="clsp_images_header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#clsp_images" aria-expanded="true" aria-controls="clsp_images">
                                    <?php  echo Text::_('COM_JEM_MAIN_IMAGE_STATS'); ?>
                                </button>
                            </h2>
                            <div id="clsp_images" class="accordion-collapse collapse" aria-labelledby="clsp_images_header" data-bs-parent="#accordion_jem">
                                <div class="accordion-body">
                                    <table class="jem-main-stats">
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_IMAGES_EVENTS'), $this->images->events ?? 0, $imageLinks['events']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_IMAGES_VENUES'), $this->images->venues ?? 0, $imageLinks['venues']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_IMAGES_CATEGORIES'), $this->images->categories ?? 0, $imageLinks['categories']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_IMAGES_TYPES'), $this->images->types ?? 0, $imageLinks['types']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_IMAGES_TOTAL'), $this->images->total ?? 0, null, true); ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="clsp_attachments_header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#clsp_attachments" aria-expanded="true" aria-controls="clsp_attachments">
                                    <?php  echo Text::_('COM_JEM_MAIN_ATTACHMENT_STATS'); ?>
                                </button>
                            </h2>
                            <div id="clsp_attachments" class="accordion-collapse collapse" aria-labelledby="clsp_attachments_header" data-bs-parent="#accordion_jem">
                                <div class="accordion-body">
                                    <table class="jem-main-stats">
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_ATTACHMENTS_EVENTS'), $this->attachments->events ?? 0, $attachmentLinks['events']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_ATTACHMENTS_VENUES'), $this->attachments->venues ?? 0, $attachmentLinks['venues']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_ATTACHMENTS_CATEGORIES'), $this->attachments->categories ?? 0, $attachmentLinks['categories']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_ATTACHMENTS_OTHER'), $this->attachments->other ?? 0, null); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_ATTACHMENTS_TOTAL'), $this->attachments->total ?? 0, $attachmentLinks['total'], true); ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="clsp_registration_header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#clsp_registration" aria-expanded="true" aria-controls="clsp_registration">
                                    <?php  echo Text::_('COM_JEM_MAIN_REGISTRATION_STATS'); ?>
                                </button>
                            </h2>
                            <div id="clsp_registration" class="accordion-collapse collapse" aria-labelledby="clsp_registration_header" data-bs-parent="#accordion_jem">
                                <div class="accordion-body">
                                    <table class="jem-main-stats">
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_REGISTRATION_ATTENDING_USERS'), $this->registration->attending_users ?? 0, $registrationLinks['events']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_REGISTRATION_BOOKED_PLACES'), $this->registration->booked_places ?? 0, $registrationLinks['events']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_REGISTRATION_WAITING_USERS'), $this->registration->waiting_users ?? 0, $registrationLinks['events']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_REGISTRATION_WAITING_PLACES'), $this->registration->waiting_places ?? 0, $registrationLinks['events']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_REGISTRATION_INVITED_USERS'), $this->registration->invited_users ?? 0, $registrationLinks['events']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_REGISTRATION_NOT_ATTENDING_USERS'), $this->registration->not_attending_users ?? 0, $registrationLinks['events']); ?>
                                        <?php echo $renderStatRow(Text::_('COM_JEM_MAIN_REGISTRATION_TOTAL'), $this->registration->total ?? 0, $registrationLinks['events'], true); ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php //echo HTMLHelper::_('sliders.end'); ?>
                    <h3 class="title mt-4"><?php echo Text::_('COM_JEM_MAIN_DONATE'); ?></h3>
                    <div class="content">
                        <?php echo Text::_('COM_JEM_MAIN_DONATE_TEXT'); ?> <br><br>
                        <div class="center">
                            <a href="https://www.joomlaeventmanager.net/project/donate" target="_blank">
                                <?php echo HTMLHelper::_('image', 'com_jem/PayPal_DonateButton.webp', Text::_('COM_JEM_MAIN_DONATE'), NULL, true); ?>
                            </a>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</form>
