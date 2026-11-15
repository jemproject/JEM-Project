<?php
declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class StatisticsEventFilterJoomlaIntegrationTest extends JoomlaTestCase
{
    /** @return void Test real SQL parity for date boundaries, status, hierarchy and combined filters. */
    public function testEventCardUsesOneFilteredCohort(): void
    {
        self::bootJoomlaSite();
        require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
        require_once JEM_TEST_ROOT . '/admin/models/statistics.php';
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        foreach (['jem_events', 'jem_cats_event_relations'] as $table) {
            $engine = $db->setQuery('SHOW TABLE STATUS WHERE Name=' . $db->quote($db->getPrefix() . $table))->loadObject();
            self::assertSame('InnoDB', $engine->Engine);
        }
        $author = 1 + (int) $db->setQuery('SELECT MAX(created_by) FROM #__jem_events')->loadResult();
        $category = 1 + (int) $db->setQuery('SELECT MAX(catid) FROM #__jem_cats_event_relations')->loadResult();
        $db->transactionStart();
        try {
            $insert = static function ($created, $state, $parent, $venue, $type, $cats) use ($db, $author): int {
                $row = (object) ['title' => 'QA filtered statistics', 'alias' => 'qa-statistics-' . bin2hex(random_bytes(8)),
                    'created' => $created, 'dates' => '2026-11-15', 'parent_event_id' => $parent,
                    'published' => $state, 'created_by' => $author, 'locid' => $venue, 'type_id' => $type,
                    'introtext' => '', 'fulltext' => '', 'created_by_alias' => '', 'metadata' => ''];
                $db->insertObject('#__jem_events', $row, 'id');
                foreach ($cats as $cat) {
                    $relation = (object) ['itemid' => $row->id, 'catid' => $cat];
                    $db->insertObject('#__jem_cats_event_relations', $relation);
                }
                return (int) $row->id;
            };
            $root = $insert('2026-11-01 00:00:00', 1, 0, 11, 21, [$category]);
            $insert('2026-11-15 23:59:59', 0, null, 12, 22, [$category + 1]);
            $insert('2026-11-10 12:00:00', 2, $root, 11, 21, [$category, $category + 1]);
            $insert('2026-11-12 12:00:00', -2, $root, 11, 22, [$category]);
            $insert('2026-10-31 23:59:59', 1, 0, 11, 21, [$category]);
            $insert('2026-11-16 00:00:00', 1, 0, 11, 21, [$category]);
            $base = (object) ['metric'=>'events', 'period'=>'custom', 'group'=>'day',
                'date_from'=>'2026-11-01','date_to'=>'2026-11-15', 'from_sql'=>'2026-11-01 00:00:00',
                'to_exclusive_sql'=>'2026-11-16 00:00:00', 'state'=>'','subtype'=>'', 'venue_id'=>0,
                'category_id'=>0,'type_id'=>0,'parent_event_id'=>0,'author_id'=>$author];
            $model = new JemModelStatistics();
            $permissions = ['events'=>true,'venues'=>false,'registrations'=>false];
            $cases = [[[],4], [['venue_id'=>11],3], [['type_id'=>21],2], [['category_id'=>$category + 1],2],
                [['parent_event_id'=>$root],3], [['subtype'=>'parent'],2], [['subtype'=>'child'],2],
                [['state'=>'1'],1], [['state'=>'0'],1], [['state'=>'2'],1], [['state'=>'-2'],1],
                [['venue_id'=>11,'category_id'=>$category + 1,'type_id'=>21,'parent_event_id'=>$root,'state'=>'2'],1],
                [['venue_id'=>9999999],0]];
            foreach ($cases as [$changes,$expected]) {
                $filters = (object) array_replace((array) $base, $changes);
                $card = $model->getDashboardData($filters,$permissions)->cards['events'];
                self::assertSame($expected,$card->period_total,json_encode($changes));
                self::assertSame($expected,$card->all_total,json_encode($changes));
                self::assertSame($expected,$card->summary->total);
                self::assertSame($expected,$card->summary->published+$card->summary->unpublished+$card->summary->archived+$card->summary->trashed);
            }
        } finally {
            $db->transactionRollback();
        }
    }

    /** @return void All presets use Joomla's timezone and calendar periods without invalid dates. */
    public function testAddedPeriodPresetsAndFallback(): void
    {
        self::bootJoomlaSite();
        require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
        require_once JEM_TEST_ROOT . '/admin/models/statistics.php';
        $input = Factory::getApplication()->input;
        $today = new DateTimeImmutable('today', new DateTimeZone(JemHelper::getJoomlaTimeZoneName()));
        $model = new JemModelStatistics();
        foreach (['7d'=>7,'14d'=>14,'30d'=>30,'90d'=>90] as $period=>$days) {
            $input->set('period',$period); $input->set('group','');
            $filters=$model->getFilters();
            self::assertSame($today->modify('-'.($days-1).' days')->format('Y-m-d'),$filters->date_from);
            self::assertSame($today->format('Y-m-d'),$filters->date_to);
            self::assertSame($today->modify('+1 day')->format('Y-m-d 00:00:00'),$filters->to_exclusive_sql);
        }
        foreach (['6m'=>6,'12m'=>12,'2y'=>24,'5y'=>60] as $period=>$months) {
            $input->set('period',$period); $input->set('group','');
            $filters=$model->getFilters();
            self::assertSame($today->modify('first day of this month')->modify('-'.($months-1).' months')->format('Y-m-d'),$filters->date_from);
            self::assertSame('month',$filters->group);
        }
        $input->set('period','unknown');
        self::assertSame('12m',$model->getFilters()->period);
    }
}
