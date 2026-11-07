<?php
declare(strict_types=1);
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class AreaCancellationJoomlaIntegrationTest extends JoomlaTestCase
{
    public function testAreaSeriesRevisionsAndAtomicRollback(): void
    {
        if (getenv('JEM_TEST_WRITABLE') !== '1') self::markTestSkipped('Requires an authorised disposable Joomla site.');
        self::bootJoomlaSite();
        foreach (['registrationidentity', 'registrationtransition', 'registrationservice', 'registrationcancellation'] as $name) {
            require_once JEM_TEST_ROOT . '/site/classes/' . $name . '.class.php';
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $userId = (int) $db->setQuery("SELECT MIN(id) FROM #__users WHERE block=0 AND (activation='' OR activation='0')")->loadResult();
        $service = new JemRegistrationService($db);
        $events = $registrations = [];
        $snapshot = ['schema' => 'jem-venue-capacity/v1', 'spaces' => [[
            'name' => 'Test hall', 'layout' => ['id' => 82470, 'revision' => 1, 'capacity' => 40],
            'capacity_areas' => [
                ['id' => 82471, 'name' => 'Floor', 'code' => 'floor', 'published' => 1, 'capacity' => 20],
                ['id' => 82472, 'name' => 'Balcony', 'code' => 'balcony', 'published' => 1, 'capacity' => 20],
            ],
        ]]];
        try {
            foreach ([2, 3] as $perArea) {
                $event = (object) ['title' => 'PHPUnit QA2470 ' . bin2hex(random_bytes(5)),
                    'alias' => 'qa2470-' . bin2hex(random_bytes(5)), 'published' => 0,
                    'introtext' => '', 'fulltext' => '', 'metadata' => '{}', 'attribs' => '{}', 'created_by_alias' => '',
                    'created' => gmdate('Y-m-d H:i:s'), 'capacity_mode' => 'areas',
                    'venue_snapshot' => json_encode($snapshot), 'maxplaces' => 40, 'waitinglist' => 1,
                    'minbookeduser' => 1, 'maxbookeduser' => 10];
                $db->insertObject('#__jem_events', $event, 'id');
                $events[(int) $event->id] = $event;
                $stored = $service->save((object) ['event' => $event->id, 'uid' => $userId, 'places' => $perArea * 2, 'status' => 1], [
                    'source' => 'phpunit.2470', 'actorId' => $userId,
                    'capacityAllocations' => ['area:82471' => $perArea, 'area:82472' => $perArea],
                ]);
                $registrations[(int) $event->id] = $stored->after;
            }
            $rows = $allocations = $expected = [];
            foreach ($registrations as $id => $registration) {
                $current = $service->capacityOptions($id, $registration->id)->current;
                $plan = JemRegistrationCancellation::reduce($events[$id], $registration, 2, ['area:82471' => 1, 'area:82472' => 1], $current);
                $rows[] = (object) ['id' => $registration->id, 'event' => $id, 'uid' => $userId, 'places' => $plan['places'], 'status' => 1];
                $allocations[$id] = $plan['allocations'];
                $expected[$id] = (int) $registration->revision;
            }
            $results = $service->saveMany($rows, ['actorId' => $userId, 'source' => 'phpunit.2470', 'requireExisting' => true,
                'expectedRevisions' => $expected, 'capacityAllocationsByEvent' => $allocations]);
            self::assertSame([2, 4], array_map(static fn($r) => (int) $r->after->places, $results));
            self::assertSame([2, 2], array_map(static fn($r) => (int) $r->after->revision, $results));
            foreach ($results as $result) {
                self::assertSame(1, (int) $result->after->status);
                self::assertSame((int) $result->after->places, array_sum($service->capacityOptions($result->after->event, $result->after->id)->current));
                self::assertSame(2, (int) $db->setQuery('SELECT COUNT(*) FROM #__jem_register_history WHERE registration_id=' . (int) $result->after->id)->loadResult());
                self::assertSame((int) $result->before->places, (int) $db->setQuery('SELECT SUM(quantity) FROM #__jem_register_capacity_allocations WHERE register_id=' . (int) $result->after->id . ' AND registration_revision=1')->loadResult());
            }
            $ids = implode(',', array_keys($events));
            $read = static function () use ($db, $ids) {
                return [
                    $db->setQuery('SELECT * FROM #__jem_register WHERE event IN (' . $ids . ') ORDER BY id')->loadAssocList(),
                    $db->setQuery('SELECT * FROM #__jem_register_history WHERE event_id IN (' . $ids . ') ORDER BY id')->loadAssocList(),
                    $db->setQuery('SELECT * FROM #__jem_register_capacity_allocations WHERE event_id IN (' . $ids . ') ORDER BY id')->loadAssocList(),
                ];
            };
            $before = $read();
            $firstId = array_key_first($events);
            $secondId = array_key_last($events);
            $rows[0]->places = 1;
            $rows[1]->places = 3;
            $allocations[$firstId] = ['area:82471' => 0, 'area:82472' => 1];
            $allocations[$secondId] = ['area:82471' => 1, 'area:82472' => 2];
            try {
                $service->saveMany($rows, ['source' => 'phpunit.2470', 'requireExisting' => true,
                    'expectedRevisions' => [$firstId => 2, $secondId => 1], 'capacityAllocationsByEvent' => $allocations]);
                self::fail('Stale later occurrence must roll back the first write.');
            } catch (RuntimeException $e) {
                self::assertStringContainsString('modified by another operation', $e->getMessage());
            }
            self::assertSame($before, $read(), 'Registrations, allocations and history must all roll back.');
            try {
                $service->saveMany($rows, ['source' => 'phpunit.2470', 'requireExisting' => true,
                    'expectedRevisions' => [$firstId => 2, $secondId => 2], 'capacityAllocationsByEvent' => [$firstId => $allocations[$firstId]]]);
                self::fail('Missing per-event allocations must abort the batch.');
            } catch (RuntimeException $e) {
                self::assertStringContainsString('Missing event capacity allocation', $e->getMessage());
            }
            self::assertSame($before, $read());
        } finally {
            if ($events) {
                $ids = implode(',', array_keys($events));
                foreach (['#__jem_register_capacity_allocations', '#__jem_register_history'] as $table) {
                    $db->setQuery('DELETE FROM ' . $table . ' WHERE event_id IN (' . $ids . ')')->execute();
                }
                $db->setQuery('DELETE FROM #__jem_register WHERE event IN (' . $ids . ')')->execute();
                $db->setQuery('DELETE FROM #__jem_events WHERE id IN (' . $ids . ')')->execute();
            }
        }
    }
}
