<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\UserFactoryInterface;

/** Recipient view-access checks scoped to one invitation operation, never the operator. */
final class JemInvitationAccess
{
    public const NO_ACCESS = 'COM_JEM_INVITATION_RECIPIENT_NO_ACCESS';
    public const ACCESS_CHANGED = 'COM_JEM_INVITATION_ACCESS_CHANGED';

    private array $records = [];
    private array $categories = [];
    private array $users = [];

    /** @param object $db Joomla database connection; caches live only for this operation. */
    public function __construct(private readonly object $db)
    {
    }

    /**
     * Validate an active Joomla recipient against the event's view-access requirements.
     * External token holders are handled explicitly by callers, never as Joomla guests.
     *
     * @param int $eventId Event to invite to or accept.
     * @param int $userId Intended Joomla recipient, not the current operator.
     * @return string Empty when allowed, otherwise an explanation language key.
     */
    public function message(int $eventId, int $userId): string
    {
        if ($userId < 1) {
            return self::NO_ACCESS;
        }
        if (!isset($this->users[$userId])) {
            $this->users[$userId] = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);
        }
        $user = $this->users[$userId];
        if ((int) $user->id !== $userId || !empty($user->block)
            || !in_array((string) ($user->activation ?? ''), ['', '0'], true)) {
            return self::NO_ACCESS;
        }
        $levels = array_map('intval', $user->getAuthorisedViewLevels());
        $seen = [];
        $current = $eventId;
        while ($current > 0 && count($seen) < 100) {
            if (isset($seen[$current])) {
                return self::NO_ACCESS;
            }
            $seen[$current] = true;
            $event = $this->record('events', $current);
            if (!$event || !in_array((int) $event->access, $levels, true)
                || !$this->hasCategory($current, $levels)
                || !$this->hasType((int) ($event->type_id ?? 0), $levels)
                || !$this->hasVenue((int) ($event->locid ?? 0), $levels, $user, $current !== $eventId)) {
                return self::NO_ACCESS;
            }
            if ($current !== $eventId && !JemHelper::isEventPublishedNow($event)
                && (int) $event->published !== 2 && !$this->canManage($user, 'event', $event)) {
                return self::NO_ACCESS;
            }
            $current = (int) ($event->parent_event_id ?? 0);
        }
        return $eventId > 0 && $current === 0 ? '' : self::NO_ACCESS;
    }

    /**
     * Filter selectable users before rendering, preserving the original row objects/order.
     *
     * @param array $users Candidate user rows containing id.
     * @param array $eventIds All sessions included in the invitation.
     * @return array Eligible rows only; an empty event selection has no candidates.
     */
    public function filterUsers(array $users, array $eventIds): array
    {
        if (!$eventIds) {
            return [];
        }
        return array_values(array_filter($users, function ($user) use ($eventIds): bool {
            foreach ($eventIds as $id) {
                if ($this->message((int) $id, (int) $user->id) !== '') {
                    return false;
                }
            }
            return true;
        }));
    }

    /** @param string $kind Internal JEM table suffix. @param int $id Primary key. @return object|null Record. */
    private function record(string $kind, int $id): ?object
    {
        $key = $kind . ':' . $id;
        if (!array_key_exists($key, $this->records)) {
            $this->records[$key] = $this->db->setQuery('SELECT * FROM #__jem_' . $kind . ' WHERE id=' . $id)->loadObject();
        }
        return $this->records[$key];
    }

    /** @param int $eventId Event. @param array $levels Recipient view levels. @return bool At least one visible category. */
    private function hasCategory(int $eventId, array $levels): bool
    {
        if (!isset($this->categories[$eventId])) {
            $this->categories[$eventId] = $this->db->setQuery('SELECT c.access FROM #__jem_categories c'
                . ' INNER JOIN #__jem_cats_event_relations r ON r.catid=c.id'
                . ' WHERE c.published=1 AND r.itemid=' . $eventId)->loadColumn();
        }
        return (bool) array_intersect($levels, array_map('intval', $this->categories[$eventId]));
    }

    /** @param int $id Optional event type. @param array $levels Recipient levels. @return bool Type is visible. */
    private function hasType(int $id, array $levels): bool
    {
        if ($id < 1) {
            return true;
        }
        $type = $this->record('types', $id);
        return $type && (int) $type->entity === 1 && (int) $type->published === 1
            && in_array((int) $type->access, $levels, true);
    }

    /**
     * Apply event-detail venue ACL and the stricter ancestor-venue visibility checks.
     *
     * @param int $id Optional physical venue.
     * @param array $levels Recipient view levels.
     * @param object $user Recipient identity.
     * @param bool $ancestor Whether this venue belongs to a programme ancestor.
     * @return bool Venue constraints permit event access.
     */
    private function hasVenue(int $id, array $levels, object $user, bool $ancestor): bool
    {
        $seen = [];
        while ($id > 0 && count($seen) < 100) {
            if (isset($seen[$id])) {
                return false;
            }
            $seen[$id] = true;
            $venue = $this->record('venues', $id);
            if (!$venue) {
                return !$ancestor; // Event detail's LEFT JOIN permits an absent direct venue.
            }
            $allowed = $levels;
            if (!$ancestor) {
                $locked = json_decode((string) (JemHelper::config()->access_level_locked_venues ?? '["1"]'), true);
                if (is_array($locked) && $locked !== ['1']) {
                    $allowed = array_merge($levels, array_map('intval', $locked));
                }
            }
            if (!in_array((int) $venue->access, $allowed, true)
                || ($ancestor && (int) $venue->published !== 1 && !$this->canManage($user, 'venue', $venue))) {
                return false;
            }
            $id = (int) ($venue->parent_venue_id ?? 0);
            $ancestor = true;
        }
        return $id === 0;
    }

    /** @param object $user Recipient. @param string $kind Event or venue. @param object $row Ancestor. @return bool Existing management exception. */
    private function canManage(object $user, string $kind, object $row): bool
    {
        $jemUser = JemFactory::getUser((int) $user->id);
        return $jemUser->can('edit', $kind, (int) $row->id, (int) ($row->created_by ?? 0))
            || $jemUser->can('publish', $kind, (int) $row->id, (int) ($row->created_by ?? 0));
    }
}
