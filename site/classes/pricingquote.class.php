<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\Database\DatabaseDriver;

require_once __DIR__ . '/money.class.php';
require_once __DIR__ . '/taxpolicy.class.php';
require_once __DIR__ . '/taxcalculation.class.php';
require_once __DIR__ . '/taxcalculator.class.php';
require_once __DIR__ . '/registrationidentity.class.php';

/**
 * Stable domain failure returned by the Point 4E quote engine.
 *
 * Controllers translate reasonCode to a public language string. The English
 * message remains useful to CLI/integration tests and server logs.
 */
final class JemPricingQuoteException extends RuntimeException
{
    public function __construct(
        private readonly string $reasonCode,
        string $message
    ) {
        parent::__construct($message);
    }

    public function getReasonCode(): string
    {
        return $this->reasonCode;
    }
}

/**
 * Server-owned booking-holder context.
 *
 * Browser requests may submit the expected pricing revision, but never their
 * own Access Levels, user groups, identity or clock value.
 */
final class JemPricingQuoteContext
{
    private function __construct(
        private readonly int $userId,
        private readonly int $expectedPricingRevision,
        private readonly array $accessLevels,
        private readonly array $userGroups,
        private readonly int $excludedRegisterId
    ) {
    }

    public static function fromIdentity(
        object $identity,
        int $expectedPricingRevision,
        int $excludedRegisterId = 0
    ): self {
        if ($expectedPricingRevision < 1) {
            throw new InvalidArgumentException('Pricing revision must be a positive integer.');
        }
        if ($excludedRegisterId < 0) {
            throw new InvalidArgumentException('Excluded registration ID cannot be negative.');
        }

        $userId = isset($identity->id)
            ? (int) $identity->id
            : (method_exists($identity, 'get') ? (int) $identity->get('id') : 0);
        $levels = method_exists($identity, 'getAuthorisedViewLevels')
            ? (array) $identity->getAuthorisedViewLevels()
            : array();
        $groups = method_exists($identity, 'getAuthorisedGroups')
            ? (array) $identity->getAuthorisedGroups()
            : array();

        return new self(
            $userId,
            $expectedPricingRevision,
            self::normaliseIds($levels),
            self::normaliseIds($groups),
            $excludedRegisterId
        );
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function expectedPricingRevision(): int
    {
        return $this->expectedPricingRevision;
    }

    public function accessLevels(): array
    {
        return $this->accessLevels;
    }

    public function userGroups(): array
    {
        return $this->userGroups;
    }

    public function excludedRegisterId(): int
    {
        return $this->excludedRegisterId;
    }

    private static function normaliseIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }
}

/**
 * Authoritative Point 4E quote, eligibility and capacity service.
 *
 * The service deliberately does not create registrations or commercial lines.
 * Phase 4F consumes the immutable quote returned here inside the same database
 * transaction used for the registration transition.
 */
final class JemPricingQuoteService
{
    public const SCHEMA = 'jem-pricing-quote/v1';

    private $clock;

    public function __construct(private readonly DatabaseDriver $db, ?callable $clock = null)
    {
        $this->clock = $clock ?? static fn (): string => gmdate('Y-m-d H:i:s');
    }

    /**
     * Recalculate an event quote from selected price IDs and quantities.
     *
     * @param int   $eventId Event identifier.
     * @param array $selections Each row contains event_price_id and quantity.
     */
    public function quote(int $eventId, array $selections, JemPricingQuoteContext $context): array
    {
        return $this->buildQuote($eventId, $selections, $context, false);
    }

    /**
     * Rebuild the stable commercial fingerprint after an authorised server
     * service has applied stored price-lock terms to a quote.
     */
    public function refingerprint(array $quote): array
    {
        $quote['quote_fingerprint'] = $this->quoteFingerprint($quote);

        return $quote;
    }

    /**
     * Lock inventory, re-quote it, and run a caller supplied atomic operation.
     *
     * The callback receives the authoritative quote. Phase 4F will use it to
     * persist the parent registration and append-only item revision. Retrying
     * an operation reference may return an existing result through the
     * idempotencyLookup callback without reapplying the write.
     */
    public function withLockedQuote(
        int $eventId,
        array $selections,
        JemPricingQuoteContext $context,
        string $expectedQuoteFingerprint,
        string $operationReference,
        callable $idempotencyLookup,
        callable $operation,
        ?callable $quoteTransformer = null
    ): mixed {
        $operationReference = trim($operationReference);
        if (!JemRegistrationIdentity::isOperationReference($operationReference)) {
            throw new InvalidArgumentException('A valid pricing operation reference is required.');
        }

        $this->db->transactionStart();
        try {
            $this->lockEventReference($eventId);
            $existing = $idempotencyLookup(
                $operationReference,
                $eventId,
                $context->userId()
            );
            if ($existing !== null) {
                $this->db->transactionCommit();

                return $existing;
            }

            $quote = $this->buildQuote($eventId, $selections, $context, true);
            if ($quoteTransformer !== null) {
                $quote = $this->refingerprint($quoteTransformer($quote));
            }
            if (preg_match('/^[a-f0-9]{64}$/D', $expectedQuoteFingerprint) !== 1
                || !hash_equals($quote['quote_fingerprint'], $expectedQuoteFingerprint)) {
                throw new JemPricingQuoteException(
                    'quote_changed',
                    'The calculated quote has changed. Review it before confirming.'
                );
            }
            $result = $operation($quote, $operationReference);
            $this->db->transactionCommit();

            return $result;
        } catch (Throwable $error) {
            $this->db->transactionRollback();
            throw $error;
        }
    }

    private function buildQuote(
        int $eventId,
        array $selections,
        JemPricingQuoteContext $context,
        bool $lock
    ): array
    {
        if ($eventId < 1) {
            throw new InvalidArgumentException('A valid priced event is required.');
        }

        $event = $this->loadEvent($eventId, $lock);
        if (!$event) {
            throw new JemPricingQuoteException('event_not_found', 'The priced event does not exist.');
        }
        if (!in_array((string) $event['pricing_mode'], array('single', 'multiple', 'priced'), true)) {
            throw new JemPricingQuoteException(
                'classic_event',
                'Classic events do not use the pricing quote service.'
            );
        }
        if ((int) $event['maxplaces'] < 1
            || preg_match('/^[A-Z]{3}$/D', (string) $event['currency']) !== 1
            || preg_match('/^[A-Z]{2}$/D', (string) $event['country_code']) !== 1) {
            throw new JemPricingQuoteException(
                'invalid_event_pricing',
                'The event pricing configuration is incomplete.'
            );
        }

        if ($context->expectedPricingRevision() !== (int) $event['pricing_revision']) {
            throw new JemPricingQuoteException(
                'stale_pricing',
                'The event pricing has changed. Refresh the quote before confirming.'
            );
        }

        $now = $this->normaliseDateTime((string) ($this->clock)());
        $this->assertEventEligible($event, $context, $now);
        $this->assertExcludedRegistration($eventId, $context, $lock);

        $selectionByPrice = $this->normaliseSelections($selections);
        $prices = $this->loadPrices($eventId, array_keys($selectionByPrice), $lock);
        if (count($prices) !== count($selectionByPrice)) {
            throw new JemPricingQuoteException(
                'price_unavailable',
                'A selected price is unavailable for this event.'
            );
        }

        $poolIds = array_values(array_unique(array_filter(array_map(
            static fn (array $price): int => (int) ($price['capacity_pool_id'] ?? 0),
            $prices
        ))));
        $pools = $this->loadPools($eventId, $poolIds, $lock);
        if (count($pools) !== count($poolIds)) {
            throw new JemPricingQuoteException(
                'pool_unavailable',
                'A selected price capacity pool is unavailable.'
            );
        }
        $poolsById = array_column($pools, null, 'id');

        $taxIds = array_values(array_unique(array_map(
            static fn (array $price): int => (int) $price['tax_rate_id'],
            $prices
        )));
        $taxRates = $this->loadTaxRates($taxIds, $lock);
        if (count($taxRates) !== count($taxIds)) {
            throw new JemPricingQuoteException(
                'tax_unavailable',
                'A selected tax rate is unavailable.'
            );
        }
        $taxRatesById = array_column($taxRates, null, 'id');

        $accessLevels = array_fill_keys($context->accessLevels(), true);
        $userGroups = array_fill_keys($context->userGroups(), true);
        $used = $this->loadUsedCapacity($eventId, $context->excludedRegisterId());
        $eventQuantity = 0;
        $requestedByPool = array();
        $inventoryFailures = array();
        $lines = array();
        $subtotalNet = JemMoney::fromMinorUnits(0, (string) $event['currency']);
        $taxTotal = JemMoney::fromMinorUnits(0, (string) $event['currency']);
        $grandTotal = JemMoney::fromMinorUnits(0, (string) $event['currency']);

        foreach ($prices as $price) {
            $priceId = (int) $price['id'];
            $quantity = $selectionByPrice[$priceId];
            $this->assertEligible($price, $quantity, $now, $accessLevels, $userGroups);

            $poolId = (int) ($price['capacity_pool_id'] ?? 0);
            if ($poolId > 0) {
                $pool = $poolsById[$poolId] ?? null;
                if (!$pool || (int) $pool['published'] !== 1) {
                    throw new JemPricingQuoteException(
                        'pool_unavailable',
                        'A selected price capacity pool is unavailable.'
                    );
                }
                $requestedByPool[$poolId] = ($requestedByPool[$poolId] ?? 0) + $quantity;
            }

            $priceUsed = (int) ($used['prices'][$priceId] ?? 0);
            if ($price['quota'] !== null && $priceUsed + $quantity > (int) $price['quota']) {
                $inventoryFailures[] = array(
                    'code' => 'price_quota',
                    'message' => 'A selected price quota would be exceeded.',
                );
            }

            $tax = $taxRatesById[(int) $price['tax_rate_id']];
            $this->assertTaxApplicable($tax, $event, $now);
            $policy = new JemTaxPolicy(
                (string) $tax['tax_type'],
                (string) $tax['rate'],
                (int) $event['prices_include_tax'] === 1
            );
            $calculation = JemTaxCalculator::calculate(
                JemMoney::fromDecimal((string) $price['amount'], (string) $event['currency']),
                $policy,
                $quantity
            );

            $subtotalNet = $subtotalNet->plus($calculation->lineNet);
            $taxTotal = $taxTotal->plus($calculation->lineTax);
            $grandTotal = $grandTotal->plus($calculation->lineGross);
            $eventQuantity += $quantity;
            $lines[] = $this->quoteLine(
                $price,
                $tax,
                $calculation,
                $used,
                (int) $event['prices_include_tax']
            );
        }

        $this->assertBookingQuantity($event, $eventQuantity);

        $eventUsed = (int) $event['reservedplaces'] + (int) $used['event'];
        $eventAvailable = max(0, (int) $event['maxplaces'] - $eventUsed);
        if ((int) $event['maxplaces'] > 0 && $eventUsed + $eventQuantity > (int) $event['maxplaces']) {
            $inventoryFailures[] = array(
                'code' => 'event_capacity',
                'message' => 'Event capacity would be exceeded.',
            );
        }
        foreach ($requestedByPool as $poolId => $quantity) {
            $poolUsed = (int) ($used['pools'][$poolId] ?? 0);
            if ($poolUsed + $quantity > (int) $poolsById[$poolId]['capacity']) {
                $inventoryFailures[] = array(
                    'code' => 'pool_capacity',
                    'message' => 'A selected capacity pool would be exceeded.',
                );
            }
        }

        $inventoryState = 'available';
        if ($inventoryFailures) {
            if (empty($event['waitinglist'])) {
                $failure = $inventoryFailures[0];
                throw new JemPricingQuoteException($failure['code'], $failure['message']);
            }
            $inventoryState = 'waiting_list';
        }

        foreach ($lines as &$line) {
            $poolId = (int) ($line['capacity_pool_id'] ?? 0);
            if ($poolId > 0) {
                $poolAvailable = max(
                    0,
                    (int) $poolsById[$poolId]['capacity'] - (int) ($used['pools'][$poolId] ?? 0)
                );
                $line['pool_available'] = $poolAvailable;
                $line['pool_remaining'] = max(
                    0,
                    $poolAvailable - ($inventoryState === 'available'
                        ? (int) ($requestedByPool[$poolId] ?? 0)
                        : 0)
                );
            }
            if ($line['quota'] !== null) {
                $quotaAvailable = max(0, (int) $line['quota'] - (int) $line['quota_used']);
                $line['quota_available'] = $quotaAvailable;
                $line['quota_remaining'] = max(
                    0,
                    $quotaAvailable - ($inventoryState === 'available' ? (int) $line['quantity'] : 0)
                );
            }
            unset($line['quota'], $line['quota_used']);
        }
        unset($line);

        $quote = array(
            'schema' => self::SCHEMA,
            'event_id' => $eventId,
            'pricing_mode' => (string) $event['pricing_mode'],
            'pricing_revision' => (int) $event['pricing_revision'],
            'currency' => (string) $event['currency'],
            'prices_include_tax' => (int) $event['prices_include_tax'],
            'quantity' => $eventQuantity,
            'inventory_state' => $inventoryState,
            'inventory_reasons' => array_values(array_unique(array_column($inventoryFailures, 'code'))),
            'event_capacity' => (int) $event['maxplaces'],
            'event_used' => $eventUsed,
            'event_available' => $eventAvailable,
            'event_remaining' => max(
                0,
                $eventAvailable - ($inventoryState === 'available' ? $eventQuantity : 0)
            ),
            'subtotal_net' => $subtotalNet->decimal(),
            'tax_total' => $taxTotal->decimal(),
            'grand_total' => $grandTotal->decimal(),
            'quoted_at' => $now,
            'lines' => $lines,
        );
        $quote['quote_fingerprint'] = $this->quoteFingerprint($quote);

        return $quote;
    }

    private function loadEvent(int $eventId, bool $lock): ?array
    {
        $query = $this->db->getQuery(true)
            ->select(array(
                'e.id', 'e.pricing_mode', 'e.pricing_revision', 'e.currency',
                'e.prices_include_tax', 'e.maxplaces', 'e.reservedplaces',
                'e.minbookeduser', 'e.maxbookeduser', 'e.waitinglist',
                'e.registra', 'e.registra_from', 'e.registra_until', 'e.reginvitedonly',
                'e.published', 'e.publish_up', 'e.publish_down', 'e.access',
                'e.dates', 'e.locid', 'e.venue_snapshot',
            ))
            ->from($this->db->quoteName('#__jem_events', 'e'))
            ->where('e.id = ' . $eventId);
        $this->db->setQuery((string) $query . ($lock ? ' FOR UPDATE' : ''));

        $event = $this->db->loadAssoc() ?: null;
        if (!$event) {
            return null;
        }

        $countryCode = '';
        $snapshot = json_decode((string) ($event['venue_snapshot'] ?? ''), true);
        if (is_array($snapshot)
            && ($snapshot['schema'] ?? '') === 'jem-venue-capacity/v1'
            && preg_match('/^[A-Z]{2}$/D', strtoupper(trim((string) ($snapshot['country_code'] ?? '')))) === 1) {
            $countryCode = strtoupper(trim((string) $snapshot['country_code']));
        } elseif (!empty($event['locid'])) {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('country'))
                ->from($this->db->quoteName('#__jem_venues'))
                ->where($this->db->quoteName('id') . ' = ' . (int) $event['locid']);
            $this->db->setQuery($query);
            $countryCode = strtoupper(trim((string) $this->db->loadResult()));
        }
        $event['country_code'] = $countryCode;
        unset($event['venue_snapshot']);

        return $event;
    }

    private function lockEventReference(int $eventId): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__jem_events'))
            ->where($this->db->quoteName('id') . ' = ' . $eventId);
        $this->db->setQuery((string) $query . ' FOR UPDATE');
        if ((int) $this->db->loadResult() !== $eventId) {
            throw new JemPricingQuoteException('event_not_found', 'The priced event does not exist.');
        }
    }

    private function loadPrices(int $eventId, array $priceIds, bool $lock): array
    {
        sort($priceIds, SORT_NUMERIC);
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jem_event_prices'))
            ->where($this->db->quoteName('event_id') . ' = ' . $eventId)
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $priceIds)) . ')')
            ->where($this->db->quoteName('published') . ' = 1')
            ->order($this->db->quoteName('id') . ' ASC');
        $this->db->setQuery((string) $query . ($lock ? ' FOR UPDATE' : ''));

        return (array) $this->db->loadAssocList();
    }

    private function loadPools(int $eventId, array $poolIds, bool $lock): array
    {
        if (!$poolIds) {
            return array();
        }
        sort($poolIds, SORT_NUMERIC);
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jem_capacity_pools'))
            ->where($this->db->quoteName('event_id') . ' = ' . $eventId)
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $poolIds)) . ')')
            ->order($this->db->quoteName('id') . ' ASC');
        $this->db->setQuery((string) $query . ($lock ? ' FOR UPDATE' : ''));

        return (array) $this->db->loadAssocList();
    }

    private function loadTaxRates(array $taxIds, bool $lock): array
    {
        sort($taxIds, SORT_NUMERIC);
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__jem_tax_rates'))
            ->where($this->db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $taxIds)) . ')')
            ->where($this->db->quoteName('published') . ' = 1')
            ->order($this->db->quoteName('id') . ' ASC');
        $this->db->setQuery((string) $query . ($lock ? ' FOR UPDATE' : ''));

        return (array) $this->db->loadAssocList();
    }

    private function loadUsedCapacity(int $eventId, int $excludedRegisterId = 0): array
    {
        $currentItems = 'i.registration_revision = r.revision';
        $active = 'r.status = 1 AND r.waiting = 0';
        $eventQuery = $this->db->getQuery(true)
            ->select('COALESCE(SUM(GREATEST(r.places, 1)), 0)')
            ->from($this->db->quoteName('#__jem_register', 'r'))
            ->where('r.event = ' . $eventId)
            ->where($active);
        if ($excludedRegisterId > 0) {
            $eventQuery->where('r.id <> ' . $excludedRegisterId);
        }
        $this->db->setQuery($eventQuery);
        $eventUsed = (int) $this->db->loadResult();

        $base = $this->db->getQuery(true)
            ->select('COALESCE(SUM(i.quantity), 0)')
            ->from($this->db->quoteName('#__jem_register_items', 'i'))
            ->join('INNER', $this->db->quoteName('#__jem_register', 'r') . ' ON r.id = i.register_id')
            ->where('r.event = ' . $eventId)
            ->where($active)
            ->where($currentItems)
            ->where("i.line_kind = 'admission'");
        if ($excludedRegisterId > 0) {
            $base->where('r.id <> ' . $excludedRegisterId);
        }
        $poolQuery = clone $base;
        $poolQuery->clear('select')
            ->select(array('i.capacity_pool_id', 'SUM(i.quantity) AS used_quantity'))
            ->where('i.capacity_pool_id IS NOT NULL')
            ->group('i.capacity_pool_id');
        $this->db->setQuery($poolQuery);
        $poolUsed = array_map('intval', (array) $this->db->loadAssocList('capacity_pool_id', 'used_quantity'));

        $priceQuery = clone $base;
        $priceQuery->clear('select')
            ->select(array('i.event_price_id', 'SUM(i.quantity) AS used_quantity'))
            ->where('i.event_price_id IS NOT NULL')
            ->group('i.event_price_id');
        $this->db->setQuery($priceQuery);
        $priceUsed = array_map('intval', (array) $this->db->loadAssocList('event_price_id', 'used_quantity'));

        return array('event' => $eventUsed, 'pools' => $poolUsed, 'prices' => $priceUsed);
    }

    private function normaliseSelections(array $selections): array
    {
        $normalised = array();
        foreach ($selections as $selection) {
            if (!is_array($selection)) {
                throw new JemPricingQuoteException(
                    'invalid_selection',
                    'Each price selection must be an array.'
                );
            }
            $priceId = $this->positiveInteger($selection['event_price_id'] ?? null, 'Event price ID');
            $quantity = $this->positiveInteger($selection['quantity'] ?? null, 'Price quantity');
            if (isset($normalised[$priceId])) {
                throw new JemPricingQuoteException(
                    'invalid_selection',
                    'Price selections require unique IDs and positive quantities.'
                );
            }
            $normalised[$priceId] = $quantity;
        }
        if (!$normalised) {
            throw new JemPricingQuoteException(
                'invalid_selection',
                'At least one admission price must be selected.'
            );
        }
        ksort($normalised, SORT_NUMERIC);

        return $normalised;
    }

    private function positiveInteger(mixed $value, string $label): int
    {
        if (is_int($value)) {
            if ($value > 0) {
                return $value;
            }
            throw new JemPricingQuoteException(
                'invalid_selection',
                $label . ' must be a positive integer.'
            );
        }
        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            throw new JemPricingQuoteException(
                'invalid_selection',
                $label . ' must be a positive integer.'
            );
        }
        $maximum = (string) PHP_INT_MAX;
        if (strlen($value) > strlen($maximum)
            || (strlen($value) === strlen($maximum) && strcmp($value, $maximum) > 0)) {
            throw new JemPricingQuoteException(
                'invalid_selection',
                $label . ' exceeds the supported integer range.'
            );
        }

        return (int) $value;
    }

    private function assertEventEligible(
        array $event,
        JemPricingQuoteContext $context,
        string $now
    ): void {
        if ($context->userId() < 1) {
            throw new JemPricingQuoteException(
                'login_required',
                'A logged-in booking holder is required for a priced quote.'
            );
        }

        $publishUp = trim((string) ($event['publish_up'] ?? ''));
        $publishDown = trim((string) ($event['publish_down'] ?? ''));
        if ((int) ($event['published'] ?? 0) !== 1
            || ($publishUp !== '' && $publishUp !== '0000-00-00 00:00:00' && $now < $publishUp)
            || ($publishDown !== '' && $publishDown !== '0000-00-00 00:00:00' && $now >= $publishDown)) {
            throw new JemPricingQuoteException(
                'event_unavailable',
                'The event is not currently published.'
            );
        }
        $eventAccess = (int) ($event['access'] ?? 0);
        if ($eventAccess > 0 && !in_array($eventAccess, $context->accessLevels(), true)) {
            throw new JemPricingQuoteException(
                'event_access',
                'The booking holder cannot access this event.'
            );
        }

        $registrationMode = (int) ($event['registra'] ?? 0);
        if ($registrationMode === 2) {
            // Preserve JEM's legacy open-date behaviour: a limited window is
            // only evaluated when the event has a concrete date.
            if (!empty($event['dates'])) {
                $from = trim((string) ($event['registra_from'] ?? ''));
                $until = trim((string) ($event['registra_until'] ?? ''));
                if ($from !== '' && $from !== '0000-00-00 00:00:00' && $now < $from) {
                    throw new JemPricingQuoteException(
                        'registration_not_started',
                        'Event registration has not started.'
                    );
                }
                if ($until !== '' && $until !== '0000-00-00 00:00:00' && $now >= $until) {
                    throw new JemPricingQuoteException(
                        'registration_closed',
                        'Event registration is closed.'
                    );
                }
            }
        } elseif ($registrationMode !== 1) {
            throw new JemPricingQuoteException(
                'registration_closed',
                'Event registration is closed.'
            );
        }

        if (!empty($event['reginvitedonly'])
            && !$this->hasRegistrationIdentity((int) $event['id'], $context->userId())) {
            throw new JemPricingQuoteException(
                'invitation_required',
                'This event only accepts invited booking holders.'
            );
        }
    }

    private function assertBookingQuantity(array $event, int $quantity): void
    {
        $minimum = max(1, (int) ($event['minbookeduser'] ?? 1));
        $maximum = (int) ($event['maxbookeduser'] ?? 0);
        if ($quantity < $minimum) {
            throw new JemPricingQuoteException(
                'booking_quantity_minimum',
                'The selected quantity is below the event minimum per registration.'
            );
        }
        if ($maximum > 0 && $quantity > $maximum) {
            throw new JemPricingQuoteException(
                'booking_quantity_maximum',
                'The selected quantity exceeds the event maximum per registration.'
            );
        }
    }

    private function hasRegistrationIdentity(int $eventId, int $userId): bool
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__jem_register'))
            ->where($this->db->quoteName('event') . ' = ' . $eventId)
            ->where($this->db->quoteName('uid') . ' = ' . $userId);
        $this->db->setQuery($query);

        return (int) $this->db->loadResult() > 0;
    }

    private function assertExcludedRegistration(
        int $eventId,
        JemPricingQuoteContext $context,
        bool $lock
    ): void {
        $registerId = $context->excludedRegisterId();
        if ($registerId < 1) {
            return;
        }

        $query = $this->db->getQuery(true)
            ->select(array('event', 'uid'))
            ->from($this->db->quoteName('#__jem_register'))
            ->where($this->db->quoteName('id') . ' = ' . $registerId);
        $this->db->setQuery((string) $query . ($lock ? ' FOR UPDATE' : ''));
        $registration = $this->db->loadAssoc();
        if (!$registration
            || (int) $registration['event'] !== $eventId
            || (int) $registration['uid'] !== $context->userId()) {
            throw new JemPricingQuoteException(
                'registration_scope',
                'The registration being replaced does not belong to this booking holder and event.'
            );
        }
    }

    private function assertEligible(
        array $price,
        int $quantity,
        string $now,
        array $accessLevels,
        array $userGroups
    ): void {
        $minimum = max(1, (int) $price['min_quantity']);
        $maximum = $price['max_quantity'] === null ? null : (int) $price['max_quantity'];
        if ($quantity < $minimum || ($maximum !== null && $quantity > $maximum)) {
            throw new JemPricingQuoteException(
                'price_quantity',
                'The selected quantity is outside the price limits.'
            );
        }
        if (($price['available_from'] !== null && $price['available_from'] > $now)
            || ($price['available_until'] !== null && $price['available_until'] < $now)) {
            throw new JemPricingQuoteException(
                'price_sale_window',
                'The selected price is outside its sale window.'
            );
        }
        $accessId = (int) ($price['access_level_id'] ?? 0);
        if ($accessId > 0 && !isset($accessLevels[$accessId])) {
            throw new JemPricingQuoteException(
                'price_access',
                'The booking holder cannot access the selected price.'
            );
        }
        $groupId = (int) ($price['user_group_id'] ?? 0);
        if ($groupId > 0 && !isset($userGroups[$groupId])) {
            throw new JemPricingQuoteException(
                'price_group',
                'The booking holder is not in the required price group.'
            );
        }

        // Point 4 snapshots age bands as commercial conditions. Per-attendee
        // date-of-birth validation belongs to the later nominative phase.
    }

    private function assertTaxApplicable(array $tax, array $event, string $now): void
    {
        $country = strtoupper(trim((string) ($event['country_code'] ?? '')));
        $taxCountry = strtoupper(trim((string) ($tax['country_code'] ?? '')));
        if ($taxCountry !== '' && $taxCountry !== $country) {
            throw new JemPricingQuoteException(
                'tax_country',
                'A selected tax rate does not apply to the event country.'
            );
        }
        $date = trim((string) ($event['dates'] ?? '')) ?: substr($now, 0, 10);
        if (($tax['valid_from'] !== null && $tax['valid_from'] !== '0000-00-00' && $tax['valid_from'] > $date)
            || ($tax['valid_until'] !== null && $tax['valid_until'] !== '0000-00-00' && $tax['valid_until'] < $date)) {
            throw new JemPricingQuoteException(
                'tax_date',
                'A selected tax rate is not valid on the event date.'
            );
        }
    }

    private function quoteLine(
        array $price,
        array $tax,
        JemTaxCalculation $calculation,
        array $used,
        int $pricesIncludeTax
    ): array
    {
        $poolId = (int) ($price['capacity_pool_id'] ?? 0);
        $quota = $price['quota'] === null ? null : (int) $price['quota'];
        $priceUsed = (int) ($used['prices'][(int) $price['id']] ?? 0);

        return array(
            'event_price_id' => (int) $price['id'],
            'capacity_pool_id' => $poolId ?: null,
            'code' => (string) $price['code'],
            'name' => (string) $price['name'],
            'description' => (string) ($price['description'] ?? ''),
            'quantity' => $calculation->quantity,
            'price_includes_tax' => $pricesIncludeTax === 1 ? 1 : 0,
            'unit_net' => $calculation->unitNet->decimal(),
            'unit_tax' => $calculation->unitTax->decimal(),
            'unit_gross' => $calculation->unitGross->decimal(),
            'line_net' => $calculation->lineNet->decimal(),
            'line_tax' => $calculation->lineTax->decimal(),
            'line_gross' => $calculation->lineGross->decimal(),
            'tax_code' => (string) $tax['code'],
            'tax_name' => (string) $tax['name'],
            'tax_type' => (string) $tax['tax_type'],
            'tax_rate' => $calculation->policy->rateDecimal(),
            'pool_available' => null,
            'pool_remaining' => null,
            'quota' => $quota,
            'quota_used' => $priceUsed,
            'quota_available' => $quota === null ? null : max(0, $quota - $priceUsed),
            'quota_remaining' => $quota === null ? null : max(0, $quota - $priceUsed),
            'conditions' => array(
                'min_quantity' => (int) $price['min_quantity'],
                'max_quantity' => $price['max_quantity'] === null ? null : (int) $price['max_quantity'],
                'available_from' => $price['available_from'],
                'available_until' => $price['available_until'],
                'min_age' => $price['min_age'] === null ? null : (int) $price['min_age'],
                'max_age' => $price['max_age'] === null ? null : (int) $price['max_age'],
                'access_level_id' => $price['access_level_id'] === null ? null : (int) $price['access_level_id'],
                'user_group_id' => $price['user_group_id'] === null ? null : (int) $price['user_group_id'],
                'verification_mode' => (string) $price['verification_mode'],
            ),
        );
    }

    private function quoteFingerprint(array $quote): string
    {
        $lineFields = array_fill_keys(array(
            'event_price_id', 'capacity_pool_id', 'code', 'name', 'description', 'quantity',
            'price_includes_tax',
            'unit_net', 'unit_tax', 'unit_gross', 'line_net', 'line_tax', 'line_gross',
            'tax_code', 'tax_name', 'tax_type', 'tax_rate', 'conditions',
        ), true);
        $lines = array_map(
            static fn (array $line): array => array_intersect_key($line, $lineFields),
            (array) ($quote['lines'] ?? array())
        );
        $canonical = array(
            'schema' => self::SCHEMA,
            'event_id' => (int) $quote['event_id'],
            'pricing_mode' => (string) ($quote['pricing_mode'] ?? ''),
            'pricing_revision' => (int) $quote['pricing_revision'],
            'currency' => (string) $quote['currency'],
            'prices_include_tax' => (int) $quote['prices_include_tax'],
            'quantity' => (int) $quote['quantity'],
            'subtotal_net' => (string) $quote['subtotal_net'],
            'tax_total' => (string) $quote['tax_total'],
            'grand_total' => (string) $quote['grand_total'],
            'lines' => $lines,
        );

        return hash(
            'sha256',
            json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );
    }

    private function normaliseDateTime(string $value): string
    {
        $value = trim($value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('Quote time must use UTC Y-m-d H:i:s format.');
        }

        return $date->format('Y-m-d H:i:s');
    }
}
