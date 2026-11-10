<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

require_once dirname(__DIR__) . '/classes/registrationstatus.class.php';

/** Shared read model: no reservation, payment or admission writes. */
class JemModelRegistrationstatus extends BaseDatabaseModel
{
    /**
     * Load a scoped, filtered, group-paginated snapshot for the current manager.
     * @return array Report data and display configuration for either client.
     */
    public function getReport(): array
    {
        $app = Factory::getApplication();
        $user = JemFactory::getUser();
        $backend = $app->isClient('administrator');
        if (!(int) $user->get('id') || ($backend && !JemHelperBackend::canManage('jem.attendees.manage'))) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $options = $backend ? [] : $app->getParams()->toArray();
        $request = [];
        foreach (['date', 'mode', 'step', 'jump', 'category', 'venue', 'country', 'region', 'county', 'city', 'publication', 'event_status', 'search'] as $name) {
            if ($app->input->exists('rs_' . $name)) {
                $request[$name] = $app->input->getString('rs_' . $name, '');
            }
        }
        $now = new DateTimeImmutable('now', new DateTimeZone($app->get('offset', 'UTC')));
        $filters = JemRegistrationStatus::filters($options, $request, $now, (int) JemHelper::config()->weekdaystart);
        $events = $this->metadata();
        foreach ($events as $id => $event) {
            if (!$backend && !$user->can('edit', 'event', $id, (int) $event->created_by, $event->categories)) {
                unset($events[$id]);
            }
        }
        $choices = $this->choices($events, $filters);
        $groups = JemRegistrationStatus::groups($events, $filters);
        $pageSize = (int) ($options['status_page_size'] ?? 20);
        $pageSize = in_array($pageSize, [10, 20, 50, 100], true) ? $pageSize : 20;
        $pages = max(1, (int) ceil(count($groups) / $pageSize));
        $page = min($pages, max(1, $app->input->getInt('rs_page', 1)));
        $selected = array_slice($groups, ($page - 1) * $pageSize, $pageSize, true);
        $ids = [];
        foreach ($selected as $members) {
            $ids = array_merge($ids, array_keys($members));
        }
        $ids = array_values(array_unique($ids));
        $totals = $ids ? $this->eventTotals($events, $ids) : [];
        $rows = [];
        foreach ($selected as $root => $members) {
            $visited = [];
            $this->appendGroup($rows, (int) $root, $members, $events, $totals, '', 0, $visited);
        }
        $historyId = max(0, $app->input->getInt('rs_history', 0));
        $canHistory = $user->authorise('core.admin', 'com_jem') || $user->authorise('jem.registrations.history', 'com_jem');
        if ($historyId > 0 && (!$canHistory || !isset($events[$historyId]))) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $historyPage = max(1, $app->input->getInt('rs_history_page', 1));
        return [
            'rows' => $rows, 'filters' => $filters, 'choices' => $choices, 'options' => $options,
            'page' => $page, 'pages' => $pages, 'groups' => count($groups), 'backend' => $backend,
            'updated' => $now->format('Y-m-d H:i:s T'), 'canHistory' => $canHistory,
            'historyId' => $historyId, 'historyTitle' => $events[$historyId]->title ?? '',
            'historyPage' => $historyPage, 'history' => $historyId ? $this->history($historyId, $historyPage) : [],
        ];
    }

    /** @return array<int,object> Event metadata and category memberships, before ACL filtering. */
    private function metadata(): array
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)->select([
            'e.id', 'e.title', 'e.created_by', 'e.parent_event_id', 'e.dates', 'e.enddates', 'e.times',
            'e.published', 'e.event_status', 'e.locid', 'e.maxplaces', 'e.reservedplaces', 'e.registra',
            'e.capacity_mode', 'e.pricing_mode', 'v.venue', 'v.country', 'v.region', 'v.state', 'v.city',
        ])->from($db->quoteName('#__jem_events', 'e'))
            ->join('LEFT', $db->quoteName('#__jem_venues', 'v') . ' ON v.id = e.locid')
            ->order('e.dates ASC, e.times ASC, e.id ASC');
        $db->setQuery($query);
        $events = (array) $db->loadObjectList('id');
        $db->setQuery($db->getQuery(true)->select(['itemid', 'catid'])->from($db->quoteName('#__jem_cats_event_relations')));
        foreach ($events as $event) {
            $event->categories = [];
        }
        foreach ((array) $db->loadObjectList() as $relation) {
            if (isset($events[$relation->itemid])) {
                $events[$relation->itemid]->categories[] = (int) $relation->catid;
            }
        }
        return $events;
    }

    /**
     * Build cascading selector values only from permitted event metadata.
     * @param array $events Authorised events.
     * @param array $filters Effective filters.
     * @return array Labels by filter and value.
     */
    private function choices(array $events, array $filters): array
    {
        $choices = array_fill_keys(['category', 'venue', 'country', 'region', 'county', 'city'], []);
        $categoryIds = [];
        foreach ($events as $event) {
            foreach ($event->categories as $id) {
                $categoryIds[$id] = $id;
            }
            if ((int) $event->locid > 0) {
                $choices['venue'][(int) $event->locid] = (string) $event->venue;
            }
            foreach (JemRegistrationStatus::LOCATIONS as $name => $property) {
                $value = trim((string) ($event->$property ?? ''));
                if ($value !== '') {
                    $choices[$name][$value] = $value;
                }
                if ($filters[$name] !== '' && $filters[$name] !== $value) {
                    break;
                }
            }
        }
        if ($categoryIds) {
            $db = $this->getDatabase();
            $db->setQuery($db->getQuery(true)->select(['id', 'catname'])->from($db->quoteName('#__jem_categories'))
                ->where('id IN (' . implode(',', $categoryIds) . ')'));
            foreach ((array) $db->loadObjectList() as $category) {
                $choices['category'][(int) $category->id] = $category->catname;
            }
        }
        foreach ($choices as &$values) {
            natcasesort($values);
        }
        unset($values);
        return $choices;
    }

    /**
     * Fetch aggregates in batches, joining details only to their current revision.
     * @param array $events Authorised metadata.
     * @param int[] $ids IDs on the displayed group page.
     * @return array<int,array> Per-event current totals.
     */
    private function eventTotals(array $events, array $ids): array
    {
        $db = $this->getDatabase();
        $list = implode(',', array_map('intval', $ids));
        $query = $db->getQuery(true)->select([
            'event', 'SUM(CASE WHEN status = 1 AND waiting = 0 THEN places ELSE 0 END) AS confirmed',
            'SUM(CASE WHEN status = 1 AND waiting = 1 THEN places ELSE 0 END) AS waiting',
            'SUM(CASE WHEN status = 0 THEN places ELSE 0 END) AS invited',
        ])->from($db->quoteName('#__jem_register'))->where('event IN (' . $list . ')')->group('event');
        $db->setQuery($query);
        $counts = (array) $db->loadObjectList('event');
        $query = $db->getQuery(true)->select([
            'event_id', "SUM(CASE WHEN old_status IN (1,2) AND action = 'cancelled' AND new_status = -1 THEN GREATEST(old_places,0) "
                . "WHEN old_status IN (1,2) AND new_status = old_status AND action IN ('places_changed','order_modified') "
                . 'THEN GREATEST(old_places-new_places,0) ELSE 0 END) AS cancelled',
        ])->from($db->quoteName('#__jem_register_history'))->where('event_id IN (' . $list . ')')->group('event_id');
        $db->setQuery($query);
        $cancelled = (array) $db->loadObjectList('event_id');
        $db->setQuery($db->getQuery(true)->select(['id', 'venue_snapshot'])->from($db->quoteName('#__jem_events'))
            ->where('id IN (' . $list . ')'));
        $snapshots = (array) $db->loadObjectList('id');
        $query = $db->getQuery(true)->select([
            'a.event_id', 'a.venue_layout_id', 'a.venue_capacity_area_id',
            'SUM(CASE WHEN r.status = 1 AND r.waiting = 0 THEN a.quantity ELSE 0 END) AS confirmed',
            'SUM(CASE WHEN r.status = 1 AND r.waiting = 1 THEN a.quantity ELSE 0 END) AS waiting',
        ])->from($db->quoteName('#__jem_register_capacity_allocations', 'a'))
            ->join('INNER', $db->quoteName('#__jem_register', 'r') . ' ON r.id = a.register_id AND r.revision = a.registration_revision AND r.event = a.event_id')
            ->where('a.event_id IN (' . $list . ')')->group(['a.event_id', 'a.venue_layout_id', 'a.venue_capacity_area_id']);
        $db->setQuery($query);
        $allocations = [];
        foreach ((array) $db->loadObjectList() as $row) {
            $key = $row->venue_capacity_area_id ? 'area:' . $row->venue_capacity_area_id : 'layout:' . $row->venue_layout_id;
            $allocations[(int) $row->event_id][$key] = $row;
        }
        $query = $db->getQuery(true)->select([
            'p.id', 'p.event_id', 'p.name', 'p.capacity',
            'SUM(CASE WHEN r.status = 1 AND r.waiting = 0 THEN i.quantity ELSE 0 END) AS confirmed',
            'SUM(CASE WHEN r.status = 1 AND r.waiting = 1 THEN i.quantity ELSE 0 END) AS waiting',
        ])->from($db->quoteName('#__jem_capacity_pools', 'p'))
            ->join('LEFT', $db->quoteName('#__jem_register_items', 'i') . " ON i.capacity_pool_id = p.id AND i.line_kind = 'admission'")
            ->join('LEFT', $db->quoteName('#__jem_register', 'r') . ' ON r.id = i.register_id AND r.revision = i.registration_revision AND r.event = p.event_id')
            ->where('p.event_id IN (' . $list . ')')->where('p.published = 1')
            ->group(['p.id', 'p.event_id', 'p.name', 'p.capacity'])->order('p.id ASC');
        $db->setQuery($query);
        $commercialPools = [];
        foreach ((array) $db->loadObjectList() as $row) {
            $commercialPools[(int) $row->event_id][] = [
                'name' => $row->name, 'space' => '', 'capacity' => (int) $row->capacity,
                'confirmed' => (int) $row->confirmed, 'waiting' => (int) $row->waiting,
            ];
        }
        $totals = [];
        foreach ($ids as $id) {
            $event = clone $events[$id];
            foreach (['confirmed', 'waiting', 'invited'] as $name) {
                $event->$name = (int) ($counts[$id]->$name ?? 0);
            }
            $event->cancelled = (int) ($cancelled[$id]->cancelled ?? 0);
            $pools = $event->capacity_mode === 'areas'
                ? $this->physicalPools((string) ($snapshots[$id]->venue_snapshot ?? ''), $allocations[$id] ?? []) : [];
            if (!$pools && $event->pricing_mode !== 'classic') {
                $pools = $commercialPools[$id] ?? [];
            }
            $totals[$id] = JemRegistrationStatus::totals($event, $pools);
        }
        return $totals;
    }

    /**
     * Read physical areas from the immutable venue snapshot used by registration.
     * @param string $json Event snapshot.
     * @param array $used Current revision allocation totals by area/layout key.
     * @return array Distinct physical capacity rows.
     */
    private function physicalPools(string $json, array $used): array
    {
        $snapshot = json_decode($json, true);
        if (!is_array($snapshot) || ($snapshot['schema'] ?? '') !== 'jem-venue-capacity/v1') {
            return [];
        }
        $pools = [];
        foreach (($snapshot['spaces'] ?? []) as $space) {
            $layout = $space['layout'] ?? [];
            if ((int) ($layout['id'] ?? 0) < 1) {
                continue;
            }
            $areas = array_filter($space['capacity_areas'] ?? [], static fn ($area) => !empty($area['published']) && (int) $area['capacity'] > 0);
            if (!$areas && (int) ($layout['capacity'] ?? 0) > 0) {
                $areas = [['id' => 0, 'name' => $space['name'] ?? '', 'capacity' => (int) $layout['capacity']]];
            }
            foreach ($areas as $area) {
                $key = !empty($area['id']) ? 'area:' . (int) $area['id'] : 'layout:' . (int) $layout['id'];
                $pools[$key] = [
                    'name' => (string) $area['name'], 'space' => (string) ($space['name'] ?? ''),
                    'space_key' => (string) $layout['id'],
                    'capacity' => (int) $area['capacity'], 'confirmed' => (int) ($used[$key]->confirmed ?? 0),
                    'waiting' => (int) ($used[$key]->waiting ?? 0),
                ];
            }
        }
        return array_values($pools);
    }

    /**
     * Append one programme subtree; summaries exclude context-only ancestors.
     * @param array $rows Output rows.
     * @param int $id Event being visited.
     * @param array $members Matched/context members of this group.
     * @param array $events Authorised metadata.
     * @param array $totals Per-event counters.
     * @param string $parent Parent row key.
     * @param int $depth Indentation level.
     * @param array $visited Cycle guard.
     * @return array Additive counters for matching registrations in the subtree.
     */
    private function appendGroup(array &$rows, int $id, array $members, array $events, array $totals, string $parent, int $depth, array &$visited): array
    {
        $visited[$id] = true;
        $event = $events[$id];
        $children = array_filter(array_keys($members), static fn ($child) => !isset($visited[$child]) && (int) $events[$child]->parent_event_id === $id);
        $key = 'event-' . $id;
        $index = count($rows);
        $own = $members[$id];
        $rows[] = ['key' => $key, 'parent' => $parent, 'depth' => $depth, 'kind' => $children ? 'programme' : 'event',
            'event' => $event, 'name' => $event->title, 'context' => !$own, 'totals' => $own ? $totals[$id] : null];
        $summary = ['confirmed' => 0, 'waiting' => 0, 'invited' => 0, 'cancelled' => 0, 'capacity' => null, 'free' => null, 'reserved' => null];
        if ($own) {
            foreach (['confirmed', 'waiting', 'invited', 'cancelled'] as $name) {
                $summary[$name] += $totals[$id][$name];
            }
            if ($children && ((int) $event->registra || $totals[$id]['confirmed'] || $totals[$id]['waiting'] || $totals[$id]['cancelled'] || $totals[$id]['pools'])) {
                $rows[] = ['key' => $key . '-own', 'parent' => $key, 'depth' => $depth + 1, 'kind' => 'own',
                    'event' => $event, 'name' => '', 'context' => false, 'totals' => $totals[$id]];
            }
            $poolParent = $children ? $key . '-own' : $key;
            $spaces = [];
            foreach ($totals[$id]['pools'] as $n => $pool) {
                $spaceKey = !empty($pool['space_key']) ? $key . '-space-' . $pool['space_key'] : '';
                if ($spaceKey !== '' && !isset($spaces[$spaceKey])) {
                    $spaces[$spaceKey] = count($rows);
                    $rows[] = ['key' => $spaceKey, 'parent' => $poolParent, 'depth' => $depth + ($children ? 2 : 1),
                        'kind' => 'space', 'event' => $event, 'name' => $pool['space'], 'space' => '', 'context' => false,
                        'totals' => ['capacity' => 0, 'confirmed' => 0, 'waiting' => 0, 'free' => 0]];
                }
                if ($spaceKey !== '') {
                    $spaceTotals = &$rows[$spaces[$spaceKey]]['totals'];
                    foreach (['capacity', 'confirmed', 'waiting'] as $name) {
                        $spaceTotals[$name] += (int) $pool[$name];
                    }
                    $spaceTotals['free'] = max(0, $spaceTotals['capacity'] - $spaceTotals['confirmed']);
                    if ($totals[$id]['free'] !== null) {
                        $spaceTotals['free'] = min($spaceTotals['free'], $totals[$id]['free']);
                    }
                    unset($spaceTotals);
                }
                $rows[] = ['key' => $key . '-pool-' . $n, 'parent' => $spaceKey ?: $poolParent, 'depth' => $depth + ($children ? 2 : 1) + ($spaceKey !== '' ? 1 : 0),
                    'kind' => 'area', 'event' => $event, 'name' => $pool['name'], 'space' => $pool['space'], 'context' => false,
                    'totals' => $pool + ['reserved' => null, 'invited' => null, 'cancelled' => null]];
            }
        }
        foreach ($children as $child) {
            $sub = $this->appendGroup($rows, $child, $members, $events, $totals, $key, $depth + 1, $visited);
            foreach (['confirmed', 'waiting', 'invited', 'cancelled'] as $name) {
                $summary[$name] += $sub[$name];
            }
        }
        if ($children) {
            $rows[$index]['totals'] = $summary;
        }
        return $summary;
    }

    /**
     * Retrieve a bounded history page only after event and history authorisation.
     * @param int $eventId Authorised event.
     * @param int $page History page.
     * @return array Up to 51 rows (the extra row indicates a next page).
     */
    private function history(int $eventId, int $page): array
    {
        $db = $this->getDatabase();
        $db->setQuery($db->getQuery(true)->select(['occurred', 'action', 'old_status', 'new_status', 'old_places', 'new_places'])
            ->from($db->quoteName('#__jem_register_history'))->where('event_id = ' . $eventId)
            ->order('occurred DESC, id DESC'), (min($page, 1000000) - 1) * 50, 51);
        return (array) $db->loadObjectList();
    }
}
