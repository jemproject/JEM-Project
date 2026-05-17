<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Table;

require_once __DIR__ . '/admin.php';

class JemModelType extends JemModelAdmin
{
    protected function canDelete($record)
    {
        if (!empty($record->id)) {
            return JemFactory::getUser()->authorise('core.delete', 'com_jem');
        }
        return false;
    }

    public function getTable($name = 'jem_types', $prefix = '', $options = array())
    {
        return Table::getInstance($name, '', $options);
    }

    public function getForm($data = array(), $loadData = true)
    {
        $form = $this->loadForm('com_jem.type', 'type', array('control' => 'jform', 'load_data' => $loadData));
        if (empty($form)) {
            return false;
        }
        return $form;
    }

    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_jem.edit.type.data', array());
        if (empty($data)) {
            $data = $this->getItem();
        }

        if (empty($data->base_language)) {
            $data->base_language = $this->getDefaultSiteLanguage();
        }

        return $data;
    }

    protected function prepareTable($table)
    {
        $date = Factory::getDate();
        $user = JemFactory::getUser();

        if (empty($table->id)) {
            $table->created    = $date->toSql();
            $table->created_by = $user->get('id');
        } else {
            $table->modified    = $date->toSql();
            $table->modified_by = $user->get('id');
        }

        if (empty($table->base_language)) {
            $table->base_language = $this->getDefaultSiteLanguage();
        }

        $translations = json_decode((string) $table->translations, true);
        if (!is_array($translations)) {
            $translations = array();
        }

        $cleanTranslations = array();
        foreach ($translations as $language => $translation) {
            if (!is_array($translation)) {
                continue;
            }

            $language = trim((string) $language);
            $name = trim((string) ($translation['name'] ?? ''));
            $description = trim((string) ($translation['description'] ?? ''));

            if ($language === '' || ($name === '' && $description === '')) {
                continue;
            }

            $cleanTranslations[$language] = array(
                'name' => $name,
                'description' => $description,
            );
        }

        $table->translations = $cleanTranslations ? json_encode($cleanTranslations, JSON_UNESCAPED_UNICODE) : null;
        $table->translation_languages = $cleanTranslations ? implode(',', array_keys($cleanTranslations)) : null;
    }

    private function getDefaultSiteLanguage()
    {
        $language = (string) ComponentHelper::getParams('com_languages')->get('site', '');

        if ($language === '') {
            $language = Factory::getApplication()->getLanguage()->getTag();
        }

        return $language;
    }
}
