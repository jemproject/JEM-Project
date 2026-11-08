<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Exercise cancellation persistence and both native frontend layouts without changing an installed site. */
final class PricedFrontendCancellationTest extends TestCase
{
    private static ?array $report = null;

    /** @return array Results from actual source with disposable SQL data and rendered templates. */
    private static function report(): array
    {
        if (self::$report !== null) { return self::$report; }
        $root = getenv('JEM_TEST_JOOMLA_ROOT');
        if (!$root || getenv('JEM_TEST_PRICED_CANCELLATION') !== '1') {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT and JEM_TEST_PRICED_CANCELLATION=1 for disposable database tests.');
        }
        $process = proc_open([PHP_BINARY, JEM_TEST_ROOT . '/tests/Support/priced_frontend_cancellation_probe.php', $root, JEM_TEST_ROOT],
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

    /** @return iterable<string, array{string, bool}> Cancellation operation and expected success. */
    public static function operations(): iterable
    {
        foreach (['single', 'multiple', 'priced', 'waiting', 'areas', 'series_equal', 'classic_full',
            'classic_partial', 'event_now_classic', 'external_payment_preserved', 'unpriced_event_now_priced'] as $case) {
            yield $case => [$case, true];
        }
        foreach (['partial', 'zero', 'excess', 'no_confirmation', 'changed_revision', 'series_unequal'] as $case) {
            yield $case => [$case, false];
        }
    }

    /** Verify cancellation releases capacity, retains history and never manufactures a payment/refund state. */
    #[DataProvider('operations')]
    public function testCancellationOperation(string $case, bool $success): void
    {
        $result = self::report()['cases'][$case];
        if (!$success) { self::assertTrue($result['refused']); return; }
        self::assertTrue($result['saved']);
        $partial = in_array($case, ['classic_partial', 'unpriced_event_now_priced'], true);
        self::assertSame($partial ? 1 : -1, $result['status']);
        self::assertSame($partial ? 3 : 0, $result['active_places']);
        self::assertSame($partial ? 'places_changed' : 'cancelled', $result['history_action']);
        self::assertSame(4, $result['history_old_places']);
        self::assertSame($case === 'external_payment_preserved' ? 'paid' : null, $result['payment_state']);
        self::assertSame('48.00', $result['amount']);
    }

    /** @return iterable<string, array{string, string}> Native layout and booking scenario. */
    public static function forms(): iterable
    {
        foreach (['default_regform.php', 'responsive/default_regform.php'] as $layout) {
            foreach (['priced_new', 'priced_existing', 'priced_waiting', 'priced_invited', 'priced_cancelled',
                'priced_closed', 'priced_areas', 'priced_series', 'priced_classic_event', 'unpriced_priced_event',
                'requested_no_answer', 'requested_no_answer_closed', 'classic_new', 'classic_existing'] as $case) {
                yield $layout . '/' . $case => [$layout, $case];
            }
        }
    }

    /** Verify available actions, whole cancellation, negative responses and unchanged classic controls. */
    #[DataProvider('forms')]
    public function testNativeForm(string $layout, string $case): void
    {
        $form = self::report()['ui'][$layout . '/' . $case];
        $classic = str_starts_with($case, 'classic_');
        $inactive = in_array($case, ['priced_new', 'priced_cancelled', 'priced_closed', 'requested_no_answer_closed'], true);
        self::assertSame($classic, $form['attend_enabled']);
        self::assertSame(!$classic, $form['notice']);
        self::assertSame(!$inactive, $form['submit']);
        self::assertSame(!$inactive, $form['dialog']);
        self::assertSame(0, $form['script']);
        self::assertSame(in_array($case, ['unpriced_priced_event', 'classic_existing'], true), $form['editable_quantity']);
        self::assertSame(0, $form['editable_areas']);
        if (!$classic && !$inactive) {
            self::assertTrue($form['cancel_selected']);
            self::assertSame($case === 'requested_no_answer' ? '0' : ($case === 'unpriced_priced_event' ? '' : '4'), $form['fixed_quantity']);
        }
        self::assertSame($case === 'priced_series' ? 1 : 0, $form['series']);
    }

    /** Guard priced orders independently of the event's current pricing configuration. */
    public function testStoredPricedOrdersRejectClassicBookingRequests(): void
    {
        self::assertSame([1 => true, -1 => true, 2 => true], self::report()['guards']);
    }

    /** Explain retained historical quantities without presenting them as occupied capacity. */
    public function testHistoryExplainsReleasedPlaces(): void
    {
        self::assertTrue(self::report()['history_message']);
    }
}
