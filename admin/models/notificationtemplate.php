<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Edit one language-specific JEM notification template.
 */
class JemModelNotificationtemplate extends BaseDatabaseModel
{
    public function getItem($templateId = null, $language = null)
    {
        $app = Factory::getApplication();
        $templateId = $templateId ?: $app->input->getString('template_id');
        $language = $this->normaliseLanguage($language ?: $app->input->getString('language'));
        $definition = JemNotificationTemplateCatalog::get($templateId);

        if (!$definition || !$this->languageExists($language)) {
            throw new InvalidArgumentException('Unknown JEM notification template or inactive language.');
        }

        $custom = $this->loadCustom($templateId, $language);
        $defaultSubject = JemNotificationTemplateService::editorDefault(
            $definition['subject_key'],
            $language,
            $definition['subject_legacy_tokens']
        );
        $defaultBody = JemNotificationTemplateService::editorDefault(
            $definition['body_key'],
            $language,
            $definition['body_legacy_tokens']
        );

        $item = (object) array_merge($definition, array(
            'template_id'     => $templateId,
            'language'        => $language,
            'customized'      => (bool) $custom,
            'default_subject' => $defaultSubject,
            'default_body'    => $defaultBody,
            'subject'         => $custom
                ? $this->normaliseForEditor((string) $custom->subject, $definition['subject_legacy_tokens'])
                : $defaultSubject,
            'body'            => $custom
                ? $this->normaliseForEditor((string) $custom->body, $definition['body_legacy_tokens'])
                : $defaultBody,
            'htmlbody'        => $custom
                ? $this->normaliseForEditor((string) $custom->htmlbody, $definition['body_legacy_tokens'])
                : '',
        ));

        $sessionData = $app->getUserState('com_jem.edit.notificationtemplate.data', array());
        if (is_array($sessionData)
            && ($sessionData['template_id'] ?? '') === $templateId
            && ($sessionData['language'] ?? '') === $language) {
            foreach (array('subject', 'body', 'htmlbody') as $field) {
                if (array_key_exists($field, $sessionData)) {
                    $item->{$field} = (string) $sessionData[$field];
                }
            }
        }

        return $item;
    }

    /**
     * Save one custom language row and return validation warnings.
     */
    public function save(array $data)
    {
        $templateId = (string) ($data['template_id'] ?? '');
        $language = $this->normaliseLanguage((string) ($data['language'] ?? ''));
        $definition = JemNotificationTemplateCatalog::get($templateId);
        if (!$definition || !$this->languageExists($language)) {
            throw new InvalidArgumentException('Invalid notification template or language.');
        }

        $filter = InputFilter::getInstance();
        $subject = trim(preg_replace('/[\r\n]+/', ' ', (string) ($data['subject'] ?? '')));
        $subject = $filter->clean($subject, 'string');
        $body = trim((string) ($data['body'] ?? ''));
        $htmlbody = trim((string) ($data['htmlbody'] ?? ''));
        $htmlbody = $htmlbody === '' ? '' : ComponentHelper::filterText($htmlbody);

        if ($subject === '' || $body === '') {
            throw new InvalidArgumentException('Notification subject and plain-text body are required.');
        }
        if (mb_strlen($subject) > 255) {
            throw new InvalidArgumentException('Notification subject is longer than 255 characters.');
        }

        $validation = array(
            'subject' => JemNotificationTemplateRenderer::validate(
                $subject,
                $definition['allowed_tokens']
            ),
            'body' => JemNotificationTemplateRenderer::validate(
                $body,
                $definition['allowed_tokens'],
                $definition['recommended_tokens']
            ),
            'htmlbody' => JemNotificationTemplateRenderer::validate(
                $htmlbody,
                $definition['allowed_tokens']
            ),
        );

        $errors = array();
        $warnings = array();
        foreach ($validation as $part => $result) {
            foreach ($result['errors'] as $error) {
                $errors[] = $part . ':' . $error;
            }
            foreach ($result['warnings'] as $warning) {
                $warnings[] = $part . ':' . $warning;
            }
        }

        if ($errors) {
            throw new InvalidArgumentException(implode(', ', array_unique($errors)));
        }

        $db = $this->getDatabase();
        $existing = $this->loadCustom($templateId, $language);
        $row = (object) array(
            'template_id' => $templateId,
            'extension'   => JemNotificationTemplateCatalog::EXTENSION,
            'language'    => $language,
            'subject'     => $subject,
            'body'        => $body,
            'htmlbody'    => $htmlbody,
            'attachments' => $existing ? (string) $existing->attachments : '',
            'params'      => json_encode(array('tags' => $definition['allowed_tokens'])),
        );

        if ($existing) {
            $db->updateObject('#__mail_templates', $row, array('template_id', 'language'));
        } else {
            $db->insertObject('#__mail_templates', $row);
        }

        Factory::getApplication()->setUserState('com_jem.edit.notificationtemplate.data', null);

        return array_values(array_unique($warnings));
    }

    /**
     * Remove only the selected language-specific override.
     */
    public function reset($templateId, $language)
    {
        $definition = JemNotificationTemplateCatalog::get($templateId);
        $language = $this->normaliseLanguage($language);
        if (!$definition || !$this->languageExists($language)) {
            throw new InvalidArgumentException('Invalid notification template or language.');
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__mail_templates'))
            ->where($db->quoteName('template_id') . ' = ' . $db->quote($templateId))
            ->where($db->quoteName('language') . ' = ' . $db->quote($language));
        $db->setQuery($query);
        $db->execute();
        Factory::getApplication()->setUserState('com_jem.edit.notificationtemplate.data', null);
    }

    public function getLanguages()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('lang_code', 'title', 'title_native', 'published')))
            ->from($db->quoteName('#__languages'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('title') . ' ASC');
        $db->setQuery($query);
        $languages = (array) $db->loadObjectList('lang_code');
        foreach ($languages as $item) {
            $item->jem_available = JemNotificationTemplateService::hasMailerLanguage($item->lang_code);
        }

        return $languages;
    }

    private function loadCustom($templateId, $language)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__mail_templates'))
            ->where($db->quoteName('template_id') . ' = ' . $db->quote($templateId))
            ->where($db->quoteName('language') . ' = ' . $db->quote($language));
        $db->setQuery($query);

        return $db->loadObject();
    }

    private function normaliseForEditor($text, array $legacyTokens)
    {
        $format = JemNotificationTemplateRenderer::detectFormat($text);

        return ($format === JemNotificationTemplateRenderer::FORMAT_LEGACY
            || $format === JemNotificationTemplateRenderer::FORMAT_MIXED)
            ? JemNotificationTemplateRenderer::normaliseLegacy($text, $legacyTokens)
            : $text;
    }

    private function languageExists($language)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__languages'))
            ->where($db->quoteName('lang_code') . ' = ' . $db->quote($language))
            ->where($db->quoteName('published') . ' = 1');
        $db->setQuery($query);

        return (int) $db->loadResult() > 0
            && JemNotificationTemplateService::hasMailerLanguage($language);
    }

    private function normaliseLanguage($language)
    {
        if (preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', (string) $language)) {
            return (string) $language;
        }

        $default = (string) ComponentHelper::getParams('com_languages')->get('site', '');

        return preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', $default) ? $default : 'en-GB';
    }
}
