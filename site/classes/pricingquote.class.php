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
 * Authoritative Point 4E quote, eligibility and capacity service.
 *
 * The service deliberately does not create registrations or commercial lines.
 * Phase 4F consumes the immutable quote returned here inside the same database
 * transaction used for the registration transition.
 */
final class JemPricingQuoteService
{
    public const SCHEMA = 'jem-pricing-quote/v1';

    public function __construct(private readonly DatabaseDriver $db)
    {
    }

    /**
     * Recalculate an event quote from selected price IDs and quantities.
     *
     * @param int   $eventId Event identifier.
     * @param array $selections Each row contains event_price_id and quantity.
     * @param array $context expectedPricingRevision, accessLevels, userGroups
     *                       and an optional server/test now value (UTC).
     */
    public function quote(int $eventId, array $selections, array $context = array()): array
    {
        return $this->buildQuote($eventId, $selections, $context, false);
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
        array $context,
        string $operationReference,
        callable $operation,
        ?callable $idempotencyLookup = null
    ): mixed {
        $operationReference = trim($operationReference);
        if (!JemRegistrationIdentity::isOperationReference($operationReference)) {
            throw new InvalidArgumentException('A valid pricing operation reference is required.');
        }

        $this->db->transactionStart();
        try {
            $this->lockEventReference($eventId);
            if ($idempotencyLookup !== null) {
                $existing = $idempotencyLookup($operationReference);
                if ($existing !== null) {
                    $this->db->transactionCommit();

                    return $existing;
                }
            }

            $quote = $this->buildQuote($eventId, $selections, $context, true);
            $result = $operation($quote, $operationReference);
            $this->db->transactionCommit();

            return $result;
        } catch (Throwable $error) {
            $this->db->transactionRollback();
            throw $error;
        }
    }

    private function buildQuote(int $eventId, array $selections, array $context, bool $lock): array
    {
        if ($eventId < 1) {
            throw new InvalidArgumentException('A valid priced event is required.');
        }

        $event = $this->loadEvent($eventId, $lock);
        if (!$event) {
            throw new RuntimeException('The priced event does not exist.');
        }
        if (!in_array((string) $event['pricing_mode'], array('single', 'multiple', 'priced'), true)) {
            throw new InvalidArgumentException('Classic events do not use the pricing quote service.');
        }

        $expectedRevision = $this->positiveInteger(
            $context['expectedPricingRevision'] ?? null,
            'Pricing revision'
        );
        if ($expectedRevision !== (int) $event['pricing_revision']) {
            throw new RuntimeException('The event pricing has changed. Refresh the quote before confirming.');
        }

        $selectionByPrice = $this->normaliseSelections($selections);
        $prices = $this->loadPrices($eventId, array_keys($selectionByPrice), $lock);
        if (count($prices) !== count($selectionByPrice)) {
            throw new InvalidArgumentException('A selected price is unavailable for this event.');
        }

        $poolIds = array_values(array_unique(array_filter(array_map(
            static fn (array $price): int => (int) ($price['capacity_pool_id'] ?? 0),
            $prices
        ))));
        $pools = $this->loadPools($eventId, $poolIds, $lock);
        if (count($pools) !== count($poolIds)) {
            throw new InvalidArgumentException('A selected price capacity pool is unavailable.');
        }
        $poolsById = array_column($pools, null, 'id');

        $taxIds = array_values(array_unique(array_map(
            static fn (array $price): int => (int) $price['tax_rate_id'],
            $prices
        )));
        $taxRates = $this->loadTaxRates($taxIds, $lock);
        if (count($taxRates) !== count($taxIds)) {
            throw new InvalidArgumentException('A selected tax rate is unavailable.');
        }
        $taxRatesById = array_column($taxRates, null, 'id');

        $now = $this->normaliseDateTime((string) ($context['now'] ?? gmdate('Y-m-d H:i:s')));
        $accessLevels = array_fill_keys(array_map('intval', (array) ($context['accessLevels'] ?? array())), true);
        $userGroups = array_fill_keys(array_map('intval', (array) ($context['userGroups'] ?? array())), true);
        $used = $this->loadUsedCapacity($eventId);
        $eventQuantity = 0;
        $requestedByPool = array();
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
                    throw new InvalidArgumentException('A selected price capacity pool is unavailable.');
                }
                $requestedByPool[$poolId] = ($requestedByPool[$poolId] ?? 0) + $quantity;
            }

            $priceUsed = (int) ($used['prices'][$priceId] ?? 0);
            if ($price['quota'] !== null && $priceUsed + $quantity > (int) $price['quota']) {
                throw new RuntimeException('A selected price quota would be exceeded.');
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
            $lines[] = $this->quoteLine($price, $tax, $calculation, $used, $poolsById);
        }

        $eventUsed = (int) $event['reservedplaces'] + (int) $used['event'];
        if ((int) $event['maxplaces'] > 0 && $eventUsed + $eventQuantity > (int) $event['maxplaces']) {
            throw new RuntimeException('Event capacity would be exceeded.');
        }
        foreach ($requestedByPool as $poolId => $quantity) {
            $poolUsed = (int) ($used['pools'][$poolId] ?? 0);
            if ($poolUsed + $quantity > (int) $poolsById[$poolId]['capacity']) {
                throw new RuntimeException('A selected capacity pool would be exceeded.');
            }
        }
        foreach ($lines as &$line) {
            $poolId = (int) ($line['capacity_pool_id'] ?? 0);
            if ($poolId > 0) {
                $line['pool_remaining'] = max(
                    0,
                    (int) $poolsById[$poolId]['capacity']
                    - (int) ($used['pools'][$poolId] ?? 0)
                    - (int) ($requestedByPool[$poolId] ?? 0)
                );
            }
        }
        unset($line);

        return array(
            'schema' => self::SCHEMA,
            'event_id' => $eventId,
            'pricing_revision' => (int) $event['pricing_revision'],
            'currency' => (string) $event['currency'],
            'prices_include_tax' => (int) $event['prices_include_tax'],
            'quantity' => $eventQuantity,
            'event_capacity' => (int) $event['maxplaces'],
            'event_used' => $eventUsed,
            'event_remaining' => max(0, (int) $event['maxplaces'] - $eventUsed - $eventQuantity),
            'subtotal_net' => $subtotalNet->decimal(),
            'tax_total' => $taxTotal->decimal(),
            'grand_total' => $grandTotal->decimal(),
            'quoted_at' => $now,
            'lines' => $lines,
        );
    }

    private function loadEvent(int $eventId, bool $lock): ?array
    {
        $query = $this->db->getQuery(true)
            ->select(array(
                'e.id', 'e.pricing_mode', 'e.pricing_revision', 'e.currency',
                'e.prices_include_tax', 'e.maxplaces', 'e.reservedplaces',
                'e.dates', 'v.country AS country_code',
            ))
            ->from($this->db->quoteName('#__jem_events', 'e'))
            ->join('LEFT', $this->db->quoteName('#__jem_venues', 'v') . ' ON v.id = e.locid')
            ->where('e.id = ' . $eventId);
        $this->db->setQuery((string) $query . ($lock ? ' FOR UPDATE' : ''));

        return $this->db->loadAssoc() ?: null;
    }

    private function lockEventReference(int $eventId): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__jem_events'))
            ->where($this->db->quoteName('id') . ' = ' . $eventId);
        $this->db->setQuery((string) $query . ' FOR UPDATE');
        if ((int) $this->db->loadResult() !== $eventId) {
            throw new RuntimeException('The priced event does not exist.');
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

    private function loadUsedCapacity(int $eventId): array
    {
        $currentItems = 'i.registration_revision = r.revision';
        $active = 'r.status = 1 AND r.waiting = 0';
        $eventQuery = $this->db->getQuery(true)
            ->select('COALESCE(SUM(GREATEST(r.places, 1)), 0)')
            ->from($this->db->quoteName('#__jem_register', 'r'))
            ->where('r.event = ' . $eventId)
            ->where($active);
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
                throw new InvalidArgumentException('Each price selection must be an array.');
            }
            $priceId = $this->positiveInteger($selection['event_price_id'] ?? null, 'Event price ID');
            $quantity = $this->positiveInteger($selection['quantity'] ?? null, 'Price quantity');
            if (isset($normalised[$priceId])) {
                throw new InvalidArgumentException('Price selections require unique IDs and positive quantities.');
            }
            $normalised[$priceId] = $quantity;
        }
        if (!$normalised) {
            throw new InvalidArgumentException('At least one admission price must be selected.');
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
            throw new InvalidArgumentException($label . ' must be a positive integer.');
        }
        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            throw new InvalidArgumentException($label . ' must be a positive integer.');
        }
        $maximum = (string) PHP_INT_MAX;
        if (strlen($value) > strlen($maximum)
            || (strlen($value) === strlen($maximum) && strcmp($value, $maximum) > 0)) {
            throw new InvalidArgumentException($label . ' exceeds the supported integer range.');
        }

        return (int) $value;
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
            throw new InvalidArgumentException('The selected quantity is outside the price limits.');
        }
        if (($price['available_from'] !== null && $price['available_from'] > $now)
            || ($price['available_until'] !== null && $price['available_until'] < $now)) {
            throw new InvalidArgumentException('The selected price is outside its sale window.');
        }
        $accessId = (int) ($price['access_level_id'] ?? 0);
        if ($accessId > 0 && !isset($accessLevels[$accessId])) {
            throw new InvalidArgumentException('The booking holder cannot access the selected price.');
        }
        $groupId = (int) ($price['user_group_id'] ?? 0);
        if ($groupId > 0 && !isset($userGroups[$groupId])) {
            throw new InvalidArgumentException('The booking holder is not in the required price group.');
        }

        // Point 4 snapshots age bands as commercial conditions. Per-attendee
        // date-of-birth validation belongs to the later nominative phase.
    }

    private function assertTaxApplicable(array $tax, array $event, string $now): void
    {
        $country = strtoupper(trim((string) ($event['country_code'] ?? '')));
        $taxCountry = strtoupper(trim((string) ($tax['country_code'] ?? '')));
        if ($taxCountry !== '' && $taxCountry !== $country) {
            throw new InvalidArgumentException('A selected tax rate does not apply to the event country.');
        }
        $date = trim((string) ($event['dates'] ?? '')) ?: substr($now, 0, 10);
        if (($tax['valid_from'] !== null && $tax['valid_from'] !== '0000-00-00' && $tax['valid_from'] > $date)
            || ($tax['valid_until'] !== null && $tax['valid_until'] !== '0000-00-00' && $tax['valid_until'] < $date)) {
            throw new InvalidArgumentException('A selected tax rate is not valid on the event date.');
        }
    }

    private function quoteLine(array $price, array $tax, JemTaxCalculation $calculation, array $used, array $pools): array
    {
        $poolId = (int) ($price['capacity_pool_id'] ?? 0);
        $poolCapacity = $poolId > 0 ? (int) $pools[$poolId]['capacity'] : null;
        $poolUsed = $poolId > 0 ? (int) ($used['pools'][$poolId] ?? 0) : null;
        $quota = $price['quota'] === null ? null : (int) $price['quota'];
        $priceUsed = (int) ($used['prices'][(int) $price['id']] ?? 0);

        return array(
            'event_price_id' => (int) $price['id'],
            'capacity_pool_id' => $poolId ?: null,
            'code' => (string) $price['code'],
            'name' => (string) $price['name'],
            'quantity' => $calculation->quantity,
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
            'pool_remaining' => $poolCapacity === null ? null : max(0, $poolCapacity - (int) $poolUsed - $calculation->quantity),
            'quota_remaining' => $quota === null ? null : max(0, $quota - $priceUsed - $calculation->quantity),
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
