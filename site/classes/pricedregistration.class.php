<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

require_once __DIR__ . '/money.class.php';
require_once __DIR__ . '/pricingquote.class.php';
require_once __DIR__ . '/registrationservice.class.php';
require_once __DIR__ . '/registrationtransition.class.php';

/**
 * Point 4F registration-order lifecycle.
 *
 * PricingQuoteService owns confirmation transactions and inventory locks. This
 * service turns the authoritative quote into one parent registration revision
 * and one complete append-only admission-line snapshot in that transaction.
 */
final class JemPricedRegistrationService
{
    private JemPricingQuoteService $quotes;
    private JemRegistrationService $registrations;

    public function __construct(private readonly mixed $db, ?callable $clock = null)
    {
        $this->quotes = new JemPricingQuoteService($db, $clock);
        $this->registrations = new JemRegistrationService($db);
    }

    /**
     * Preview an order. An active order keeps the price and tax snapshots of
     * its existing admission types; only newly added types use current terms.
     */
    public function quote(int $eventId, array $selections, JemPricingQuoteContext $context): array
    {
        $quote = $this->quotes->quote($eventId, $selections, $context);

        return $this->quotes->refingerprint($this->applyLockedTerms($quote, $context, false));
    }

    /**
     * Confirm a new order, an explicitly modified order, or a reactivation.
     *
     * $registrationData may contain comment and uip. expectedRevision belongs
     * in $options for optimistic concurrency on an existing order.
     */
    public function confirm(
        int $eventId,
        array $selections,
        JemPricingQuoteContext $context,
        string $expectedQuoteFingerprint,
        string $operationReference,
        array $registrationData = array(),
        array $options = array()
    ) {
        return $this->quotes->withLockedQuote(
            $eventId,
            $selections,
            $context,
            $expectedQuoteFingerprint,
            $operationReference,
            fn (string $reference, int $scopeEventId, int $userId) =>
                $this->loadAppliedOperation($reference, $scopeEventId, $userId),
            function (array $quote, string $reference) use ($context, $registrationData, $options) {
                return $this->persistLockedQuote($quote, $reference, $context, $registrationData, $options);
            },
            fn (array $quote): array => $this->applyLockedTerms($quote, $context, true)
        );
    }

    /**
     * Select waiting orders that fit event, pool and price inventories while
     * the caller owns the event/registration transaction. Manual selection is
     * all-or-nothing; automatic selection honours strict/fill queue policy.
     */
    public function selectPromotableLocked(
        int $eventId,
        array $confirmed,
        array $candidates,
        int $eventAvailable,
        string $strategy,
        bool $manual
    ): array {
        if (!$candidates) {
            return array();
        }

        $all = array_merge($confirmed, $candidates);
        $ids = array_values(array_unique(array_map(static fn ($row): int => (int) $row->id, $all)));
        sort($ids, SORT_NUMERIC);
        $query = $this->db->getQuery(true)
            ->select('i.*')
            ->from($this->db->quoteName('#__jem_register_items', 'i'))
            ->join('INNER', $this->db->quoteName('#__jem_register', 'r')
                . ' ON r.id = i.register_id AND r.revision = i.registration_revision')
            ->where('r.event = ' . $eventId)
            ->where('r.id IN (' . implode(',', $ids) . ')')
            ->where("i.line_kind = 'admission'")
            ->order('i.register_id ASC, i.line_number ASC');
        $this->db->setQuery((string) $query . ' FOR UPDATE');
        $items = (array) $this->db->loadObjectList();
        $itemsByRegister = array();
        $poolIds = array();
        $priceIds = array();
        foreach ($items as $item) {
            $itemsByRegister[(int) $item->register_id][] = $item;
            if ((int) ($item->capacity_pool_id ?? 0) > 0) {
                $poolIds[] = (int) $item->capacity_pool_id;
            }
            if ((int) ($item->event_price_id ?? 0) > 0) {
                $priceIds[] = (int) $item->event_price_id;
            }
        }

        $poolCapacity = $this->loadCapacitiesLocked(
            '#__jem_capacity_pools',
            $eventId,
            $poolIds,
            'capacity'
        );
        $priceQuota = $this->loadCapacitiesLocked(
            '#__jem_event_prices',
            $eventId,
            $priceIds,
            'quota'
        );
        $poolUsed = array();
        $priceUsed = array();
        foreach ($confirmed as $registration) {
            foreach ($itemsByRegister[(int) $registration->id] ?? array() as $item) {
                $quantity = (int) $item->quantity;
                if ((int) ($item->capacity_pool_id ?? 0) > 0) {
                    $poolId = (int) $item->capacity_pool_id;
                    $poolUsed[$poolId] = ($poolUsed[$poolId] ?? 0) + $quantity;
                }
                if ((int) ($item->event_price_id ?? 0) > 0) {
                    $priceId = (int) $item->event_price_id;
                    $priceUsed[$priceId] = ($priceUsed[$priceId] ?? 0) + $quantity;
                }
            }
        }

        $selected = array();
        foreach ($candidates as $candidate) {
            $places = max(1, (int) ($candidate->places ?? 1));
            $fits = $places <= $eventAvailable;
            $requirementsByPool = array();
            $requirementsByPrice = array();
            foreach ($itemsByRegister[(int) $candidate->id] ?? array() as $item) {
                $quantity = (int) $item->quantity;
                if ((int) ($item->capacity_pool_id ?? 0) > 0) {
                    $poolId = (int) $item->capacity_pool_id;
                    $requirementsByPool[$poolId] = ($requirementsByPool[$poolId] ?? 0) + $quantity;
                }
                if ((int) ($item->event_price_id ?? 0) > 0) {
                    $priceId = (int) $item->event_price_id;
                    $requirementsByPrice[$priceId] = ($requirementsByPrice[$priceId] ?? 0) + $quantity;
                }
            }
            foreach ($requirementsByPool as $poolId => $quantity) {
                $fits = $fits
                    && array_key_exists($poolId, $poolCapacity)
                    && $poolCapacity[$poolId] !== null
                    && ($poolUsed[$poolId] ?? 0) + $quantity <= $poolCapacity[$poolId];
            }
            foreach ($requirementsByPrice as $priceId => $quantity) {
                if (!array_key_exists($priceId, $priceQuota)) {
                    $fits = false;
                } elseif ($priceQuota[$priceId] !== null) {
                    $fits = $fits && ($priceUsed[$priceId] ?? 0) + $quantity <= $priceQuota[$priceId];
                }
            }

            if (!$fits) {
                if ($manual || $strategy === 'strict') {
                    return array();
                }
                continue;
            }

            $selected[] = $candidate;
            $eventAvailable -= $places;
            foreach ($requirementsByPool as $poolId => $quantity) {
                $poolUsed[$poolId] = ($poolUsed[$poolId] ?? 0) + $quantity;
            }
            foreach ($requirementsByPrice as $priceId => $quantity) {
                $priceUsed[$priceId] = ($priceUsed[$priceId] ?? 0) + $quantity;
            }
        }

        return $selected;
    }

    private function persistLockedQuote(
        array $quote,
        string $operationReference,
        JemPricingQuoteContext $context,
        array $registrationData,
        array $options
    ) {
        $before = $this->loadRegistrationForUpdate(
            (int) $quote['event_id'],
            $context->userId(),
            $context->excludedRegisterId()
        );
        $oldStatus = is_object($before)
            ? JemRegistrationTransition::logicalStatus($before)
            : null;

        if ($context->excludedRegisterId() < 1 && is_object($before)) {
            throw new RuntimeException('The booking holder already has a registration for this event.', 1062);
        }
        if ($context->excludedRegisterId() > 0 && !is_object($before)) {
            throw new RuntimeException('The priced registration no longer exists.');
        }
        if ($oldStatus === JemRegistrationTransition::ATTENDING
            && ($quote['inventory_state'] ?? '') !== 'available') {
            throw new JemPricingQuoteException(
                'modification_capacity',
                'The confirmed order cannot be changed because its complete revised selection is unavailable.'
            );
        }

        $after = is_object($before) ? clone $before : new stdClass();
        $after->event = (int) $quote['event_id'];
        $after->uid = $context->userId();
        $after->places = (int) $quote['quantity'];
        $after->comment = (string) ($registrationData['comment'] ?? ($after->comment ?? ''));
        $after->uip = (string) ($registrationData['uip'] ?? ($after->uip ?? ''));

        $requestedStatus = isset($options['requestedStatus'])
            ? (int) $options['requestedStatus']
            : null;
        if ($requestedStatus === JemRegistrationTransition::WAITING_LIST) {
            JemRegistrationTransition::applyLogicalStatus($after, JemRegistrationTransition::WAITING_LIST);
        } elseif ($oldStatus === JemRegistrationTransition::WAITING_LIST) {
            JemRegistrationTransition::applyLogicalStatus($after, JemRegistrationTransition::WAITING_LIST);
        } elseif (($quote['inventory_state'] ?? '') === 'waiting_list') {
            JemRegistrationTransition::applyLogicalStatus($after, JemRegistrationTransition::WAITING_LIST);
        } else {
            JemRegistrationTransition::applyLogicalStatus($after, JemRegistrationTransition::ATTENDING);
        }

        $after->pricing_mode = (string) ($quote['pricing_mode'] ?? 'priced');
        $after->currency = (string) $quote['currency'];
        $after->subtotal_net = (string) $quote['subtotal_net'];
        $after->discount_total = '0.00';
        $after->tax_total = (string) $quote['tax_total'];
        $after->management_fee_net = '0.00';
        $after->management_fee_tax = '0.00';
        $after->management_fee_gross = '0.00';
        $after->grand_total = (string) $quote['grand_total'];
        $after->payment_state = null;
        $after->external_payment_reference = null;
        $after->price_locked_at = is_object($before)
            && $oldStatus !== JemRegistrationTransition::NOT_ATTENDING
            && !empty($before->price_locked_at)
                ? (string) $before->price_locked_at
                : (string) $quote['quoted_at'];

        $saveOptions = $options;
        $saveOptions['operationReference'] = $operationReference;
        $saveOptions['commercialLines'] = $this->quoteItems($quote);
        $saveOptions['forceRevision'] = is_object($before);
        $saveOptions['requireNew'] = !is_object($before);
        $saveOptions['requireExisting'] = is_object($before);
        $saveOptions['action'] = is_object($before)
            ? ($oldStatus === JemRegistrationTransition::NOT_ATTENDING ? 'reactivated' : 'order_modified')
            : 'created';
        $saveOptions['source'] = (string) ($options['source'] ?? 'priced_registration');

        return $this->registrations->saveLocked($before, $after, $saveOptions);
    }

    /**
     * Replace current price calculations with immutable terms for price IDs
     * that already belong to an active or waiting order.
     */
    private function applyLockedTerms(array $quote, JemPricingQuoteContext $context, bool $lock): array
    {
        if ($context->excludedRegisterId() < 1) {
            return $quote;
        }

        $registration = $this->loadRegistration(
            (int) $quote['event_id'],
            $context->userId(),
            $context->excludedRegisterId(),
            $lock
        );
        $logicalStatus = $registration
            ? JemRegistrationTransition::logicalStatus($registration)
            : null;
        if (!$registration || $logicalStatus === JemRegistrationTransition::NOT_ATTENDING) {
            // Reactivation deliberately uses current prices.
            return $quote;
        }
        if ($logicalStatus === JemRegistrationTransition::ATTENDING
            && ($quote['inventory_state'] ?? '') !== 'available') {
            throw new JemPricingQuoteException(
                'modification_capacity',
                'The confirmed order cannot be changed because its complete revised selection is unavailable.'
            );
        }
        if ($logicalStatus === JemRegistrationTransition::WAITING_LIST) {
            // Editing a queued order never jumps the promotion queue.
            $quote['inventory_state'] = 'waiting_list';
            $quote['inventory_reasons'][] = 'waiting_queue';
            $quote['inventory_reasons'] = array_values(array_unique($quote['inventory_reasons']));
            $quote['event_remaining'] = (int) $quote['event_available'];
            foreach ($quote['lines'] as &$waitingLine) {
                if (array_key_exists('pool_available', $waitingLine)) {
                    $waitingLine['pool_remaining'] = $waitingLine['pool_available'];
                }
                if (array_key_exists('quota_available', $waitingLine)) {
                    $waitingLine['quota_remaining'] = $waitingLine['quota_available'];
                }
            }
            unset($waitingLine);
        }

        $stored = $this->registrations->commercialItems(
            (int) $registration->id,
            max(1, (int) ($registration->revision ?? 1)),
            $lock
        );
        $byPrice = array();
        foreach ($stored as $item) {
            if (($item->line_kind ?? '') === 'admission' && (int) ($item->event_price_id ?? 0) > 0) {
                $byPrice[(int) $item->event_price_id] = $item;
            }
        }

        $currency = (string) ($registration->currency ?? $quote['currency']);
        if ($currency !== (string) $quote['currency']) {
            throw new JemPricingQuoteException(
                'currency_changed',
                'An existing order cannot mix its locked currency with the current event currency.'
            );
        }

        $subtotal = JemMoney::fromDecimal('0.00', $currency);
        $taxTotal = JemMoney::fromDecimal('0.00', $currency);
        $grandTotal = JemMoney::fromDecimal('0.00', $currency);
        foreach ($quote['lines'] as &$line) {
            $item = $byPrice[(int) $line['event_price_id']] ?? null;
            if ($item) {
                $quantity = (int) $line['quantity'];
                $line['code'] = (string) $item->item_code;
                $line['name'] = (string) $item->item_name;
                $line['description'] = (string) ($item->item_description ?? '');
                $line['capacity_pool_id'] = !empty($item->capacity_pool_id)
                    ? (int) $item->capacity_pool_id
                    : null;
                $includesTax = !empty($item->price_includes_tax);
                $policy = new JemTaxPolicy(
                    (string) $item->tax_type,
                    (string) $item->tax_rate,
                    $includesTax
                );
                $calculation = JemTaxCalculator::calculate(
                    JemMoney::fromDecimal(
                        (string) ($includesTax ? $item->unit_gross : $item->unit_net),
                        $currency
                    ),
                    $policy,
                    $quantity
                );
                $line['price_includes_tax'] = $includesTax ? 1 : 0;
                $line['unit_net'] = $calculation->unitNet->decimal();
                $line['unit_tax'] = $calculation->unitTax->decimal();
                $line['unit_gross'] = $calculation->unitGross->decimal();
                $line['line_net'] = $calculation->lineNet->decimal();
                $line['line_tax'] = $calculation->lineTax->decimal();
                $line['line_gross'] = $calculation->lineGross->decimal();
                $line['tax_code'] = (string) $item->tax_code;
                $line['tax_name'] = (string) $item->tax_name;
                $line['tax_type'] = (string) $item->tax_type;
                $line['tax_rate'] = (string) $item->tax_rate;
                $conditions = json_decode((string) ($item->condition_snapshot ?? ''), true);
                if (is_array($conditions)) {
                    $line['conditions'] = $conditions['conditions'] ?? $conditions;
                }
            }

            $subtotal = $subtotal->plus(JemMoney::fromDecimal((string) $line['line_net'], $currency));
            $taxTotal = $taxTotal->plus(JemMoney::fromDecimal((string) $line['line_tax'], $currency));
            $grandTotal = $grandTotal->plus(JemMoney::fromDecimal((string) $line['line_gross'], $currency));
        }
        unset($line);

        $quote['currency'] = $currency;
        $quote['subtotal_net'] = $subtotal->decimal();
        $quote['tax_total'] = $taxTotal->decimal();
        $quote['grand_total'] = $grandTotal->decimal();

        return $quote;
    }

    private function quoteItems(array $quote): array
    {
        return array_map(static function (array $line) use ($quote): object {
            return (object) array(
                'line_kind' => 'admission',
                'event_price_id' => (int) $line['event_price_id'],
                'capacity_pool_id' => $line['capacity_pool_id'] === null
                    ? null
                    : (int) $line['capacity_pool_id'],
                'item_code' => (string) $line['code'],
                'item_name' => (string) $line['name'],
                'item_description' => (string) ($line['description'] ?? ''),
                'quantity' => (int) $line['quantity'],
                'currency' => (string) $quote['currency'],
                'price_includes_tax' => (int) ($line['price_includes_tax'] ?? $quote['prices_include_tax']),
                'unit_net' => (string) $line['unit_net'],
                'unit_tax' => (string) $line['unit_tax'],
                'unit_gross' => (string) $line['unit_gross'],
                'line_net' => (string) $line['line_net'],
                'line_tax' => (string) $line['line_tax'],
                'line_gross' => (string) $line['line_gross'],
                'tax_code' => (string) $line['tax_code'],
                'tax_name' => (string) $line['tax_name'],
                'tax_type' => (string) $line['tax_type'],
                'tax_rate' => (string) $line['tax_rate'],
                'calculation_mode' => 'admission',
                'calculation_value' => null,
                'calculation_basis' => (int) ($line['price_includes_tax'] ?? $quote['prices_include_tax']) === 1
                    ? 'gross'
                    : 'net',
                'condition_snapshot' => json_encode(array(
                    'schema' => 'jem-price-condition/v1',
                    'conditions' => (array) ($line['conditions'] ?? array()),
                ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            );
        }, (array) $quote['lines']);
    }

    private function loadRegistrationForUpdate(int $eventId, int $userId, int $registerId): ?object
    {
        return $this->loadRegistration($eventId, $userId, $registerId, true);
    }

    private function loadRegistration(
        int $eventId,
        int $userId,
        int $registerId,
        bool $lock
    ): ?object {
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jem_register'))
            ->where($this->db->quoteName('event') . ' = ' . $eventId)
            ->where($this->db->quoteName('uid') . ' = ' . $userId);
        if ($registerId > 0) {
            $query->where($this->db->quoteName('id') . ' = ' . $registerId);
        }
        $this->db->setQuery((string) $query . ($lock ? ' FOR UPDATE' : ''));

        return $this->db->loadObject() ?: null;
    }

    private function loadAppliedOperation(string $operationReference, int $eventId, int $userId): ?object
    {
        $query = $this->db->getQuery(true)
            ->select('r.*')
            ->from($this->db->quoteName('#__jem_register_history', 'h'))
            ->join('INNER', $this->db->quoteName('#__jem_register', 'r') . ' ON r.id = h.registration_id')
            ->where('h.operation_reference = ' . $this->db->quote($operationReference))
            ->where('h.event_id = ' . $eventId)
            ->where('r.uid = ' . $userId)
            ->order('h.id DESC');
        $this->db->setQuery($query, 0, 1);
        $after = $this->db->loadObject();

        if (!$after) {
            return null;
        }

        return (object) array(
            'before' => $after,
            'after' => $after,
            'transition' => null,
            'operationReference' => $operationReference,
            'changed' => false,
        );
    }

    private function loadCapacitiesLocked(
        string $table,
        int $eventId,
        array $ids,
        string $capacityColumn
    ): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return array();
        }
        sort($ids, SORT_NUMERIC);
        $query = $this->db->getQuery(true)
            ->select(array('id', $capacityColumn))
            ->from($this->db->quoteName($table))
            ->where('event_id = ' . $eventId)
            ->where('id IN (' . implode(',', $ids) . ')')
            ->order('id ASC');
        $this->db->setQuery((string) $query . ' FOR UPDATE');
        $rows = (array) $this->db->loadAssocList('id', $capacityColumn);

        return array_map(static fn ($value): ?int => $value === null ? null : (int) $value, $rows);
    }
}
