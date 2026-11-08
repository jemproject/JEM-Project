<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Diagnose retained registration details using actual SQL, cancellation and purge operations. */
final class HousekeepingPurgedDetailsTest extends TestCase
{
    private static ?array $report = null;

    /** @return array Results from an isolated database; no installed Joomla records are changed. */
    private static function report(): array
    {
        if (self::$report !== null) {
            return self::$report;
        }
        $root = getenv('JEM_TEST_JOOMLA_ROOT');
        if (!$root || getenv('JEM_TEST_PURGED_DETAILS') !== '1') {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT and JEM_TEST_PURGED_DETAILS=1 for disposable database tests.');
        }
        $process = proc_open([PHP_BINARY, JEM_TEST_ROOT . '/tests/Support/housekeeping_purged_details_probe.php', $root, JEM_TEST_ROOT],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error . $output);
        self::assertSame('', $error);
        return self::$report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return iterable<string, array{string, int|null, int|null}> Case and expected per-table result. */
    public static function cases(): iterable
    {
        foreach (['empty', 'live', 'cancelled_parent', 'native_purge', 'cancel_then_purge', 'deleted_event',
            'multiple_revisions', 'duplicate_purge'] as $case) {
            yield $case => [$case, 0, 0];
        }
        foreach (['genuine_orphan', 'cancelled_without_purge', 'newer_revision', 'equal_revision', 'zero_revision',
            'zero_id', 'mixed'] as $case) {
            yield $case => [$case, 1, 1];
        }
        yield 'wrong_event' => ['wrong_event', 0, 1];
        yield 'missing_history_table' => ['missing_history_table', null, null];
        yield 'missing_history_revision' => ['missing_history_revision', null, null];
        yield 'missing_items' => ['missing_items', null, 0];
        yield 'missing_allocations' => ['missing_allocations', 0, null];
        yield 'missing_detail_revision' => ['missing_detail_revision', null, 0];
    }

    /**
     * Verify counts, diagnostic warnings, template output and data retention together.
     * @param string $name Fixture case.
     * @param int|null $items Expected item count or unverified state.
     * @param int|null $allocations Expected capacity count or unverified state.
     * @return void
     */
    #[DataProvider('cases')]
    public function testRetainedDetails(string $name, ?int $items, ?int $allocations): void
    {
        $case = self::report()['cases'][$name];
        $expected = ['#__jem_register_items' => $items, '#__jem_register_capacity_allocations' => $allocations];
        self::assertSame($expected, $case['counts']);
        self::assertTrue($case['unchanged'], 'Diagnostics and rendering must not change retained data.');
        self::assertSame(0, $case['diagnostics']['error']);
        $issues = array_filter($expected, static fn($count): bool => $count === null || $count > 0);
        self::assertSame(count($issues), $case['diagnostics']['warning']);
        self::assertCount(count($issues), $case['diagnostics']['lines']);
        $lines = array_values($case['diagnostics']['lines']);
        $offset = 0;
        foreach ($issues as $table => $count) {
            $code = $count === null ? 'COM_JEM_TOOLS_REGISTRATION_DETAILS_UNVERIFIED' : 'COM_JEM_TOOLS_REGISTRATION_DETAILS_ORPHANED';
            self::assertSame($code, $lines[$offset]['code']);
            self::assertStringContainsString($table, $lines[$offset]['message']);
            self::assertStringContainsString($count === null ? 'could not be verified' : 'matching purge history', $lines[$offset]['message']);
            self::assertStringContainsString($lines[$offset]['message'], $case['html']);
            $offset++;
        }
        if (!$issues) {
            self::assertStringNotContainsString('Registration details requiring review', $case['html']);
        } else {
            self::assertStringContainsString('Registration details requiring review', $case['html']);
            self::assertStringContainsString('Do not delete historical records', $case['html']);
        }
        if (in_array($name, ['cancelled_parent', 'cancel_then_purge', 'deleted_event', 'cancelled_without_purge'], true)) {
            self::assertSame(-1, $case['cancelled_status']);
        }
        if (in_array($name, ['cancel_then_purge', 'deleted_event'], true)) {
            self::assertSame(['cancelled', 'purged'], array_column($case['history'], 'action'));
            self::assertSame([4, 5], array_map('intval', array_column($case['history'], 'revision')));
            foreach ($case['history'] as $entry) {
                self::assertSame('Retained event title', $entry['event_title']);
                self::assertSame(11, (int) $entry['event_id']);
                self::assertNotEmpty($entry['registration_reference']);
            }
        }
    }
}
