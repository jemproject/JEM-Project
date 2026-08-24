<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/eventslistmenupolicy.class.php';

final class EventslistMenuPolicyTest extends TestCase
{
    #[DataProvider('dayLimitProvider')]
    public function testDayLimitsPreserveOnlySupportedStates($input, $expected): void
    {
        self::assertSame($expected, JemEventslistMenuPolicy::normaliseDayLimit($input));
    }

    /**
     * @return array<string, array{mixed, int|string}>
     */
    public static function dayLimitProvider(): array
    {
        return array(
            'null is unrestricted' => array(null, ''),
            'empty is unrestricted' => array('', ''),
            'legacy all is unrestricted' => array('all', ''),
            'zero means today' => array('0', 0),
            'integer zero means today' => array(0, 0),
            'positive offset' => array('30', 30),
            'whitespace is trimmed' => array(' 60 ', 60),
            'negative value is rejected' => array('-30', ''),
            'explicit plus sign is rejected' => array('+30', ''),
            'decimal is rejected' => array('1.5', ''),
            'mixed value is rejected' => array('30 days', ''),
            'array is rejected' => array(array('30'), ''),
        );
    }

    #[DataProvider('dateWindowProvider')]
    public function testAllSupportedDateWindowScenarios(
        string $from,
        string $until,
        ?string $expectedFrom,
        ?string $expectedUntil
    ): void {
        $window = JemEventslistMenuPolicy::dateWindow(
            $from,
            $until,
            new DateTimeImmutable('2026-08-24')
        );

        self::assertSame($expectedFrom, $window['from_date']);
        self::assertSame($expectedUntil, $window['until_date']);
    }

    /**
     * The nine public scenarios documented for the Events List menu.
     *
     * @return array<string, array{string, string, ?string, ?string}>
     */
    public static function dateWindowProvider(): array
    {
        return array(
            '1 all history and future' => array('', '', null, null),
            '2 all history through today' => array('', '0', null, '2026-08-24'),
            '3 all history through a future offset' => array('', '30', null, '2026-09-23'),
            '4 today onwards' => array('0', '', '2026-08-24', null),
            '5 today only' => array('0', '0', '2026-08-24', '2026-08-24'),
            '6 today through a future offset' => array('0', '30', '2026-08-24', '2026-09-23'),
            '7 bounded past and future offsets' => array('60', '30', '2026-06-25', '2026-09-23'),
            '8 past offset through today' => array('60', '0', '2026-06-25', '2026-08-24'),
            '9 symmetric date window' => array('30', '30', '2026-07-25', '2026-09-23'),
        );
    }

    public function testMenuOrderingMapsZeroWithoutTreatingItAsEmpty(): void
    {
        self::assertSame('a.dates', JemEventslistMenuPolicy::orderField('0'));
        self::assertSame('a.title', JemEventslistMenuPolicy::orderField('1'));
        self::assertSame('c.catname', JemEventslistMenuPolicy::orderField('5'));
        self::assertSame('a.dates', JemEventslistMenuPolicy::orderField('invalid'));
    }

    public function testOrderDirectionIsRestrictedToAllowedValues(): void
    {
        self::assertSame('DESC', JemEventslistMenuPolicy::orderDirection('desc'));
        self::assertSame('ASC', JemEventslistMenuPolicy::orderDirection('invalid'));
        self::assertSame('DESC', JemEventslistMenuPolicy::orderDirection('invalid', 'DESC'));
    }

    public function testDateOrderingDoesNotRepeatThePrimaryField(): void
    {
        self::assertSame(
            array('a.dates DESC', 'a.times DESC', 'a.created DESC'),
            JemEventslistMenuPolicy::buildOrderBy('a.dates', 'DESC')
        );
    }

    public function testNonDateOrderingUsesChronologicalTieBreakers(): void
    {
        self::assertSame(
            array('a.title DESC', 'a.dates ASC', 'a.times ASC', 'a.created ASC'),
            JemEventslistMenuPolicy::buildOrderBy('a.title', 'DESC')
        );
        self::assertSame(
            array('a.title ASC', 'a.dates DESC', 'a.times DESC', 'a.created DESC'),
            JemEventslistMenuPolicy::buildOrderBy('a.title', 'ASC', true)
        );
    }

    public function testOrderContextChangesWhenMenuDefaultsOrArchiveModeChange(): void
    {
        $base = JemEventslistMenuPolicy::orderContext('0:42', 'a.dates', 'ASC');

        self::assertSame($base, JemEventslistMenuPolicy::orderContext('0:42', 'a.dates', 'ASC'));
        self::assertNotSame($base, JemEventslistMenuPolicy::orderContext('0:42', 'a.title', 'ASC'));
        self::assertNotSame($base, JemEventslistMenuPolicy::orderContext('0:42', 'a.dates', 'DESC'));
        self::assertNotSame($base, JemEventslistMenuPolicy::orderContext('0:42', 'a.dates', 'ASC', true));
    }
}
