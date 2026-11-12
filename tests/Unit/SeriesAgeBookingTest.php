<?php
declare(strict_types=1);

use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Real registration orchestration and age calculation, with isolated retrieval/write boundaries. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SeriesAgeBookingTest extends TestCase
{
    private CancellationDatabase $db;
    private CancellationValues $input;
    private CancellationEventModel $model;

    /** @return void Seven dated sessions, one participant, and a known birthday inside the series. */
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/cancellation_lookup_environment.php';
        $this->db = new CancellationDatabase();
        Factory::$database = $this->db;
        $this->input = new CancellationValues(['reg_check' => 1, 'addplaces' => 1, 'reg_check_series' => '1']);
        Factory::$application = new CancellationApplication($this->input);
        JemFactory::$user = new CancellationValues(['id' => 42, 'guest' => 0, 'block' => 0]);
        $this->birthday('2014-12-20');
        $dates = ['2026-12-02', '2026-12-09', '2026-12-16', '2026-12-23', '2026-12-30', '2027-01-06', '2027-01-13'];
        foreach ($dates as $n => $date) {
            $id = 10 + $n;
            $this->db->events[$id] = (object) [
                'id' => $id, 'did' => $id, 'title' => 'Session ' . $id, 'dates' => $date,
                'event_age_minimum' => 0, 'event_age_maximum' => 11,
                'event_status' => 'scheduled', 'published' => 1, 'registra' => 1, 'unregistra' => 1,
                'registration_open' => true, 'params' => new CancellationValues(['access-view' => true]),
                'recurrence_type' => 2, 'recurrence_first_id' => 10, 'series_id' => 0,
                'seriesbooking' => 1, 'singlebooking' => 0, 'maxplaces' => 20, 'waitinglist' => 1,
                'minbookeduser' => 1, 'maxbookeduser' => 5,
            ];
        }
        $this->model = new class extends CancellationEventModel {
            public array $loaded = [];
            public bool $editor = false;

            /** @param int|null $pk Selected date. @return object|false Apply the real age visibility check. */
            public function getItem($pk = null)
            {
                $this->loaded[] = $pk;
                $event = parent::getItem($pk);
                if (!$event) {
                    return false;
                }
                JemAgeAccess::decorateEvent($event, JemFactory::$user, $this->editor);
                if (!JemAgeAccess::canViewEvent($event)) {
                    Factory::$application->enqueueMessage('COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND', 'error');
                    return false;
                }
                return $event;
            }
        };
        $this->model->setId(10);
        $this->model->series = array_values($this->db->events);
    }

    /** @param string|null $date Known DOB, or missing profile value. @return void */
    private function birthday(?string $date): void
    {
        (new ReflectionProperty(JemAgeAccess::class, 'birthDates'))->setValue(null, [42 => $date]);
    }

    /** @param array $ids Expected new/modified dates. @param int $excluded Excluded age count. @return void */
    private function assertBooked(array $ids, int $excluded): void
    {
        self::assertGreaterThan(0, $this->model->userregister(), $this->model->getError());
        self::assertCount(1, JemRegistrationService::$writes);
        self::assertSame($ids, array_column(JemRegistrationService::$writes[0], 'event'));
        $messages = Factory::$application->messages;
        self::assertNotContains('error', array_column($messages, 1));
        $ageMessages = array_values(array_filter($messages, static fn ($row) => str_starts_with($row[0], 'COM_JEM_SERIES_BOOKING_AGE_EXCLUDED')));
        self::assertSame($excluded ? [['COM_JEM_SERIES_BOOKING_AGE_EXCLUDED' . $excluded, 'notice']] : [], $ageMessages);
    }

    /** @return iterable Mandatory/optional booking, including custom-date series. */
    public static function seriesModes(): iterable
    {
        yield 'mandatory recurring' => [0, false];
        yield 'optional recurring' => [1, false];
        yield 'mandatory custom' => [0, true];
        yield 'optional custom' => [1, true];
    }

    /** @param int $single Individual booking setting. @param bool $custom Custom series. @return void */
    #[DataProvider('seriesModes')]
    public function testBirthdayIsEvaluatedForEverySession(int $single, bool $custom): void
    {
        $this->db->events[10]->singlebooking = $single;
        if ($custom) {
            $this->db->events[10]->recurrence_type = 0;
            $this->db->events[10]->series_id = 1;
        }
        $this->assertBooked([10, 11, 12], 4);
        self::assertSame([10, 11, 12], $this->model->loaded, 'Excluded dates must not produce the loader 404.');
    }

    /** @return void The birthday itself uses the newly reached age, not the preceding age. */
    public function testExactBirthdayBoundary(): void
    {
        $this->birthday('2014-12-09');
        $this->assertBooked([10], 6);
    }

    /** @return void A younger member books all seven dates. */
    public function testMemberRemainsEligibleForTheEntireSeries(): void
    {
        $this->birthday('2016-12-20');
        $this->assertBooked(range(10, 16), 0);
    }

    /** @return void Per-date changes can tighten or relax the first event's range. */
    public function testEachEventUsesItsOwnLimits(): void
    {
        $this->db->events[11]->event_age_maximum = 10;
        $this->db->events[13]->event_age_maximum = 15;
        $this->assertBooked([10, 12, 13], 4);
    }

    /** @return void The event and venue ranges are intersected, including an unrestricted event. */
    public function testVenueRestrictionAppliesIndependently(): void
    {
        foreach ($this->db->events as $event) {
            $event->event_age_minimum = null;
            $event->event_age_maximum = null;
            $event->venue_age_minimum = 0;
            $event->venue_age_maximum = 11;
        }
        $this->db->events[12]->venue_age_maximum = 10;
        $this->assertBooked([10, 11], 5);
    }

    /** @return void Reaching a minimum age can make later sessions eligible. */
    public function testMinimumAgeIsAlsoAssessedOnEachDate(): void
    {
        $this->birthday('2008-12-20');
        foreach ($this->db->events as $event) {
            $event->event_age_minimum = 18;
            $event->event_age_maximum = 99;
        }
        $this->db->events[10]->event_age_minimum = 16;
        $this->assertBooked([10, 13, 14, 15, 16], 2);
    }

    /** @return iterable Existing confirmed/waiting reservations, including a priced order. */
    public static function existingBookings(): iterable
    {
        yield 'confirmed' => [0, 'classic'];
        yield 'waiting' => [1, 'classic'];
        yield 'priced confirmed' => [0, 'priced'];
    }

    /** @param int $waiting Waiting flag. @param string $pricing Pricing mode. @return void */
    #[DataProvider('existingBookings')]
    public function testExistingReservationsRemainUnchanged(int $waiting, string $pricing): void
    {
        $old = (object) ['id' => 91, 'event' => 13, 'uid' => 42, 'status' => 1, 'waiting' => $waiting,
            'places' => 2, 'revision' => 4, 'comment' => 'Keep', 'pricing_mode' => $pricing];
        $this->db->registrations[13] = clone $old;
        $this->assertBooked([10, 11, 12], 3);
        self::assertEquals($old, $this->db->registrations[13]);
    }

    /** @return void Suspension and age exclusions have separate, non-duplicated counts. */
    public function testMixedSuspensionAndAgeRestrictions(): void
    {
        $this->db->events[13]->event_status = 'cancelled';
        $this->db->events[14]->event_status = 'postponed';
        $this->assertBooked([10, 11, 12], 2);
        self::assertContains(['COM_JEM_SERIES_BOOKING_RESULT3,0,2', 'notice'], Factory::$application->messages);
    }

    /** @return void Duplicate candidates do not duplicate writes or notices. */
    public function testDuplicateDatesAreCountedOnlyOnce(): void
    {
        $this->model->series[] = $this->db->events[13];
        $this->model->series[] = $this->db->events[11];
        $this->assertBooked([10, 11, 12], 4);
    }

    /** @return iterable Other checks remain blocking rather than becoming partial success. */
    public static function blockingCases(): iterable
    {
        foreach (['missing_dob', 'acl', 'unpublished', 'closed', 'missing_event', 'invitation', 'sql'] as $case) {
            yield $case => [$case];
        }
    }

    /** @param string $case Failure not explained by a known age restriction. @return void */
    #[DataProvider('blockingCases')]
    public function testOtherFailuresAreNotSilentlySkipped(string $case): void
    {
        if ($case === 'missing_dob') {
            $this->birthday(null);
        } elseif ($case === 'acl') {
            $this->db->events[11]->params = new CancellationValues(['access-view' => false]);
        } elseif ($case === 'unpublished') {
            $this->db->events[11]->published = 0;
        } elseif ($case === 'closed') {
            $this->db->events[11]->registration_open = false;
        } elseif ($case === 'missing_event') {
            $this->model->series[] = (object) ['id' => 999];
        } elseif ($case === 'invitation') {
            JemHelper::$settings['regallowinvitation'] = 1;
            $this->db->events[11]->reginvitedonly = 1;
        } else {
            $this->db->failureStage = 'age_query';
        }
        self::assertFalse($this->model->userregister());
        self::assertSame([], JemRegistrationService::$writes);
        self::assertNotContains('notice', array_column(Factory::$application->messages, 1));
        if ($case === 'sql') {
            self::assertSame('COM_JEM_ERROR_REGISTRATION', $this->model->getError());
        }
    }

    /** @return void Visibility permissions never grant an age exception to registration. */
    public function testEditorCannotRegisterAnIneligiblePrimaryEvent(): void
    {
        $this->model->editor = true;
        $this->model->setId(13);
        self::assertFalse($this->model->userregister());
        self::assertStringContainsString('COM_JEM_AGE_ACCESS_REGISTRATION_DENIED', $this->model->getError());
        self::assertSame([], JemRegistrationService::$writes);
    }

    /** @return void Single-date booking is not expanded or affected by later birthdays. */
    public function testSingleBookingKeepsItsOwnAgeCheck(): void
    {
        $this->db->events[10]->singlebooking = 1;
        $this->input->values['reg_check_series'] = '0';
        $this->assertBooked([10], 0);
        $this->model->setId(13);
        self::assertFalse($this->model->userregister());
        self::assertCount(1, JemRegistrationService::$writes);
    }

    /** @return void A participant can still cancel previously held seats after becoming ineligible. */
    public function testCancellationDoesNotFilterByAge(): void
    {
        foreach ($this->db->events as $event) {
            unset($event->params);
            $this->db->registrations[$event->id] = (object) ['id' => 100 + $event->id, 'event' => $event->id,
                'uid' => 42, 'status' => 1, 'waiting' => 0, 'places' => 1, 'revision' => 1, 'comment' => ''];
        }
        $this->input->values = ['reg_check' => -1, 'cancelplaces' => 1, 'reg_check_series' => '1'];
        $preview = $this->model->userregister(true);
        self::assertIsArray($preview, $this->model->getError());
        self::assertCount(7, $preview['events']);
        self::assertSame([], JemRegistrationService::$writes);
        $this->input->values['jem_cancel_confirm'] = 1;
        $this->input->values['jem_cancel_fingerprint'] = $preview['fingerprint'];
        self::assertGreaterThan(0, $this->model->userregister(), $this->model->getError());
        self::assertSame(range(10, 16), array_column(JemRegistrationService::$writes[0], 'event'));
    }
}
