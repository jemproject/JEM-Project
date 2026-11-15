<?php
/** @package JEM @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL */
defined('_JEXEC') or die;

/** Event-scoped access for the frontend reservation editor. */
final class JemAttendeeEditorAccess
{
    /**
     * Check the same event-edit permission used by frontend Manage Attendees.
     * @param object $db Database connection.
     * @param object $actor Authenticated JEM user.
     * @param int $eventId Authoritative event ID, never an alternative posted target.
     * @return void Throws before personal reservation data is returned.
     */
    public static function assertEvent(object $db, object $actor, int $eventId): void
    {
        if (empty($actor->id) || $eventId < 1 || !method_exists($actor, 'can')) {
            throw new DomainException('JERROR_ALERTNOAUTHOR', 403);
        }
        $event = $db->setQuery('SELECT id,created_by FROM #__jem_events WHERE id=' . $eventId)->loadObject();
        if (!$event || !$actor->can('edit', 'event', $eventId, (int) $event->created_by)) {
            throw new DomainException('JERROR_ALERTNOAUTHOR', 403);
        }
    }

    /**
     * Resolve a reservation's event and check access before reading its details.
     * @param object $db Database connection.
     * @param object $actor Authenticated JEM user.
     * @param int $id Existing registration ID.
     * @return int Authoritative event ID.
     */
    public static function assertReservation(object $db, object $actor, int $id): int
    {
        if (empty($actor->id) || $id < 1) {
            throw new DomainException('JERROR_ALERTNOAUTHOR', 403);
        }
        $eventId = (int) $db->setQuery('SELECT event FROM #__jem_register WHERE id=' . $id)->loadResult();
        self::assertEvent($db, $actor, $eventId);
        return $eventId;
    }
}
