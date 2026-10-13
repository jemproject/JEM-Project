<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CancellationLookupTest extends TestCase
{
    private $database;
    private $model;
    private $input;

    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/cancellation_lookup_environment.php';
        $this->database = new CancellationDatabase();
        \Joomla\CMS\Factory::$database = $this->database;
        $this->input = new CancellationValues(['reg_check' => -1, 'cancelplaces' => 2]);
        \Joomla\CMS\Factory::$application = new CancellationApplication($this->input);
        JemFactory::$user = new CancellationValues(['id' => 42, 'guest' => 0, 'block' => 0]);
        foreach ([15, 16, 17] as $id) {
            $this->database->events[$id] = (object) [
                'id' => $id, 'did' => $id, 'dates' => '2026-12-01', 'unregistra' => 1,
                'recurrence_type' => 0, 'recurrence_first_id' => 0, 'series_id' => 0,
                'seriesbooking' => 0, 'singlebooking' => 1,
                'minbookeduser' => 1, 'maxbookeduser' => 5,
            ];
        }
        foreach ([15, 17] as $id) {
            $this->database->registrations[$id] = (object) [
                'id' => $id + 76, 'event' => $id, 'uid' => 42,
                'places' => 2, 'status' => 1, 'waiting' => 0,
            ];
        }
        $this->model = new CancellationEventModel();
        $this->model->setId(15);
    }

    public static function failureStages(): iterable
    {
        yield 'query construction' => ['query'];
        yield 'statement preparation' => ['prepare'];
        yield 'execution' => ['execute'];
    }

    #[DataProvider('failureStages')]
    public function testLookupFailuresRemainErrorsInsteadOfMissingRows(string $stage): void
    {
        $this->database->failureStage = $stage;
        try {
            $this->model->lookup(15, 42);
            self::fail('A failed lookup must throw.');
        } catch (RuntimeException $error) {
            self::assertSame('COM_JEM_CANCELLATION_LOAD_FAILED', $error->getMessage());
            self::assertInstanceOf(RuntimeException::class, $error->getPrevious());
        }
        self::assertCount(1, JemLog::$entries);
        self::assertSame(15, JemLog::$entries[0][2]['event_id']);
        self::assertStringNotContainsString('Sensitive', json_encode(JemLog::$entries));
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testMissingAndForeignRegistrationsRemainAbsent(): void
    {
        self::assertSame(15, $this->model->lookup(15, 42)->id);
        self::assertFalse($this->model->lookup(16, 42));
        self::assertFalse($this->model->lookup(15, 43));
        self::assertFalse($this->model->lookup(0, 42));
        self::assertFalse($this->model->lookup(15, 0));
        self::assertSame([], JemLog::$entries);
    }

    #[DataProvider('failureStages')]
    public function testUserCancellationFailsSafelyBeforeAnyWrite(string $stage): void
    {
        $this->database->failureStage = $stage;
        self::assertFalse($this->model->userregister());
        $this->assertSafeFailure();
    }

    public function testHiddenCancellationFallbackHandlesQueryFailure(): void
    {
        $this->model->visible = false;
        $this->database->failureStage = 'prepare';
        self::assertFalse($this->model->userregister());
        $this->assertSafeFailure();
    }

    public function testLegacyWriterHandlesHiddenCancellationFailure(): void
    {
        $this->model->visible = false;
        $this->database->failureStage = 'execute';
        $message = '';
        self::assertFalse($this->model->storeHiddenCancellation($message));
        self::assertSame('COM_JEM_CANCELLATION_LOAD_FAILED', $message);
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testIndividualCancellationStillReachesTheWriter(): void
    {
        self::assertSame(91, $this->model->userregister());
        self::assertCount(1, JemRegistrationService::$writes);
        $rows = JemRegistrationService::$writes[0];
        self::assertCount(1, $rows);
        self::assertSame(15, $rows[0]->event);
        self::assertSame(-1, $rows[0]->status);
        self::assertSame(0, $rows[0]->places);
    }

    public function testPartialSeriesSkipsOnlyUnbookedOccurrences(): void
    {
        $this->enableSeries();
        self::assertSame(91, $this->model->userregister());
        self::assertCount(1, JemRegistrationService::$writes);
        self::assertSame([15, 17], array_column(JemRegistrationService::$writes[0], 'event'));
    }

    public function testLaterLookupFailureCannotCancelEarlierOccurrences(): void
    {
        $this->enableSeries();
        $this->database->failureEvent = 17;
        self::assertFalse($this->model->userregister());
        $this->assertSafeFailure();
        self::assertSame(17, JemLog::$entries[0][2]['event_id']);
    }

    #[DataProvider('failureStages')]
    public function testSeriesSelectionFailureCannotFallBackToThePrimaryEvent(string $stage): void
    {
        $this->enableSeries();
        $this->model->realSeriesLoader = true;
        $this->database->failureStage = $stage;
        self::assertFalse($this->model->userregister());
        $this->assertSafeFailure();
    }

    public function testNoOwnedRegistrationHasItsOwnMessage(): void
    {
        $this->database->registrations = [];
        self::assertFalse($this->model->userregister());
        self::assertSame('COM_JEM_REGISTRATION_NOT_FOUND', $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
        self::assertSame([], JemLog::$entries);
    }

    public static function registrationFailureStages(): iterable
    {
        yield 'registration preparation' => ['registration_prepare'];
        yield 'registration execution' => ['registration_execute'];
    }

    #[DataProvider('registrationFailureStages')]
    public function testRegistrationLookupErrorsAbortBeforeWriting(string $stage): void
    {
        $this->model->realRegistrationLoader = true;
        $this->database->failureStage = $stage;
        self::assertFalse($this->model->userregister());
        $this->assertSafeFailure();
        self::assertSame(15, JemLog::$entries[0][2]['event_id']);
    }

    public function testLaterRegistrationFailureCannotPartiallyCancelSeries(): void
    {
        $this->enableSeries();
        $this->model->realRegistrationLoader = true;
        $this->database->registrationFailureEvent = 17;
        self::assertFalse($this->model->userregister());
        $this->assertSafeFailure();
        self::assertSame(17, JemLog::$entries[0][2]['event_id']);
    }

    public function testLegacyWriterAbortsOnRegistrationLookupFailure(): void
    {
        $this->model->realRegistrationLoader = true;
        $this->database->failureStage = 'registration_execute';
        $message = '';
        self::assertFalse($this->model->storeHiddenCancellation($message));
        self::assertSame('COM_JEM_CANCELLATION_LOAD_FAILED', $message);
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testHiddenEventCanStillReleaseItsOwnersRegistration(): void
    {
        $this->model->visible = false;
        $this->model->realRegistrationLoader = true;
        self::assertSame(91, $this->model->userregister());
        self::assertCount(1, JemRegistrationService::$writes);
    }

    public function testLoggerFailureStillAbortsCancellation(): void
    {
        JemLog::$fail = true;
        $this->database->failureStage = 'execute';
        self::assertFalse($this->model->userregister());
        $this->assertSafeFailure();
    }

    public function testCancellationDeadlineIsStillEnforced(): void
    {
        JemHelper::$cancellationOpen = false;
        self::assertFalse($this->model->userregister());
        self::assertSame('COM_JEM_ERROR_ANNULATION_NOT_ALLOWED', $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
    }

    public function testPartialCancellationStillHonoursMinimumPlaces(): void
    {
        $this->input->values['cancelplaces'] = 1;
        $this->database->events[15]->minbookeduser = 2;
        self::assertFalse($this->model->userregister());
        self::assertSame('COM_JEM_ERROR_REGISTRATION', $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
        $source = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/event.php');
        self::assertStringContainsString("'e.minbookeduser'", $source);
    }

    public function testGuestsCannotCancel(): void
    {
        JemFactory::$user->values['id'] = 0;
        self::assertFalse($this->model->userregister());
        self::assertSame('JERROR_ALERTNOAUTHOR', $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
    }

    private function enableSeries(): void
    {
        $this->database->events[15]->recurrence_type = 2;
        $this->database->events[15]->seriesbooking = 1;
        $this->database->events[15]->singlebooking = 0;
        $this->model->series = array_values($this->database->events);
    }

    private function assertSafeFailure(): void
    {
        self::assertSame('COM_JEM_CANCELLATION_LOAD_FAILED', $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
        self::assertSame([], \Joomla\CMS\Factory::$application->messages);
    }
}
