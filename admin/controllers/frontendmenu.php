<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;

class JemControllerFrontendmenu extends BaseController
{
    protected $frontendMenuLanguages;

    public function create()
    {
        JemHelper::requirePostToken();

        if (!JemHelperBackend::canManage('jem.tools.manage')) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        try {
            $created = $this->createFrontendMenu();
            $message = Text::sprintf('COM_JEM_FRONTEND_MENU_CREATED', $created);
            $type    = 'message';
        } catch (\Throwable $e) {
            $message = Text::sprintf('COM_JEM_FRONTEND_MENU_CREATE_FAILED', $e->getMessage());
            $type    = 'error';
        }

        $this->setRedirect('index.php?option=com_jem&view=main', $message, $type);
    }

    protected function createFrontendMenu()
    {
        $db          = Factory::getContainer()->get('DatabaseDriver');
        $menutype    = 'jem-frontend-menu';
        $componentId = $this->getComponentId();
        $specialAccessId = $this->getAccessLevelId('Special', 3);
        $administratorUrl = rtrim(Uri::root(true), '/') . '/administrator/index.php';
        $created     = 0;

        $this->ensureMenuType($menutype);
        $this->ensureMenuModule($menutype);

        $rootId = $this->createMenuItem($menutype, array('JEM'), 'JEM', 'jem-frontend', '#', 1, 'heading', 0, array('jem'));

        $groups = array(
            'events'     => $this->createMenuItem($menutype, array('COM_JEM_FRONTEND_MENU_GROUP_EVENTS', 'COM_JEM_MENU_EVENTS'), 'Events', 'events', '#', $rootId, 'url', 0),
            'calendars'  => $this->createMenuItem($menutype, array('COM_JEM_FRONTEND_MENU_GROUP_CALENDARS', 'COM_JEM_PDF_CALENDARS'), 'Calendars', 'calendars', '#', $rootId, 'url', 0),
            'venues'     => $this->createMenuItem($menutype, array('COM_JEM_FRONTEND_MENU_GROUP_VENUES', 'COM_JEM_MENU_VENUES'), 'Venues', 'venues', '#', $rootId, 'url', 0),
            'categories' => $this->createMenuItem($menutype, array('COM_JEM_FRONTEND_MENU_GROUP_CATEGORIES', 'COM_JEM_MENU_CATEGORIES'), 'Categories', 'categories', '#', $rootId, 'url', 0),
            'types'      => $this->createMenuItem($menutype, array('COM_JEM_FRONTEND_MENU_GROUP_TYPES', 'COM_JEM_MENU_TYPES'), 'Types', 'types', '#', $rootId, 'url', 0),
            'management' => $this->createMenuItem($menutype, array('COM_JEM_FRONTEND_MENU_GROUP_MANAGEMENT'), 'Management', 'management', '#', $rootId, 'url', 0, array(), $specialAccessId),
            'user'       => $this->createMenuItem($menutype, array('COM_JEM_FRONTEND_MENU_GROUP_USER_AREA'), 'User Area', 'user-area', '#', $rootId, 'url', 0),
            'modules'    => $this->createMenuItem($menutype, array('COM_JEM_FRONTEND_MENU_GROUP_MODULES'), 'Modules', 'modules', '#', $rootId, 'url', 0),
        );

        $items = array(
            array(array('COM_JEM_FRONTEND_MENU_EVENTS_LIST', 'COM_JEM_EVENTSLIST_VIEW_DEFAULT_TITLE'), 'Events List', 'events-list', 'index.php?option=com_jem&view=eventslist', $groups['events']),
            array(array('COM_JEM_FRONTEND_MENU_EVENTS_BLOG', 'COM_JEM_EVENTSBLOG_VIEW_DEFAULT_TITLE'), 'Events Blog', 'events-blog', 'index.php?option=com_jem&view=eventsblog', $groups['events']),
            array(array('COM_JEM_FRONTEND_MENU_EVENTS_MAP', 'COM_JEM_EVENTSMAP_VIEW_DEFAULT_TITLE'), 'Events Map', 'events-map', 'index.php?option=com_jem&view=eventsmap', $groups['events']),
            array(array('COM_JEM_FRONTEND_MENU_SUBMIT_EVENT', 'COM_JEM_EDITEVENT_VIEW_DEFAULT_TITLE'), 'Submit Event', 'submit-event', 'index.php?option=com_jem&view=editevent', $groups['events']),
            array(array('COM_JEM_FRONTEND_MENU_TODAY', 'COM_JEM_TIMETABLE_TODAY', 'COM_JEM_DAY_VIEW_DEFAULT_TITLE'), 'Today', 'today', 'index.php?option=com_jem&view=day&id=0', $groups['calendars']),
            array(array('COM_JEM_FRONTEND_MENU_DAY_TIMETABLE', 'COM_JEM_DAY_VIEW_TIMETABLE_TITLE'), 'Day Timetable', 'day-timetable', 'index.php?option=com_jem&view=day&layout=timetable&id=0', $groups['calendars']),
            array(array('COM_JEM_FRONTEND_MENU_DAY_TIMELINE', 'COM_JEM_DAY_VIEW_TIMELINE_TITLE'), 'Day Timeline', 'day-timeline', 'index.php?option=com_jem&view=day&layout=timeline&id=0', $groups['calendars']),
            array(array('COM_JEM_DAY_VIEW_VENUEBOARD_TITLE'), 'Day Venue Board', 'day-venue-board', 'index.php?option=com_jem&view=day&layout=venueboard&id=0', $groups['calendars']),
            array(array('COM_JEM_FRONTEND_MENU_ANNUAL_CALENDAR', 'COM_JEM_ANNUALCALENDAR_VIEW_DEFAULT_TITLE'), 'Annual Calendar', 'annual-calendar', 'index.php?option=com_jem&view=annualcalendar', $groups['calendars']),
            array(array('COM_JEM_FRONTEND_MENU_MONTHLY_CALENDAR', 'COM_JEM_CALENDAR_VIEW_DEFAULT_TITLE'), 'Monthly Calendar', 'monthly-calendar', 'index.php?option=com_jem&view=calendar', $groups['calendars']),
            array(array('COM_JEM_FRONTEND_MENU_WEEKLY_CALENDAR', 'COM_JEM_WEEKCAL_VIEW_DEFAULT_TITLE'), 'Weekly Calendar', 'weekly-calendar', 'index.php?option=com_jem&view=weekcal', $groups['calendars']),
            array(array('COM_JEM_FRONTEND_MENU_VENUES', 'COM_JEM_VENUES_VIEW_DEFAULT_TITLE'), 'Venues', 'venues-overview', 'index.php?option=com_jem&view=venues', $groups['venues']),
            array(array('COM_JEM_FRONTEND_MENU_VENUES_LIST', 'COM_JEM_VENUESLIST_VIEW_DEFAULT_TITLE'), 'Venues List', 'venues-list', 'index.php?option=com_jem&view=venueslist', $groups['venues']),
            array(array('COM_JEM_FRONTEND_MENU_VENUES_MAP', 'COM_JEM_VENUESMAP_VIEW_DEFAULT_TITLE'), 'Venues Map', 'venues-map', 'index.php?option=com_jem&view=venuesmap', $groups['venues']),
            array(array('COM_JEM_FRONTEND_MENU_SUBMIT_VENUE', 'COM_JEM_EDITVENUE_VIEW_DEFAULT_TITLE'), 'Submit Venue', 'submit-venue', 'index.php?option=com_jem&view=editvenue', $groups['venues']),
            array(array('COM_JEM_FRONTEND_MENU_CATEGORIES', 'COM_JEM_CATEGORIES_VIEW_DEFAULT_TITLE'), 'Categories', 'categories-list', 'index.php?option=com_jem&view=categories', $groups['categories']),
            array(array('COM_JEM_FRONTEND_MENU_SUBMIT_CATEGORY', 'COM_JEM_EDITCATEGORY_MENU_TITLE'), 'Submit Category', 'submit-category', 'index.php?option=com_jem&view=editcategory', $groups['categories']),
            array(array('COM_JEM_FRONTEND_MENU_SEARCH', 'COM_JEM_SEARCH_VIEW_DEFAULT_TITLE'), 'Search', 'search', 'index.php?option=com_jem&view=search', $groups['user']),
            array(array('COM_JEM_FRONTEND_MENU_SPECIAL_DAYS', 'COM_JEM_SPECIAL_DAYS_VIEW_DEFAULT_TITLE'), 'Special Days', 'special-days', 'index.php?option=com_jem&view=specialdays', $groups['management']),
            array(array('COM_JEM_FRONTEND_MENU_SUBMIT_SPECIAL_DAY', 'COM_JEM_SPECIALDAY_VIEW_EDIT_TITLE'), 'Submit Special Day', 'submit-special-day', 'index.php?option=com_jem&view=specialday&layout=edit', $groups['management']),
            array(array('COM_JEM_FRONTEND_MENU_ATTENDEE_REGISTRATIONS', 'COM_JEM_ATTENDEE_REGISTRATIONS_VIEW_DEFAULT_TITLE'), 'Attendee Registrations', 'attendee-registrations', 'index.php?option=com_jem&view=attendeeregistrations', $groups['management']),
            array(array('COM_JEM_FRONTEND_MENU_FIELDS', 'COM_JEM_MENU_FIELDS'), 'Fields', 'fields', $administratorUrl . '?option=com_fields&view=fields&context=com_jem.event', $groups['management'], 'url', 0),
            array(array('COM_JEM_FRONTEND_MENU_FIELD_GROUPS', 'COM_JEM_MENU_FIELD_GROUPS'), 'Field Groups', 'field-groups', $administratorUrl . '?option=com_fields&view=groups&context=com_jem.event', $groups['management'], 'url', 0),
            array(array('COM_JEM_FRONTEND_MENU_MY_EVENTS', 'COM_JEM_MYEVENTS_VIEW_DEFAULT_TITLE'), 'My Events', 'my-events', 'index.php?option=com_jem&view=myevents', $groups['user']),
            array(array('COM_JEM_FRONTEND_MENU_MY_TIMELINE', 'COM_JEM_MY_TIMELINE_VIEW_DEFAULT_TITLE'), 'My Timeline', 'my-timeline', 'index.php?option=com_jem&view=mytimeline', $groups['user']),
            array(array('COM_JEM_FRONTEND_MENU_MY_VENUES', 'COM_JEM_MYVENUES_VIEW_DEFAULT_TITLE'), 'My Venues', 'my-venues', 'index.php?option=com_jem&view=myvenues', $groups['user']),
            array(array('COM_JEM_FRONTEND_MENU_MY_ATTENDANCES', 'COM_JEM_MYATTENDANCES_VIEW_DEFAULT_TITLE'), 'My Attendances', 'my-attendances', 'index.php?option=com_jem&view=myattendances', $groups['user']),
            array(array('COM_JEM_FRONTEND_MENU_MY_ATTENDANCES_TIMELINE', 'COM_JEM_MYATTENDANCES_VIEW_TIMELINE_TITLE'), 'My Attendances Timeline', 'my-attendances-timeline', 'index.php?option=com_jem&view=myattendances&layout=timeline', $groups['user']),
        );

        $event = $this->getRandomRecord('#__jem_events', 'published = 1', array('id', 'alias'));
        if ($event) {
            $items[] = array(array('COM_JEM_FRONTEND_MENU_SAMPLE_EVENT', 'COM_JEM_EVENT_VIEW_DEFAULT_TITLE'), 'Sample Event', 'event', 'index.php?option=com_jem&view=event&id=' . $this->slug($event), $groups['events']);
        }

        $venueCalendarItem = null;
        $venue = $this->getRandomRecord('#__jem_venues', 'published = 1', array('id', 'alias'));
        if ($venue) {
            $items[] = array(array('COM_JEM_FRONTEND_MENU_SAMPLE_VENUE', 'COM_JEM_VENUE_VIEW_DEFAULT_TITLE'), 'Sample Venue', 'venue', 'index.php?option=com_jem&view=venue&id=' . $this->slug($venue), $groups['venues']);
            $venueCalendarItem = array(
                array('COM_JEM_FRONTEND_MENU_VENUE_CALENDAR', 'COM_JEM_VENUE_CALENDAR_VIEW_DEFAULT_TITLE'),
                'Venue Calendar',
                'venue-calendar',
                'index.php?option=com_jem&view=venue&layout=calendar&id=' . $this->slug($venue),
                $groups['calendars'],
                'component',
                $componentId,
                array('show_venue_selector' => '1'),
            );
        } else {
            $this->keepExistingGeneratedMenuItems($menutype, array('venue', 'venue-calendar'));
        }

        $category = $this->getRandomCategoryRecord();
        if ($category) {
            $items[] = array(array('COM_JEM_FRONTEND_MENU_SAMPLE_CATEGORY', 'COM_JEM_CATEGORY_VIEW_DEFAULT_TITLE'), 'Sample Category', 'category', 'index.php?option=com_jem&view=category&id=' . $this->slug($category), $groups['categories']);
            $items[] = array(array('COM_JEM_FRONTEND_MENU_CATEGORY_CALENDAR', 'COM_JEM_CATEGORY_CALENDAR_VIEW_DEFAULT_TITLE'), 'Category Calendar', 'category-calendar', 'index.php?option=com_jem&view=category&layout=calendar&id=' . $this->slug($category), $groups['calendars']);
        } else {
            $this->keepExistingGeneratedMenuItems($menutype, array('category', 'sample-category-calendar', 'category-calendar'));
        }

        // Keep the Venue view (with Venue Calendar) last in the Calendars group.
        if ($venueCalendarItem) {
            $items[] = $venueCalendarItem;
        }

        $eventType = $this->getRandomRecord('#__jem_types', 'published = 1 AND entity = 1', array('id', 'alias'));
        $items[] = $eventType
            ? array(array('COM_JEM_FRONTEND_MENU_EVENTS_BY_TYPE', 'COM_JEM_TYPEEVENTS_VIEW_DEFAULT_TITLE'), 'Events by Type', 'events-by-type', 'index.php?option=com_jem&view=typeevents&id=' . (int) $eventType->id, $groups['types'])
            : array(array('COM_JEM_FRONTEND_MENU_EVENTS_BY_TYPE', 'COM_JEM_TYPEEVENTS_VIEW_DEFAULT_TITLE'), 'Events by Type', 'events-by-type', 'index.php?option=com_jem&view=typeevents', $groups['types']);

        $venueType = $this->getRandomRecord('#__jem_types', 'published = 1 AND entity = 3', array('id', 'alias'));
        $items[] = $venueType
            ? array(array('COM_JEM_FRONTEND_MENU_VENUES_BY_TYPE', 'COM_JEM_TYPEVENUES_VIEW_DEFAULT_TITLE'), 'Venues by Type', 'venues-by-type', 'index.php?option=com_jem&view=typevenues&id=' . (int) $venueType->id, $groups['types'])
            : array(array('COM_JEM_FRONTEND_MENU_VENUES_BY_TYPE', 'COM_JEM_TYPEVENUES_VIEW_DEFAULT_TITLE'), 'Venues by Type', 'venues-by-type', 'index.php?option=com_jem&view=typevenues', $groups['types']);

        $items[] = array(array('COM_JEM_FRONTEND_MENU_CATEGORIES_BY_TYPE'), 'Categories by Type', 'categories-by-type', 'index.php?option=com_jem&view=categories&id=1&typeid=0', $groups['types']);

        $viewLayouts = $this->getGeneratedViewLayouts($items, $groups);
        $viewHeadings = array();
        $singleLayoutHeadings = array();
        foreach ($items as $item) {
            $itemType        = $item[5] ?? 'component';
            $itemComponentId = $item[6] ?? $componentId;
            $itemParams      = array_merge($this->getMenuItemDefaultParams($item[3]), $item[7] ?? array());
            $itemAccess      = ((int) $item[4] === (int) $groups['management']) ? $specialAccessId : 1;
            $parentId        = $item[4];

            if ($itemType === 'component') {
                $parts = array();
                parse_str((string) parse_url($item[3], PHP_URL_QUERY), $parts);
                $view = (string) ($parts['view'] ?? '');
                $group = array_search((int) $item[4], $groups, true);

                if (($parts['option'] ?? '') === 'com_jem' && preg_match('/^[a-z][a-z0-9]*$/', $view) && $group !== false) {
                    $key = $group . ':' . $view;
                    if (count($viewLayouts[$key]) > 1) {
                        if (!isset($viewHeadings[$key])) {
                            $viewTitle = $view === 'day' ? 'Day' : $item[1];
                            $viewHeadings[$key] = $this->createMenuItem(
                                $menutype,
                                array('COM_JEM_' . strtoupper($view) . '_VIEW_DEFAULT_TITLE'),
                                $viewTitle,
                                'view-' . $group . '-' . $view,
                                '#',
                                $item[4],
                                'heading',
                                0,
                                array(),
                                $itemAccess
                            );
                        }
                        $parentId = $viewHeadings[$key];
                    } else {
                        $singleLayoutHeadings[$key] = array($item[4], 'view-' . $group . '-' . $view);
                    }
                }
            }

            $menuItemId = $this->createMenuItem($menutype, $item[0], $item[1], $item[2], $item[3], $parentId, $itemType, $itemComponentId, array(), $itemAccess, $itemParams);

            if ($menuItemId) {
                $created++;

                if ($item[2] === 'venue-calendar') {
                    $this->moveMenuItemToLastChild($parentId === $groups['calendars'] ? $menuItemId : $parentId, $groups['calendars']);
                }
            }
        }

        foreach ($singleLayoutHeadings as [$groupId, $alias]) {
            $this->unpublishEmptyGeneratedViewHeading($menutype, $groupId, $alias);
        }

        $this->unpublishGeneratedMenuItems($menutype, array('sample-venue-calendar', 'sample-category-calendar'));

        $created += $this->createModuleLayoutMenu($menutype, $groups['modules'], $componentId);

        return $created;
    }

    /** Count distinct layouts per generated view and menu group, not menu instances. */
    protected function getGeneratedViewLayouts(array $items, array $groups): array
    {
        $viewLayouts = array();
        foreach ($items as $item) {
            if (($item[5] ?? 'component') !== 'component') {
                continue;
            }

            $parts = array();
            parse_str((string) parse_url($item[3], PHP_URL_QUERY), $parts);
            $view = (string) ($parts['view'] ?? '');
            $group = array_search((int) $item[4], $groups, true);

            if (($parts['option'] ?? '') === 'com_jem' && preg_match('/^[a-z][a-z0-9]*$/', $view) && $group !== false) {
                $viewLayouts[$group . ':' . $view][(string) ($parts['layout'] ?? 'default')] = true;
            }
        }

        return $viewLayouts;
    }

    /** Create one isolated, published module instance and menu page per available layout. */
    protected function createModuleLayoutMenu(string $menutype, int $groupId, int $componentId): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $modules = array(
            'mod_jem', 'mod_jem_banner', 'mod_jem_cal', 'mod_jem_jubilee',
            'mod_jem_map', 'mod_jem_teaser', 'mod_jem_types', 'mod_jem_wide',
        );
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('element', 'name')))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('module'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('element') . ' IN (' . implode(',', array_map(array($db, 'quote'), $modules)) . ')');
        $db->setQuery($query);
        $installed = $db->loadObjectList('element') ?: array();
        $created = 0;

        foreach ($modules as $module) {
            if (!isset($installed[$module])) {
                continue;
            }

            $layouts = $this->getModuleLayouts($module);
            if (!$layouts) {
                continue;
            }

            $language = Factory::getApplication()->getLanguage();
            $language->load($module . '.sys', JPATH_SITE)
                || $language->load($module . '.sys', JPATH_SITE . '/modules/' . $module);
            $titleKey = strtoupper($module);
            $title = $language->hasKey($titleKey)
                ? Text::_($titleKey)
                : (trim((string) $installed[$module]->name) ?: $module);
            $moduleAlias = str_replace('_', '-', $module);
            $moduleParentId = $this->createMenuItem(
                $menutype, array(), $title, 'module-' . $moduleAlias, '#', $groupId, 'heading', 0
            );

            foreach ($layouts as $layout) {
                $moduleId = $this->ensureGeneratedModuleInstance($module, $layout, $title);
                $layoutTitle = ucfirst(str_replace(array('_', '-', ':'), ' ', $layout));
                $alias = 'layout-' . $moduleAlias . '-' . strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '-', $layout));
                $link = 'index.php?option=com_jem&view=modulepreview&layout=default&module_id=' . $moduleId;
                $menuItemId = $this->createMenuItem(
                    $menutype, array(), $layoutTitle, $alias, $link, $moduleParentId,
                    'component', $componentId, array(), 1, array('module_id' => $moduleId)
                );
                $this->assignGeneratedModuleToMenu($moduleId, $menuItemId);
                $created++;
            }
        }

        return $created;
    }

    /** Match Joomla's modulelayout field: top-level PHP files without underscores. */
    protected function getModuleLayouts(string $module): array
    {
        $path = JPATH_SITE . '/modules/' . $module . '/tmpl';
        $layouts = array();
        foreach (glob($path . '/*.php') ?: array() as $file) {
            $layout = basename($file, '.php');
            if (preg_match('/^[a-zA-Z0-9-]+$/', $layout)) {
                $layouts[] = $layout;
            }
        }
        sort($layouts);

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('element'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('template'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('enabled') . ' = 1');
        $db->setQuery($query);
        foreach ($db->loadColumn() ?: array() as $template) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', (string) $template)) {
                continue;
            }
            $overridePath = JPATH_SITE . '/templates/' . $template . '/html/' . $module;
            foreach (glob($overridePath . '/*.php') ?: array() as $file) {
                $layout = basename($file, '.php');
                if (preg_match('/^[a-zA-Z0-9-]+$/', $layout) && !in_array($layout, $layouts, true)) {
                    $layouts[] = $template . ':' . $layout;
                }
            }
        }

        return $layouts;
    }

    /** Never reuse or alter ordinary administrator-created module instances. */
    protected function ensureGeneratedModuleInstance(string $module, string $layout, string $moduleTitle): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $note = 'JEM generated menu: ' . $module . ':' . $layout;
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote($module))
            ->where($db->quoteName('note') . ' = ' . $db->quote($note))
            ->where($db->quoteName('client_id') . ' = 0');
        $db->setQuery($query, 0, 1);
        $existingId = (int) $db->loadResult();

        if ($existingId) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__modules'))
                ->set($db->quoteName('published') . ' = 1')
                ->set($db->quoteName('position') . ' = ' . $db->quote('jem-menu-preview'))
                ->where($db->quoteName('id') . ' = ' . $existingId);
            $db->setQuery($query);
            $db->execute();

            return $existingId;
        }

        $params = $this->getModuleDefaultParams($module);
        $params['layout'] = str_contains($layout, ':') ? $layout : '_:' . $layout;
        $columns = array(
            'title', 'note', 'content', 'ordering', 'position', 'checked_out', 'checked_out_time',
            'publish_up', 'publish_down', 'published', 'module', 'access', 'showtitle', 'params',
            'client_id', 'language',
        );
        $values = array(
            $db->quote($moduleTitle . ' - ' . ucfirst(str_replace('-', ' ', $layout))),
            $db->quote($note),
            $db->quote(''),
            0,
            $db->quote('jem-menu-preview'),
            0,
            $db->quote('0000-00-00 00:00:00'),
            'NULL',
            'NULL',
            1,
            $db->quote($module),
            1,
            0,
            $db->quote(json_encode($params)),
            0,
            $db->quote('*'),
        );
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__modules'))
            ->columns($db->quoteName($columns))
            ->values(implode(',', $values));
        $db->setQuery($query);
        $db->execute();

        return (int) $db->insertid();
    }

    protected function getModuleDefaultParams(string $module): array
    {
        $path = JPATH_SITE . '/modules/' . $module . '/' . $module . '.xml';
        $xml = is_file($path) ? @simplexml_load_file($path) : false;
        $defaults = array();
        if (!$xml) {
            return $defaults;
        }

        foreach ($xml->xpath('./config/fields[@name="params"]//field[@name and @default]') ?: array() as $field) {
            $defaults[(string) $field['name']] = (string) $field['default'];
        }

        return $defaults;
    }

    protected function assignGeneratedModuleToMenu(int $moduleId, int $menuItemId): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__modules_menu'))
            ->where($db->quoteName('moduleid') . ' = ' . $moduleId);
        $db->setQuery($query);
        $db->execute();

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__modules_menu'))
            ->columns($db->quoteName(array('moduleid', 'menuid')))
            ->values($moduleId . ',' . $menuItemId);
        $db->setQuery($query);
        $db->execute();
    }

    protected function ensureMenuType($menutype)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__menu_types'))
            ->where($db->quoteName('menutype') . ' = ' . $db->quote($menutype));
        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__modules'))
                ->set($db->quoteName('published') . ' = 1')
                ->set($db->quoteName('access') . ' = 1')
                ->where($db->quoteName('module') . ' = ' . $db->quote('mod_menu'))
                ->where($db->quoteName('client_id') . ' = 0')
                ->where($db->quoteName('params') . ' LIKE ' . $db->quote('%"menutype":"' . $menutype . '"%'));
            $db->setQuery($query);
            $db->execute();

            return;
        }

        $columns = array('menutype', 'title', 'description', 'client_id');
        $values  = array(
            $db->quote($menutype),
            $db->quote($this->translateFrontendMenuTitle(array('COM_JEM_FRONTEND_MENU_TITLE'), 'JEM Frontend Menu')),
            $db->quote($this->translateFrontendMenuTitle(array('COM_JEM_FRONTEND_MENU_DESCRIPTION'), 'Generated menu with the available JEM frontend views.')),
            0,
        );

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__menu_types'))
            ->columns($db->quoteName($columns))
            ->values(implode(',', $values));
        $db->setQuery($query);
        $db->execute();
    }

    protected function ensureMenuModule($menutype)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_menu'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('params') . ' LIKE ' . $db->quote('%"menutype":"' . $menutype . '"%'));
        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            return;
        }

        $params = json_encode(array(
            'menutype'        => $menutype,
            'startLevel'      => 1,
            'endLevel'        => 0,
            'showAllChildren' => 1,
            'tag_id'          => '',
            'class_sfx'       => '',
            'layout'          => '_:default',
            'moduleclass_sfx' => '',
            'cache'           => 1,
            'cache_time'      => 900,
            'cachemode'       => 'itemid',
        ));

        $orderingQuery = $db->getQuery(true)
            ->select('COALESCE(MAX(' . $db->quoteName('ordering') . '), 0) + 1')
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('position') . ' = ' . $db->quote('menu'))
            ->where($db->quoteName('client_id') . ' = 0');
        $db->setQuery($orderingQuery);
        $ordering = (int) $db->loadResult();

        $columns = array(
            'title', 'note', 'content', 'ordering', 'position', 'checked_out', 'checked_out_time',
            'publish_up', 'publish_down', 'published', 'module', 'access', 'showtitle', 'params',
            'client_id', 'language'
        );

        $values = array(
            $db->quote($this->translateFrontendMenuTitle(array('COM_JEM_FRONTEND_MENU_TITLE'), 'JEM Frontend Menu')),
            $db->quote(''),
            $db->quote(''),
            $ordering,
            $db->quote('menu'),
            0,
            $db->quote('0000-00-00 00:00:00'),
            $db->quote('0000-00-00 00:00:00'),
            $db->quote('0000-00-00 00:00:00'),
            1,
            $db->quote('mod_menu'),
            1,
            1,
            $db->quote($params),
            0,
            $db->quote('*'),
        );

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__modules'))
            ->columns($db->quoteName($columns))
            ->values(implode(',', $values));
        $db->setQuery($query);
        $db->execute();

        $moduleId = (int) $db->insertid();

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__modules_menu'))
            ->columns($db->quoteName(array('moduleid', 'menuid')))
            ->values($moduleId . ',0');
        $db->setQuery($query);
        $db->execute();
    }

    protected function createMenuItem($menutype, array $titleKeys, $defaultTitle, $alias, $link, $parentId, $type, $componentId, array $legacyAliases = array(), $access = 1, array $params = array())
    {
        $title = $this->translateFrontendMenuTitle($titleKeys, $defaultTitle);
        $existing = $this->getExistingMenuItem($menutype, array_merge(array($alias), $legacyAliases), $parentId);

        if ($existing) {
            $this->updateExistingMenuItem($existing, $title, $defaultTitle, $link, $type, $componentId, $parentId, $access, $params);
            return (int) $existing;
        }

        $table = Table::getInstance('Menu');
        $table->setLocation((int) $parentId, 'last-child');

        $data = array(
            'menutype'     => $menutype,
            'title'        => $title,
            'alias'        => $alias,
            'note'         => '',
            'path'         => '',
            'link'         => $link,
            'type'         => $type,
            'published'    => 1,
            'parent_id'    => (int) $parentId,
            'level'        => 0,
            'component_id' => (int) $componentId,
            'checked_out'  => 0,
            'browserNav'   => 0,
            'access'       => max(1, (int) $access),
            'img'          => '',
            'template_style_id' => 0,
            'params'       => $params ? json_encode($params) : '{}',
            'home'         => 0,
            'language'     => '*',
            'client_id'    => 0,
        );

        if (!$table->bind($data) || !$table->check() || !$table->store()) {
            throw new \RuntimeException($table->getError());
        }

        return (int) $table->id;
    }

    protected function getExistingMenuItem($menutype, array $aliases, $parentId)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $aliases = array_values(array_filter(array_unique($aliases), static fn ($alias) => trim((string) $alias) !== ''));

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('menutype') . ' = ' . $db->quote($menutype))
            ->where($db->quoteName('alias') . ' IN (' . implode(',', array_map(array($db, 'quote'), $aliases)) . ')')
            ->where($db->quoteName('parent_id') . ' = ' . (int) $parentId)
            ->where($db->quoteName('client_id') . ' = 0');

        $db->setQuery($query);

        $existing = (int) $db->loadResult();

        if ($existing) {
            return $existing;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('menutype') . ' = ' . $db->quote($menutype))
            ->where($db->quoteName('alias') . ' IN (' . implode(',', array_map(array($db, 'quote'), $aliases)) . ')')
            ->where($db->quoteName('client_id') . ' = 0');
        $db->setQuery($query, 0, 1);

        return (int) $db->loadResult();
    }

    protected function updateExistingMenuItem($id, $title, $defaultTitle, $link, $type, $componentId, $parentId = null, $access = 1, array $defaultParams = array())
    {
        $table = Table::getInstance('Menu');

        if (!$table->load((int) $id)) {
            throw new \RuntimeException($table->getError());
        }

        if ($parentId !== null && (int) $table->parent_id !== (int) $parentId) {
            $table->setLocation((int) $parentId, 'last-child');
        }

        $data = array(
            'link'         => $link,
            'type'         => $type,
            'component_id' => (int) $componentId,
            'published'    => 1,
            'access'       => max(1, (int) $access),
            'parent_id'    => $parentId !== null ? (int) $parentId : (int) $table->parent_id,
        );

        if (trim((string) $table->title) === '' || (string) $table->title === (string) $defaultTitle) {
            $data['title'] = $title;
        }

        if ($defaultParams) {
            $data['params'] = $this->mergeMenuParamDefaults((string) $table->params, $defaultParams);
        }

        if (!$table->bind($data) || !$table->check() || !$table->store()) {
            throw new \RuntimeException($table->getError());
        }
    }

    protected function translateFrontendMenuTitle(array $keys, $fallback)
    {
        $languages = $this->getFrontendMenuLanguages();

        foreach ($languages as $language) {
            foreach ($keys as $key) {
                if ($language->hasKey($key)) {
                    return $language->_($key);
                }
            }
        }

        return (string) $fallback;
    }

    protected function getFrontendMenuLanguages()
    {
        if (is_array($this->frontendMenuLanguages)) {
            return $this->frontendMenuLanguages;
        }

        $factory = Factory::getContainer()->get(LanguageFactoryInterface::class);
        $english = $factory->createLanguage('en-GB');
        $english->load('com_jem.sys', JPATH_ADMINISTRATOR, 'en-GB', true, false);
        $english->load('com_jem', JPATH_ADMINISTRATOR, 'en-GB', true, false);

        $siteTag = trim((string) ComponentHelper::getParams('com_languages')->get('site', 'en-GB'));
        if (!preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', $siteTag)) {
            $siteTag = 'en-GB';
        }

        $languages = array();
        if ($siteTag !== 'en-GB') {
            $siteLanguage = $factory->createLanguage($siteTag);
            $hasSystemPack = $siteLanguage->load('com_jem.sys', JPATH_ADMINISTRATOR, $siteTag, true, false);
            $hasComponentPack = $siteLanguage->load('com_jem', JPATH_ADMINISTRATOR, $siteTag, true, false);

            if ($hasSystemPack || $hasComponentPack) {
                $languages[] = $siteLanguage;
            }
        }

        $languages[] = $english;
        $this->frontendMenuLanguages = $languages;

        return $this->frontendMenuLanguages;
    }

    protected function moveMenuItemToLastChild($id, $parentId)
    {
        $table = Table::getInstance('Menu');

        if (!$table->load((int) $id)) {
            throw new \RuntimeException($table->getError());
        }

        $table->setLocation((int) $parentId, 'last-child');

        if (!$table->store()) {
            throw new \RuntimeException($table->getError());
        }
    }

    protected function mergeMenuParamDefaults(string $currentParams, array $defaultParams): string
    {
        $params = json_decode($currentParams, true);

        if (!is_array($params)) {
            $params = array();
        }

        foreach ($defaultParams as $key => $value) {
            if (!array_key_exists($key, $params)) {
                $params[$key] = $value;
            }
        }

        return json_encode($params);
    }

    protected function getMenuItemDefaultParams(string $link): array
    {
        $query = parse_url($link, PHP_URL_QUERY);

        if (!is_string($query) || $query === '') {
            return array();
        }

        parse_str($query, $parts);

        if (($parts['option'] ?? '') !== 'com_jem' || empty($parts['view'])) {
            return array();
        }

        $view = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $parts['view']);
        $layout = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($parts['layout'] ?? 'default'));
        $xmlPath = JPATH_SITE . '/components/com_jem/views/' . $view . '/tmpl/' . $layout . '.xml';

        if (!is_file($xmlPath)) {
            return array();
        }

        $xml = @simplexml_load_file($xmlPath);

        if (!$xml) {
            return array();
        }

        $defaults = array();
        foreach ($xml->xpath('./fields[@name="params"]//field[@name and @default]') ?: array() as $field) {
            $name = trim((string) $field['name']);

            if ($name !== '') {
                $defaults[$name] = (string) $field['default'];
            }
        }

        return $defaults;
    }

    /** Hide a former one-layout view heading only after its generated leaf has moved out. */
    protected function unpublishEmptyGeneratedViewHeading(string $menutype, int $groupId, string $alias): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('menutype') . ' = ' . $db->quote($menutype))
            ->where($db->quoteName('parent_id') . ' = ' . $groupId)
            ->where($db->quoteName('alias') . ' = ' . $db->quote($alias))
            ->where($db->quoteName('type') . ' = ' . $db->quote('heading'))
            ->where($db->quoteName('link') . ' = ' . $db->quote('#'))
            ->where($db->quoteName('client_id') . ' = 0');
        $db->setQuery($query, 0, 1);
        $headingId = (int) $db->loadResult();

        if (!$headingId) {
            return;
        }

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('parent_id') . ' = ' . $headingId)
            ->where($db->quoteName('published') . ' = 1');
        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            return;
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__menu'))
            ->set($db->quoteName('published') . ' = 0')
            ->where($db->quoteName('id') . ' = ' . $headingId);
        $db->setQuery($query);
        $db->execute();
    }

    protected function unpublishGeneratedMenuItems($menutype, array $aliases)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $aliases = array_values(array_filter(array_unique($aliases), static fn ($alias) => trim((string) $alias) !== ''));

        if ($aliases === array()) {
            return;
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__menu'))
            ->set($db->quoteName('published') . ' = 0')
            ->where($db->quoteName('menutype') . ' = ' . $db->quote($menutype))
            ->where($db->quoteName('alias') . ' IN (' . implode(',', array_map(array($db, 'quote'), $aliases)) . ')')
            ->where($db->quoteName('client_id') . ' = 0');
        $db->setQuery($query);
        $db->execute();
    }

    protected function keepExistingGeneratedMenuItems($menutype, array $aliases)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $aliases = array_values(array_filter(array_unique($aliases), static fn ($alias) => trim((string) $alias) !== ''));

        if ($aliases === array()) {
            return;
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__menu'))
            ->set($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('menutype') . ' = ' . $db->quote($menutype))
            ->where($db->quoteName('alias') . ' IN (' . implode(',', array_map(array($db, 'quote'), $aliases)) . ')')
            ->where($db->quoteName('client_id') . ' = 0');
        $db->setQuery($query);
        $db->execute();
    }

    protected function getComponentId()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_jem'));
        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    protected function getAccessLevelId($title, $fallback)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__viewlevels'))
            ->where($db->quoteName('title') . ' = ' . $db->quote($title));
        $db->setQuery($query, 0, 1);

        return (int) $db->loadResult() ?: (int) $fallback;
    }

    protected function getRandomRecord($table, $where, array $columns)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName($columns))
            ->from($db->quoteName($table))
            ->where($where)
            ->order('RAND()');
        $db->setQuery($query, 0, 1);

        return $db->loadObject();
    }

    protected function getRandomCategoryRecord()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName(array('id', 'alias')))
            ->from($db->quoteName('#__jem_categories'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('id') . ' > 1')
            ->where($db->quoteName('alias') . ' <> ' . $db->quote('root'))
            ->where($db->quoteName('catname') . ' <> ' . $db->quote('root'))
            ->order('RAND()');
        $db->setQuery($query, 0, 1);

        return $db->loadObject();
    }

    protected function slug($row)
    {
        $alias = trim((string) ($row->alias ?? ''));

        return (int) $row->id . ($alias !== '' ? ':' . $alias : '');
    }
}
