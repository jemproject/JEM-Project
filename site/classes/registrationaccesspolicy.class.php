<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Immutable result of a frontend self-registration access check.
 */
final class JemRegistrationAccessDecision
{
    private bool $allowed;
    private string $reason;
    private string $messageKey;

    public function __construct(bool $allowed, string $reason, string $messageKey = '')
    {
        $this->allowed = $allowed;
        $this->reason = $reason;
        $this->messageKey = $messageKey;
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getMessageKey(): string
    {
        return $this->messageKey;
    }
}

/**
 * Authoritative policy for a user changing their own event registration.
 *
 * Rendering a form is never treated as authorisation. Every selected event,
 * including each occurrence of a series, must pass this policy immediately
 * before the model prepares any database write.
 */
final class JemRegistrationAccessPolicy
{
    public const ALLOWED = 'allowed';
    public const INVALID_RESPONSE = 'invalid_response';
    public const AUTHENTICATION_REQUIRED = 'authentication_required';
    public const EVENT_NOT_VIEWABLE = 'event_not_viewable';
    public const REGISTRATION_DISABLED = 'registration_disabled';
    public const REGISTRATION_CLOSED = 'registration_closed';
    public const INVITATION_REQUIRED = 'invitation_required';
    public const REGISTRATION_NOT_FOUND = 'registration_not_found';
    public const CANCELLATION_CLOSED = 'cancellation_closed';

    /**
     * Resolve a registration window without trusting the rendered form.
     * The event start is always the final boundary, even in the unlimited mode.
     */
    public static function registrationWindowState(
        int $mode,
        int $eventStart,
        int $from,
        int $until,
        int $now
    ): string {
        if (!in_array($mode, array(1, 2), true)) {
            return 'disabled';
        }

        if ($eventStart > 0 && $now >= $eventStart) {
            return 'closed';
        }

        if ($mode === 1) {
            return 'open';
        }

        if ($from > 0 && $now < $from) {
            return 'not_started';
        }

        if ($until > 0 && $now >= $until) {
            return 'closed';
        }

        return 'open';
    }

    /**
     * Resolve a cancellation window. A configured deadline can only shorten,
     * never extend, the time before the event starts.
     */
    public static function unregistrationWindowState(
        int $mode,
        int $eventStart,
        int $until,
        int $now
    ): string {
        if (!in_array($mode, array(1, 2), true)) {
            return 'disabled';
        }

        if ($eventStart > 0 && $now >= $eventStart) {
            return 'closed';
        }

        if ($mode === 2 && ($until < 1 || $now >= $until)) {
            return 'closed';
        }

        return 'open';
    }

    /**
     * Decide whether a frontend user may register or cancel their own place.
     *
     * Positive responses create or update a reservation and therefore require
     * a currently published, viewable event plus all registration settings.
     * A cancellation only releases an existing row owned by the current user;
     * it remains possible after visibility changes so capacity is not trapped.
     */
    public static function decide(
        $user,
        $event,
        $settings,
        $registration,
        int $status,
        bool $publishedNow,
        bool $registrationOpen,
        bool $unregistrationOpen
    ): JemRegistrationAccessDecision {
        if (!in_array($status, array(-1, 1), true)) {
            return self::deny(self::INVALID_RESPONSE, 'COM_JEM_ATTENDEES_STATUS_UNKNOWN');
        }

        $userId = (int) self::value($user, 'id', 0);
        $guest = (bool) self::value($user, 'guest', $userId < 1);
        $blocked = (bool) self::value($user, 'block', false);

        if ($userId < 1 || $guest || $blocked) {
            return self::deny(self::AUTHENTICATION_REQUIRED, 'JERROR_ALERTNOAUTHOR');
        }

        if ($status < 0) {
            if (!self::hasOwnedRegistration($registration)) {
                return self::deny(self::REGISTRATION_NOT_FOUND, 'COM_JEM_REGISTRATION_NOT_FOUND');
            }

            $currentStatus = (int) self::value($registration, 'status', 0);
            if (in_array($currentStatus, array(1, 2), true) && !$unregistrationOpen) {
                return self::deny(self::CANCELLATION_CLOSED, 'COM_JEM_ERROR_ANNULATION_NOT_ALLOWED');
            }

            return self::allow();
        }

        if (!$publishedNow || !self::eventIsViewable($event)) {
            return self::deny(self::EVENT_NOT_VIEWABLE, 'COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND');
        }

        if (!self::registrationIsEnabled($settings, $event)) {
            return self::deny(self::REGISTRATION_DISABLED, 'COM_JEM_EVENT_REGISTRATION_CLOSED');
        }

        if (!$registrationOpen) {
            return self::deny(self::REGISTRATION_CLOSED, 'COM_JEM_EVENT_REGISTRATION_CLOSED');
        }

        $invitedOnly = (((int) self::value($event, 'reginvitedonly', 0)) & 1) !== 0;
        $invitationsEnabled = (int) self::value($settings, 'regallowinvitation', 0) > 0;
        if ($invitedOnly && $invitationsEnabled && !self::hasOwnedRegistration($registration)) {
            return self::deny(self::INVITATION_REQUIRED, 'COM_JEM_NOT_INVITED');
        }

        return self::allow();
    }

    private static function eventIsViewable($event): bool
    {
        $params = self::value($event, 'params');

        if (is_object($params) && method_exists($params, 'get')) {
            return (bool) $params->get('access-view', false);
        }

        return false;
    }

    private static function registrationIsEnabled($settings, $event): bool
    {
        $globalMode = (int) self::value($settings, 'showfroregistra', 0);
        $eventMode = (int) self::value($event, 'registra', 0);

        return $globalMode === 1
            || ($globalMode === 2 && (($eventMode & 1) !== 0 || ($eventMode & 2) !== 0));
    }

    private static function hasOwnedRegistration($registration): bool
    {
        return is_object($registration) && (int) self::value($registration, 'id', 0) > 0;
    }

    private static function value($source, string $name, $default = null)
    {
        if (!is_object($source)) {
            return $default;
        }

        if (method_exists($source, 'get')) {
            return $source->get($name, $default);
        }

        return property_exists($source, $name) ? $source->$name : $default;
    }

    private static function allow(): JemRegistrationAccessDecision
    {
        return new JemRegistrationAccessDecision(true, self::ALLOWED);
    }

    private static function deny(string $reason, string $messageKey): JemRegistrationAccessDecision
    {
        return new JemRegistrationAccessDecision(false, $reason, $messageKey);
    }
}
