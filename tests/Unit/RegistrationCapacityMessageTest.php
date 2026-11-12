<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Verify frontend model error handling without dispatching notifications or storing rows. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class RegistrationCapacityMessageTest extends TestCase
{
    private CancellationEventModel $model;

    /** @return void Prepare an existing booking and make the writer reject its increase. */
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/cancellation_lookup_environment.php';
        Factory::$database = new CancellationDatabase();
        Factory::$application = new CancellationApplication(new CancellationValues(['reg_check' => 1, 'addplaces' => 1]));
        JemFactory::$user = new CancellationValues(['id' => 42, 'guest' => 0, 'block' => 0]);
        Factory::$database->events[10] = (object) [
            'id' => 10, 'did' => 10, 'title' => 'Capacity', 'dates' => '2099-12-01',
            'event_status' => 'scheduled', 'published' => 1, 'registra' => 1, 'unregistra' => 1,
            'registration_open' => true, 'params' => new CancellationValues(['access-view' => true]),
            'recurrence_type' => 0, 'seriesbooking' => 0, 'singlebooking' => 1,
            'maxplaces' => 6, 'waitinglist' => 1, 'minbookeduser' => 1, 'maxbookeduser' => 5,
        ];
        Factory::$database->registrations[10] = (object) ['id' => 91, 'uid' => 42, 'event' => 10, 'places' => 1, 'status' => 1, 'waiting' => 0];
        JemRegistrationService::$failure = JemRegistrationService::CHANGE_UNAVAILABLE;
        $this->model = new CancellationEventModel();
        $this->model->setId(10);
    }

    /** @return void The normal frontend route reports failure without enqueueing success messages. */
    public function testRejectedIncreaseProducesTranslatedErrorAndNoSuccess(): void
    {
        self::assertFalse($this->model->userregister());
        self::assertSame('COM_JEM_REGISTRATION_CHANGE_UNAVAILABLE', $this->model->getError());
        self::assertSame([], Factory::$application->messages);
        self::assertSame([], JemRegistrationService::$writes);
        self::assertSame(1, Factory::$database->registrations[10]->places);
    }

    /** @return void The single-registration API exposes the same translated error. */
    public function testSingleRegistrationRouteReportsTheSameFailure(): void
    {
        $error = '';
        $arguments = [10, 42, '', 1, 2, '', &$error, 91, true];
        $result = (new ReflectionMethod($this->model, '_doRegister'))->invokeArgs($this->model, $arguments);
        self::assertFalse($result);
        self::assertSame('COM_JEM_REGISTRATION_CHANGE_UNAVAILABLE', $error);
        self::assertSame([], JemRegistrationService::$writes);
    }
}
