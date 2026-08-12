<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;

/**
 * Central policy for attendee registration status transitions.
 *
 * A deleted registration is deliberately not represented as another status.
 * Logical status 2 maps to the stored combination status=1, waiting=1.
 */
final class JemRegistrationTransition
{
    public const NOT_ATTENDING = -1;
    public const INVITED = 0;
    public const ATTENDING = 1;
    public const WAITING_LIST = 2;

    public static function isValidStatus($status)
    {
        if (!is_int($status) && !(is_string($status) && preg_match('/^-?\d+$/D', $status))) {
            return false;
        }

        return in_array((int) $status, array(
            self::NOT_ATTENDING,
            self::INVITED,
            self::ATTENDING,
            self::WAITING_LIST,
        ), true);
    }

    public static function logicalStatus($registration)
    {
        if (!is_object($registration)) {
            return null;
        }

        $status = (int) ($registration->status ?? self::INVITED);

        return $status === self::ATTENDING && !empty($registration->waiting)
            ? self::WAITING_LIST
            : $status;
    }

    public static function applyLogicalStatus($registration, $status)
    {
        if (!is_object($registration) || !self::isValidStatus($status)) {
            return false;
        }

        $status = (int) $status;
        $registration->status = $status === self::WAITING_LIST ? self::ATTENDING : $status;
        $registration->waiting = $status === self::WAITING_LIST ? 1 : 0;

        return true;
    }

    public static function activePlaces($registration)
    {
        return self::logicalStatus($registration) === self::ATTENDING
            ? max(1, (int) ($registration->places ?? 1))
            : 0;
    }

    public static function releasesCapacity($before, $after = null)
    {
        if (!is_object($before) || empty($before->event)) {
            return false;
        }

        if (is_object($after) && (int) ($after->event ?? 0) !== (int) $before->event) {
            return self::activePlaces($before) > 0;
        }

        return self::activePlaces($before) > self::activePlaces($after);
    }

    public static function create($before, $after, $actorId, $source)
    {
        $hasBefore = is_object($before);
        $hasAfter = is_object($after);
        $before = is_object($before) ? $before : new stdClass();
        $after = is_object($after) ? $after : new stdClass();
        $transition = new stdClass();
        $transition->registrationId = (int) ($after->id ?? $before->id ?? 0);
        $transition->eventId = (int) ($after->event ?? $before->event ?? 0);
        $transition->attendeeId = (int) ($after->uid ?? $before->uid ?? 0);
        $transition->oldStatus = $hasBefore ? self::logicalStatus($before) : null;
        $transition->newStatus = $hasAfter ? self::logicalStatus($after) : null;
        $transition->oldPlaces = $hasBefore ? max(1, (int) ($before->places ?? 1)) : null;
        $transition->newPlaces = $hasAfter ? max(1, (int) ($after->places ?? 1)) : null;
        $transition->actorId = (int) $actorId;
        $transition->source = preg_replace('/[^a-z0-9_.-]/i', '', (string) $source);
        $transition->notificationRequested = false;
        $transition->forced = false;
        $transition->changed = $transition->registrationId > 0
            && $transition->eventId > 0
            && $transition->newStatus !== null
            && $transition->oldStatus !== $transition->newStatus;
        $transition->capacityChanged = $transition->registrationId > 0
            && $transition->eventId > 0
            && $transition->oldPlaces !== null
            && $transition->newPlaces !== null
            && self::activePlaces($before) !== self::activePlaces($after);

        return $transition;
    }

    /**
     * Dispatch the existing public mailer events using one consistent mapping.
     *
     * Status -1 means that the registration remains stored as Not Attending.
     * onEventUserUnregistered is therefore reserved for actual row deletion.
     */
    public static function dispatchStatusMail($dispatcher, $registration, $transition, $userOnly = false, $force = false)
    {
        if (!is_object($registration) || !is_object($transition)) {
            return false;
        }

        if (!$force && empty($transition->changed)) {
            return false;
        }

        $registrationId = (int) ($registration->id ?? 0);
        $status = self::logicalStatus($registration);

        if ($registrationId < 1 || !self::isValidStatus($status)) {
            return false;
        }

        if ($status === self::WAITING_LIST || (int) ($transition->oldStatus ?? 0) === self::WAITING_LIST) {
            $dispatcher->triggerEvent('onUserOnOffWaitinglist', array($registrationId));
        } else {
            $dispatcher->triggerEvent(
                'onEventUserRegistered',
                array($registrationId, (int) ($registration->places ?? 0), (bool) $userOnly)
            );
        }

        $transition->notificationRequested = true;

        try {
            $factory = JPATH_SITE . '/components/com_jem/factory.php';
            if (!class_exists('JemReminderService', false) && is_file($factory)) {
                require_once $factory;
            }
            if (class_exists('JemReminderService', false)) {
                (new JemReminderService())->syncRegistration($registrationId, true);
            }
        } catch (Throwable $error) {
            if (method_exists('JemHelper', 'addLogEntry')) {
                JemHelper::addLogEntry(
                    'Could not synchronise reminders for registration #' . $registrationId . ': ' . $error->getMessage(),
                    __METHOD__,
                    Log::WARNING
                );
            }
        }

        return true;
    }

    public static function dispatchDeletionMail($dispatcher, $registration)
    {
        if (!is_object($registration) || empty($registration->event)) {
            return false;
        }

        try {
            $factory = JPATH_SITE . '/components/com_jem/factory.php';
            if (!class_exists('JemNotificationService', false) && is_file($factory)) {
                require_once $factory;
            }
            if (class_exists('JemNotificationService', false)) {
                (new JemNotificationService())->cancelPendingReminders((int) ($registration->id ?? 0));
            }
        } catch (Throwable $error) {
            if (method_exists('JemHelper', 'addLogEntry')) {
                JemHelper::addLogEntry(
                    'Could not cancel reminders for deleted registration #' . (int) ($registration->id ?? 0) . ': ' . $error->getMessage(),
                    __METHOD__,
                    Log::WARNING
                );
            }
        }

        $dispatcher->triggerEvent('onEventUserUnregistered', array((int) $registration->event, $registration));

        return true;
    }

    public static function dispatchAudit($dispatcher, array $transitions)
    {
        $changed = array_values(array_filter($transitions, static function ($transition) {
            return is_object($transition)
                && (!empty($transition->changed) || !empty($transition->capacityChanged));
        }));

        if (!$changed) {
            return false;
        }

        $ids = array_map(static function ($transition) {
            return (int) $transition->registrationId;
        }, $changed);
        $eventIds = array_unique(array_map(static function ($transition) {
            return (int) $transition->eventId;
        }, $changed));
        $status = count(array_unique(array_map(static function ($transition) {
            return (int) $transition->newStatus;
        }, $changed))) === 1 ? (int) $changed[0]->newStatus : null;
        $eventId = count($eventIds) === 1 ? (int) reset($eventIds) : 0;

        $dispatcher->triggerEvent(
            'onJemAfterAttendeeStatusChange',
            array($ids, $status, $eventId, $changed)
        );

        return true;
    }
}
