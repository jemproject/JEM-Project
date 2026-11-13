<?php

declare(strict_types=1);

use JemProject\Tests\ReservationStatus\Text;
use JemProject\Tests\ReservationStatus\View;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Public state labels must remain readable without changing state-dependent actions. */
final class ReservationStatusLabelsTest extends TestCase
{
    /** @return void Load isolated rendering boundaries and real English strings. */
    protected function setUp(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/reservation_status_labels_environment.php';
        Text::$strings = parse_ini_file(JEM_TEST_ROOT . '/site/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
    }

    /** @return iterable<string, array{int, int, string, bool}> Logical booking states and defensive combinations. */
    public static function bookings(): iterable
    {
        yield 'confirmed' => [1, 0, 'Attending', true];
        yield 'waiting' => [1, 1, 'On waiting list', false];
        yield 'invited' => [0, 0, 'Invited', false];
        yield 'not attending' => [-1, 0, 'Not attending', false];
        yield 'stale waiting after cancellation' => [-1, 1, 'Not attending', false];
        yield 'stale waiting on invitation' => [0, 1, 'Invited', false];
        yield 'logical waiting' => [2, 0, 'On waiting list', false];
        yield 'unknown' => [99, 0, 'Status unknown', false];
    }

    /** @param int $status Stored status. @param int $waiting Waiting flag. @param string $label Text. @param bool $download Ticket action. @return void */
    #[DataProvider('bookings')]
    public function testBookingLabelsAndTicketAction(int $status, int $waiting, string $label, bool $download): void
    {
        $view = new View();
        $view->item = (object) ['id' => 1, 'event' => 2, 'reference' => 'TEST', 'event_title' => 'Test event',
            'user_email' => 'test@example.invalid', 'places' => 1, 'status' => $status, 'waiting' => $waiting];
        $xpath = $view->render();
        self::assertSame($label, trim($xpath->query('//dl/dd')->item(1)->textContent));
        self::assertSame($download ? 1 : 0, $xpath->query('//form[contains(@action,"tickets.download")]')->length);
        self::assertSame(0, $xpath->query('//form[contains(@action,"notification.resend")]')->length);
    }

    /** @return iterable<string, array{string, string}> All known states plus escaped unknown values. */
    public static function notifications(): iterable
    {
        yield 'scheduled' => ['scheduled', 'Scheduled'];
        yield 'queued' => ['queued', 'Queued'];
        yield 'processing' => ['processing', 'Sending'];
        yield 'sent' => ['sent', 'Sent'];
        yield 'failed' => ['failed', 'Failed'];
        yield 'cancelled' => ['cancelled', 'Cancelled'];
        yield 'unknown' => ['future_state', 'future_state'];
        yield 'escaped unknown' => ['<img src=x onerror="alert(1)">', '<img src=x onerror="alert(1)">'];
    }

    /** @param string $state Stored code. @param string $label Display text. @return void */
    #[DataProvider('notifications')]
    public function testFrontendBackendLabelsFiltersAndActions(string $state, string $label): void
    {
        $view = new View($state);
        $view->item = (object) ['id' => 1, 'event' => 2, 'reference' => 'TEST', 'event_title' => 'Test event',
            'user_email' => 'test@example.invalid', 'places' => 1, 'status' => 1, 'waiting' => 0];
        $notification = (object) ['id' => 3, 'state' => $state, 'created' => '2026-11-13 08:00:00',
            'notification_type' => 'registration.confirmed', 'resolved_language' => 'en-GB', 'registration_revision' => 1,
            'event_title' => 'Test event', 'event_id' => 2, 'registration_id' => 1, 'registration_reference' => 'TEST',
            'recipient_name' => 'Test user', 'recipient_email' => 'test@example.invalid', 'recipient_type' => 'user',
            'subject' => 'Test subject', 'body' => 'Test body', 'attempts_total' => 0, 'attempt_count' => 0, 'max_attempts' => 3];
        $view->notifications = [$notification];
        $front = $view->render();
        self::assertSame($label, $front->query('//tbody/tr/td[2]')->item(0)->textContent);
        self::assertSame(0, $front->query('//tbody//img')->length);

        Text::$strings = parse_ini_file(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
        $view->items = [$notification];
        $view->states = [$state];
        $backend = $view->render(true);
        self::assertSame($label, $backend->query('//tbody/tr/td[2]/span')->item(0)->textContent);
        $option = $backend->query('//select[@name="filter_state"]/option[@selected]')->item(0);
        self::assertNotNull($option);
        self::assertSame($state, $option->getAttribute('value'));
        self::assertSame($label, $option->textContent);
        self::assertSame('registration.confirmed', $backend->query('//select[@name="filter_type"]/option[2]')->item(0)->textContent);
        self::assertSame(0, $backend->query('//img')->length);
        self::assertSame(in_array($state, ['queued', 'failed'], true) ? 1 : 0, $backend->query('//button[@value="notification.retry"]')->length);
        self::assertSame($state === 'sent' ? 1 : 0, $backend->query('//button[@value="notification.resend"]')->length);
        $attendee = $view->renderAttendee();
        self::assertSame($label . ' (0)', trim($attendee->query('//tbody/tr/td[2]/text()[1]')->item(0)->textContent));
        self::assertSame(0, $attendee->query('//img')->length);
        self::assertSame(in_array($state, ['queued', 'failed'], true) ? 1 : 0, $attendee->query('//button[@value="notification.retry"]')->length);
        self::assertSame($state === 'sent' ? 1 : 0, $attendee->query('//button[@value="notification.resend"]')->length);
        self::assertSame($state, $notification->state);
    }

    /** @return void Translations, including special characters, are rendered as text. */
    public function testLanguageOverridesAreUsedAndEscaped(): void
    {
        Text::$strings['COM_JEM_ATTENDEES_ATTENDING'] = '<b>Asiste</b> & confirmado';
        Text::$strings['COM_JEM_NOTIFICATION_STATE_QUEUED'] = '<b>En cola</b> & pendiente';
        $view = new View();
        $view->item = (object) ['id' => 1, 'event' => 2, 'reference' => 'TEST', 'event_title' => 'Test event',
            'user_email' => 'test@example.invalid', 'places' => 1, 'status' => 1, 'waiting' => 0];
        $view->notifications = [(object) ['state' => 'queued', 'created' => '2026-11-13', 'subject' => 'Test']];
        $xpath = $view->render();
        self::assertSame('<b>Asiste</b> & confirmado', $xpath->query('//dl/dd')->item(1)->textContent);
        self::assertSame('<b>En cola</b> & pendiente', $xpath->query('//tbody/tr/td[2]')->item(0)->textContent);
        self::assertSame(0, $xpath->query('//dl//b | //tbody//b')->length);
    }

    /** @return iterable Action eligibility remains based on permissions and stored attempt limits. */
    public static function attendeeActions(): iterable
    {
        yield 'retry queued' => ['queued', true, 1, 3, 1, 0];
        yield 'retry failed' => ['failed', true, 1, 3, 1, 0];
        yield 'attempts exhausted' => ['failed', true, 3, 3, 0, 0];
        yield 'no retry permission' => ['queued', false, 1, 3, 0, 0];
        yield 'resend sent' => ['sent', true, 3, 3, 0, 1];
        yield 'no resend permission' => ['sent', false, 0, 3, 0, 0];
    }

    /**
     * Keep audit counters, revisions, escaping and action eligibility unchanged.
     *
     * @param string $state Stored notification code.
     * @param bool $allowed Permission to retry/resend.
     * @param int $attempts Attempts for the current delivery cycle.
     * @param int $maximum Maximum attempts for that cycle.
     * @param int $retry Expected retry buttons.
     * @param int $resend Expected resend buttons.
     * @return void
     */
    #[DataProvider('attendeeActions')]
    public function testAttendeeCountersTranslationsAndActions(string $state, bool $allowed, int $attempts, int $maximum, int $retry, int $resend): void
    {
        Text::$strings = parse_ini_file(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
        Text::$strings['COM_JEM_NOTIFICATION_STATE_' . strtoupper($state)] = '<b>Translated</b> & text';
        $view = new View();
        $view->canNotificationResend = $allowed;
        $notification = (object) ['id' => 3, 'state' => $state, 'created' => '2026-11-13',
            'registration_revision' => 7, 'recipient_email' => 'test@example.invalid',
            'resolved_language' => 'en-GB', 'subject' => 'Test', 'body' => 'Test body',
            'last_error' => '<img src=x>', 'attempts_total' => 12,
            'attempt_count' => $attempts, 'max_attempts' => $maximum];
        $before = clone $notification;
        $view->notifications = [$notification];
        $xpath = $view->renderAttendee();
        self::assertSame('<b>Translated</b> & text (12)', trim($xpath->query('//tbody/tr/td[2]/text()[1]')->item(0)->textContent));
        self::assertSame(Text::_('COM_JEM_REGISTRATION_HISTORY_REVISION') . ' 7', $xpath->query('//tbody/tr/td[2]/small[1]')->item(0)->textContent);
        self::assertSame('<img src=x>', $xpath->query('//small[@class="text-danger"]')->item(0)->textContent);
        self::assertSame(0, $xpath->query('//img | //b')->length);
        self::assertSame($retry, $xpath->query('//button[@value="notification.retry"]')->length);
        self::assertSame($resend, $xpath->query('//button[@value="notification.resend"]')->length);
        self::assertEquals($before, $notification);
    }
}
