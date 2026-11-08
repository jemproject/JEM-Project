<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

require_once __DIR__ . '/registrationquantity.class.php';
require_once __DIR__ . '/registrationtransition.class.php';

/** Calculate a cancellation without changing a registration or its history. */
final class JemRegistrationCancellation
{
    /**
     * Reduce one stored reservation by the requested quantity and area amounts.
     *
     * @param object $event Authoritative event and quantity limits.
     * @param object $registration Current owned registration.
     * @param int $quantity Places to cancel in this occurrence.
     * @param array $requestedAreas Cancellation quantities keyed by area identity.
     * @param array $currentAreas Stored quantities keyed by area identity.
     * @return array Calculated response, remaining allocation and cancellation summary.
     * @throws InvalidArgumentException When any requested reduction is inconsistent.
     */
    public static function reduce(
        object $event,
        object $registration,
        int $quantity,
        array $requestedAreas,
        array $currentAreas
    ): array {

        if (JemRegistrationTransition::logicalStatus($registration) === JemRegistrationTransition::NOT_ATTENDING) {
            throw new InvalidArgumentException('COM_JEM_CANCELLATION_REVIEW_CHANGED');
        }
        try {
            $response = JemRegistrationQuantity::resolveResponse(-1, 0, $quantity, $registration, $event);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException('COM_JEM_CANCELLATION_QUANTITY_INVALID', 0, $exception);
        }
        $remaining = (int) $response->places;
        $isArea = (string) ($event->capacity_mode ?? 'classic') === 'areas';
        $isPriced = in_array((string) ($registration->pricing_mode ?? 'classic'), array('single', 'multiple', 'priced'), true);
        $cancelledAreas = array();
        $allocations = array();
        if ($isArea) {
            foreach ($currentAreas as $key => $value) {
                $value = JemRegistrationQuantity::parse($value);
                if ($value > 0) {
                    $allocations[(string) $key] = $value;
                }
            }
            if (JemRegistrationQuantity::sumOptional($allocations) !== (int) $registration->places) {
                throw new InvalidArgumentException('COM_JEM_CANCELLATION_AREAS_INVALID');
            }
            if (!$requestedAreas && count($allocations) === 1) {
                $requestedAreas[array_key_first($allocations)] = $quantity;
            } elseif (!$requestedAreas && $remaining === 0) {
                $requestedAreas = $allocations;
            }
            foreach ($requestedAreas as $key => $value) {
                $value = JemRegistrationQuantity::parse($value);
                if ($value === 0) {
                    continue;
                }
                if (!isset($allocations[$key]) || $value > $allocations[$key]) {
                    throw new InvalidArgumentException('COM_JEM_CANCELLATION_AREAS_INVALID');
                }
                $cancelledAreas[$key] = $value;
                $allocations[$key] -= $value;
            }
            if (JemRegistrationQuantity::sumOptional($cancelledAreas) !== $quantity) {
                throw new InvalidArgumentException('COM_JEM_CANCELLATION_AREAS_INVALID');
            }
        } elseif (JemRegistrationQuantity::sumOptional($requestedAreas) !== 0) {
            throw new InvalidArgumentException('COM_JEM_CANCELLATION_AREAS_INVALID');
        }
        // Cancelled area and commercial responses retain their historical quantity.
        $places = $remaining;
        if ($remaining === 0 && ($isArea || $isPriced)) {
            $places = (int) $registration->places;
            $allocations = $currentAreas;
        }
        if ($remaining > 0 && $isPriced) {
            throw new InvalidArgumentException('COM_JEM_PRICED_CANCELLATION_FULL_ONLY');
        }
        ksort($allocations);
        ksort($cancelledAreas);
        return array(
            'places' => $places,
            'status' => (int) $response->status,
            'remaining' => $remaining,
            'quantity' => $quantity,
            'allocations' => $allocations,
            'cancelledAreas' => $cancelledAreas,
        );
    }
}
