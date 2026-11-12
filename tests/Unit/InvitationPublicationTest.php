<?php
declare(strict_types=1);

use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Publication-first invitations, using real models and Joomla's in-memory SQL driver. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class InvitationPublicationTest extends TestCase
{
    private object $db;

    /** @return void Create isolated SQL fixtures; no installed database or mail transport is used. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php') || !extension_loaded('pdo_sqlite') || !extension_loaded('sqlite3')) {
            self::markTestSkipped('Requires Joomla libraries, pdo_sqlite and sqlite3, but no installed application.');
        }
        require JEM_TEST_ROOT . '/tests/Support/invitation_publication_environment.php';
        $this->db = Factory::getContainer()->get('DatabaseDriver');
        $this->db->setQuery("INSERT INTO #__jem_events VALUES (7,'Session',1,NULL,NULL,'scheduled',1,5,1,'2099-11-12','12:00',0,NULL,1)")->execute();
        $this->db->setQuery('INSERT INTO #__jem_register (id,event,uid,status,waiting,places,revision) VALUES (10,7,42,0,0,1,1)')->execute();
    }

    /** @return iterable Publication and suspension boundaries, with a fixed UTC instant. */
    public static function availability(): iterable
    {
        yield 'published' => [[], true];
        yield 'unpublished' => [['published' => 0], false];
        yield 'archived' => [['published' => 2], false];
        yield 'trashed' => [['published' => -2], false];
        yield 'future publication' => [['publish_up' => '2026-11-13 10:00:00'], false];
        yield 'publication starts now' => [['publish_up' => '2026-11-12 10:00:00'], true];
        yield 'publication ends now' => [['publish_down' => '2026-11-12 10:00:00'], false];
        yield 'publication ends later' => [['publish_down' => '2026-11-13 10:00:00'], true];
        yield 'legacy zero dates' => [['publish_up' => '0000-00-00 00:00:00', 'publish_down' => '0000-00-00 00:00:00'], true];
        yield 'cancelled' => [['event_status' => 'cancelled'], false];
        yield 'postponed' => [['event_status' => 'postponed'], false];
        yield 'rescheduled' => [['event_status' => 'rescheduled'], true];
        yield 'online' => [['event_status' => 'moved_online'], true];
    }

    /** @param array $changes State to test. @param bool $allowed Expected availability. @return void */
    #[DataProvider('availability')]
    public function testPublicationBoundaries(array $changes, bool $allowed): void
    {
        $event = array_replace(['published' => 1, 'event_status' => 'scheduled'], $changes);
        self::assertSame($allowed, JemRegistrationAccessPolicy::invitationEventMessage($event, '2026-11-12 10:00:00') === '');
        self::assertSame($allowed, JemRegistrationAccessPolicy::invitationEventMessage((object) $event, '2026-11-12 10:00:00') === '');
    }

    /** @return void Drafts are saved without invitations; even forged publish/new requests must save first. */
    public function testEventFormRequiresSavedPublicationBeforeAddingRecipients(): void
    {
        $model = (new ReflectionClass(JemModelEvent::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($model, 'validateInvitationSubmission');
        self::assertTrue($method->invoke($model, ['invited' => ''], true));
        self::assertFalse($method->invoke($model, ['published' => 1, 'invited' => '43'], true));
        self::assertTrue($method->invoke($model, ['id' => 7, 'invited' => '43'], false));
        self::assertFalse($method->invoke($model, ['id' => 7, 'published' => 0, 'invited' => '43'], false));
        $this->db->setQuery('UPDATE #__jem_events SET published=0 WHERE id=7')->execute();
        self::assertFalse($method->invoke($model, ['id' => 7, 'published' => 1, 'invited' => '43'], false));
        self::assertTrue($method->invoke($model, ['id' => 7, 'invited' => '42'], false));
        self::assertTrue($method->invoke($model, ['id' => 7], false));
        self::assertSame('COM_JEM_INVITATION_PUBLISH_FIRST', $model->getError());
    }

    /** @return void Missing/disabled fields never remove existing invitations on a draft. */
    public function testUnpublishedEventSavePreservesExistingInvitations(): void
    {
        $this->db->setQuery('UPDATE #__jem_events SET published=0 WHERE id=7')->execute();
        $model = (new ReflectionClass(JemModelEvent::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($model, '_storeUsersInvited');
        self::assertTrue($method->invoke($model, 7, '', true, false));
        self::assertSame(1, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register WHERE status=0')->loadResult());
    }

    /** @return iterable Existing invitation edits/cancellations versus new assignments. */
    public static function transitions(): iterable
    {
        yield 'new invitation' => [null, 0, 1, false];
        yield 'reactivated invitation' => [-1, 0, 1, false];
        yield 'existing invitation' => [0, 0, 1, true];
        yield 'extend invitation' => [0, 0, 2, false];
        yield 'withdraw invitation' => [0, -1, 0, true];
    }

    /** @param int|null $old Stored response. @param int $new Proposed response. @param int $places Quantity. @param bool $allowed Expected permission. @return void */
    #[DataProvider('transitions')]
    public function testRegistrationWriterRejectsNewInvitationsOnUnpublishedEvents(?int $old, int $new, int $places, bool $allowed): void
    {
        $this->db->setQuery('UPDATE #__jem_events SET published=0 WHERE id=7')->execute();
        $before = $old === null ? null : (object) ['id' => 10, 'event' => 7, 'uid' => 42, 'status' => $old, 'waiting' => 0, 'places' => 1];
        $after = (object) ['event' => 7, 'uid' => 42, 'status' => $new, 'waiting' => 0, 'places' => $places];
        if (!$allowed) {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('COM_JEM_INVITATION_PUBLISH_FIRST');
        }
        (new ReflectionMethod(JemRegistrationService::class, 'assertQuantityPolicy'))->invoke(new JemRegistrationService($this->db), $after, $before);
        if ($allowed) {
            self::assertTrue(true);
        }
    }

    /** @return void An unavailable event renders an explanation and no invitation input to submit. */
    public function testClassicSelectorIsDisabledOnDraft(): void
    {
        require JEM_TEST_ROOT . '/site/models/fields/modal/users.php';
        $this->db->setQuery('UPDATE #__jem_events SET published=0 WHERE id=7')->execute();
        $field = new JFormFieldModal_Users();
        $field->setup(new SimpleXMLElement('<field name="invited" eventid="7" />'), '42');
        $html = (new ReflectionMethod($field, 'getInput'))->invoke($field);
        self::assertStringContainsString('disabled', $html);
        self::assertStringContainsString('COM_JEM_INVITATION_PUBLISH_FIRST', $html);
        self::assertStringNotContainsString('name="invited"', $html);
    }

    /** @return void The new service explains publication and registration availability. */
    public function testComposerChecksEverySession(): void
    {
        if (!is_file(JEM_TEST_ROOT . '/site/classes/invitationservice.class.php')) {
            self::markTestSkipped('Invitations view belongs to JEM 5.1.');
        }
        require_once JEM_TEST_ROOT . '/site/classes/invitationservice.class.php';
        $service = new JemInvitationService($this->db);
        $event = $this->db->setQuery('SELECT * FROM #__jem_events WHERE id=7')->loadObject();
        self::assertSame('', $service->availabilityMessage($event));
        $event->published = 0;
        self::assertSame('COM_JEM_INVITATION_PUBLISH_FIRST', $service->availabilityMessage($event));
        $event->published = 1;
        $event->registra = 0;
        self::assertSame('COM_JEM_INV_EVENT_CLOSED', $service->availabilityMessage($event));
    }

    /** @return object Operator authorised for the fixture events. */
    private function actor(): object
    {
        return new class {
            public int $id = 5;
            /** @return bool Authorisation is granted; event availability is tested separately. */
            public function authorise($action, $asset): bool { return true; }
        };
    }

    /** @return void A direct send rejects the entire selection and identifies the unavailable session. */
    public function testComposerRejectsUnpublishedSeriesSessionBeforeWriting(): void
    {
        if (!is_file(JEM_TEST_ROOT . '/site/classes/invitationservice.class.php')) {
            self::markTestSkipped('Invitations view belongs to JEM 5.1.');
        }
        require_once JEM_TEST_ROOT . '/site/classes/invitationservice.class.php';
        $this->db->setQuery("INSERT INTO #__jem_events (id,title,published,event_status,recurrence_first_id,registra) VALUES (8,'Pending session',0,'scheduled',7,1)")->execute();
        $service = new JemInvitationService($this->db);
        foreach ([false, true] as $send) {
            try {
                $service->compose(['event_id' => 7, 'scope' => 'series', 'audience' => 'users', 'users' => [42],
                    'quantity' => 1, 'kind' => 'open', 'fingerprint' => str_repeat('a', 64)], $this->actor(), $send);
                self::fail('An unpublished session must block preview and send.');
            } catch (DomainException $error) {
                self::assertSame('COM_JEM_EVENT_REGISTRATION_EVENT_MESSAGE', $error->getMessage());
            }
            self::assertSame(1, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register')->loadResult());
            self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_notifications')->loadResult());
        }
    }

    /** @return void Rejection occurs before a resend rotates tokens or revisions. */
    public function testResendRejectsUnpublishedEventWithoutChangingInvitation(): void
    {
        if (!is_file(JEM_TEST_ROOT . '/site/classes/invitationservice.class.php')) {
            self::markTestSkipped('Invitations view belongs to JEM 5.1.');
        }
        require_once JEM_TEST_ROOT . '/site/classes/invitationservice.class.php';
        $this->db->setQuery('UPDATE #__jem_events SET published=0 WHERE id=7')->execute();
        $this->db->setQuery("UPDATE #__jem_register SET invitation_key='test-key',invitation_token_hash='original-token-hash'")->execute();
        try {
            (new JemInvitationService($this->db))->manage(10, $this->actor(), false);
            self::fail('Resending for an unpublished event must be rejected.');
        } catch (DomainException $error) {
            self::assertSame('COM_JEM_INVITATION_PUBLISH_FIRST', $error->getMessage());
        }
        $row = $this->db->setQuery('SELECT * FROM #__jem_register WHERE id=10')->loadObject();
        self::assertSame('original-token-hash', $row->invitation_token_hash);
        self::assertSame(1, (int) $row->revision);
    }

    /** @return void Unpublished new dates do not acquire inherited invitations. */
    public function testPropagationSkipsUnpublishedNewOccurrence(): void
    {
        if (!is_file(JEM_TEST_ROOT . '/site/classes/invitationservice.class.php')) {
            self::markTestSkipped('Invitations view belongs to JEM 5.1.');
        }
        require_once JEM_TEST_ROOT . '/site/classes/invitationservice.class.php';
        $this->db->setQuery("UPDATE #__jem_register SET invitation_key='series-key',invitation_scope='series'")->execute();
        $this->db->setQuery("INSERT INTO #__jem_events (id,title,published,event_status,recurrence_first_id,registra) VALUES (8,'Pending session',0,'scheduled',7,1)")->execute();
        (new JemInvitationService($this->db))->propagate(7, 8);
        self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register WHERE event=8')->loadResult());
        self::assertSame(1, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register WHERE event=7')->loadResult());
    }

    /** @return iterable Delivery-time changes; ordinary confirmations remain unaffected. */
    public static function deliveryChanges(): iterable
    {
        yield 'still available' => ['', '', false];
        yield 'unpublished' => ['UPDATE #__jem_events SET published=0', '', true];
        yield 'postponed' => ["UPDATE #__jem_events SET event_status='postponed'", '', true];
        yield 'accepted' => ['UPDATE #__jem_register SET status=1', '', true];
        yield 'withdrawn' => ['UPDATE #__jem_register SET invitation_revoked=1', '', true];
        yield 'expired' => ["UPDATE #__jem_register SET invitation_expires='2000-01-01 00:00:00'", '', true];
        yield 'replaced token revision' => ['UPDATE #__jem_register SET revision=2', '', true];
        yield 'deleted invitation' => ['DELETE FROM #__jem_register', '', true];
        yield 'ordinary confirmation' => ['UPDATE #__jem_events SET published=0', 'registration', false];
    }

    /** @param string $sql State mutation. @param string $type Override notification kind. @param bool $cancelled Expected cancellation. @return void */
    #[DataProvider('deliveryChanges')]
    public function testQueueRechecksAvailabilityBeforeClaimingDelivery(string $sql, string $type, bool $cancelled): void
    {
        if (!is_file(JEM_TEST_ROOT . '/site/classes/notificationservice.class.php')) {
            self::markTestSkipped('Notification queue belongs to JEM 5.1.');
        }
        require_once JEM_TEST_ROOT . '/site/classes/notificationservice.class.php';
        $this->db->setQuery("INSERT INTO #__jem_notifications (id,registration_id,registration_revision,notification_type,state,attempt_count,max_attempts) VALUES (1,10,1,'" . ($type ?: 'invitation_invitation') . "','queued',0,4)")->execute();
        if ($sql !== '') {
            $this->db->setQuery($sql)->execute();
        }
        $service = new JemNotificationService($this->db);
        $result = $service->beginAttempt(1);
        self::assertSame(!$cancelled, (bool) $result);
        $row = $service->getById(1);
        self::assertSame($cancelled ? 'cancelled' : 'processing', $row->state);
        self::assertSame($cancelled ? 0 : 1, (int) $row->attempt_count);
        if ($cancelled) {
            $this->db->setQuery('UPDATE #__jem_events SET published=1')->execute();
            self::assertFalse($service->beginAttempt(1), 'Republication must not automatically resend a cancelled notification.');
        }
    }

    /** @return void Publication of the first session cannot permit a stale whole-series email. */
    public function testQueueChecksOtherIncludedSessions(): void
    {
        if (!is_file(JEM_TEST_ROOT . '/site/classes/notificationservice.class.php')) {
            self::markTestSkipped('Notification queue belongs to JEM 5.1.');
        }
        require_once JEM_TEST_ROOT . '/site/classes/notificationservice.class.php';
        $this->db->setQuery("INSERT INTO #__jem_events (id,published,event_status) VALUES (8,0,'scheduled')")->execute();
        $this->db->setQuery('INSERT INTO #__jem_register (id,event,uid,status,waiting,places,revision) VALUES (11,8,42,0,0,1,1)')->execute();
        $payload = json_encode(['invitation_rows' => [['id' => 10, 'revision' => 1], ['id' => 11, 'revision' => 1]]]);
        $this->db->setQuery("INSERT INTO #__jem_notifications (id,registration_id,registration_revision,notification_type,payload_json,state,attempt_count,max_attempts) VALUES (1,10,1,'invitation_invitation'," . $this->db->quote($payload) . ",'queued',0,4)")->execute();
        self::assertFalse((new JemNotificationService($this->db))->beginAttempt(1));
    }
}
