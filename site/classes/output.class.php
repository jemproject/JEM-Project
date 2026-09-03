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
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Filesystem\File;
use Joomla\CMS\Date\Date;
use Joomla\String\StringHelper;
use Joomla\Component\Jem\Site\Helper\JemMapHelper;

// ensure JemFactory is loaded (because this class is used by modules or plugins too)
require_once(JPATH_SITE.'/components/com_jem/factory.php');
require_once(JPATH_SITE.'/administrator/components/com_jem/helpers/html/jemhtml.php');
require_once(JPATH_SITE.'/components/com_jem/helpers/map.php');

// HTMLHelper::addIncludePath(JPATH_SITE . '/administrator/components/com_jem/helpers/html');

/**
 * Holds the logic for all output related things
 */
class JemOutput
{
    /**
     * Writes footer.
     */
    static public function footer()
    {
        $app = Factory::getApplication();

        if ($app->input->get('print','','int')) {
            return;
        } else {
            echo 'Powered by <a href="https://www.joomlaeventmanager.net" target="_blank" title="Joomla Event Manager">JEM</a>';
        }
    }

/**
 * Load stylesheet and JS for lightbox.
 */
static public function lightbox() {
    $settings = JemHelper::config();
    $app = Factory::getApplication();
    if ($settings->lightbox == 1) {
        $document = Factory::getApplication()->getDocument();
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager()->useScript('jquery');
        JemHelper::loadCss('lightbox.min');
        $document->addScript(Uri::base() . 'media/com_jem/js/lightbox.min.js');
        echo '<script>lightbox.option({
                      \'showImageNumberLabel\': false,
                })
        </script>';
        }
    else {
          return;
    }
}

    /**
     * Creates the button bar shown on frontend view's top right corner.
     *
     * @param  string $view        Name of the view
     *                             ('attendees', 'calendar', 'categories', 'category', 'category-cal', 'day',
     *                              'editevent', 'editvenue', 'event', 'eventslist', 'myattendances', 'myevents', 'myvenues',
     *                              'search', 'venue', 'venue-cal', 'venues', 'weekcal')
     * @param  object $permissions Object holding relevant permissions
     *                             (canAddEvent, canAddVenue, canPublishEvent, canPublishVenue)
     * @param  object $params      Object containing other relevant parameters
     *                             (id: for '&id=', for Archive and Export button,
     *                              slug: for '&id=', for Mail and iCal button,
     *                              task: e.g. 'archive', for Archive button,
     *                              print_link: for Print button
     *                              today_link: for Today button
     *                              show, hide: to override button visibility; array of one or more of
     *                              'addEvent', 'addVenue', 'addUsers'
     *                              'archive' 'mail', 'print', 'ical', ('export', 'back',)
     *                              'publish', 'unpublish', 'trash' - note: some buttons may not work or need additional changes)
     *
     * @return string              Resulting HTML code.
     */
    static public function createButtonBar($view, $permissions, $params)
    {
        foreach (array('canAddEvent', 'canAddVenue', 'canAddUsers', 'canPublishEvent', 'canPublishVenue') as $key) {
            ${$key} = isset($permissions->$key) ? $permissions->$key: null;
        }
        if (is_object($params)) {
            foreach (array('id', 'slug', 'task', 'archive_link', 'print_link', 'pdf_link', 'today_link', 'show', 'hide', 'ical_link', 'archive_link') as $key) {
                ${$key} = isset($params->$key) ? $params->$key : null;
            }
        } elseif (is_array($params)) {
            foreach (array('id', 'slug', 'task', 'archive_link','print_link', 'pdf_link', 'today_link', 'show', 'hide', 'ical_link', 'archive_link') as $key) {
                ${$key} = key_exists($key, $params) ? $params[$key] : null;
            }
        } else {
            foreach (array('id', 'slug', 'task', 'archive_link', 'print_link', 'pdf_link', 'today_link') as $key) {
                ${$key} = null;
            }
        }

        $btns_show = isset($show) ? (array)$show : array();
        $btns_hide = isset($hide) ? (array)$hide : array();
        $archive = !empty($task) && ($task == 'archive');
        $buttons = array();
        $idx = 0;

        # Left block ------------------

        if (!$archive) {
            if (in_array('addEvent', $btns_show) || (!in_array('addEvent', $btns_hide) && in_array($view, array('calendar', 'categories', 'category', 'day', 'event', 'eventslist', 'myevents', 'myvenues', 'venue', 'venues')))) {
                $buttons[$idx][] = JemOutput::submitbutton(!empty($canAddEvent), null);
            }
            if (in_array('addVenue', $btns_show) || (!in_array('addVenue', $btns_hide) && in_array($view, array('calendar', 'categories', 'category', 'day', 'event', 'eventslist', 'myevents', 'myvenues', 'venue', 'venues', 'venueslist')))) {
                $buttons[$idx][] = JemOutput::addvenuebutton(!empty($canAddVenue), null, null);
            }
            if (in_array('addUsers', $btns_show) || (!in_array('addUsers', $btns_hide) && in_array($view, array('attendees')))) {
                $buttons[$idx][] = JemOutput::addusersbutton(!empty($canAddUsers), $id);
            }
        }

        ++$idx;

        # Middle block ----------------

        if (in_array('archive', $btns_show) || (!in_array('archive', $btns_hide) && in_array($view, array('annualcalendar', 'categories', 'category', 'eventslist', 'myattendances', 'myevents', 'venue')))) {
            $buttons[$idx][] = JemOutput::archivebutton($archive_link, $task , $id); // task: archive, id: for '&id='
        }
        if (in_array('mail', $btns_show) || (!in_array('mail', $btns_hide) && in_array($view, array('category', 'event', 'venue', 'venueslist')))) {
            $buttons[$idx][] = JemOutput::mailbutton($slug, $view, null); // slug: for '&id='
        }
        if (!empty($today_link) && !in_array('today', $btns_hide)) {
            $buttons[$idx][] = JemOutput::todaybutton($today_link);
        }
        if (in_array('print', $btns_show) || (!in_array('print', $btns_hide) && in_array($view, array('annualcalendar', 'attendees', 'calendar', 'categories', 'category', 'category-cal', 'day', 'event', 'eventslist', 'myattendances', 'myevents', 'myvenues', 'venue', 'venue-cal', 'venues', 'venueslist', 'weekcal')))) {
            $buttons[$idx][] = JemOutput::printbutton($print_link, null);
        }
        if (empty($pdf_link) && JemOutput::isPdfViewEnabled($view)) {
            $pdf_link = JemOutput::buildCurrentPdfLink();
        }

        if (!empty($pdf_link) && JemOutput::isPdfViewEnabled($view) && (in_array('pdf', $btns_show) || !in_array('pdf', $btns_hide))) {
            $buttons[$idx][] = JemOutput::pdfbutton($pdf_link);
        }
        if (in_array('ical', $btns_show) || (!in_array('ical', $btns_hide) && in_array($view, array('event', 'eventslist', 'calendar', 'annualcalendar', 'venue', 'venue-cal', 'weekcal', 'category', 'category-cal')))) {
            $buttons[$idx][] = JemOutput::icalbutton(($ical_link? $ical_link: $slug), $view, $task); // slug: for '&id='
        }
        if (in_array('export', $btns_show) || (!in_array('export', $btns_hide) && in_array($view, array('attendees')))) {
            $buttons[$idx][] = JemOutput::exportbutton($id); // id: for '&id='
        }
        if (in_array('back', $btns_show) || (!in_array('back', $btns_hide) && in_array($view, array('attendees')))) {
            $buttons[$idx][] = JemOutput::backbutton(null, $view);
        }

        ++$idx;

        # Right block -----------------

        if (!empty($canPublishEvent) || !empty($canPublishVenue)) {
            if (in_array('publish', $btns_show) || (!in_array('publish', $btns_hide) && in_array($view, array('myevents', 'myvenues')))) {
                $buttons[$idx][] = JemOutput::publishbutton($view);
            }
            if (in_array('unpublish', $btns_show) || (!in_array('unpublish', $btns_hide) && in_array($view, array('myevents', 'myvenues')))) {
                $buttons[$idx][] = JemOutput::unpublishbutton($view);
            }
            if (in_array('trash', $btns_show) || (!in_array('trash', $btns_hide) && in_array($view, array('myevents')))) {
                $buttons[$idx][] = JemOutput::trashbutton($view);
            }
        }

        # -----------------------------

        foreach ($buttons as $i => $btns) {
            $buttons[$i] = implode('', array_filter($btns));
        }
        $result = implode('<span class="gap">&nbsp;</span>', array_filter($buttons));
        return $result;
    }

    /**
     * Writes Event submission button
     *
     * @param int $dellink Access of user
     * @param array $params needed params
     **/
    static public function submitbutton($dellink, $params)
    {
        if ($dellink)
        {
            $settings  = JemHelper::globalattribs();
            $settings2 = JemHelper::config();
            $uri       = Uri::getInstance();
            $app = Factory::getApplication();

            if ($app->input->get('print','','int')) {
                return;
            }

            if ($settings->get('global_show_icons',1)) {
                $image = jemhtml::icon( 'com_jem/submitevent.webp', 'fa fa-fw fa-lg fa-calendar-plus jem-submitbutton', Text::_('COM_JEM_DELIVER_NEW_EVENT'), NULL, !$app->isClient('site'));
            } else {
                $image = Text::_('COM_JEM_DELIVER_NEW_EVENT');
            }

            $url = 'index.php?option=com_jem&task=event.add&return='.base64_encode($uri).'&a_id=0';
            $overlib = Text::_('COM_JEM_SUBMIT_EVENT_DESC');
            $output = HTMLHelper::_('link', self::escapeLinkAttribute($url), $image, self::tooltip(Text::_('COM_JEM_DELIVER_NEW_EVENT'), $overlib, '', 'bottom'));

            return $output;
        }
    }

    /**
     * Writes addvenuebutton
     *
     * @param int $addvenuelink Access of user
     * @param array $params needed params
     * @param $settings, retrieved from settings-table
     *
     * Active in views:
     * venue, venues
     **/
    static public function addvenuebutton($addvenuelink, $params, $settings2)
    {
        if ($addvenuelink) {
            $app      = Factory::getApplication();
            $settings = JemHelper::globalattribs();
            $uri      = Uri::getInstance();

            if ($app->input->get('print','','int')) {
                return;
            }

            if ($settings->get('global_show_icons',1)) {
                $image = jemhtml::icon( 'com_jem/addvenue.webp', 'fa fa-fw fa-lg fa-plus-square jem-addvenuebutton', Text::_('COM_JEM_DELIVER_NEW_VENUE'), NULL, !$app->isClient('site'));
            } else {
                $image = Text::_('COM_JEM_DELIVER_NEW_VENUE');
            }

            $url = 'index.php?option=com_jem&task=venue.add&return='.base64_encode($uri).'&a_id=0';
            $overlib = Text::_('COM_JEM_DELIVER_NEW_VENUE_DESC');
            $output = HTMLHelper::_('link', self::escapeLinkAttribute($url), $image, self::tooltip(Text::_('COM_JEM_DELIVER_NEW_VENUE'), $overlib, '', 'bottom'));

            return $output;
        }
    }

    /**
     * Writes addusersbutton
     *
     * @param int $addvenuelink Access of user
     * @param int $eventid id of corresponding event
     * @param array $params needed params
     * @param $settings, retrieved from settings-table
     *
     * Active in views:
     * venue, venues
     **/
    static public function addusersbutton($adduserslink, $eventid)
    {
        if ($adduserslink) {
            $app      = Factory::getApplication();
            $settings = JemHelper::globalattribs();
            $uri      = Uri::getInstance();

            if ($app->input->get('print','','int')) {
                return;
            }

            if ($settings->get('global_show_icons',1)) {
                $image = jemhtml::icon( 'com_jem/icon-16-new.webp', 'fa fa-fw fa-lg fa-user-plus jem-addusersbutton', Text::_('COM_JEM_ADD_USER_REGISTRATIONS'), NULL, !$app->isClient('site'));
            } else {
                $image = Text::_('COM_JEM_ADD_USER_REGISTRATIONS');
            }

            $url = 'index.php?option=com_jem&view=attendees&layout=addusers&tmpl=component&return='.base64_encode($uri).'&id='.$eventid;
            $overlib = Text::_('COM_JEM_ADD_USER_REGISTRATIONS_DESC');
            // $output = HTMLHelper::_('link', self::escapeLinkAttribute($url), $image, self::tooltip(Text::_('COM_JEM_ADD_USER_REGISTRATIONS'), $overlib, 'flyermodal', 'bottom').' rel="{handler: \'iframe\', size: {x:800, y:450}}"');


            $output= HTMLHelper::_(
                'bootstrap.renderModal',
                'adduser-modal',
                array(
                    'url'    => $url,
                    'title'  => Text::_('COM_JEM_SELECT'),
                    'width'  => '800px',
                    'height' => '450px',
                    'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
                )
            );
            $output.='<a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#adduser-modal" ' .  self::tooltip(Text::_('COM_JEM_ADD_USER_REGISTRATIONS'), $overlib, 'flyermodal', 'bottom'). '>' . $image . '</a>';


            return $output;
        }
    }

    /**
     * Creates a remove button
     *
     * @param int   $text       alternative text
     * @param array $attributes additional attributes
     *
     * Active in views:
     *
     **/
    static public function removebutton($text, $attributes = array())
    {
        $app      = Factory::getApplication();

        $output = jemhtml::icon( 'com_jem/publish_r.webp', 'fa fa-lg fa-times-circle jem-removebutton', $text, $attributes, !$app->isClient('site'));

        return $output;
    }

    /**
     * Prepares addeventbutton for calendar days.
     *
     * @param string $urlparams additional url oarams, e.g. 'locid=123'
     *
     * Active in views:
     * all calendar views
     **/
    static public function prepareAddEventButton($urlparams = '')
    {
        $uri   = Uri::getInstance();
        $app   = Factory::getApplication();
        $image = jemhtml::icon( 'com_jem/submitevent.webp', 'fa fa-fw fa-lg fa-calendar-plus jem-submitbutton', Text::_('COM_JEM_DELIVER_NEW_EVENT'), NULL, !$app->isClient('site'));
        $url   = 'index.php?option=com_jem&task=event.add&a_id=0&date={date}&return='.base64_encode($uri);
        if (!empty($urlparams) && preg_match('/^[a-z]+=\w+$/i', $urlparams)) {
            $url .= '&'.$urlparams;
        }
        $html  = '<div class="inline-button-right">';
        $html .= HTMLHelper::_('link', self::escapeLinkAttribute($url), $image, self::tooltip(Text::_('COM_JEM_DELIVER_NEW_EVENT'), Text::_('COM_JEM_SUBMIT_EVENT_DESC'), '', 'bottom'));
        $html .= '</div>';

        return $html;
    }

    /**
     * Returns true when the current view is enabled for PDF output.
     *
     * @param   string  $view  View name.
     *
     * @return  boolean
     */
    static protected function isPdfViewEnabled($view)
    {
        $settings = JemHelper::config();
        $enabled = isset($settings->pdf_enabled_views) ? (string) $settings->pdf_enabled_views : self::getDefaultPdfViews();
        $views = array_filter(array_map('trim', explode(',', $enabled)));
        $view = (string) $view;

        return in_array($view, $views, true) && is_file(JPATH_COMPONENT_SITE . '/views/' . $view . '/view.raw.php');
    }

    /**
     * Returns the frontend views with implemented PDF output.
     */
    static protected function getDefaultPdfViews()
    {
        return 'annualcalendar,attendeeregistrations,calendar,categories,category,day,event,eventslist,eventsmap,myattendances,myevents,mytimeline,myvenues,specialdays,typeevents,typevenues,venue,venues,venueslist,venuesmap,weekcal';
    }

    /**
     * Escapes a dynamic value for use in an HTML attribute.
     */
    static public function escapeHtmlAttribute($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

    /**
     * Routes a link and escapes it for use in an HTML attribute.
     */
    static protected function escapeLinkAttribute($link)
    {
        return self::escapeHtmlAttribute(Route::_((string) $link));
    }

    /**
     * Builds a PDF link from the current request.
     */
    static protected function buildCurrentPdfLink()
    {
        // Uri::getInstance() is shared. Mutating it here would contaminate
        // edit/add return URLs rendered after the PDF button.
        $uri = clone Uri::getInstance();
        $query = $uri->getQuery(true);
        $query['format'] = 'raw';
        $query['layout'] = 'pdf';
        unset($query['print'], $query['tmpl']);
        $uri->setQuery($query);

        return $uri->toString(array('path', 'query'));
    }

    /**
     * Writes Archivebutton
     *
     * @param string $archive_link The link archive button
     * @param string $task The current task (optional)
     * @param int $id id of category/event/venue if useful (optional)
     *
     * Views:
     * Categories, Categoriesdetailed, Category, Eventslist, Search, Venue, Venues
     */
    static public function archivebutton($archive_link, $task = NULL, $id = NULL)
    {
        $settings  = JemHelper::globalattribs();
        $settings2 = JemHelper::config();
        $app       = Factory::getApplication();
        $uri       = Uri::getInstance();
        $jemPath   = $uri->getPath();

        if ($settings->get('global_show_archive_icon',1)) {
            if ($app->input->get('print','','int')) {
                return;
            }

            $view = $app->input->getWord('view');

            if (empty($view)) {
                return; // there must be a view - just to be sure...
            }

            if ($task == 'archive') {
                if ($settings->get('global_show_icons',1)) {
                    $image = jemhtml::icon( 'com_jem/el.webp', 'fa fa-fw fa-lg fa-calendar jem-archivebutton-return', Text::_('COM_JEM_SHOW_EVENTS'), NULL, !$app->isClient('site'));
                } else {
                    $image = Text::_('COM_JEM_SHOW_EVENTS');
                }

                // TODO: Title and overlib just fit to events view
                $overlib = Text::_('COM_JEM_SHOW_EVENTS_DESC');
                $title = Text::_('COM_JEM_SHOW_EVENTS');

                if ($id) {
                    $url = $archive_link . (str_contains($archive_link ?? '','?')?'&':'?') . 'id=' . $id . '&filter_reset=1';
                } else {
                    $url = $archive_link . (str_contains($archive_link ?? '','?')?'&':'?') . 'filter_reset=1';
                }
            } else {
                if ($settings->get('global_show_icons',1)) {
                       $image = jemhtml::icon( 'com_jem/archive_front.webp', 'fa fa-fw fa-lg fa-archive jem-archivebutton', Text::_('COM_JEM_SHOW_ARCHIVE'), NULL, !$app->isClient('site'));
                } else {
                    $image = Text::_('COM_JEM_SHOW_ARCHIVE');
                }

                $overlib = Text::_('COM_JEM_SHOW_ARCHIVE_DESC');
                $title = Text::_('COM_JEM_SHOW_ARCHIVE');

                if ($id) {
                    $url = $archive_link . (str_contains($archive_link ?? '','?')?'&':'?') . 'id=' . $id . '&task=archive&filter_reset=1';
                } else {
                    $url = $archive_link . (str_contains($archive_link ?? '','?')?'&':'?') . 'task=archive&filter_reset=1';
                }
            }

            $output = HTMLHelper::_('link', self::escapeLinkAttribute($url), $image, self::tooltip($title, $overlib, '', 'bottom'));

            return $output;
        }
    }

    /**
     * Creates the edit button
     *
     * @param int $Itemid
     * @param int $id
     * @param array $params
     * @param int $allowedtoedit
     * @param string $view
     *
     * Views:
     * Event, Venue, Category
     */
    static public function editbutton($item, $params, $attribs, $allowedtoedit, $view)
    {
        if ($allowedtoedit) {
            $app = Factory::getApplication();

            if ($app->input->get('print','','int')) {
                return;
            }

            // Ignore if the state is negative (trashed).
            if ($item->published < 0) {
                return;
            }

            // Initialise variables.
            $user     = JemFactory::getUser();
            $userId   = $user->get('id');
            $uri      = Uri::getInstance();
            $settings = JemHelper::globalattribs();

            // On Joomla Edit icon is always used regardless if "Show icons" is set to Yes or No.
            $showIcon = $settings->get('global_show_icons', 1);

            $iconEditEventRoot='fa-sharp fa-solid fa-pen-to-square jem-editbutton';

            switch ($view)
            {
                case 'editevent':
                    if (property_exists($item, 'checked_out') && property_exists($item, 'checked_out_time') && $item->checked_out > 0 && $item->checked_out != $userId) {
                        $checkoutUser = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($item->checked_out);
                        $button = HTMLHelper::_('image', 'system/checked_out.webp', NULL, NULL, true);
                        $date = HTMLHelper::_('date', $item->checked_out_time);
                        return '<span ' . self::tooltip(Text::_('JLIB_HTML_CHECKED_OUT'), htmlspecialchars(Text::sprintf('COM_JEM_GLOBAL_CHECKED_OUT_BY', $checkoutUser->name) . ' <br> ' . $date, ENT_COMPAT, 'UTF-8')) . '>' . $button . '</span>';
                    }

                    if ($showIcon) {
                        if($item->recurrence_type && !$item->recurrence_first_id){
                            $image = jemhtml::icon('com_jem/calendar_edit_root.webp', $iconEditEventRoot, Text::_('COM_JEM_EDIT_EVENT_ROOT'), NULL, !$app->isClient('site'));
                            $overlib = Text::_('COM_JEM_EDIT_EVENT_ROOT_DESC');
                            $text = Text::_('COM_JEM_EDIT_EVENT_ROOT');
                        }else {
                            $image = jemhtml::icon('com_jem/calendar_edit.webp', 'fa fa-fw fa-pen-square jem-editbutton', Text::_('COM_JEM_EDIT_EVENT'), NULL, !$app->isClient('site'));
                            $overlib = Text::_('COM_JEM_EDIT_EVENT_DESC');
                            $text = Text::_('COM_JEM_EDIT_EVENT');
                        }
                    } else {
                        $image = Text::_('COM_JEM_EDIT_EVENT');
                        $overlib = Text::_('COM_JEM_EDIT_EVENT_DESC');
                        $text = Text::_('COM_JEM_EDIT_EVENT');
                    }
                    $id = isset($item->did) ? $item->did : $item->id;
                    $url = 'index.php?option=com_jem&view=editevent&task=event.edit&a_id='.$id.'&return='.base64_encode($uri);
                    break;

                case 'editvenue':
                    if (property_exists($item, 'vChecked_out') && property_exists($item, 'vChecked_out_time') && $item->vChecked_out > 0 && $item->vChecked_out != $userId) {
                        $checkoutUser = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($item->vChecked_out);
                        $button = HTMLHelper::_('image', 'system/checked_out.webp', NULL, NULL, true);
                        $date = HTMLHelper::_('date', $item->vChecked_out_time);
                        return '<span ' . self::tooltip(Text::_('JLIB_HTML_CHECKED_OUT'), htmlspecialchars(Text::sprintf('COM_JEM_GLOBAL_CHECKED_OUT_BY', $checkoutUser->name) . ' <br> ' . $date, ENT_COMPAT, 'UTF-8')) . '>' . $button . '</span>';
                    }

                    if ($showIcon) {
                        $image = jemhtml::icon( 'com_jem/calendar_edit.webp', 'fa fa-fw fa-pen-square jem-editbutton', Text::_('COM_JEM_EDIT_VENUE'), NULL, !$app->isClient('site'));
                    } else {
                        $image = Text::_('COM_JEM_EDIT_VENUE');
                    }
                    $id = $item->locid;
                    $overlib = Text::_('COM_JEM_EDIT_VENUE_DESC');
                    $text = Text::_('COM_JEM_EDIT_VENUE');
                    $url = 'index.php?option=com_jem&view=editvenue&task=venue.edit&a_id='.$id.'&return='.base64_encode($uri);
                    break;

                case 'venue':
                    if (property_exists($item, 'vChecked_out') && property_exists($item, 'vChecked_out_time') && $item->vChecked_out > 0 && $item->vChecked_out != $userId) {
                        $checkoutUser = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($item->vChecked_out);
                        $button = HTMLHelper::_('image', 'system/checked_out.webp', NULL, NULL, true);
                        $date = HTMLHelper::_('date', $item->vChecked_out_time);
                        return '<span ' . self::tooltip(Text::_('JLIB_HTML_CHECKED_OUT'), htmlspecialchars(Text::sprintf('COM_JEM_GLOBAL_CHECKED_OUT_BY', $checkoutUser->name) . ' <br> ' . $date, ENT_COMPAT, 'UTF-8')) . '>' . $button . '</span>';
                    }

                    if ($showIcon) {
                        $image = jemhtml::icon( 'com_jem/calendar_edit.webp', 'fa fa-fw fa-pen-square jem-editbutton', Text::_('COM_JEM_EDIT_VENUE'), NULL, !$app->isClient('site'));
                    } else {
                        $image = Text::_('COM_JEM_EDIT_VENUE');
                    }
                    $id = $item->id;
                    $overlib = Text::_('COM_JEM_EDIT_VENUE_DESC');
                    $text = Text::_('COM_JEM_EDIT_VENUE');
                    $url = 'index.php?option=com_jem&view=editvenue&task=venue.edit&a_id='.$id.'&return='.base64_encode($uri);
                    break;

                case 'editcategory':
                    if (property_exists($item, 'checked_out') && $item->checked_out > 0 && $item->checked_out != $userId) {
                        $checkoutUser = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($item->checked_out);
                        $button = HTMLHelper::_('image', 'system/checked_out.webp', null, null, true);
                        $date = HTMLHelper::_('date', $item->checked_out_time);

                        return '<span ' . self::tooltip(
                            Text::_('JLIB_HTML_CHECKED_OUT'),
                            htmlspecialchars(
                                Text::sprintf('COM_JEM_GLOBAL_CHECKED_OUT_BY', $checkoutUser->name) . ' <br> ' . $date,
                                ENT_COMPAT,
                                'UTF-8'
                            )
                        ) . '>' . $button . '</span>';
                    }

                    if ($showIcon) {
                        $image = jemhtml::icon(
                            'com_jem/calendar_edit.webp',
                            'fa fa-fw fa-pen-square jem-editbutton',
                            Text::_('COM_JEM_EDIT_CATEGORY'),
                            null,
                            !$app->isClient('site')
                        );
                    } else {
                        $image = Text::_('COM_JEM_EDIT_CATEGORY');
                    }

                    $id = (int) $item->id;
                    $overlib = Text::_('COM_JEM_EDIT_CATEGORY_DESC');
                    $text = Text::_('COM_JEM_EDIT_CATEGORY');
                    $url = 'index.php?option=com_jem&view=editcategory&task=category.edit&a_id=' . $id
                        . '&return=' . base64_encode($uri);
                    break;
            }

            if (!$url) {
                return; // we need at least url to generate useful output
            }

            $output = HTMLHelper::_('link', self::escapeLinkAttribute($url), $image, self::tooltip($text, $overlib));

            return $output;
        }
    }

    /**
     * Creates a copy button
     *
     * @param object $item
     * @param array $params
     * @param int $allowedtoadd
     * @param string $view
     *
     * Views:
     * Event, Venue
     */
    static public function copybutton($item, $params, $attribs, $allowedtoadd, $view)
    {
        if ($allowedtoadd) {
            $app = Factory::getApplication();

            if ($app->input->get('print','','int')) {
                return;
            }

            // Initialise variables.
            $user     = JemFactory::getUser();
            $userId   = $user->get('id');
            $uri      = Uri::getInstance();
            $settings = JemHelper::globalattribs();

            // On Joomla Edit icon is always used regardless if "Show icons" is set to Yes or No.
            $showIcon = $settings->get('global_show_icons', 1);

            switch ($view)
            {
                case 'editevent':
                    if ($showIcon) {
                        $image = jemhtml::icon( 'com_jem/calendar_copy.webp', 'fas fa-fw fa-copy jem-copybutton', Text::_('COM_JEM_COPY_EVENT'), NULL, !$app->isClient('site'));
                    } else {
                        $image = Text::_('COM_JEM_COPY_EVENT');
                    }
                    $id = isset($item->did) ? $item->did : $item->id;
                    $overlib = Text::_('COM_JEM_COPY_EVENT_DESC');
                    $text = Text::_('COM_JEM_COPY_EVENT');
                    $url = 'index.php?option=com_jem&view=editevent&task=event.copy&a_id='.$id.'&return='.base64_encode($uri);
                    break;

                case 'editvenue':
                    if ($showIcon) {
                        $image = jemhtml::icon( 'com_jem/calendar_copy.webp', 'fas fa-fw fa-copy jem-copybutton', Text::_('COM_JEM_COPY_VENUE'), NULL, !$app->isClient('site'));
                    } else {
                        $image = Text::_('COM_JEM_COPY_VENUE');
                    }
                    $id = $item->locid;
                    $overlib = Text::_('COM_JEM_COPY_VENUE_DESC');
                    $text = Text::_('COM_JEM_COPY_VENUE');
                    $url = 'index.php?option=com_jem&view=editvenue&task=venue.copy&a_id='.$id.'&return='.base64_encode($uri);
                    break;

                case 'venue':
                    if ($showIcon) {
                        $image = jemhtml::icon( 'com_jem/calendar_copy.webp', 'fas fa-fw fa-copy jem-copybutton', Text::_('COM_JEM_COPY_VENUE'), NULL, !$app->isClient('site'));
                    } else {
                        $image = Text::_('COM_JEM_COPY_VENUE');
                    }
                    $id = $item->id;
                    $overlib = Text::_('COM_JEM_COPY_VENUE_DESC');
                    $text = Text::_('COM_JEM_COPY_VENUE');
                    $url = 'index.php?option=com_jem&view=editvenue&task=venue.copy&a_id='.$id.'&return='.base64_encode($uri);
                    break;
            }

            if (!$url) {
                return; // we need at least url to generate useful output
            }

            $output = HTMLHelper::_('link', self::escapeLinkAttribute($url), $image, self::tooltip($text, $overlib));

            return $output;
        }
    }

    /**
     * Creates the print button
     *
     * @param string $print_link
     * @param array $params
     */
    static public function printbutton($print_link, $params)
    {
        $app      = Factory::getApplication();
        $settings = JemHelper::globalattribs();

        if ($settings->get('global_show_print_icon',0)) {

            $status = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=640,height=480,directories=no,location=no';

            if ($settings->get('global_show_icons',1)) {
                $image = jemhtml::icon( 'com_jem/printButton.webp', 'fa fa-fw fa-lg fa-print jem-printbutton', Text::_('JGLOBAL_PRINT'), NULL, !$app->isClient('site'));
            } else {
                $image = Text::_('COM_JEM_PRINT');
            }

            if ($app->input->get('print','','int')) {
                //button in popup
                $overlib = Text::_('COM_JEM_PRINT_DESC');
                $text = Text::_('COM_JEM_PRINT');
                $output = '<a href="#" onclick="window.print();return false;"><span class="icon icon-print"></span></a>';

            } else {
                //button in view
                $overlib = Text::_('COM_JEM_PRINT_DESC');
                $text = Text::_('COM_JEM_PRINT');
                $output = '<a href="' . self::escapeLinkAttribute($print_link . '&tmpl=component') . '" ' . self::tooltip($text, $overlib, 'editlinktip', 'bottom')
                        . ' onclick="window.open(this.href,\'win2\',\'' . $status . '\'); return false;">' . $image . '</a>';
            }
            return $output;
        }
        return;
    }

    /**
     * Creates the PDF download button.
     *
     * @param string $pdf_link
     */
    static public function pdfbutton($pdf_link)
    {
        $app      = Factory::getApplication();
        $settings = JemHelper::globalattribs();

        if (empty($pdf_link) || $app->input->get('print', '', 'int')) {
            return;
        }

        if (!class_exists('JemPdf', false) || !JemPdf::isAvailable()) {
            return;
        }

        if ($settings->get('global_show_icons', 1)) {
            $image = '<i class="fa fa-fw fa-lg fa-file-pdf jem-pdfbutton" aria-hidden="true"></i><span class="visually-hidden">'
                . Text::_('COM_JEM_ANNUALCALENDAR_DOWNLOAD_PDF')
                . '</span>';
        } else {
            $image = Text::_('COM_JEM_ANNUALCALENDAR_PDF');
        }

        return HTMLHelper::_('link', self::escapeLinkAttribute($pdf_link), $image, self::tooltip(Text::_('COM_JEM_ANNUALCALENDAR_PDF'), Text::_('COM_JEM_ANNUALCALENDAR_DOWNLOAD_PDF'), '', 'bottom'));
    }

    /**
     * Creates the Today button.
     *
     * @param string $today_link
     */
    static public function todaybutton($today_link)
    {
        $app      = Factory::getApplication();
        $settings = JemHelper::globalattribs();

        if (empty($today_link) || $app->input->get('print', '', 'int')) {
            return;
        }

        if ($settings->get('global_show_icons', 1)) {
            $image = jemhtml::icon('com_jem/el.webp', 'fa fa-fw fa-lg fa-calendar-day jem-todaybutton', Text::_('COM_JEM_TIMETABLE_TODAY'), null, !$app->isClient('site'));
        } else {
            $image = Text::_('COM_JEM_TIMETABLE_TODAY');
        }

        $text = Text::_('COM_JEM_TIMETABLE_TODAY');

        return HTMLHelper::_('link', self::escapeLinkAttribute($today_link), $image, self::tooltip($text, $text, '', 'bottom'));
    }

    /**
     * Creates the email and public link sharing actions.
     *
     * @param object $slug
     * @param $view
     * @param array $params
     *
     * Views:
     * Category, Event, Venue
     */
    static public function mailbutton($slug, $view, $params)
    {
        $app = Factory::getApplication();
        $settings = JemHelper::globalattribs();

        if (!$settings->get('global_show_email_icon') || $app->input->get('print', '', 'int')) {
            return;
        }

        $uri = Uri::getInstance();
        $base = $uri->toString(array('scheme', 'host', 'port'));
        $link = $base . Route::_('index.php?option=com_jem&view=' . $view . '&id=' . $slug, false);
        $shareHtml = self::shareLinkButton($link, $settings, $app);

        if ($app->getIdentity()->guest) {
            return $shareHtml;
        }

        $url = 'index.php?option=com_jem&tmpl=component&view=mailto&link=' . JemMailtoHelper::addLink(
            $link,
            array('view' => $view, 'id' => $slug)
        );

        if ($settings->get('global_show_icons')) {
            $image = jemhtml::icon(
                'com_jem/emailButton.webp',
                'fa fa-fw fa-lg fa-envelope jem-mailbutton',
                Text::_('COM_JEM_INVITE_BY_EMAIL'),
                null,
                !$app->isClient('site')
            );
        } else {
            $image = Text::_('COM_JEM_INVITE_BY_EMAIL');
        }

        $overlib = Text::_('COM_JEM_INVITE_BY_EMAIL_DESC');
        $text = Text::_('COM_JEM_INVITE_BY_EMAIL');
        $html = HTMLHelper::_(
            'bootstrap.renderModal',
            'mailto-modal',
            array(
                'url' => $url,
                'title' => Text::_('COM_JEM_SELECT'),
                'width' => '800px',
                'height' => '550px',
                'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">'
                    . Text::_('COM_JEM_CLOSE') . '</button>',
            )
        );
        $html .= '<a href="' . self::escapeLinkAttribute($url)
            . '" data-bs-toggle="modal" data-bs-target="#mailto-modal" '
            . self::tooltip($text, $overlib, '', 'bottom') . '>' . $image . '</a>';
        $html .= '<span class="gap">&nbsp;</span>' . $shareHtml;

        return $html;
    }

    /**
     * Build a local copy-link action without third-party requests.
     *
     * @param   string  $link      Absolute public link.
     * @param   object  $settings  JEM global attributes.
     * @param   object  $app       Joomla application.
     *
     * @return  string
     */
    static protected function shareLinkButton($link, $settings, $app)
    {
        $document = $app->getDocument();
        $document->getWebAssetManager()->registerAndUseScript(
            'com_jem.share-link',
            'media/com_jem/js/share-link.js',
            array(),
            array('defer' => true)
        );

        $text = Text::_('COM_JEM_COPY_LINK');
        $description = Text::_('COM_JEM_COPY_LINK_DESC');

        if ($settings->get('global_show_icons')) {
            $image = jemhtml::icon(
                'com_jem/shareButton.svg',
                'fa fa-fw fa-lg fa-share-alt jem-sharebutton',
                $text,
                null,
                !$app->isClient('site')
            );
        } else {
            $image = $text;
        }

        return '<a href="' . self::escapeHtmlAttribute($link) . '" data-jem-share-link="'
            . self::escapeHtmlAttribute($link) . '" data-jem-share-success="'
            . self::escapeHtmlAttribute(Text::_('COM_JEM_LINK_COPIED')) . '" data-jem-share-prompt="'
            . self::escapeHtmlAttribute(Text::_('COM_JEM_COPY_LINK_PROMPT')) . '" aria-label="'
            . self::escapeHtmlAttribute($text) . '" '
            . self::tooltip($text, $description, 'jem-share-link', 'bottom') . '>' . $image . '</a>'
            . '<span class="visually-hidden" data-jem-share-status aria-live="polite"></span>';
    }

    /**
     * Creates the ical button
     *
     * @param object $slug
     * @view string view name
     * @task string task name
     */
    static public function icalbutton($slug, $view, $task = null)
    {
        $app = Factory::getApplication();
        $settings = JemHelper::globalattribs();

        if ($settings->get('global_show_ical_icon','0')==1) {
            if ($app->input->get('print','','int')) {
                return;
            }

            if ($settings->get('global_show_icons','0')==1) {
                $image = jemhtml::icon( 'com_jem/iCal2.0.webp', 'fa fa-fw fa-lg fa-calendar-check jem-icalbutton', Text::_('COM_JEM_EXPORT_ICS'), NULL, !$app->isClient('site'));
            } else {
                $image = Text::_('COM_JEM_EXPORT_ICS');
            }

            $overlib = Text::_('COM_JEM_ICAL_DESC');
            $text = Text::_('COM_JEM_ICAL');

            $url = 'index.php?option=com_jem&view=' . $view . '&id=' . ($slug??0) . ($task? '&task=' . $task : '') . '&format=raw&layout=ics';
            $output = HTMLHelper::_('link', self::escapeLinkAttribute($url), $image, self::tooltip($text, $overlib, '', 'bottom'));

            return $output;
        }
    }

    /**
     * Creates the publish button
     *
     * View:
     * Myevents, Myvenues
     */
    static public function publishbutton($prefix)
    {
        $app = Factory::getApplication();

        if (empty($prefix) || $app->input->get('print','','int')) {
            // button in popup or wrong call
            $output = '';
        } else {
            // button in view
            $image = jemhtml::icon( 'com_jem/publish.webp', 'fa fa-fw fa-lg fa-check-circle jem-publishbutton', Text::_('COM_JEM_PUBLISH'), NULL, !$app->isClient('site'));
            $overlib = Text::_('COM_JEM_PUBLISH_DESC');
            $text = Text::_('COM_JEM_PUBLISH');

            $print_link = "javascript:void(Joomla.submitbutton('" . $prefix . ".publish'));";
            $output = '<a href="' . self::escapeLinkAttribute($print_link) . '" ' . self::tooltip($text, $overlib, 'editlinktip', 'bottom') . '>' . $image . '</a>';
        }

        return $output;
    }

    /**
     * Creates the trash button
     *
     * View:
     * Myevents, Myvenues
     */
    static public function trashbutton($prefix)
    {
        $app = Factory::getApplication();

        if (empty($prefix) || $app->input->get('print','','int')) {
            // button in popup or wrong call
            $output = '';
        } else {
            // button in view
            $image = jemhtml::icon( 'com_jem/trash.webp', 'fa fa-fw fa-lg fa-trash jem-trashbutton', Text::_('COM_JEM_TRASH'), NULL, !$app->isClient('site'));
            $overlib = Text::_('COM_JEM_TRASH_DESC');
            $text = Text::_('COM_JEM_TRASH');

            $print_link = "javascript:void(Joomla.submitbutton('" . $prefix . ".trash'));";
            $output = '<a href="' . self::escapeLinkAttribute($print_link) . '" ' . self::tooltip($text, $overlib, 'editlinktip', 'bottom') . '>' . $image . '</a>';
        }

        return $output;
    }

    /**
     * Creates the unpublish button
     *
     * View:
     * Myevents, Myvenues
     */
    static public function unpublishbutton($prefix)
    {
        $app = Factory::getApplication();

        if (empty($prefix) || $app->input->get('print','','int')) {
            // button in popup or wrong call
            $output = '';
        } else {
            // button in view
            $image = jemhtml::icon( 'com_jem/unpublish.webp', 'fa fa-fw fa-lg fa-eye-slash jem-unpublishbutton', Text::_('COM_JEM_UNPUBLISH'), NULL, !$app->isClient('site'));
            $overlib = Text::_('COM_JEM_UNPUBLISH_DESC');
            $text = Text::_('COM_JEM_UNPUBLISH');

            $print_link = "javascript:void(Joomla.submitbutton('" . $prefix . ".unpublish'));";
            $output = '<a href="' . self::escapeLinkAttribute($print_link) . '" ' . self::tooltip($text, $overlib, 'editlinktip', 'bottom') . '>' . $image . '</a>';
        }

        return $output;
    }

    /**
     * Creates the export button
     *
     * view:
     * attendees
     */
    static public function exportbutton($eventid)
    {
        $app = Factory::getApplication();

        $image = jemhtml::icon( 'com_jem/export_excel.webp', 'fa fa-fw fa-lg fa-download jem-exportbutton', Text::_('COM_JEM_EXPORT'), NULL, !$app->isClient('site'));

        if ($app->input->get('print','','int')) {
            //button in popup
            $output = '';
        } else {
            //button in view
            $overlib = Text::_('COM_JEM_EXPORT_DESC');
            $text = Text::_('COM_JEM_EXPORT');

            $print_link = 'index.php?option=com_jem&view=attendees&task=attendees.export&tmpl=raw&id=' . $eventid;
            $output = '<a href="' . self::escapeLinkAttribute($print_link) . '" ' . self::tooltip($text, $overlib, 'editlinktip', 'bottom') . '>' . $image . '</a>';
        }

        return $output;
    }

    /**
     * Creates the back button
     *
     * view:
     * attendees
     */
    static public function backbutton($backlink, $view)
    {
        $app = Factory::getApplication();
        $id  = $app->input->getInt('id');
        $fid = $app->input->getInt('Itemid');

        $image = jemhtml::icon( 'com_jem/icon-16-back.webp', 'fa fa-fw fa-lg fa-chevron-circle-left jem-backbutton', Text::_('COM_JEM_BACK'), NULL, !$app->isClient('site'));

        if ($app->input->get('print','','int')) {
            //button in popup
            $output = '';
        } else {
            //button in view
            $overlib = Text::_('COM_JEM_BACK');
            $text = Text::_('COM_JEM_BACK');

            $link = 'index.php?option=com_jem&view='.$view.'&id='.$id.'&Itemid='.$fid.'&task='.$view.'.back';
            $output = '<a href="' . self::escapeLinkAttribute($link) . '" ' . self::tooltip($text, $overlib, 'editlinktip', 'bottom') . '>' . $image . '</a>';
        }

        return $output;
    }

    /**
     * Creates tooltip attributes.
     *
     * @param  string  $title   translated title of the tooltip
     * @param  string  $text    translated text of the tooltip
     * @param  string  $classes additional css classes (optional)
     *
     * @return string  attributes in form 'class="..." title="..."'
     */
    static public function tooltip($title, $text, $classes = '', $position = '')
    {
        $result = array();

        $result = 'class="'.$classes.' hasTooltip" data-bs-toggle="tooltip" title="'.HTMLHelper::tooltipText($title, $text, 0).'"';
        if (!empty($position) && (array_search($position, array('top', 'bottom', 'left', 'right')) !== false)) {
            $result .= ' data-placement="'.$position.'"';
        }

        return $result;
    }

    /**
     * Build an identifying User-Agent for Nominatim requests.
     *
     * @return string
     */
    static protected function nominatimUserAgent()
    {
        return 'JEM (+https://www.joomlaeventmanager.net; site=' . Uri::root() . ')';
    }

    /**
     * Render an OpenStreetMap canvas using JEM's local Leaflet assets.
     *
     * The map is initialised by osm-map.js when it is visible. This also supports
     * maps inside Bootstrap modals, whose dimensions are not available until the
     * modal has been opened.
     *
     * @param float  $latitude  Marker latitude
     * @param float  $longitude Marker longitude
     * @param string $height    CSS height including its unit
     * @param int    $zoom      Initial Leaflet zoom level
     * @param string $id        Optional unique element id
     * @param string $class     Optional additional CSS classes
     * @param string $marker    Configured fallback marker image
     * @param string $typeIcon  Optional type icon CSS class
     * @param string $typeColor Optional type marker background colour
     *
     * @return string
     */
    static public function osmMapCanvas($latitude, $longitude, $height = '250px', $zoom = 15, $id = '', $class = '', $marker = '', $typeIcon = '', $typeColor = '')
    {
        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if ($latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) {
            return '';
        }

        $height = preg_match('/^\d+(?:\.\d+)?(?:px|vh|vw|rem|%)$/', (string) $height)
            ? (string) $height
            : '250px';
        $zoom = max(1, min(19, (int) $zoom));
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $id);
        $class = trim(preg_replace('/[^A-Za-z0-9 _-]/', '', (string) $class));
        $marker = JemMapHelper::resolveMarkerUrl($marker, 'media/com_jem/images/marker-red.webp');
        $typeIcon = trim((string) $typeIcon);
        $typeIcon = preg_match('/^[a-zA-Z0-9_-]+(?:\s+[a-zA-Z0-9_-]+)*$/', $typeIcon) ? $typeIcon : '';
        $typeColor = trim((string) $typeColor);
        $typeColor = preg_match('/^#[0-9a-fA-F]{6}$/', $typeColor) ? strtolower($typeColor) : '#d9ddb5';
        $typeIconColor = JemHelper::getContrastTextColor($typeColor) ?: '#ffffff';

        if ($id === '') {
            static $mapNumber = 0;
            $id = 'jem-osm-map-' . ++$mapNumber;
        }

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

        JemHelper::loadCss('leaflet');
        if (!$wa->assetExists('script', 'leaflet')) {
            $wa->registerScript('leaflet', 'media/com_jem/js/leaflet.js');
        }
        if (!$wa->assetExists('script', 'jem.osm-map')) {
            $wa->registerScript('jem.osm-map', 'media/com_jem/js/osm-map.js', array(), array('defer' => true), array('leaflet'));
        }

        $wa->useScript('leaflet');
        $wa->useScript('jem.osm-map');

        return '<div id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"'
            . ' class="jem-osm-map' . ($class !== '' ? ' ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') : '') . '"'
            . ' style="width:100%;height:' . htmlspecialchars($height, ENT_QUOTES, 'UTF-8') . ';min-height:1px"'
            . ' data-latitude="' . htmlspecialchars((string) $latitude, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-longitude="' . htmlspecialchars((string) $longitude, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-zoom="' . $zoom . '"'
            . ' data-marker="' . htmlspecialchars($marker, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-type-icon="' . htmlspecialchars($typeIcon, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-type-color="' . htmlspecialchars($typeColor, ENT_QUOTES, 'UTF-8') . '"'
            . ' data-type-icon-color="' . htmlspecialchars($typeIconColor, ENT_QUOTES, 'UTF-8') . '"'
            . ' role="region" aria-label="' . htmlspecialchars(Text::_('COM_JEM_MAP'), ENT_QUOTES, 'UTF-8') . '"></div>';
    }

    /**
     * Creates the map button
     *
     * @param obj $data
     */
    static public function mapicon($data, $view, $params)
    {
        $app = Factory::getApplication();
        $settings = JemHelper::globalattribs();
        $paramGet = static function ($source, string $name, $default = null) {
            if (is_object($source) && method_exists($source, 'get')) {
                return $source->get($name, $default);
            }

            if (is_array($source) && array_key_exists($name, $source)) {
                return $source[$name];
            }

            if (is_object($source) && isset($source->$name)) {
                return $source->$name;
            }

            return $default;
        };

        //stop if disabled
        if (!$data->map) {
            return;
        }

        $latitude = trim((string) ($data->latitude ?? ''));
        $longitude = trim((string) ($data->longitude ?? ''));
        $hasCoordinates = $latitude !== '' && $longitude !== ''
            && is_numeric($latitude) && is_numeric($longitude)
            && (float) $latitude >= -90.0 && (float) $latitude <= 90.0
            && (float) $longitude >= -180.0 && (float) $longitude <= 180.0
            && (float) $latitude !== 0.0
            && (float) $longitude !== 0.0;
        $hasAddress = trim((string) ($data->street ?? '')) !== ''
            && trim((string) ($data->city ?? '')) !== ''
            && trim((string) ($data->country ?? '')) !== ''
            && trim((string) ($data->postalCode ?? '')) !== '';

        if (!$hasCoordinates && !$hasAddress) {
            return;
        }

        if ($view == 'event') {
            $tld     = 'event_tld';
            $lg      = 'event_lg';
            $mapserv = $paramGet($params, 'event_show_mapserv');
        } else if ($view == 'venues') {
            $tld     = 'global_tld';
            $lg      = 'global_lg';
            $mapserv = (int) $paramGet($params, 'global_show_mapserv');
            $mapserv = ($mapserv == 3) ? 0 : $mapserv;
        } else {
            $tld     = 'global_tld';
            $lg      = 'global_lg';
            $mapserv = $paramGet($params, 'global_show_mapserv');
        }

        //Link to map
        $mapimage = jemhtml::icon( 'com_jem/map_icon.webp', 'fa fa-map', Text::_('COM_JEM_MAP'), 'class="jem-mapicon"');

        //set var
        $output = null;
        $attributes = null;

        $data->country = StringHelper::strtoupper($data->country);

        if ($data->latitude == 0.000000) {
            $data->latitude = null;
        }
        if ($data->longitude == 0.000000) {
            $data->longitude = null;
        }

        $url = 'https://nominatim.openstreetmap.org/ui/search.html?q=' . urlencode($data->street . ', ' . $data->postalCode . ' ' . $data->city);

        // maps
        switch ($mapserv)
        {
            case 1:
                // google map link
                if ($hasCoordinates) {
                    $url = 'https://maps.google.'.$paramGet($params, $tld, 'com').'/maps?hl='.$paramGet($params, $lg, 'en').'&q=loc:'.$data->latitude.',+'.$data->longitude.'&amp;ie=UTF8&amp;t=m&amp;z=14&amp;iwloc=B';
                } else {
                $url = 'https://www.google.'.$paramGet($params, $tld, 'com').'/maps/place/'.htmlentities($data->street.',+'.$data->postalCode.'+'.$data->city.'+'.$data->country).'?hl='.$paramGet($params, $lg, 'en').'+('.$data->venue.')'; }

                $message = Text::_('COM_JEM_MAP').':';
                $attributes = ' rel="{handler: \'iframe\', size: {x: 800, y: 500}}" latitude="" longitude=""';
                $output = '<dt class="venue_mapicon">'.$message.'</dt><dd class="venue_mapicon"><a class="flyermodal mapicon jem-map-button" title="'.Text::_('COM_JEM_MAP').'" target="_blank" href="'.$url.'"'.$attributes.'>'.$mapimage.'&nbsp;'.Text::sprintf('COM_JEM_LINK_TO_GOOGLE_MAP', $data->venue) .'</a></dd>';
                break;

            case 2:
                // include iframe
                if ($hasCoordinates) {
                    $url = 'https://maps.google.'.$paramGet($params, $tld, 'com').'/maps?width=100%25&amp;height=600&amp;hl='.$paramGet($params, $lg, 'en').'&q=loc:'.$data->latitude.',+'.$data->longitude.'&amp;ie=UTF8&amp;t=m&amp;z=14&amp;iwloc=B&amp;output=embed';
                }
                else {
                    $url = 'https://maps.google.'.$paramGet($params, $tld, 'com').'/maps?hl='.$paramGet($params, $lg, 'en').'&q='.urlencode($data->street.',+'.$data->postalCode.'+'.$data->city.'+'.$data->country).'&ie=UTF8&z=15&iwloc=B&output=embed';
                }

                $output = '<div class="venue_map"><iframe width="500" height="250" src="'.$url.'" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" ></iframe></div>';
                break;

            case 3:
                // include Google map with API3
                // NOT WORKING YET 2023-05
                # https://developers.google.com/maps/documentation/javascript/tutorial
                $api = $paramGet($params, 'global_googleapi');
                $clientid = $paramGet($params, 'global_googleclientid');
                $output = '';

                if (empty($api) && empty($clientid)) {
                    break;
                } else {
                    $api = trim($api);
                    $clientid = trim($clientid);
                }

                $document   = $app->getDocument();

                # do we have a client-ID?
                if ($clientid) {
                    $document->addScript('https://maps.googleapis.com/maps/api/js?client='.$clientid.'&sensor=false&v=3.15');
                } else {
                    # do we have an api-key?
                    if ($api) {
                        $document->addScript('https://maps.googleapis.com/maps/api/js?key='.$api.'&sensor=false');
                    } else {
                        $document->addScript('https://maps.googleapis.com/maps/api/js?sensor=false');
                    }
                }

                JemHelper::loadCss('googlemap');

                 $wa = $app->getDocument()->getWebAssetManager();
                 $wa->registerScript('jem.infobox', 'com_jem/infobox.js')->useScript('jem.infobox');
                 $wa->registerScript('jem.googlemap', 'com_jem/googlemap.js')->useScript('jem.googlemap');

                $output = '<div id="map-canvas" class="map_canvas"/></div>';
                break;

            case 4:
                // OpenStreetMap link
                if ($hasCoordinates) {
                    $lat = $data->latitude;
                    $lng = $data->longitude;
                } else {
                $address = 'street=' . urlencode($data->street) . '&city=' . urlencode($data->city) . '&country=' . urlencode($data->country) . '&postalcode=' . urlencode($data->postalCode);
                $search_url = "https://nominatim.openstreetmap.org/search?q=" . urlencode($address) . "&format=jsonv2";
                $httpOptions = [
                    "http" => [
                        "method" => "GET",
                        "header" => "User-Agent: " . self::nominatimUserAgent(),
                        "timeout" => 10 // Timeout in Seconds
                    ]
                ];

                $streamContext = stream_context_create($httpOptions);
                $json = file_get_contents($search_url, false, $streamContext);

                $decoded = json_decode($json, true);
                $lat = $decoded[0]["lat"] ?? null;
                $lng = $decoded[0]["lon"] ?? null;
                }

                if ($lat && $lng) {
                    $url = 'https://www.openstreetmap.org/?mlat=' . htmlentities($lat) . '&mlon=' . htmlentities($lng) . '&zoom=15#map=15/' . htmlentities($lat) . '/' . htmlentities($lng);
                } else {
                    $url = 'https://nominatim.openstreetmap.org/ui/search.html?' . $address; // Handle the case when coordinates are not found
                }

                $message = Text::_('COM_JEM_MAP') . ':';
                $attributes = ' rel="{handler: \'iframe\', size: {x: 800, y: 500}}" latitude="" longitude=""';
                $output = '<dt class="venue_mapicon">' . $message . '</dt><dd class="venue_mapicon"><a class="flyermodal mapicon jem-map-button" title="' . Text::_('COM_JEM_MAP') . '" target="_blank" href="' . $url . '"' . $attributes . '>' . $mapimage . '&nbsp;' . Text::sprintf('COM_JEM_LINK_TO_OSM', $data->venue) . '</a></dd>';

                break;

            case 5:
                // embed OpenStreetMap
                if ($hasCoordinates) {
                    $lat = $data->latitude;
                    $lng = $data->longitude;
                } else {
                $address = 'street=' . urlencode($data->street) . '&city=' . urlencode($data->city) . '&country=' . urlencode($data->country) . '&postalcode=' . urlencode($data->postalCode);
                $search_url = "https://nominatim.openstreetmap.org/search?" . $address . "&format=jsonv2";
                $httpOptions = [
                    "http" => [
                        "method" => "GET",
                        "header" => "User-Agent: " . self::nominatimUserAgent(),
                        "timeout" => 10 // Timeout in seconds
                    ]
                ];

                $streamContext = stream_context_create($httpOptions);
                $json = file_get_contents($search_url, false, $streamContext);

                $decoded = json_decode($json, true);
                $lat = $decoded[0]["lat"] ?? null;
                $lng = $decoded[0]["lon"] ?? null;
                }

                if ($lat && $lng) {
                    $typeIcon = '';
                    $typeColor = '';

                    if ($view === 'event') {
                        $typeIcon = (string) ($data->type_icon ?? '');
                        $typeColor = (string) ($data->type_color ?? '');

                        if ($typeIcon === '' && !empty($data->categories)) {
                            foreach ((array) $data->categories as $category) {
                                if (!empty($category->type_icon)) {
                                    $typeIcon = (string) $category->type_icon;
                                    $typeColor = (string) ($category->type_color ?? '');
                                    break;
                                }
                            }
                        }

                        if ($typeIcon === '') {
                            $typeIcon = (string) ($data->venue_type_icon ?? '');
                            $typeColor = (string) ($data->venue_type_color ?? '');
                        }
                    } else {
                        $typeIcon = (string) ($data->type_icon ?? $data->venue_type_icon ?? '');
                        $typeColor = (string) ($data->type_color ?? $data->venue_type_color ?? '');
                    }

                    $marker = $paramGet($params, 'venue_markerfile', 'media/com_jem/images/marker-red.webp');
                    $output = self::osmMapCanvas($lat, $lng, '250px', 15, '', '', $marker, $typeIcon, $typeColor);
                } else {
                    $fallback_url = "https://nominatim.openstreetmap.org/ui/search.html?" . $address;
                    $output = '<p>' . Text::sprintf('COM_JEM_OSM_NO_MAP', $fallback_url) . '</p>';
                }
            break;
        }
        return $output;
    }

    /**
     * Creates the recurrence icon
     *
     * @param obj  $event
     * @param bool $showinline Add css class to scale icon to fit text height
     * @param bool $showtitle  Add title (tooltip)
     */
    public static function recurrenceicon($event, $showinline = true, $showtitle = true)
    {
        $app       = Factory::getApplication();
        $settings2 = JemHelper::config();
        $item = empty($event->recurr_bak) ? $event : $event->recurr_bak;

        //stop if disabled
        if (empty($item->recurrence_number) && empty($item->recurrence_type) && empty($item->series_id)) {
            return null;
        }

        $iconRecurrenceFirst = 'fa fa-fw fa-refresh jem-recurrencefirsticon';
        $iconRecurrence      = 'fa fa-fw fa-refresh jem-recurrenceicon';

        $first = !empty($item->series_id)
            ? ((int) ($item->series_order ?? 0) === 1)
            : (!empty($item->recurrence_type) && empty($item->recurrence_first_id));

        $image = $first
            ? 'com_jem/icon-32-recurrence-first.svg'
            : 'com_jem/icon-32-recurrence.svg';

        $icon = $first ? $iconRecurrenceFirst : $iconRecurrence;

        $showinline &= !($settings2->useiconfont == 1 && $app->isClient('site'));

        $attribs = [];

        if ($showinline) {
            $attribs['class'] = 'icon-inline';
        }

        if ($showtitle) {
            $attribs['title'] = Text::_(
                $first
                    ? 'COM_JEM_RECURRING_FIRST_EVENT_DESC'
                    : 'COM_JEM_RECURRING_EVENT_DESC'
            );
        }

        return jemhtml::icon(
            $image,
            $icon,
            Text::_('COM_JEM_RECURRING_EVENT'),
            $attribs,
            !$app->isClient('site')
        );
    }

    /**
     * Creates the unpublished icon
     *
     * @param mixed $item         mixed Object with attribute 'published' or plain value containing the state (well known -2, 0, 1, 2)
     * @param array $ignorestates States to ignore (returning empty string), defaults to trashed (-2), published (1) and archived (2)
     * @param bool  $showinline   Add css class to scale icon to fit text height
     * @param bool  $showtitle    Add title (tooltip)
     */
    static public function publishstateicon($item, $ignorestates = array(-2, 1, 2), $showinline = true, $showtitle = true)
    {
        //$settings = JemHelper::globalattribs();  /// @todo use global setting to influence visibility of publish state icon?
        $app = Factory::getApplication();

        // early return
        if (is_object($item)) {
            if (!isset($item->published) || in_array($item->published, $ignorestates)) {
                return '';
            }
        } else {
            if (in_array($item, $ignorestates)) {
                return '';
            }
        }

        $published = is_object($item) ? $item->published : $item;
        switch ($published) {
        case -2: // trashed
            $image = 'com_jem/trash.webp';
            $icon = 'fa fa-fw fa-lg fa-trash jem-publishstateicon-trashed';
            $alt   = Text::_('JTRASHED');
            break;
        case  0: // unpublished F10C: fa-circle-o F070: fa-eye-slash, F192: fa-dot-circle-o
            $image = 'com_jem/publish_x.webp';
            $icon = 'fa fa-fw fa-lg fa-eye-slash jem-publishstateicon-unpublished';
            $alt   = Text::_('JUNPUBLISHED');
            break;
        case  1: // published F06E: fa-eye
            $image = 'com_jem/publish.webp';
            $icon = 'fa fa-fw fa-lg fa-check-circle jem-publishstateicon-published';
            $alt   = Text::_('JPUBLISHED');
            break;
        case  2: // archived
            $image = 'com_jem/archive_front.webp';
            $icon = 'fa fa-fw fa-lg fa-archive jem-publishstateicon-archived';
            $alt   = Text::_('JARCHIVED');
            break;
        default: // unknown state - abort!
            return '';
        }

        // additional attributes
        $attributes = array();
        if ($showinline) {
            $attributes['class'] = 'icon-inline';
        }
        if ($showtitle) {
            $attributes['title'] = $alt;
        }

        $output = jemhtml::icon( $image, $icon, $alt, $attributes, !$app->isClient('site'));

        return $output;
    }

    static public function getEffectiveTicketAvailability($event)
    {
        $validAvailabilities = array('instock', 'preorder', 'soldout');
        $ticketAvailability = !empty($event->ticket_availability) && in_array($event->ticket_availability, $validAvailabilities, true) ? $event->ticket_availability : 'instock';

        if ($ticketAvailability !== 'instock') {
            return $ticketAvailability;
        }

        $maxplaces = isset($event->maxplaces) ? (int) $event->maxplaces : 0;
        if ($maxplaces <= 0) {
            return 'instock';
        }

        $booked = isset($event->booked) ? (int) $event->booked : (isset($event->regCount) ? (int) $event->regCount : 0);
        $reserved = isset($event->reservedplaces) ? (int) $event->reservedplaces : (isset($event->reserved) ? (int) $event->reserved : 0);

        if (($booked + $reserved) >= $maxplaces) {
            return !empty($event->waitinglist) ? 'waitinglist' : 'soldout';
        }

        return 'instock';
    }

    /**
     * Add the effective module status presentation to event rows.
     *
     * Registration totals are loaded once for the complete result set when
     * availability indicators are enabled.
     *
     * @param array       $events   Event rows
     * @param object|null $settings JEM settings, mainly for tests
     * @param int|null    $now      Current timestamp, mainly for tests
     *
     * @return void
     */
    static public function prepareModuleEventStatuses(&$events, $settings = null, $now = null)
    {
        if (!is_array($events) || $events === array()) {
            return;
        }

        $settings = $settings ?: JemHelper::config();
        if (!(int) ($settings->module_status_ribbons ?? 1)) {
            return;
        }

        $needsRegistrationTotals = false;
        foreach (array('soldout', 'waitinglist', 'last_places', 'open') as $status) {
            if (self::isModuleStatusActive($settings, $status)) {
                $needsRegistrationTotals = true;
                break;
            }
        }

        if ($needsRegistrationTotals) {
            $hasRegistrationTotals = true;

            foreach ($events as $event) {
                if (!isset($event->regCount)) {
                    $hasRegistrationTotals = false;
                    break;
                }
            }

            if (!$hasRegistrationTotals) {
                JemHelper::getAttendeesNumbers($events);
            }
        }

        foreach ($events as $event) {
            $event->event_status_indicators_prepared = true;
            $event->module_event_status = self::getModuleEventStatus($event, $settings, $now);
        }
    }

    /**
     * Resolve the single status indicator shown by event modules.
     *
     * @param object      $event    Event row
     * @param object|null $settings JEM settings, mainly for tests
     * @param int|null    $now      Current timestamp, mainly for tests
     *
     * @return array|null
     */
    static public function getModuleEventStatus($event, $settings = null, $now = null)
    {
        if (!is_object($event)) {
            return null;
        }

        $settings = $settings ?: JemHelper::config();
        if (!(int) ($settings->module_status_ribbons ?? 1)) {
            return null;
        }

        $eventStatus = self::getEventStatusPresentation($event);
        if ($eventStatus['status'] !== 'scheduled'
            && self::isModuleStatusActive($settings, $eventStatus['status'])) {
            return $eventStatus;
        }

        $availabilityStatuses = array('preorder', 'soldout', 'waitinglist', 'last_places');
        $hasActiveAvailabilityStatus = false;
        foreach ($availabilityStatuses as $status) {
            if (self::isModuleStatusActive($settings, $status)) {
                $hasActiveAvailabilityStatus = true;
                break;
            }
        }

        if ($hasActiveAvailabilityStatus) {
            $registrationOpen = JemHelper::isEventRegistrationOpen($event, $now);
            $ticketAvailability = strtolower(trim((string) ($event->ticket_availability ?? 'instock')));

            if ($ticketAvailability === 'soldout'
                && self::isModuleStatusActive($settings, 'soldout')) {
                return self::getModuleStatusPresentation('soldout');
            }

            if ($ticketAvailability === 'preorder'
                && self::isModuleStatusActive($settings, 'preorder')) {
                return self::getModuleStatusPresentation('preorder');
            }

            if ($registrationOpen) {
                $maxPlaces = max(0, (int) ($event->maxplaces ?? 0));
                $registeredPlaces = max(0, (int) ($event->regCount ?? $event->booked ?? 0));
                $reservedPlaces = max(0, (int) ($event->reservedplaces ?? $event->reserved ?? 0));
                $availablePlaces = $maxPlaces > 0
                    ? max(0, $maxPlaces - $registeredPlaces - $reservedPlaces)
                    : null;

                if ($availablePlaces === 0) {
                    if (!empty($event->waitinglist)
                        && self::isModuleStatusActive($settings, 'waitinglist')) {
                        return self::getModuleStatusPresentation('waitinglist');
                    }

                    if (self::isModuleStatusActive($settings, 'soldout')) {
                        return self::getModuleStatusPresentation('soldout');
                    }
                }

                $lastPlacesThreshold = max(1, (int) ($settings->module_status_last_places_threshold ?? 10));
                if (self::isModuleStatusActive($settings, 'last_places')
                    && $availablePlaces !== null
                    && $availablePlaces < $lastPlacesThreshold) {
                    return self::getModuleStatusPresentation('last_places');
                }
            }
        }

        if (self::isModuleStatusActive($settings, 'new')) {
            $created = strtotime((string) ($event->created ?? ''));
            $newDays = max(1, (int) ($settings->module_status_new_days ?? 7));
            $now = $now === null ? time() : (int) $now;

            if ($created !== false && $created <= $now && $created >= ($now - ($newDays * 86400))) {
                return self::getModuleStatusPresentation('new');
            }
        }

        if (self::isModuleStatusActive($settings, 'open')
            && JemHelper::isEventRegistrationOpen($event, $now)) {
            return self::getModuleStatusPresentation('open');
        }

        return null;
    }

    /**
     * Check whether one module status is enabled in the global policy.
     *
     * @param object $settings JEM settings
     * @param string $status   Internal module status name
     *
     * @return bool
     */
    static protected function isModuleStatusActive($settings, $status)
    {
        $property = 'module_status_active_' . $status;
        $default = $status === 'open' ? 0 : 1;

        return (int) ($settings->{$property} ?? $default) === 1;
    }

    /**
     * Render a module status as an image ribbon.
     *
     * @param object $event Prepared module event item
     *
     * @return string
     */
    static public function moduleEventStatusRibbon($event)
    {
        return self::renderModuleEventStatus($event, 'ribbon');
    }

    /**
     * Render a module status as a badge beside the event title.
     *
     * @param object $event Prepared module event item
     *
     * @return string
     */
    static public function moduleEventStatusBadge($event)
    {
        return self::renderModuleEventStatus($event, 'badge');
    }

    /**
     * Wrap a rendered event or venue image with its configured status ribbon.
     *
     * Only the first eligible image for an event receives the indicator.
     *
     * @param object $event     Prepared event row
     * @param string $imageHtml Trusted image markup generated by JEM
     * @param string $classes   Additional internal wrapper classes
     *
     * @return string
     */
    static public function eventStatusImage($event, $imageHtml, $classes = '')
    {
        $imageHtml = (string) $imageHtml;
        if ($imageHtml === ''
            || !is_object($event)
            || empty($event->event_status_indicators_prepared)
            || !empty($event->event_status_indicator_on_image)) {
            return $imageHtml;
        }

        $ribbon = self::moduleEventStatusRibbon($event);
        if ($ribbon === '') {
            return $imageHtml;
        }

        $classes = trim((string) preg_replace('/[^A-Za-z0-9 _-]/', '', (string) $classes));
        $event->event_status_indicator_image_available = true;
        $event->event_status_indicator_on_image = true;

        return '<div class="jem-event-status-image jem-module-event-status-image'
            . ($classes !== '' ? ' ' . $classes : '') . '">'
            . $imageHtml . $ribbon . '</div>';
    }

    /**
     * Render one configured status badge when no image can carry the ribbon.
     *
     * @param object $event Prepared event row
     *
     * @return string
     */
    static public function eventStatusFallbackBadge($event)
    {
        if (!is_object($event)
            || empty($event->event_status_indicators_prepared)
            || !empty($event->event_status_indicator_image_available)
            || !empty($event->event_status_indicator_on_image)
            || !empty($event->event_status_indicator_badge_rendered)) {
            return '';
        }

        $badge = self::moduleEventStatusBadge($event);
        if ($badge !== '') {
            $event->event_status_indicator_badge_rendered = true;
        }

        return $badge;
    }

    /**
     * Return the normalized presentation for a public event status.
     *
     * @param object $event Event row
     *
     * @return array
     */
    protected static function getEventStatusPresentation($event)
    {
        $status = strtolower(trim((string) ($event->event_status ?? 'scheduled')));
        $validStatuses = array('scheduled', 'cancelled', 'postponed', 'rescheduled', 'moved_online');

        return self::getModuleStatusPresentation(in_array($status, $validStatuses, true) ? $status : 'scheduled');
    }

    /**
     * Return a whitelisted module status presentation.
     *
     * @param string $status Status identifier
     *
     * @return array|null
     */
    protected static function getModuleStatusPresentation($status)
    {
        $options = array(
            'scheduled'    => array('label' => 'COM_JEM_EVENT_STATUS_SCHEDULED', 'class' => 'jem-event-state-badge--scheduled'),
            'cancelled'    => array('label' => 'COM_JEM_EVENT_STATUS_CANCELLED', 'class' => 'jem-event-state-badge--cancelled'),
            'postponed'    => array('label' => 'COM_JEM_EVENT_STATUS_POSTPONED', 'class' => 'jem-event-state-badge--postponed'),
            'rescheduled'  => array('label' => 'COM_JEM_EVENT_STATUS_RESCHEDULED', 'class' => 'jem-event-state-badge--rescheduled'),
            'moved_online' => array('label' => 'COM_JEM_EVENT_STATUS_MOVED_ONLINE', 'class' => 'jem-event-state-badge--moved-online'),
            'preorder'     => array('label' => 'COM_JEM_EVENT_AVAILABILITY_PREORDER', 'class' => 'jem-event-state-badge--preorder'),
            'soldout'      => array('label' => 'COM_JEM_EVENT_AVAILABILITY_SOLDOUT', 'class' => 'jem-event-state-badge--soldout'),
            'waitinglist'  => array('label' => 'COM_JEM_EVENT_AVAILABILITY_WAITINGLIST', 'class' => 'jem-event-state-badge--waitinglist'),
            'last_places'  => array('label' => 'COM_JEM_EVENT_AVAILABILITY_LAST_PLACES', 'class' => 'jem-event-state-badge--last-places'),
            'new'          => array('label' => 'COM_JEM_EVENT_STATUS_NEW', 'class' => 'jem-event-state-badge--new'),
            'open'         => array('label' => 'COM_JEM_EVENT_AVAILABILITY_OPEN', 'class' => 'jem-event-state-badge--available'),
        );

        if (!isset($options[$status])) {
            return null;
        }

        return array(
            'status' => $status,
            'label'  => $options[$status]['label'],
            'class'  => $options[$status]['class'],
        );
    }

    /**
     * Render prepared and whitelisted module status markup.
     *
     * @param object $event Event module item
     * @param string $mode  ribbon or badge
     *
     * @return string
     */
    protected static function renderModuleEventStatus($event, $mode)
    {
        $status = is_object($event) ? ($event->module_event_status ?? null) : null;
        if (!is_array($status) || empty($status['status']) || empty($status['label']) || empty($status['class'])) {
            return '';
        }

        $settings = JemHelper::config();
        $position = (string) ($settings->module_status_ribbon_position ?? 'diagonal_ascending');
        $validPositions = array(
            'horizontal_top',
            'horizontal_center',
            'horizontal_bottom',
            'diagonal_ascending',
            'diagonal_descending',
        );
        if (!in_array($position, $validPositions, true)) {
            $position = 'diagonal_ascending';
        }

        $defaultColors = array(
            'cancelled'    => array('#b3261ee6', '#ffffff'),
            'postponed'    => array('#b55b00e6', '#ffffff'),
            'rescheduled'  => array('#2456a5e6', '#ffffff'),
            'moved_online' => array('#247a3de6', '#ffffff'),
            'preorder'     => array('#b55b00e6', '#ffffff'),
            'soldout'      => array('#b3261ee6', '#ffffff'),
            'waitinglist'  => array('#b55b00e6', '#ffffff'),
            'last_places'  => array('#b55b00e6', '#ffffff'),
            'new'          => array('#2456a5e6', '#ffffff'),
            'open'         => array('#247a3de6', '#ffffff'),
        );
        $statusName = (string) $status['status'];
        if (!isset($defaultColors[$statusName])) {
            return '';
        }

        $backgroundKey = 'module_status_color_' . $statusName . '_bg';
        $textKey = 'module_status_color_' . $statusName . '_text';
        $background = self::normaliseModuleStatusColor(
            $settings->{$backgroundKey} ?? '',
            $defaultColors[$statusName][0],
            true
        );
        $textColor = self::normaliseModuleStatusColor(
            $settings->{$textKey} ?? '',
            $defaultColors[$statusName][1],
            false
        );
        $sideMargin = min(200, max(0, (int) ($settings->module_status_ribbon_side_margin ?? 0)));
        $ribbonScale = min(200, max(50, (int) ($settings->module_status_ribbon_scale ?? 100)));
        $label = Text::_($status['label']);
        $labelLength = min(40, max(1, mb_strlen($label)));
        $fontSize = max(0.58, min(0.85, 1.06 - (max(0, $labelLength - 8) * 0.022)));
        $style = '--jem-module-status-background:' . $background
            . ';--jem-module-status-color:' . $textColor
            . ';--jem-module-status-side-margin:' . $sideMargin . 'px'
            . ';--jem-module-status-font-size:' . number_format($fontSize, 2, '.', '') . 'rem;';
        $statusClass = str_replace('_', '-', $statusName);
        if ($mode === 'ribbon') {
            $baseClass = 'jem-event-status jem-event-status-ribbon jem-module-event-status jem-module-event-status-ribbon'
                . ' jem-event-status--' . $statusClass
                . ' jem-module-event-status-ribbon--' . str_replace('_', '-', $position)
                . ' jem-module-event-status--' . $statusClass
                . ' jem-module-event-status-ribbon--' . $statusClass;
            $dataAttributes = ' data-jem-module-status-scale="' . $ribbonScale . '"'
                . ' data-jem-module-status-base-font-size="'
                . number_format($fontSize, 2, '.', '') . '"';
        } else {
            $baseClass = 'jem-event-state-badge jem-event-status jem-event-status-badge jem-module-event-status jem-module-event-status-badge'
                . ' jem-event-status--' . $statusClass
                . ' jem-module-event-status--' . $statusClass
                . ' jem-module-event-status-badge--' . $statusClass;
            $dataAttributes = '';
        }

        return '<span class="' . $baseClass . ' ' . htmlspecialchars($status['class'], ENT_QUOTES, 'UTF-8')
            . '"' . $dataAttributes . ' style="' . $style . '">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</span>';
    }

    /**
     * Normalize a configurable module status color before using it in CSS.
     *
     * @param string $value       Configured color
     * @param string $fallback    Trusted fallback color
     * @param bool   $allowAlpha  Whether #RRGGBBAA is accepted
     *
     * @return string
     */
    protected static function normaliseModuleStatusColor($value, $fallback, $allowAlpha)
    {
        $pattern = $allowAlpha ? '/^#[0-9a-f]{8}$/i' : '/^#[0-9a-f]{6}$/i';
        $value = trim((string) $value);

        return preg_match($pattern, $value) ? strtolower($value) : $fallback;
    }

    /**
     * Create public event status and ticket availability badges.
     *
     * @param object $event            Event row
     * @param bool   $includeMicrodata Retained for template compatibility; ignored since 5.1
     * @param bool   $showAvailabilityText
     *
     * @return string
     */
    static public function eventStateBadges($event, $includeMicrodata = false, $showAvailabilityText = false)
    {
        if (empty($event)) {
            return '';
        }

        if (!empty($event->event_status_indicators_prepared)) {
            return self::eventStatusFallbackBadge($event);
        }

        $eventStatusOptions = array(
            'scheduled'    => array('label' => 'COM_JEM_EVENT_STATUS_SCHEDULED', 'class' => 'jem-event-state-badge--scheduled'),
            'cancelled'    => array('label' => 'COM_JEM_EVENT_STATUS_CANCELLED', 'class' => 'jem-event-state-badge--cancelled'),
            'postponed'    => array('label' => 'COM_JEM_EVENT_STATUS_POSTPONED', 'class' => 'jem-event-state-badge--postponed'),
            'rescheduled'  => array('label' => 'COM_JEM_EVENT_STATUS_RESCHEDULED', 'class' => 'jem-event-state-badge--rescheduled'),
            'moved_online' => array('label' => 'COM_JEM_EVENT_STATUS_MOVED_ONLINE', 'class' => 'jem-event-state-badge--moved-online'),
        );
        $ticketAvailabilityOptions = array(
            'instock'  => array('label' => 'COM_JEM_EVENT_AVAILABILITY_INSTOCK', 'class' => 'jem-event-state-badge--available'),
            'preorder' => array('label' => 'COM_JEM_EVENT_AVAILABILITY_PREORDER', 'class' => 'jem-event-state-badge--preorder'),
            'soldout'  => array('label' => 'COM_JEM_EVENT_AVAILABILITY_SOLDOUT', 'class' => 'jem-event-state-badge--soldout'),
            'waitinglist' => array('label' => 'COM_JEM_EVENT_AVAILABILITY_WAITINGLIST', 'class' => 'jem-event-state-badge--waitinglist'),
        );

        $eventStatus = !empty($event->event_status) && isset($eventStatusOptions[$event->event_status]) ? $event->event_status : 'scheduled';
        $eventStatusOption = $eventStatusOptions[$eventStatus];
        $ticketAvailability = self::getEffectiveTicketAvailability($event);
        $ticketAvailabilityOption = $ticketAvailabilityOptions[$ticketAvailability];

        $badges = array();
        if ($eventStatus !== 'scheduled') {
            $badges[] = '<span class="jem-event-state-badge ' . $eventStatusOption['class'] . '">' . htmlspecialchars(Text::_($eventStatusOption['label']), ENT_QUOTES, 'UTF-8') . '</span>';
        }
        if ($showAvailabilityText && $ticketAvailability !== 'instock') {
            $badges[] = '<span class="jem-event-state-badge ' . $ticketAvailabilityOption['class'] . '">' . htmlspecialchars(Text::_($ticketAvailabilityOption['label']), ENT_QUOTES, 'UTF-8') . '</span>';
        }

        if ($badges) {
            return '<span class="jem-event-badges jem-event-badges--list">' . implode('', $badges) . '</span>';
        }

        return '';
    }

    static public function typeBadge($event)
    {
        return self::typedEntityBadge($event, 'type_', 'event');
    }

    static public function typedEntityBadge($item, $prefix = 'type_', $entity = 'event')
    {
        self::translateType($item, $prefix);

        $nameProperty = $prefix . 'name';
        if (empty($item->{$nameProperty})) {
            return '';
        }

        $descriptionProperty = $prefix . 'description';
        $colorProperty       = $prefix . 'color';
        $iconProperty        = $prefix . 'icon';
        $idProperty          = $prefix . 'id';
        $aliasProperty       = $prefix . 'alias';

        $name       = htmlspecialchars($item->{$nameProperty}, ENT_QUOTES, 'UTF-8');
        $tooltip    = self::typeDescriptionSummary(isset($item->{$descriptionProperty}) ? $item->{$descriptionProperty} : '');
        $attributes = '';
        $style      = '';

        if ($tooltip !== '') {
            $safeTooltip = htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8');
            $attributes .= ' title="' . $safeTooltip . '" aria-label="' . $name . ': ' . $safeTooltip . '"';
        }

        if (!empty($item->{$colorProperty}) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $item->{$colorProperty})) {
            $style = ' style="background-color:' . htmlspecialchars($item->{$colorProperty}, ENT_QUOTES, 'UTF-8') . '; color:' . self::contrastingTextColor((string) $item->{$colorProperty}) . ';"';
        }

        $inner = '';
        if (!empty($item->{$iconProperty}) && self::isValidIconClass((string) $item->{$iconProperty})) {
            $icon  = htmlspecialchars($item->{$iconProperty}, ENT_QUOTES, 'UTF-8');
            $inner .= '<span class="' . $icon . '" aria-hidden="true"></span> ';
        }
        $inner .= $name;

        $typeRouteId = (int) $item->{$idProperty};
        if (!empty($item->{$aliasProperty})) {
            $typeRouteId .= ':' . $item->{$aliasProperty};
        }

        $route = $entity === 'venue'
            ? JemHelperRoute::getTypevenuesRoute($typeRouteId)
            : JemHelperRoute::getTypeeventsRoute($typeRouteId);
        $link = self::escapeLinkAttribute($route);

        return '<a href="' . $link . '" class="jem-type-badge"' . $style . $attributes . '>' . $inner . '</a>';
    }

    static protected function isValidIconClass($icon)
    {
        $icon = trim((string) $icon);

        return $icon !== ''
            && preg_match('/^[a-zA-Z0-9_ -]+$/', $icon)
            && preg_match('/\b(fa|fa-[a-z0-9-]+|icon-[a-z0-9-]+)\b/i', $icon);
    }

    static protected function contrastingTextColor($background)
    {
        if (!preg_match('/^#?([0-9a-fA-F]{6})$/', (string) $background, $matches)) {
            return '#ffffff';
        }

        $hex = $matches[1];
        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));
        $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $luminance > 145 ? '#111827' : '#ffffff';
    }

    static public function typeDescriptionSummary($description)
    {
        $text = trim(html_entity_decode(strip_tags((string) $description), ENT_QUOTES, 'UTF-8'));

        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\s+/', ' ', $text);
        $periodPosition = strpos($text, '.');

        if ($periodPosition !== false) {
            $text = substr($text, 0, $periodPosition + 1);
        }

        return trim($text);
    }

    static public function translateType($type, $prefix = '')
    {
        if (!is_object($type)) {
            return $type;
        }

        $nameProperty = $prefix . 'name';
        $descriptionProperty = $prefix . 'description';
        $translatedName = self::getTypeTranslationValue($type, $prefix, 'name');
        $translatedDescription = self::getTypeTranslationValue($type, $prefix, 'description');

        if ($translatedName !== '') {
            $type->{$nameProperty} = $translatedName;
        }

        if ($translatedDescription !== '') {
            $type->{$descriptionProperty} = $translatedDescription;
        }

        return $type;
    }

    static public function getTypeTranslationValue($type, $prefix, $field)
    {
        $translationsProperty = $prefix . 'translations';
        $languagesProperty = $prefix . 'translation_languages';
        $baseLanguageProperty = $prefix . 'base_language';
        $fallbackProperty = $prefix . $field;

        $translations = json_decode((string) ($type->{$translationsProperty} ?? ''), true);
        if (!is_array($translations)) {
            $translations = array();
        }

        $currentLanguage = Factory::getApplication()->getLanguage()->getTag();
        $defaultLanguage = (string) ComponentHelper::getParams('com_languages')->get('site', '');
        $baseLanguage = trim((string) ($type->{$baseLanguageProperty} ?? ''));
        $savedLanguages = array_filter(array_map('trim', explode(',', (string) ($type->{$languagesProperty} ?? ''))));
        $fallbackValue = trim((string) ($type->{$fallbackProperty} ?? ''));

        $fallbackOrder = array($currentLanguage, $defaultLanguage, 'en-GB');
        $fallbackOrder = array_merge($fallbackOrder, $savedLanguages, array_keys($translations));

        foreach ($fallbackOrder as $language) {
            $language = trim((string) $language);
            if ($fallbackValue !== '' && $baseLanguage !== '' && $language === $baseLanguage) {
                return $fallbackValue;
            }

            if ($language === '' || empty($translations[$language]) || !is_array($translations[$language])) {
                continue;
            }

            $value = trim((string) ($translations[$language][$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $fallbackValue;
    }

    /**
     * Creates the flyer
     *
     * @param obj $data
     * @param array $image
     * @param string $type
     */

    static public function flyer($data, $image, $type, $id = null)
    {
        $uri      = Uri::getInstance();
        $id_attr  = $id ? 'id="'.$id.'"' : '';
        $settings = JemHelper::config();
        switch($type) {
            case 'event':
                $folder = 'events';
                $imagefile = $id && isset($data->$id) ? $data->$id : $data->datimage;
                $info = $data->title;
                if(!$settings->flyer){
                    $precaption = Text::_('COM_JEM_EVENT');
                    $id = 'eventid-'. $data->id;
                }
                break;

            case 'category':
                $folder = 'categories';
                $imagefile = $data->image;
                $info = $data->catname;
                if(!$settings->flyer){
                    $precaption = Text::_('COM_JEM_CATEGORY');
                    $id = 'catid-'. $data->id;
                }
                break;

            case 'venue':
                $folder = 'venues';
                $imagefile = $data->locimage;
                $info = trim((string) ($data->locimage_alt ?? '')) !== ''
                    ? (string) $data->locimage_alt
                    : (string) $data->venue;
                if(!$settings->flyer){
                    $precaption = Text::_('COM_JEM_VENUE');
                    if (property_exists($data, 'locid')) {
                        $id = $data->locid;
                    } else {
                        $id = $data->id;
                    }
                }
                break;
        }

        $info = htmlspecialchars((string) $info, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Do we have an image?
        if (empty($imagefile) || empty($image)) {
            return;
        } else if(!$settings->flyer){
            list($imagewidth, $imageheight) = getimagesize(JPATH_SITE . '/' . $image['original']) ?? [100, 100];
            list($thumbwidth, $thumbheight) = getimagesize(JPATH_SITE . '/' . $image['thumb']) ?? [50, 50];
        }

        // Does a thumbnail exist?
        if (!$settings->flyer){
            $thumbPath = $image['thumb'] ?? '';

            if ($thumbPath !== '' && is_file(JPATH_SITE . '/' . $thumbPath)) {
                // if "Enable Pop Up Thumbnail" is disabled
                if (($settings->gddisabled == 0) && ($settings->lightbox == 0))    {
                    $icon = '<img src="'.$uri->base().$image['thumb'].'" width="'.$thumbwidth.'" height="'.$thumbheight.'" alt="'.$info.'" title="'.$info.'" />';
                    $output = '<div class="flyerimage">'.$icon.'</div>';
                }

                // if "Enable Pop Up Thumbnail" is enabled and lightbox disabled
                elseif (($settings->gddisabled == 1) && ($settings->lightbox == 0)) {
                    $attributes = $id_attr.' class="flyerimage" onclick="window.open(\''.$uri->base().$image['original'].'\',\'Popup\',\'width='. $imagewidth.',height='.$imageheight.',location=no,menubar=no,scrollbars=no,status=no,toolbar=no,resizable=no\')"';
                    $icon = '<img '.$attributes.' src="'.$uri->base().$image['thumb'].'" width="'.$thumbwidth.'" height="'.$thumbheight.'" alt="'.$info.'" title="'.Text::_('COM_JEM_CLICK_TO_ENLARGE').'" />';
                    $output = '<div class="flyerimage">'.$icon.'</div>';
                }

                // if "Enable Pop Up Thumbnail" and lightbox are enabled
                elseif (($settings->gddisabled == 1) && ($settings->lightbox == 1)) {
                    $url = $uri->base().$image['original'];
                    $attributes = $id_attr.' rel="lightbox" class="flyermodal flyerimage" data-lightbox="lightbox-image-'.$id.'" title="'.$info.'" data-title="'.$precaption.': '.$info.'"';
                    $icon = '<img class="example-thumbnail" itemprop="image" src="'.$uri->base().$image['thumb'].'" alt="'.$info.'" title="'.Text::_('COM_JEM_CLICK_TO_ENLARGE').'" />';
                    $output = '<div class="flyerimage"><a href="'.$url.'" '.$attributes.'>'.$icon.'</a></div>';

                }
                // If there is no thumbnail, then take the values for the original image specified in the settings
            } else {
                $output = '<img '.$id_attr.' class="notmodal" src="'.$uri->base().$image['original'].'" width="'.$image['width'].'" height="'.$image['height'].'" alt="'.$info.'" />';
            }
        }else{
            $output = '<img '.$id_attr.' class="notmodal img-responsive" src="'.$uri->base().$image['original'].'" style="width:auto;height:200px;" alt="'.$info.'" />';
        }

        if (($type === 'event' || $type === 'venue') && is_object($data)) {
            $output = self::eventStatusImage(
                $data,
                $output,
                'jem-module-event-status-image--inline jem-event-status-image--' . $type
            );
        }

        return $output;
    }

    /**
     * Formats date
     *
     * @param string $date
     * @param string $format
     * @return string $formatdate
     */
    static public function formatdate($date, $format = "")
    {
        $settings = JemHelper::config();
        $check    = JemHelper::isValidDate($date);
        //$timezone = JemHelper::getTimeZoneName();
        $timezone = null;

        if ($check) {
            $jdate = new Date($date, $timezone);
            if (!$format) {
                // If no format set, use long format as standard
                $format = $settings->formatdate;
            }

            return $jdate->format($format);
        } else {
            return false;
        }
    }

    /**
     * Formats time
     *
     * @param string $time
     * @return string $formattime
     */
    static public function formattime($time, $format = "", $addSuffix = true)
    {
        $settings = JemHelper::config();
        $check    = JemHelper::isValidTime($time);

        if (!$check)
        {
            return;
        }

        if(!$format) {
            // If no format set, use settings format as standard
            $format = $settings->formattime;
        }

        $formattedTime = date($format, strtotime($time));

        if ($addSuffix && !empty($settings->timename)) {
            $formattedTime .= ' '.$settings->timename;
        }

        return $formattedTime;
    }

    /**
     * Formats the input dates and times to be used as a from-to string for
     * events. Takes care of unset dates and or times.
     * Values can be styled using css classes jem_date-1 and jem_time-1.
     *
     * @param  mixed  $dateStart Start date of event or an associative array with keys contained in
     *                           {'dateStart','timeStart','dateEnd','timeEnd','dateFormat','timeFormat','addSuffix','showTime'}
     *                           and values corresponding to parameters of the same name.
     * @param  string $timeStart Start time of event
     * @param  string $dateEnd End date of event
     * @param  string $timeEnd End time of event
     * @param  string $dateFormat Date Format
     * @param  string $timeFormat Time Format
     * @param  bool   $addSuffix if true add suffix specified in settings
     * @param  bool   $showTime global setting to respect
     * @param  bool   $showDayLink if true date will be shown as link to day view
     * @return string Formatted date and time string to print
     */
    static public function formatDateTime($dateStart, $timeStart ='', $dateEnd = '', $timeEnd = '', $dateFormat = '', $timeFormat = '', $addSuffix = true, $showTime = true, $showDayLink = false)
    {
        if (is_array($dateStart)) {
            foreach (array('timeStart','dateEnd','timeEnd','dateFormat','timeFormat','addSuffix','showTime', 'showDayLink') as $param) {
                if (isset($dateStart[$param])) {
                    $$param = $dateStart[$param];
                }
            }
            $dateStart = isset($dateStart['dateStart']) ? $dateStart['dateStart'] : '';
        }

        $output = '';

        if (JemHelper::isValidDate($dateStart)) {
            $output .= '<span class="jem_date-1">';
            if ($showDayLink) {
                $output .= '<a href="' . self::escapeLinkAttribute(JemHelperRoute::getRoute(str_replace('-', '', $dateStart), 'day')) . '">';
            }
            $output .= self::formatdate($dateStart, $dateFormat);
            if ($showDayLink) {
                $output .= '</a>';
            }
            $output .= '</span>';

            if ($showTime && JemHelper::isValidTime($timeStart)) {
                $output .= '<span class="jem_time-1">, '.self::formattime($timeStart, $timeFormat, $addSuffix).'</span>';
            }

            // Display end date only when it differs from start date
            $displayDateEnd = JemHelper::isValidDate($dateEnd) && $dateEnd != $dateStart;
            if ($displayDateEnd) {
                $output .= '<span class="jem_date2"> - ';
                if ($showDayLink) {
                    $output .= '<a href="' . self::escapeLinkAttribute(JemHelperRoute::getRoute(str_replace('-', '', $dateEnd), 'day')) . '">';
                }
                $output .= self::formatdate($dateEnd, $dateFormat);
                if ($showDayLink) {
                    $output .= '</a>';
                }
                $output .= '</span>';
            }

            // Display end time only when both times are set
            if ($showTime && JemHelper::isValidTime($timeStart) && JemHelper::isValidTime($timeEnd))
            {
                $output .= $displayDateEnd ? ', ' : ' - ';
                $output .= '<span class="jem_time-2">'.self::formattime($timeEnd, $timeFormat, $addSuffix).'</span>';
            }
        } else {
            $output .= '<span class="jem_date-1">'.Text::_('COM_JEM_OPEN_DATE').'</span>';

            if ($showTime) {
                if (JemHelper::isValidTime($timeStart)) {
                    $output .= '<span class="jem_time-1">, '.self::formattime($timeStart, $timeFormat, $addSuffix).'</span>';

                    // Display end time only when both times are set
                    if (JemHelper::isValidTime($timeEnd)) {
                        $output .= '<span class="jem_time-1"> - '.self::formattime($timeEnd, $timeFormat, $addSuffix).'</span>';
                    }
                }
            }
        }

        return $output;
    }

    /**
     * Formats the input dates and times to be used as a from-to string for
     * events. Takes care of unset dates and or times.
     * First line is for (short) date, second line for time values.
     * Lines can be styled using css classes jem_date-2 and jem_time-2.
     *
     * @param  mixed  $dateStart Start date of event or an associative array with keys contained in
     *                           {'dateStart','timeStart','dateEnd','timeEnd','dateFormat','timeFormat','addSuffix','showTime'}
     *                           and values corresponding to parameters of the same name.
     * @param  string $timeStart Start time of event
     * @param  string $dateEnd End date of event
     * @param  string $timeEnd End time of event
     * @param  string $dateFormat Date Format
     * @param  string $timeFormat Time Format
     * @param  bool   $addSuffix if true add suffix specified in settings
     * @param  bool   $showTime global setting to respect
     * @return string Formatted date and time string to print
     */
    static public function formatDateTime2Lines($dateStart, $timeStart = '', $dateEnd = '', $timeEnd = '', $dateFormat = '', $timeFormat = '', $addSuffix = true, $showTime = true)
    {
        if (is_array($dateStart)) {
            foreach (array('timeStart','dateEnd','timeEnd','dateFormat','timeFormat','addSuffix','showTime') as $param) {
                if (isset($dateStart[$param])) {
                    $$param = $dateStart[$param];
                }
            }
            $dateStart = isset($dateStart['dateStart']) ? $dateStart['dateStart'] : '';
        }

        $output = '';
        $jemconfig = JemHelper::config();

        if (empty($dateFormat)) {
            // Use format saved in settings if specified or format in language file otherwise
            $dateFormat = empty($jemconfig->formatShortDate) ? Text::_('COM_JEM_FORMAT_SHORT_DATE') : $jemconfig->formatShortDate;
        }

        if (JemHelper::isValidDate($dateStart)) {
            $outDate = self::formatdate($dateStart, $dateFormat);

            if (JemHelper::isValidDate($dateEnd) && ($dateEnd != $dateStart)) {
                $outDate .= ' - ' . self::formatdate($dateEnd, $dateFormat);
            }
        } else {
            $outDate = Text::_('COM_JEM_OPEN_DATE');
        }

        if ($showTime && JemHelper::isValidTime($timeStart)) {
            $outTime = self::formattime($timeStart, $timeFormat, $addSuffix);

            if (JemHelper::isValidTime($timeEnd)) {
                $outTime .= ' - ' . self::formattime($timeEnd, $timeFormat, $addSuffix);
            }
        }

        $output = '<span class="jem_date-2">' . $outDate . '</span>';
        if (!empty($outTime)) {
            $output .= '<br class="jem_break-2"><span class="jem_time-2">' . $outTime . '</span>';
        }
        return $output;
    }

    /**
     * Formats the input dates and times to be used as a long from-to string for
     * events. Takes care of unset dates and or times.
     *
     * @param  string $dateStart Start date of event or an associative array with keys contained in
     *                           {'dateStart','timeStart','dateEnd','timeEnd','showTime'}
     *                           and values corresponding to parameters of the same name.
     * @param  mixed  $timeStart Start time of event
     * @param  string $dateEnd End date of event
     * @param  string $timeEnd End time of event
     * @param  bool   $showTime global setting to respect
     * @return string Formatted date and time string to print
     */
    static public function formatLongDateTime($dateStart, $timeStart = '', $dateEnd = '', $timeEnd = '', $showTime = true)
    {
        return self::formatDateTime(is_array($dateStart) ? $dateStart : array('dateStart' => $dateStart, 'timeStart' => $timeStart, 'dateEnd' => $dateEnd, 'timeEnd' => $timeEnd, 'addSuffix' => true, 'showTime' => $showTime));
    }

    /**
     * Formats the input dates and times to be used as a short from-to string for
     * events. Takes care of unset dates and or times.
     *
     * @param  string $dateStart Start date of event or an associative array with keys contained in
     *                           {'dateStart','timeStart','dateEnd','timeEnd','showTime'}
     *                           and values corresponding to parameters of the same name.
     * @param  mixed  $timeStart Start time of event
     * @param  string $dateEnd End date of event
     * @param  string $timeEnd End time of event
     * @param  bool   $showTime global setting to respect
     * @return string Formatted date and time string to print
     */
    static public function formatShortDateTime($dateStart, $timeStart = '', $dateEnd = '', $timeEnd = '', $showTime = true)
    {
        $settings = JemHelper::config();

        $params = is_array($dateStart) ? $dateStart : array('dateStart' => $dateStart, 'timeStart' => $timeStart, 'dateEnd' => $dateEnd, 'timeEnd' => $timeEnd, 'showTime' => $showTime);
        $params['addSuffix'] = true;
        // Use format saved in settings if specified or format in language file otherwise
        $params['dateFormat'] = (isset($settings->formatShortDate) && $settings->formatShortDate) ? $settings->formatShortDate : Text::_('COM_JEM_EVENTS_FORMAT_SHORT_DATE');

        if (isset($settings->datemode) && ($settings->datemode == 2)) {
            return self::formatDateTime2Lines($params);
        } else {
            return self::formatDateTime($params);
        }
    }

    static public function formatSchemaOrgDateTime($dateStart, $timeStart = '', $dateEnd = '', $timeEnd = '', $showTime = true, $event = null)
    {
        if (is_array($dateStart)) {
            foreach (array('timeStart','dateEnd','timeEnd','showTime','event') as $param) {
                if (isset($dateStart[$param])) {
                    $$param = $dateStart[$param];
                }
            }
            $dateStart = isset($dateStart['dateStart']) ? $dateStart['dateStart'] : '';
        }

        $output  = '';
        $formatD = 'Y-m-d';
        $formatT = 'H:i';

        // Schema.org Event startDate/endDate only accept Date or DateTime.
        // An open-date event may retain its visible times in JEM, but those
        // times cannot be emitted as standalone temporal metadata.
        if (!JemHelper::isValidDate($dateStart)) {
            return $output;
        }

        $timeZoneName = JemHelper::getEventTimeZoneName($event ?: (object) array('timezone_mode' => 'joomla'));
        $timeZone = new \DateTimeZone($timeZoneName);

        $content = self::formatdate($dateStart, $formatD);

        if ($showTime && JemHelper::isValidTime($timeStart)) {
            try {
                $content = (new \DateTimeImmutable($dateStart . ' ' . $timeStart, $timeZone))->format('Y-m-d\TH:iP');
            } catch (\Exception $e) {
                $content .= 'T'.self::formattime($timeStart, $formatT, false);
            }
        }
        $output .= '<meta itemprop="startDate" content="'.$content.'" />';

        $effectiveEndDate = JemHelper::isValidDate($dateEnd) ? $dateEnd : '';

        // JEM treats an end time without an explicit end date as ending on
        // the start date. Preserve that meaning in the structured metadata.
        if ($effectiveEndDate === '' && $showTime
            && JemHelper::isValidTime($timeStart) && JemHelper::isValidTime($timeEnd)) {
            $effectiveEndDate = $dateStart;
        }

        if ($effectiveEndDate !== '') {
            $content = self::formatdate($effectiveEndDate, $formatD);

            if ($showTime && JemHelper::isValidTime($timeEnd)) {
                try {
                    $content = (new \DateTimeImmutable($effectiveEndDate . ' ' . $timeEnd, $timeZone))->format('Y-m-d\TH:iP');
                } catch (\Exception $e) {
                    $content .= 'T'.self::formattime($timeEnd, $formatT, false);
                }
            }
            $output .= '<meta itemprop="endDate" content="'.$content.'" />';
        }

        return $output;
    }

    /**
     * Returns an array for ical formatting
     * @todo alter, where is this used for?
     *
     * @param string date
     * @param string time
     * @return array
     */
    static public function getIcalDateArray($date, $time = null)
    {
        if ($time) {
            $sec = strtotime($date. ' ' .$time);
        } else {
            $sec = strtotime($date);
        }
        if (!$sec) {
            return false;
        }

        //Format date
        $parsed = date('Y-m-d H:i:s', $sec);

        $date = array('year'  => (int) substr($parsed, 0, 4),
                      'month' => (int) substr($parsed, 5, 2),
                      'day'   => (int) substr($parsed, 8, 2));

        //Format time
        if (substr($parsed, 11, 8) != '00:00:00')
        {
            $date['hour'] = substr($parsed, 11, 2);
            $date['min']  = substr($parsed, 14, 2);
            $date['sec']  = substr($parsed, 17, 2);
        }
        return $date;
    }

    /**
     * Get a category names list
     * @param unknown $categories Category List
     * @param boolean $doLink Link the categories to the respective Category View
     * @param boolean $backend Used for backend (true) or frontend (false, default)
     * @return string|multitype:
     */
    static public function getCategoryList($categories, $doLink, $backend = false)
    {
        $output = array_map(
            function ($category) use ($doLink, $backend) {
                $hasAccess = !isset($category->user_has_access_category) || (bool) $category->user_has_access_category;
                $lockIcon  = $hasAccess ? '' : ' <span class="icon-lock jem-lockicon" aria-hidden="true"></span>';
                $categoryName = htmlspecialchars((string) ($category->catname ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                if ($doLink && $hasAccess) {
                    if ($backend) {
                        $path = $category->path;
                        $path = str_replace('/', ' &#187; ', $path);
                        $value  = '<span ' . self::tooltip(Text::_('COM_JEM_EDIT_CATEGORY'), $path, 'editlinktip') . '>';
                        $value .= '<a href="index.php?option=com_jem&amp;task=category.edit&amp;id=' . (int) $category->id . '">' .
                                      $categoryName . '</a>';
                        $value .= '</span>';
                    } else {
                        $value  = '<a href="' . self::escapeLinkAttribute(JemHelperRoute::getCategoryRoute($category->catslug)) . '">' .
                                      $categoryName . '</a>';
                    }
                } else {
                    $value = $categoryName;
                }
                return $value . $lockIcon;
            },
            $categories);

        return $output;
    }
}
?>
