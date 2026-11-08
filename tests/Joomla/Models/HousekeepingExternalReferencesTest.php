<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Verify detected references and informed deletion with real SQL and disposable files. */
final class HousekeepingExternalReferencesTest extends TestCase
{
    private static ?array $report = null;

    /** @return array Probe results; installed Joomla data is never modified. */
    private static function report(): array
    {
        if (self::$report !== null) {
            return self::$report;
        }
        $root = getenv('JEM_TEST_JOOMLA_ROOT');
        if (!$root || getenv('JEM_TEST_REFERENCE_CLEANUP') !== '1') {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT and JEM_TEST_REFERENCE_CLEANUP=1 for disposable SQL fixtures.');
        }
        $process = proc_open([PHP_BINARY, JEM_TEST_ROOT . '/tests/Support/housekeeping_external_references_probe.php', $root, JEM_TEST_ROOT],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error . $output);
        self::assertSame('', $error);
        self::$report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        return self::$report;
    }

    /** @return iterable<string, array{string, string, bool}> Area, scenario and confirmation. */
    public static function cases(): iterable
    {
        foreach (['events', 'venues', 'categories'] as $area) {
            foreach (['menu_original', 'menu_thumbnail', 'orphan_thumbnail', 'article', 'core_category', 'module',
                'late_5101', 'query_failure', 'unused', 'new_reference_after_preview', 'sixth_reference',
                'longer_filename', 'external_host', 'same_host', 'root_relative', 'encoded', 'unicode_json',
                'fragment', 'entities'] as $case) {
                foreach ([false, true] as $confirmed) {
                    yield $area . ':' . $case . ':' . (int) $confirmed => [$area, $case, $confirmed];
                }
            }
        }
    }

    /**
     * Verify warnings, confirmation, stale reviews and complete scans before deletion.
     * @param string $area Managed image area.
     * @param string $scenario Stored-reference case.
     * @param bool $confirmed Explicit acknowledgement of known references.
     * @return void
     */
    #[DataProvider('cases')]
    public function testReferenceReview(string $area, string $scenario, bool $confirmed): void
    {
        $case = self::report()['cases'][$area . ':' . $scenario . ':' . (int) $confirmed];
        $unreferenced = in_array($scenario, ['unused', 'longer_filename', 'external_host', 'query_failure', 'new_reference_after_preview'], true);
        self::assertSame(!$unreferenced, !empty($case['references']));
        if ($scenario === 'query_failure' || (!$confirmed && !$unreferenced && $scenario !== 'sixth_reference')) {
            self::assertNull($case['result']);
            self::assertStringContainsString('No files were deleted', $case['error']);
            self::assertSame($scenario === 'query_failure', $case['cause']);
            self::assertSame($scenario !== 'orphan_thumbnail', $case['original_survives']);
            self::assertTrue($case['thumbnail_survives']);
        } elseif (in_array($scenario, ['new_reference_after_preview', 'sixth_reference'], true)) {
            self::assertNull($case['error']);
            self::assertSame(0, $case['result']['deleted']);
            self::assertSame(1, $case['result']['skipped']);
            self::assertTrue($case['original_survives']);
            self::assertTrue($case['thumbnail_survives']);
        } else {
            self::assertNull($case['error']);
            self::assertSame(1, $case['result']['deleted']);
            self::assertFalse($case['original_survives']);
            self::assertFalse($case['thumbnail_survives']);
        }
        if ($scenario === 'late_5101') {
            self::assertSame(5101, $case['references'][0]['id']);
        }
        if ($scenario === 'core_category') {
            self::assertSame('COM_JEM_HOUSEKEEPING_FILES_USED_JOOMLA_CATEGORY', $case['references'][0]['label']);
        }
        if ($scenario === 'sixth_reference') {
            self::assertCount(5, $case['references']);
        }
    }

    /** @return void A missing acknowledgement blocks the batch before any file deletion. */
    public function testMixedSelection(): void
    {
        $mixed = self::report()['mixed'];
        self::assertTrue($mixed['blocked']);
        self::assertTrue($mixed['unused_survives']);
        self::assertTrue($mixed['used_survives']);
        self::assertSame(2, $mixed['confirmed']['deleted']);
    }

    /** @return void Registered attachments follow actual filesystem identity. */
    public function testAttachmentCase(): void
    {
        $case = self::report()['attachments'];
        self::assertContains('event1/unused.txt', $case['candidates']);
        self::assertNotContains('event1/registered.txt', $case['candidates']);
        self::assertSame($case['case_sensitive'], in_array('event1/REGISTERED.TXT', $case['candidates'], true));
    }
}
