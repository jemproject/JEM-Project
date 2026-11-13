<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseDriver;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

/** Real Joomla/database/plugin checks; the transport never opens a connection. */
final class NotificationDateLanguageJoomlaIntegrationTest extends JoomlaTestCase
{
    private static array $languageFiles = [];
    private static mixed $originalSession;
    private DatabaseDriver $db;
    private object $plugin;
    private mixed $originalMailer;
    private mixed $originalUser;
    private int $eventId;
    private int $venueId;
    private int $registrationId;
    private array $recipients = [];
    private array $originalSettings;
    private mixed $originalLanguage;
    private mixed $originalFactoryLanguage;

    /** Load candidate classes and temporarily expose their English defaults and a second language. */
    public static function setUpBeforeClass(): void
    {
        self::bootJoomlaSite();
        self::$originalSession = Factory::getApplication()->getSession();
        Factory::getApplication()->setSession(Factory::getContainer()->get('session.cli'));
        self::assertSame(
            hash_file('sha256', JEM_TEST_ROOT . '/site/classes/notificationtemplateservice.class.php'),
            hash_file('sha256', JPATH_SITE . '/components/com_jem/classes/notificationtemplateservice.class.php'),
            'Test the candidate installation, not an older installed catalogue.'
        );
        foreach (['notificationtemplatecatalog.class.php', 'reminderservice.class.php'] as $file) {
            self::assertSame(
                hash_file('sha256', JEM_TEST_ROOT . '/site/classes/' . $file),
                hash_file('sha256', JPATH_SITE . '/components/com_jem/classes/' . $file),
                'Install the candidate notification classes before running this test.'
            );
        }
        require_once JPATH_SITE . '/components/com_jem/factory.php';
        require_once JEM_TEST_ROOT . '/plugins/plg_jem_mailer/mailer.php';
        require_once JEM_TEST_ROOT . '/admin/models/notificationtemplate.php';
        require_once JEM_TEST_ROOT . '/admin/controllers/notificationtemplate.php';
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/helpers/helper.php';
        register_shutdown_function(array(self::class, 'restoreLanguageFiles'));
        foreach (array('en-GB', 'es-ES') as $tag) {
            // Use the first path read by the real resolver; preserve any existing package bytes.
            $path = JPATH_ADMINISTRATOR . '/language/' . $tag . '/plg_jem_mailer.ini';
            self::$languageFiles[$path] = is_file($path) ? file_get_contents($path) : null;
            if (!is_dir(dirname($path))) {
                throw new RuntimeException('Install the Joomla en-GB and es-ES languages in the test site first.');
            }
            file_put_contents($path, file_get_contents(JEM_TEST_ROOT . '/plugins/plg_jem_mailer/language/en-GB/plg_jem_mailer.ini'));
        }
    }

    /** Restore the site's language files byte for byte. */
    public static function tearDownAfterClass(): void
    {
        self::restoreLanguageFiles();
        Factory::getApplication()->setSession(self::$originalSession);
    }

    /** Restore temporary language packages on normal completion or a failed process. */
    public static function restoreLanguageFiles(): void
    {
        foreach (self::$languageFiles as $path => $bytes) {
            if ($bytes === null) {
                unlink($path);
            } else {
                file_put_contents($path, $bytes);
            }
        }
        self::$languageFiles = [];
    }

    /** Create disposable fixtures inside a transaction and intercept Joomla's mail boundary. */
    protected function setUp(): void
    {
        $this->db = Factory::getContainer()->get(DatabaseDriver::class);
        $this->db->transactionStart();
        try {
            $this->prepareFixtures();
        } catch (Throwable $e) {
            $this->db->transactionRollback();
            if (isset($this->originalMailer)) {
                Factory::$mailer = $this->originalMailer;
            }
            throw $e;
        }
    }

    /** Populate fixture rows and set a private in-memory recipient transport. */
    private function prepareFixtures(): void
    {
        $app = Factory::getApplication();
        $this->originalLanguage = $app->getLanguage();
        $this->originalFactoryLanguage = Factory::$language;
        $settings = JemHelper::config();
        $this->originalSettings = [$settings->formatdate, $settings->formattime, $settings->timename];
        $settings->formatdate = 'D, d. F Y';
        $settings->formattime = 'H:i';
        $settings->timename = 'h';
        $this->setRequestLanguage('es-ES');
        $this->originalUser = $app->getSession()->get('user');
        $this->originalMailer = Factory::$mailer;
        Factory::$mailer = new JemDateMailCapture();
        JemDateMailCapture::$messages = [];
        JemDateMailCapture::$fail = false;
        $name = 'jem-date-test-' . bin2hex(random_bytes(6));
        $row = (object) array('name' => 'Ana & Bob', 'username' => $name, 'email' => $name . '@example.invalid',
            'password' => '', 'block' => 0, 'params' => '{"language":"en-GB"}', 'registerDate' => '2026-01-01 00:00:00');
        $this->db->insertObject('#__users', $row, 'id');
        $mapping = (object) array('user_id' => $row->id, 'group_id' => 8);
        $this->db->insertObject('#__user_usergroup_map', $mapping);
        $user = new User($row->id);
        $app->loadIdentity($user);
        $app->getSession()->set('user', $user);
        $venue = (object) array('venue' => 'Hall & Garden', 'alias' => $name, 'published' => 1, 'city' => 'Madrid',
            'street' => '10 Main Street', 'postalCode' => '28001', 'country' => 'ES', 'url' => 'https://example.invalid',
            'created' => '2026-01-01 12:00:00', 'modified' => '2026-01-02 12:00:00', 'created_by' => $row->id,
            'locdescription' => '<p>Venue description.</p>');
        $this->db->insertObject('#__jem_venues', $venue, 'id');
        $this->venueId = (int) $venue->id;
        $event = (object) array('title' => 'Show & Learn', 'alias' => $name, 'locid' => $venue->id, 'published' => 1,
            'event_status' => 'scheduled', 'dates' => '2026-11-23', 'times' => '20:00:00',
            'created' => '2026-01-01 12:00:00', 'modified' => '2026-01-02 12:00:00', 'created_by' => $row->id,
            'introtext' => '<p>Event description.</p>', 'fulltext' => '', 'created_by_alias' => '', 'metadata' => '');
        $this->db->insertObject('#__jem_events', $event, 'id');
        $this->eventId = (int) $event->id;
        foreach (['en-GB', 'es-ES'] as $tag) {
            $recipient = (object) ['name' => $tag, 'username' => $name . $tag,
                'email' => $name . $tag . '@example.invalid', 'password' => '', 'block' => 0,
                'params' => json_encode(['language' => $tag]), 'registerDate' => '2026-01-01 00:00:00'];
            $this->db->insertObject('#__users', $recipient, 'id');
            $this->recipients[$tag] = $recipient;
        }
        $registration = (object) ['event' => $this->eventId, 'uid' => $row->id, 'status' => 1,
            'waiting' => 0, 'places' => 1, 'comment' => '', 'reference' => $name,
            'registerDate' => '2026-01-01 12:00:00'];
        $this->db->insertObject('#__jem_register', $registration, 'id');
        $this->registrationId = (int) $registration->id;
        $this->plugin = (new ReflectionClass(plgJemMailer::class))->newInstanceWithoutConstructor();
        foreach (['_SiteName' => 'Date Test Site', '_MailFrom' => 'sender@example.invalid',
            '_FromName' => 'JEM Test', '_AdminDBList' => array_column($this->recipients, 'email'),
            'params' => new Registry(['send_html' => 1, 'reg_mail_user' => 0,
                'reg_mail_user_onoff' => 0, 'unreg_mail_user' => 0])] as $key => $value) {
            (new ReflectionProperty($this->plugin, $key))->setValue($this->plugin, $value);
        }
        JemNotificationTemplateService::registerDefaults();
        $this->db->setQuery("DELETE FROM #__mail_templates WHERE extension='plg_jem_mailer' AND language IN ('en-GB','es-ES')")->execute();
    }

    /** Roll back all templates, users and content and restore the previous transport and identity. */
    protected function tearDown(): void
    {
        Factory::$mailer = $this->originalMailer;
        Factory::getApplication()->getSession()->set('user', $this->originalUser);
        Factory::getApplication()->loadIdentity($this->originalUser instanceof User ? $this->originalUser : new User());
        $this->db->transactionRollback();
        $app = Factory::getApplication();
        $app->loadLanguage($this->originalLanguage);
        Factory::$language = $this->originalFactoryLanguage;
        $settings = JemHelper::config();
        [$settings->formatdate, $settings->formattime, $settings->timename] = $this->originalSettings;
    }

    /** @return iterable<string,array{string,string}> Real hooks under both request languages. */
    public static function hooks(): iterable
    {
        foreach (['en-GB', 'es-ES'] as $request) {
            foreach (['registration', 'waiting', 'promoted', 'cancelled', 'unregistered'] as $action) {
                yield $request . ' ' . $action => [$request, $action];
            }
        }
    }

    /** @param string $request Request language. @param string $action Mailer hook. @return void */
    #[DataProvider('hooks')]
    public function testHooksFormatEachRecipientBeforePersistence(string $request, string $action): void
    {
        $this->setRequestLanguage($request);
        if ($action === 'waiting') {
            $this->db->setQuery('UPDATE #__jem_register SET waiting=1 WHERE id=' . $this->registrationId)->execute();
        } elseif ($action === 'cancelled') {
            $this->db->setQuery('UPDATE #__jem_register SET status=-1 WHERE id=' . $this->registrationId)->execute();
        }
        $before = Factory::getApplication()->getLanguage();
        $factoryBefore = Factory::$language;
        $success = match ($action) {
            'waiting', 'promoted' => $this->plugin->onUserOnOffWaitinglist($this->registrationId),
            'unregistered' => $this->plugin->onEventUserUnregistered($this->eventId,
                $this->db->setQuery('SELECT * FROM #__jem_register WHERE id=' . $this->registrationId)->loadObject(),
                $this->registrationId),
            default => $this->plugin->onEventUserRegistered($this->registrationId),
        };
        self::assertTrue($success);
        self::assertCount(2, JemDateMailCapture::$messages);
        self::assertCount(2, $this->notifications());
        foreach ($this->notifications() as $row) {
            $expected = $this->expectedDate($row->resolved_language);
            self::assertStringContainsString($expected, $row->body);
            $values = json_decode($row->payload_json, true)['values'];
            self::assertSame($expected, $values['event_date']);
            self::assertSame('20:00 h', $values['event_time']);
        }
        foreach (JemDateMailCapture::$messages as $message) {
            $tag = $message->recipient === $this->recipients['en-GB']->email ? 'en-GB' : 'es-ES';
            self::assertStringContainsString($this->expectedDate($tag), $message->AltBody);
            self::assertStringContainsString($this->expectedDate($tag), $message->Body);
        }
        self::assertSame($before, Factory::getApplication()->getLanguage());
        self::assertSame($factoryBefore, Factory::$language);
        self::assertSame('2026-11-23', $this->db->setQuery('SELECT dates FROM #__jem_events WHERE id=' . $this->eventId)->loadResult());
    }

    /** Attendee notifications also follow the account language when a different request initiates them. */
    public function testAttendeeLanguageIsIndependentOfTheRequest(): void
    {
        $this->db->setQuery('UPDATE #__jem_register SET waiting=1 WHERE id=' . $this->registrationId)->execute();
        $userId = (int) $this->db->setQuery('SELECT uid FROM #__jem_register WHERE id=' . $this->registrationId)->loadResult();
        foreach (['en-GB' => 'es-ES', 'es-ES' => 'en-GB'] as $request => $recipient) {
            $this->setRequestLanguage($request);
            $this->db->setQuery('UPDATE #__users SET params=' . $this->db->quote(json_encode(['language' => $recipient]))
                . ' WHERE id=' . $userId)->execute();
            JemDateMailCapture::$messages = [];
            self::assertTrue($this->plugin->onEventUserRegistered($this->registrationId, false, true, ['force_unique' => true]));
            self::assertCount(1, JemDateMailCapture::$messages);
            self::assertStringContainsString($this->expectedDate($recipient), JemDateMailCapture::$messages[0]->AltBody);
            $rows = $this->notifications();
            $last = end($rows);
            self::assertSame('user', $last->recipient_type);
            self::assertSame($recipient, $last->resolved_language);
            self::assertSame($this->expectedDate($recipient), json_decode($last->payload_json, true)['values']['event_date']);
            self::assertSame($request, Factory::getApplication()->getLanguage()->getTag());
        }
    }

    /** Custom subject, plain text and HTML share the date resolved for that recipient. */
    public function testCustomTemplatesAndMissingRecipientLanguage(): void
    {
        $id = JemNotificationTemplateCatalog::templateIdForBodyKey('PLG_JEM_MAILER_ADMIN_REG_BODY_8');
        $model = new JemModelNotificationtemplate(['dbo' => $this->db]);
        foreach (['en-GB', 'es-ES'] as $tag) {
            $model->save(['template_id' => $id, 'language' => $tag,
                'subject' => '{event_date}', 'body' => '{event_date} {event_time}',
                'htmlbody' => '<p>{event_date} {event_time}</p>']);
        }
        self::assertTrue($this->plugin->onEventUserRegistered($this->registrationId));
        foreach (JemDateMailCapture::$messages as $message) {
            $tag = $message->recipient === $this->recipients['en-GB']->email ? 'en-GB' : 'es-ES';
            self::assertSame($this->expectedDate($tag), $message->subject);
            self::assertStringStartsWith($this->expectedDate($tag) . ' 20:00 h', $message->AltBody);
            self::assertStringContainsString($this->expectedDate($tag), $message->Body);
        }
        $recipient = $this->recipients['es-ES'];
        $this->db->setQuery('UPDATE #__users SET params=' . $this->db->quote('{"language":"zz-ZZ"}') . ' WHERE id=' . $recipient->id)->execute();
        $row = $this->notifications()[1];
        self::assertTrue($this->plugin->onJemNotificationAction($row->id, 'resend'));
        $rows = $this->notifications();
        $last = end($rows);
        self::assertSame('zz-ZZ', $last->requested_language);
        self::assertSame('en-GB', $last->resolved_language);
        self::assertSame($this->expectedDate('en-GB'), $last->subject);
    }

    /** A retry uses the immutable message, while a new resend uses current recipient preferences. */
    public function testRetryPreservesSnapshotAndResendRefreshesDateLanguage(): void
    {
        JemDateMailCapture::$fail = true;
        self::assertFalse($this->plugin->onEventUserRegistered($this->registrationId));
        $row = $this->notifications()[0];
        self::assertSame('en-GB', $row->resolved_language);
        $this->db->setQuery('UPDATE #__users SET params=' . $this->db->quote('{"language":"es-ES"}')
            . ' WHERE id=' . $this->recipients['en-GB']->id)->execute();
        JemDateMailCapture::$fail = false;
        self::assertTrue($this->plugin->onJemNotificationAction($row->id, 'retry'));
        self::assertSame($row->body, end(JemDateMailCapture::$messages)->AltBody);
        self::assertTrue($this->plugin->onJemNotificationAction($row->id, 'resend'));
        self::assertStringContainsString($this->expectedDate('es-ES'), end(JemDateMailCapture::$messages)->AltBody);
        self::assertSame($row->body, $this->notifications()[0]->body);
    }

    /** Reminder snapshots use recipient dates without changing their UTC delivery schedule. */
    public function testRemindersUseBothRecipientLanguages(): void
    {
        $service = new JemReminderService($this->db);
        $method = new ReflectionMethod($service, 'createReminder');
        $before = Factory::getApplication()->getLanguage();
        foreach ($this->recipients as $tag => $recipient) {
            $registration = (object) ['user_id' => $recipient->id, 'user_email' => $recipient->email,
                'user_name' => $recipient->name, 'dates' => '2026-11-23', 'times' => '20:00:00',
                'event_title' => 'Date test', 'venue' => '', 'city' => '', 'places' => 1, 'event_description' => '',
                'event_id' => $this->eventId, 'registration_id' => $this->registrationId,
                'registration_reference' => 'date-test', 'registration_revision' => 1, 'modified' => '2026-01-01 12:00:00'];
            $result = $method->invoke($service, $registration, (object) ['id' => 0, 'title' => 'One day', 'code' => 'test'],
                '2099-11-22 19:00:00', false);
            self::assertNotEmpty($result);
        }
        $rows = $this->notifications();
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            self::assertStringContainsString($this->expectedDate($row->resolved_language), $row->body);
            self::assertSame('2099-11-22 19:00:00', $row->scheduled_at);
            self::assertSame('20:00 h', json_decode($row->payload_json, true)['values']['event_time']);
        }
        self::assertSame($before, Factory::getApplication()->getLanguage());
        self::assertCount(0, JemDateMailCapture::$messages);
    }

    /** Event submission remains recipient-specific after sharing the date formatter. */
    public function testEventSubmissionDateRemainsCorrect(): void
    {
        self::assertTrue($this->plugin->onEventEdited($this->eventId, false));
        self::assertNotEmpty(JemDateMailCapture::$messages);
        foreach (JemDateMailCapture::$messages as $message) {
            $tag = $message->recipient === $this->recipients['es-ES']->email ? 'es-ES' : 'en-GB';
            self::assertStringContainsString($this->expectedDate($tag), $message->AltBody);
        }
    }

    /** Display configuration is preserved; no timezone conversion or invented date/time is introduced. */
    public function testNumericDatesEmptyTimesAndTimezoneBoundaries(): void
    {
        $settings = JemHelper::config();
        $settings->formatdate = 'd/m/Y';
        $settings->formattime = 'H:i:s';
        $settings->timename = '';
        $timezone = date_default_timezone_get();
        try {
            foreach (['Pacific/Auckland', 'America/Los_Angeles', 'Europe/Madrid'] as $zone) {
                date_default_timezone_set($zone);
                foreach (['en-GB', 'es-ES'] as $tag) {
                    self::assertSame(['event_date' => '25/10/2026', 'event_time' => '00:05:00'],
                        JemNotificationTemplateService::formatEventDateTime('2026-10-25', '00:05:00', $tag));
                    self::assertSame(['event_date' => '23/11/2026', 'event_time' => ''],
                        JemNotificationTemplateService::formatEventDateTime('2026-11-23', null, $tag));
                    self::assertSame(['event_date' => '', 'event_time' => ''],
                        JemNotificationTemplateService::formatEventDateTime(null, null, $tag));
                }
            }
        } finally {
            date_default_timezone_set($timezone);
        }
    }

    /** Failed formatting cannot leak the recipient language into the application or Joomla factory. */
    public function testFormattingFailureRestoresBothLanguageObjects(): void
    {
        $before = Factory::getApplication()->getLanguage();
        $factoryBefore = Factory::$language;
        JemHelper::config()->formatdate = ['invalid-format'];
        try {
            JemNotificationTemplateService::formatEventDateTime('2026-11-23', '20:00:00', 'en-GB');
            self::fail('An invalid date format must raise an error.');
        } catch (TypeError $e) {
            self::assertSame($before, Factory::getApplication()->getLanguage());
            self::assertSame($factoryBefore, Factory::$language);
        }
    }

    /** @param string $tag Request language to simulate. @return void */
    private function setRequestLanguage(string $tag): void
    {
        $language = new Language($tag);
        $language->load('joomla', JPATH_SITE, $tag, true, false);
        Factory::getApplication()->loadLanguage($language);
        Factory::$language = $language;
    }

    /** @param string $tag Recipient language. @return string Independently expected calendar translation. */
    private function expectedDate(string $tag): string
    {
        return $tag === 'es-ES' ? 'Lun, 23. Noviembre 2026' : 'Mon, 23. November 2026';
    }

    /** @return object[] Only the notifications from the current disposable registration. */
    private function notifications(): array
    {
        return $this->db->setQuery('SELECT * FROM #__jem_notifications WHERE registration_id='
            . $this->registrationId . ' ORDER BY id')->loadObjectList();
    }

}

/** Capture the real plugin's prepared messages; this object cannot send email. */
final class JemDateMailCapture
{
    public static array $messages = [];
    public static bool $fail = false;
    public string $Body = '';
    public string $AltBody = '';
    public string $Encoding = '';
    public string $subject = '';
    public string $recipient = '';

    /** @param array $sender Sender. @return void */
    public function setSender(array $sender): void
    {
    }
    /** @param string $subject Subject. @return void */
    public function setSubject(string $subject): void
    {
        $this->subject = $subject;
    }
    /** @param string $body Body. @return void */
    public function setBody(string $body): void
    {
        $this->Body = $body;
    }
    /** @param bool $html Selected mail mode. @return void */
    public function isHTML(bool $html): void
    {
    }
    /** @param string $recipient Destination. @return void */
    public function addRecipient(string $recipient): void
    {
        $this->recipient = $recipient;
    }
    /** @return bool Capture only, with an optional simulated transport failure. */
    public function send(): bool
    {
        self::$messages[] = clone $this;
        return !self::$fail;
    }
}
