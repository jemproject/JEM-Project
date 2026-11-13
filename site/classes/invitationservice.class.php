<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\UserFactoryInterface;

require_once __DIR__ . '/invitationpolicy.class.php';
require_once __DIR__ . '/invitationaccess.class.php';
require_once __DIR__ . '/registrationservice.class.php';
require_once __DIR__ . '/registrationidentity.class.php';
require_once __DIR__ . '/registrationtransition.class.php';
require_once __DIR__ . '/pricedregistration.class.php';

/** Invitation orchestration; all occupancy changes use the transactional registration writer. */
final class JemInvitationService
{
    /** @param object $db Joomla database connection. */
    public function __construct(private readonly object $db)
    {
    }

    /** @param object $actor Operator. @param int $eventId Event. @param string $action Permission. @return bool */
    public function can(object $actor, int $eventId, string $action = 'manage'): bool
    {
        if (empty($actor->id) || !empty($actor->block)) {
            return false;
        }
        if ($actor->authorise('jem.invitations.' . $action, 'com_jem')) {
            return true;
        }
        foreach ($this->db->setQuery('SELECT catid FROM #__jem_cats_event_relations WHERE itemid=' . $eventId)->loadColumn() as $id) {
            if ($actor->authorise('jem.invitations.' . $action, 'com_jem.category.' . (int) $id)) {
                return true;
            }
        }
        return false;
    }

    /** @param object $actor Operator. @return array Manageable events, without exposing other event metadata. */
    public function events(object $actor): array
    {
        $events = $this->db->setQuery('SELECT * FROM #__jem_events WHERE published IN (0,1) ORDER BY dates,title')->loadObjectList();
        return array_values(array_filter($events, fn ($event) => $this->can($actor, (int) $event->id)));
    }

    /**
     * Resolve one date or all current dates in its recurrence series.
     * @param int $eventId Selected event. @param string $scope single or series.
     * @return array<int,object> Dates in deterministic lock order.
     */
    public function dates(int $eventId, string $scope): array
    {
        $event = $this->db->setQuery('SELECT * FROM #__jem_events WHERE id=' . $eventId)->loadObject();
        if (!$event || !in_array($scope, ['single', 'series'], true)) {
            throw new DomainException('COM_JEM_INV_INVALID_EVENT');
        }
        $root = (int) ($event->recurrence_first_id ?: $event->id);
        $where = 'id=' . $root . ' OR recurrence_first_id=' . $root;
        if (!empty($event->series_id)) {
            $where .= ' OR series_id=' . $this->db->quote($event->series_id);
        }
        $events = $scope === 'series'
            ? $this->db->setQuery('SELECT * FROM #__jem_events WHERE (' . $where . ') ORDER BY id')->loadObjectList('id')
            : [$eventId => $event];
        return $events;
    }

    /**
     * Validate and resolve an explicit recipient snapshot, deduplicated by Joomla ID/email.
     * @param array $input Composer input. @param object $actor Operator.
     * @return array{recipients:array,excluded:array} Effective holders and exclusion reasons.
     */
    public function recipients(array $input, object $actor): array
    {
        $audience = (string) ($input['audience'] ?? 'users');
        if (!in_array($audience, ['all', 'groups', 'users'], true)) {
            throw new DomainException('COM_JEM_INV_INVALID_RECIPIENTS');
        }
        if ($audience !== 'users' && !$actor->authorise('jem.invitations.bulk', 'com_jem')) {
            throw new DomainException('JERROR_ALERTNOAUTHOR');
        }
        $ids = static function (mixed $values): array {
            $result = [];
            foreach ((array) $values as $value) {
                if (!is_scalar($value) || !preg_match('/^[1-9][0-9]*$/D', (string) $value)) {
                    throw new DomainException('COM_JEM_INV_INVALID_RECIPIENTS');
                }
                $result[] = (int) $value;
            }
            return array_values(array_unique($result));
        };
        $selected = $ids($input[$audience === 'groups' ? 'groups' : 'users'] ?? []);
        $where = '1=1';
        if ($audience === 'groups') {
            $where = $selected ? 'id IN (SELECT user_id FROM #__user_usergroup_map WHERE group_id IN (' . implode(',', $selected) . '))' : '1=0';
        } elseif ($audience === 'users') {
            $where = $selected ? 'id IN (' . implode(',', $selected) . ')' : '1=0';
        }
        $users = $this->db->setQuery('SELECT id,name,email,block,activation FROM #__users WHERE ' . $where . ' ORDER BY id')->loadObjectList();
        $recipients = [];
        $excluded = [];
        foreach ($users as $user) {
            if (!empty($user->block) || JemRegistrationService::activationRequiresVerification($user->activation)) {
                $excluded[] = ['name' => $user->name, 'reason' => 'COM_JEM_INV_INACTIVE_USER'];
                continue;
            }
            $email = JemInvitationPolicy::email($user->email);
            $recipients[$email] = ['uid' => (int) $user->id, 'name' => $user->name, 'email' => $email];
        }
        foreach (preg_split('/\R/u', trim((string) ($input['external'] ?? ''))) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parts = explode(';', $line, 2);
            if (count($parts) !== 2 || trim($parts[0]) === '') {
                throw new DomainException('COM_JEM_INV_EXTERNAL_FORMAT');
            }
            $email = JemInvitationPolicy::email($parts[1]);
            if (isset($recipients[$email])) {
                continue;
            }
            $user = $this->db->setQuery('SELECT * FROM #__users WHERE LOWER(email)=' . $this->db->quote($email))->loadObject();
            if ($user && (!empty($user->block) || JemRegistrationService::activationRequiresVerification($user->activation))) {
                $excluded[] = ['name' => $parts[0], 'reason' => 'COM_JEM_INV_INACTIVE_USER'];
                continue;
            }
            $recipients[$email] = ['uid' => $user ? (int) $user->id : null,
                'name' => $user ? $user->name : mb_substr(trim(strip_tags($parts[0])), 0, 255), 'email' => $email];
        }
        if (!$recipients) {
            throw new DomainException('COM_JEM_INV_NO_RECIPIENTS');
        }
        return ['recipients' => array_values($recipients), 'excluded' => $excluded];
    }

    /**
     * Preview or commit the same all-or-nothing operation. Preview rolls back every write.
     * @param array $input Composer input. @param object $actor Authorised organiser.
     * @param bool $send Commit and queue Mailer snapshots when true.
     * @return array Recipient/date totals, exclusions and preview fingerprint.
     */
    public function compose(array $input, object $actor, bool $send = false): array
    {
        foreach (['event_id', 'scope', 'audience', 'external', 'kind', 'quantity', 'free', 'expires', 'message', 'price', 'area', 'fingerprint'] as $field) {
            if (isset($input[$field]) && !is_scalar($input[$field])) {
                throw new DomainException('COM_JEM_INV_INVALID_RECIPIENTS');
            }
        }
        $events = $this->dates((int) ($input['event_id'] ?? 0), (string) ($input['scope'] ?? 'single'));
        foreach ($events as $event) {
            if (!$this->can($actor, (int) $event->id)
                || (!empty($input['free']) && !$this->can($actor, (int) $event->id, 'free'))) {
                throw new DomainException('JERROR_ALERTNOAUTHOR');
            }
        }
        $resolved = $this->recipients($input, $actor);
        $kind = (string) ($input['kind'] ?? 'open');
        if (!in_array($kind, ['open', 'reserved'], true)) {
            throw new DomainException('COM_JEM_INV_INVALID_KIND');
        }
        $quantity = JemRegistrationQuantity::parse($input['quantity'] ?? 1);
        $expires = trim((string) ($input['expires'] ?? ''));
        if ($expires !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $expires)
            || !checkdate((int) substr($expires, 5, 2), (int) substr($expires, 8, 2), (int) substr($expires, 0, 4)))) {
            throw new DomainException('COM_JEM_INV_INVALID_EXPIRY');
        }
        $expiry = $expires !== '' ? (new DateTimeImmutable($expires . ' 23:59:59', new DateTimeZone(
            Factory::getApplication()->get('offset', 'UTC'))))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') : null;
        if ($expiry !== null && $expiry <= gmdate('Y-m-d H:i:s')) {
            throw new DomainException('COM_JEM_INV_INVALID_EXPIRY');
        }
        $hashInput = $input;
        unset($hashInput['fingerprint']);
        $fingerprint = hash('sha256', json_encode([$hashInput, $resolved['recipients'], array_keys($events)], JSON_THROW_ON_ERROR));
        if ($send && !preg_match('/^[a-f0-9]{64}$/D', (string) ($input['fingerprint'] ?? ''))) {
            throw new DomainException('COM_JEM_INV_PREVIEW_AGAIN');
        }
        $report = ['recipients' => 0, 'dates' => count($events), 'places' => 0,
            'excluded' => $resolved['excluded'], 'fingerprint' => $fingerprint, 'rows' => []];
        $this->db->transactionStart();
        try {
            $this->lockEvents(array_keys($events));
            foreach ($events as $id => $event) {
                $events[$id] = $this->db->setQuery('SELECT * FROM #__jem_events WHERE id=' . (int) $id)->loadObject();
                if (!$events[$id] || !$this->can($actor, (int) $id)) {
                    throw new DomainException('COM_JEM_INV_INVALID_EVENT');
                }
                $message = $this->availabilityMessage($events[$id]);
                if ($message !== '') {
                    throw new DomainException(\Joomla\CMS\Language\Text::sprintf(
                        'COM_JEM_EVENT_REGISTRATION_EVENT_MESSAGE', (int) $id, \Joomla\CMS\Language\Text::_($message)
                    ));
                }
            }
            $recipientAccess = new JemInvitationAccess($this->db);
            foreach ($resolved['recipients'] as $holder) {
                $key = bin2hex(random_bytes(16));
                $token = JemInvitationPolicy::token();
                $rows = [];
                $existing = false;
                $accessDenied = false;
                foreach ($events as $event) {
                    $identity = '(guest_email=' . $this->db->quote($holder['email'])
                        . ($holder['uid'] ? ' OR uid=' . (int) $holder['uid'] : '') . ')';
                    if ($this->db->setQuery('SELECT COUNT(*) FROM #__jem_register WHERE event=' . (int) $event->id . ' AND ' . $identity)->loadResult()) {
                        $existing = true;
                        break;
                    }
                    if ($holder['uid']) {
                        if ($recipientAccess->message((int) $event->id, (int) $holder['uid']) !== '') {
                            $accessDenied = true;
                            $existing = true;
                            break;
                        }
                    }
                }
                if ($existing) {
                    $report['excluded'][] = ['name' => $holder['name'], 'reason' => $accessDenied ? JemInvitationAccess::NO_ACCESS : 'COM_JEM_INV_EXISTING_OR_ACCESS'];
                    continue;
                }
                foreach ($events as $event) {
                    $this->assertOpen($event);
                    JemRegistrationQuantity::assertActiveTotal($quantity, $event);
                    $row = (object) [
                        'event' => (int) $event->id, 'uid' => $holder['uid'], 'places' => $quantity, 'status' => 0, 'waiting' => 0,
                        'guest_email' => $holder['uid'] ? null : $holder['email'], 'guest_name' => $holder['uid'] ? '' : $holder['name'],
                        'invitation_key' => $key, 'invitation_scope' => $input['scope'] ?? 'single',
                        'invitation_root' => (int) (reset($events)->recurrence_first_id ?: reset($events)->id),
                        'invitation_kind' => $kind, 'invitation_quantity' => $quantity, 'invitation_free' => !empty($input['free']) ? 1 : 0,
                        'invitation_message' => mb_substr(trim(strip_tags((string) ($input['message'] ?? ''))), 0, 4000),
                        'invitation_expires' => $expiry, 'invitation_token_hash' => JemInvitationPolicy::digest($token),
                        'invitation_revoked' => 0, 'invitation_actor' => (int) $actor->id,
                        'invitation_terms' => json_encode(['price' => (string) ($input['price'] ?? ''),
                            'area' => (string) ($input['area'] ?? '')], JSON_THROW_ON_ERROR),
                    ];
                    $rows[] = $this->store(null, $row, $event, (int) $actor->id, 'invited')->after;
                }
                if ($send) {
                    $this->notify($rows, $token, 'invitation');
                }
                ++$report['recipients'];
                $report['places'] += $kind === 'reserved' ? $quantity * count($events) : 0;
                $report['rows'][] = ['name' => $holder['name'], 'email' => $holder['email'],
                    'sessions' => array_map(static fn ($row) => [
                        'event' => (int) $row->event, 'date' => $events[(int) $row->event]->dates,
                        'title' => $events[(int) $row->event]->title,
                        'quantity' => (int) $row->places, 'currency' => (string) ($row->currency ?? ''),
                        'amount' => (string) ($row->grand_total ?? '0.00'),
                    ], $rows)];
            }
            if (!$report['recipients']) {
                $denied = array_filter($report['excluded'], static fn ($row) => $row['reason'] === JemInvitationAccess::NO_ACCESS);
                throw new DomainException($denied ? JemInvitationAccess::NO_ACCESS : 'COM_JEM_INV_NO_RECIPIENTS');
            }
            $report['fingerprint'] = hash('sha256', json_encode([
                $fingerprint, $report['rows'], $report['excluded'],
            ], JSON_THROW_ON_ERROR));
            if ($send && !hash_equals((string) $input['fingerprint'], $report['fingerprint'])) {
                throw new DomainException('COM_JEM_INV_PREVIEW_AGAIN');
            }
            $send ? $this->db->transactionCommit() : $this->db->transactionRollback();
            return $report;
        } catch (Throwable $error) {
            $this->db->transactionRollback();
            throw $error;
        }
    }

    /** @param array $rows Previewed responses. @return string Exact financial/inventory terms. */
    public static function responseFingerprint(array $rows): string
    {
        return hash('sha256', json_encode(array_map(static fn ($row) => [
            (int) $row->id, (int) $row->event, (int) $row->places, (int) $row->waiting,
            (string) ($row->currency ?? ''), (string) ($row->grand_total ?? '0.00'),
        ], $rows), JSON_THROW_ON_ERROR));
    }

    /** @param array $ids Event IDs. @return void Lock in ascending order before any registration. */
    private function lockEvents(array $ids): void
    {
        sort($ids, SORT_NUMERIC);
        foreach (array_unique($ids) as $id) {
            $this->db->setQuery('SELECT id FROM #__jem_events WHERE id=' . (int) $id . ' FOR UPDATE')->loadResult();
        }
    }

    /** @param int $id Joomla user ID (0 for external recipient). @return object Joomla identity. */
    private function user(int $id): object
    {
        return Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($id);
    }

    /**
     * Explain why an event cannot currently receive invitations.
     * @param object $event Stored event. @return string Language key, or an empty string.
     */
    public function availabilityMessage(object $event): string
    {
        $message = JemRegistrationAccessPolicy::invitationEventMessage($event);
        return $message !== '' ? $message
            : (JemHelper::getEventRegistrationWindowState($event) === 'open' ? '' : 'COM_JEM_INV_EVENT_CLOSED');
    }

    /** @param object $event Event under examination. @return void Reject unavailable/suspended/closed dates. */
    private function assertOpen(object $event): void
    {
        $message = $this->availabilityMessage($event);
        if ($message !== '') {
            throw new DomainException($message);
        }
        $now = gmdate('Y-m-d H:i:s');
        if ((int) $event->published !== 1 || JemRegistrationAccessPolicy::eventStatusMessage($event) !== ''
            || JemHelper::getEventRegistrationWindowState($event) !== 'open'
            || (!empty($event->publish_up) && $event->publish_up > $now)
            || (!empty($event->publish_down) && $event->publish_down !== '0000-00-00 00:00:00' && $event->publish_down <= $now)
            || ((int) $event->registra === 2 && !empty($event->dates)
                && ((!empty($event->registra_from) && $event->registra_from > $now)
                || (!empty($event->registra_until) && $event->registra_until !== '0000-00-00 00:00:00' && $event->registra_until <= $now)))) {
            throw new DomainException('COM_JEM_INV_EVENT_CLOSED');
        }
    }

    /**
     * Apply authoritative pricing and physical allocations before the shared writer.
     * @param object|null $before Previous registration. @param object $row Proposed registration.
     * @param object $event Current locked event. @param int $actorId Audit actor. @param string $action Audit action.
     * @return object Writer result.
     */
    private function store(?object $before, object $row, object $event, int $actorId, string $action): object
    {
        if ((int) $row->status === 0 && $row->invitation_kind === 'reserved' && (int) $event->maxplaces > 0) {
            $confirmed = (int) $this->db->setQuery('SELECT COALESCE(SUM(places),0) FROM #__jem_register WHERE event='
                . (int) $event->id . ' AND status=1 AND waiting=0')->loadResult();
            if ($confirmed + (int) $event->reservedplaces > (int) $event->maxplaces) {
                throw new DomainException('COM_JEM_INV_NOT_ENOUGH_RESERVED');
            }
        }
        $writer = new JemRegistrationService($this->db);
        $options = ['actorId' => $actorId, 'source' => 'invitation', 'action' => $action,
            'invitationWrite' => true, 'invitationResponse' => true, 'respectPlaces' => true,
            'allowWaiting' => $row->invitation_kind === 'open', 'expectedRevision' => $before ? (int) $before->revision : 0];
        $terms = json_decode((string) $row->invitation_terms, true, 32, JSON_THROW_ON_ERROR);
        if ($before && $row->invitation_kind === 'reserved' && (int) $before->status === 0 && (int) $row->status === 1) {
            // A guaranteed invitation already owns its immutable price and area snapshot.
            // Acceptance transfers inventory; it does not silently reprice the offer.
            return $writer->saveLocked($before, $row, $options);
        }
        if ((int) $row->status >= 0 && ($row->invitation_kind === 'reserved' || (int) $row->status === 1)) {
            if (($event->pricing_mode ?? 'classic') !== 'classic') {
                $price = $this->db->setQuery('SELECT id FROM #__jem_event_prices WHERE event_id=' . (int) $event->id
                    . ' AND code=' . $this->db->quote((string) ($terms['price'] ?? '')) . ' AND published=1')->loadResult();
                if (!$price) {
                    throw new DomainException('COM_JEM_INV_SELECT_PRICE');
                }
                $context = JemPricingQuoteContext::fromInvitation($this->user((int) $row->uid), $row, (int) $event->pricing_revision);
                $quote = (new JemPricingQuoteService($this->db))->quote((int) $event->id,
                    [['event_price_id' => (int) $price, 'quantity' => (int) $row->places]], $context);
                if ($row->invitation_free) {
                    $quote = JemInvitationPolicy::complimentary($quote);
                }
                if ($quote['inventory_state'] !== 'available' && $row->invitation_kind === 'reserved') {
                    throw new DomainException('COM_JEM_INV_NOT_ENOUGH_RESERVED');
                }
                if ((int) $row->status === 1 && $quote['inventory_state'] === 'waiting_list') {
                    $row->waiting = 1;
                }
                $row->pricing_mode = $quote['pricing_mode'];
                $row->currency = $quote['currency'];
                foreach (['subtotal_net', 'tax_total', 'grand_total'] as $name) {
                    $row->$name = $quote[$name];
                }
                $row->discount_total = '0.00';
                $row->management_fee_net = $row->management_fee_tax = $row->management_fee_gross = '0.00';
                $row->price_locked_at = $before->price_locked_at ?? $quote['quoted_at'];
                $row->payment_state = $quote['grand_total'] === '0.00' ? 'free' : 'unpaid';
                $priced = new JemPricedRegistrationService($this->db);
                $options['commercialLines'] = $priced->quoteItems($quote);
                $options['capacityAllocations'] = $priced->quoteCapacityAllocations($quote);
            } elseif (($event->capacity_mode ?? '') === 'areas') {
                $options['capacityAllocations'] = [(string) ($terms['area'] ?? '') => (int) $row->places];
            }
        }
        return $writer->saveLocked($before, $row, $options);
    }

    /**
     * Read an email capability without accepting it or changing inventory.
     * @param string $token Raw emailed secret. @param bool $lock Lock the resolved rows.
     * @return array Registrations for this invitation only.
     */
    public function resolve(string $token, bool $lock = false): array
    {
        $digest = JemInvitationPolicy::digest($token);
        $rows = $this->db->setQuery('SELECT * FROM #__jem_register WHERE invitation_token_hash='
            . $this->db->quote($digest) . ' ORDER BY event' . ($lock ? ' FOR UPDATE' : ''))->loadObjectList();
        if (!$rows || array_filter($rows, static fn ($row) => !empty($row->invitation_revoked)
            || JemInvitationPolicy::expired($row, gmdate('Y-m-d H:i:s')))) {
            throw new DomainException('COM_JEM_INV_INVALID_LINK');
        }
        return $rows;
    }

    /**
     * Resolve only the current pending batch owned by an authenticated Joomla recipient.
     * @param int $id Registration ID. @param object $user Current identity.
     * @param bool $lock Lock rows inside the response transaction. @return array Owned rows.
     */
    public function resolveOwned(int $id, object $user, bool $lock = false): array
    {
        if (empty($user->id) || !empty($user->block)) {
            throw new DomainException('COM_JEM_INV_INVALID_LINK');
        }
        $row = $this->db->setQuery('SELECT * FROM #__jem_register WHERE id=' . $id
            . ' AND uid=' . (int) $user->id)->loadObject();
        if (!$row || (int) $row->status !== 0 || empty($row->invitation_token_hash)) {
            throw new DomainException('COM_JEM_INV_INVALID_LINK');
        }
        $rows = $this->db->setQuery('SELECT * FROM #__jem_register WHERE uid=' . (int) $user->id
            . ' AND invitation_token_hash=' . $this->db->quote($row->invitation_token_hash)
            . ' ORDER BY event' . ($lock ? ' FOR UPDATE' : ''))->loadObjectList();
        if (!$rows || array_filter($rows, static fn ($item) => $item->invitation_revoked
            || JemInvitationPolicy::expired($item, gmdate('Y-m-d H:i:s')))) {
            throw new DomainException('COM_JEM_INV_INVALID_LINK');
        }
        return $rows;
    }

    /**
     * Respond as the authenticated owner without exposing or rotating email secrets.
     * @param int $id Owned row. @param object $user Current identity. @param bool $accept Answer.
     * @param bool $preview Roll back validation. @param string $expected Reviewed terms.
     * @return array Resulting rows.
     */
    public function respondOwned(int $id, object $user, bool $accept, bool $preview = false, string $expected = ''): array
    {
        return $this->answer(fn ($lock) => $this->resolveOwned($id, $user, $lock), $accept, $preview, $expected);
    }

    /**
     * Answer all included dates atomically; consume the invitation capability on success.
     * @param string $token Emailed capability. @param bool $accept Accept or decline.
     * @param bool $preview Validate and roll back. @param string $expected Reviewed financial terms.
     * @return array Current reservations for a restricted post-response session.
     */
    public function respond(string $token, bool $accept, bool $preview = false, string $expected = ''): array
    {
        return $this->answer(fn ($lock) => $this->resolve($token, $lock), $accept, $preview, $expected);
    }

    /**
     * Apply the same atomic response for a verified owner or email capability.
     * @param callable $resolve Authorised row loader. @param bool $accept Answer.
     * @param bool $preview Roll back writes. @param string $expected Reviewed terms. @return array
     */
    private function answer(callable $resolve, bool $accept, bool $preview, string $expected): array
    {
        $rows = $resolve(false);
        $this->db->transactionStart();
        try {
            $this->lockEvents(array_map(static fn ($row) => (int) $row->event, $rows));
            $rows = $resolve(true);
            $results = [];
            foreach ($rows as $row) {
                if ((int) $row->status !== 0) {
                    throw new DomainException('COM_JEM_INV_ALREADY_ANSWERED');
                }
                $event = $this->db->setQuery('SELECT * FROM #__jem_events WHERE id=' . (int) $row->event)->loadObject();
                if ($accept) {
                    if ($row->uid && (new JemInvitationAccess($this->db))->message((int) $row->event, (int) $row->uid) !== '') {
                        throw new DomainException(JemInvitationAccess::ACCESS_CHANGED);
                    }
                    if ($row->uid) {
                        $user = $this->db->setQuery('SELECT block,activation FROM #__users WHERE id=' . (int) $row->uid)->loadObject();
                        if (!$user || $user->block || JemRegistrationService::activationRequiresVerification($user->activation)) {
                            throw new DomainException('COM_JEM_INV_INACTIVE_USER');
                        }
                    }
                    $this->assertOpen($event);
                }
                $after = clone $row;
                $after->status = $accept ? 1 : -1;
                $after->invitation_token_hash = null;
                if (!$accept) {
                    $after->invitation_revoked = 1;
                }
                $results[] = $this->store($row, $after, $event, (int) $row->uid, $accept ? 'invitation_accepted' : 'invitation_declined')->after;
            }
            $fingerprint = self::responseFingerprint($results);
            if ($accept && !$preview && $expected !== '' && !hash_equals($expected, $fingerprint)) {
                throw new DomainException('COM_JEM_INV_PREVIEW_AGAIN');
            }
            if (!$preview) {
                $this->cancelQueuedLinks($rows);
                $this->notify($results, '', $accept ? 'accepted' : 'declined');
            }
            $preview ? $this->db->transactionRollback() : $this->db->transactionCommit();
            return $results;
        } catch (Throwable $error) {
            $this->db->transactionRollback();
            throw $error;
        }
    }

    /**
     * Rotate a link or revoke pending invitations without cancelling existing confirmed bookings.
     * @param int $id Invitation row. @param object $actor Operator. @param bool $revoke Revoke rather than resend.
     * @return void
     */
    public function manage(int $id, object $actor, bool $revoke): void
    {
        $row = $this->db->setQuery('SELECT * FROM #__jem_register WHERE id=' . $id)->loadObject();
        if (!$row || empty($row->invitation_key)) {
            throw new DomainException('COM_JEM_INV_INVALID_LINK');
        }
        $rows = $this->db->setQuery('SELECT * FROM #__jem_register WHERE invitation_key='
            . $this->db->quote($row->invitation_key) . ' ORDER BY event')->loadObjectList();
        foreach ($rows as $item) {
            if (!$this->can($actor, (int) $item->event)) {
                throw new DomainException('JERROR_ALERTNOAUTHOR');
            }
        }
        $this->db->transactionStart();
        try {
            $this->lockEvents(array_map(static fn ($item) => (int) $item->event, $rows));
            $token = JemInvitationPolicy::token();
            $results = [];
            foreach ($rows as $item) {
                $before = $this->db->setQuery('SELECT * FROM #__jem_register WHERE id=' . (int) $item->id . ' FOR UPDATE')->loadObject();
                if (!$revoke && ((int) $before->status !== 0 || $before->invitation_revoked
                    || JemInvitationPolicy::expired($before, gmdate('Y-m-d H:i:s')))) {
                    continue;
                }
                $after = clone $before;
                $after->invitation_token_hash = $revoke ? null : JemInvitationPolicy::digest($token);
                $after->invitation_revoked = $revoke ? 1 : 0;
                if ($revoke && (int) $before->status === 0) {
                    $after->status = -1;
                }
                $event = $this->db->setQuery('SELECT * FROM #__jem_events WHERE id=' . (int) $item->event)->loadObject();
                if (!$revoke) {
                    $this->assertOpen($event);
                    if ($before->uid && (new JemInvitationAccess($this->db))->message((int) $before->event, (int) $before->uid) !== '') {
                        throw new DomainException(JemInvitationAccess::NO_ACCESS);
                    }
                }
                $results[] = (new JemRegistrationService($this->db))->saveLocked($before, $after, [
                    'invitationWrite' => true, 'invitationResponse' => true, 'actorId' => (int) $actor->id,
                    'source' => 'invitation', 'action' => $revoke ? 'invitation_revoked' : 'invitation_resent'])->after;
            }
            $this->cancelQueuedLinks($results);
            if ($results && !$revoke) {
                $this->notify($results, $token, 'invitation');
            }
            $this->db->transactionCommit();
        } catch (Throwable $error) {
            $this->db->transactionRollback();
            throw $error;
        }
    }

    /**
     * Inherit explicit series invitations, retaining provenance after earlier acceptance.
     * The caller owns the occurrence transaction (event, categories, links and invitations).
     * @param int $sourceId Existing series member. @param int $targetId Newly created member.
     * @return void
     */
    public function propagate(int $sourceId, int $targetId): void
    {
        $dates = $this->dates($sourceId, 'series');
        $ids = array_keys($dates);
        $ids[] = $targetId;
        $this->lockEvents($ids);
        $source = $this->db->setQuery('SELECT * FROM #__jem_events WHERE id=' . $sourceId)->loadObject();
        $target = $this->db->setQuery('SELECT * FROM #__jem_events WHERE id=' . $targetId)->loadObject();
        if (!$source || !$target) {
            throw new DomainException('COM_JEM_INV_INVALID_EVENT');
        }
        if ($this->availabilityMessage($target) !== '') {
            return; // An unpublished occurrence requires an explicit invitation after approval.
        }
        if (!isset($dates[$targetId])) {
            return; // Copying links to an unrelated event does not copy invitations.
        }
        $rows = $this->db->setQuery("SELECT * FROM #__jem_register WHERE invitation_scope='series' AND invitation_key IS NOT NULL"
            . ' AND event IN (' . implode(',', array_map('intval', array_keys($dates))) . ') ORDER BY id DESC')->loadObjectList();
        $byKey = [];
        foreach ($rows as $row) {
            if (!isset($byKey[$row->invitation_key])) {
                $byKey[$row->invitation_key] = $row;
            }
        }
        if (!$byKey) {
            return;
        }
        $this->copySeriesPrices($source, $target);
        // Copies use the complete organiser allocation, not the diminished unconfirmed balance.
        $confirmed = (int) $this->db->setQuery('SELECT COALESCE(SUM(reserved_confirmed),0) FROM #__jem_register WHERE event=' . $sourceId)->loadResult();
        if (!(int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register WHERE event=' . $targetId)->loadResult()) {
            $target->reservedplaces = (int) $source->reservedplaces + $confirmed;
            $this->db->setQuery('UPDATE #__jem_events SET reservedplaces=' . (int) $target->reservedplaces . ' WHERE id=' . $targetId)->execute();
        }
        $recipientAccess = new JemInvitationAccess($this->db);
        foreach ($byKey as $original) {
            if ($original->invitation_revoked || JemInvitationPolicy::expired($original, gmdate('Y-m-d H:i:s'))) {
                continue;
            }
            if ($original->uid && $recipientAccess->message($targetId, (int) $original->uid) !== '') {
                continue;
            }
            if ($original->uid) {
                $holder = $this->db->setQuery('SELECT block,activation FROM #__users WHERE id=' . (int) $original->uid)->loadObject();
                if (!$holder || $holder->block || JemRegistrationService::activationRequiresVerification($holder->activation)) {
                    continue;
                }
                $identity = 'uid=' . (int) $original->uid;
            } else {
                $email = $this->db->quote($original->guest_email);
                $identity = '(guest_email=' . $email . ' OR uid IN (SELECT id FROM #__users WHERE LOWER(email)=' . $email . '))';
            }
            if ($this->db->setQuery('SELECT COUNT(*) FROM #__jem_register WHERE event=' . $targetId . ' AND ' . $identity)->loadResult()) {
                continue;
            }
            $row = (object) array_intersect_key((array) $original, array_flip(array_keys(JemInvitationSchema::columns())));
            $row->uid = $original->uid;
            $row->event = $targetId;
            $row->places = (int) $row->invitation_quantity;
            $row->status = 0;
            $row->waiting = 0;
            $row->reserved_assigned = $row->reserved_confirmed = 0;
            $token = JemInvitationPolicy::token();
            $row->invitation_token_hash = JemInvitationPolicy::digest($token);
            $result = $this->store(null, $row, $target, 0, 'invitation_propagated');
            $this->notify([$result->after], $token, 'invitation');
        }
    }

    /**
     * Give a new series occurrence independent price/pool rows before invitations use them.
     * Existing target configurations are never replaced. The caller owns the transaction.
     * @param object $source Original occurrence. @param object $target New occurrence. @return void
     */
    private function copySeriesPrices(object $source, object $target): void
    {
        if (($target->pricing_mode ?? 'classic') === 'classic'
            || $this->db->setQuery('SELECT COUNT(*) FROM #__jem_event_prices WHERE event_id=' . (int) $target->id)->loadResult()) {
            return;
        }
        $map = [];
        $existing = $this->db->setQuery('SELECT id,code FROM #__jem_capacity_pools WHERE event_id=' . (int) $target->id)->loadAssocList('code', 'id');
        foreach ($this->db->setQuery('SELECT * FROM #__jem_capacity_pools WHERE event_id=' . (int) $source->id)->loadObjectList() as $pool) {
            $old = (int) $pool->id;
            if (isset($existing[$pool->code])) {
                $map[$old] = (int) $existing[$pool->code];
                continue;
            }
            unset($pool->id);
            $pool->event_id = (int) $target->id;
            $pool->created = gmdate('Y-m-d H:i:s');
            $pool->modified = null;
            $this->db->insertObject('#__jem_capacity_pools', $pool, 'id');
            $map[$old] = (int) $pool->id;
        }
        foreach ($this->db->setQuery('SELECT * FROM #__jem_event_prices WHERE event_id=' . (int) $source->id)->loadObjectList() as $price) {
            unset($price->id);
            $price->event_id = (int) $target->id;
            $price->capacity_pool_id = $price->capacity_pool_id ? ($map[(int) $price->capacity_pool_id] ?? null) : null;
            $price->created = gmdate('Y-m-d H:i:s');
            $price->modified = null;
            $this->db->insertObject('#__jem_event_prices', $price, 'id');
        }
    }

    /**
     * Release expired pending assignments using the same audited transition as withdrawal.
     * @param int $limit Maximum rows processed by one scheduler invocation. @return int Released invitations.
     */
    public function expire(int $limit = 100): int
    {
        $rows = $this->db->setQuery("SELECT id,event FROM #__jem_register WHERE status=0 AND invitation_revoked=0"
            . ' AND invitation_expires IS NOT NULL AND invitation_expires<=' . $this->db->quote(gmdate('Y-m-d H:i:s'))
            . ' ORDER BY event,id', 0, max(1, min(1000, $limit)))->loadObjectList();
        $count = 0;
        foreach ($rows as $candidate) {
            $this->db->transactionStart(true);
            try {
                $this->lockEvents([(int) $candidate->event]);
                $before = $this->db->setQuery('SELECT * FROM #__jem_register WHERE id=' . (int) $candidate->id . ' FOR UPDATE')->loadObject();
                if ($before && (int) $before->status === 0 && !$before->invitation_revoked
                    && JemInvitationPolicy::expired($before, gmdate('Y-m-d H:i:s'))) {
                    $after = clone $before;
                    $after->status = -1;
                    $after->invitation_revoked = 1;
                    $after->invitation_token_hash = null;
                    (new JemRegistrationService($this->db))->saveLocked($before, $after, [
                        'invitationWrite' => true, 'invitationResponse' => true, 'actorId' => 0,
                        'source' => 'invitation.expiry', 'action' => 'invitation_expired']);
                    $this->cancelQueuedLinks([$before]);
                    ++$count;
                }
                $this->db->transactionCommit(true);
            } catch (Throwable $error) {
                $this->db->transactionRollback(true);
                throw $error;
            }
        }
        return $count;
    }

    /**
     * Retain journal history but prevent delivery/retry of an obsolete invitation link.
     * In-flight email cannot be recalled; its capability is still revoked atomically.
     * @param array $rows Invitations whose old links are no longer usable. @return void
     */
    private function cancelQueuedLinks(array $rows): void
    {
        $ids = array_values(array_unique(array_map(static fn ($row) => (int) $row->id, $rows)));
        if (!$ids) {
            return;
        }
        $now = $this->db->quote(gmdate('Y-m-d H:i:s'));
        $this->db->setQuery("UPDATE #__jem_notifications SET state='cancelled',cancelled_at=" . $now . ',modified=' . $now
            . " WHERE notification_type='invitation_invitation' AND state IN ('queued','scheduled','failed')"
            . ' AND registration_id IN (' . implode(',', $ids) . ')')->execute();
    }

    /**
     * Queue through the JEM Mailer; delivery is performed by the existing notification task.
     * @param array $rows Invitations for one recipient. @param string $token Raw email capability.
     * @param string $action invitation, accepted or declined. @return void
     */
    private function notify(array $rows, string $token, string $action): void
    {
        PluginHelper::importPlugin('jem');
        $result = JemFactory::getDispatcher()->triggerEvent('onJemInvitationNotification', [$rows, $token, $action]);
        if (!in_array(true, (array) $result, true)) {
            throw new DomainException('COM_JEM_INV_MAILER_REQUIRED');
        }
    }
}
