<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Verify real reference queries and file deletion in disposable Joomla fixtures. */
final class HousekeepingLinkImagesTest extends TestCase
{
    private static ?array $report = null;

    /** @return array Captured source-model outcomes without changing an installed site. */
    private static function report(): array
    {
        if (self::$report !== null) {
            return self::$report;
        }
        $root = getenv('JEM_TEST_JOOMLA_ROOT');
        if (!$root || getenv('JEM_TEST_LINK_CLEANUP') !== '1') {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT and JEM_TEST_LINK_CLEANUP=1; requires disposable CREATE DATABASE access.');
        }
        $command = [PHP_BINARY, JEM_TEST_ROOT . '/tests/Support/housekeeping_link_images_probe.php', $root, JEM_TEST_ROOT];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error . $output);
        self::assertSame('', $error);
        self::$report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(105, self::$report['cases']);
        return self::$report;
    }

    /** @return iterable<string, array{string, string, string}> Area, scenario and operation. */
    public static function cases(): iterable
    {
        foreach (['events', 'venues', 'categories'] as $area) {
            foreach (['plain', 'unpublished', 'archived', 'trashed', 'fragment', 'encoded', 'backslash', 'thumbnail',
                'elsewhere', 'external', 'empty', 'escaped_key', 'broken_json', 'array_image', 'missing_table',
                'missing_column', 'stale', 'changed_file', 'only_selected'] as $scenario) {
                foreach (['quick', 'selected'] as $mode) {
                    if (in_array($scenario, ['stale', 'changed_file', 'only_selected'], true) && $mode === 'quick') {
                        continue;
                    }
                    yield "$area:$scenario:$mode" => [$area, $scenario, $mode];
                }
            }
        }
    }

    /**
     * Protect direct and link references while removing only current unused selections.
     * @param string $area Managed image area.
     * @param string $scenario Stored-reference or selection scenario.
     * @param string $mode Direct model cleanup or reviewed deletion.
     * @return void
     */
    #[DataProvider('cases')]
    public function testCleanup(string $area, string $scenario, string $mode): void
    {
        $case = self::report()['cases']["$area:$scenario:$mode"];
        if (in_array($scenario, ['broken_json', 'array_image', 'missing_table', 'missing_column'], true)) {
            self::assertNull($case['preview']);
            self::assertNull($case['deleted']);
            self::assertSame($case['preview_error'], $case['delete_error']);
            self::assertStringContainsString('No files were deleted', $case['delete_error']);
            self::assertNotEmpty($case['cause']);
            self::assertSame($case['before'], $case['after']);
            return;
        }
        self::assertNull($case['preview_error']);
        self::assertNull($case['delete_error']);
        if ($scenario === 'changed_file') {
            self::assertSame(0, $case['deleted']);
            self::assertSame(1, $case['skipped']);
            self::assertSame($case['before'], $case['after']);
            return;
        }
        $unusedLink = in_array($scenario, ['elsewhere', 'external', 'empty'], true);
        self::assertSame($unusedLink ? 2 : 1, $case['deleted']);
        $expectedRemoved = ['orphan.png', 'small/orphan.png'];
        if ($unusedLink) {
            $expectedRemoved = [...$expectedRemoved, 'linked.png', 'small/linked.png'];
        }
        self::assertSame(array_values(array_diff($case['before'], $expectedRemoved)), $case['after']);
        self::assertContains('cover.png', $case['after']);
        self::assertContains('small/cover.png', $case['after']);
        if ($area === 'events') {
            self::assertContains('detail.png', $case['after']);
            self::assertContains('small/detail.png', $case['after']);
        }
        if ($scenario === 'stale') {
            self::assertContains('linked.png', $case['preview']);
            self::assertSame(1, $case['skipped']);
        } elseif (!$unusedLink && $scenario !== 'only_selected') {
            self::assertSame(['orphan.png'], $case['preview']);
        }
    }
}
