<?php

declare(strict_types=1);

namespace JemProject\Tests\JubileeDateMatch;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Exercise Jubilee's production SQL predicate against synthetic dates in MySQL/MariaDB. */
final class JubileeDateMatchTest extends TestCase
{
    private static PDO $db;
    private static string $dateMatchCode;

    /** @return void Read the predicate and connect locally; no tables or installed data are changed. */
    public static function setUpBeforeClass(): void
    {
        $configuration = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\') . '/configuration.php';
        if (!is_file($configuration) || !extension_loaded('pdo_mysql')) {
            self::markTestSkipped('Requires local JEM_TEST_JOOMLA_ROOT and pdo_mysql.');
        }
        require_once $configuration;
        $config = new \JConfig();
        self::assertContains($config->host, ['localhost', '127.0.0.1']);
        self::$db = new PDO(
            'mysql:host=' . $config->host . ';dbname=' . $config->db . ';charset=utf8mb4',
            $config->user,
            $config->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $source = file_get_contents(JEM_TEST_ROOT . '/modules/mod_jem_jubilee/helper.php');
        $start = strpos($source, '        switch ($date_match_mode)');
        self::assertIsInt($start);
        $end = strpos($source, "        \$model->setState('filter.opendates'", $start);
        self::assertIsInt($end);
        self::$dateMatchCode = substr($source, $start, $end - $start);
    }

    /** @return iterable<string,array{string,?string}> Intervals covering all date-match modes and year boundaries. */
    public static function intervals(): iterable
    {
        yield 'reported intervening year' => ['2025-11-20', '2027-11-10'];
        yield 'short endpoint months with whole year between' => ['2025-12-20', '2027-01-10'];
        yield 'same year' => ['2026-10-01', '2026-11-20'];
        yield 'December only' => ['2026-12-01', '2026-12-31'];
        yield 'short December January wrap' => ['2025-12-20', '2026-01-10'];
        yield 'one full anniversary' => ['2025-11-20', '2026-11-20'];
        yield 'missing end' => ['2026-11-14', null];
        yield 'same start and end' => ['2026-11-14', '2026-11-14'];
        yield 'intervening leap year' => ['2023-11-20', '2025-11-10'];
        yield 'leap day through March' => ['2024-02-29', '2024-03-01'];
    }

    /**
     * Compare the predicate with the month/day set independently enumerated from the interval.
     *
     * @param string $from Event start date.
     * @param string|null $until Optional end date, falling back to the start.
     * @return void
     */
    #[DataProvider('intervals')]
    public function testDateMatchModes(string $from, ?string $until): void
    {
        $start = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($until ?? $from);
        $days = [];
        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
            $days[$day->format('md')] = true;
        }
        foreach (['0101', '0110', '0228', '0301', '0615', '1031', '1110', '1114', '1120', '1220', '1231'] as $monthDay) {
            foreach ([0, 1, 2, 3] as $mode) {
                $expected = match ($mode) {
                    0 => isset($days[$monthDay]),
                    1 => $start->format('md') === $monthDay,
                    2 => $end->format('md') === $monthDay,
                    3 => $start->format('md') === $monthDay || $end->format('md') === $monthDay,
                };
                self::assertSame($expected, self::eventMatches($from, $until, $monthDay, $mode), "$monthDay, mode $mode");
            }
        }
    }

    /** @return void A February 29 anniversary must not shift to February 28 or March 1. */
    public function testLeapDayOnly(): void
    {
        foreach (['0228', '0229', '0301'] as $monthDay) {
            foreach ([0, 1, 2, 3] as $mode) {
                self::assertSame($monthDay === '0229', self::eventMatches('2024-02-29', null, $monthDay, $mode));
            }
        }
    }

    /**
     * Execute the unmodified production switch and its SQL using a synthetic derived row.
     *
     * @param string $from Start date.
     * @param string|null $until End date.
     * @param string $cur_md Target month/day, independent of the current year.
     * @param int $date_match_mode Module's selected matching mode.
     * @return bool Whether the event matches.
     */
    private static function eventMatches(string $from, ?string $until, string $cur_md, int $date_match_mode): bool
    {
        eval(self::$dateMatchCode);
        $sql = 'SELECT (' . $cal_from . ') FROM (SELECT CAST(' . self::$db->quote($from)
            . ' AS DATE) AS dates, CAST(' . ($until === null ? 'NULL' : self::$db->quote($until))
            . ' AS DATE) AS enddates) a';
        return (bool) self::$db->query($sql)->fetchColumn();
    }
}
