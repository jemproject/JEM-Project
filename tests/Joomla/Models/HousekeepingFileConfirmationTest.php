<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Verify the real controller and template around the informed-deletion confirmation. */
final class HousekeepingFileConfirmationTest extends TestCase
{
    private static ?array $report = null;

    /** @return array Rendered views and controller results with request/model boundaries isolated. */
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
        return self::$report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return iterable<string, array{string, bool}> Submitted acknowledgement cases. */
    public static function confirmations(): iterable
    {
        yield 'explicit' => ['confirmed_references', true];
        yield 'missing' => ['unconfirmed_references', false];
        yield 'invalid' => ['invalid_confirmation', false];
    }

    /**
     * Forward only explicit acknowledgement and return friendly warnings otherwise.
     * @param string $scenario Submitted value case.
     * @param bool $allowed Whether deletion was acknowledged.
     * @return void
     */
    #[DataProvider('confirmations')]
    public function testControllerAcknowledgement(string $scenario, bool $allowed): void
    {
        $case = self::report()['cases'][$scenario];
        self::assertSame($allowed, $case['confirmed']);
        self::assertSame(0, $case['code']);
        self::assertCount(1, $case['redirects']);
        self::assertSame($allowed ? 'message' : 'warning', $case['redirects'][0][2]);
        if (!$allowed) {
            self::assertStringContainsString('No files were deleted', $case['redirects'][0][1]);
        }
    }

    /** @return void Warned files remain individually selectable and start unacknowledged. */
    public function testReviewExplainsReferencesAndThumbnails(): void
    {
        $html = self::report()['html']['referenced'];
        $document = new DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        self::assertSame(1, $xpath->query('//input[@name="confirm_references" and @value="0"]')->length);
        self::assertSame(1, $xpath->query('//input[@name="files[]" and @data-elsewhere="1" and not(@disabled) and not(@checked)]')->length);
        self::assertSame(2, $xpath->query('//input[@name="files[]" and @data-thumbnail="1"]')->length);
        self::assertStringContainsString('Appears in menu item #7', $html);
        self::assertStringContainsString('select them individually', $html);
    }
}
