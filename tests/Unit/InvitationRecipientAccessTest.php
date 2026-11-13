<?php
declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\User\UserFactoryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Invitation boundaries use the recipient's current Joomla ACL, never the operator's. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class InvitationRecipientAccessTest extends TestCase
{
    private object $db;

    /** @return void Create in-memory fixtures; no installed database, SMTP or browser. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php') || !extension_loaded('sqlite3')) {
            self::markTestSkipped('Requires Joomla libraries and SQLite.');
        }
        require JEM_TEST_ROOT . '/tests/Support/invitation_publication_environment.php';
        $this->db = Factory::getContainer()->get('DatabaseDriver');
        foreach (['locid', 'type_id', 'parent_event_id'] as $column) {
            $this->db->setQuery('ALTER TABLE #__jem_events ADD ' . $column . ' INTEGER DEFAULT 0')->execute();
        }
        $this->db->setQuery("INSERT INTO #__jem_events (id,title,published,event_status,minbookeduser,maxbookeduser,registra,dates,times,access) VALUES (7,'Private session',1,'scheduled',1,5,1,'2099-11-12','12:00',2)")->execute();
        $this->db->setQuery('INSERT INTO #__jem_register (id,event,uid,status,waiting,places,revision) VALUES (10,7,43,0,0,1,1)')->execute();
    }

    /** @return iterable Access combinations including related records and inactive accounts. */
    public static function accessCases(): iterable
    {
        yield 'registered' => ['', true];
        yield 'public' => ['UPDATE #__jem_events SET access=1', true];
        yield 'special event' => ['UPDATE #__jem_events SET access=3', false];
        yield 'hidden category' => ['UPDATE #__jem_cats_event_relations SET catid=2', false];
        yield 'unpublished category' => ['UPDATE #__jem_categories SET published=0 WHERE id=1', false];
        yield 'another visible category' => ['INSERT INTO #__jem_cats_event_relations VALUES (2,7)', true];
        yield 'hidden event type' => ['UPDATE #__jem_events SET type_id=2', false];
        yield 'visible event type' => ['UPDATE #__jem_events SET type_id=1', true];
        yield 'restricted venue' => ['UPDATE #__jem_events SET locid=2', false];
        yield 'online without venue' => ['UPDATE #__jem_events SET locid=0', true];
        yield 'blocked user' => ['UPDATE #__users SET block=1 WHERE id=43', false];
        yield 'unactivated user' => ["UPDATE #__users SET activation='pending' WHERE id=43", false];
        yield 'deleted user' => ['DELETE FROM #__users WHERE id=43', false];
        yield 'missing programme ancestor' => ['UPDATE #__jem_events SET parent_event_id=99', false];
        yield 'programme cycle' => ['UPDATE #__jem_events SET parent_event_id=7', false];
    }

    /** @param string $sql Fixture change. @param bool $allowed Expected recipient access. @return void */
    #[DataProvider('accessCases')]
    public function testRecipientAndRelatedContent(string $sql, bool $allowed): void
    {
        if ($sql !== '') { $this->db->setQuery($sql)->execute(); }
        $message = (new JemInvitationAccess($this->db))->message(7, 43);
        self::assertSame($allowed ? '' : JemInvitationAccess::NO_ACCESS, $message);
        self::assertSame(42, (int) Factory::getApplication()->getIdentity()->id);
    }

    /** @return void A series selector must exclude a user denied on any included session. */
    public function testSingleAndSeriesCandidateLists(): void
    {
        $this->db->setQuery('UPDATE #__jem_events SET access=1')->execute();
        $this->db->setQuery('INSERT INTO #__jem_events (id,published,access) VALUES (8,1,3)')->execute();
        $this->db->setQuery('INSERT INTO #__jem_cats_event_relations VALUES (1,8)')->execute();
        $users = $this->db->setQuery('SELECT id FROM #__users ORDER BY id')->loadObjectList();
        $policy = new JemInvitationAccess($this->db);
        self::assertCount(3, $policy->filterUsers($users, [7]));
        self::assertSame([42, 44], array_map(static fn ($u) => (int) $u->id, $policy->filterUsers($users, [7, 8])));
        self::assertSame([], $policy->filterUsers($users, []));
    }

    /** @return void Legacy modal SQL filters users before pagination and does not use operator ACL. */
    public function testClassicSelectorFiltersBeforePagination(): void
    {
        require_once JEM_TEST_ROOT . '/site/models/editevent.php';
        $this->db->setQuery('UPDATE #__jem_events SET access=3')->execute();
        $model = (new ReflectionClass(JemModelEditevent::class))->newInstanceWithoutConstructor();
        $model->setState('event.id', 7);
        (new ReflectionProperty($model, '__state_set'))->setValue($model, true);
        $query = (new ReflectionMethod($model, 'buildQueryUsers'))->invoke($model);
        $ids = $this->db->setQuery($query)->loadColumn();
        self::assertSame([44, 42], array_map('intval', $ids));
    }

    /** @return void Forging the hidden event-form recipient fails before saving the event. */
    public function testEventSubmissionRejectsIneligibleRecipient(): void
    {
        $this->db->setQuery('DELETE FROM #__jem_register')->execute();
        $this->db->setQuery('UPDATE #__jem_events SET access=3')->execute();
        $model = (new ReflectionClass(JemModelEvent::class))->newInstanceWithoutConstructor();
        self::assertFalse((new ReflectionMethod($model, 'validateInvitationSubmission'))->invoke($model, ['id' => 7, 'invited' => '43'], false));
        self::assertSame(JemInvitationAccess::NO_ACCESS, $model->getError());
        self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register')->loadResult());
    }

    /** @return void An access change within the same event save is checked again with a useful error. */
    public function testClassicStoreRechecksAfterEventEdit(): void
    {
        $this->db->setQuery('DELETE FROM #__jem_register')->execute();
        $model = (new ReflectionClass(JemModelEvent::class))->newInstanceWithoutConstructor();
        self::assertTrue((new ReflectionMethod($model, 'validateInvitationSubmission'))->invoke($model, ['id' => 7, 'invited' => '43'], false));
        $this->db->setQuery('UPDATE #__jem_events SET access=3')->execute();
        self::assertFalse((new ReflectionMethod($model, '_storeUsersInvited'))->invoke($model, 7, '43', true, false));
        self::assertSame(JemInvitationAccess::NO_ACCESS, $model->getError());
        self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register')->loadResult());
    }

    /** @return iterable Assignment, acceptance and cancellation must have different consequences. */
    public static function transitions(): iterable
    {
        yield 'new invitation' => [null, 0, false];
        yield 'reactivated invitation' => [-1, 0, false];
        yield 'acceptance' => [0, 1, false];
        yield 'decline' => [0, -1, true];
        yield 'existing unchanged' => [0, 0, true];
        yield 'ordinary booking' => [null, 1, true];
    }

    /** @param int|null $old Previous status. @param int $new Next status. @param bool $allowed Expected result. @return void */
    #[DataProvider('transitions')]
    public function testWriterGuardsInvitationTransitions(?int $old, int $new, bool $allowed): void
    {
        $this->db->setQuery('UPDATE #__jem_events SET access=3')->execute();
        $row = (object) ['id' => 10, 'event' => 7, 'uid' => 43, 'status' => $new, 'places' => 1, 'waiting' => 0];
        $before = $old === null ? null : (object) array_replace((array) $row, ['status' => $old]);
        if (!$allowed) { $this->expectExceptionMessage(JemInvitationAccess::NO_ACCESS); }
        (new ReflectionMethod(JemRegistrationService::class, 'assertQuantityPolicy'))->invoke(new JemRegistrationService($this->db), $row, $before);
        if ($allowed) { self::assertTrue(true); }
    }

    /** @return void A new operation re-evaluates changed event permissions. */
    public function testChangedAccessIsNotCachedAcrossOperations(): void
    {
        self::assertSame('', (new JemInvitationAccess($this->db))->message(7, 43));
        $this->db->setQuery('UPDATE #__jem_events SET access=3')->execute();
        self::assertSame(JemInvitationAccess::NO_ACCESS, (new JemInvitationAccess($this->db))->message(7, 43));
        $this->db->setQuery('UPDATE #__jem_events SET access=2')->execute();
        self::assertSame('', (new JemInvitationAccess($this->db))->message(7, 43));
    }

    /** @return void New token and owner responses cannot accept after access changes, without modifying the row. */
    public function testTokenAndOwnerAcceptanceRecheckAccess(): void
    {
        if (!is_file(JEM_TEST_ROOT . '/site/classes/invitationservice.class.php')) { self::markTestSkipped('JEM 5.1 composer.'); }
        require_once JEM_TEST_ROOT . '/site/classes/invitationservice.class.php';
        $token = str_repeat('a', 64);
        $digest = JemInvitationPolicy::digest($token);
        $this->db->setQuery('UPDATE #__jem_register SET invitation_token_hash=' . $this->db->quote($digest))->execute();
        $this->db->setQuery('UPDATE #__jem_events SET access=3')->execute();
        $service = new JemInvitationService($this->db);
        $ana = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById(43);
        foreach ([fn () => $service->respond($token, true), fn () => $service->respondOwned(10, $ana, true)] as $call) {
            try { $call(); self::fail('Ineligible acceptance succeeded.'); }
            catch (DomainException $error) { self::assertSame(JemInvitationAccess::ACCESS_CHANGED, $error->getMessage()); }
            $row = $this->db->setQuery('SELECT * FROM #__jem_register WHERE id=10')->loadObject();
            self::assertSame(0, (int) $row->status);
            self::assertSame(1, (int) $row->revision);
            self::assertSame($digest, $row->invitation_token_hash);
        }
    }

    /** @return void Deliverability is rechecked and does not treat external capability holders as guests. */
    public function testDeliveryStopsStaleUserInvitationButPreservesExternalInvitation(): void
    {
        if (!is_file(JEM_TEST_ROOT . '/site/classes/notificationservice.class.php')) { self::markTestSkipped('JEM 5.1 queue.'); }
        require_once JEM_TEST_ROOT . '/site/classes/notificationservice.class.php';
        $this->db->setQuery('UPDATE #__jem_events SET access=3')->execute();
        $this->db->setQuery("INSERT INTO #__jem_notifications (id,registration_id,registration_revision,notification_type,state,attempt_count,max_attempts) VALUES (1,10,1,'invitation_invitation','queued',0,4)")->execute();
        $service = new JemNotificationService($this->db);
        self::assertFalse($service->beginAttempt(1));
        self::assertSame('cancelled', $service->getById(1)->state);
        self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_notifications_attempts')->loadResult());
        $this->db->setQuery('UPDATE #__jem_events SET access=2')->execute();
        self::assertFalse($service->beginAttempt(1), 'Restoring access does not automatically resend.');
        $this->db->setQuery('UPDATE #__jem_events SET access=3')->execute();
        $this->db->setQuery('UPDATE #__jem_register SET uid=NULL')->execute();
        $this->db->setQuery("UPDATE #__jem_notifications SET state='queued'")->execute();
        self::assertIsObject($service->beginAttempt(1));
    }

    /** @return iterable Bulk resolution and external emails matching a Joomla account use the same policy. */
    public static function audiences(): iterable
    {
        yield 'selected user' => ['users', ['users' => [43]]];
        yield 'group' => ['groups', ['groups' => [2]]];
        yield 'external email matching account' => ['users', ['users' => [], 'external' => 'Ana; ana@example.test']];
        yield 'whole series with one inaccessible date' => ['users', ['users' => [43], 'scope' => 'series']];
    }

    /** @param string $audience Target mode. @param array $changes Input. @return void */
    #[DataProvider('audiences')]
    public function testComposerRejectsRecipientAtServerBoundary(string $audience, array $changes): void
    {
        if (!is_file(JEM_TEST_ROOT . '/site/classes/invitationservice.class.php')) { self::markTestSkipped('JEM 5.1 composer.'); }
        require_once JEM_TEST_ROOT . '/site/classes/invitationservice.class.php';
        $this->db->setQuery('ALTER TABLE #__jem_register ADD guest_email TEXT')->execute();
        $this->db->setQuery('DELETE FROM #__jem_register')->execute();
        $this->db->setQuery('UPDATE #__jem_events SET access=3')->execute();
        if (($changes['scope'] ?? '') === 'series') {
            $this->db->setQuery('UPDATE #__jem_events SET access=2')->execute();
            $this->db->setQuery("INSERT INTO #__jem_events (id,title,published,event_status,registra,recurrence_first_id,access) VALUES (8,'Special date',1,'scheduled',1,7,3)")->execute();
            $this->db->setQuery('INSERT INTO #__jem_cats_event_relations VALUES (1,8)')->execute();
        }
        $actor = new class {
            public int $id = 42;
            /** @return bool Fixture operator can manage invitations. */
            public function authorise($action, $asset): bool { return true; }
        };
        try {
            (new JemInvitationService($this->db))->compose(array_replace([
                'event_id' => 7, 'scope' => 'single', 'audience' => $audience, 'external' => '',
                'kind' => 'open', 'quantity' => 1, 'free' => 1,
            ], $changes), $actor);
            self::fail('Ineligible recipient reached persistence.');
        } catch (DomainException $error) {
            self::assertSame(JemInvitationAccess::NO_ACCESS, $error->getMessage());
        }
        self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register')->loadResult());
    }

    /** @return void Resending cannot rotate a link after the recipient loses access. */
    public function testResendPreservesBlockedInvitation(): void
    {
        if (!is_file(JEM_TEST_ROOT . '/site/classes/invitationservice.class.php')) { self::markTestSkipped('JEM 5.1 composer.'); }
        require_once JEM_TEST_ROOT . '/site/classes/invitationservice.class.php';
        $this->db->setQuery("UPDATE #__jem_register SET invitation_key='batch',invitation_token_hash='old'")->execute();
        $this->db->setQuery('UPDATE #__jem_events SET access=3')->execute();
        $actor = new class {
            public int $id = 42;
            /** @return bool Fixture operator can resend invitations. */
            public function authorise($action, $asset): bool { return true; }
        };
        try {
            (new JemInvitationService($this->db))->manage(10, $actor, false);
            self::fail('Resend ignored current recipient access.');
        } catch (DomainException $error) {
            self::assertSame(JemInvitationAccess::NO_ACCESS, $error->getMessage());
        }
        self::assertSame('old', $this->db->setQuery('SELECT invitation_token_hash FROM #__jem_register WHERE id=10')->loadResult());
    }

    /** @return void Related venue restrictions include its parents and the existing locked-venue exception. */
    public function testVenueHierarchy(): void
    {
        $this->db->setQuery('UPDATE #__jem_events SET locid=1')->execute();
        self::assertSame('', (new JemInvitationAccess($this->db))->message(7, 43));
        $this->db->setQuery('UPDATE #__jem_venues SET parent_venue_id=2 WHERE id=1')->execute();
        self::assertSame(JemInvitationAccess::NO_ACCESS, (new JemInvitationAccess($this->db))->message(7, 43));
    }

    /** @return iterable Pending email portal and already-confirmed holder session. */
    public static function portalStates(): iterable
    {
        yield 'pending token' => [false];
        yield 'holder session' => [true];
    }

    /** @param bool $answered Session mode. @return void Neither portal path exposes event details after access loss. */
    #[DataProvider('portalStates')]
    public function testPortalHidesDetailsAndReportsOneWarning(bool $answered): void
    {
        if (!is_file(JEM_TEST_ROOT . '/site/classes/invitationservice.class.php')) { self::markTestSkipped('JEM 5.1 portal.'); }
        class_alias(InvitationRecipientViewBase::class, 'JemView');
        require_once JEM_TEST_ROOT . '/site/views/invitation/view.html.php';
        $token = str_repeat('a', 64);
        $this->db->setQuery('UPDATE #__jem_register SET invitation_token_hash=' . $this->db->quote(JemInvitationPolicy::digest($token)))->execute();
        $this->db->setQuery('UPDATE #__jem_events SET access=3')->execute();
        $app = new class ($answered ? '' : $token) {
            public array $messages = [];
            public object $input;
            /** @param string $token Synthetic link; never logged. */
            public function __construct(string $token) { $this->input = new Joomla\Input\Input(['invitation_token' => $token]); }
            /** @return object Page-cache listener collector. */
            public function getDispatcher() { return new class { public function addListener(...$args) {} }; }
            /** @return void Headers are irrelevant to the view-access assertions. */
            public function setHeader(...$args) {}
            /** @return object Metadata sink. */
            public function getDocument() { return new class { public function setMetadata(...$args) {} }; }
            /** @return object Fixture holder session. */
            public function getSession() {
                return new class {
                    public function get($key, $default = null) { return $key === 'com_jem.invitation.holder' ? ['ids' => [10], 'until' => time() + 60] : $default; }
                    public function set(...$args) {}
                };
            }
            /** @return mixed Site configuration defaults. */
            public function get($key, $default = null) { return $default; }
            /** @return void Collect only translated warnings, never tokens. */
            public function enqueueMessage($message, $type = 'message') { $this->messages[] = $message; }
        };
        Factory::$application = $app;
        $view = new JemViewInvitation();
        $view->display();
        self::assertTrue($view->accessDenied);
        self::assertFalse($view->canAccept);
        self::assertNull($view->invitationRows[0]['event']);
        self::assertSame([JemInvitationAccess::ACCESS_CHANGED], $app->messages);
    }
}

/** Only the parent renderer is omitted; the production portal display method executes normally. */
class InvitationRecipientViewBase
{
    /** @param string|null $tpl Unused. @return void */
    public function display($tpl = null) {}
}
