<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\LanguageFactoryInterface;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\Uri\Uri;

/**
 * Joomla storage, translation fallback and rendering for JEM notifications.
 */
final class JemNotificationTemplateService
{
    /**
     * Whether the installed JEM Mailer files provide the requested language.
     */
    public static function hasMailerLanguage($languageTag)
    {
        $languageTag = trim((string) $languageTag);
        if (!preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', $languageTag)) {
            return false;
        }

        $filename = $languageTag . '/plg_jem_mailer.ini';
        foreach (array(
            JPATH_PLUGINS . '/jem/mailer/language/' . $filename,
            JPATH_SITE . '/language/' . $filename,
            JPATH_ADMINISTRATOR . '/language/' . $filename,
        ) as $path) {
            if (is_file($path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create or update native master rows without touching language overrides.
     */
    public static function registerDefaults()
    {
        foreach (JemNotificationTemplateCatalog::all() as $definition) {
            self::registerDefinition($definition);
        }

        foreach (JemNotificationTemplateCatalog::sharedAll() as $definition) {
            self::registerDefinition($definition);
        }
    }

    private static function registerDefinition(array $definition)
    {
        $existing = MailTemplate::getTemplate($definition['id'], '');
        $htmlbodyKey = (string) ($definition['htmlbody_key'] ?? '');

        if ($existing) {
            MailTemplate::updateTemplate(
                $definition['id'],
                $definition['subject_key'],
                $definition['body_key'],
                $definition['allowed_tokens'],
                $htmlbodyKey
            );
        } else {
            MailTemplate::createTemplate(
                $definition['id'],
                $definition['subject_key'],
                $definition['body_key'],
                $definition['allowed_tokens'],
                $htmlbodyKey
            );
        }
    }

    /**
     * Resolve and render the template selected by an existing Mailer branch.
     */
    public static function renderByLanguageKeys($subjectKey, $bodyKey, array $values, $languageTag = null)
    {
        $definition = JemNotificationTemplateCatalog::findByLanguageKeys($subjectKey, $bodyKey);
        if (!$definition) {
            throw new InvalidArgumentException('The selected Mailer language keys are not catalogued.');
        }

        $languageTag = self::normaliseLanguageTag($languageTag);
        $mail = MailTemplate::getTemplate($definition['id'], $languageTag);
        $isCustom = $mail && (string) $mail->language === $languageTag;

        $subjectTemplate = $isCustom
            ? (string) $mail->subject
            : self::translateWithEnglishFallback($definition['subject_key'], $languageTag);
        $bodyTemplate = $isCustom
            ? (string) $mail->body
            : self::translateWithEnglishFallback($definition['body_key'], $languageTag);
        $htmlTemplate = $isCustom ? (string) $mail->htmlbody : '';

        try {
            $message = (object) array(
                'template_id' => $definition['id'],
                'language'    => $languageTag,
                'custom'      => $isCustom,
                'subject'     => JemNotificationTemplateRenderer::render(
                    $subjectTemplate,
                    $values,
                    $definition['subject_legacy_tokens']
                ),
                'body'        => JemNotificationTemplateRenderer::render(
                    $bodyTemplate,
                    $values,
                    $definition['body_legacy_tokens']
                ),
                'htmlbody'    => $htmlTemplate === '' ? '' : JemNotificationTemplateRenderer::render(
                    $htmlTemplate,
                    $values,
                    $definition['body_legacy_tokens'],
                    true
                ),
                'fallback_reason' => '',
            );
        } catch (Throwable $e) {
            if (!$isCustom) {
                throw $e;
            }

            $fallbackSubject = self::translateWithEnglishFallback($definition['subject_key'], $languageTag);
            $fallbackBody = self::translateWithEnglishFallback($definition['body_key'], $languageTag);

            $message = (object) array(
                'template_id' => $definition['id'],
                'language'    => $languageTag,
                'custom'      => false,
                'subject'     => JemNotificationTemplateRenderer::render(
                    $fallbackSubject,
                    $values,
                    $definition['subject_legacy_tokens']
                ),
                'body'        => JemNotificationTemplateRenderer::render(
                    $fallbackBody,
                    $values,
                    $definition['body_legacy_tokens']
                ),
                'htmlbody'    => '',
                'fallback_reason' => $e->getMessage(),
            );
        }

        return self::appendSharedContent(
            $message,
            $definition['recipient'],
            $values,
            $languageTag
        );
    }

    /**
     * Resolve one shared footer or disclaimer for a recipient class.
     */
    public static function renderShared($section, $recipient, array $values, $languageTag = null)
    {
        $definition = JemNotificationTemplateCatalog::shared($section);
        if (!$definition || !in_array($recipient, array('user', 'admin'), true)) {
            throw new InvalidArgumentException('Invalid shared notification section or recipient.');
        }

        $languageTag = self::normaliseLanguageTag($languageTag);
        $mail = MailTemplate::getTemplate($definition['id'], $languageTag);
        $isCustom = $mail && (string) $mail->language === $languageTag;
        $enabledKey = 'enabled_' . $recipient;
        $enabled = $isCustom
            ? (bool) $mail->params->get($enabledKey, $definition['default_' . $enabledKey])
            : (bool) $definition['default_' . $enabledKey];

        if (!$enabled) {
            return (object) array(
                'enabled' => false,
                'body' => '',
                'htmlbody' => '',
                'fallback_reason' => '',
            );
        }

        $values += array(
            'site_name' => (string) Factory::getApplication()->get('sitename'),
            'site_url' => Uri::root(),
            'privacy_url' => $isCustom ? (string) $mail->params->get('privacy_url', '') : '',
            'contact_email' => (string) Factory::getApplication()->get('mailfrom'),
        );
        $bodyTemplate = $isCustom
            ? (string) $mail->body
            : self::translateWithEnglishFallback($definition['body_key'], $languageTag);
        $htmlTemplate = $isCustom
            ? (string) $mail->htmlbody
            : self::translateWithEnglishFallback($definition['htmlbody_key'], $languageTag);

        try {
            return (object) array(
                'enabled' => true,
                'body' => JemNotificationTemplateRenderer::render($bodyTemplate, $values),
                'htmlbody' => $htmlTemplate === ''
                    ? ''
                    : JemNotificationTemplateRenderer::render($htmlTemplate, $values, array(), true),
                'fallback_reason' => '',
            );
        } catch (Throwable $e) {
            if (!$isCustom) {
                throw $e;
            }

            $fallbackBody = self::translateWithEnglishFallback($definition['body_key'], $languageTag);
            $fallbackHtml = self::translateWithEnglishFallback($definition['htmlbody_key'], $languageTag);

            return (object) array(
                'enabled' => true,
                'body' => JemNotificationTemplateRenderer::render($fallbackBody, $values),
                'htmlbody' => $fallbackHtml === ''
                    ? ''
                    : JemNotificationTemplateRenderer::render($fallbackHtml, $values, array(), true),
                'fallback_reason' => $e->getMessage(),
            );
        }
    }

    /**
     * Append enabled shared content in the stable footer/disclaimer order.
     */
    private static function appendSharedContent($message, $recipient, array $values, $languageTag)
    {
        foreach (array('footer', 'disclaimer') as $section) {
            $shared = self::renderShared($section, $recipient, $values, $languageTag);
            if (!$shared->enabled) {
                continue;
            }

            if ($shared->body !== '') {
                $message->body = rtrim((string) $message->body) . "\n\n" . trim($shared->body);
            }
            if ((string) $message->htmlbody !== '' && ($shared->htmlbody !== '' || $shared->body !== '')) {
                $html = $shared->htmlbody !== ''
                    ? $shared->htmlbody
                    : '<p>' . nl2br(htmlspecialchars($shared->body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</p>';
                $message->htmlbody = rtrim((string) $message->htmlbody)
                    . '<div class="jem-notification-' . $section . '">' . $html . '</div>';
            }
            if ($shared->fallback_reason !== '') {
                $prefix = $message->fallback_reason === '' ? '' : $message->fallback_reason . '; ';
                $message->fallback_reason = $prefix . $section . ': ' . $shared->fallback_reason;
            }
        }

        return $message;
    }

    /**
     * Translate one default key for a requested language and then English.
     */
    public static function translateWithEnglishFallback($key, $languageTag)
    {
        $languageTag = self::normaliseLanguageTag($languageTag);
        $siteDefault = (string) ComponentHelper::getParams('com_languages')->get('site', '');
        $fallbacks = array_values(array_unique(array_filter(array($languageTag, $siteDefault, 'en-GB'))));

        foreach ($fallbacks as $fallback) {
            $language = self::loadMailerLanguage($fallback);
            if ($language->hasKey($key)) {
                return $language->_($key);
            }
        }

        return (string) $key;
    }

    /**
     * Return a default string normalised for display in the JEM editor.
     */
    public static function editorDefault($key, $languageTag, array $legacyTokens)
    {
        $text = self::translateWithEnglishFallback($key, $languageTag);
        $format = JemNotificationTemplateRenderer::detectFormat($text);

        return ($format === JemNotificationTemplateRenderer::FORMAT_LEGACY
            || $format === JemNotificationTemplateRenderer::FORMAT_MIXED)
            ? JemNotificationTemplateRenderer::normaliseLegacy($text, $legacyTokens)
            : $text;
    }

    private static function normaliseLanguageTag($languageTag)
    {
        $languageTag = trim((string) $languageTag);
        if (!preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', $languageTag)) {
            $languageTag = Factory::getApplication()->getLanguage()->getTag();
        }

        return $languageTag;
    }

    private static function loadMailerLanguage($languageTag)
    {
        static $languages = array();

        if (isset($languages[$languageTag])) {
            return $languages[$languageTag];
        }

        $factory = Factory::getContainer()->get(LanguageFactoryInterface::class);
        $language = $factory->createLanguage($languageTag);
        $source = JPATH_PLUGINS . '/jem/mailer';

        $language->load('plg_jem_mailer', JPATH_ADMINISTRATOR, $languageTag, true, false);
        $language->load('plg_jem_mailer', $source, $languageTag, true, false);

        $languages[$languageTag] = $language;

        return $language;
    }
}
