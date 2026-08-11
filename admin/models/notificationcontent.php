<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Edit a language-specific shared notification footer or disclaimer.
 */
class JemModelNotificationcontent extends BaseDatabaseModel
{
    public function getItem($section = null, $language = null)
    {
        $app = Factory::getApplication();
        $section = $section ?: $app->input->getCmd('section', 'footer');
        $language = $this->normaliseLanguage($language ?: $app->input->getString('language'));
        $definition = JemNotificationTemplateCatalog::shared($section);

        if (!$definition || !$this->languageExists($language)) {
            throw new InvalidArgumentException('Unknown shared notification section or inactive language.');
        }

        $custom = $this->loadCustom($definition['id'], $language);
        $params = $custom ? json_decode((string) $custom->params, true) : array();
        $params = is_array($params) ? $params : array();
        $defaultBody = JemNotificationTemplateService::editorDefault($definition['body_key'], $language, array());
        $defaultHtml = JemNotificationTemplateService::editorDefault($definition['htmlbody_key'], $language, array());

        $item = (object) array_merge($definition, array(
            'template_id'    => $definition['id'],
            'language'       => $language,
            'customized'     => (bool) $custom,
            'body'           => $custom ? (string) $custom->body : $defaultBody,
            'htmlbody'       => $custom ? (string) $custom->htmlbody : $defaultHtml,
            'enabled_user'   => isset($params['enabled_user'])
                ? (int) $params['enabled_user']
                : (int) $definition['default_enabled_user'],
            'enabled_admin'  => isset($params['enabled_admin'])
                ? (int) $params['enabled_admin']
                : (int) $definition['default_enabled_admin'],
            'privacy_url'    => (string) ($params['privacy_url'] ?? ''),
        ));

        $sessionData = $app->getUserState('com_jem.edit.notificationcontent.data', array());
        if (is_array($sessionData)
            && ($sessionData['section'] ?? '') === $section
            && ($sessionData['language'] ?? '') === $language) {
            foreach (array('body', 'htmlbody', 'enabled_user', 'enabled_admin', 'privacy_url') as $field) {
                if (array_key_exists($field, $sessionData)) {
                    $item->{$field} = $sessionData[$field];
                }
            }
        }

        return $item;
    }

    public function save(array $data)
    {
        $section = (string) ($data['section'] ?? '');
        $language = $this->normaliseLanguage((string) ($data['language'] ?? ''));
        $definition = JemNotificationTemplateCatalog::shared($section);
        if (!$definition || !$this->languageExists($language)) {
            throw new InvalidArgumentException('Invalid shared notification section or inactive language.');
        }

        $body = trim((string) ($data['body'] ?? ''));
        $htmlbody = trim((string) ($data['htmlbody'] ?? ''));
        $htmlbody = $htmlbody === '' ? '' : ComponentHelper::filterText($htmlbody);
        $enabledUser = empty($data['enabled_user']) ? 0 : 1;
        $enabledAdmin = empty($data['enabled_admin']) ? 0 : 1;
        $privacyUrl = trim((string) ($data['privacy_url'] ?? ''));

        if (($enabledUser || $enabledAdmin) && $body === '') {
            throw new InvalidArgumentException('The plain-text shared content is required while this section is enabled.');
        }
        if ($privacyUrl !== ''
            && (!filter_var($privacyUrl, FILTER_VALIDATE_URL)
                || !in_array(strtolower((string) parse_url($privacyUrl, PHP_URL_SCHEME)), array('http', 'https'), true))) {
            throw new InvalidArgumentException('The privacy policy URL must be an absolute HTTP or HTTPS URL.');
        }

        $errors = array();
        foreach (array('body' => $body, 'htmlbody' => $htmlbody) as $part => $text) {
            $result = JemNotificationTemplateRenderer::validate($text, $definition['allowed_tokens']);
            foreach ($result['errors'] as $error) {
                $errors[] = $part . ':' . $error;
            }
            if (($enabledUser || $enabledAdmin)
                && in_array('privacy_url', $result['variables'], true)
                && $privacyUrl === '') {
                $errors[] = $part . ':privacy_url_required';
            }
        }
        if ($errors) {
            throw new InvalidArgumentException(implode(', ', array_unique($errors)));
        }

        $db = $this->getDatabase();
        $existing = $this->loadCustom($definition['id'], $language);
        $row = (object) array(
            'template_id' => $definition['id'],
            'extension'   => JemNotificationTemplateCatalog::EXTENSION,
            'language'    => $language,
            'subject'     => JemNotificationTemplateService::editorDefault(
                $definition['subject_key'],
                $language,
                array()
            ),
            'body'        => $body,
            'htmlbody'    => $htmlbody,
            'attachments' => $existing ? (string) $existing->attachments : '',
            'params'      => json_encode(array(
                'tags'          => $definition['allowed_tokens'],
                'enabled_user'  => $enabledUser,
                'enabled_admin' => $enabledAdmin,
                'privacy_url'   => $privacyUrl,
            )),
        );

        if ($existing) {
            $db->updateObject('#__mail_templates', $row, array('template_id', 'language'));
        } else {
            $db->insertObject('#__mail_templates', $row);
        }

        Factory::getApplication()->setUserState('com_jem.edit.notificationcontent.data', null);

        return true;
    }

    public function reset($section, $language)
    {
        $definition = JemNotificationTemplateCatalog::shared($section);
        $language = $this->normaliseLanguage($language);
        if (!$definition || !$this->languageExists($language)) {
            throw new InvalidArgumentException('Invalid shared notification section or inactive language.');
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__mail_templates'))
            ->where($db->quoteName('template_id') . ' = ' . $db->quote($definition['id']))
            ->where($db->quoteName('language') . ' = ' . $db->quote($language));
        $db->setQuery($query);
        $db->execute();
        Factory::getApplication()->setUserState('com_jem.edit.notificationcontent.data', null);
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
        if ($this->languageExists($default)) {
            return $default;
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('lang_code'))
            ->from($db->quoteName('#__languages'))
            ->where($db->quoteName('published') . ' = 1')
            ->order($db->quoteName('ordering') . ' ASC');
        $db->setQuery($query);
        foreach ((array) $db->loadColumn() as $candidate) {
            if (JemNotificationTemplateService::hasMailerLanguage($candidate)) {
                return $candidate;
            }
        }

        return 'en-GB';
    }
}
