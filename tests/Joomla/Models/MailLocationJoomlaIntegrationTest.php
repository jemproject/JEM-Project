<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseDriver;
use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

/** Real Joomla translation and template boundaries, without sending or queuing email. */
final class MailLocationJoomlaIntegrationTest extends JoomlaTestCase
{
    private static mixed $originalSession;
    private mixed $originalLanguage;
    private mixed $originalFactoryLanguage;
    private DatabaseDriver $db;
    private object $plugin;
    private bool $named;

    /** @return void Bootstrap the disposable Joomla site with an isolated CLI session. */
    public static function setUpBeforeClass(): void
    {
        self::bootJoomlaSite();
        self::$originalSession = Factory::getApplication()->getSession();
        Factory::getApplication()->setSession(Factory::getContainer()->get('session.cli'));
        require_once JPATH_SITE . '/components/com_jem/factory.php';
        require_once JEM_TEST_ROOT . '/plugins/plg_jem_mailer/mailer.php';
        self::assertSame(hash_file('sha256', JEM_TEST_ROOT . '/site/classes/maillocation.class.php'),
            hash_file('sha256', JPATH_SITE . '/components/com_jem/classes/maillocation.class.php'));
        if (is_file(JEM_TEST_ROOT . '/site/classes/notificationtemplateservice.class.php')) {
            foreach (['notificationtemplateservice.class.php', 'notificationtemplaterenderer.class.php'] as $file) {
                self::assertSame(hash_file('sha256', JEM_TEST_ROOT . '/site/classes/' . $file),
                    hash_file('sha256', JPATH_SITE . '/components/com_jem/classes/' . $file));
            }
        }
    }

    /** @return void Restore the site's previous session. */
    public static function tearDownAfterClass(): void
    {
        Factory::getApplication()->setSession(self::$originalSession);
    }

    /** @return void Isolate template edits in a transaction and load the candidate language file in memory. */
    protected function setUp(): void
    {
        $app = Factory::getApplication();
        $this->originalLanguage = $app->getLanguage();
        $this->originalFactoryLanguage = Factory::$language;
        $language = new Language('en-GB');
        $language->load('plg_jem_mailer', JEM_TEST_ROOT . '/plugins/plg_jem_mailer', 'en-GB', true, false);
        $app->loadLanguage($language);
        Factory::$language = $language;
        $this->plugin = (new ReflectionClass(plgJemMailer::class))->newInstanceWithoutConstructor();
        $this->named = !method_exists($this->plugin, '_formatEventMailBody');
        $this->db = Factory::getContainer()->get(DatabaseDriver::class);
        $this->db->transactionStart();
        if ($this->named) {
            $cached = (new ReflectionMethod(JemNotificationTemplateService::class, 'loadMailerLanguage'))->invoke(null, 'en-GB');
            $cached->load('plg_jem_mailer', JEM_TEST_ROOT . '/plugins/plg_jem_mailer', 'en-GB', true, false);
            $this->db->setQuery("DELETE FROM #__mail_templates WHERE extension='plg_jem_mailer' AND language='en-GB'")->execute();
        }
    }

    /** @return void Restore database and language state after every test. */
    protected function tearDown(): void
    {
        $this->db->transactionRollback();
        Factory::getApplication()->loadLanguage($this->originalLanguage);
        Factory::$language = $this->originalFactoryLanguage;
    }

    /** @return iterable<string,array{string,string,string,string,?string}> Physical and online scenarios. */
    public static function scenarios(): iterable
    {
        yield 'online' => ['', '', 'scheduled', 'https://meet.example.invalid/session', null];
        yield 'moved online' => ['', '', 'moved_online', 'https://meet.example.invalid/session', null];
        yield 'no location' => ['', '', 'scheduled', '', null];
        yield 'physical' => ['Hall', 'Madrid', 'scheduled', '', 'Venue: Hall / Madrid'];
        yield 'venue only' => ['Hall', '', 'scheduled', '', 'Venue: Hall'];
        yield 'city only' => ['', 'Madrid', 'scheduled', '', 'Venue: Madrid'];
        yield 'hybrid' => ['Hall', 'Madrid', 'scheduled', 'https://meet.example.invalid/session', 'Venue: Hall / Madrid'];
        yield 'whitespace' => ['  ', "\t", 'scheduled', '', null];
    }

    /** @param string $venue Venue. @param string $city City. @param string $status Event state. @param string $url Online URL. @param ?string $expected Expected row. @return void */
    #[DataProvider('scenarios')]
    public function testRegistrationUserAndAdministratorBodies(string $venue, string $city, string $status, string $url, ?string $expected): void
    {
        $event = (object) ['online_meeting_url' => $url, 'online_meeting_label' => 'Join online', 'event_status' => $status];
        $link = (new ReflectionMethod($this->plugin, '_appendOnlineMeetingInfo'))->invoke($this->plugin, 'https://example.invalid/event', $event);
        $values = $this->values($venue, $city, $link);
        foreach (['USER_REG_BODY_9' => 'USER_REG_SUBJECT', 'ADMIN_REG_BODY_8' => 'ADMIN_REG_SUBJECT'] as $body => $subject) {
            if ($this->named) {
                $message = JemNotificationTemplateService::renderByLanguageKeys('PLG_JEM_MAILER_' . $subject,
                    'PLG_JEM_MAILER_' . $body, $values, 'en-GB');
                self::assertFalse($message->custom);
                $rendered = $message->body;
            } else {
                $args = ['Ana', 'Demo', '23/11/2026', '20:00', $venue, $city, 2];
                if (str_starts_with($body, 'USER_')) {
                    $args[] = 'Description';
                }
                array_push($args, $link, 'Test site');
                $rendered = (new ReflectionMethod($this->plugin, '_formatEventMailBody'))->invoke($this->plugin,
                    'PLG_JEM_MAILER_' . $body, 4, ...$args);
            }
            preg_match('/^Venue:[^\r\n]*/m', $rendered, $match);
            self::assertSame($expected, isset($match[0]) ? trim($match[0]) : null);
            self::assertStringContainsString('Booked places:2', $rendered);
            self::assertStringContainsString('https://example.invalid/event', $rendered);
            self::assertStringContainsString('23/11/2026', $rendered);
            if ($url !== '') {
                self::assertStringContainsString($url, $rendered);
            }
        }
    }

    /** Every supplied default location row is optional; subsequent placeholders keep their meaning. */
    public function testAllDefaultLocationVariants(): void
    {
        $strings = parse_ini_file(JEM_TEST_ROOT . '/plugins/plg_jem_mailer/language/en-GB/plg_jem_mailer.ini', false, INI_SCANNER_RAW);
        $checked = 0;
        foreach ($strings as $key => $text) {
            $template = Text::_($key);
            if ($this->named) {
                if (!str_contains($text, 'Venue: {venue} / {city}')) {
                    continue;
                }
                $definition = null;
                foreach (JemNotificationTemplateCatalog::all() as $candidate) {
                    if ($candidate['body_key'] === $key) {
                        $definition = $candidate;
                        break;
                    }
                }
                self::assertNotNull($definition, $key);
                $values = array_fill_keys($definition['allowed_tokens'], 'VALUE');
                $values['venue'] = $values['city'] = '';
                $before = JemNotificationTemplateRenderer::render($template, $values);
                $after = JemNotificationTemplateService::renderByLanguageKeys($definition['subject_key'], $key, $values, 'en-GB')->body;
            } else {
                $location = strpos($template, 'Venue: %s / %s');
                if ($location === false) {
                    continue;
                }
                $index = substr_count(substr($template, 0, $location), '%s');
                $args = array_fill(0, substr_count($template, '%s'), 'VALUE');
                $args[$index] = $args[$index + 1] = '';
                $before = vsprintf($template, $args);
                $after = (new ReflectionMethod($this->plugin, '_formatEventMailBody'))->invoke($this->plugin, $key, $index, ...$args);
            }
            $expected = preg_replace('/^Venue:  \/ *\r?\n/m', '', $before);
            self::assertStringStartsWith($expected, $after, $key);
            self::assertStringNotContainsString('Venue:  / ', $after, $key);
            ++$checked;
        }
        self::assertGreaterThan(40, $checked);
    }

    /** @param string $venue Venue. @param string $city City. @param string $url Full direct link. @return array Template values. */
    private function values(string $venue = '', string $city = '', string $url = ''): array
    {
        return ['user_name' => 'Ana', 'site_name' => 'Test site', 'event_title' => 'Demo', 'event_date' => '23/11/2026',
            'event_time' => '20:00', 'venue' => $venue, 'city' => $city, 'places' => 2,
            'event_description' => 'Description', 'event_url' => $url];
    }
}
