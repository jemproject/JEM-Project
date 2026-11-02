<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Event-level labels are informational; no booking operation is performed by this policy. */
final class TicketAvailabilityTest extends TestCase
{
    /** @return void Load the actual standalone policy. */
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/site/classes/ticketavailability.class.php';
    }

    /** @return iterable<string, array{array, array, bool, string}> Strict boundaries and explicit states. */
    public static function scenarios(): iterable
    {
        $base = ['ticket_availability' => 'instock', 'maxplaces' => 100, 'booked' => 0, 'reservedplaces' => 0, 'waitinglist' => 0];
        foreach ([
            'many left' => [0, 'instock'],
            'eleven left' => [89, 'instock'],
            'ten left' => [90, 'instock'],
            'nine left' => [91, 'limited'],
            'one left' => [99, 'limited'],
            'full' => [100, 'soldout'],
            'overbooked' => [101, 'soldout'],
        ] as $label => [$booked, $expected]) {
            foreach (['places', 'percent'] as $mode) {
                yield $label . '/' . $mode => [array_replace($base, ['booked' => $booked]), ['module_status_last_places_mode' => $mode], true, $expected];
            }
        }
        foreach ([
            [1000, 900, 'instock'], [1000, 901, 'limited'],
            [15, 14, 'limited'], [10, 9, 'instock'], [9, 8, 'instock'],
        ] as [$capacity, $booked, $expected]) {
            yield 'percentage/' . $capacity . '/' . $booked => [array_replace($base, ['maxplaces' => $capacity, 'booked' => $booked]), ['module_status_last_places_mode' => 'percent'], true, $expected];
        }
        yield '1000 seats absolute threshold' => [array_replace($base, ['maxplaces' => 1000, 'booked' => 901]), [], true, 'instock'];
        yield 'reserved seats reach warning' => [array_replace($base, ['booked' => 89, 'reservedplaces' => 2]), [], true, 'limited'];
        yield 'reserved seats fill event' => [array_replace($base, ['booked' => 90, 'reservedplaces' => 10]), [], true, 'soldout'];
        yield 'waiting list' => [array_replace($base, ['booked' => 100, 'waitinglist' => 1]), [], true, 'waitinglist'];
        yield 'waiting registrations do not consume seats' => [array_replace($base, ['booked' => 89, 'waiting' => 25]), [], true, 'instock'];
        yield 'unlimited' => [array_replace($base, ['maxplaces' => 0]), [], true, 'instock'];
        yield 'unknown capacity' => [['ticket_availability' => 'instock', 'booked' => 0], [], true, 'instock'];
        yield 'unknown totals' => [['ticket_availability' => 'instock', 'maxplaces' => 5], [], true, 'instock'];
        yield 'null totals' => [['maxplaces' => 5, 'booked' => null, 'regCount' => null], [], true, 'instock'];
        yield 'module count alias' => [['maxplaces' => 100, 'regCount' => 89, 'reserved' => 2], [], true, 'limited'];
        yield 'detail count precedence' => [array_replace($base, ['booked' => 90, 'regCount' => 99]), [], true, 'instock'];
        yield 'closed window suppresses automatic warning' => [array_replace($base, ['booked' => 99]), [], false, 'instock'];
        yield 'custom absolute threshold equality' => [array_replace($base, ['booked' => 80]), ['module_status_last_places_threshold' => 20], true, 'instock'];
        yield 'custom absolute threshold below' => [array_replace($base, ['booked' => 81]), ['module_status_last_places_threshold' => 20], true, 'limited'];
        yield 'custom percent equality' => [array_replace($base, ['maxplaces' => 200, 'booked' => 160]), ['module_status_last_places_mode' => 'percent', 'module_status_last_places_threshold' => 20], true, 'instock'];
        yield 'custom percent below' => [array_replace($base, ['maxplaces' => 200, 'booked' => 161]), ['module_status_last_places_mode' => 'percent', 'module_status_last_places_threshold' => 20], true, 'limited'];
        foreach (['limited', 'preorder', 'soldout'] as $stored) {
            foreach ([true, false] as $open) {
                yield 'manual/' . $stored . '/' . (int) $open => [array_replace($base, ['ticket_availability' => $stored]), [], $open, $stored];
            }
        }
        yield 'manual external unknown capacity' => [['ticket_availability' => 'limited'], [], false, 'limited'];
        yield 'manual full superseded' => [array_replace($base, ['ticket_availability' => 'limited', 'booked' => 100]), [], true, 'soldout'];
        yield 'manual full waiting' => [array_replace($base, ['ticket_availability' => 'limited', 'booked' => 100, 'waitinglist' => 1]), [], false, 'waitinglist'];
        yield 'explicit preorder preserved when full' => [array_replace($base, ['ticket_availability' => 'preorder', 'booked' => 100]), [], true, 'preorder'];
        yield 'explicit soldout preserved' => [array_replace($base, ['ticket_availability' => 'soldout', 'waitinglist' => 1]), [], true, 'soldout'];
        yield 'invalid availability fallback' => [array_replace($base, ['ticket_availability' => 'unknown']), [], true, 'instock'];
        yield 'unlimited manual warning' => [array_replace($base, ['ticket_availability' => 'limited', 'maxplaces' => 0]), [], true, 'limited'];
        foreach (['classic', 'configured', 'areas'] as $capacityMode) {
            yield 'event-wide ceiling/' . $capacityMode => [array_replace($base, ['capacity_mode' => $capacityMode, 'booked' => 91]), [], true, 'limited'];
        }
    }

    /**
     * Assert the resolved label without mutating event data or settings.
     * @param array $data Event facts. @param array $config Settings. @param bool $open Window state. @param string $expected Label.
     * @return void
     */
    #[DataProvider('scenarios')]
    public function testResolution(array $data, array $config, bool $open, string $expected): void
    {
        $event = (object) $data;
        $settings = (object) $config;
        self::assertSame($expected, JemTicketAvailability::resolve($event, $settings, $open));
        self::assertSame($data, (array) $event);
        self::assertSame($config, (array) $settings);
    }

    /** @return iterable<string, array{array, array}> Stored defaults and malformed setting boundaries. */
    public static function rules(): iterable
    {
        yield 'default' => [[], ['mode' => 'places', 'threshold' => 10]];
        yield 'legacy threshold preserved' => [['module_status_last_places_threshold' => 8], ['mode' => 'places', 'threshold' => 8]];
        yield 'invalid mode' => [['module_status_last_places_mode' => 'invalid'], ['mode' => 'places', 'threshold' => 10]];
        yield 'percentage' => [['module_status_last_places_mode' => 'percent'], ['mode' => 'percent', 'threshold' => 10]];
        yield 'percentage capped' => [['module_status_last_places_mode' => 'percent', 'module_status_last_places_threshold' => 101], ['mode' => 'percent', 'threshold' => 100]];
        yield 'minimum' => [['module_status_last_places_threshold' => -1], ['mode' => 'places', 'threshold' => 1]];
        yield 'places capped' => [['module_status_last_places_threshold' => 10000], ['mode' => 'places', 'threshold' => 9999]];
    }

    /** @param array $config Submitted settings. @param array $expected Normalized rule. @return void */
    #[DataProvider('rules')]
    public function testRule(array $config, array $expected): void
    {
        self::assertSame($expected, JemTicketAvailability::rule((object) $config));
    }

    /** @return void Cancellation returns the automatic label to Available without persisting a status. */
    public function testAutomaticRecoveryAndManualPersistence(): void
    {
        $event = (object) ['ticket_availability' => 'instock', 'maxplaces' => 100, 'booked' => 91];
        self::assertSame('limited', JemTicketAvailability::resolve($event, (object) [], true));
        $event->booked = 88;
        self::assertSame('instock', JemTicketAvailability::resolve($event, (object) [], true));
        $event->ticket_availability = 'limited';
        self::assertSame('limited', JemTicketAvailability::resolve($event, (object) [], true));
    }
}
