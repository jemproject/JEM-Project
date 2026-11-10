<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Exercise the real frontend model; isolate row retrieval and observe its batch writer. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SeriesSuspendedBookingTest extends TestCase
{
    private CancellationDatabase $db;
    private CancellationValues $input;
    private CancellationEventModel $model;

    /** @return void Prepare nine future occurrences and an authenticated participant. */
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/cancellation_lookup_environment.php';
        $this->db = new CancellationDatabase();
        Factory::$database = $this->db;
        $this->input = new CancellationValues(['reg_check' => 1, 'addplaces' => 1, 'reg_check_series' => '1']);
        Factory::$application = new CancellationApplication($this->input);
        JemFactory::$user = new CancellationValues(['id' => 42, 'guest' => 0, 'block' => 0]);
        foreach (range(10, 18) as $id) {
            $this->db->events[$id] = (object) [
                'id' => $id, 'did' => $id, 'title' => 'Occurrence ' . $id, 'dates' => '2099-12-01',
                'event_status' => 'scheduled', 'published' => 1, 'registra' => 1, 'unregistra' => 1,
                'registration_open' => true, 'params' => new CancellationValues(['access-view' => true]),
                'recurrence_type' => 1, 'recurrence_first_id' => 10, 'series_id' => 10,
                'seriesbooking' => 1, 'singlebooking' => 1, 'maxplaces' => 20, 'waitinglist' => 1,
                'minbookeduser' => 1, 'maxbookeduser' => 5,
            ];
        }
        $this->model = new CancellationEventModel();
        $this->model->setId(10);
        $this->model->series = array_values($this->db->events);
    }

    /** @return iterable<string,array{string,int}> Suspensions with optional or mandatory series booking. */
    public static function suspendedCases(): iterable
    {
        foreach (['cancelled', 'postponed'] as $status) {
            foreach ([0, 1] as $singleBooking) {
                yield $status . '/single=' . $singleBooking => [$status, $singleBooking];
            }
        }
    }

    /**
     * Reserve the eight remaining occurrences, preserving any suspended reservation.
     * @param string $status Suspension state. @param int $singleBooking Individual booking option. @return void
     */
    #[DataProvider('suspendedCases')]
    public function testSeriesSkipsSuspendedOccurrenceAndPreservesItsExistingReservation(string $status, int $singleBooking): void
    {
        $this->db->events[12]->event_status = $status;
        $this->db->events[10]->singlebooking = $singleBooking;
        $this->input->values['reg_check_series'] = (string) $singleBooking;
        $old = (object) ['id' => 91, 'event' => 12, 'uid' => 42, 'places' => 2, 'status' => 1, 'waiting' => 0, 'comment' => 'Keep'];
        $this->db->registrations[12] = clone $old;
        self::assertGreaterThan(0, $this->model->userregister(), $this->model->getError());
        self::assertCount(1, JemRegistrationService::$writes);
        self::assertSame([10, 11, 13, 14, 15, 16, 17, 18], array_column(JemRegistrationService::$writes[0], 'event'));
        self::assertEquals($old, $this->db->registrations[12]);
        self::assertSame(['COM_JEM_SERIES_BOOKING_RESULT8,0,1', 'notice'], $this->summary());
    }

    /** @return void Both suspension states are excluded and waiting dates are counted separately. */
    public function testMixedSuspensionsAndWaitingListSummary(): void
    {
        $this->db->events[12]->event_status = 'cancelled';
        $this->db->events[14]->event_status = 'postponed';
        $this->db->registrations[15] = (object) ['id' => 92, 'uid' => 42, 'event' => 15, 'places' => 1, 'status' => 1, 'waiting' => 1];
        self::assertGreaterThan(0, $this->model->userregister(), $this->model->getError());
        self::assertCount(7, JemRegistrationService::$writes[0]);
        self::assertSame(['COM_JEM_SERIES_BOOKING_RESULT6,1,2', 'notice'], $this->summary());
    }

    /** @param string $status Suspension. @param int $singleBooking Individual option. @return void Reject the starting event even if omitted from the query. */
    #[DataProvider('suspendedCases')]
    public function testOriginatingSuspendedEventCannotBeBypassed(string $status, int $singleBooking): void
    {
        $this->db->events[10]->event_status = $status;
        $this->db->events[10]->singlebooking = $singleBooking;
        $this->model->series = array_slice($this->model->series, 1);
        self::assertFalse($this->model->userregister());
        self::assertStringContainsString('COM_JEM_EVENT_REGISTRATION_EVENT_MESSAGE10,', $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
    }

    /** @return void An empty eligible selection produces a closed-registration error, not success. */
    public function testAllQueriedOccurrencesSuspended(): void
    {
        $this->model->series = array_slice($this->model->series, 1);
        foreach ($this->model->series as $event) {
            $event->event_status = 'cancelled';
        }
        self::assertFalse($this->model->userregister());
        self::assertSame('COM_JEM_EVENT_REGISTRATION_CLOSED', $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
        self::assertSame([], Factory::$application->messages);
    }

    /** @return iterable<string,array{string}> Other eligibility failures must not be silently skipped. */
    public static function blockingCases(): iterable
    {
        foreach (['access', 'publication', 'deadline', 'quantity'] as $case) {
            yield $case => [$case];
        }
    }

    /** @param string $case Failure on an otherwise active occurrence. @return void No writes if a remaining occurrence fails validation. */
    #[DataProvider('blockingCases')]
    public function testOtherFailuresRejectTheWholeRemainingSelection(string $case): void
    {
        $this->db->events[12]->event_status = 'cancelled';
        if ($case === 'access') {
            $this->db->events[13]->params = new CancellationValues(['access-view' => false]);
        } elseif ($case === 'publication') {
            $this->db->events[13]->published = 0;
        } elseif ($case === 'deadline') {
            $this->db->events[13]->registration_open = false;
        } else {
            $this->db->events[13]->minbookeduser = 2;
        }
        self::assertFalse($this->model->userregister());
        self::assertSame([], JemRegistrationService::$writes);
        self::assertNull($this->summary());
    }

    /** @return void A single booking does not expand to or report excluded siblings. */
    public function testIndividualBookingStillUsesOnlyItsOwnEvent(): void
    {
        $this->input->values['reg_check_series'] = '0';
        $this->db->events[12]->event_status = 'cancelled';
        self::assertGreaterThan(0, $this->model->userregister());
        self::assertSame([10], array_column(JemRegistrationService::$writes[0], 'event'));
        self::assertNull($this->summary());
    }

    /** @return void An all-active series preserves the existing complete selection. */
    public function testAllActiveOccurrencesRemainIncluded(): void
    {
        self::assertGreaterThan(0, $this->model->userregister());
        self::assertSame(range(10, 18), array_column(JemRegistrationService::$writes[0], 'event'));
        self::assertNull($this->summary());
    }

    /** @return void Cancelling existing reservations includes suspended occurrences. */
    public function testCancellationDoesNotApplyTheNewBookingExclusion(): void
    {
        $this->input->values = ['reg_check' => -1, 'cancelplaces' => 1, 'reg_check_series' => '1'];
        // The owned-registration lookup returns database columns, not view parameters.
        foreach ($this->db->events as $event) {
            unset($event->params);
        }
        foreach ([10, 12, 14] as $id) {
            $this->db->registrations[$id] = (object) ['id' => $id + 90, 'uid' => 42, 'event' => $id,
                'places' => 1, 'status' => 1, 'waiting' => 0, 'revision' => 1];
        }
        $this->db->events[12]->event_status = 'cancelled';
        $this->db->events[14]->event_status = 'postponed';
        if (method_exists($this->model, 'getCancellationResults')) {
            $preview = $this->model->userregister(true);
            self::assertIsArray($preview, $this->model->getError());
            $this->input->values['jem_cancel_confirm'] = 1;
            $this->input->values['jem_cancel_fingerprint'] = $preview['fingerprint'];
        }
        self::assertGreaterThan(0, $this->model->userregister(), $this->model->getError());
        self::assertSame([10, 12, 14], array_column(JemRegistrationService::$writes[0], 'event'));
        foreach ([10, 12, 14] as $id) {
            self::assertSame(-1, $this->db->registrations[$id]->status);
        }
        self::assertNull($this->summary());
    }

    /** @return array|null Public series summary after a successful batch, if exclusions occurred. */
    private function summary(): ?array
    {
        foreach (Factory::$application->messages as $message) {
            if (str_starts_with($message[0], 'COM_JEM_SERIES_BOOKING_RESULT')) {
                return $message;
            }
        }
        return null;
    }
}
