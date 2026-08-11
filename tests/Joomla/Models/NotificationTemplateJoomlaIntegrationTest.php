<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

final class NotificationTemplateJoomlaIntegrationTest extends JoomlaTestCase
{
    private const TEST_LANGUAGE = 'zz-ZZ';

    protected function setUp(): void
    {
        self::bootJoomlaSite();
        require_once JPATH_BASE . '/components/com_jem/factory.php';
        $this->deleteTestOverrides();
    }

    protected function tearDown(): void
    {
        $this->deleteTestOverrides();
    }

    public function testNativeMasterRegistrationIsCompleteAndIdempotent(): void
    {
        JemNotificationTemplateService::registerDefaults();
        JemNotificationTemplateService::registerDefaults();

        $db = $this->db();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__mail_templates'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote(JemNotificationTemplateCatalog::EXTENSION))
            ->where($db->quoteName('language') . ' = ' . $db->quote(''));
        $db->setQuery($query);

        self::assertSame(
            count(JemNotificationTemplateCatalog::all()) + count(JemNotificationTemplateCatalog::sharedAll()),
            (int) $db->loadResult()
        );
    }

    public function testReregisteringDefaultsPreservesLanguageCustomization(): void
    {
        JemNotificationTemplateService::registerDefaults();
        $definitions = JemNotificationTemplateCatalog::all();
        $definition = reset($definitions);
        $this->insertCustom($definition['id'], 'Custom {site_name}', 'Hello {user_name}', '<p>{event_title}</p>');

        JemNotificationTemplateService::registerDefaults();

        $custom = $this->loadCustom($definition['id']);
        self::assertNotNull($custom);
        self::assertSame('Custom {site_name}', $custom->subject);
        self::assertSame('Hello {user_name}', $custom->body);
        self::assertSame('<p>{event_title}</p>', $custom->htmlbody);
    }

    public function testNativeCustomizationRendersNamedPlainAndHtmlBodies(): void
    {
        JemNotificationTemplateService::registerDefaults();
        $definition = JemNotificationTemplateCatalog::findByLanguageKeys(
            'PLG_JEM_MAILER_USER_REG_SUBJECT',
            'PLG_JEM_MAILER_USER_REG_BODY_9'
        );
        $this->insertCustom(
            $definition['id'],
            '{site_name}: {event_title}',
            'Hello {user_name}',
            '<p>{user_name}: {event_title}</p>'
        );

        $message = JemNotificationTemplateService::renderByLanguageKeys(
            $definition['subject_key'],
            $definition['body_key'],
            $this->values(),
            self::TEST_LANGUAGE
        );

        self::assertTrue($message->custom);
        self::assertSame('Example Site: JEM Day', $message->subject);
        self::assertStringStartsWith('Hello <Ada>', $message->body);
        self::assertStringContainsString('Example Site', $message->body);
        self::assertStringStartsWith('<p>&lt;Ada&gt;: JEM Day</p>', $message->htmlbody);
        self::assertStringContainsString('jem-notification-footer', $message->htmlbody);
    }

    public function testJemEditorModelSavesValidatesAndRestoresOneLanguageOverride(): void
    {
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/notificationtemplate.php';
        JemNotificationTemplateService::registerDefaults();
        $definition = JemNotificationTemplateCatalog::findByLanguageKeys(
            'PLG_JEM_MAILER_USER_REG_SUBJECT',
            'PLG_JEM_MAILER_USER_REG_BODY_9'
        );
        $language = 'en-GB';
        $backup = $this->loadCustomForLanguage($definition['id'], $language);
        $this->deleteOverride($definition['id'], $language);

        try {
            $model = new JemModelNotificationtemplate(array('dbo' => $this->db()));
            $warnings = $model->save(array(
                'template_id' => $definition['id'],
                'language' => $language,
                'subject' => '{site_name}: {event_title}',
                'body' => 'Hello {user_name}, welcome to {event_title}.',
                'htmlbody' => '<style>.title { color: red; }</style><p>{user_name}</p>',
            ));

            self::assertSame(array(), $warnings);
            $stored = $this->loadCustomForLanguage($definition['id'], $language);
            self::assertNotNull($stored);
            self::assertSame('{site_name}: {event_title}', $stored->subject);
            self::assertStringContainsString('{user_name}', $stored->htmlbody);

            try {
                $model->save(array(
                    'template_id' => $definition['id'],
                    'language' => $language,
                    'subject' => 'Invalid {made_up}',
                    'body' => 'Hello {user_name} {event_title}',
                    'htmlbody' => '',
                ));
                self::fail('Unknown variables must be rejected.');
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString('unknown_variable:made_up', $e->getMessage());
            }

            $model->reset($definition['id'], $language);
            self::assertNull($this->loadCustomForLanguage($definition['id'], $language));
        } finally {
            $this->deleteOverride($definition['id'], $language);
            if ($backup) {
                $this->db()->insertObject('#__mail_templates', $backup);
            }
        }
    }

    public function testJemListModelLoadsRegisteredTemplatesAndCatalogueMetadata(): void
    {
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/notificationtemplates.php';
        JemNotificationTemplateService::registerDefaults();
        $model = new JemModelNotificationtemplates(array('dbo' => $this->db()));
        $model->getState();
        $model->setState('list.limit', 100);
        $model->setState('list.start', 0);
        $model->setState('filter_language', 'en-GB');
        $model->setState('filter_search', '');
        $model->setState('filter_workflow', '');
        $model->setState('filter_recipient', '');
        $model->setState('filter_customized', '');
        $items = $model->getItems();

        self::assertCount(count(JemNotificationTemplateCatalog::all()), $items);
        foreach ($items as $item) {
            self::assertNotSame('', $item->workflow);
            self::assertNotSame('', $item->recipient);
            self::assertStringStartsWith('plg_jem_mailer.', $item->template_id);
        }
    }

    public function testNotificationsMainModelKeepsTheTemplateCatalogueAvailable(): void
    {
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/notifications.php';
        JemNotificationTemplateService::registerDefaults();
        $model = new JemModelNotifications(array('dbo' => $this->db()));
        $model->getState();
        $model->setState('list.limit', 100);
        $model->setState('list.start', 0);
        $model->setState('filter_language', 'en-GB');
        $model->setState('filter_search', '');
        $model->setState('filter_workflow', '');
        $model->setState('filter_recipient', '');
        $model->setState('filter_customized', '');

        self::assertCount(count(JemNotificationTemplateCatalog::all()), $model->getItems());
    }

    public function testSharedFooterAndDisclaimerSaveRenderAndResetIndependently(): void
    {
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/notificationcontent.php';
        JemNotificationTemplateService::registerDefaults();
        $language = 'en-GB';
        $footer = JemNotificationTemplateCatalog::shared('footer');
        $disclaimer = JemNotificationTemplateCatalog::shared('disclaimer');
        $footerBackup = $this->loadCustomForLanguage($footer['id'], $language);
        $disclaimerBackup = $this->loadCustomForLanguage($disclaimer['id'], $language);
        $this->deleteOverride($footer['id'], $language);
        $this->deleteOverride($disclaimer['id'], $language);

        try {
            $model = new JemModelNotificationcontent(array('dbo' => $this->db()));
            $defaultFooter = $model->getItem('footer', $language);
            self::assertFalse($defaultFooter->customized);
            self::assertSame(1, $defaultFooter->enabled_user);

            $model->save(array(
                'section' => 'footer',
                'language' => $language,
                'body' => 'FOOTER {site_name}',
                'htmlbody' => '<footer>FOOTER {site_name}</footer>',
                'enabled_user' => 1,
                'enabled_admin' => 0,
                'privacy_url' => '',
            ));
            $model->save(array(
                'section' => 'disclaimer',
                'language' => $language,
                'body' => 'PRIVACY {privacy_url}',
                'htmlbody' => '<aside>PRIVACY {privacy_url}</aside>',
                'enabled_user' => 1,
                'enabled_admin' => 0,
                'privacy_url' => 'https://example.test/privacy',
            ));

            $definition = JemNotificationTemplateCatalog::findByLanguageKeys(
                'PLG_JEM_MAILER_USER_REG_SUBJECT',
                'PLG_JEM_MAILER_USER_REG_BODY_9'
            );
            $message = JemNotificationTemplateService::renderByLanguageKeys(
                $definition['subject_key'],
                $definition['body_key'],
                $this->values(),
                $language
            );

            self::assertStringContainsString('FOOTER Example Site', $message->body);
            self::assertStringContainsString('PRIVACY https://example.test/privacy', $message->body);
            self::assertLessThan(
                strpos($message->body, 'PRIVACY'),
                strpos($message->body, 'FOOTER')
            );

            $model->reset('footer', $language);
            self::assertNull($this->loadCustomForLanguage($footer['id'], $language));
            self::assertNotNull($this->loadCustomForLanguage($disclaimer['id'], $language));
            $model->reset('disclaimer', $language);
            self::assertNull($this->loadCustomForLanguage($disclaimer['id'], $language));
        } finally {
            $this->deleteOverride($footer['id'], $language);
            $this->deleteOverride($disclaimer['id'], $language);
            if ($footerBackup) {
                $this->db()->insertObject('#__mail_templates', $footerBackup);
            }
            if ($disclaimerBackup) {
                $this->db()->insertObject('#__mail_templates', $disclaimerBackup);
            }
        }
    }

    public function testActiveJoomlaLanguagesWithoutJemMailerPackageAreReadOnlyAndReturnNoRows(): void
    {
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/notificationtemplates.php';
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/notificationcontent.php';
        JemNotificationTemplateService::registerDefaults();
        $model = new JemModelNotificationtemplates(array('dbo' => $this->db()));
        $languages = $model->getLanguages();
        $unavailable = null;

        foreach ($languages as $language) {
            self::assertSame(
                JemNotificationTemplateService::hasMailerLanguage($language->lang_code),
                (bool) $language->jem_available
            );
            if (!$language->jem_available && $unavailable === null) {
                $unavailable = $language->lang_code;
            }
        }

        if ($unavailable === null) {
            self::assertNotEmpty($languages);
            return;
        }

        $model->getState();
        $model->setState('list.limit', 100);
        $model->setState('list.start', 0);
        $model->setState('filter_language', $unavailable);
        $model->setState('filter_search', '');
        $model->setState('filter_workflow', '');
        $model->setState('filter_recipient', '');
        $model->setState('filter_customized', '');
        self::assertSame(array(), $model->getItems());

        $this->expectException(InvalidArgumentException::class);
        (new JemModelNotificationcontent(array('dbo' => $this->db())))->getItem('footer', $unavailable);
    }

    private function insertCustom(string $templateId, string $subject, string $body, string $htmlbody): void
    {
        $definition = JemNotificationTemplateCatalog::get($templateId);
        $row = (object) array(
            'template_id' => $templateId,
            'extension' => JemNotificationTemplateCatalog::EXTENSION,
            'language' => self::TEST_LANGUAGE,
            'subject' => $subject,
            'body' => $body,
            'htmlbody' => $htmlbody,
            'attachments' => '',
            'params' => json_encode(array('tags' => $definition['allowed_tokens'])),
        );
        $this->db()->insertObject('#__mail_templates', $row);
    }

    private function loadCustom(string $templateId): ?object
    {
        $db = $this->db();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__mail_templates'))
            ->where($db->quoteName('template_id') . ' = ' . $db->quote($templateId))
            ->where($db->quoteName('language') . ' = ' . $db->quote(self::TEST_LANGUAGE));
        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    private function loadCustomForLanguage(string $templateId, string $language): ?object
    {
        $db = $this->db();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__mail_templates'))
            ->where($db->quoteName('template_id') . ' = ' . $db->quote($templateId))
            ->where($db->quoteName('language') . ' = ' . $db->quote($language));
        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    private function deleteOverride(string $templateId, string $language): void
    {
        $db = $this->db();
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__mail_templates'))
            ->where($db->quoteName('template_id') . ' = ' . $db->quote($templateId))
            ->where($db->quoteName('language') . ' = ' . $db->quote($language));
        $db->setQuery($query);
        $db->execute();
    }

    private function deleteTestOverrides(): void
    {
        if (!defined('JPATH_BASE')) {
            return;
        }

        $db = $this->db();
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__mail_templates'))
            ->where($db->quoteName('extension') . ' = ' . $db->quote(JemNotificationTemplateCatalog::EXTENSION))
            ->where($db->quoteName('language') . ' = ' . $db->quote(self::TEST_LANGUAGE));
        $db->setQuery($query);
        $db->execute();
    }

    private function values(): array
    {
        return array(
            'user_name' => '<Ada>',
            'actor_name' => 'Admin',
            'comment' => '',
            'event_title' => 'JEM Day',
            'event_date' => '2030-01-01',
            'event_time' => '18:00',
            'venue' => 'Hall',
            'city' => 'City',
            'places' => '1',
            'event_description' => 'Description',
            'event_url' => 'https://example.test/event',
            'event_image_url' => 'https://example.test/images/event.jpg',
            'venue_image_url' => 'https://example.test/images/venue.jpg',
            'site_name' => 'Example Site',
            'site_url' => 'https://example.test/',
            'privacy_url' => 'https://example.test/privacy',
            'contact_email' => 'events@example.test',
        );
    }

    private function db(): DatabaseDriver
    {
        return Factory::getContainer()->get(DatabaseDriver::class);
    }
}
