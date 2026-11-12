<?php
/** @package JEM @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\UserFactoryInterface;

require_once __DIR__ . '/registrationidentity.class.php';
require_once __DIR__ . '/pricedregistration.class.php';
require_once __DIR__ . '/relocationpolicy.class.php';
require_once __DIR__ . '/featurepolicy.class.php';

/** Reviewed, atomic relocation within one event; no new reservation or payment records. */
final class JemRegistrationRelocation
{
    /** @param object $db Joomla database connection used for reviews and atomic writes. */
    public function __construct(private readonly object $db)
    {
    }

    /** @param object $db Installer connection. @return void Repeatable migration for earlier 5.1 betas. */
    public static function ensureSchema(object $db): void
    {
        if (!isset($db->getTableColumns('#__jem_register_history')['change_snapshot'])) {
            $db->setQuery('ALTER TABLE #__jem_register_history ADD change_snapshot MEDIUMTEXT NULL DEFAULT NULL')->execute();
        }
    }

    /**
     * Build a server-owned review from current inventory, prices and collected payments.
     * @param int $id Existing reservation ID.
     * @param array $input Quantities, reason and economic decision; no client totals.
     * @param object $actor Authenticated managing user.
     * @return array Review suitable for display and later fingerprint verification.
     */
    public function preview(int $id, array $input, object $actor): array
    {
        return $this->prepare($id, $input, $actor)['review'];
    }

    /**
     * Rebuild and commit exactly the reviewed change under event/registration locks.
     * @param int $id Existing reservation ID.
     * @param array $input Server-retained reviewed input.
     * @param object $actor Authenticated managing user.
     * @param string $fingerprint Expected complete review fingerprint.
     * @param string $operation Idempotency reference retained with the review.
     * @return object Committed registration result and immutable change snapshot.
     */
    public function confirm(int $id, array $input, object $actor, string $fingerprint, string $operation): object
    {
        $this->assertManager($actor);
        if (!JemRegistrationIdentity::isOperationReference($operation)) {
            throw new DomainException('COM_JEM_RELOCATION_REVIEW_EXPIRED');
        }
        $eventId = (int) $this->db->setQuery('SELECT event FROM #__jem_register WHERE id=' . $id)->loadResult();
        $this->db->transactionStart();
        try {
            $this->db->setQuery('SELECT id FROM #__jem_events WHERE id=' . $eventId . ' FOR UPDATE')->loadResult();
            $this->db->setQuery('SELECT id FROM #__jem_register WHERE id=' . $id . ' FOR UPDATE')->loadResult();
            $previous = $this->db->setQuery('SELECT * FROM #__jem_register_history WHERE registration_id=' . $id
                . ' AND operation_reference=' . $this->db->quote($operation))->loadObject();
            if ($previous) {
                $snapshot = json_decode((string) $previous->change_snapshot, true);
                if ((int) $previous->actor_user_id !== (int) $actor->id
                    || !hash_equals((string) ($snapshot['fingerprint'] ?? ''), $fingerprint)) {
                    throw new DomainException('COM_JEM_RELOCATION_REVIEW_CHANGED');
                }
                $row = $this->db->setQuery('SELECT * FROM #__jem_register WHERE id=' . $id)->loadObject();
                $this->db->transactionCommit();
                return (object) ['after' => $row, 'snapshot' => json_decode($previous->change_snapshot, true), 'replayed' => true];
            }
            // Event edits normally share the event lock. Lock their price/pool/tax rows as well
            // so the reviewed destination terms cannot change between calculation and persistence.
            $this->db->setQuery('SELECT id FROM #__jem_event_prices WHERE event_id=' . $eventId . ' ORDER BY id FOR UPDATE')->loadColumn();
            $this->db->setQuery('SELECT id FROM #__jem_capacity_pools WHERE event_id=' . $eventId . ' ORDER BY id FOR UPDATE')->loadColumn();
            $this->db->setQuery('SELECT id FROM #__jem_tax_rates WHERE id IN (SELECT tax_rate_id FROM #__jem_event_prices WHERE event_id='
                . $eventId . ') ORDER BY id FOR UPDATE')->loadColumn();
            $prepared = $this->prepare($id, $input, $actor);
            $review = $prepared['review'];
            if (!hash_equals($review['fingerprint'], $fingerprint)) {
                throw new DomainException('COM_JEM_RELOCATION_REVIEW_CHANGED');
            }
            $row = $prepared['before'];
            $after = clone $row;
            $after->places = $review['new_places'];
            $options = ['actorId' => (int) $actor->id, 'source' => 'administrator.attendee.relocation',
                'action' => 'relocated', 'reasonCode' => $review['financial']['choice'],
                'operationReference' => $operation, 'expectedRevision' => (int) $row->revision,
                'requireExisting' => true, 'respectPlaces' => true, 'allowWaiting' => false,
                'capacityAllocations' => $prepared['allocations'], 'forceRevision' => $prepared['quote'] !== null,
                'changeSnapshot' => $review];
            if ($prepared['quote'] !== null) {
                $quote = $prepared['quote'];
                foreach (['currency', 'subtotal_net', 'tax_total', 'grand_total'] as $field) {
                    $after->$field = $quote[$field];
                }
                $options['commercialLines'] = (new JemPricedRegistrationService($this->db))->quoteItems($quote);
                $after->payment_state = JemTicketPolicy::payment($after, $review['financial']['paid'])['state'];
            }
            $result = (new JemRegistrationService($this->db))->saveLocked($row, $after, $options);
            $this->db->transactionCommit();
            $result->snapshot = $review;
            $result->replayed = false;
            return $result;
        } catch (Throwable $exception) {
            $this->db->transactionRollback();
            throw $exception;
        }
    }

    /**
     * Keep backend area changes in the reviewed workflow, while allowing notes/status edits.
     * @param object $db Database connection.
     * @param object|null $before Stored reservation.
     * @param int $status Requested logical status.
     * @param array $allocations Proposed physical quantities.
     * @return void Throws only when a confirmed allocation changes outside this workflow.
     */
    public static function requireReview(object $db, ?object $before, int $status, array $allocations): void
    {
        if (!$before || (int) $before->status !== 1 || !empty($before->waiting) || $status !== 1) {
            return;
        }
        $capacity = (new JemRegistrationService($db))->capacityOptions((int) $before->event, (int) $before->id);
        if (!$capacity->enabled) {
            return;
        }
        $after = [];
        foreach ($allocations as $key => $value) {
            $quantity = JemRegistrationQuantity::parseOptional($value);
            if ($quantity > 0) {
                $after[ctype_digit((string) $key) ? 'area:' . $key : $key] = $quantity;
            }
        }
        $current = $capacity->current;
        ksort($current);
        ksort($after);
        if ($current !== $after) {
            throw new DomainException('COM_JEM_RELOCATION_REVIEW_REQUIRED');
        }
    }

    /** @param object $actor Authenticated operator. @return void Enforce management permission at the service boundary. */
    private function assertManager(object $actor): void
    {
        if (empty($actor->id) || (!$actor->authorise('core.admin', 'com_jem') && !$actor->authorise('jem.attendees.manage', 'com_jem'))) {
            throw new DomainException('JERROR_ALERTNOAUTHOR');
        }
    }

    /** @param int $id Reservation ID. @param array $input Review choices. @param object $actor Operator. @return array Review plus private writer inputs. */
    private function prepare(int $id, array $input, object $actor): array
    {
        $this->assertManager($actor);
        if (!isset($this->db->getTableColumns('#__jem_register_history')['change_snapshot'])) {
            throw new DomainException('COM_JEM_RELOCATION_SCHEMA_REQUIRED');
        }
        $row = $this->db->setQuery('SELECT * FROM #__jem_register WHERE id=' . $id)->loadObject();
        if (!$row || (int) $row->status !== 1 || (int) $row->waiting || (int) $row->places < 1) {
            throw new DomainException('COM_JEM_RELOCATION_CONFIRMED_ONLY');
        }
        $event = $this->db->setQuery('SELECT * FROM #__jem_events WHERE id=' . (int) $row->event)->loadObject();
        if (!$event || (int) $event->published !== 1 || JemRegistrationAccessPolicy::eventStatusMessage($event) !== '') {
            throw new DomainException('COM_JEM_RELOCATION_EVENT_CLOSED');
        }
        $reason = trim(strip_tags((string) ($input['reason'] ?? '')));
        if ($reason === '' || strlen($reason) > 1000) {
            throw new DomainException('COM_JEM_RELOCATION_REASON_REQUIRED');
        }
        $ledger = $this->db->setQuery('SELECT * FROM #__jem_ticket_operations WHERE registration_reference='
            . $this->db->quote($row->reference) . ' ORDER BY id')->loadObjectList();
        $paid = 0;
        foreach ($ledger as $entry) {
            if ($entry->action !== 'payment' && (int) $entry->quantity > 0) {
                throw new DomainException('COM_JEM_RELOCATION_ALREADY_ENTERED');
            }
            if ($entry->action === 'payment') {
                if ((string) $entry->currency !== (string) $row->currency) {
                    throw new DomainException('COM_JEM_TK_CURRENCY_CHANGED');
                }
                $paid += JemTicketPolicy::cents((string) $entry->amount);
            }
        }
        $writer = new JemRegistrationService($this->db);
        $capacity = $writer->capacityOptions((int) $row->event, $id);
        if (!$capacity->enabled) {
            throw new DomainException('COM_JEM_RELOCATION_NO_AREAS');
        }
        $quote = null;
        $isPriced = in_array($row->pricing_mode, ['single', 'multiple', 'priced'], true);
        if ($isPriced) {
            if (!JemFeaturePolicy::current()->allows(JemFeaturePolicy::FEATURE_PRICING)) {
                throw new DomainException('COM_JEM_PRICED_REGISTRATION_COMMERCE_READ_ONLY');
            }
            if (empty($row->uid) || !empty($row->invitation_free)) {
                throw new DomainException('COM_JEM_RELOCATION_INVITATION_TERMS');
            }
            $owner = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById((int) $row->uid);
            $context = JemPricingQuoteContext::fromIdentity($owner, (int) $event->pricing_revision, $id);
            $selections = [];
            foreach ((array) ($input['admissions'] ?? []) as $price => $quantity) {
                if (!ctype_digit((string) $price)) {
                    throw new DomainException('COM_JEM_RELOCATION_SELECTION_INVALID');
                }
                $quantity = JemRegistrationQuantity::parseOptional($quantity);
                if ($quantity > 0) {
                    $selections[] = ['event_price_id' => (int) $price, 'quantity' => $quantity];
                }
            }
            $priced = new JemPricedRegistrationService($this->db);
            $quote = $priced->quote((int) $event->id, $selections, $context);
            $allocations = $priced->quoteCapacityAllocations($quote);
        } else {
            $allocations = [];
            foreach ((array) ($input['areas'] ?? []) as $key => $quantity) {
                $allocations[(string) $key] = JemRegistrationQuantity::parseOptional($quantity);
            }
        }
        $catalogue = array_column($capacity->options, null, 'key');
        foreach ($allocations as $key => $quantity) {
            if (!isset($catalogue[$key]) || ($quantity > (int) $catalogue[$key]['remaining']
                && $quantity > (int) ($capacity->current[$key] ?? 0))) {
                throw new DomainException('COM_JEM_REGISTRATION_CHANGE_UNAVAILABLE');
            }
        }
        $newPlaces = JemRegistrationQuantity::areaSelectionTotal($allocations, $event);
        if (!empty($row->invitation_key) && $newPlaces > (int) $row->invitation_quantity) {
            throw new DomainException('COM_JEM_INV_QUANTITY_FIXED');
        }
        if ($newPlaces > (int) $row->places && (int) $event->maxplaces > 0) {
            $others = (int) $this->db->setQuery('SELECT COALESCE(SUM(places),0) FROM #__jem_register WHERE event='
                . (int) $event->id . ' AND status=1 AND waiting=0 AND id<>' . $id)->loadResult();
            if ($others + (int) $event->reservedplaces + $newPlaces > (int) $event->maxplaces) {
                throw new DomainException('COM_JEM_REGISTRATION_CHANGE_UNAVAILABLE');
            }
        }
        $choice = (string) ($input['choice'] ?? 'standard');
        $old = $isPriced ? JemTicketPolicy::cents((string) $row->grand_total) : 0;
        $new = $isPriced ? JemTicketPolicy::cents((string) $quote['grand_total']) : 0;
        if (($old !== $new || $choice !== 'standard') && !$actor->authorise('jem.tickets.pay', 'com_jem')) {
            throw new DomainException('COM_JEM_RELOCATION_FINANCE_PERMISSION');
        }
        $financial = JemRelocationPolicy::resolve($old, $new, $paid, $choice, !empty($input['accepted']));
        if ($quote !== null && $financial['adjustment'] !== 0) {
            $quote = self::adjustQuote($quote, $financial['adjustment'], $choice);
        }
        $before = $after = [];
        foreach ($catalogue as $key => $area) {
            $label = trim($area['space_name'] . ' / ' . $area['area_name'], ' /');
            $before[] = ['key' => $key, 'label' => $label, 'quantity' => (int) ($capacity->current[$key] ?? 0)];
            $after[] = ['key' => $key, 'label' => $label, 'quantity' => (int) ($allocations[$key] ?? 0)];
        }
        if ($before === $after && $old === $financial['total']) {
            throw new DomainException('COM_JEM_RELOCATION_NO_CHANGE');
        }
        $review = ['schema' => 'jem-registration-relocation/v1', 'id' => $id, 'event_id' => (int) $event->id,
            'event_title' => $event->title, 'reference' => $row->reference, 'revision' => (int) $row->revision,
            'actor_id' => (int) $actor->id, 'reason' => $reason, 'old_places' => (int) $row->places,
            'new_places' => $newPlaces, 'before' => $before, 'after' => $after, 'financial' => $financial,
            'currency' => $isPriced ? $row->currency : '', 'quote' => $quote['quote_fingerprint'] ?? '',
            'ledger_revision' => $ledger ? (int) end($ledger)->id : 0];
        $review['fingerprint'] = hash('sha256', json_encode($review, JSON_THROW_ON_ERROR));
        return ['review' => $review, 'before' => $row, 'quote' => $quote, 'allocations' => $allocations];
    }

    /**
     * Add explicit signed commercial adjustment lines using the destination tax groups.
     * @param array $quote Server-calculated quote.
     * @param int $adjustment Signed gross amount in cents.
     * @param string $reason Authorised adjustment reason.
     * @return array Quote with exact net/tax/gross totals and immutable adjustment lines.
     */
    public static function adjustQuote(array $quote, int $adjustment, string $reason): array
    {
        $groups = [];
        foreach ($quote['lines'] as $line) {
            $key = $line['tax_type'] . '|' . $line['tax_rate'];
            $groups[$key] ??= ['gross' => 0, 'line' => $line];
            $groups[$key]['gross'] += JemTicketPolicy::cents($line['line_gross']);
        }
        $weight = array_sum(array_column($groups, 'gross'));
        if ($weight < 1) {
            // A retained collected amount can accompany a move to complimentary admissions.
            foreach ($groups as &$group) {
                $group['gross'] = 1;
            }
            unset($group);
            $weight = count($groups);
        }
        if (!$weight) {
            throw new DomainException('COM_JEM_RELOCATION_AMOUNT_INVALID');
        }
        $remaining = abs($adjustment);
        $remainingWeight = $weight;
        foreach ($groups as $group) {
            if ($group['gross'] < 1) {
                continue;
            }
            // Split by tax group using exact integer arithmetic; final group receives rounding residue.
            $amount = JemRelocationPolicy::share($remaining, $group['gross'], $remainingWeight);
            $remaining -= $amount;
            $remainingWeight -= $group['gross'];
            if (!$amount) {
                continue;
            }
            $line = $group['line'];
            $calculation = JemTaxCalculator::calculate(JemMoney::fromMinorUnits($amount, $quote['currency']),
                new JemTaxPolicy($line['tax_type'], $line['tax_rate'], true), 1);
            $sign = $adjustment < 0 ? -1 : 1;
            $item = (object) ['line_kind' => 'relocation_adjustment', 'quantity' => 1,
                'item_code' => $reason, 'item_name' => 'Relocation adjustment', 'currency' => $quote['currency'],
                'price_includes_tax' => 1, 'tax_type' => $line['tax_type'], 'tax_rate' => $line['tax_rate'],
                'tax_code' => $line['tax_code'], 'tax_name' => $line['tax_name']];
            foreach (['net', 'tax', 'gross'] as $part) {
                $property = 'line' . ucfirst($part);
                $value = JemMoney::fromMinorUnits($sign * $calculation->$property->minorUnits(), $quote['currency']);
                $item->{'unit_' . $part} = $item->{'line_' . $part} = $value->decimal();
                $totalKey = ['net' => 'subtotal_net', 'tax' => 'tax_total', 'gross' => 'grand_total'][$part];
                $quote[$totalKey] = JemMoney::fromDecimal($quote[$totalKey], $quote['currency'])->plus($value)->decimal();
            }
            $quote['adjustment_items'][] = $item;
        }
        foreach (['subtotal_net', 'tax_total', 'grand_total'] as $key) {
            if (JemMoney::fromDecimal($quote[$key], $quote['currency'])->minorUnits() < 0) {
                throw new DomainException('COM_JEM_RELOCATION_AMOUNT_INVALID');
            }
        }
        return $quote;
    }
}
