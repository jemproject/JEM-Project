<?php
declare(strict_types=1);
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AreaQuantityModelTest extends TestCase
{
    private $db;
    private $model;
    private $input;

    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/cancellation_lookup_environment.php';
        $this->db = new CancellationDatabase();
        \Joomla\CMS\Factory::$database = $this->db;
        $this->input = new CancellationValues(['reg_check' => 1, 'capacity_areas' => ['area:1' => 1, 'area:2' => 1]]);
        \Joomla\CMS\Factory::$application = new CancellationApplication($this->input);
        JemFactory::$user = new CancellationValues(['id' => 42, 'guest' => 0, 'block' => 0]);
        $this->db->events[15] = (object) [
            'id' => 15, 'dates' => '2030-12-01', 'capacity_mode' => 'areas', 'minbookeduser' => 2,
            'maxbookeduser' => 4, 'recurrence_type' => 0, 'series_id' => 0, 'registra' => 1,
            'published' => 1, 'registration_open' => true, 'params' => new CancellationValues(['access-view' => true]),
        ];
        $this->model = new CancellationEventModel();
        $this->model->setId(15);
    }

    #[DataProvider('invalidSelections')]
    public function testSpecificFeedbackAndNoWrites(array $selection, string $key): void
    {
        $this->input->values['capacity_areas'] = $selection;
        self::assertFalse($this->model->userregister());
        self::assertStringContainsString('COM_JEM_CAPACITY_REGISTRATION_' . $key, $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
    }

    public static function invalidSelections(): array
    {
        return [[[1,0], 'BELOW_MINIMUM'], [[3,2], 'ABOVE_MAXIMUM'], [[5], 'ABOVE_MAXIMUM'],
            [[0,0], 'SELECTION_REQUIRED'], [['abc'], 'INVALID_QUANTITY'], [[-1,3], 'INVALID_QUANTITY']];
    }

    #[DataProvider('validSelections')]
    public function testValidDistributionReachesWriter(array $selection, int $total): void
    {
        $this->input->values['capacity_areas'] = $selection;
        self::assertSame(1015, $this->model->userregister());
        self::assertSame($total, JemRegistrationService::$writes[0][0]->places);
    }

    public static function validSelections(): array
    {
        return [[[1,1],2], [[2,0],2], [[2,2],4]];
    }

    public function testExistingBookingUsesFinalTotalNotAdditionalPlaces(): void
    {
        $this->db->registrations[15] = (object) ['id' => 91, 'event' => 15, 'uid' => 42, 'places' => 2, 'status' => 1, 'waiting' => 0];
        $this->input->values['capacity_areas'] = [2,2];
        self::assertSame(91, $this->model->userregister());
        self::assertSame(4, JemRegistrationService::$writes[0][0]->places);
    }

    public function testAreaLimitErrorsDoNotBreakPartialCancellation(): void
    {
        $this->db->registrations[15] = (object) ['id' => 91, 'event' => 15, 'uid' => 42, 'places' => 4, 'status' => 1, 'waiting' => 0, 'revision' => 1];
        unset($this->db->events[15]->params); // The cancellation fingerprint serialises the event.
        JemRegistrationService::$capacities[15] = ['area:1' => 2, 'area:2' => 2];
        $this->input->values = ['reg_check' => -1, 'cancelplaces' => 2, 'cancel_capacity_areas' => ['area:1' => 1, 'area:2' => 1]];
        $preview = $this->model->userregister(true);
        self::assertIsArray($preview, $this->model->getError());
        $this->input->values['jem_cancel_confirm'] = 1;
        $this->input->values['jem_cancel_fingerprint'] = $preview['fingerprint'];
        self::assertSame(91, $this->model->userregister());
        self::assertSame(2, JemRegistrationService::$writes[0][0]->places);
        self::assertSame(1, JemRegistrationService::$writes[0][0]->status);
    }
}
