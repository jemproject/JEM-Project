<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

/**
 * Authoritative quantity rules for registration changes.
 */
final class JemRegistrationQuantity
{
    public const REGISTER = 'register';
    public const INCREASE = 'increase';
    public const DECREASE = 'decrease';
    public const CANCEL = 'cancel';

    private const MAX_DATABASE_INTEGER = 2147483647;

    /**
     * Parse an optional request quantity without accepting signs, decimals,
     * arrays, exponent notation or values outside the database integer range.
     */
    public static function parseOptional($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return self::parse($value);
    }

    /**
     * Parse a quantity which must be represented as a non-negative integer.
     */
    public static function parse($value): int
    {
        if (is_int($value)) {
            $quantity = $value;
        } elseif (is_string($value)) {
            $value = trim($value);
            if ($value === '' || preg_match('/^[0-9]+$/D', $value) !== 1) {
                throw new InvalidArgumentException('Invalid registration quantity.');
            }

            if (strlen($value) > 10
                || (strlen($value) === 10 && strcmp($value, (string) self::MAX_DATABASE_INTEGER) > 0)) {
                throw new InvalidArgumentException('Registration quantity is outside the supported range.');
            }

            $quantity = (int) $value;
        } else {
            throw new InvalidArgumentException('Invalid registration quantity.');
        }

        if ($quantity < 0 || $quantity > self::MAX_DATABASE_INTEGER) {
            throw new InvalidArgumentException('Registration quantity is outside the supported range.');
        }

        return $quantity;
    }

    /**
     * Sum a flat set of optional request quantities without integer overflow.
     */
    public static function sumOptional(array $values): int
    {
        $total = 0;
        foreach ($values as $value) {
            $total = self::safeAdd($total, self::parseOptional($value));
        }

        return $total;
    }

    /**
     * Parse either one shared manager quantity or the per-user mapping emitted
     * by the attendee selector.
     */
    public static function parseManagerSelection($value, array $userIds): object
    {
        if (!is_string($value) && !is_int($value)) {
            throw new InvalidArgumentException('Invalid attendee quantity input.');
        }

        $raw = trim((string) $value);
        if (strpos($raw, ':') === false) {
            return (object) array(
                'places' => self::parseOptional($raw),
                'byUser' => array(),
            );
        }

        $allowedIds = array_values(array_unique(array_map(static function ($userId) {
            return self::parse($userId);
        }, $userIds)));
        $byUser = array();

        foreach (explode(',', $raw) as $pair) {
            $parts = array_map('trim', explode(':', $pair, 2));
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                throw new InvalidArgumentException('Invalid attendee quantity mapping.');
            }

            $userId = self::parse($parts[0]);
            $quantity = self::parse($parts[1]);
            if ($userId < 1 || isset($byUser[$userId]) || !in_array($userId, $allowedIds, true)) {
                throw new InvalidArgumentException('Invalid attendee quantity mapping.');
            }
            $byUser[$userId] = $quantity;
        }

        foreach ($allowedIds as $userId) {
            if (!array_key_exists($userId, $byUser)) {
                throw new InvalidArgumentException('Missing attendee quantity.');
            }
        }

        return (object) array('places' => 0, 'byUser' => $byUser);
    }

    /**
     * Resolve one classic frontend response into one explicit operation and
     * an authoritative resulting total.
     */
    public static function resolveResponse($requestedStatus, $addPlaces, $cancelPlaces, $registration, $event): object
    {
        $requestedStatus = (int) $requestedStatus;
        $addPlaces = self::parse($addPlaces);
        $cancelPlaces = self::parse($cancelPlaces);
        $hasRegistration = is_object($registration);
        $currentPlaces = $hasRegistration
            ? self::parse($registration->places ?? 0)
            : 0;
        $currentStatus = $hasRegistration
            ? JemRegistrationTransition::logicalStatus($registration)
            : null;

        if ($requestedStatus === JemRegistrationTransition::ATTENDING) {
            if ($addPlaces < 1) {
                throw new InvalidArgumentException('A registration increase requires a positive quantity.');
            }

            $isActive = in_array($currentStatus, array(
                JemRegistrationTransition::ATTENDING,
                JemRegistrationTransition::WAITING_LIST,
            ), true);
            $basePlaces = $isActive ? $currentPlaces : 0;
            $places = self::safeAdd($basePlaces, $addPlaces);
            self::assertActiveTotal($places, $event);

            return (object) array(
                'operation' => $isActive ? self::INCREASE : self::REGISTER,
                'quantity'  => $addPlaces,
                'places'    => $places,
                'status'    => $currentStatus === JemRegistrationTransition::WAITING_LIST
                    ? JemRegistrationTransition::WAITING_LIST
                    : JemRegistrationTransition::ATTENDING,
            );
        }

        if ($requestedStatus !== JemRegistrationTransition::NOT_ATTENDING || !$hasRegistration) {
            throw new InvalidArgumentException('Invalid registration quantity operation.');
        }

        if ($currentPlaces === 0) {
            if ($cancelPlaces !== 0) {
                throw new InvalidArgumentException('The cancellation quantity exceeds the current registration.');
            }

            return (object) array(
                'operation' => self::CANCEL,
                'quantity'  => 0,
                'places'    => 0,
                'status'    => JemRegistrationTransition::NOT_ATTENDING,
            );
        }

        if ($cancelPlaces < 1 || $cancelPlaces > $currentPlaces) {
            throw new InvalidArgumentException('The cancellation quantity is invalid.');
        }

        $places = $currentPlaces - $cancelPlaces;
        if ($places > 0 && in_array($currentStatus, array(
            JemRegistrationTransition::ATTENDING,
            JemRegistrationTransition::WAITING_LIST,
        ), true)) {
            self::assertActiveTotal($places, $event);
        }

        return (object) array(
            'operation' => $places > 0 ? self::DECREASE : self::CANCEL,
            'quantity'  => $cancelPlaces,
            'places'    => $places,
            'status'    => $places > 0 ? $currentStatus : JemRegistrationTransition::NOT_ATTENDING,
        );
    }

    /**
     * Validate a row immediately before persistence.
     */
    public static function assertStoredRow($registration, $event): void
    {
        $places = self::parse($registration->places ?? 0);
        $status = JemRegistrationTransition::logicalStatus($registration);

        if (in_array($status, array(
            JemRegistrationTransition::ATTENDING,
            JemRegistrationTransition::WAITING_LIST,
        ), true)) {
            self::assertActiveTotal($places, $event);
        }
    }

    /**
     * Validate an active total against the configured per-user limits.
     */
    public static function assertActiveTotal($places, $event): void
    {
        $places = self::parse($places);
        $minimum = max(1, (int) ($event->minbookeduser ?? 1));
        $maximum = max(0, (int) ($event->maxbookeduser ?? 0));

        if ($places < $minimum) {
            throw new InvalidArgumentException('Registration quantity is below the configured minimum.');
        }
        if ($maximum > 0 && $places > $maximum) {
            throw new InvalidArgumentException('Registration quantity exceeds the configured maximum.');
        }
    }

    private static function safeAdd(int $left, int $right): int
    {
        if ($right > self::MAX_DATABASE_INTEGER - $left) {
            throw new InvalidArgumentException('Registration quantity is outside the supported range.');
        }

        return $left + $right;
    }
}
