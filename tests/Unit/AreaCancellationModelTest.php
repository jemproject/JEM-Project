<?php
declare(strict_types=1);
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AreaCancellationModelTest extends TestCase
{
    private $db;
    private $input;
    private $model;

    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/cancellation_lookup_environment.php';
        $this->db = new CancellationDatabase();
        \Joomla\CMS\Factory::$database = $this->db;
        $this->input = new CancellationValues(['reg_check' => -1, 'cancelplaces' => 2, 'reg_check_series' => '1']);
        \Joomla\CMS\Factory::$application = new CancellationApplication($this->input);
        JemFactory::$user = new CancellationValues(['id' => 42, 'guest' => 0, 'block' => 0]);
        foreach ([15, 16] as $id) {
            $this->db->events[$id] = (object) [
                'id' => $id, 'dates' => '2030-12-01', 'capacity_mode' => 'areas',
                'recurrence_type' => 1, 'recurrence_first_id' => 15, 'series_id' => 15,
                'seriesbooking' => 1, 'singlebooking' => 1, 'unregistra' => 1,
                'minbookeduser' => 1, 'maxbookeduser' => 10,
            ];
            $this->db->registrations[$id] = (object) [
                'id' => $id + 76, 'uid' => 42, 'event' => $id, 'places' => 4, 'revision' => 2,
                'status' => 1, 'waiting' => 0,
            ];
            JemRegistrationService::$capacities[$id] = ['area:1' => 4];
        }
        $this->model = new CancellationEventModel();
        $this->model->setId(15);
        $this->model->series = array_values($this->db->events);
    }

    private function preview(): array
    {
        $preview = $this->model->userregister(true);
        self::assertIsArray($preview, $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
        $this->input->values['jem_cancel_confirm'] = 1;
        $this->input->values['jem_cancel_fingerprint'] = $preview['fingerprint'];
        return $preview;
    }

    public function testPreviewThenConfirmReducesEveryOccurrence(): void
    {
        $preview = $this->preview();
        self::assertCount(2, $preview['events']);
        self::assertSame([2, 2], array_column($preview['events'], 'remaining'));
        self::assertSame(91, $this->model->userregister());
        self::assertSame([2, 2], array_column(JemRegistrationService::$writes[0], 'places'));
        self::assertSame([15 => 2, 16 => 2], JemRegistrationService::$writeOptions[0]['expectedRevisions']);
    }

    public function testNoConfirmationDoesNotWrite(): void
    {
        self::assertFalse($this->model->userregister());
        self::assertSame('COM_JEM_CANCELLATION_REVIEW_CHANGED', $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testChangedRevisionInvalidatesTheConfirmation(): void
    {
        $this->preview();
        $this->db->registrations[16]->revision++;
        self::assertFalse($this->model->userregister());
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testChangedQuantityInvalidatesTheConfirmation(): void
    {
        $this->preview();
        $this->input->values['cancelplaces'] = 1;
        self::assertFalse($this->model->userregister());
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testChangedSeriesScopeInvalidatesTheConfirmation(): void
    {
        $this->preview();
        $this->input->values['reg_check_series'] = '0';
        self::assertFalse($this->model->userregister());
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testClosedCancellationWindowPreventsConfirmedWrite(): void
    {
        $this->preview();
        JemHelper::$cancellationOpen = false;
        self::assertFalse($this->model->userregister());
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testSingleOccurrenceDoesNotModifyTheOtherReservation(): void
    {
        $this->input->values['reg_check_series'] = '0';
        $preview = $this->preview();
        self::assertCount(1, $preview['events']);
        self::assertSame(91, $this->model->userregister());
        self::assertSame(4, $this->db->registrations[16]->places);
    }

    public function testForcedSeriesUsesAllOccurrences(): void
    {
        $this->input->values['reg_check_series'] = '0';
        $this->db->events[15]->singlebooking = 0;
        self::assertCount(2, $this->preview()['events']);
    }

    public function testInvalidLaterQuantityPreventsAnyWrite(): void
    {
        $this->db->registrations[16]->places = 1;
        self::assertFalse($this->model->userregister(true));
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testAbsentPrimaryFromSeriesStillValidatesReduction(): void
    {
        $this->model->series = [$this->db->events[16]];
        $this->input->values['cancelplaces'] = 5;
        self::assertFalse($this->model->userregister(true));
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testUnbookedOccurrenceIsNotCreated(): void
    {
        unset($this->db->registrations[16]);
        self::assertCount(1, $this->preview()['events']);
        self::assertSame(91, $this->model->userregister());
        self::assertArrayNotHasKey(16, $this->db->registrations);
    }

    public function testGuestCannotPreviewSomeoneElsesCancellation(): void
    {
        JemFactory::$user->values['id'] = 0;
        self::assertFalse($this->model->userregister(true));
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testFirstNegativeAnswerDoesNotRequireCancellationConfirmation(): void
    {
        $this->db->registrations = [];
        $this->input->values['reg_check_series'] = '0';
        $this->input->values['cancelplaces'] = 0;
        $event = $this->db->events[15];
        $event->requestanswer = 1;
        $event->published = 1;
        $event->registra = 1;
        $event->registration_open = true;
        $event->params = new CancellationValues(['access-view' => true]);
        self::assertSame(['required' => false], $this->model->userregister(true));
        self::assertSame([], JemRegistrationService::$writes);
        self::assertSame(1015, $this->model->userregister());
        self::assertSame(0, JemRegistrationService::$writes[0][0]->places);
        self::assertSame(-1, JemRegistrationService::$writes[0][0]->status);
    }

    public function testForgedConfirmationCannotWrite(): void
    {
        $this->preview();
        $this->input->values['jem_cancel_fingerprint'] = str_repeat('0', 64);
        self::assertFalse($this->model->userregister());
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testPreviewCannotBeUsedToRegister(): void
    {
        $this->input->values['reg_check'] = 1;
        self::assertFalse($this->model->userregister(true));
        self::assertSame([], JemRegistrationService::$writes);
    }
}
