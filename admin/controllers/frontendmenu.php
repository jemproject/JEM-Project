<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Table\Table;

class JemControllerFrontendmenu extends BaseController
{
    public function create()
    {
        Session::checkToken('get') or jexit(Text::_('JINVALID_TOKEN'));

        if (!Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_jem')) {
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
        $created     = 0;

        $this->ensureMenuType($menutype);
        $this->ensureMenuModule($menutype);

        $rootId = $this->createMenuItem($menutype, 'JEM', 'jem-frontend', '#', 1, 'heading', 0, array('jem'));

        $groups = array(
            'events'     => $this->createMenuItem($menutype, 'Events', 'events', '#', $rootId, 'heading', 0),
            'calendars'  => $this->createMenuItem($menutype, 'Calendars', 'calendars', '#', $rootId, 'heading', 0),
            'venues'     => $this->createMenuItem($menutype, 'Venues', 'venues', '#', $rootId, 'heading', 0),
            'categories' => $this->createMenuItem($menutype, 'Categories', 'categories', '#', $rootId, 'heading', 0),
            'types'      => $this->createMenuItem($menutype, 'Types', 'types', '#', $rootId, 'heading', 0),
            'user'       => $this->createMenuItem($menutype, 'User Area', 'user-area', '#', $rootId, 'heading', 0),
        );

        $items = array(
            array('Events List', 'events-list', 'index.php?option=com_jem&view=eventslist', $groups['events']),
            array('Events Map', 'events-map', 'index.php?option=com_jem&view=eventsmap', $groups['events']),
            array('Submit Event', 'submit-event', 'index.php?option=com_jem&view=editevent', $groups['events']),
            array('Today', 'today', 'index.php?option=com_jem&view=day&id=0', $groups['calendars']),
            array('Day Timetable', 'day-timetable', 'index.php?option=com_jem&view=day&layout=timetable&id=0', $groups['calendars']),
            array('Day Timeline', 'day-timeline', 'index.php?option=com_jem&view=day&layout=timeline&id=0', $groups['calendars']),
            array('Monthly Calendar', 'monthly-calendar', 'index.php?option=com_jem&view=calendar', $groups['calendars']),
            array('Weekly Calendar', 'weekly-calendar', 'index.php?option=com_jem&view=weekcal', $groups['calendars']),
            array('Venues', 'venues-overview', 'index.php?option=com_jem&view=venues', $groups['venues']),
            array('Venues List', 'venues-list', 'index.php?option=com_jem&view=venueslist', $groups['venues']),
            array('Venues Map', 'venues-map', 'index.php?option=com_jem&view=venuesmap', $groups['venues']),
            array('Submit Venue', 'submit-venue', 'index.php?option=com_jem&view=editvenue', $groups['venues']),
            array('Categories', 'categories-list', 'index.php?option=com_jem&view=categories', $groups['categories']),
            array('Search', 'search', 'index.php?option=com_jem&view=search', $groups['user']),
            array('My Events', 'my-events', 'index.php?option=com_jem&view=myevents', $groups['user']),
            array('My Timeline', 'my-timeline', 'index.php?option=com_jem&view=mytimeline', $groups['user']),
            array('My Venues', 'my-venues', 'index.php?option=com_jem&view=myvenues', $groups['user']),
            array('My Attendances', 'my-attendances', 'index.php?option=com_jem&view=myattendances', $groups['user']),
            array('My Attendances Timeline', 'my-attendances-timeline', 'index.php?option=com_jem&view=myattendances&layout=timeline', $groups['user']),
        );

        $event = $this->getRandomRecord('#__jem_events', 'published = 1', array('id', 'alias'));
        if ($event) {
            $items[] = array('Sample Event', 'sample-event', 'index.php?option=com_jem&view=event&id=' . $this->slug($event), $groups['events']);
        }

        $venue = $this->getRandomRecord('#__jem_venues', 'published = 1', array('id', 'alias'));
        if ($venue) {
            $items[] = array('Sample Venue', 'sample-venue', 'index.php?option=com_jem&view=venue&id=' . $this->slug($venue), $groups['venues']);
            $items[] = array('Venue Calendar', 'venue-calendar', 'index.php?option=com_jem&view=venue&layout=calendar&id=' . $this->slug($venue), $groups['calendars']);
        } else {
            $this->keepExistingGeneratedMenuItems($menutype, array('sample-venue', 'venue-calendar'));
        }

        $category = $this->getRandomCategoryRecord();
        if ($category) {
            $items[] = array('Sample Category', 'sample-category', 'index.php?option=com_jem&view=category&id=' . $this->slug($category), $groups['categories']);
            $items[] = array('Category Calendar', 'category-calendar', 'index.php?option=com_jem&view=category&layout=calendar&id=' . $this->slug($category), $groups['calendars']);
        } else {
            $this->keepExistingGeneratedMenuItems($menutype, array('sample-category', 'category-calendar'));
        }

        $eventType = $this->getRandomRecord('#__jem_types', 'published = 1 AND entity = 1', array('id', 'alias'));
        $items[] = $eventType
            ? array('Events by Type', 'events-by-type', 'index.php?option=com_jem&view=typeevents&id=' . (int) $eventType->id, $groups['types'])
            : array('Events by Type', 'events-by-type', 'index.php?option=com_jem&view=typeevents', $groups['types']);

        $venueType = $this->getRandomRecord('#__jem_types', 'published = 1 AND entity = 3', array('id', 'alias'));
        $items[] = $venueType
            ? array('Venues by Type', 'venues-by-type', 'index.php?option=com_jem&view=typevenues&id=' . (int) $venueType->id, $groups['types'])
            : array('Venues by Type', 'venues-by-type', 'index.php?option=com_jem&view=typevenues', $groups['types']);

        $items[] = array('Categories by Type', 'categories-by-type', 'index.php?option=com_jem&view=categories&id=1&typeid=0', $groups['types']);

        foreach ($items as $item) {
            $itemType        = $item[4] ?? 'component';
            $itemComponentId = $item[5] ?? $componentId;

            if ($this->createMenuItem($menutype, $item[0], $item[1], $item[2], $item[3], $itemType, $itemComponentId)) {
                $created++;
            }
        }

        $this->unpublishGeneratedMenuItems($menutype, array('sample-venue-calendar', 'sample-category-calendar'));

        return $created;
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
            $db->quote('JEM Frontend Menu'),
            $db->quote('Generated menu with the available JEM frontend views.'),
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
            $db->quote('JEM Frontend Menu'),
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

    protected function createMenuItem($menutype, $title, $alias, $link, $parentId, $type, $componentId, array $legacyAliases = array())
    {
        $existing = $this->getExistingMenuItem($menutype, array_merge(array($alias), $legacyAliases), $parentId);

        if ($existing) {
            $this->updateExistingMenuItem($existing, $title, $link, $type, $componentId, $parentId);
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
            'access'       => 1,
            'img'          => '',
            'template_style_id' => 0,
            'params'       => '{}',
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

    protected function updateExistingMenuItem($id, $title, $link, $type, $componentId, $parentId = null)
    {
        $table = Table::getInstance('Menu');

        if (!$table->load((int) $id)) {
            throw new \RuntimeException($table->getError());
        }

        if ($parentId !== null && (int) $table->parent_id !== (int) $parentId) {
            $table->setLocation((int) $parentId, 'last-child');
        }

        $data = array(
            'title'        => $title,
            'link'         => $link,
            'type'         => $type,
            'component_id' => (int) $componentId,
            'published'    => 1,
            'access'       => 1,
            'parent_id'    => $parentId !== null ? (int) $parentId : (int) $table->parent_id,
        );

        if (!$table->bind($data) || !$table->check() || !$table->store()) {
            throw new \RuntimeException($table->getError());
        }
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
