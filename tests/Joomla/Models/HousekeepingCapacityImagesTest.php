<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Protect capacity media using actual model SQL and filesystem deletion in disposable fixtures. */
final class HousekeepingCapacityImagesTest extends TestCase
{
    private static ?array $report = null;

    /** @return array Captured native cleanup outcomes; no installed site is changed. */
    private static function report(): array
    {
        if (self::$report !== null) {
            return self::$report;
        }
        $root = getenv('JEM_TEST_JOOMLA_ROOT');
        if (!$root || getenv('JEM_TEST_CAPACITY_CLEANUP') !== '1') {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT and JEM_TEST_CAPACITY_CLEANUP=1; requires disposable CREATE DATABASE access.');
        }
        $command = [PHP_BINARY, JEM_TEST_ROOT . '/tests/Support/housekeeping_capacity_images_probe.php', $root, JEM_TEST_ROOT];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error . $output);
        self::assertSame('', $error);
        self::$report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(37, self::$report['cases']);
        return self::$report;
    }

    /** @return iterable<string, array{string, bool, bool}> Case, blocked inventory and no image assignments. */
    public static function cases(): iterable
    {
        $names = ['published', 'unpublished', 'missing_original', 'shared_names', 'encoded_names',
            'missing_space', 'missing_layout', 'invalid_venue', 'invalid_filename', 'empty_images', 'stale_selection'];
        foreach (['venue_capacity_profiles', 'venue_spaces', 'venue_layouts', 'venue_capacity_areas'] as $table) {
            $names[] = 'missing_table:' . $table;
            $names[] = 'missing_column:' . $table;
        }
        foreach ($names as $name) {
            foreach (['quick', 'selected'] as $mode) {
                if ($name === 'stale_selection' && $mode === 'quick') {
                    continue;
                }
                $blocked = str_starts_with($name, 'missing_table:') || str_starts_with($name, 'missing_column:')
                    || in_array($name, ['missing_space', 'missing_layout', 'invalid_venue', 'invalid_filename'], true);
                yield $name . ':' . $mode => [$name . ':' . $mode, $blocked, $name === 'empty_images'];
            }
        }
    }

    /**
     * Check preview and physical deletion against complete references, including schema failures.
     * @param string $name Probe scenario.
     * @param bool $blocked Whether reference validation must prevent every deletion.
     * @param bool $unassigned Whether the four capacity images have no stored reference.
     * @return void
     */
    #[DataProvider('cases')]
    public function testCapacityMediaSurvivesCleanup(string $name, bool $blocked, bool $unassigned): void
    {
        $case = self::report()['cases'][$name];
        if ($blocked) {
            self::assertNull($case['preview']);
            self::assertNull($case['deleted']);
            self::assertSame($case['preview_error'], $case['delete_error']);
            self::assertStringContainsString('No files were deleted', $case['delete_error']);
            self::assertSame($case['before'], $case['after']);
            self::assertNotEmpty($case['cause']);
            return;
        }
        self::assertNull($case['preview_error']);
        self::assertNull($case['delete_error']);
        self::assertSame($unassigned ? 5 : 1, $case['deleted']);
        if ($unassigned) {
            self::assertCount(5, $case['preview']);
            self::assertSame(['main.png', 'small/main.png', 'link.png', 'small/link.png'], $case['after']);
        } else {
            self::assertSame(['orphan.png'], $case['preview']);
            self::assertSame(array_values(array_diff($case['before'], ['orphan.png', 'small/orphan.png'])), $case['after']);
            foreach ($case['capacity'] as $path) {
                self::assertContains('small/' . $path, $case['after']);
            }
        }
        if ($name === 'stale_selection:selected') {
            self::assertSame(4, $case['result']['skipped'], 'References assigned after preview must be checked again.');
        }
    }

    /** @return void Open the review without deleting when a legacy cleanup button is used. */
    public function testControllerKeepsItsGuardsAndOpensTheReview(): void
    {
        $report = self::report();
        self::assertSame(2, $report['token_checks']);
        foreach ($report['controller'] as $case) {
            self::assertTrue($case['allowed']);
            self::assertCount(1, $case['redirects']);
            self::assertSame('index.php?option=com_jem&view=housekeeping&tool=files&area=venues', $case['redirects'][0][0]);
            self::assertCount(1, $case['redirects'][0]);
        }
    }

    /** @return void An incomplete venue inventory must not hide the other file areas or claim zero unused files. */
    public function testPreviewShowsAnEscapedWarningAndUnknownCount(): void
    {
        $report = self::report();
        self::assertSame(['events' => 1, 'venues' => null, 'categories' => 1, 'attachments' => 1], $report['view']['filesCounts']);
        self::assertSame([], $report['view']['filesCandidates']);
        self::assertStringContainsString('alert-warning', $report['warning_html']);
        self::assertStringContainsString('&lt;capacity&gt;', $report['warning_html']);
        self::assertStringNotContainsString('alert-success', $report['warning_html']);
        self::assertStringContainsString('$count === null', file_get_contents(JEM_TEST_ROOT . '/admin/views/housekeeping/tmpl/files.php'));
    }
}
