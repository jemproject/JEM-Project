<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;

/**
 * Administrator list of JEM notification templates.
 */
class JemModelNotificationtemplates extends ListModel
{
    public function __construct($config = array())
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'template_id', 'a.template_id',
                'subject', 'a.subject',
                'body', 'a.body',
                'language',
                'customized',
            );
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = null, $direction = null)
    {
        foreach (array('search', 'workflow', 'recipient', 'language', 'customized') as $filter) {
            $default = $filter === 'language' ? $this->defaultLanguage() : '';
            $value = $this->getUserStateFromRequest(
                $this->context . '.filter_' . $filter,
                'filter_' . $filter,
                $default,
                'string'
            );
            $this->setState('filter_' . $filter, $value);
        }

        parent::populateState('a.template_id', 'asc');
    }

    protected function getStoreId($id = '')
    {
        foreach (array('search', 'workflow', 'recipient', 'language', 'customized') as $filter) {
            $id .= ':' . $this->getState('filter_' . $filter);
        }

        return parent::getStoreId($id);
    }

    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $language = $this->normaliseLanguage((string) $this->getState('filter_language'));
        $query = $db->getQuery(true)
            ->select(array(
                'a.template_id',
                'a.subject AS default_subject_key',
                'a.body AS default_body_key',
                'c.subject AS custom_subject',
                'c.body AS custom_body',
                'c.htmlbody AS custom_htmlbody',
                'CASE WHEN c.template_id IS NULL THEN 0 ELSE 1 END AS customized',
            ))
            ->from($db->quoteName('#__mail_templates', 'a'))
            ->join(
                'LEFT',
                $db->quoteName('#__mail_templates', 'c')
                . ' ON ' . $db->quoteName('c.template_id') . ' = ' . $db->quoteName('a.template_id')
                . ' AND ' . $db->quoteName('c.language') . ' = ' . $db->quote($language)
            )
            ->where($db->quoteName('a.extension') . ' = ' . $db->quote(JemNotificationTemplateCatalog::EXTENSION))
            ->where($db->quoteName('a.language') . ' = ' . $db->quote(''))
            ->where($db->quoteName('a.template_id') . ' IN (' . implode(',', array_map(
                array($db, 'quote'),
                array_keys(JemNotificationTemplateCatalog::all())
            )) . ')');

        if (!JemNotificationTemplateService::hasMailerLanguage($language)) {
            $query->where('1 = 0');
        }

        $search = trim((string) $this->getState('filter_search'));
        if ($search !== '') {
            $like = $db->quote('%' . $db->escape($search, true) . '%');
            $query->where('('
                . $db->quoteName('a.template_id') . ' LIKE ' . $like
                . ' OR ' . $db->quoteName('a.subject') . ' LIKE ' . $like
                . ' OR ' . $db->quoteName('a.body') . ' LIKE ' . $like
                . ')');
        }

        foreach (array('workflow', 'recipient') as $filter) {
            $value = trim((string) $this->getState('filter_' . $filter));
            if ($value === '') {
                continue;
            }

            $ids = array();
            foreach (JemNotificationTemplateCatalog::all() as $definition) {
                if ($definition[$filter] === $value) {
                    $ids[] = $db->quote($definition['id']);
                }
            }
            $query->where($ids ? $db->quoteName('a.template_id') . ' IN (' . implode(',', $ids) . ')' : '1 = 0');
        }

        $customized = (string) $this->getState('filter_customized');
        if ($customized === '1') {
            $query->where($db->quoteName('c.template_id') . ' IS NOT NULL');
        } elseif ($customized === '0') {
            $query->where($db->quoteName('c.template_id') . ' IS NULL');
        }

        $orderCol = $this->state->get('list.ordering', 'a.template_id');
        $orderDir = strtoupper((string) $this->state->get('list.direction', 'ASC'));
        if (!in_array($orderCol, $this->filter_fields, true)) {
            $orderCol = 'a.template_id';
        }
        if (!in_array($orderDir, array('ASC', 'DESC'), true)) {
            $orderDir = 'ASC';
        }

        return $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));
    }

    public function getItems()
    {
        $items = parent::getItems();
        $language = $this->normaliseLanguage((string) $this->getState('filter_language'));

        foreach ($items as $item) {
            $definition = JemNotificationTemplateCatalog::get($item->template_id);
            if (!$definition) {
                continue;
            }

            foreach ($definition as $key => $value) {
                $item->{$key} = $value;
            }

            $item->language = $language;
            $item->display_subject = $item->customized
                ? (string) $item->custom_subject
                : JemNotificationTemplateService::editorDefault(
                    $definition['subject_key'],
                    $language,
                    $definition['subject_legacy_tokens']
                );
        }

        return $items;
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

        foreach ($languages as $language) {
            $language->jem_available = JemNotificationTemplateService::hasMailerLanguage($language->lang_code);
        }

        return $languages;
    }

    private function defaultLanguage()
    {
        $language = (string) ComponentHelper::getParams('com_languages')->get('site', '');

        if ($this->isUsableLanguage($language)) {
            return $language;
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

    private function normaliseLanguage($language)
    {
        return preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', (string) $language)
            ? (string) $language
            : $this->defaultLanguage();
    }

    private function isUsableLanguage($language)
    {
        if (!preg_match('/^[a-z]{2,3}-[A-Z]{2}$/', (string) $language)
            || !JemNotificationTemplateService::hasMailerLanguage($language)) {
            return false;
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__languages'))
            ->where($db->quoteName('lang_code') . ' = ' . $db->quote($language))
            ->where($db->quoteName('published') . ' = 1');
        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }
}
