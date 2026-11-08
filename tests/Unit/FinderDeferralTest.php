<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FinderDeferralTest extends TestCase
{
    /** Load the actual queue and plugin with isolated Joomla side effects. */
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/site/classes/finderdeferral.class.php';
        require_once JEM_TEST_ROOT . '/tests/Support/finder_deferral_environment.php';
    }

    /** @return iterable<string, array{string}> Both event entry contexts. */
    public static function contexts(): iterable
    {
        yield 'backend' => ['com_jem.event'];
        yield 'frontend' => ['com_jem.editevent'];
    }

    /**
     * Capture access per event and avoid using a later mutable table or plugin value.
     * @param string $context Frontend or backend event context.
     * @return void
     */
    #[DataProvider('contexts')]
    public function testCommitIndexesCapturedEventsOnlyAfterTheBoundary(string $context): void
    {
        $plugin = new FinderDeferralPluginProbe();
        JemFinderDeferral::begin();
        $row = (object) ['id' => 10, 'access' => 2];
        $plugin->onFinderBeforeSave($context, $row, false);
        self::assertTrue($plugin->onFinderAfterSave($context, $row, false));
        $row->id = 99;
        $row->access = 7;
        Adapter::$storedAccess[99] = 7;
        $plugin->onFinderBeforeSave($context, $row, false);
        self::assertSame([], Adapter::$calls);
        JemFinderDeferral::end(true);
        self::assertSame([['access', 10, 2], ['index', 10]], Adapter::$calls);
        self::assertSame([], Factory::$messages);
        self::assertSame([], Log::$entries);
    }

    /**
     * A rejected save leaves all Finder work untouched.
     * @param string $context Frontend or backend event context.
     * @return void
     */
    #[DataProvider('contexts')]
    public function testRollbackDiscardsIndexAndAccessChanges(string $context): void
    {
        JemFinderDeferral::begin();
        $this->saveNotification(10, 3, $context);
        JemFinderDeferral::end(false);
        self::assertSame([], Adapter::$calls);
        self::assertSame([], Factory::$messages);
    }

    /** Verify normal saves outside a JEM transaction retain immediate indexing. */
    public function testImmediateIndexingOutsideTheScopeIsUnchanged(): void
    {
        $this->saveNotification(10, 1);
        self::assertSame([['index', 10]], Adapter::$calls);
    }

    /** Verify new events do not inherit the preceding event's access change. */
    public function testNewEventDoesNotUseStaleAccess(): void
    {
        $plugin = new FinderDeferralPluginProbe();
        $plugin->onFinderBeforeSave('com_jem.event', (object) ['id' => 10, 'access' => 7], false);
        JemFinderDeferral::begin();
        $plugin->onFinderAfterSave('com_jem.event', (object) ['id' => 11, 'access' => 2], true);
        JemFinderDeferral::end(true);
        self::assertSame([['index', 11]], Adapter::$calls);
    }

    /** Verify nested successful scopes wait for the outer commit. */
    public function testNestedCommitWaitsForOuterCommit(): void
    {
        JemFinderDeferral::begin();
        $this->saveNotification(10, 1);
        JemFinderDeferral::begin();
        $this->saveNotification(11, 1);
        JemFinderDeferral::end(true);
        self::assertSame([], Adapter::$calls);
        JemFinderDeferral::end(true);
        self::assertSame([['index', 10], ['index', 11]], Adapter::$calls);
    }

    /** @return iterable<string, array{bool, bool}> Nested rollback combinations. */
    public static function rollbackScopes(): iterable
    {
        yield 'inner rejected' => [false, true];
        yield 'outer rejected' => [true, false];
        yield 'both rejected' => [false, false];
    }

    /**
     * Never flush work belonging to an aborted nested transaction.
     * @param bool $inner Inner transaction result.
     * @param bool $outer Outer transaction result.
     * @return void
     */
    #[DataProvider('rollbackScopes')]
    public function testAnyRollbackDiscardsTheBatch(bool $inner, bool $outer): void
    {
        JemFinderDeferral::begin();
        $this->saveNotification(10, 1);
        JemFinderDeferral::begin();
        $this->saveNotification(11, 1);
        JemFinderDeferral::end($inner);
        JemFinderDeferral::end($outer);
        self::assertSame([], Adapter::$calls);
    }

    /** Retain an earlier access change while deduplicating repeated save contexts. */
    public function testRepeatedEventUsesLatestRowAndRetainsItsAccessChange(): void
    {
        JemFinderDeferral::begin();
        $this->saveNotification(10, 2);
        Adapter::$storedAccess[10] = 2;
        $this->saveNotification(10, 2, 'com_jem.editevent');
        $this->saveNotification(11, 1);
        JemFinderDeferral::end(true);
        self::assertSame([['access', 10, 2], ['index', 10], ['index', 11]], Adapter::$calls);
    }

    /** Invalid IDs must not invoke an indexer inside a transaction. */
    public function testMissingIdsAreDiscarded(): void
    {
        JemFinderDeferral::begin();
        $this->saveNotification(0, 1);
        $this->saveNotification(-1, 1);
        JemFinderDeferral::end(true);
        self::assertSame([], Adapter::$calls);
    }

    /** Rollback must not contaminate the next save in the same request. */
    public function testNextScopeStartsEmptyAfterRollback(): void
    {
        JemFinderDeferral::begin();
        $this->saveNotification(10, 3);
        JemFinderDeferral::end(false);
        JemFinderDeferral::begin();
        $this->saveNotification(11, 1);
        JemFinderDeferral::end(true);
        self::assertSame([['index', 11]], Adapter::$calls);
    }

    /** Failed index updates do not abort the remaining committed series items. */
    public function testPostCommitErrorsWarnOnceAndContinueTheBatch(): void
    {
        Adapter::$indexCallback = static function (int $id): void {
            if ($id !== 12) {
                throw new RuntimeException('Finder SQL failure ' . $id);
            }
        };
        JemFinderDeferral::begin();
        foreach ([10, 11, 12] as $id) {
            $this->saveNotification($id, 1);
        }
        JemFinderDeferral::end(true);
        self::assertSame([['index', 10], ['index', 11], ['index', 12]], Adapter::$calls);
        self::assertCount(2, Log::$entries);
        self::assertStringContainsString('event 10', Log::$entries[0][0]);
        self::assertStringContainsString('Finder SQL failure 10', Log::$entries[0][0]);
        self::assertSame([['COM_JEM_FINDER_INDEX_FAILED_AFTER_SAVE', 'warning']], Factory::$messages);
        self::assertFalse(JemFinderDeferral::defer(20, static function (): void {}, false));
    }

    /** Notification and logging failures cannot escape into a committed save. */
    public function testReportingFailuresAreIsolated(): void
    {
        Adapter::$indexCallback = static function (): void { throw new RuntimeException('Index failed'); };
        Log::$fail = true;
        Factory::$failWarning = true;
        JemFinderDeferral::begin();
        $this->saveNotification(10, 1);
        JemFinderDeferral::end(true);
        self::assertSame([['index', 10]], Adapter::$calls);
        self::assertFalse(JemFinderDeferral::defer(11, static function (): void {}, false));
    }

    /** Keep immediate-save exceptions visible when there was no owned transaction. */
    public function testImmediateIndexFailureIsNotSilentlySuppressed(): void
    {
        Adapter::$indexCallback = static function (): void { throw new RuntimeException('Immediate failure'); };
        $this->expectExceptionMessage('Immediate failure');
        $this->saveNotification(10, 1);
    }

    /** The queue is cleared before callbacks, allowing independent reentrant work. */
    public function testCallbackCanOpenAnIndependentScope(): void
    {
        $calls = [];
        JemFinderDeferral::begin();
        JemFinderDeferral::defer(10, static function () use (&$calls): void {
            $calls[] = 10;
            JemFinderDeferral::begin();
            JemFinderDeferral::defer(11, static function () use (&$calls): void { $calls[] = 11; }, false);
            JemFinderDeferral::end(true);
        }, false);
        JemFinderDeferral::end(true);
        JemFinderDeferral::end(true);
        self::assertSame([10, 11], $calls);
    }

    /** Other contexts retain their existing plugin behaviour. */
    public function testOtherContextsAreNotQueued(): void
    {
        $plugin = new FinderDeferralPluginProbe();
        JemFinderDeferral::begin();
        $plugin->onFinderAfterSave('com_content.article', (object) ['id' => 10, 'access' => 1], false);
        $plugin->onFinderAfterSave('com_jem.category', (object) ['id' => 20, 'access' => 2], false);
        JemFinderDeferral::end(false);
        self::assertSame([['category', 20]], Adapter::$calls);
    }

    /** Check the owning model wires its queue around all transaction exit paths. */
    public function testModelClosesDeferralAfterRollbackAndMarksSuccessfulCommits(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/models/event.php');
        self::assertSame(1, preg_match('/public function save\(\$data\).*?(?=\n    \/\*\*)/s', $source, $match));
        $save = $match[0];
        self::assertLessThan(strpos($save, 'parent::save($data)'), strpos($save, 'JemFinderDeferral::begin();'));
        self::assertStringContainsString('$finderCommitted = false;', $save);
        foreach (str_contains($save, '$pricingDb') ? ['series', 'pricing'] : ['series'] as $kind) {
            self::assertStringContainsString('$' . $kind . "Db->transactionCommit();\n                $" . $kind . "TransactionActive = false;\n                " . '$finderCommitted = true;', str_replace("\r\n", "\n", $save));
        }
        $finallyOffset = strpos($save, '} finally {');
        self::assertNotFalse($finallyOffset);
        $finally = substr($save, $finallyOffset);
        self::assertStringContainsString('JemFinderDeferral::end($finderCommitted);', $finally);
        self::assertLessThan(strpos($finally, 'JemFinderDeferral::end('), strrpos($finally, '->transactionRollback();'));
        self::assertStringContainsString("} finally {\n            try {", str_replace("\r\n", "\n", $save));
        self::assertSame(1, substr_count($save, 'JemFinderDeferral::end('));
        foreach (['admin', 'site'] as $client) {
            self::assertStringContainsString('COM_JEM_FINDER_INDEX_FAILED_AFTER_SAVE=', file_get_contents(JEM_TEST_ROOT . '/' . $client . '/language/en-GB/com_jem.ini'));
        }
    }

    /**
     * Exercise real JEM before/after notifications using recorded old access levels.
     * @param int $id Event ID.
     * @param int $access New access level.
     * @param string $context Save context.
     * @return void
     */
    private function saveNotification(int $id, int $access, string $context = 'com_jem.event'): void
    {
        $plugin = new FinderDeferralPluginProbe();
        $row = (object) ['id' => $id, 'access' => $access];
        $plugin->onFinderBeforeSave($context, $row, false);
        self::assertTrue($plugin->onFinderAfterSave($context, $row, false));
    }
}
