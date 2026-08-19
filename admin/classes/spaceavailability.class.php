<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Authoritative UTC availability checks for physical Venue Spaces.
 */
final class JemSpaceAvailabilityService
{
    public static function intervalsOverlap(
        string $firstStart,
        string $firstEnd,
        string $secondStart,
        string $secondEnd
    ): bool {
        return $firstStart < $secondEnd && $firstEnd > $secondStart;
    }

    /**
     * Lock selected Spaces and reject overlapping Event intervals.
     *
     * @return array<int,array<string,mixed>> Accepted override audit rows.
     */
    public static function assertAvailable(
        array $eventData,
        array $capacityContext,
        int $eventId,
        bool $override,
        string $reason,
        bool $overrideAuthorised
    ): array {
        if (empty($capacityContext['physical_active'])) {
            return array();
        }
        $spaceIds = array_values(array_unique(array_filter(array_map(
            static fn (array $row): int => (int) ($row['venue_space_id'] ?? 0),
            (array) ($capacityContext['assignments'] ?? array())
        ))));
        if (!$spaceIds) {
            return array();
        }

        $event = (object) $eventData;
        JemHelper::setEventUtcDates($event);
        $startUtc = (string) ($event->start_utc ?? '');
        $endUtc = (string) ($event->end_utc ?? '');
        if ($startUtc === '' || $endUtc === '' || $startUtc >= $endUtc) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_SPACE_CONFLICT_INVALID_INTERVAL'));
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        sort($spaceIds, SORT_NUMERIC);
        $db->setQuery(
            'SELECT ' . $db->quoteName('id')
            . ' FROM ' . $db->quoteName('#__jem_venue_spaces')
            . ' WHERE ' . $db->quoteName('id') . ' IN (' . implode(',', $spaceIds) . ')'
            . ' ORDER BY ' . $db->quoteName('id') . ' FOR UPDATE'
        );
        $lockedIds = array_map('intval', (array) $db->loadColumn());
        if ($lockedIds !== $spaceIds) {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_PRICING_ERROR_CONFIGURATION_SELECTION'));
        }

        $query = $db->getQuery(true)
            ->select(array(
                'e.id AS event_id', 'e.title AS event_title', 'e.start_utc', 'e.end_utc',
                's.id AS venue_space_id', 's.name AS space_name', 'v.venue AS venue_name',
            ))
            ->from($db->quoteName('#__jem_event_space_layouts', 'esl'))
            ->join('INNER', $db->quoteName('#__jem_events', 'e') . ' ON e.id = esl.event_id')
            ->join('INNER', $db->quoteName('#__jem_venue_spaces', 's') . ' ON s.id = esl.venue_space_id')
            ->join('INNER', $db->quoteName('#__jem_venues', 'v') . ' ON v.id = s.venue_id')
            ->where('esl.venue_space_id IN (' . implode(',', $spaceIds) . ')')
            ->where('e.start_utc IS NOT NULL')
            ->where('e.end_utc IS NOT NULL')
            ->where('e.start_utc < ' . $db->quote($endUtc))
            ->where('e.end_utc > ' . $db->quote($startUtc))
            ->where('e.published <> -2')
            ->where('(e.event_status IS NULL OR e.event_status <> ' . $db->quote('cancelled') . ')')
            ->order('e.start_utc ASC, e.id ASC, s.id ASC');
        $parentEventId = (int) ($event->parent_event_id ?? 0);
        if ($eventId > 0) {
            $query->where('e.id <> ' . $eventId);
            // A programme container and its direct items may intentionally use
            // the same physical Space during the containing interval. Sibling
            // programme items are still checked against each other.
            $query->where('(e.parent_event_id IS NULL OR e.parent_event_id <> ' . $eventId . ')');
        }
        if ($parentEventId > 0) {
            $query->where('e.id <> ' . $parentEventId);
        }
        $db->setQuery($query);
        $conflicts = (array) $db->loadAssocList();
        if (!$conflicts) {
            return array();
        }

        $reason = trim(strip_tags($reason));
        if (!$override) {
            $labels = array_map(static function (array $conflict): string {
                return sprintf(
                    '%s (#%d) - %s / %s [%s - %s UTC]',
                    (string) $conflict['event_title'],
                    (int) $conflict['event_id'],
                    (string) $conflict['venue_name'],
                    (string) $conflict['space_name'],
                    (string) $conflict['start_utc'],
                    (string) $conflict['end_utc']
                );
            }, $conflicts);
            throw new RuntimeException(Text::sprintf('COM_JEM_EVENT_SPACE_CONFLICT_FOUND', implode('; ', $labels)));
        }
        if (!$overrideAuthorised) {
            throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'));
        }
        if ($reason === '') {
            throw new InvalidArgumentException(Text::_('COM_JEM_EVENT_SPACE_CONFLICT_REASON_REQUIRED'));
        }

        return array_map(static function (array $conflict) use ($startUtc, $endUtc, $reason): array {
            return array(
                'conflicting_event_id' => (int) $conflict['event_id'],
                'venue_space_id' => (int) $conflict['venue_space_id'],
                'requested_start_utc' => $startUtc,
                'requested_end_utc' => $endUtc,
                'conflicting_start_utc' => (string) $conflict['start_utc'],
                'conflicting_end_utc' => (string) $conflict['end_utc'],
                'reason' => $reason,
            );
        }, $conflicts);
    }

    /**
     * Persist accepted exceptions append-only after the Event has an ID.
     */
    public static function saveOverrides(int $eventId, array $rows): void
    {
        if ($eventId < 1 || !$rows) {
            return;
        }
        $db = Factory::getContainer()->get('DatabaseDriver');
        $identity = Factory::getApplication()->getIdentity();
        $created = Factory::getDate()->toSql();
        foreach ($rows as $row) {
            $audit = (object) array_merge($row, array(
                'event_id' => $eventId,
                'created' => $created,
                'created_by' => (int) ($identity->id ?? 0),
            ));
            $db->insertObject('#__jem_space_conflict_overrides', $audit, 'id');
        }
    }
}
