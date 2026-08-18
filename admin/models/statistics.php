<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

require_once __DIR__ . '/main.php';

/**
 * Model for the dedicated administrator statistics dashboard.
 */
class JemModelStatistics extends JemModelMain
{
    private const METRICS = array(
        'events', 'venues', 'categories', 'types', 'images', 'attachments', 'registrations',
    );

    /**
     * Read and normalise the dashboard filters.
     */
    public function getFilters()
    {
        $input = Factory::getApplication()->input;
        $metric = $input->getCmd('metric', 'all');
        if ($metric !== 'all' && !in_array($metric, self::METRICS, true)) {
            $metric = 'all';
        }

        $period = $input->getCmd('period', '12m');
        if (!in_array($period, array('30d', '90d', '12m', 'all', 'custom'), true)) {
            $period = '12m';
        }

        $timeZone = new DateTimeZone(JemHelper::getJoomlaTimeZoneName());
        $today = new DateTimeImmutable('today', $timeZone);
        $to = $today;
        switch ($period) {
            case '30d':
                $from = $today->modify('-29 days');
                break;
            case '90d':
                $from = $today->modify('-89 days');
                break;
            case 'all':
                $from = $this->earliestDate($today);
                break;
            case 'custom':
                $from = $this->parseDate($input->getString('date_from'), $today->modify('-29 days'), $timeZone);
                $to = $this->parseDate($input->getString('date_to'), $today, $timeZone);
                break;
            case '12m':
            default:
                $from = $today->modify('first day of this month')->modify('-11 months');
                break;
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $group = $input->getCmd('group', '');
        if (!in_array($group, array('day', 'week', 'month', 'year'), true)) {
            $group = $period === 'all' ? 'year' : ($period === '12m' ? 'month' : 'day');
        }

        $days = max(1, (int) $from->diff($to)->format('%a') + 1);
        if ($group === 'day' && $days > 400) {
            $group = 'month';
        }
        if ($group === 'week' && $days > 2800) {
            $group = 'month';
        }
        if ($group === 'month' && $days > 12400) {
            $group = 'year';
        }

        $state = $input->getString('state_filter', '');
        if (!in_array($state, array('', '-2', '0', '1', '2'), true)) {
            $state = '';
        }

        $subtype = $input->getCmd('subtype', '');
        $allowedSubtypes = array(
            'events' => array('', 'parent', 'child'),
            'venues' => array('', 'parent', 'child'),
            'types' => array('', 'event', 'category', 'venue'),
            'images' => array('', 'events', 'venues', 'categories', 'types'),
            'attachments' => array('', 'event', 'venue', 'category', 'other'),
            'registrations' => array('', 'attending', 'waiting', 'invited', 'not_attending'),
        );
        if (!isset($allowedSubtypes[$metric]) || !in_array($subtype, $allowedSubtypes[$metric], true)) {
            $subtype = '';
        }

        $venueId = max(0, $input->getInt('venue_id', 0));
        $categoryId = max(0, $input->getInt('category_id', 0));
        $typeId = max(0, $input->getInt('type_id', 0));
        $parentEventId = max(0, $input->getInt('parent_event_id', 0));
        $authorId = max(0, $input->getInt('author_id', 0));

        return (object) array(
            'metric' => $metric,
            'period' => $period,
            'group' => $group,
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
            'from_sql' => $from->format('Y-m-d 00:00:00'),
            'to_exclusive_sql' => $to->modify('+1 day')->format('Y-m-d 00:00:00'),
            'state' => $state,
            'subtype' => $subtype,
            'venue_id' => $venueId,
            'category_id' => $categoryId,
            'type_id' => $typeId,
            'parent_event_id' => $parentEventId,
            'author_id' => $authorId,
        );
    }

    /**
     * Human-readable choices for event-scoped dashboard filters.
     */
    public function getFilterOptions()
    {
        $db = $this->getDatabase();
        $options = (object) array('venues' => array(), 'categories' => array(), 'types' => array(), 'programmes' => array(), 'authors' => array());

        $query = $db->getQuery(true)
            ->select(array('v.id', 'v.venue AS text', 'v.parent_venue_id', 'parent.venue AS parent_text'))
            ->from($db->quoteName('#__jem_venues', 'v'))
            ->join('LEFT', $db->quoteName('#__jem_venues', 'parent') . ' ON parent.id = v.parent_venue_id')
            ->where('v.published <> -2')
            ->order('COALESCE(parent.venue, v.venue) ASC, CASE WHEN v.parent_venue_id IS NULL OR v.parent_venue_id = 0 THEN 0 ELSE 1 END ASC, v.venue ASC');
        $db->setQuery($query);
        $options->venues = (array) $db->loadObjectList();

        $query = $db->getQuery(true)
            ->select(array('id', 'catname AS text', 'level'))
            ->from($db->quoteName('#__jem_categories'))
            ->where("alias <> 'root'")
            ->where('published <> -2')
            ->order('lft ASC');
        $db->setQuery($query);
        $options->categories = (array) $db->loadObjectList();

        $query = $db->getQuery(true)
            ->select(array('id', 'name AS text'))
            ->from($db->quoteName('#__jem_types'))
            ->where('entity = 1')
            ->where('published <> -2')
            ->order('name ASC');
        $db->setQuery($query);
        $options->types = (array) $db->loadObjectList();

        $query = $db->getQuery(true)
            ->select(array('parent.id', 'parent.title AS text'))
            ->from($db->quoteName('#__jem_events', 'parent'))
            ->where('(parent.parent_event_id IS NULL OR parent.parent_event_id = 0)')
            ->where('parent.published <> -2')
            ->where('EXISTS (SELECT 1 FROM ' . $db->quoteName('#__jem_events', 'child') . ' WHERE child.parent_event_id = parent.id AND child.published <> -2)')
            ->order('parent.title ASC');
        $db->setQuery($query);
        $options->programmes = (array) $db->loadObjectList();

        $query = $db->getQuery(true)
            ->select(array('u.id', 'u.name AS text', 'u.username'))
            ->from($db->quoteName('#__users', 'u'))
            ->where('EXISTS (SELECT 1 FROM ' . $db->quoteName('#__jem_events', 'authored') . ' WHERE authored.created_by = u.id)')
            ->order('u.name ASC, u.username ASC');
        $db->setQuery($query);
        $options->authors = (array) $db->loadObjectList();

        return $options;
    }

    /**
     * Return permitted statistic cards, summaries and time series.
     */
    public function getDashboardData($filters, array $permissions)
    {
        $summaries = array(
            'events' => !empty($permissions['events']) ? $this->getEventsData() : null,
            'venues' => !empty($permissions['venues']) ? $this->getVenuesData() : null,
            'categories' => $this->getCategoriesData(),
            'types' => $this->getTypesData(),
            'type_entities' => $this->getTypeEntitiesData(),
            'images' => $this->getImagesData(),
            'attachments' => $this->getAttachmentsData(),
            'registrations' => !empty($permissions['registrations']) ? $this->getRegistrationData() : null,
        );
        if ($this->hasEventFilters($filters)) {
            if ($summaries['events']) {
                $summaries['events'] = $this->getFilteredEventSummary($filters);
            }
            if ($summaries['registrations']) {
                $summaries['registrations'] = $this->getFilteredRegistrationSummary($filters);
            }
        }
        if ($summaries['venues'] && (int) $filters->venue_id > 0) {
            $summaries['venues'] = $this->getFilteredVenueSummary($filters);
        }
        foreach (array('images', 'attachments') as $summaryKey) {
            if (empty($permissions['events'])) {
                $summaries[$summaryKey]->total -= (int) $summaries[$summaryKey]->events;
                $summaries[$summaryKey]->events = 0;
            }
            if (empty($permissions['venues'])) {
                $summaries[$summaryKey]->total -= (int) $summaries[$summaryKey]->venues;
                $summaries[$summaryKey]->venues = 0;
            }
        }
        $hierarchy = $this->getHierarchyData();
        if ($summaries['events'] && !$this->hasEventFilters($filters)) {
            $summaries['events']->parents = $hierarchy->event_parents;
            $summaries['events']->children = $hierarchy->event_children;
        }
        if ($summaries['venues'] && (int) $filters->venue_id === 0) {
            $summaries['venues']->parents = $hierarchy->venue_parents;
            $summaries['venues']->children = $hierarchy->venue_children;
        }

        $available = array('categories', 'types', 'images', 'attachments');
        if (!empty($permissions['events'])) {
            array_unshift($available, 'events');
        }
        if (!empty($permissions['venues'])) {
            $position = in_array('events', $available, true) ? 1 : 0;
            array_splice($available, $position, 0, array('venues'));
        }
        if (!empty($permissions['registrations'])) {
            $available[] = 'registrations';
        }

        $selected = $filters->metric === 'all'
            ? $available
            : (in_array($filters->metric, $available, true) ? array($filters->metric) : array());
        $cards = array();
        foreach ($selected as $metric) {
            $points = $this->timeline($metric, $filters, $permissions);
            $cards[$metric] = (object) array(
                'key' => $metric,
                'title_key' => $this->titleKey($metric),
                'series_key' => $this->seriesKey($metric),
                'period_total' => array_sum(array_column($points, 'value')),
                'all_total' => (int) ($summaries[$metric]->total ?? 0),
                'points' => $points,
                'summary' => $summaries[$metric],
                'secondary' => $metric === 'types' ? $summaries['type_entities'] : null,
            );
        }

        return (object) array(
            'cards' => $cards,
            'summaries' => $summaries,
            'venue_infrastructure' => !empty($permissions['venues']) ? $this->getVenueInfrastructure($filters) : array(),
            'future_events' => !empty($permissions['events']) ? $this->getFutureEventCapacity($filters) : array(),
            'programmes' => !empty($permissions['events']) ? $this->getProgrammeSummary($filters) : array(),
            'booking_value_series' => !empty($permissions['registrations']) ? $this->getBookingValueTimeline($filters) : array(),
            'registration_workflow' => !empty($permissions['registrations']) ? $this->getRegistrationWorkflowSummary($filters) : new stdClass(),
            'registration_commercial' => !empty($permissions['registrations'])
                ? $this->getRegistrationCommercialSummary($filters)
                : (object) array('revenue' => array()),
        );
    }

    private function hasEventFilters($filters)
    {
        return (int) $filters->venue_id > 0
            || (int) $filters->category_id > 0
            || (int) $filters->type_id > 0
            || (int) $filters->parent_event_id > 0
            || (int) $filters->author_id > 0;
    }

    private function getFilteredEventSummary($filters)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(array(
                'COUNT(*) AS total',
                'SUM(CASE WHEN e.published = 1 THEN 1 ELSE 0 END) AS published',
                'SUM(CASE WHEN e.published = 0 THEN 1 ELSE 0 END) AS unpublished',
                'SUM(CASE WHEN e.published = 2 THEN 1 ELSE 0 END) AS archived',
                'SUM(CASE WHEN e.published = -2 THEN 1 ELSE 0 END) AS trashed',
                'SUM(CASE WHEN (e.parent_event_id IS NULL OR e.parent_event_id = 0) AND e.published <> -2 THEN 1 ELSE 0 END) AS parents',
                'SUM(CASE WHEN e.parent_event_id > 0 AND e.published <> -2 THEN 1 ELSE 0 END) AS children',
            ))
            ->from($db->quoteName('#__jem_events', 'e'));
        foreach ($this->eventFilterConditions($filters, 'e') as $condition) {
            $query->where($condition);
        }
        $db->setQuery($query);
        $row = $db->loadObject() ?: new stdClass();
        foreach (array('total', 'published', 'unpublished', 'archived', 'trashed', 'parents', 'children') as $field) {
            $row->{$field} = (int) ($row->{$field} ?? 0);
        }

        return $row;
    }

    private function getFilteredRegistrationSummary($filters)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(array(
                'COUNT(*) AS total',
                'SUM(CASE WHEN r.status = 1 AND r.waiting = 0 THEN 1 ELSE 0 END) AS attending_users',
                'SUM(CASE WHEN r.status = 1 AND r.waiting = 1 THEN 1 ELSE 0 END) AS waiting_users',
                'SUM(CASE WHEN r.status = 0 THEN 1 ELSE 0 END) AS invited_users',
                'SUM(CASE WHEN r.status = -1 THEN 1 ELSE 0 END) AS not_attending_users',
                'COALESCE(SUM(CASE WHEN r.status = 1 AND r.waiting = 0 THEN r.places ELSE 0 END), 0) AS booked_places',
                'COALESCE(SUM(CASE WHEN r.status = 1 AND r.waiting = 1 THEN r.places ELSE 0 END), 0) AS waiting_places',
                'COALESCE(SUM(CASE WHEN r.status = 0 THEN r.places ELSE 0 END), 0) AS invited_places',
            ))
            ->from($db->quoteName('#__jem_register', 'r'))
            ->join('INNER', $db->quoteName('#__jem_events', 'e') . ' ON e.id = r.event');
        foreach ($this->eventFilterConditions($filters, 'e') as $condition) {
            $query->where($condition);
        }
        $db->setQuery($query);
        $row = $db->loadObject() ?: new stdClass();
        foreach (array('total', 'attending_users', 'waiting_users', 'invited_users', 'not_attending_users', 'booked_places', 'waiting_places', 'invited_places') as $field) {
            $row->{$field} = (int) ($row->{$field} ?? 0);
        }

        return $row;
    }

    private function getFilteredVenueSummary($filters)
    {
        $db = $this->getDatabase();
        $venueId = (int) $filters->venue_id;
        $query = $db->getQuery(true)
            ->select(array(
                'COUNT(*) AS total',
                'SUM(CASE WHEN v.published = 1 THEN 1 ELSE 0 END) AS published',
                'SUM(CASE WHEN v.published = 0 THEN 1 ELSE 0 END) AS unpublished',
                'SUM(CASE WHEN v.published = 2 THEN 1 ELSE 0 END) AS archived',
                'SUM(CASE WHEN v.published = -2 THEN 1 ELSE 0 END) AS trashed',
                'SUM(CASE WHEN (v.parent_venue_id IS NULL OR v.parent_venue_id = 0) AND v.published <> -2 THEN 1 ELSE 0 END) AS parents',
                'SUM(CASE WHEN v.parent_venue_id > 0 AND v.published <> -2 THEN 1 ELSE 0 END) AS children',
            ))
            ->from($db->quoteName('#__jem_venues', 'v'))
            ->where('(v.id = ' . $venueId . ' OR v.parent_venue_id = ' . $venueId . ')');
        $db->setQuery($query);
        $row = $db->loadObject() ?: new stdClass();
        foreach (array('total', 'published', 'unpublished', 'archived', 'trashed', 'parents', 'children') as $field) {
            $row->{$field} = (int) ($row->{$field} ?? 0);
        }

        return $row;
    }

    /**
     * Split current event and venue totals into roots and direct children.
     */
    public function getHierarchyData()
    {
        $db = $this->getDatabase();
        $data = new stdClass();
        foreach (array('event' => '#__jem_events', 'venue' => '#__jem_venues') as $name => $table) {
            $field = 'parent_' . $name . '_id';
            $query = $db->getQuery(true)
                ->select('SUM(CASE WHEN ' . $db->quoteName($field) . ' IS NULL OR ' . $db->quoteName($field) . ' = 0 THEN 1 ELSE 0 END) AS parents')
                ->select('SUM(CASE WHEN ' . $db->quoteName($field) . ' > 0 THEN 1 ELSE 0 END) AS children')
                ->from($db->quoteName($table))
                ->where($db->quoteName('published') . ' <> -2');
            $db->setQuery($query);
            $row = $db->loadObject() ?: new stdClass();
            $data->{$name . '_parents'} = (int) ($row->parents ?? 0);
            $data->{$name . '_children'} = (int) ($row->children ?? 0);
        }

        return $data;
    }

    /**
     * Return current physical configuration counts for each venue.
     */
    public function getVenueInfrastructure($filters)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(array(
                'v.id', 'v.venue', 'v.parent_venue_id', 'v.capacity', 'parent.venue AS parent_name',
                '(SELECT COUNT(*) FROM ' . $db->quoteName('#__jem_venue_capacity_profiles', 'p')
                    . ' WHERE p.venue_id = v.id AND p.published = 1) AS profiles',
                '(SELECT COUNT(*) FROM ' . $db->quoteName('#__jem_venue_spaces', 's')
                    . ' WHERE s.venue_id = v.id AND s.published = 1) AS spaces',
                '(SELECT COUNT(DISTINCT a.id) FROM ' . $db->quoteName('#__jem_venue_capacity_areas', 'a')
                    . ' INNER JOIN ' . $db->quoteName('#__jem_venue_profile_spaces', 'ps') . ' ON ps.venue_layout_id = a.venue_layout_id'
                    . ' INNER JOIN ' . $db->quoteName('#__jem_venue_capacity_profiles', 'p2') . ' ON p2.id = ps.venue_profile_id'
                    . ' WHERE p2.venue_id = v.id AND p2.published = 1 AND a.published = 1) AS areas',
            ))
            ->from($db->quoteName('#__jem_venues', 'v'))
            ->join('LEFT', $db->quoteName('#__jem_venues', 'parent') . ' ON parent.id = v.parent_venue_id')
            ->where('v.published <> -2');
        if ((int) $filters->venue_id > 0) {
            $query->where('(v.id = ' . (int) $filters->venue_id . ' OR v.parent_venue_id = ' . (int) $filters->venue_id . ')');
        }
        $query->order('COALESCE(parent.venue, v.venue) ASC, CASE WHEN v.parent_venue_id IS NULL OR v.parent_venue_id = 0 THEN 0 ELSE 1 END ASC, v.venue ASC');
        $db->setQuery($query);

        return (array) $db->loadObjectList();
    }

    /**
     * Future event occupancy and confirmed commercial totals. Register rows
     * already point at their current immutable revision and monetary snapshot.
     */
    public function getFutureEventCapacity($filters)
    {
        $db = $this->getDatabase();
        $today = JemHelper::getJoomlaDate();
        $query = $db->getQuery(true)
            ->select(array(
                'e.id', 'e.title', 'e.dates', 'e.times', 'e.parent_event_id', 'parent.title AS parent_title',
                'e.maxplaces', 'e.reservedplaces', 'e.pricing_mode', 'e.currency',
                'SUM(CASE WHEN r.status = 1 AND r.waiting = 0 THEN 1 ELSE 0 END) AS confirmed_orders',
                'COALESCE(SUM(CASE WHEN r.status = 1 AND r.waiting = 0 THEN r.places ELSE 0 END), 0) AS confirmed_places',
                'SUM(CASE WHEN r.status = 1 AND r.waiting = 1 THEN 1 ELSE 0 END) AS waiting_orders',
                'COALESCE(SUM(CASE WHEN r.status = 1 AND r.waiting = 1 THEN r.places ELSE 0 END), 0) AS waiting_places',
                'COALESCE(SUM(CASE WHEN r.status = 1 AND r.waiting = 0 THEN r.grand_total ELSE 0 END), 0) AS confirmed_revenue',
            ))
            ->from($db->quoteName('#__jem_events', 'e'))
            ->join('LEFT', $db->quoteName('#__jem_events', 'parent') . ' ON parent.id = e.parent_event_id')
            ->join('LEFT', $db->quoteName('#__jem_register', 'r') . ' ON r.event = e.id')
            ->where('e.published = 1')
            ->where('e.dates IS NOT NULL')
            ->where('e.dates >= ' . $db->quote($today))
            ->where('(e.registra = 1 OR EXISTS (SELECT 1 FROM ' . $db->quoteName('#__jem_register', 'existing') . ' WHERE existing.event = e.id))');
        foreach ($this->eventFilterConditions($filters, 'e') as $condition) {
            $query->where($condition);
        }
        $query
            ->group(array(
                'e.id', 'e.title', 'e.dates', 'e.times', 'e.parent_event_id', 'parent.title',
                'e.maxplaces', 'e.reservedplaces', 'e.pricing_mode', 'e.currency',
            ))
            ->order('e.dates ASC, e.times ASC, COALESCE(parent.title, e.title) ASC, e.title ASC');
        $db->setQuery($query, 0, 100);
        $events = (array) $db->loadObjectList();
        if (!$events) {
            return array();
        }

        $eventIds = array_map(static fn ($event) => (int) $event->id, $events);
        $poolQuery = $db->getQuery(true)
            ->select(array(
                'p.event_id', 'p.id', 'p.name', 'p.capacity',
                'COALESCE(SUM(CASE WHEN r.status = 1 AND r.waiting = 0 AND i.registration_revision = r.revision THEN i.quantity ELSE 0 END), 0) AS used',
            ))
            ->from($db->quoteName('#__jem_capacity_pools', 'p'))
            ->join('LEFT', $db->quoteName('#__jem_register_items', 'i') . ' ON i.capacity_pool_id = p.id AND i.line_kind = ' . $db->quote('admission'))
            ->join('LEFT', $db->quoteName('#__jem_register', 'r') . ' ON r.id = i.register_id')
            ->where('p.published = 1')
            ->where('p.event_id IN (' . implode(',', $eventIds) . ')')
            ->group(array('p.event_id', 'p.id', 'p.name', 'p.capacity'))
            ->order('p.event_id ASC, p.ordering ASC, p.id ASC');
        $db->setQuery($poolQuery);
        $pools = array();
        foreach ((array) $db->loadObjectList() as $pool) {
            $pool->used = (int) $pool->used;
            $pool->remaining = max(0, (int) $pool->capacity - $pool->used);
            $pools[(int) $pool->event_id][] = $pool;
        }

        foreach ($events as $event) {
            $event->confirmed_orders = (int) $event->confirmed_orders;
            $event->confirmed_places = (int) $event->confirmed_places;
            $event->waiting_orders = (int) $event->waiting_orders;
            $event->waiting_places = (int) $event->waiting_places;
            $event->available_places = (int) $event->maxplaces > 0
                ? max(0, (int) $event->maxplaces - (int) $event->reservedplaces - $event->confirmed_places)
                : null;
            $event->occupancy_percent = (int) $event->maxplaces > 0
                ? min(100, round(100 * ((int) $event->reservedplaces + $event->confirmed_places) / (int) $event->maxplaces, 1))
                : null;
            $event->pools = $pools[(int) $event->id] ?? array();
        }

        return $events;
    }

    /**
     * Current registration/order summary and confirmed revenue per currency.
     */
    public function getRegistrationCommercialSummary($filters)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(array(
                "SUM(CASE WHEN r.pricing_mode = 'classic' THEN 1 ELSE 0 END) AS classic",
                "SUM(CASE WHEN r.pricing_mode <> 'classic' THEN 1 ELSE 0 END) AS priced",
                'SUM(CASE WHEN r.status = 1 AND r.waiting = 0 THEN 1 ELSE 0 END) AS confirmed',
                'SUM(CASE WHEN r.status = 1 AND r.waiting = 1 THEN 1 ELSE 0 END) AS waiting',
                'SUM(CASE WHEN r.status = 0 THEN 1 ELSE 0 END) AS invited',
                'SUM(CASE WHEN r.status = -1 THEN 1 ELSE 0 END) AS cancelled',
                'COALESCE(SUM(CASE WHEN r.status = 1 AND r.waiting = 0 THEN r.places ELSE 0 END), 0) AS confirmed_places',
            ))
            ->from($db->quoteName('#__jem_register', 'r'))
            ->join('INNER', $db->quoteName('#__jem_events', 'e') . ' ON e.id = r.event');
        foreach ($this->eventFilterConditions($filters, 'e') as $condition) {
            $query->where($condition);
        }
        $db->setQuery($query);
        $summary = $db->loadObject() ?: new stdClass();
        foreach (array('classic', 'priced', 'confirmed', 'waiting', 'invited', 'cancelled', 'confirmed_places') as $field) {
            $summary->{$field} = (int) ($summary->{$field} ?? 0);
        }

        $revenueQuery = $db->getQuery(true)
            ->select(array('r.currency', 'SUM(r.grand_total) AS total'))
            ->from($db->quoteName('#__jem_register', 'r'))
            ->join('INNER', $db->quoteName('#__jem_events', 'e') . ' ON e.id = r.event')
            ->where('r.status = 1 AND r.waiting = 0')
            ->where("r.pricing_mode <> 'classic'")
            ->where("r.currency <> ''");
        foreach ($this->eventFilterConditions($filters, 'e') as $condition) {
            $revenueQuery->where($condition);
        }
        $revenueQuery->group('r.currency')->order('r.currency ASC');
        $db->setQuery($revenueQuery);
        $summary->revenue = (array) $db->loadObjectList();

        return $summary;
    }

    /**
     * Confirmed booking value over time, grouped by the immutable order currency.
     */
    public function getBookingValueTimeline($filters)
    {
        $db = $this->getDatabase();
        $dateSql = "COALESCE(r.created, STR_TO_DATE(NULLIF(r.uregdate, ''), '%Y-%m-%d %H:%i:%s'))";
        $bucket = $this->bucketExpression($dateSql, $filters->group);
        $query = $db->getQuery(true)
            ->select(array(
                $bucket . ' AS ' . $db->quoteName('bucket'),
                'r.currency',
                'COUNT(*) AS orders',
                'COALESCE(SUM(r.places), 0) AS places',
                'COALESCE(SUM(r.grand_total), 0) AS value',
            ))
            ->from($db->quoteName('#__jem_register', 'r'))
            ->join('INNER', $db->quoteName('#__jem_events', 'e') . ' ON e.id = r.event')
            ->where('r.status = 1 AND r.waiting = 0')
            ->where("r.pricing_mode <> 'classic'")
            ->where("r.currency <> ''")
            ->where($dateSql . ' >= ' . $db->quote($filters->from_sql))
            ->where($dateSql . ' < ' . $db->quote($filters->to_exclusive_sql));
        foreach ($this->eventFilterConditions($filters, 'e') as $condition) {
            $query->where($condition);
        }
        $query->group(array($bucket, 'r.currency'))->order($bucket . ' ASC, r.currency ASC');
        $db->setQuery($query);

        $currencies = array();
        foreach ((array) $db->loadObjectList() as $row) {
            $currency = (string) $row->currency;
            $key = (string) $row->bucket;
            if (!isset($currencies[$currency])) {
                $currencies[$currency] = array('values' => array(), 'orders' => array(), 'places' => array());
            }
            $currencies[$currency]['values'][$key] = (float) $row->value;
            $currencies[$currency]['orders'][$key] = (int) $row->orders;
            $currencies[$currency]['places'][$key] = (int) $row->places;
        }

        $series = array();
        foreach ($currencies as $currency => $data) {
            $points = $this->fillTimelineValues($data['values'], $filters, false);
            $orders = 0;
            $places = 0;
            foreach ($points as &$point) {
                $point['orders'] = (int) ($data['orders'][$point['key']] ?? 0);
                $point['places'] = (int) ($data['places'][$point['key']] ?? 0);
                $point['display'] = $currency . ' ' . number_format((float) $point['value'], 2, '.', '');
                $orders += $point['orders'];
                $places += $point['places'];
            }
            unset($point);
            $series[] = (object) array(
                'currency' => $currency,
                'total' => array_sum(array_column($points, 'value')),
                'orders' => $orders,
                'places' => $places,
                'points' => $points,
            );
        }

        return $series;
    }

    /**
     * Registration lifecycle activity inside the selected reporting period.
     */
    public function getRegistrationWorkflowSummary($filters)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(array(
                "SUM(CASE WHEN h.action = 'cancelled' THEN 1 ELSE 0 END) AS cancelled",
                "SUM(CASE WHEN h.action = 'reactivated' THEN 1 ELSE 0 END) AS reactivated",
                "SUM(CASE WHEN h.action = 'promoted' THEN 1 ELSE 0 END) AS promoted",
                "SUM(CASE WHEN h.action = 'order_modified' OR h.action = 'places_changed' THEN 1 ELSE 0 END) AS modified",
            ))
            ->from($db->quoteName('#__jem_register_history', 'h'))
            ->join('INNER', $db->quoteName('#__jem_events', 'e') . ' ON e.id = h.event_id')
            ->where('h.occurred >= ' . $db->quote($filters->from_sql))
            ->where('h.occurred < ' . $db->quote($filters->to_exclusive_sql));
        foreach ($this->eventFilterConditions($filters, 'e') as $condition) {
            $query->where($condition);
        }
        $db->setQuery($query);
        $summary = $db->loadObject() ?: new stdClass();
        foreach (array('cancelled', 'reactivated', 'promoted', 'modified') as $field) {
            $summary->{$field} = (int) ($summary->{$field} ?? 0);
        }

        $waitingQuery = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__jem_register', 'r'))
            ->join('INNER', $db->quoteName('#__jem_events', 'e') . ' ON e.id = r.event')
            ->where('r.status = 1 AND r.waiting = 1');
        foreach ($this->eventFilterConditions($filters, 'e') as $condition) {
            $waitingQuery->where($condition);
        }
        $db->setQuery($waitingQuery);
        $summary->current_waiting = (int) $db->loadResult();
        $queueTotal = $summary->promoted + $summary->current_waiting;
        $summary->queue_resolution = $queueTotal > 0 ? round(100 * $summary->promoted / $queueTotal, 1) : null;

        return $summary;
    }

    /**
     * Aggregate programme orders across each main event and its children.
     * Capacity is intentionally not summed because programme members can use
     * independent or shared physical pools.
     */
    public function getProgrammeSummary($filters)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select(array(
                'parent.id', 'parent.title', 'parent.dates',
                'COUNT(DISTINCT CASE WHEN member.parent_event_id = parent.id THEN member.id END) AS child_events',
                'COUNT(DISTINCT CASE WHEN r.status = 1 AND r.waiting = 0 THEN r.id END) AS confirmed_orders',
                'COALESCE(SUM(CASE WHEN r.status = 1 AND r.waiting = 0 THEN r.places ELSE 0 END), 0) AS confirmed_places',
                'COUNT(DISTINCT CASE WHEN r.status = 1 AND r.waiting = 1 THEN r.id END) AS waiting_orders',
                'COALESCE(SUM(CASE WHEN r.status = 1 AND r.waiting = 1 THEN r.places ELSE 0 END), 0) AS waiting_places',
            ))
            ->from($db->quoteName('#__jem_events', 'parent'))
            ->join('INNER', $db->quoteName('#__jem_events', 'member') . ' ON member.id = parent.id OR member.parent_event_id = parent.id')
            ->join('LEFT', $db->quoteName('#__jem_register', 'r') . ' ON r.event = member.id')
            ->where('(parent.parent_event_id IS NULL OR parent.parent_event_id = 0)')
            ->where('parent.published <> -2')
            ->where('EXISTS (SELECT 1 FROM ' . $db->quoteName('#__jem_events', 'child') . ' WHERE child.parent_event_id = parent.id AND child.published <> -2)');
        foreach ($this->eventFilterConditions($filters, 'member') as $condition) {
            $query->where($condition);
        }
        $query->group(array('parent.id', 'parent.title', 'parent.dates'))
            ->order('parent.dates ASC, parent.title ASC');
        $db->setQuery($query, 0, 100);
        $programmes = (array) $db->loadObjectList();
        if (!$programmes) {
            return array();
        }

        $programmeIds = array_map(static fn ($programme) => (int) $programme->id, $programmes);
        $revenueQuery = $db->getQuery(true)
            ->select(array('parent.id AS programme_id', 'r.currency', 'SUM(r.grand_total) AS total'))
            ->from($db->quoteName('#__jem_events', 'parent'))
            ->join('INNER', $db->quoteName('#__jem_events', 'member') . ' ON member.id = parent.id OR member.parent_event_id = parent.id')
            ->join('INNER', $db->quoteName('#__jem_register', 'r') . ' ON r.event = member.id')
            ->where('parent.id IN (' . implode(',', $programmeIds) . ')')
            ->where('r.status = 1 AND r.waiting = 0')
            ->where("r.pricing_mode <> 'classic' AND r.currency <> ''");
        foreach ($this->eventFilterConditions($filters, 'member') as $condition) {
            $revenueQuery->where($condition);
        }
        $revenueQuery->group(array('parent.id', 'r.currency'))->order('parent.id ASC, r.currency ASC');
        $db->setQuery($revenueQuery);
        $revenue = array();
        foreach ((array) $db->loadObjectList() as $row) {
            $revenue[(int) $row->programme_id][] = $row;
        }

        foreach ($programmes as $programme) {
            foreach (array('child_events', 'confirmed_orders', 'confirmed_places', 'waiting_orders', 'waiting_places') as $field) {
                $programme->{$field} = (int) $programme->{$field};
            }
            $programme->revenue = $revenue[(int) $programme->id] ?? array();
        }

        return $programmes;
    }

    private function eventFilterConditions($filters, $alias = '')
    {
        $prefix = $alias !== '' ? $alias . '.' : '#__jem_events.';
        $conditions = array();
        if ((int) $filters->venue_id > 0) {
            $conditions[] = $prefix . 'locid = ' . (int) $filters->venue_id;
        }
        if ((int) $filters->category_id > 0) {
            $conditions[] = 'EXISTS (SELECT 1 FROM #__jem_cats_event_relations AS scope_category WHERE scope_category.itemid = '
                . $prefix . 'id AND scope_category.catid = ' . (int) $filters->category_id . ')';
        }
        if ((int) $filters->type_id > 0) {
            $conditions[] = $prefix . 'type_id = ' . (int) $filters->type_id;
        }
        if ((int) $filters->parent_event_id > 0) {
            $conditions[] = '(' . $prefix . 'id = ' . (int) $filters->parent_event_id
                . ' OR ' . $prefix . 'parent_event_id = ' . (int) $filters->parent_event_id . ')';
        }
        if ((int) $filters->author_id > 0) {
            $conditions[] = $prefix . 'created_by = ' . (int) $filters->author_id;
        }

        return $conditions;
    }

    private function timeline($metric, $filters, array $permissions)
    {
        $where = array();
        $dateExpression = '';
        $table = '';

        switch ($metric) {
            case 'events':
                $table = '#__jem_events';
                $dateExpression = 'created';
                $this->addStateFilter($where, $filters);
                foreach ($this->eventFilterConditions($filters) as $condition) {
                    $where[] = $condition;
                }
                if ($filters->subtype === 'parent') {
                    $where[] = 'parent_event_id = 0';
                } elseif ($filters->subtype === 'child') {
                    $where[] = 'parent_event_id > 0';
                }
                break;
            case 'venues':
                $table = '#__jem_venues';
                $dateExpression = 'created';
                $this->addStateFilter($where, $filters);
                if ((int) $filters->venue_id > 0) {
                    $where[] = '(id = ' . (int) $filters->venue_id . ' OR parent_venue_id = ' . (int) $filters->venue_id . ')';
                }
                if ($filters->subtype === 'parent') {
                    $where[] = 'parent_venue_id = 0';
                } elseif ($filters->subtype === 'child') {
                    $where[] = 'parent_venue_id > 0';
                }
                break;
            case 'categories':
                $table = '#__jem_categories';
                $dateExpression = 'created_time';
                $where[] = "alias <> 'root'";
                $this->addStateFilter($where, $filters);
                break;
            case 'types':
                $table = '#__jem_types';
                $dateExpression = 'created';
                $this->addStateFilter($where, $filters);
                $entities = array('event' => 1, 'category' => 2, 'venue' => 3);
                if (isset($entities[$filters->subtype])) {
                    $where[] = 'entity = ' . (int) $entities[$filters->subtype];
                }
                break;
            case 'attachments':
                $table = '#__jem_attachments';
                $dateExpression = 'created';
                if (($filters->subtype === 'event' && empty($permissions['events']))
                    || ($filters->subtype === 'venue' && empty($permissions['venues']))) {
                    $where[] = '1 = 0';
                } elseif (in_array($filters->subtype, array('event', 'venue', 'category'), true)) {
                    $where[] = 'object LIKE ' . $this->getDatabase()->quote($filters->subtype . '%');
                } elseif ($filters->subtype === 'other') {
                    $where[] = "object NOT LIKE 'event%' AND object NOT LIKE 'venue%' AND object NOT LIKE 'category%'";
                } else {
                    if (empty($permissions['events'])) {
                        $where[] = "object NOT LIKE 'event%'";
                    }
                    if (empty($permissions['venues'])) {
                        $where[] = "object NOT LIKE 'venue%'";
                    }
                }
                break;
            case 'registrations':
                $table = '#__jem_register';
                $dateExpression = "COALESCE(created, STR_TO_DATE(NULLIF(uregdate, ''), '%Y-%m-%d %H:%i:%s'))";
                $statusWhere = array(
                    'attending' => 'status = 1 AND waiting = 0',
                    'waiting' => 'status = 1 AND waiting = 1',
                    'invited' => 'status = 0',
                    'not_attending' => 'status = -1',
                );
                if (isset($statusWhere[$filters->subtype])) {
                    $where[] = $statusWhere[$filters->subtype];
                }
                $eventConditions = $this->eventFilterConditions($filters, 'scope_event');
                if ($eventConditions) {
                    $where[] = 'event IN (SELECT scope_event.id FROM #__jem_events AS scope_event WHERE ' . implode(' AND ', $eventConditions) . ')';
                }
                break;
            case 'images':
                return $this->imageTimeline($filters, $permissions);
            default:
                return array();
        }

        $values = $this->queryTimeline($table, $dateExpression, $filters, $where);

        return $this->fillTimeline($values, $filters);
    }

    private function imageTimeline($filters, array $permissions)
    {
        $sources = array(
            'events' => array('#__jem_events', 'created', "(CASE WHEN datimage IS NOT NULL AND datimage <> '' THEN 1 ELSE 0 END + CASE WHEN fullimage IS NOT NULL AND fullimage <> '' THEN 1 ELSE 0 END)"),
            'venues' => array('#__jem_venues', 'created', "CASE WHEN locimage IS NOT NULL AND locimage <> '' THEN 1 ELSE 0 END"),
            'categories' => array('#__jem_categories', 'created_time', "CASE WHEN image IS NOT NULL AND image <> '' THEN 1 ELSE 0 END"),
            'types' => array('#__jem_types', 'created', "CASE WHEN icon IS NOT NULL AND icon <> '' THEN 1 ELSE 0 END"),
        );
        if (empty($permissions['events'])) {
            unset($sources['events']);
        }
        if (empty($permissions['venues'])) {
            unset($sources['venues']);
        }
        if ($filters->subtype !== '' && isset($sources[$filters->subtype])) {
            $sources = array($filters->subtype => $sources[$filters->subtype]);
        }

        $combined = array();
        foreach ($sources as $key => [$table, $dateExpression, $countExpression]) {
            $where = $key === 'categories' ? array("alias <> 'root'") : array();
            $values = $this->queryTimeline($table, $dateExpression, $filters, $where, 'SUM(' . $countExpression . ')');
            foreach ($values as $bucket => $value) {
                $combined[$bucket] = ($combined[$bucket] ?? 0) + (int) $value;
            }
        }

        return $this->fillTimeline($combined, $filters);
    }

    private function queryTimeline($table, $dateExpression, $filters, array $where, $countExpression = 'COUNT(*)')
    {
        $db = $this->getDatabase();
        $dateSql = preg_match('/^[a-z_]+$/i', $dateExpression)
            ? $db->quoteName($dateExpression)
            : $dateExpression;
        $bucket = $this->bucketExpression($dateSql, $filters->group);

        $query = $db->getQuery(true)
            ->select($bucket . ' AS ' . $db->quoteName('bucket'))
            ->select($countExpression . ' AS ' . $db->quoteName('num'))
            ->from($db->quoteName($table))
            ->where($dateSql . ' >= ' . $db->quote($filters->from_sql))
            ->where($dateSql . ' < ' . $db->quote($filters->to_exclusive_sql));
        foreach ($where as $condition) {
            $query->where($condition);
        }
        $query->group($bucket)->order($bucket . ' ASC');
        $db->setQuery($query);

        $values = array();
        foreach ((array) $db->loadObjectList() as $row) {
            if (!empty($row->bucket)) {
                $values[(string) $row->bucket] = (int) $row->num;
            }
        }

        return $values;
    }

    private function fillTimeline(array $values, $filters)
    {
        return $this->fillTimelineValues($values, $filters, true);
    }

    private function fillTimelineValues(array $values, $filters, $integer)
    {
        $timeZone = new DateTimeZone(JemHelper::getJoomlaTimeZoneName());
        $cursor = new DateTimeImmutable($filters->date_from, $timeZone);
        $last = new DateTimeImmutable($filters->date_to, $timeZone);
        if ($filters->group === 'week') {
            $cursor = $cursor->modify('monday this week');
        } elseif ($filters->group === 'month') {
            $cursor = $cursor->modify('first day of this month');
        } elseif ($filters->group === 'year') {
            $cursor = $cursor->setDate((int) $cursor->format('Y'), 1, 1);
        }

        $points = array();
        while ($cursor <= $last) {
            $key = $cursor->format('Y-m-d');
            $points[] = array(
                'key' => $key,
                'label' => $this->bucketLabel($cursor, $filters->group),
                'value' => $integer ? (int) ($values[$key] ?? 0) : round((float) ($values[$key] ?? 0), 2),
            );
            if ($filters->group === 'day') {
                $cursor = $cursor->modify('+1 day');
            } elseif ($filters->group === 'week') {
                $cursor = $cursor->modify('+1 week');
            } elseif ($filters->group === 'month') {
                $cursor = $cursor->modify('+1 month');
            } else {
                $cursor = $cursor->modify('+1 year');
            }
        }

        return $points;
    }

    private function bucketExpression($dateSql, $group)
    {
        switch ($group) {
            case 'day':
                return 'DATE(' . $dateSql . ')';
            case 'week':
                return 'DATE_SUB(DATE(' . $dateSql . '), INTERVAL WEEKDAY(' . $dateSql . ') DAY)';
            case 'year':
                return "CONCAT(YEAR(" . $dateSql . "), '-01-01')";
            case 'month':
            default:
                return "DATE_FORMAT(" . $dateSql . ", '%Y-%m-01')";
        }
    }

    private function bucketLabel(DateTimeImmutable $date, $group)
    {
        if ($group === 'year') {
            return $date->format('Y');
        }
        if ($group === 'month') {
            return $date->format('M Y');
        }

        return $date->format('d M');
    }

    private function addStateFilter(array &$where, $filters)
    {
        if ($filters->state !== '') {
            $where[] = 'published = ' . (int) $filters->state;
        }
    }

    private function parseDate($value, DateTimeImmutable $fallback, DateTimeZone $timeZone)
    {
        $value = trim((string) $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timeZone);

        return $date && $date->format('Y-m-d') === $value ? $date : $fallback;
    }

    private function earliestDate(DateTimeImmutable $fallback)
    {
        $db = $this->getDatabase();
        $sources = array(
            array('#__jem_events', 'created'),
            array('#__jem_venues', 'created'),
            array('#__jem_categories', 'created_time'),
            array('#__jem_types', 'created'),
            array('#__jem_attachments', 'created'),
            array('#__jem_register', 'created'),
        );
        $earliest = null;
        foreach ($sources as [$table, $field]) {
            $query = $db->getQuery(true)
                ->select('MIN(' . $db->quoteName($field) . ')')
                ->from($db->quoteName($table));
            $db->setQuery($query);
            $value = $db->loadResult();
            if ($value && ($earliest === null || (string) $value < $earliest)) {
                $earliest = (string) $value;
            }
        }

        try {
            return $earliest
                ? new DateTimeImmutable(substr($earliest, 0, 10), $fallback->getTimezone())
                : $fallback->modify('-11 months')->modify('first day of this month');
        } catch (Throwable $e) {
            return $fallback->modify('-11 months')->modify('first day of this month');
        }
    }

    private function titleKey($metric)
    {
        $keys = array(
            'events' => 'COM_JEM_MAIN_EVENT_STATS',
            'venues' => 'COM_JEM_MAIN_VENUE_STATS',
            'categories' => 'COM_JEM_MAIN_CATEGORY_STATS',
            'types' => 'COM_JEM_MAIN_TYPE_STATS',
            'images' => 'COM_JEM_MAIN_IMAGE_STATS',
            'attachments' => 'COM_JEM_MAIN_ATTACHMENT_STATS',
            'registrations' => 'COM_JEM_MAIN_REGISTRATION_STATS',
        );

        return $keys[$metric];
    }

    private function seriesKey($metric)
    {
        return $metric === 'registrations'
            ? 'COM_JEM_STATISTICS_SERIES_REGISTRATIONS'
            : ($metric === 'images'
                ? 'COM_JEM_STATISTICS_SERIES_IMAGES'
                : 'COM_JEM_STATISTICS_SERIES_CREATED');
    }
}
