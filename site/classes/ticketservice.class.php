<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

require_once __DIR__ . '/registrationidentity.class.php';
require_once __DIR__ . '/ticketpolicy.class.php';

/** Shared cashier/admission writer. All writes lock event then registration. */
final class JemTicketService
{
    private object $db;

    /** @param object $db Joomla database connection. */
    public function __construct(object $db)
    {
        $this->db = $db;
    }

    /**
     * Check a dedicated operation permission, optionally scoped to event categories.
     * @param object $user Current Joomla identity.
     * @param string $action pay or admit.
     * @param array $categories Event category IDs.
     * @return bool Whether this operation is authorised.
     */
    public static function can(object $user, string $action, array $categories = []): bool
    {
        if (!(int) $user->id || !in_array($action, ['pay', 'admit'], true)) {
            return false;
        }
        if (!$categories) {
            return $user->authorise('jem.tickets.' . $action, 'com_jem');
        }
        foreach ($categories as $id) {
            if ($user->authorise('jem.tickets.' . $action, 'com_jem.category.' . (int) $id)) {
                return true;
            }
        }
        return false;
    }

    /** @param int $eventId Event ID. @return int[] Assigned categories. */
    private function categories(int $eventId): array
    {
        return array_map('intval', $this->db->setQuery('SELECT catid FROM #__jem_cats_event_relations WHERE itemid=' . $eventId)->loadColumn());
    }

    /**
     * Find a reservation only in the selected event and within the operator's scope.
     * @param string $reference Opaque locator, never an authorisation credential.
     * @param int $eventId Selected gate/event.
     * @param object $user Current operator.
     * @return array Safe display data and a concurrency version.
     */
    public function lookup(string $reference, int $eventId, object $user): array
    {
        [$event, $row, $rights] = $this->load($reference, $eventId, $user);
        return $this->snapshot($event, $row, $rights);
    }

    /**
     * Load event, identity and reservation without leaking unauthorised records.
     * @param string $reference Reservation reference.
     * @param int $eventId Event chosen by the operator.
     * @param object $user Current identity.
     * @param bool $owner Allow the owner to download their own PDF.
     * @return array Event, reservation and permitted operations.
     */
    private function load(string $reference, int $eventId, object $user, bool $owner = false): array
    {
        if (!(int) $user->id || $eventId < 1 || !JemRegistrationIdentity::isRegistrationReference($reference)) {
            throw new DomainException('COM_JEM_TK_NOT_FOUND');
        }
        $categories = $this->categories($eventId);
        $rights = ['pay' => self::can($user, 'pay', $categories), 'admit' => self::can($user, 'admit', $categories)];
        if (!$owner && !array_filter($rights)) {
            throw new DomainException('JERROR_ALERTNOAUTHOR');
        }
        $db = $this->db;
        $event = $db->setQuery('SELECT id,title,dates,times,published,event_status,locid FROM #__jem_events WHERE id=' . $eventId)->loadObject();
        $row = $db->setQuery('SELECT * FROM #__jem_register WHERE event=' . $eventId . ' AND reference=' . $db->quote($reference))->loadObject();
        if (!$event || !$row || ($owner && (int) $row->uid !== (int) $user->id && !array_filter($rights))) {
            throw new DomainException('COM_JEM_TK_NOT_FOUND');
        }
        return [$event, $row, $rights];
    }

    /**
     * Read durable collection and admission history for a reservation.
     * @param string $reference Immutable reservation reference.
     * @return array Current accumulated money, places and last operation ID.
     */
    private function ledger(string $reference): array
    {
        $rows = $this->db->setQuery('SELECT * FROM #__jem_ticket_operations WHERE registration_reference='
            . $this->db->quote($reference) . ' ORDER BY id')->loadObjectList();
        $result = ['paid' => 0, 'entered' => 0, 'last' => 0, 'pools' => [], 'history' => [], 'currency' => ''];
        foreach ($rows as $row) {
            $result['last'] = (int) $row->id;
            if ($row->action === 'payment') {
                if ($result['currency'] !== '' && $result['currency'] !== $row->currency) {
                    throw new DomainException('COM_JEM_TK_CURRENCY_CHANGED');
                }
                $result['currency'] = $row->currency;
                $result['paid'] += JemTicketPolicy::cents($row->amount);
            } else {
                $result['entered'] += (int) $row->quantity;
                foreach (json_decode($row->allocations, true, 512, JSON_THROW_ON_ERROR) as $pool => $quantity) {
                    $result['pools'][$pool] = ($result['pools'][$pool] ?? 0) + (int) $quantity;
                }
            }
            $result['history'][] = ['action' => $row->action, 'quantity' => (int) $row->quantity,
                'amount' => $row->amount, 'currency' => $row->currency, 'occurred' => $row->occurred,
                'operator' => (int) $row->actor_user_id, 'method' => $row->method];
        }
        $result['history'] = array_slice($result['history'], -20);
        return $result;
    }

    /**
     * Resolve current physical allocations, then commercial pools, then general admission.
     * @param object $row Current reservation revision.
     * @return array Pool labels and reserved quantities.
     */
    private function pools(object $row): array
    {
        $db = $this->db;
        $where = 'register_id=' . (int) $row->id . ' AND registration_revision=' . (int) $row->revision;
        $pools = [];
        foreach ($db->setQuery('SELECT * FROM #__jem_register_capacity_allocations WHERE ' . $where)->loadObjectList() as $area) {
            $key = (int) $area->venue_capacity_area_id ? 'area:' . $area->venue_capacity_area_id : 'layout:' . $area->venue_layout_id;
            $pools[$key] = ['name' => trim($area->space_name . ' / ' . $area->area_name, ' /'), 'quantity' => (int) $area->quantity];
        }
        if (!$pools && $row->pricing_mode !== 'classic') {
            foreach ($db->setQuery("SELECT capacity_pool_id,MAX(item_name) AS name,SUM(quantity) AS quantity FROM #__jem_register_items WHERE "
                . $where . " AND line_kind='admission' GROUP BY capacity_pool_id")->loadObjectList() as $pool) {
                $pools['pool:' . (int) $pool->capacity_pool_id] = ['name' => $pool->name, 'quantity' => (int) $pool->quantity];
            }
        }
        return $pools ?: ['general' => ['name' => 'COM_JEM_TK_GENERAL', 'quantity' => (int) $row->places]];
    }

    /**
     * Compose lookup data from the live registration and append-only operations.
     * @param object $event Current event.
     * @param object $row Current reservation.
     * @param array $rights Operator permissions.
     * @return array Locator, monetary state, allocations and audit information.
     */
    private function snapshot(object $event, object $row, array $rights): array
    {
        $ledger = $this->ledger($row->reference);
        if ($ledger['paid'] && $ledger['currency'] !== $row->currency) {
            throw new DomainException('COM_JEM_TK_CURRENCY_CHANGED');
        }
        if (!$ledger['paid'] && !in_array($row->payment_state, [null, '', 'free', 'unpaid'], true)) {
            throw new DomainException('COM_JEM_TK_SETTLEMENT_REVIEW');
        }
        $payment = JemTicketPolicy::payment($row, $ledger['paid']);
        $pools = $this->pools($row);
        foreach ($pools as $key => &$pool) {
            $pool['entered'] = $ledger['pools'][$key] ?? 0;
            $pool['remaining'] = max(0, min($pool['quantity'] - $pool['entered'], (int) $row->places - $ledger['entered']));
        }
        unset($pool);
        return ['reference' => $row->reference, 'event_id' => (int) $event->id, 'event' => $event->title,
            'date' => $event->dates, 'time' => $event->times, 'places' => (int) $row->places,
            'confirmed' => (int) $row->status === 1 && !(int) $row->waiting,
            'event_open' => (int) $event->published === 1 && !in_array($event->event_status, ['cancelled', 'postponed'], true),
            'holder' => (string) $this->db->setQuery('SELECT name FROM #__users WHERE id=' . (int) $row->uid)->loadResult(),
            'currency' => $row->currency, 'payment' => $payment, 'entered' => $ledger['entered'],
            'remaining' => max(0, (int) $row->places - $ledger['entered']), 'pools' => $pools, 'rights' => $rights,
            'blocked' => JemTicketPolicy::blocked($event, $row, $payment, $ledger['entered']),
            'version' => (int) $row->revision . ':' . $ledger['last'], 'history' => $ledger['history'],
            'operation' => JemRegistrationIdentity::generateOperationReference()];
    }

    /**
     * Record collection or admission exactly once, with authoritative validation.
     * @param string $action payment or admission.
     * @param array $input Submitted locator, event, version, operation and operation details.
     * @param object $user Authenticated operator.
     * @return array Updated lookup data, including duplicate acknowledgement.
     */
    public function execute(string $action, array $input, object $user): array
    {
        if (!in_array($action, ['payment', 'admission'], true)
            || !JemRegistrationIdentity::isOperationReference($input['operation'] ?? '')) {
            throw new DomainException('COM_JEM_TK_INVALID_OPERATION');
        }
        $reference = (string) ($input['reference'] ?? '');
        $eventId = (int) ($input['event_id'] ?? 0);
        $permission = $action === 'payment' ? 'pay' : 'admit';
        $canonical = ['action' => $action, 'event' => $eventId, 'reference' => $reference,
            'quantity' => $input['quantity'] ?? '', 'pool' => $input['pool'] ?? '',
            'amount' => $input['amount'] ?? '', 'currency' => $input['currency'] ?? '',
            'method' => $input['method'] ?? '', 'receipt' => $input['receipt'] ?? ''];
        $fingerprint = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
        // Authorise before acquiring locks; recheck after locking against current categories.
        $this->load($reference, $eventId, $user);
        $db = $this->db;
        $db->transactionStart();
        try {
            $db->setQuery('SELECT id FROM #__jem_events WHERE id=' . $eventId . ' FOR UPDATE')->loadResult();
            $db->setQuery('SELECT id FROM #__jem_register WHERE event=' . $eventId . ' AND reference=' . $db->quote($reference) . ' FOR UPDATE')->loadResult();
            [$event, $row, $rights] = $this->load($reference, $eventId, $user);
            if (!$rights[$permission]) {
                throw new DomainException('JERROR_ALERTNOAUTHOR');
            }
            $prior = $db->setQuery('SELECT * FROM #__jem_ticket_operations WHERE operation_reference=' . $db->quote($input['operation']))->loadObject();
            $snapshot = $this->snapshot($event, $row, $rights);
            if ($prior) {
                if ($prior->fingerprint !== $fingerprint || (int) $prior->actor_user_id !== (int) $user->id) {
                    throw new DomainException('COM_JEM_TK_INVALID_OPERATION');
                }
                $db->transactionCommit();
                return $snapshot + ['duplicate' => true];
            }
            if (($input['version'] ?? '') !== $snapshot['version']) {
                throw new DomainException('COM_JEM_TK_STALE');
            }
            if (!$snapshot['event_open'] || !$snapshot['confirmed']) {
                throw new DomainException('COM_JEM_TK_NOT_CONFIRMED');
            }
            $operation = (object) ['operation_reference' => $input['operation'], 'fingerprint' => $fingerprint,
                'registration_id' => (int) $row->id, 'registration_reference' => $reference,
                'registration_revision' => (int) $row->revision, 'event_id' => $eventId, 'event_title' => $event->title,
                'action' => $action, 'quantity' => 0, 'allocations' => '{}', 'amount' => null,
                'currency' => '', 'method' => '', 'external_reference' => '', 'actor_user_id' => (int) $user->id,
                'occurred' => gmdate('Y-m-d H:i:s')];
            if ($action === 'payment') {
                $amount = JemTicketPolicy::cents((string) ($input['amount'] ?? ''));
                if ($amount < 1 || $snapshot['payment']['due'] !== $amount || ($input['currency'] ?? '') !== $row->currency
                    || !in_array($input['method'] ?? '', ['cash', 'bank_transfer'], true)) {
                    throw new DomainException('COM_JEM_TK_INVALID_AMOUNT');
                }
                $receipt = trim((string) ($input['receipt'] ?? ''));
                if (strlen($receipt) > 255 || preg_match('/[\x00-\x1f\x7f]/', $receipt)
                    || ($input['method'] === 'bank_transfer' && $receipt === '')) {
                    throw new DomainException('COM_JEM_TK_RECEIPT_REQUIRED');
                }
                $operation->amount = JemTicketPolicy::money($amount);
                $operation->currency = $row->currency;
                $operation->method = $input['method'];
                $operation->external_reference = $receipt;
            } else {
                if ($snapshot['blocked'] !== '') {
                    throw new DomainException($snapshot['blocked']);
                }
                $raw = (string) ($input['quantity'] ?? '');
                $pool = (string) ($input['pool'] ?? '');
                if (!preg_match('/^[1-9][0-9]{0,7}$/D', $raw) || !isset($snapshot['pools'][$pool])
                    || (int) $raw > $snapshot['pools'][$pool]['remaining'] || (int) $raw > $snapshot['remaining']) {
                    throw new DomainException('COM_JEM_TK_INVALID_QUANTITY');
                }
                $operation->quantity = (int) $raw;
                $operation->allocations = json_encode([$pool => (int) $raw], JSON_THROW_ON_ERROR);
            }
            $db->insertObject('#__jem_ticket_operations', $operation);
            if ($action === 'payment') {
                $db->setQuery('UPDATE #__jem_register SET payment_state=' . $db->quote('paid')
                    . ', external_payment_reference=' . $db->quote($operation->external_reference)
                    . ' WHERE id=' . (int) $row->id)->execute();
            }
            $result = $this->snapshot($event, $row, $rights);
            $db->transactionCommit();
            return $result + ['duplicate' => false];
        } catch (Throwable $error) {
            $db->transactionRollback();
            throw $error;
        }
    }

    /**
     * Keep real settlement history across booking edits; do not invent a refund.
     * Called by the registration writer while it owns event and registration locks.
     * @param object $before Existing reservation.
     * @param object $after Proposed reservation.
     * @return void
     */
    public function reconcile(object $before, object $after): void
    {
        $ledger = $this->ledger($before->reference);
        if (!$ledger['paid'] && !$ledger['entered']) {
            return;
        }
        if ((int) $before->uid !== (int) $after->uid || ($ledger['paid'] && $before->currency !== $after->currency)) {
            throw new DomainException('COM_JEM_TK_IDENTITY_LOCKED');
        }
        if ($ledger['paid']) {
            $previous = JemTicketPolicy::payment($before, $ledger['paid']);
            $next = JemTicketPolicy::payment($after, $ledger['paid']);
            if (((int) $before->status === -1 && (int) $after->status !== -1)
                || ($previous['refund'] > 0 && $next['total'] > $previous['total'])) {
                // No refund settlement workflow exists yet; do not recycle possibly refunded money.
                throw new DomainException('COM_JEM_TK_SETTLEMENT_REVIEW');
            }
            $after->payment_state = JemTicketPolicy::payment($after, $ledger['paid'])['state'];
            $after->external_payment_reference = $before->external_payment_reference;
        }
    }

    /**
     * Load private PDF data for an authenticated owner or authorised operator.
     * @param string $reference Reservation locator.
     * @param int $eventId Event ID.
     * @param object $user Current identity.
     * @return array Safe PDF snapshot.
     */
    public function document(string $reference, int $eventId, object $user): array
    {
        [$event, $row, $rights] = $this->load($reference, $eventId, $user, true);
        if ((int) $row->status !== 1 || (int) $row->waiting) {
            throw new DomainException('COM_JEM_TK_NOT_CONFIRMED');
        }
        return $this->snapshot($event, $row, $rights);
    }
}
