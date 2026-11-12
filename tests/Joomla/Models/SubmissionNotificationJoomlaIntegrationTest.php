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
final class SubmissionNotificationJoomlaIntegrationTest extends JoomlaTestCase
{
    private static array $languageFiles = [];
    private static mixed $originalSession;
    private DatabaseDriver $db;
    private object $plugin;
    private mixed $originalMailer;
    private mixed $originalUser;
    private int $eventId;
    private int $venueId;

    /** Load candidate classes and temporarily expose their English defaults and a second language. */
    public static function setUpBeforeClass(): void
    {
        self::bootJoomlaSite();
        self::$originalSession = Factory::getApplication()->getSession();
        Factory::getApplication()->setSession(Factory::getContainer()->get('session.cli'));
        self::assertSame(
            hash_file('sha256', JEM_TEST_ROOT . '/site/classes/notificationtemplatecatalog.class.php'),
            hash_file('sha256', JPATH_SITE . '/components/com_jem/classes/notificationtemplatecatalog.class.php'),
            'Test the candidate installation, not an older installed catalogue.'
        );
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
        $app->loadLanguage(Language::getInstance('en-GB'));
        $this->originalUser = $app->getSession()->get('user');
        $this->originalMailer = Factory::$mailer;
        Factory::$mailer = new JemSubmissionMailCapture();
        JemSubmissionMailCapture::$messages = [];
        JemSubmissionMailCapture::$fail = false;
        $name = 'jem-template-test-' . bin2hex(random_bytes(6));
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
            'event_status' => 'scheduled', 'dates' => '2099-06-15', 'times' => '18:00:00',
            'created' => '2026-01-01 12:00:00', 'modified' => '2026-01-02 12:00:00', 'created_by' => $row->id,
            'introtext' => '<p>Event description.</p>', 'fulltext' => '', 'created_by_alias' => '', 'metadata' => '');
        $this->db->insertObject('#__jem_events', $event, 'id');
        $this->eventId = (int) $event->id;
        $this->plugin = (new ReflectionClass(plgJemMailer::class))->newInstanceWithoutConstructor();
        foreach (array('_SiteName' => 'Template Test Site', '_MailFrom' => 'sender@example.invalid',
            '_FromName' => 'JEM Test', '_AdminDBList' => array('admin@example.invalid'), 'params' => new Registry(array('send_html' => 1))) as $key => $value) {
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
    }

    /** @return iterable<string,array{string,bool}> Four actions, each notifying user and administrators. */
    public static function actions(): iterable
    {
        foreach (array('event', 'venue') as $kind) {
            yield $kind . ' new' => array($kind, true);
            yield $kind . ' edited' => array($kind, false);
        }
    }

    /** @param string $kind Content kind. @param bool $new Creation or edit. @return void */
    #[DataProvider('actions')]
    public function testActualHooksDeliverBothDefaultMessages(string $kind, bool $new): void
    {
        $before = Factory::getApplication()->getLanguage();
        $this->invokeAction($kind, $new);
        self::assertCount(2, JemSubmissionMailCapture::$messages);
        foreach (JemSubmissionMailCapture::$messages as $mail) {
            self::assertStringContainsString('Template Test Site', $mail->subject);
            self::assertStringContainsString($kind === 'event' ? 'Show &amp; Learn' : 'Hall &amp; Garden', $mail->Body);
            self::assertStringContainsString('Ana & Bob', $mail->AltBody);
            self::assertSame([], JemNotificationTemplateRenderer::extractVariables($mail->Body));
            self::assertStringNotContainsString('%s', $mail->Body);
            self::assertMatchesRegularExpression($new ? '/submission|submitted|added/i' : '/edited/i', $mail->AltBody);
            self::assertStringNotContainsString('PLG_JEM_MAILER_', $mail->Body);
        }
        self::assertSame($before, Factory::getApplication()->getLanguage());
    }

    /** @param string $kind Content kind. @param bool $new Creation or edit. @return void */
    #[DataProvider('actions')]
    public function testCustomSubjectTextAndHtmlReachTheActualHooks(string $kind, bool $new): void
    {
        $model = new JemModelNotificationtemplate(array('dbo' => $this->db));
        foreach (JemNotificationTemplateCatalog::all() as $definition) {
            if ($definition['workflow'] !== $kind . '_submission' || $definition['variant'] !== ($new ? 'new' : 'edit')) {
                continue;
            }
            $title = $kind === 'event' ? 'event_title' : 'venue';
            $model->save(array('template_id' => $definition['id'], 'language' => 'en-GB',
                'subject' => 'Custom {' . $title . '}', 'body' => '{user_name}: {' . $title . '} / {publication_status}',
                'htmlbody' => '<p>Custom {user_name}: {' . $title . '}</p>'));
        }
        $this->invokeAction($kind, $new);
        self::assertCount(2, JemSubmissionMailCapture::$messages);
        foreach (JemSubmissionMailCapture::$messages as $mail) {
            self::assertStringStartsWith('Custom ', $mail->subject);
            self::assertStringContainsString('<p>Custom Ana &amp; Bob:', $mail->Body);
            self::assertStringStartsWith('Ana & Bob:', $mail->AltBody);
        }
    }

    /** The editor preserves another language and custom bodies across repeated default registration. */
    public function testEditorSaveReloadResetAndDefaultRefreshAreLanguageSpecific(): void
    {
        $model = new JemModelNotificationtemplate(array('dbo' => $this->db));
        $id = JemNotificationTemplateCatalog::templateIdForBodyKey('PLG_JEM_MAILER_USER_MAIL_NEW_EVENT_9');
        foreach (array('en-GB', 'es-ES') as $language) {
            $model->save(array('template_id' => $id, 'language' => $language, 'subject' => $language . ' {event_title}',
                'body' => '{user_name}: {event_title} {publication_status}', 'htmlbody' => '<p>{event_title}</p>'));
        }
        JemNotificationTemplateService::registerDefaults();
        JemNotificationTemplateService::registerDefaults();
        self::assertSame('en-GB {event_title}', $model->getItem($id, 'en-GB')->subject);
        self::assertSame('es-ES {event_title}', $model->getItem($id, 'es-ES')->subject);
        $model->reset($id, 'en-GB');
        self::assertFalse($model->getItem($id, 'en-GB')->customized);
        self::assertTrue($model->getItem($id, 'es-ES')->customized);
        self::assertSame('es-ES {event_title}', $model->getItem($id, 'es-ES')->subject);
        self::assertSame(59, (int) $this->db->setQuery("SELECT COUNT(*) FROM #__mail_templates WHERE extension='plg_jem_mailer' AND language=''")->loadResult());
    }

    /** Each recipient gets their own template; an unavailable preferred language falls back. */
    public function testRecipientLanguageAndFallbackRemainSeparateFromTheRequest(): void
    {
        $user = Factory::getApplication()->getIdentity();
        $this->db->setQuery('UPDATE #__users SET params=' . $this->db->quote('{"language":"es-ES"}') . ' WHERE id=' . (int) $user->id)->execute();
        $model = new JemModelNotificationtemplate(array('dbo' => $this->db));
        foreach (array('USER_MAIL_NEW_EVENT_9', 'NEW_EVENT_9') as $key) {
            $model->save(array('template_id' => JemNotificationTemplateCatalog::templateIdForBodyKey('PLG_JEM_MAILER_' . $key),
                'language' => 'es-ES', 'subject' => 'ES {event_title}', 'body' => 'Hola {user_name}: {event_title} {publication_status}', 'htmlbody' => ''));
        }
        $this->invokeAction('event', true);
        self::assertStringStartsWith('ES ', JemSubmissionMailCapture::$messages[0]->subject);
        self::assertStringNotContainsString('ES ', JemSubmissionMailCapture::$messages[1]->subject);
        self::assertSame('en-GB', Factory::getApplication()->getLanguage()->getTag());
        $this->db->setQuery('UPDATE #__users SET params=' . $this->db->quote('{"language":"zz-ZZ"}') . ' WHERE id=' . (int) $user->id)->execute();
        JemSubmissionMailCapture::$messages = [];
        $this->invokeAction('event', true);
        self::assertStringNotContainsString('ES ', JemSubmissionMailCapture::$messages[0]->subject);
        self::assertCount(2, JemSubmissionMailCapture::$messages);
    }

    /** Image-present paths use Joomla Uri and online/no-venue events remain valid. */
    public function testExistingImagesAndAnEventWithoutVenue(): void
    {
        $paths = glob(JPATH_SITE . '/images/jem/events/*.*');
        self::assertNotEmpty($paths, 'Install JEM sample images in the test site.');
        $relative = substr(str_replace('\\', '/', $paths[0]), strlen(str_replace('\\', '/', JPATH_SITE)) + 1);
        $this->db->setQuery('UPDATE #__jem_events SET locid=0, datimage=' . $this->db->quote($relative) . ' WHERE id=' . $this->eventId)->execute();
        $method = new ReflectionMethod($this->plugin, '_notificationImageUrl');
        self::assertStringContainsString('/images/jem/events/', $method->invoke($this->plugin, $relative, 'event'));
        self::assertSame('', $method->invoke($this->plugin, 'missing-image-1881.png', 'event'));
        $this->invokeAction('event', true);
        self::assertCount(2, JemSubmissionMailCapture::$messages);
        self::assertStringContainsString('Show & Learn', JemSubmissionMailCapture::$messages[0]->AltBody);
        $this->db->setQuery('UPDATE #__jem_venues SET locimage=' . $this->db->quote($relative) . ' WHERE id=' . $this->venueId)->execute();
        JemSubmissionMailCapture::$messages = [];
        $this->invokeAction('venue', true);
        self::assertCount(2, JemSubmissionMailCapture::$messages);
    }

    /** A failed delivery always restores the original request language. */
    public function testTransportFailureRestoresLanguage(): void
    {
        JemSubmissionMailCapture::$fail = true;
        $language = Factory::getApplication()->getLanguage();
        $this->invokeAction('event', true);
        self::assertSame($language, Factory::getApplication()->getLanguage());
        self::assertCount(2, JemSubmissionMailCapture::$messages);
    }

    /** Existing positional overrides still render; malformed stored customisations use the named defaults. */
    public function testLegacyOverrideAndInvalidStoredOverrideFallback(): void
    {
        $id = JemNotificationTemplateCatalog::templateIdForBodyKey('PLG_JEM_MAILER_USER_MAIL_NEW_EVENT_9');
        $row = (object) array('template_id' => $id, 'extension' => 'plg_jem_mailer', 'language' => 'en-GB',
            'subject' => 'Legacy %2$s / %1$s', 'body' => 'Hello %1$s / %3$s', 'htmlbody' => '', 'attachments' => '', 'params' => '{}');
        $this->db->insertObject('#__mail_templates', $row);
        $this->invokeAction('event', true);
        self::assertSame('Legacy Show & Learn / Template Test Site', JemSubmissionMailCapture::$messages[0]->subject);
        self::assertStringStartsWith('Hello Ana & Bob / Show & Learn', JemSubmissionMailCapture::$messages[0]->AltBody);
        $this->db->setQuery('UPDATE #__mail_templates SET body=' . $this->db->quote('{unknown_variable}')
            . ' WHERE template_id=' . $this->db->quote($id) . " AND language='en-GB'")->execute();
        JemSubmissionMailCapture::$messages = [];
        $this->invokeAction('event', true);
        self::assertStringContainsString('successfully received your submission', JemSubmissionMailCapture::$messages[0]->AltBody);
        self::assertStringNotContainsString('{unknown_variable}', JemSubmissionMailCapture::$messages[0]->Body);
    }

    /** New customisations reject legacy and unknown variables without replacing the saved text. */
    public function testEditorRejectsUnknownAndPositionalVariables(): void
    {
        $model = new JemModelNotificationtemplate(array('dbo' => $this->db));
        $id = JemNotificationTemplateCatalog::templateIdForBodyKey('PLG_JEM_MAILER_USER_MAIL_NEW_EVENT_9');
        foreach (array('Hello %s', 'Hello {not_available}', '') as $body) {
            try {
                $model->save(array('template_id' => $id, 'language' => 'en-GB', 'subject' => '{event_title}', 'body' => $body));
                self::fail('Invalid custom template must not be saved.');
            } catch (InvalidArgumentException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
        self::assertFalse($model->getItem($id, 'en-GB')->customized);
    }

    /** Disabled recipient settings still suppress all submission messages. */
    public function testDisabledSubmissionNotificationsDoNotSend(): void
    {
        (new ReflectionProperty($this->plugin, 'params'))->setValue($this->plugin, new Registry(array(
            'newevent_mail_user' => 0, 'newevent_mail_admin' => 0,
            'newvenue_mail_user' => 0, 'newvenue_mail_admin' => 0,
        )));
        $this->invokeAction('event', true);
        $this->invokeAction('venue', true);
        self::assertSame([], JemSubmissionMailCapture::$messages);
    }

    /** Event lifecycle wording remains present when an edited event is postponed. */
    public function testPostponedEventRetainsTranslatedLifecycleInformation(): void
    {
        $this->db->setQuery("UPDATE #__jem_events SET event_status='postponed' WHERE id=" . $this->eventId)->execute();
        $this->invokeAction('event', false);
        foreach (JemSubmissionMailCapture::$messages as $mail) {
            self::assertStringContainsString('Event status: Postponed', $mail->AltBody);
        }
    }

    /** A user without the dedicated permission cannot reach template changes. */
    public function testEditorPermissionDeniesGuest(): void
    {
        Factory::getApplication()->getSession()->set('user', new User());
        Factory::getApplication()->loadIdentity(new User());
        $controller = new JemControllerNotificationtemplate(array('base_path' => JPATH_ADMINISTRATOR . '/components/com_jem'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(403);
        (new ReflectionMethod($controller, 'assertAccess'))->invoke($controller);
    }

    /** @param string $kind Content kind. @param bool $new New or edited. @return void */
    private function invokeAction(string $kind, bool $new): void
    {
        self::assertTrue($kind === 'event'
            ? $this->plugin->onEventEdited($this->eventId, $new)
            : $this->plugin->onVenueEdited($this->venueId, $new));
    }
}

/** Capture the real plugin's prepared messages; this object cannot send email. */
final class JemSubmissionMailCapture
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
