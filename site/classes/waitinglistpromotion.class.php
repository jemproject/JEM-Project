<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;

/**
 * Applies one waiting-list promotion policy to frontend and backend workflows.
 */
final class JemWaitingListPromotion
{
    public const MODE_AUTOMATIC = 'automatic';
    public const MODE_MANUAL = 'manual';
    public const STRATEGY_STRICT = 'strict';
    public const STRATEGY_FILL = 'fill';

    /**
     * Reconcile or manually promote waiting registrations for one event.
     *
     * The result defines success, promoted IDs, capacity before/after,
     * waiting counts, notification state and a machine-readable reason.
     */
    public static function promote($eventId, array $options = array())
    {
        $eventId = (int) $eventId;
        $mode = ($options['mode'] ?? self::MODE_AUTOMATIC) === self::MODE_MANUAL
            ? self::MODE_MANUAL
            : self::MODE_AUTOMATIC;
        $result = self::newResult($eventId, $mode);

        if ($eventId < 1) {
            $result->reason = 'invalid_event';
            return $result;
        }

        $jemsettings = JemHelper::config();
        $strategy = ($jemsettings->waitinglist_strategy ?? self::STRATEGY_STRICT) === self::STRATEGY_FILL
            ? self::STRATEGY_FILL
            : self::STRATEGY_STRICT;
        $result->strategy = $mode === self::MODE_AUTOMATIC ? $strategy : self::MODE_MANUAL;

        if ($mode === self::MODE_AUTOMATIC
            && !(int) ($jemsettings->waitinglist_automatic ?? 1)) {
            $result->success = true;
            $result->skipped = true;
            $result->reason = 'automatic_disabled';
            self::loadAvailability($result);
            return $result;
        }

        $selectedIds = self::normaliseIds($options['registrationIds'] ?? array());
        $excludedIds = self::normaliseIds($options['excludeIds'] ?? array());
        $notify = !array_key_exists('notify', $options) || (bool) $options['notify'];
        $force = !empty($options['force']);
        $actorId = (int) ($options['actorId'] ?? Factory::getApplication()->getIdentity()->id);
        $source = (string) ($options['source'] ?? ($mode === self::MODE_MANUAL ? 'manual' : 'automatic'));

        if ($mode === self::MODE_AUTOMATIC) {
            $source .= '.' . $strategy;
        }

        if ($mode === self::MODE_MANUAL && !$selectedIds) {
            $result->reason = 'no_selection';
            return $result;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        try {
            $db->transactionStart();

            $query = $db->getQuery(true)
                ->select(array('maxplaces', 'waitinglist', 'reservedplaces'))
                ->from($db->quoteName('#__jem_events'))
                ->where($db->quoteName('id') . ' = ' . $eventId);
            $db->setQuery((string) $query . ' FOR UPDATE');
            $event = $db->loadObject();

            if (!$event) {
                throw new RuntimeException('event_not_found');
            }

            if (!(int) $event->waitinglist || (int) $event->maxplaces < 1) {
                $db->transactionCommit();
                $result->success = true;
                $result->skipped = true;
                $result->reason = 'waiting_list_unavailable';
                return $result;
            }

            $query = $db->getQuery(true)
                ->select(array('id', 'event', 'uid', 'status', 'waiting', 'places', 'uregdate'))
                ->from($db->quoteName('#__jem_register'))
                ->where($db->quoteName('event') . ' = ' . $eventId)
                ->where($db->quoteName('status') . ' = 1')
                ->order($db->quoteName('uregdate') . ' ASC')
                ->order($db->quoteName('id') . ' ASC');
            $db->setQuery((string) $query . ' FOR UPDATE');
            $registrations = (array) $db->loadObjectList();

            $registeredPlaces = (int) $event->reservedplaces;
            $waiting = array();

            foreach ($registrations as $registration) {
                $registration->places = max(1, (int) $registration->places);

                if ((int) $registration->waiting === 1) {
                    $waiting[(int) $registration->id] = $registration;
                } else {
                    $registeredPlaces += $registration->places;
                }
            }

            $available = max(0, (int) $event->maxplaces - $registeredPlaces);
            $result->availableBefore = $available;
            $result->waitingBefore = count($waiting);

            if ($mode === self::MODE_MANUAL) {
                if (array_diff($selectedIds, array_keys($waiting))) {
                    throw new RuntimeException('selection_not_waiting');
                }

                $candidates = array_values(array_intersect_key($waiting, array_flip($selectedIds)));
                $required = array_sum(array_map(static function ($registration) {
                    return (int) $registration->places;
                }, $candidates));

                if (!$force && $required > $available) {
                    throw new RuntimeException('capacity_exceeded');
                }

                $promoted = $candidates;
            } else {
                $candidates = array_values(array_diff_key($waiting, array_flip($excludedIds)));
                $promoted = self::selectForAvailablePlaces($candidates, $available, $strategy);
            }

            if (!$promoted) {
                $db->transactionCommit();
                $result->success = true;
                $result->availableAfter = $available;
                $result->waitingAfter = count($waiting);
                $result->reason = 'nothing_to_promote';
                return $result;
            }

            $promotedIds = array_map(static function ($registration) {
                return (int) $registration->id;
            }, $promoted);
            $usedPlaces = array_sum(array_map(static function ($registration) {
                return (int) $registration->places;
            }, $promoted));

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__jem_register'))
                ->set($db->quoteName('waiting') . ' = 0')
                ->where($db->quoteName('event') . ' = ' . $eventId)
                ->where($db->quoteName('status') . ' = 1')
                ->where($db->quoteName('waiting') . ' = 1')
                ->where($db->quoteName('id') . ' IN (' . implode(',', $promotedIds) . ')');
            $db->setQuery($query);
            $db->execute();
            $db->transactionCommit();
            Factory::getCache('com_jem')->clean();

            // The state change is committed before external notifications.
            // A mailer failure must never report that the promotion was rolled back.
            $result->success = true;
            $result->promotedIds = $promotedIds;
            $result->promotedPlaces = $usedPlaces;
            $result->availableAfter = $force ? $available - $usedPlaces : max(0, $available - $usedPlaces);
            $result->waitingAfter = count($waiting) - count($promotedIds);
            $result->notified = $notify;
            $result->forced = $force;

            $transitions = array();

            foreach ($promoted as $before) {
                $after = clone $before;
                $after->waiting = 0;
                $transition = JemRegistrationTransition::create($before, $after, $actorId, $source);
                $transition->forced = $force;
                $transitions[] = $transition;
            }

            try {
                PluginHelper::importPlugin('jem');
                PluginHelper::importPlugin('actionlog', 'jem');
                $dispatcher = JemFactory::getDispatcher();

                if ($notify) {
                    foreach ($promoted as $index => $before) {
                        $after = clone $before;
                        $after->waiting = 0;
                        JemRegistrationTransition::dispatchStatusMail($dispatcher, $after, $transitions[$index]);
                    }
                }

                JemRegistrationTransition::dispatchAudit($dispatcher, $transitions);
            } catch (Throwable $notificationError) {
                $result->reason = 'notification_failed';
                $result->notificationError = $notificationError->getMessage();
            }

            return $result;
        } catch (Throwable $e) {
            try {
                $db->transactionRollback();
            } catch (Throwable $rollbackError) {
                // Preserve the original failure reason.
            }

            $result->reason = $e->getMessage();
            return $result;
        }
    }

    public static function selectForAvailablePlaces(array $registrations, $available, $strategy = self::STRATEGY_STRICT)
    {
        $available = max(0, (int) $available);
        $strategy = $strategy === self::STRATEGY_FILL ? self::STRATEGY_FILL : self::STRATEGY_STRICT;
        $selected = array();

        foreach ($registrations as $registration) {
            $places = max(1, (int) ($registration->places ?? 1));

            if ($places <= $available) {
                $selected[] = $registration;
                $available -= $places;
            } elseif ($strategy === self::STRATEGY_STRICT) {
                // Preserve FIFO fairness: do not let a later registration
                // overtake the first waiting request that cannot yet be met.
                break;
            }
        }

        return $selected;
    }

    public static function availability($eventId)
    {
        $result = self::newResult((int) $eventId, 'status');
        self::loadAvailability($result);
        $result->success = (int) $eventId > 0;
        return $result;
    }

    private static function loadAvailability($result)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array(
                'e.maxplaces',
                'e.waitinglist',
                'e.reservedplaces',
                'COUNT(CASE WHEN r.status = 1 AND r.waiting = 1 THEN 1 END) AS waiting_count',
                'COALESCE(SUM(CASE WHEN r.status = 1 AND r.waiting = 0 THEN r.places ELSE 0 END), 0) AS registered_places',
            ))
            ->from($db->quoteName('#__jem_events', 'e'))
            ->join('LEFT', $db->quoteName('#__jem_register', 'r') . ' ON r.event = e.id')
            ->where('e.id = ' . (int) $result->eventId)
            ->group(array('e.maxplaces', 'e.waitinglist', 'e.reservedplaces'));
        $db->setQuery($query);
        $row = $db->loadObject();

        if ($row) {
            $result->availableBefore = max(0, (int) $row->maxplaces - (int) $row->reservedplaces - (int) $row->registered_places);
            $result->availableAfter = $result->availableBefore;
            $result->waitingBefore = (int) $row->waiting_count;
            $result->waitingAfter = $result->waitingBefore;
            $result->waitingListEnabled = (bool) $row->waitinglist;
            $result->maxPlaces = (int) $row->maxplaces;
        }
    }

    private static function normaliseIds($ids)
    {
        $ids = array_map('intval', (array) $ids);
        return array_values(array_unique(array_filter($ids)));
    }

    private static function newResult($eventId, $mode)
    {
        return (object) array(
            'success' => false,
            'eventId' => (int) $eventId,
            'mode' => (string) $mode,
            'promotedIds' => array(),
            'promotedPlaces' => 0,
            'availableBefore' => 0,
            'availableAfter' => 0,
            'waitingBefore' => 0,
            'waitingAfter' => 0,
            'notified' => false,
            'forced' => false,
            'skipped' => false,
            'reason' => '',
            'waitingListEnabled' => false,
            'maxPlaces' => 0,
            'strategy' => self::STRATEGY_STRICT,
        );
    }
}
