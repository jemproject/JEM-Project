<?php
declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

#[RunClassInSeparateProcess]
#[PreserveGlobalState(false)]
final class AreaQuantityJoomlaIntegrationTest extends JoomlaTestCase
{
    /** Verify backend rejection and real availability caps without retaining test data. */
    public function testBackendLimitsAndWaitingAvailability(): void
    {
        if (getenv('JEM_TEST_WRITABLE') !== '1') {
            self::markTestSkipped('Requires an authorised disposable Joomla site.');
        }
        self::bootJoomlaSite();
        foreach (['registrationidentity', 'registrationtransition', 'registrationservice'] as $name) {
            require_once JEM_TEST_ROOT . '/site/classes/' . $name . '.class.php';
        }
        require_once JEM_TEST_ROOT . '/admin/models/attendee.php';
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $uid = (int) $db->setQuery("SELECT MIN(id) FROM #__users WHERE block=0 AND (activation='' OR activation='0')")->loadResult();
        $service = new JemRegistrationService($db);
        $snapshot = ['schema' => 'jem-venue-capacity/v1', 'spaces' => [[
            'name' => 'Hall', 'layout' => ['id' => 82471, 'revision' => 1, 'capacity' => 2],
            'capacity_areas' => [
                ['id' => 82471, 'name' => 'Floor', 'code' => 'floor', 'published' => 1, 'capacity' => 1],
                ['id' => 82472, 'name' => 'Balcony', 'code' => 'balcony', 'published' => 1, 'capacity' => 1],
            ],
        ]]];
        $event = (object) ['title' => 'PHPUnit QA2471 ' . bin2hex(random_bytes(5)),
            'alias' => 'qa2471-' . bin2hex(random_bytes(5)), 'published' => 0,
            'introtext' => '', 'fulltext' => '', 'metadata' => '{}', 'attribs' => '{}', 'created_by_alias' => '',
            'created' => gmdate('Y-m-d H:i:s'), 'capacity_mode' => 'areas',
            'venue_snapshot' => json_encode($snapshot), 'maxplaces' => 2, 'waitinglist' => 1,
            'minbookeduser' => 2, 'maxbookeduser' => 4];
        $db->insertObject('#__jem_events', $event, 'id');
        try {
            foreach ([[[1, 0], 'BELOW_MINIMUM'], [[3, 2], 'ABOVE_MAXIMUM'], [['abc', 2], 'INVALID_QUANTITY']] as [$quantities, $reason]) {
                $model = new JemModelAttendee();
                self::assertFalse($model->store(['event' => $event->id, 'uid' => $uid, 'status' => 1, 'capacity_areas' => $quantities]));
                self::assertStringContainsString('COM_JEM_CAPACITY_REGISTRATION_' . $reason, $model->getError());
                self::assertSame(0, (int) $db->setQuery('SELECT COUNT(*) FROM #__jem_register WHERE event=' . $event->id)->loadResult());
            }
            $saved = $service->save((object) ['event' => $event->id, 'uid' => $uid, 'places' => 2, 'status' => 1], [
                'actorId' => $uid, 'source' => 'phpunit.2471', 'capacityAllocations' => ['area:82471' => 1, 'area:82472' => 1],
            ]);
            $waiting = $service->capacityOptions($event->id);
            self::assertSame(2, $waiting->minimum);
            self::assertSame(4, $waiting->maximum);
            self::assertSame([0, 0], array_column($waiting->options, 'remaining'));
            self::assertSame([1, 1], array_column($waiting->options, 'input_max'), 'Full areas must still allow a waiting-list request.');
            $db->setQuery('UPDATE #__jem_events SET waitinglist=0 WHERE id=' . $event->id)->execute();
            self::assertSame([0, 0], array_column($service->capacityOptions($event->id)->options, 'input_max'));
            self::assertSame([1, 1], array_column($service->capacityOptions($event->id, $saved->after->id)->options, 'input_max'), 'Editing uses the final quantity, excluding its own reservation from used places.');
        } finally {
            foreach (['#__jem_register_capacity_allocations', '#__jem_register_history'] as $table) {
                $db->setQuery('DELETE FROM ' . $table . ' WHERE event_id=' . (int) $event->id)->execute();
            }
            $db->setQuery('DELETE FROM #__jem_register WHERE event=' . (int) $event->id)->execute();
            $db->setQuery('DELETE FROM #__jem_events WHERE id=' . (int) $event->id)->execute();
        }
    }
}
