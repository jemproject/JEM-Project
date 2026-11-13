<?php
declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\User\User;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

/** Candidate source with native Joomla and rollback-only fixtures; no transport or installed-file writes. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class OnlineMeetingJoomlaTest extends JoomlaTestCase
{
    private object $db;
    private object $app;
    private int $eventId;
    private int $userId;
    private const SECRET = 'https://meet.example.invalid/join?synthetic=JEM2530PRIVATE';

    protected function setUp(): void
    {
        self::bootJoomlaSite();
        $this->app = Factory::getApplication();
        $this->app->setSession(Factory::getContainer()->get('session.cli'));
        $this->app->loadIdentity(new User(0));
        $this->app->getSession()->set('user', $this->app->getIdentity());
        $this->app->loadLanguage(new Language('en-GB'));
        Factory::$language = $this->app->getLanguage();
        $this->app->loadDocument();
        require_once JEM_TEST_ROOT . '/site/helpers/helper.php';
        require_once JEM_TEST_ROOT . '/site/helpers/route.php';
        require_once JEM_TEST_ROOT . '/site/classes/attachment.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/output.class.php';
        self::assertSame(realpath(JEM_TEST_ROOT . '/site/helpers/helper.php'), realpath((new ReflectionClass(JemHelper::class))->getFileName()));
        JemHelper::globalattribs()->set('event_show_online_meeting', 1);
        $this->db = Factory::getContainer()->get('DatabaseDriver');
        $this->db->transactionStart();
        $this->eventId = (int) $this->db->setQuery('SELECT e.id FROM #__jem_events e'
            . ' INNER JOIN #__jem_cats_event_relations r ON r.itemid=e.id'
            . ' INNER JOIN #__jem_categories c ON c.id=r.catid WHERE c.published=1 AND c.access=1 ORDER BY e.id', 0, 1)->loadResult();
        self::assertGreaterThan(0, $this->eventId, 'Needs one local event in a public category.');
        $this->userId = (int) $this->db->setQuery('SELECT id FROM #__users WHERE block=0 ORDER BY id', 0, 1)->loadResult();
        self::assertGreaterThan(0, $this->userId);
        $this->db->setQuery('UPDATE #__jem_events SET published=1, access=1, publish_up=NULL, publish_down=NULL,'
            . " event_status='scheduled',locid=0,type_id=NULL,age_level_id=NULL,parent_event_id=NULL,recurrence_first_id=0,"
            . " recurrence_type='0',online_meeting_url=" . $this->db->quote(self::SECRET) . ' WHERE id=' . $this->eventId)->execute();
        $this->db->setQuery('DELETE FROM #__jem_register WHERE event=' . $this->eventId)->execute();
        $this->db->setQuery('DELETE FROM #__jem_links WHERE event_id=' . $this->eventId)->execute();
    }

    protected function tearDown(): void
    {
        if (isset($this->db)) {
            $this->db->transactionRollback();
        }
    }

    private function policy(int $access, int $registered): void
    {
        $this->db->setQuery('UPDATE #__jem_events SET attribs=' . $this->db->quote(json_encode([
            'online_meeting_access' => $access, 'online_meeting_registered' => $registered])) . ' WHERE id=' . $this->eventId)->execute();
    }

    private function item(): object
    {
        require_once JEM_TEST_ROOT . '/site/models/event.php';
        $model = new JemModelEvent(['ignore_request' => true]);
        $item = $model->getItem($this->eventId);
        self::assertIsObject($item, (string) $model->getError());
        return $item;
    }

    private function login(): void
    {
        $user = Factory::getContainer()->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($this->userId);
        $this->app->loadIdentity($user);
        $this->app->getSession()->set('user', $user);
    }

    private function register(int $uid, int $status = 1, int $waiting = 0): object
    {
        $row = (object) ['event' => $this->eventId, 'uid' => $uid, 'status' => $status, 'waiting' => $waiting,
            'places' => 1, 'revision' => 1, 'reference' => 'JEM2530TEST', 'invitation_key' => 'jem2530-synthetic'];
        $this->db->insertObject('#__jem_register', $row, 'id');
        return $row;
    }

    public function testGuestAndConfirmedModelNeverExposePrivateCredential(): void
    {
        $this->policy(1, 0);
        self::assertSame(self::SECRET, JemHelper::getOnlineMeetingUrl($this->item()));
        $this->policy(1, 1);
        $guest = $this->item();
        self::assertTrue($guest->jem_private_meeting);
        self::assertSame('', $guest->online_meeting_url);
        self::assertSame('', $guest->jem_meeting_join_url);
        $this->login();
        self::assertSame('', $this->item()->jem_meeting_join_url);
        $row = $this->register($this->userId, 1, 1);
        self::assertSame('', $this->item()->jem_meeting_join_url);
        $this->db->setQuery('UPDATE #__jem_register SET waiting=0 WHERE id=' . (int) $row->id)->execute();
        $confirmed = $this->item();
        self::assertNotSame('', $confirmed->jem_meeting_join_url);
        self::assertStringNotContainsString('JEM2530PRIVATE', json_encode($confirmed));
        self::assertSame('', JemHelper::getOnlineMeetingUrl($confirmed));
        $this->db->setQuery('UPDATE #__jem_register SET status=-1,places=0 WHERE id=' . (int) $row->id)->execute();
        self::assertSame('', $this->item()->jem_meeting_join_url);
    }

    public function testPublicFeedAndIcsFallbackNeverUsePrivateOnlineLinks(): void
    {
        $this->policy(1, 1);
        $link = (object) ['event_id' => $this->eventId, 'type' => 'online', 'state' => 1, 'ordering' => 1,
            'title' => 'Join', 'url' => self::SECRET, 'created_by' => $this->userId, 'created' => gmdate('Y-m-d H:i:s')];
        $this->db->insertObject('#__jem_links', $link, 'id');
        $this->login();
        $this->register($this->userId);
        $item = $this->item();
        self::assertStringNotContainsString('JEM2530PRIVATE', json_encode($item->event_links));
        self::assertSame(['url' => '', 'label' => ''], JemHelper::getOnlineMeetingEventLink($item));
        require_once JEM_TEST_ROOT . '/site/classes/feed.class.php';
        $item->slug = (string) $item->id;
        $item->datimage = '';
        $document = new \Joomla\CMS\Document\FeedDocument();
        JemFeed::addEvents($document, [$item]);
        self::assertCount(1, $document->items);
        self::assertStringNotContainsString('JEM2530PRIVATE', $document->items[0]->description);
        $calendar = JemHelper::getCalendarTool();
        JemHelper::icalAddEvent($calendar, $item);
        self::assertStringNotContainsString('JEM2530PRIVATE', $calendar->createCalendar());
    }

    public function testFormDefaultsAndCheckboxRoundTrip(): void
    {
        foreach (['site', 'admin'] as $client) {
            $form = new \Joomla\CMS\Form\Form('jem2530-' . $client);
            self::assertTrue($form->loadFile(JEM_TEST_ROOT . '/' . $client . '/models/forms/event.xml'));
            $field = $form->getField('online_meeting_registered', 'attribs');
            self::assertNotNull($field);
            self::assertStringNotContainsString(' checked', $field->input);
            self::assertStringContainsString('value="1"', $field->input);
            self::assertSame('1', (string) $form->getField('online_meeting_access', 'attribs')->value);
            $form->bind(['attribs' => ['online_meeting_access' => 2, 'online_meeting_registered' => 1]]);
            self::assertStringContainsString(' checked', $form->getField('online_meeting_registered', 'attribs')->input);
            $form->bind(['attribs' => ['online_meeting_access' => 1, 'online_meeting_registered' => 0]]);
            self::assertStringNotContainsString(' checked', $form->getField('online_meeting_registered', 'attribs')->input);
        }
    }

    private function gateway(array $input): object
    {
        require_once JEM_TEST_ROOT . '/site/controllers/onlinemeeting.php';
        foreach (array_replace(['id' => $this->eventId, 'registration_id' => 0, 'meeting_key' => '', 'link_id' => 0], $input) as $key => $value) {
            $this->app->input->set($key, $value);
        }
        $controller = new JemControllerOnlinemeeting(['base_path' => JPATH_SITE . '/components/com_jem']);
        $controller->join();
        return $controller;
    }

    public function testGatewayRechecksCurrentBookingAndKeepsCredentialsOutOfCachedPages(): void
    {
        $this->policy(1, 1);
        try {
            $this->gateway([]);
            self::fail('An anonymous visitor must not receive the provider URL.');
        } catch (RuntimeException $error) {
            self::assertSame(403, $error->getCode());
        }
        $this->login();
        $row = $this->register($this->userId);
        $controller = $this->gateway([]);
        self::assertSame(self::SECRET, (new ReflectionProperty($controller, 'redirect'))->getValue($controller));
        $headers = array_column($this->app->getHeaders(), 'value', 'name');
        self::assertSame('no-store, private', $headers['Cache-Control']);
        self::assertSame('no-referrer', $headers['Referrer-Policy']);
        $this->db->setQuery('UPDATE #__jem_register SET status=-1,places=0 WHERE id=' . (int) $row->id)->execute();
        try {
            $this->gateway([]);
            self::fail('A formerly confirmed reservation no longer grants access.');
        } catch (RuntimeException $error) {
            self::assertSame(403, $error->getCode());
        }
    }

    public function testExternalGatewayCannotBorrowTheLoggedInUsersAcl(): void
    {
        $this->policy(1, 1);
        $row = $this->register(0);
        $key = JemOnlineMeeting::guestKey($row, (string) $this->app->get('secret'));
        $this->login();
        $controller = $this->gateway(['registration_id' => (int) $row->id, 'meeting_key' => $key]);
        self::assertSame(self::SECRET, (new ReflectionProperty($controller, 'redirect'))->getValue($controller));
        self::assertSame($this->userId, (int) $this->app->getIdentity()->id);
        self::assertSame($this->userId, (int) $this->app->getSession()->get('user')->id);
        $this->policy(2, 1);
        try {
            $this->gateway(['registration_id' => (int) $row->id, 'meeting_key' => $key]);
            self::fail('An external capability must not inherit a logged-in administrator access level.');
        } catch (RuntimeException $error) {
            self::assertSame(403, $error->getCode());
        }
        self::assertSame($this->userId, (int) $this->app->getIdentity()->id);
    }

    public function testQueuedMessagesWithOldPublicCredentialAreNotDeliverableAfterRestriction(): void
    {
        $service = new JemNotificationService();
        $method = new ReflectionMethod($service, 'meetingSnapshotStillPublic');
        $message = (object) ['event_id' => $this->eventId, 'body' => self::SECRET];
        $this->policy(1, 0);
        self::assertTrue($method->invoke($service, $message));
        $this->policy(1, 1);
        self::assertFalse($method->invoke($service, $message));
        $message->body = JemOnlineMeeting::route($this->eventId);
        self::assertTrue($method->invoke($service, $message));
    }

    public static function frontendLayouts(): array
    {
        return ['default' => [0], 'responsive' => [1]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('frontendLayouts')]
    public function testBothFrontendLayoutsShowNoticeWithoutThePrivateUrl(int $style): void
    {
        $this->policy(1, 1);
        foreach (['option' => 'com_jem', 'view' => 'event', 'id' => $this->eventId, 'task' => '', 'layout' => 'default'] as $key => $value) {
            $this->app->input->set($key, $value);
        }
        $this->app->getLanguage()->load('com_jem', JEM_TEST_ROOT . '/site', 'en-GB', true, false);
        // The legacy Joomla dispatcher requires the entrypoint once per request.
        // Each layout therefore runs in its own native request/process.
            JemHelper::config()->layoutstyle = $style;
            ob_start();
            $returned = \Joomla\CMS\Component\ComponentHelper::renderComponent('com_jem');
            $html = ob_get_clean() . $returned;
            self::assertStringContainsString('jem-online-meeting-notice', $html, json_encode($this->app->getMessageQueue()));
            self::assertStringNotContainsString('JEM2530PRIVATE', $html);
            self::assertStringContainsString('Joining details are restricted.', $html);
            self::assertStringNotContainsString('JEM2530PRIVATE', json_encode($this->app->getDocument()->getHeadData()));
    }

    public function testPublicPdfOmitsTheCredentialEvenForAConfirmedParticipant(): void
    {
        $this->policy(1, 1);
        $this->login();
        $this->register($this->userId);
        if (!defined('JPATH_COMPONENT_SITE')) {
            define('JPATH_COMPONENT_SITE', JPATH_SITE . '/components/com_jem');
        }
        require_once JEM_TEST_ROOT . '/site/classes/pdf.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/image.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/pdfimagepolicy.class.php';
        require_once JEM_TEST_ROOT . '/site/classes/pdfview.class.php';
        require_once JEM_TEST_ROOT . '/site/views/event/view.raw.php';
        $view = new JemViewEvent(['base_path' => JPATH_COMPONENT_SITE]);
        $item = $this->item();
        $item->slug = (string) $item->id;
        $html = (new ReflectionMethod($view, 'buildEventPdfHtml'))->invoke($view, $item);
        self::assertStringNotContainsString('JEM2530PRIVATE', $html);
        self::assertNotSame('', $html);
    }

    public function testCatalogKeepsPrivatePolicyWithExplicitCredentialAndSafeGenericLink(): void
    {
        $this->policy(2, 1);
        $this->db->setQuery("UPDATE #__jem_events SET title='JEM2530-CATALOG' WHERE id=" . $this->eventId)->execute();
        $link = (object) ['event_id' => $this->eventId, 'type' => 'online', 'state' => 1, 'ordering' => 1,
            'title' => 'Join', 'url' => self::SECRET, 'created_by' => $this->userId, 'created' => gmdate('Y-m-d H:i:s')];
        $this->db->insertObject('#__jem_links', $link, 'id');
        if (!defined('JPATH_COMPONENT_ADMINISTRATOR')) {
            define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_jem');
        }
        require_once JEM_TEST_ROOT . '/admin/models/export.php';
        $export = new JemModelExport(['ignore_request' => true]);
        $rows = $export->getCatalogExportEvents(['search' => 'JEM2530-CATALOG'], false, 0,
            ['title', 'event_url', 'online_meeting_url']);
        self::assertCount(1, $rows);
        self::assertSame('', $rows[0]['event_url']);
        self::assertSame(self::SECRET, $rows[0]['online_meeting_url']);
        self::assertSame(['online_meeting_access' => 2, 'online_meeting_registered' => 1],
            json_decode($rows[0]['attribs'], true));
        require_once JEM_TEST_ROOT . '/admin/helpers/importsecurity.php';
        $imported = JemImportSecurityHelper::sanitiseRecord($rows[0], 'events', 1);
        self::assertSame(['online_meeting_access' => 2, 'online_meeting_registered' => 1],
            JemOnlineMeeting::settings($imported));
        $this->policy(1, 0);
        $rows = $export->getCatalogExportEvents(['search' => 'JEM2530-CATALOG'], false, 0, ['event_url']);
        self::assertSame(self::SECRET, $rows[0]['event_url']);
    }

    public function testMailerIncludesOnlyTheRevalidatedRouteForPrivateMeetings(): void
    {
        require_once JEM_TEST_ROOT . '/plugins/plg_jem_mailer/mailer.php';
        $plugin = (new ReflectionClass(plgJemMailer::class))->newInstanceWithoutConstructor();
        $this->app->getLanguage()->load('plg_jem_mailer', JEM_TEST_ROOT . '/plugins/plg_jem_mailer', 'en-GB', true, false);
        $method = new ReflectionMethod($plugin, '_appendOnlineMeetingInfo');
        $event = (object) ['id' => $this->eventId, 'online_meeting_url' => self::SECRET,
            'attribs' => ['online_meeting_registered' => 1]];
        $body = $method->invoke($plugin, 'Event link', $event);
        self::assertStringNotContainsString('JEM2530PRIVATE', $body);
        self::assertStringContainsString('onlinemeeting', $body);
        $event->online_meeting_url = '';
        self::assertSame('Event link', $method->invoke($plugin, 'Event link', $event));
        $event->online_meeting_url = self::SECRET;
        $event->attribs = [];
        self::assertStringContainsString(self::SECRET, $method->invoke($plugin, 'Event link', $event));
    }

    public function testBackendValidatesAndPreservesMeetingPolicyOnPartialSaves(): void
    {
        require_once JEM_TEST_ROOT . '/admin/models/event.php';
        $model = new JemModelEvent(['ignore_request' => true]);
        $data = ['online_meeting_url' => self::SECRET, 'attribs' => JemOnlineMeeting::DEFAULTS];
        self::assertTrue($model->validateOnlineMeetingData($data), (string) $model->getError());
        foreach ([['online_meeting_access' => 0], ['online_meeting_access' => '2xyz'],
            ['online_meeting_access' => 2147483647], ['online_meeting_registered' => 2]] as $bad) {
            $data['attribs'] = array_replace(JemOnlineMeeting::DEFAULTS, $bad);
            self::assertFalse($model->validateOnlineMeetingData($data));
        }
        $stored = ['id' => $this->eventId, 'attribs' => ['online_meeting_access' => 2, 'online_meeting_registered' => 1]];
        $method = new ReflectionMethod($model, 'prepareSeriesSubmission');
        $prepared = $method->invoke($model, ['title' => 'Other edit', 'attribs' => ['unrelated' => 'value']], $stored);
        self::assertSame(['online_meeting_access' => 2, 'online_meeting_registered' => 1], JemOnlineMeeting::settings($prepared));
        $prepared = $method->invoke($model, ['attribs' => ['online_meeting_registered' => 0]], $stored);
        self::assertSame(['online_meeting_access' => 2, 'online_meeting_registered' => 0], JemOnlineMeeting::settings($prepared));
        $table = $model->getTable();
        self::assertTrue($table->load($this->eventId));
        self::assertTrue($table->bind(['attribs' => $prepared['attribs']]));
        self::assertTrue($table->store(), (string) $table->getError());
        $table->load($this->eventId);
        self::assertSame(['online_meeting_access' => 2, 'online_meeting_registered' => 0], JemOnlineMeeting::settings($table));
    }
}
