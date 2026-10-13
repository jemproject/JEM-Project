<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class InitialNegativeResponseTest extends TestCase
{
    private $database;
    private $model;
    private $input;

    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/cancellation_lookup_environment.php';
        $this->database = new CancellationDatabase();
        \Joomla\CMS\Factory::$database = $this->database;
        $this->input = new CancellationValues(['reg_check' => -1, 'addplaces' => 1, 'reg_comment' => 'Unable to attend.']);
        \Joomla\CMS\Factory::$application = new CancellationApplication($this->input);
        JemFactory::$user = new CancellationValues(['id' => 42, 'guest' => 0, 'block' => 0]);
        foreach ([15, 16, 17] as $id) {
            $this->database->events[$id] = (object) [
                'id' => $id, 'did' => $id, 'dates' => '2026-12-01', 'published' => 1,
                'requestanswer' => 1, 'registra' => 1, 'registration_open' => true,
                'unregistra' => 1, 'reginvitedonly' => 0, 'params' => new CancellationValues(['access-view' => true]),
                'recurrence_type' => 0, 'recurrence_first_id' => 0, 'series_id' => 0,
                'seriesbooking' => 0, 'singlebooking' => 1, 'minbookeduser' => 2, 'maxbookeduser' => 5,
            ];
        }
        $this->model = new CancellationEventModel();
        $this->model->setId(15);
    }

    public function testFirstNegativeAnswerIsStoredAsAResponseWithNoPlaces(): void
    {
        self::assertSame(1015, $this->model->userregister());
        $row = $this->database->registrations[15];
        self::assertSame(42, $row->uid);
        self::assertSame(-1, $row->status);
        self::assertSame(0, $row->places);
        self::assertSame(0, $row->waiting);
        self::assertSame('Unable to attend.', $row->comment);
        self::assertTrue(JemRegistrationService::$writeOptions[0]['requireNew']);
    }

    public function testInitialAnswerDoesNotRequireCancellationToBeEnabled(): void
    {
        JemHelper::$cancellationOpen = false;
        $this->database->events[15]->unregistra = 0;
        self::assertSame(1015, $this->model->userregister());
        self::assertSame(0, $this->database->registrations[15]->places);
    }

    public static function deniedCases(): iterable
    {
        yield 'request answer disabled' => ['requestanswer', 0, 'COM_JEM_REGISTRATION_NOT_FOUND'];
        yield 'unpublished' => ['published', 0, 'COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND'];
        yield 'registration closed or not started' => ['registration_open', false, 'COM_JEM_EVENT_REGISTRATION_CLOSED'];
        yield 'no view access' => ['access', false, 'COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND'];
        yield 'global registration disabled' => ['global', 0, 'COM_JEM_EVENT_REGISTRATION_CLOSED'];
        yield 'event registration disabled' => ['registra', 0, 'COM_JEM_EVENT_REGISTRATION_CLOSED'];
        yield 'invitation required' => ['reginvitedonly', 1, 'COM_JEM_NOT_INVITED'];
        yield 'blocked user' => ['block', 1, 'JERROR_ALERTNOAUTHOR'];
        yield 'guest' => ['id', 0, 'JERROR_ALERTNOAUTHOR'];
    }

    #[DataProvider('deniedCases')]
    public function testInitialAnswerCannotBypassAccessChecks(string $field, $value, string $message): void
    {
        if ($field === 'access') {
            $this->database->events[15]->params->values['access-view'] = $value;
        } elseif ($field === 'global') {
            JemHelper::$settings['showfroregistra'] = $value;
        } elseif (in_array($field, ['block', 'id'], true)) {
            JemFactory::$user->values[$field] = $value;
        } else {
            $this->database->events[15]->$field = $value;
        }
        if ($field === 'registra') {
            JemHelper::$settings['showfroregistra'] = 2;
        }
        JemHelper::$settings['regallowinvitation'] = 1;
        self::assertFalse($this->model->userregister());
        self::assertSame($message, $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testInitialAnswerCannotBypassAnUnavailableEvent(): void
    {
        $this->model->visible = false;
        self::assertFalse($this->model->userregister());
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testInitialAnswerRejectsACancellationQuantity(): void
    {
        $this->input->values['cancelplaces'] = 1;
        self::assertFalse($this->model->userregister());
        self::assertSame('COM_JEM_ERROR_REGISTRATION', $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testResponseCanBeRepeatedWithoutCreatingAnotherRow(): void
    {
        self::assertSame(1015, $this->model->userregister());
        self::assertSame(1015, $this->model->userregister());
        self::assertCount(1, $this->database->registrations);
        self::assertSame(-1, $this->database->registrations[15]->status);
        self::assertSame(0, $this->database->registrations[15]->places);
    }

    public function testFirstSeriesResponseStoresOnlyUnansweredOccurrences(): void
    {
        $this->enableSeries();
        $this->database->registrations[16] = (object) [
            'id' => 92, 'event' => 16, 'uid' => 42, 'status' => 1, 'waiting' => 0, 'places' => 2,
        ];
        self::assertSame(1015, $this->model->userregister());
        self::assertSame([15, 17], array_column(JemRegistrationService::$writes[0], 'event'));
        self::assertSame(1, $this->database->registrations[16]->status);
        self::assertSame(2, $this->database->registrations[16]->places);
        self::assertSame(-1, $this->database->registrations[17]->status);
        self::assertSame(0, $this->database->registrations[17]->places);
    }

    public function testEachNewSeriesResponseRequiresItsOwnAccessChecks(): void
    {
        $this->enableSeries();
        $this->database->events[17]->params = new CancellationValues(['access-view' => false]);
        self::assertFalse($this->model->userregister());
        self::assertSame('COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND', $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testEachNewSeriesResponseRequiresRequestAnswer(): void
    {
        $this->enableSeries();
        $this->database->events[17]->requestanswer = 0;
        self::assertFalse($this->model->userregister());
        self::assertSame('COM_JEM_REGISTRATION_NOT_FOUND', $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testFailedRegistrationLookupDoesNotBecomeANewNegativeAnswer(): void
    {
        $this->model->realRegistrationLoader = true;
        $this->database->failureStage = 'registration_execute';
        self::assertFalse($this->model->userregister());
        self::assertSame('COM_JEM_CANCELLATION_LOAD_FAILED', $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testQuantityPolicyDistinguishesAFirstAnswerFromCancellation(): void
    {
        $result = JemRegistrationQuantity::resolveResponse(-1, 1, 0, false, $this->database->events[15]);
        self::assertSame('decline', $result->operation);
        self::assertSame(-1, $result->status);
        self::assertSame(0, $result->places);
        self::assertSame(0, $result->quantity);
    }

    private function enableSeries(): void
    {
        $this->database->events[15]->recurrence_type = 2;
        $this->database->events[15]->seriesbooking = 1;
        $this->database->events[15]->singlebooking = 0;
        $this->model->series = array_values($this->database->events);
    }

    public function testFirstResponseCanLaterBecomeAnAttendance(): void
    {
        self::assertSame(1015, $this->model->userregister());
        $this->input->values['reg_check'] = 1;
        $this->input->values['addplaces'] = 2;
        self::assertSame(1015, $this->model->userregister());
        self::assertCount(1, $this->database->registrations);
        self::assertSame(1, $this->database->registrations[15]->status);
        self::assertSame(2, $this->database->registrations[15]->places);
    }

    public function testEntireUnansweredSeriesStoresZeroPlaceResponses(): void
    {
        $this->enableSeries();
        self::assertSame(1015, $this->model->userregister());
        self::assertSame([15, 16, 17], array_column(JemRegistrationService::$writes[0], 'event'));
        self::assertSame([-1, -1, -1], array_column(JemRegistrationService::$writes[0], 'status'));
        self::assertSame([0, 0, 0], array_column(JemRegistrationService::$writes[0], 'places'));
    }

    public function testLaterRegistrationLookupFailureAbortsAllInitialSeriesAnswers(): void
    {
        $this->enableSeries();
        $this->model->realRegistrationLoader = true;
        $this->database->registrationFailureEvent = 17;
        self::assertFalse($this->model->userregister());
        self::assertSame('COM_JEM_CANCELLATION_LOAD_FAILED', $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testAgeRestrictionsApplyToInitialAnswersWhenSupported(): void
    {
        foreach (['unknown', 'restricted'] as $state) {
            $this->database->events[15]->age_access_state = $state;
            if (defined('JemRegistrationAccessPolicy::AGE_UNKNOWN')) {
                self::assertFalse($this->model->userregister());
                self::assertSame([], JemRegistrationService::$writes);
            } else {
                // 5.0.2 has no age-access feature.
                self::assertSame(1015, $this->model->userregister());
                $this->database->registrations = [];
            }
        }
    }

    public function testAreaEventInitialAnswerStillStoresZeroPlaces(): void
    {
        $this->database->events[15]->capacity_mode = 'areas';
        $this->input->values['capacity_areas'] = ['1' => 2];
        self::assertSame(1015, $this->model->userregister());
        self::assertSame(0, $this->database->registrations[15]->places);
        if (array_key_exists('capacityAllocations', JemRegistrationService::$writeOptions[0])) {
            self::assertSame([], JemRegistrationService::$writeOptions[0]['capacityAllocations']);
        }
    }

    public function testAreaEventInitialAnswerRejectsAnInvalidCancellationQuantity(): void
    {
        $this->database->events[15]->capacity_mode = 'areas';
        $this->input->values['cancelplaces'] = 2;
        self::assertFalse($this->model->userregister());
        self::assertSame('COM_JEM_ERROR_REGISTRATION', $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
    }
}
