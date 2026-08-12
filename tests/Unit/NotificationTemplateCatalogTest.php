<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/notificationtemplatecatalog.class.php';
require_once JEM_TEST_ROOT . '/site/classes/notificationtemplaterenderer.class.php';

final class NotificationTemplateCatalogTest extends TestCase
{
    private array $language;

    protected function setUp(): void
    {
        $language = parse_ini_file(
            JEM_TEST_ROOT . '/plugins/plg_jem_mailer/language/en-GB/plg_jem_mailer.ini',
            false,
            INI_SCANNER_RAW
        );
        self::assertIsArray($language);
        $this->language = $language;
    }

    public function testPoint2ACatalogueHasUniqueNativeTemplateIdentifiers(): void
    {
        $definitions = JemNotificationTemplateCatalog::all();

        self::assertCount(49, $definitions);
        self::assertCount(49, array_unique(array_column($definitions, 'id')));

        foreach ($definitions as $id => $definition) {
            self::assertSame($id, $definition['id']);
            self::assertStringStartsWith('plg_jem_mailer.', $id);
            self::assertLessThanOrEqual(127, strlen($id));
        }
    }

    public function testEveryDefaultKeyExistsAndLegacyOrderMatchesEnglishMarkers(): void
    {
        foreach (JemNotificationTemplateCatalog::all() as $definition) {
            foreach (array('subject', 'body') as $part) {
                $key = $definition[$part . '_key'];
                $tokens = $definition[$part . '_legacy_tokens'];

                self::assertArrayHasKey($key, $this->language, $key);
                preg_match_all('/%(?:(?:[1-9][0-9]*)\$)?[sd]/', $this->language[$key], $matches);
                self::assertCount(count($matches[0]), $tokens, $key);
                if (JemNotificationTemplateRenderer::detectFormat($this->language[$key]) === 'named') {
                    self::assertSame(array(), $tokens, $key);
                }
            }
        }
    }

    public function testEveryLegacyVariableIsAllowedForItsTemplate(): void
    {
        foreach (JemNotificationTemplateCatalog::all() as $definition) {
            $legacy = array_merge(
                $definition['subject_legacy_tokens'],
                $definition['body_legacy_tokens']
            );

            foreach ($legacy as $token) {
                self::assertContains($token, $definition['allowed_tokens'], $definition['id']);
            }
        }
    }

    public function testCustomTemplatesOfferEventAndVenueImageUrls(): void
    {
        foreach (JemNotificationTemplateCatalog::all() as $definition) {
            self::assertContains('event_image_url', $definition['allowed_tokens']);
            self::assertContains('venue_image_url', $definition['allowed_tokens']);
        }
    }

    public function testFooterAndDisclaimerAreSeparateSharedDefinitions(): void
    {
        $shared = JemNotificationTemplateCatalog::sharedAll();

        self::assertSame(array('footer', 'disclaimer'), array_keys($shared));
        self::assertSame('plg_jem_mailer.shared_footer', $shared['footer']['id']);
        self::assertSame('plg_jem_mailer.shared_privacy_disclaimer', $shared['disclaimer']['id']);
        self::assertContains('privacy_url', $shared['disclaimer']['allowed_tokens']);
        self::assertTrue($shared['footer']['default_enabled_user']);
        self::assertFalse($shared['disclaimer']['default_enabled_user']);
    }

    public function testEverySharedDefaultIsDefinedByTheBundledLanguageIni(): void
    {
        foreach (JemNotificationTemplateCatalog::sharedAll() as $definition) {
            self::assertArrayHasKey($definition['subject_key'], $this->language);
            self::assertArrayHasKey($definition['body_key'], $this->language);
            self::assertArrayHasKey($definition['htmlbody_key'], $this->language);
        }
    }

    public function testLookupUsesTheExactLegacySubjectAndBodyPair(): void
    {
        $definition = JemNotificationTemplateCatalog::findByLanguageKeys(
            'PLG_JEM_MAILER_USER_REG_SUBJECT',
            'PLG_JEM_MAILER_USER_REG_BODY_9'
        );

        self::assertNotNull($definition);
        self::assertSame('registration', $definition['workflow']);
        self::assertSame('user', $definition['recipient']);
        self::assertSame('self', $definition['variant']);
        self::assertFalse($definition['with_comment']);
    }

    public function testRendererPreservesEveryCurrentEnglishLegacyTemplate(): void
    {
        foreach (JemNotificationTemplateCatalog::all() as $definition) {
            $values = array();
            foreach ($definition['allowed_tokens'] as $token) {
                $values[$token] = 'VALUE_' . strtoupper($token);
            }

            foreach (array('subject', 'body') as $part) {
                $key = $definition[$part . '_key'];
                $tokens = $definition[$part . '_legacy_tokens'];
                $actual = JemNotificationTemplateRenderer::render(
                    $this->language[$key],
                    $values,
                    $tokens
                );
                if (JemNotificationTemplateRenderer::detectFormat($this->language[$key]) === 'legacy') {
                    $arguments = array_map(static fn ($token) => $values[$token], $tokens);
                    self::assertSame(vsprintf($this->language[$key], $arguments), $actual, $key);
                } else {
                    self::assertStringNotContainsString('{', $actual, $key);
                }
            }
        }
    }

    public function testScheduledReminderUsesLiteralNamedVariables(): void
    {
        $definition = JemNotificationTemplateCatalog::findByLanguageKeys(
            'PLG_JEM_MAILER_USER_REMINDER_SUBJECT',
            'PLG_JEM_MAILER_USER_REMINDER_BODY'
        );

        self::assertNotNull($definition);
        self::assertSame('reminder', $definition['workflow']);
        self::assertSame('scheduled', $definition['variant']);
        self::assertContains('reminder_interval', $definition['allowed_tokens']);
        self::assertSame(array(), $definition['subject_legacy_tokens']);
        self::assertSame(array(), $definition['body_legacy_tokens']);
    }
}
