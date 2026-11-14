<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class ProgrammeStatisticsJoomlaIntegrationTest extends JoomlaTestCase
{
    private DatabaseInterface $db;
    private int $authorId;

    /**
     * Exercise both supported root representations and optional commerce totals.
     *
     * @return array<string, array{?int, bool}>
     */
    public static function programmeModes(): array
    {
        return [
            'null root with revenue' => [null, true],
            'zero root with revenue' => [0, true],
            'null root without revenue' => [null, false],
            'zero root without revenue' => [0, false],
        ];
    }

    /**
     * Verify direct-child membership, filters and monetary totals on native SQL.
     * All fixtures are rolled back, including when an assertion fails.
     *
     * @param ?int $rootParent Root parent representation.
     * @param bool $commerce Whether to include booking value by currency.
     * @return void
     */
    #[DataProvider('programmeModes')]
    public function testProgrammeSummaryPreservesMembershipAndFilters(?int $rootParent, bool $commerce): void
    {
        self::bootJoomlaSite();
        require_once JEM_TEST_ROOT . '/admin/models/statistics.php';
        $this->db = Factory::getContainer()->get(DatabaseInterface::class);
        foreach (['jem_events', 'jem_register', 'jem_cats_event_relations'] as $table) {
            $status = $this->db->setQuery('SHOW TABLE STATUS WHERE Name = '
                . $this->db->quote($this->db->getPrefix() . $table))->loadObject();
            self::assertSame('InnoDB', $status->Engine, 'Fixture tables must support rollback.');
        }
        $this->authorId = 1 + (int) $this->db->setQuery('SELECT MAX(created_by) FROM #__jem_events')->loadResult();
        $categoryA = 1 + (int) $this->db->setQuery('SELECT MAX(catid) FROM #__jem_cats_event_relations')->loadResult();
        $categoryB = $categoryA + 1;
        $this->db->transactionStart();
        try {
            $root = $this->event('Programme', $rootParent, 1, [$categoryA]);
            $first = $this->event('First session', $root, 1, [$categoryB], 11, 21);
            $second = $this->event('Archived session', $root, 2, [$categoryA, $categoryB], 12, 22);
            $trashed = $this->event('Trashed session', $root, -2, [$categoryA]);
            $grandchild = $this->event('Nested session', $first, 1, [$categoryB]);
            $empty = $this->event('Empty programme', $rootParent, 0, []);
            $this->event('Empty session', $empty, 1, []);
            $trashRoot = $this->event('Trashed programme', $rootParent, -2, []);
            $this->event('Live child of trashed programme', $trashRoot, 1, []);
            $deadEnd = $this->event('Only trashed children', $rootParent, 1, []);
            $this->event('Only child in trash', $deadEnd, -2, []);
            $self = $this->event('Self parent', $rootParent, 1, []);
            $this->db->setQuery('UPDATE #__jem_events SET parent_event_id = ' . $self . ' WHERE id = ' . $self)->execute();

            $this->booking($root, 2, 1, 0, 'EUR', '20.00');
            $this->booking($first, 3, 1, 0, 'EUR', '30.00');
            $this->booking($first, 4, 1, 1, 'EUR', '40.00');
            $this->booking($first, 5, -1, 0, 'USD', '50.00');
            $this->booking($second, 1, 1, 0, 'USD', '12.50');
            $this->booking($second, 2, 1, 0, 'EUR', '7.00', 'classic');
            $this->booking($trashed, 1, 1, 0, 'EUR', '5.00');
            foreach ([$grandchild, $trashRoot, $deadEnd, $self] as $excluded) {
                $this->booking($excluded, 100, 1, 0, 'EUR', '1000.00');
            }
            $model = new JemModelStatistics();
            $base = ['venue_id' => 0, 'category_id' => 0, 'type_id' => 0,
                'parent_event_id' => 0, 'author_id' => $this->authorId];
            $summary = static fn(array $counts, array $money): array => array_merge($counts, [$commerce ? $money : []]);
            // Explicit expected values are independent of the SQL join implementation.
            $all = $summary([3, 5, 9, 1, 4], ['EUR' => '55.00', 'USD' => '12.50']);
            $firstOnly = $summary([1, 1, 3, 1, 4], ['EUR' => '30.00']);
            $cases = [
                'all' => [[], [$empty => $summary([1, 0, 0, 0, 0], []), $root => $all]],
                'category A' => [['category_id' => $categoryA], [$root => $summary([2, 4, 6, 0, 0], ['EUR' => '25.00', 'USD' => '12.50'])]],
                'category B' => [['category_id' => $categoryB], [$root => $summary([2, 3, 6, 1, 4], ['EUR' => '30.00', 'USD' => '12.50'])]],
                'venue' => [['venue_id' => 11], [$root => $firstOnly]],
                'type' => [['type_id' => 22], [$root => $summary([1, 2, 3, 0, 0], ['USD' => '12.50'])]],
                'root programme' => [['parent_event_id' => $root], [$root => $all]],
                'nested programme filter' => [['parent_event_id' => $first], [$root => $firstOnly]],
                'combined filters' => [['category_id' => $categoryB, 'venue_id' => 11, 'type_id' => 21, 'parent_event_id' => $root], [$root => $firstOnly]],
                'no matching members' => [['venue_id' => 11, 'type_id' => 22], []],
                'no matching category' => [['category_id' => $categoryB + 1], []],
            ];
            foreach ($cases as $label => [$overrides, $expected]) {
                $rows = $model->getProgrammeSummary((object) array_replace($base, $overrides), $commerce);
                $actual = [];
                foreach ($rows as $row) {
                    $money = [];
                    foreach ($row->revenue as $value) {
                        self::assertSame((int) $row->id, (int) $value->programme_id, $label);
                        $money[$value->currency] = $value->total;
                    }
                    $actual[(int) $row->id] = [$row->child_events, $row->confirmed_orders, $row->confirmed_places,
                        $row->waiting_orders, $row->waiting_places, $money];
                }
                self::assertSame($expected, $actual, $label);
            }
        } finally {
            $this->db->transactionRollback();
        }
    }

    /**
     * Insert an isolated event and its category relations without invoking save hooks.
     *
     * @param string $title Fixture title.
     * @param ?int $parent Direct parent, zero or null for roots.
     * @param int $published Publication state.
     * @param int[] $categories Category relation identifiers.
     * @param int $venue Venue filter value.
     * @param int $type Type filter value.
     * @return int Inserted event identifier.
     */
    private function event(string $title, ?int $parent, int $published, array $categories, int $venue = 0, int $type = 0): int
    {
        $row = (object) ['title' => $title, 'alias' => 'programme-query-test-' . bin2hex(random_bytes(6)),
            'parent_event_id' => $parent, 'published' => $published, 'created_by' => $this->authorId,
            'dates' => '2026-11-14', 'locid' => $venue, 'type_id' => $type,
            'introtext' => '', 'fulltext' => '', 'created_by_alias' => '', 'metadata' => ''];
        $this->db->insertObject('#__jem_events', $row, 'id');
        foreach ($categories as $category) {
            $relation = (object) ['itemid' => $row->id, 'catid' => $category];
            $this->db->insertObject('#__jem_cats_event_relations', $relation);
        }

        return (int) $row->id;
    }

    /**
     * Insert a synthetic booking; no user, notification or payment operation runs.
     *
     * @param int $event Event identifier.
     * @param int $places Booked places.
     * @param int $status Registration status.
     * @param int $waiting Waiting-list flag.
     * @param string $currency Immutable booking currency.
     * @param string $total Immutable booking value.
     * @param string $pricing Pricing mode.
     * @return void
     */
    private function booking(int $event, int $places, int $status, int $waiting, string $currency, string $total, string $pricing = 'ticketing'): void
    {
        $row = (object) ['event' => $event, 'uid' => null, 'reference' => bin2hex(random_bytes(14)),
            'places' => $places, 'status' => $status, 'waiting' => $waiting,
            'currency' => $currency, 'grand_total' => $total, 'pricing_mode' => $pricing];
        $this->db->insertObject('#__jem_register', $row, 'id');
    }
}
