<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Authoritative permission for assigning a Venue to an Event.
 */
final class JemVenueAccess
{
    public static function canUse($user, $venueId)
    {
        if (class_exists('JemAclMigrationStatus', false) && JemAclMigrationStatus::pending()) {
            return false;
        }
        $venueId = (int) $venueId;
        if ($venueId === 0) {
            return true;
        }

        if (empty($user->id) || $user->get('guest', 0)) {
            return false;
        }

        if ($user->authorise('core.admin', 'com_jem')) {
            return self::exists($venueId);
        }

        return self::exists($venueId)
            && $user->authorise('jem.venues.use', 'com_jem.venue.' . $venueId);
    }

    /**
     * Check a venue assignment while allowing an unchanged stored association.
     *
     * The caller must independently authorise editing the event. The existing
     * event must come from the database, never from submitted form values.
     * Copies create a new association and always require venue-use permission.
     *
     * @param object $user Acting JEM user.
     * @param int $venueId Requested venue ID, or zero to clear the association.
     * @param object|null $storedEvent Successfully loaded event before this save.
     * @param bool $createsNewEvent Whether this operation creates or copies an event.
     * @return bool Whether the venue association may be saved.
     */
    public static function canAssignToEvent($user, int $venueId, ?object $storedEvent, bool $createsNewEvent): bool
    {
        $retainsVenue = !$createsNewEvent && !empty($storedEvent->id)
            && $venueId > 0 && $venueId === (int) ($storedEvent->locid ?? 0);

        if (!$retainsVenue) {
            return self::canUse($user, $venueId);
        }

        if (class_exists('JemAclMigrationStatus', false) && JemAclMigrationStatus::pending()) {
            return false;
        }

        if (empty($user->id) || $user->get('guest', 0)) {
            return false;
        }

        // Unpublished venues may be retained; physically deleted rows may not.
        return self::exists($venueId);
    }

    public static function getAuthorisedIds($user, $publishedOnly = true)
    {
        if (class_exists('JemAclMigrationStatus', false) && JemAclMigrationStatus::pending()) {
            return array();
        }
        if (empty($user->id) || $user->get('guest', 0)) {
            return array();
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__jem_venues'));
        if ($publishedOnly) {
            $query->where($db->quoteName('published') . ' = 1');
        }
        $db->setQuery($query);

        return array_values(array_filter(array_map('intval', (array) $db->loadColumn()), static function ($venueId) use ($user) {
            return $user->authorise('core.admin', 'com_jem')
                || $user->authorise('jem.venues.use', 'com_jem.venue.' . $venueId);
        }));
    }

    private static function exists($venueId)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->setQuery(
            $db->getQuery(true)
                ->select('1')
                ->from($db->quoteName('#__jem_venues'))
                ->where($db->quoteName('id') . ' = ' . (int) $venueId)
        );

        return (bool) $db->loadResult();
    }
}
