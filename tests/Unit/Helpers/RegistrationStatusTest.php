<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/registrationstatus.class.php';

final class RegistrationStatusTest extends TestCase
{
    private function filters(array $request = [], array $options = []): array
    {
        return JemRegistrationStatus::filters($options, $request, new DateTimeImmutable('2026-11-10 00:30', new DateTimeZone('Europe/Madrid')), 1);
    }

    private function event(array $data = []): object
    {
        return (object) ($data + ['id' => 1, 'title' => 'Event', 'published' => 1, 'event_status' => 'scheduled',
            'locid' => 1, 'parent_event_id' => 0, 'dates' => '2026-11-10', 'enddates' => null,
            'country' => 'ES', 'region' => 'Madrid', 'state' => 'Madrid', 'city' => 'Madrid', 'categories' => [2],
            'maxplaces' => 50, 'reservedplaces' => 0]);
    }

    public function testHiddenFiltersIgnoreForgedInput(): void
    {
        $filters = $this->filters(['country' => 'DE', 'publication' => 'all', 'mode' => 'all', 'step' => '1'],
            ['show_country_filter' => 0, 'default_country' => 'ES', 'show_publication_filter' => 0,
                'default_publication' => '2', 'show_date_filter' => 0, 'date_filter_default' => 'today']);
        self::assertSame('ES', $filters['country']);
        self::assertSame('2', $filters['publication']);
        self::assertSame('2026-11-10', $filters['start']);
        self::assertFalse(JemRegistrationStatus::matches($this->event(), $filters));
    }

    public function testDefaultAndMapDateNavigation(): void
    {
        self::assertSame('upcoming', $this->filters()['mode']);
        self::assertSame('', $this->filters([], ['default_country' => '0'])['country']);
        self::assertSame('2026-11-11', $this->filters([], ['date_filter_default' => 'tomorrow'])['date']);
        $next = $this->filters(['mode' => 'today', 'step' => '1']);
        self::assertSame('date', $next['mode']);
        self::assertSame('2026-11-11', $next['date']);
        self::assertSame('2026-11-16', $this->filters(['mode' => 'week', 'step' => '1'])['date']);
        self::assertSame('2026-11-17', $this->filters(['mode' => 'date', 'jump' => '1'])['date']);
        self::assertSame('2027-11-01', $this->filters(['mode' => 'month', 'jump' => '1'])['date']);
        self::assertSame(0, $this->filters(['category' => '2xyz'])['category']);
    }

    public function testOngoingAndUndatedEvents(): void
    {
        self::assertTrue(JemRegistrationStatus::matches($this->event(['dates' => '2026-11-01', 'enddates' => '2026-11-12']), $this->filters()));
        self::assertFalse(JemRegistrationStatus::matches($this->event(['dates' => '2026-11-09']), $this->filters()));
        self::assertFalse(JemRegistrationStatus::matches($this->event(['dates' => null]), $this->filters()));
        self::assertTrue(JemRegistrationStatus::matches($this->event(['dates' => null]), $this->filters(['mode' => 'all'])));
        self::assertTrue(JemRegistrationStatus::matches($this->event(['dates' => '1970-01-01']), $this->filters(['mode' => 'date', 'date' => '1970-01-01'])));
    }

    public function testIndependentFiltersCannotBeBypassed(): void
    {
        foreach (['category' => 3, 'country' => 'DE', 'county' => 'Other', 'city' => 'Other', 'region' => 'Other', 'venue' => 2, 'event_status' => 'cancelled', 'search' => 'missing', 'publication' => '2'] as $name => $value) {
            self::assertFalse(JemRegistrationStatus::matches($this->event(), $this->filters([$name => $value])), $name);
        }
        self::assertTrue(JemRegistrationStatus::matches($this->event(['published' => 2, 'event_status' => 'cancelled']), $this->filters(['publication' => 'all'])));
    }

    public function testGroupingKeepsOnlyPermittedContextAndMatchingChildren(): void
    {
        $events = [1 => $this->event(['dates' => '2026-01-01']),
            2 => $this->event(['id' => 2, 'parent_event_id' => 1]),
            3 => $this->event(['id' => 3, 'parent_event_id' => 1, 'dates' => '2026-01-01'])];
        self::assertSame([1 => [2 => true, 1 => false]], JemRegistrationStatus::groups($events, $this->filters()));
        unset($events[1]);
        self::assertSame([2 => [2 => true]], JemRegistrationStatus::groups($events, $this->filters()));
    }

    public function testCyclicLegacyHierarchyDoesNotDuplicateGroups(): void
    {
        $events = [1 => $this->event(['parent_event_id' => 2]), 2 => $this->event(['id' => 2, 'parent_event_id' => 1])];
        self::assertSame([1 => [1 => true, 2 => true]], JemRegistrationStatus::groups($events, $this->filters()));
    }

    public static function cancellations(): iterable
    {
        yield 'full with preserved historical quantity' => [1, -1, 4, 4, 'cancelled', 4];
        yield 'classic full' => [1, -1, 4, 0, 'cancelled', 4];
        yield 'partial' => [1, 1, 4, 2, 'places_changed', 2];
        yield 'waiting cancellation' => [2, -1, 3, 0, 'cancelled', 3];
        yield 'negative response' => [null, -1, null, 0, 'created', 0];
        yield 'declined invitation' => [0, -1, 3, 0, 'cancelled', 0];
        yield 'promotion' => [2, 1, 4, 4, 'promoted', 0];
        yield 'reactivation' => [-1, 1, 4, 4, 'status_changed', 0];
        yield 'purge' => [1, null, 4, null, 'purged', 0];
        yield 'increase' => [1, 1, 2, 4, 'places_changed', 0];
    }

    #[DataProvider('cancellations')]
    public function testCancellationMovements($old, $new, $before, $after, string $action, int $expected): void
    {
        self::assertSame($expected, JemRegistrationStatus::cancelled((object) [
            'old_status' => $old, 'new_status' => $new, 'old_places' => $before, 'new_places' => $after, 'action' => $action,
        ]));
    }

    public function testAreaAvailabilitySharesGlobalLimit(): void
    {
        $pools = [['capacity' => 40, 'confirmed' => 25], ['capacity' => 30, 'confirmed' => 15]];
        $totals = JemRegistrationStatus::totals($this->event(['confirmed' => 40, 'reservedplaces' => 2]), $pools);
        self::assertSame(8, $totals['free']);
        self::assertSame([8, 8], array_column($totals['pools'], 'free'));
        self::assertSame(50, $totals['capacity']);
    }

    public function testNoVenueUnlimitedAndFinitePools(): void
    {
        $unlimited = JemRegistrationStatus::totals($this->event(['locid' => 0, 'maxplaces' => 0]), []);
        self::assertNull($unlimited['capacity']);
        self::assertNull($unlimited['free']);
        $limited = JemRegistrationStatus::totals($this->event(['maxplaces' => 0, 'confirmed' => 8]), [['capacity' => 10, 'confirmed' => 8]]);
        self::assertSame(2, $limited['free']);
        self::assertSame(0, JemRegistrationStatus::totals($this->event(), [['capacity' => 0, 'confirmed' => 0]])['free']);
        self::assertTrue(JemRegistrationStatus::totals($this->event(['capacity_mode' => 'areas']), [])['unknown']);
    }
}
