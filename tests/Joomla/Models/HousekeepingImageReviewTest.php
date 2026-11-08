<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Verify the controller boundary and actual review markup without an installed site. */
final class HousekeepingImageReviewTest extends TestCase
{
    private static ?array $report = null;

    /** @return array Actual controller/template output with request and model boundaries isolated. */
    private static function report(): array
    {
        if (self::$report !== null) {
            return self::$report;
        }
        $process = proc_open([PHP_BINARY, JEM_TEST_ROOT . '/tests/Support/housekeeping_image_review_probe.php', JEM_TEST_ROOT],
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

    /** @return iterable<string, array{string}> Controller operation under test. */
    public static function cases(): iterable
    {
        foreach (['open_events', 'open_venues', 'open_categories', 'unknown_task', 'success', 'empty', 'bad_nonce', 'permission',
            'csrf', 'references', 'invalid_selection', 'replay'] as $name) {
            yield $name => [$name];
        }
    }

    /**
     * Enforce review, guards, one-use confirmation and clear failure responses.
     * @param string $name Request scenario.
     * @return void
     */
    #[DataProvider('cases')]
    public function testControllerWorkflow(string $name): void
    {
        $report = self::report();
        $case = $report['cases'][$name];
        if (str_starts_with($name, 'open_')) {
            self::assertSame(0, $case['calls']);
            self::assertCount(1, $case['redirects']);
            self::assertCount(1, $case['redirects'][0]);
            $area = substr($name, 5);
            $target = $report['modern'] ? '&tool=files&area=' . $area : '&tool=images&type=' . ['events' => 1, 'venues' => 2, 'categories' => 3][$area];
            self::assertStringEndsWith($target, $case['redirects'][0][0]);
        } elseif (in_array($name, ['unknown_task', 'bad_nonce', 'permission', 'csrf'], true)) {
            self::assertSame(0, $case['calls']);
            self::assertSame($name === 'unknown_task' ? 404 : 403, $case['code']);
            self::assertSame([], $case['redirects']);
        } else {
            self::assertSame($name === 'empty' && $report['modern'] ? 0 : 1, $case['calls']);
            self::assertCount(1, $case['redirects']);
            self::assertSame($name === 'replay' ? 403 : 0, $case['code']);
            self::assertSame(in_array($name, ['success', 'replay'], true) ? 'message' : 'warning', $case['redirects'][0][2]);
            self::assertSame($report['modern'] ? 'housekeeping.deleteUnusedFiles' : 'housekeeping.images.1', $case['purposes'][0]);
            if ($name === 'references') {
                self::assertSame('Unverified <image> references', $case['redirects'][0][1]);
            }
        }
    }

    /** @return void Reviewed entries start unchecked, escape filenames and require a final confirmation. */
    public function testReviewTemplate(): void
    {
        $html = self::report()['html']['normal'];
        self::assertStringContainsString('orphan &lt;photo&gt;.png', $html);
        self::assertDoesNotMatchRegularExpression('/<input[^>]+\schecked(?:\s|=|>)/', $html);
        self::assertStringContainsString('disabled', $html);
        self::assertStringContainsString('window.confirm', $html);
        self::assertStringContainsString('review-token', $html);
        self::assertStringContainsString('name="csrf"', $html);
    }

    /** @return void Failed inventories show a warning and cannot submit a deletion. */
    public function testFailedAndEmptyReviewsHaveNoDeleteForm(): void
    {
        foreach (['error', 'empty'] as $state) {
            $html = self::report()['html'][$state];
            self::assertStringNotContainsString('<form', $html);
            self::assertStringNotContainsString('review-token', $html);
        }
        self::assertStringContainsString('Unverified &lt;image&gt; references', self::report()['html']['error']);
        self::assertStringContainsString('alert-warning', self::report()['html']['error']);
    }
}
