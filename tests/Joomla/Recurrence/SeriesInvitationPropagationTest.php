<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Native SQL regression for the legacy pending-invitation propagation contract. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SeriesInvitationPropagationTest extends TestCase
{
    private object $db;
    private object $server;
    private string $database;

    /** @return void Build an isolated schema; installed Joomla data is never written. */
    protected function setUp(): void
    {
        $root = (string) getenv('JEM_TEST_JOOMLA_ROOT');
        if (!extension_loaded('mysqli') || !is_file($root . '/configuration.php')) {
            self::markTestSkipped('Requires local Joomla libraries and MariaDB access for a disposable database.');
        }
        [$this->db, $this->server, $this->database] = require JEM_TEST_ROOT . '/tests/Support/series_invitation_environment.php';
        $this->db->setQuery("INSERT INTO #__jem_categories (id,catname,title,published,access) VALUES (1,'Public','Public',1,1)")->execute();
        $this->event(1);
        $this->event(2, ['recurrence_first_id' => 1]);
    }

    /** @return void Remove only the randomly named fixture database. */
    protected function tearDown(): void
    {
        if (isset($this->server, $this->database)) {
            $this->db->disconnect();
            $this->server->query('DROP DATABASE IF EXISTS ' . $this->database);
        }
    }

    /** @param int $id Event ID. @param array $changes Field overrides. @return void */
    private function event(int $id, array $changes = []): void
    {
        $event = (object) array_replace([
            'id' => $id, 'title' => 'Invitation QA ' . $id, 'alias' => 'invitation-qa-' . $id,
            'published' => 1, 'event_status' => 'scheduled', 'access' => 1, 'registra' => 1,
            'reginvitedonly' => 1, 'dates' => date('Y-m-d'), 'times' => '10:00:00',
            'introtext' => '', 'fulltext' => '', 'metadata' => '{}', 'created_by_alias' => '',
        ], $changes);
        $this->db->insertObject('#__jem_events', $event);
        $this->db->setQuery('INSERT INTO #__jem_cats_event_relations (catid,itemid) VALUES (1,' . $id . ')')->execute();
    }

    /** @param int $event Event. @param int $uid Recipient. @param int $status Stored status. @param int $waiting Waiting flag. @param int $places Quantity. @return void */
    private function registration(int $event, int $uid, int $status = 0, int $waiting = 0, int $places = 1): void
    {
        $row = (object) compact('event', 'uid', 'status', 'waiting', 'places');
        $this->db->insertObject('#__jem_register', $row);
    }

    /** @param int $event Occurrence. @return array Complete saved rows, keyed by holder. */
    private function rows(int $event): array
    {
        return $this->db->setQuery('SELECT * FROM #__jem_register WHERE event=' . $event . ' ORDER BY uid')->loadAssocList('uid');
    }

    /** @return void Copy pending invitations and quantities without overwriting any target response. */
    public function testPendingOnlyPreservesEveryExistingStateAndIsIdempotent(): void
    {
        foreach (range(43, 48) as $uid) { $this->registration(1, $uid, 0, 0, $uid === 44 ? 2 : 1); }
        $this->registration(1, 49, 1);
        $this->registration(1, 50, -1);
        $this->registration(1, 51, 1, 1);
        foreach ([[45, 1, 0], [46, 1, 1], [47, -1, 0], [48, 0, 0]] as [$uid, $status, $waiting]) {
            $this->registration(2, $uid, $status, $waiting);
        }
        $before = $this->rows(2);
        self::assertTrue(JemHelper::copyEventInvitations(1, 2));
        $after = $this->rows(2);
        self::assertCount(6, $after);
        foreach ($before as $uid => $row) { self::assertSame($row, $after[$uid]); }
        self::assertSame(2, (int) $after[44]['places']);
        self::assertSame(0, (int) $after[43]['status']);
        self::assertTrue(JemHelper::copyEventInvitations(1, 2));
        self::assertSame($after, $this->rows(2));
        self::assertSame(0, SeriesInvitationPlugins::$imports);
    }

    /** @return iterable Unavailable event states, including publication scheduling. */
    public static function unavailableEvents(): iterable
    {
        foreach ([0, 2, -2] as $state) { yield 'publication ' . $state => ['published=' . $state]; }
        yield 'cancelled' => ["event_status='cancelled'"];
        yield 'postponed' => ["event_status='postponed'"];
        yield 'future publication' => ["publish_up='2099-01-01 00:00:00'"];
        yield 'expired publication' => ["publish_down='2000-01-01 00:00:00'"];
    }

    /** @param string $change SQL fixture change. @return void Do not invite to an unavailable date. */
    #[DataProvider('unavailableEvents')]
    public function testUnavailableOccurrencesDoNotReceiveInvitations(string $change): void
    {
        $this->registration(1, 43);
        $this->db->setQuery('UPDATE #__jem_events SET ' . $change . ' WHERE id=2')->execute();
        self::assertTrue(JemHelper::copyEventInvitations(1, 2));
        self::assertSame([], $this->rows(2));
    }

    /** @return void Invalid accounts and inaccessible events are skipped without rejecting eligible invitees. */
    public function testRecipientAccessAndIdentityAreRetained(): void
    {
        foreach ([43, 44, 45, 52] as $uid) { $this->registration(1, $uid); }
        $this->db->setQuery('UPDATE #__users SET block=1 WHERE id=44')->execute();
        $this->db->setQuery("UPDATE #__users SET activation='pending' WHERE id=45")->execute();
        $this->db->setQuery('UPDATE #__jem_events SET access=2 WHERE id=2')->execute();
        self::assertTrue(JemHelper::copyEventInvitations(1, 2));
        self::assertSame([43], array_map('intval', array_keys($this->rows(2))));
        foreach ([[0, 2], [1, 1], [1, 999], [2, 999]] as [$source, $target]) {
            self::assertFalse(JemHelper::copyEventInvitations($source, $target));
        }
        $this->event(3);
        self::assertFalse(JemHelper::copyEventInvitations(1, 3));
    }

    /** @return void A completed invitation batch must not commit the caller's occurrence. */
    public function testOuterRollbackStillRemovesTheOccurrence(): void
    {
        $this->registration(1, 43);
        $this->db->transactionStart();
        $this->event(3, ['recurrence_first_id' => 1]);
        self::assertTrue(JemHelper::copyEventInvitations(1, 3));
        self::assertCount(1, $this->rows(3));
        $this->db->transactionRollback();
        self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_events WHERE id=3')->loadResult());
        self::assertSame([], $this->rows(3));
        self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_cats_event_relations WHERE itemid=3')->loadResult());
    }

    /** @return void Inject a real SQL failure after the first invitation in a batch. */
    private function rejectSecondRecipient(): void
    {
        $this->server->select_db($this->database);
        $this->server->query("CREATE TRIGGER qa_reject_invitation BEFORE INSERT ON qa_jem_register FOR EACH ROW BEGIN IF NEW.event<>1 AND NEW.uid=44 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='QA invitation write failure'; END IF; END");
    }

    /** @return void Batch failure rolls back its writes while retaining the outer transaction. */
    public function testFailedBatchIsAtomic(): void
    {
        $this->registration(1, 43); $this->registration(1, 44);
        $this->rejectSecondRecipient();
        $this->db->transactionStart();
        $this->db->setQuery("UPDATE #__jem_events SET title='Outer change' WHERE id=2")->execute();
        self::assertFalse(JemHelper::copyEventInvitations(1, 2));
        self::assertSame([], $this->rows(2));
        self::assertSame('Outer change', $this->db->setQuery('SELECT title FROM #__jem_events WHERE id=2')->loadResult());
        $this->db->transactionRollback();
        self::assertSame('Invitation QA 2', $this->db->setQuery('SELECT title FROM #__jem_events WHERE id=2')->loadResult());
    }

    /** @param int $count Date count. @return array Valid custom dates consumed by the real model. */
    private function schedule(int $count): array
    {
        $rows = [];
        for ($i = 0; $i < $count; ++$i) {
            $rows[] = ['event_id' => 0, 'date' => date('Y-m-d', strtotime('+' . $i . ' day')), 'time' => '10:00:00', 'end_date' => '', 'end_time' => ''];
        }
        return $rows;
    }

    /** @return SeriesInvitationModel Real series model with isolated CRUD boundaries. */
    private function model(): SeriesInvitationModel
    {
        return (new ReflectionClass(SeriesInvitationModel::class))->newInstanceWithoutConstructor();
    }

    /** @return void Exercise creation, schedule extension and save-all with preserved responses. */
    public function testCustomDatesAndSaveAll(): void
    {
        $this->registration(1, 43);
        $model = $this->model();
        self::assertTrue((new ReflectionMethod($model, 'createCustomEventSeries'))->invoke($model, 1, $this->schedule(3), [1], true), (string) $model->getError());
        $seriesId = (int) $this->db->setQuery('SELECT series_id FROM #__jem_events WHERE id=1')->loadResult();
        $ids = $this->db->setQuery('SELECT id FROM #__jem_events WHERE series_id=' . $seriesId . ' ORDER BY series_order')->loadColumn();
        self::assertCount(3, $ids);
        foreach ($ids as $id) { self::assertCount(1, $this->rows((int) $id)); }
        $this->db->setQuery('UPDATE #__jem_register SET status=1 WHERE event=' . (int) $ids[1])->execute();
        $before = $this->rows((int) $ids[1]);
        $schedule = $this->schedule(4);
        foreach ($ids as $i => $id) { $schedule[$i]['event_id'] = (int) $id; }
        self::assertTrue((new ReflectionMethod($model, 'synchroniseCustomSeriesSchedule'))->invoke($model, $seriesId, 1, $schedule, [1], true), (string) $model->getError());
        $this->registration(1, 44);
        self::assertTrue((new ReflectionMethod($model, 'propagateCustomSeriesFields'))->invoke($model, $seriesId, 1, ['title' => 'Updated series'], [1], true), (string) $model->getError());
        $ids = $this->db->setQuery('SELECT id FROM #__jem_events WHERE series_id=' . $seriesId)->loadColumn();
        self::assertCount(4, $ids);
        foreach ($ids as $id) { self::assertCount(2, $this->rows((int) $id)); }
        self::assertSame($before[43], $this->rows((int) $schedule[1]['event_id'])[43]);
        self::assertSame(0, SeriesInvitationPlugins::$imports);
    }

    /** @return void Custom-series failure does not leave a partial series or its new invitations. */
    public function testCustomCreationFailureRollsBack(): void
    {
        $this->registration(1, 43); $this->registration(1, 44); $this->rejectSecondRecipient();
        $model = $this->model();
        self::assertFalse((new ReflectionMethod($model, 'createCustomEventSeries'))->invoke($model, 1, $this->schedule(3), [1], true));
        self::assertSame(2, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_events')->loadResult());
        self::assertSame(0, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_event_series')->loadResult());
        self::assertSame(2, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register')->loadResult());
    }

    /** @return iterable Both scoped save generation and ordinary daily cleanup, with SQL failures. */
    public static function generationCases(): iterable
    {
        yield 'scoped success' => [1, false, 2]; yield 'scoped failure' => [1, true, 2];
        yield 'forced daily success' => [0, false, 2]; yield 'forced daily failure' => [0, true, 2];
        yield 'automatic daily success' => [0, false, 0]; yield 'automatic daily failure' => [0, true, 0];
    }

    /** @param int $scope Root or global cleanup. @param bool $fail SQL error injection. @param int $forced Manual or automatic run. @return void */
    #[DataProvider('generationCases')]
    public function testWeeklyGenerationIsAtomic(int $scope, bool $fail, int $forced): void
    {
        $this->db->setQuery('DELETE FROM #__jem_events WHERE id=2')->execute();
        $this->db->setQuery('DELETE FROM #__jem_cats_event_relations WHERE itemid=2')->execute();
        $this->db->setQuery("UPDATE #__jem_events SET recurrence_type=2,recurrence_number=1,recurrence_limit_date="
            . $this->db->quote(date('Y-m-d', strtotime('+14 days'))) . ' WHERE id=1')->execute();
        $this->db->setQuery("INSERT INTO #__jem_links (event_id,type,title,url,created_by) VALUES (1,'url','Fixture','https://example.invalid',42)")->execute();
        $this->registration(1, 43); $this->registration(1, 44);
        if ($fail) { $this->rejectSecondRecipient(); }
        try {
            JemHelper::cleanup($forced, $scope);
            self::assertFalse($fail && $forced > 0, 'A forced run must report the SQL failure.');
        } catch (RuntimeException $error) {
            if (!$fail) { throw $error; }
            self::assertStringContainsString('INVITED_USERS', $error->getMessage());
        }
        $count = $fail ? 1 : 3;
        self::assertSame($count, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_events')->loadResult());
        self::assertSame($count, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_cats_event_relations')->loadResult());
        self::assertSame($count, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_links')->loadResult());
        self::assertSame($count * 2, (int) $this->db->setQuery('SELECT COUNT(*) FROM #__jem_register')->loadResult());
        if ($fail && !$forced) {
            self::assertStringContainsString('Daily event cleanup failed', implode('\n', SeriesInvitationPlugins::$logs));
        }
    }
}
