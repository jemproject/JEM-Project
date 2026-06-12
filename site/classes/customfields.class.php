<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Helper for the configurable legacy JEM custom fields.
 */
class JemCustomFields
{
    /**
     * Cached legacy language files read from INI and Joomla overrides.
     *
     * @var array
     */
    protected static $languageFileCache = array();

    /**
     * Return the decoded custom field settings.
     *
     * @return array
     */
    public static function getConfig()
    {
        $settings = JemHelper::config();
        $raw = $settings->custom_fields_config ?? '';
        $config = self::decodeConfig($raw);

        if ($config) {
            return $config;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('value'))
            ->from($db->quoteName('#__jem_config'))
            ->where($db->quoteName('keyname') . ' = ' . $db->quote('custom_fields_config'));
        $db->setQuery($query);

        try {
            return self::decodeConfig($db->loadResult());
        } catch (Exception $e) {
            return array();
        }
    }

    protected static function decodeConfig($raw)
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_object($raw)) {
            return json_decode(json_encode($raw), true) ?: array();
        }

        if (!is_string($raw) || trim($raw) === '') {
            return array();
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : array();
    }

    /**
     * Normalise posted settings before they are stored in JSON.
     *
     * @param   array  $posted  Posted custom field settings.
     *
     * @return array
     */
    public static function normaliseConfig($posted)
    {
        $posted = is_array($posted) ? $posted : array();
        $languages = self::getLanguageTags();
        $config = array();

        foreach (array('event', 'venue') as $context) {
            $config[$context] = array();

            for ($i = 1; $i <= 10; $i++) {
                $field = 'custom' . $i;
                $source = isset($posted[$context][$field]) && is_array($posted[$context][$field]) ? $posted[$context][$field] : array();

                $labels = array();
                $descriptions = array();

                foreach ($languages as $language) {
                    $label = isset($source['labels'][$language]) ? trim((string) $source['labels'][$language]) : '';
                    $description = isset($source['descriptions'][$language]) ? trim((string) $source['descriptions'][$language]) : '';

                    if ($label !== '') {
                        $labels[$language] = $label;
                    }

                    if ($description !== '') {
                        $descriptions[$language] = $description;
                    }
                }

                $config[$context][$field] = array(
                    'enabled'            => !empty($source['enabled']) ? 1 : 0,
                    'show_backend'       => !empty($source['show_backend']) ? 1 : 0,
                    'show_frontend_edit' => !empty($source['show_frontend_edit']) ? 1 : 0,
                    'show_detail'        => !empty($source['show_detail']) ? 1 : 0,
                    'hide_empty'         => !empty($source['hide_empty']) ? 1 : 0,
                    'labels'             => $labels,
                    'descriptions'       => $descriptions,
                );
            }
        }

        return $config;
    }

    /**
     * Return active Joomla language tags.
     *
     * @return array
     */
    public static function getLanguageTags()
    {
        $tags = array();

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName('lang_code'))
                ->from($db->quoteName('#__languages'))
                ->where($db->quoteName('published') . ' = 1')
                ->order($db->quoteName('ordering') . ' ASC');
            $db->setQuery($query);
            $languages = (array) $db->loadColumn();

            foreach ($languages as $language) {
                if (!empty($language) && self::hasLegacyLanguageIni($language)) {
                    $tags[] = $language;
                }
            }
        } catch (Exception $e) {
            $languages = \Joomla\CMS\Language\LanguageHelper::getContentLanguages(array(1), false);

            foreach ($languages as $language) {
                if (!empty($language->lang_code) && self::hasLegacyLanguageIni($language->lang_code)) {
                    $tags[] = $language->lang_code;
                }
            }
        }

        if (!$tags) {
            $language = Factory::getApplication()->getLanguage()->getTag();

            if (self::hasLegacyLanguageIni($language)) {
                $tags[] = $language;
            }
        }

        return array_values(array_unique($tags));
    }

    protected static function hasLegacyLanguageIni($language)
    {
        foreach (self::getLegacyLanguageFiles($language) as $file) {
            if (is_file($file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a single field configuration with defaults.
     *
     * @param   string  $context  event or venue.
     * @param   string  $field    custom1..custom10.
     *
     * @return array
     */
    public static function getFieldConfig($context, $field)
    {
        $config = self::getConfig();
        $fieldConfig = isset($config[$context][$field]) && is_array($config[$context][$field]) ? $config[$context][$field] : array();

        return array_replace(array(
            'enabled'            => 1,
            'show_backend'       => 1,
            'show_frontend_edit' => 1,
            'show_detail'        => 1,
            'hide_empty'         => 1,
            'labels'             => array(),
            'descriptions'       => array(),
        ), $fieldConfig);
    }

    public static function getLabel($context, $field, $fallback = '')
    {
        return self::getTranslatedValue($context, $field, 'labels', $fallback);
    }

    public static function getDescription($context, $field, $fallback = '')
    {
        return self::getTranslatedValue($context, $field, 'descriptions', $fallback);
    }

    /**
     * Return legacy INI labels/descriptions for the settings preload button.
     *
     * @param   array  $languages  Language tags to export.
     *
     * @return array
     */
    public static function getLegacyLanguageValues(array $languages = array())
    {
        $languages = $languages ?: self::getLanguageTags();
        $values = array();

        foreach (array('event', 'venue') as $context) {
            $values[$context] = array();

            for ($i = 1; $i <= 10; $i++) {
                $field = 'custom' . $i;
                $values[$context][$field] = array(
                    'labels'       => array(),
                    'descriptions' => array(),
                );

                foreach ($languages as $language) {
                    $label = self::getLegacyLanguageValue($context, $field, 'labels', $language, true);
                    $description = self::getLegacyLanguageValue($context, $field, 'descriptions', $language, true);

                    if ($label !== '') {
                        $values[$context][$field]['labels'][$language] = $label;
                    }

                    if ($description !== '') {
                        $values[$context][$field]['descriptions'][$language] = $description;
                    }
                }
            }
        }

        return $values;
    }

    public static function isVisible($context, $field, $scope)
    {
        $config = self::getFieldConfig($context, $field);

        if (empty($config['enabled'])) {
            return false;
        }

        $scopeKey = $scope === 'detail' ? 'show_detail' : ($scope === 'backend' ? 'show_backend' : 'show_frontend_edit');

        return !empty($config[$scopeKey]);
    }

    public static function applyFormLabels($form, $context, $scope)
    {
        if (!$form) {
            return;
        }

        for ($i = 1; $i <= 10; $i++) {
            $field = 'custom' . $i;

            if (!self::isVisible($context, $field, $scope)) {
                if (!$form->removeField($field, 'custom')) {
                    $form->removeField($field);
                }
                continue;
            }

            $prefix = strtoupper($context) === 'EVENT' ? 'COM_JEM_EVENT_CUSTOM_FIELD' : 'COM_JEM_VENUE_CUSTOM_FIELD';
            $label = self::getLabel($context, $field, Text::_($prefix . $i));
            $description = self::getDescription($context, $field, Text::_($prefix . $i . '_DESC'));

            if (!$form->setFieldAttribute($field, 'label', $label, 'custom')) {
                $form->setFieldAttribute($field, 'label', $label);
            }

            if (!$form->setFieldAttribute($field, 'description', $description, 'custom')) {
                $form->setFieldAttribute($field, 'description', $description);
            }
        }
    }

    protected static function getTranslatedValue($context, $field, $key, $fallback)
    {
        $config = self::getFieldConfig($context, $field);
        $values = isset($config[$key]) && is_array($config[$key]) ? $config[$key] : array();
        $language = Factory::getApplication()->getLanguage()->getTag();
        $defaultLanguage = self::getDefaultLanguage();

        if (!empty($values[$language])) {
            return $values[$language];
        }

        if (!empty($values[$defaultLanguage])) {
            return $values[$defaultLanguage];
        }

        foreach ($values as $value) {
            if ($value !== '') {
                return $value;
            }
        }

        $legacyValue = self::getLegacyLanguageValue($context, $field, $key, $language);

        if ($legacyValue !== '') {
            return $legacyValue;
        }

        return $fallback;
    }

    protected static function getDefaultLanguage()
    {
        $languages = self::getLanguageTags();

        return $languages[0] ?? Factory::getApplication()->getLanguage()->getTag();
    }

    protected static function getLegacyLanguageValue($context, $field, $key, $language, $customOnly = false)
    {
        $languageKey = self::getLegacyLanguageKey($context, $field, $key);

        if ($languageKey === '') {
            return '';
        }

        if ($customOnly) {
            return self::getCustomLegacyLanguageValue($languageKey, $language);
        }

        $fallback = '';

        foreach (self::getLegacyLanguageFiles($language) as $file) {
            $strings = self::loadLegacyLanguageFile($file);

            if (!isset($strings[$languageKey])) {
                continue;
            }

            $value = trim((string) $strings[$languageKey]);

            if ($value === '') {
                continue;
            }

            if (self::isCustomLegacyValue($context, $field, $key, $value)) {
                return $value;
            }

            if ($fallback === '') {
                $fallback = $value;
            }
        }

        return $fallback;
    }

    protected static function getCustomLegacyLanguageValue($languageKey, $language)
    {
        $default = self::getPackageLanguageValue($languageKey, $language);

        foreach (self::getCustomLanguageFiles($language) as $file) {
            $strings = self::loadLegacyLanguageFile($file);

            if (!isset($strings[$languageKey])) {
                continue;
            }

            $value = trim((string) $strings[$languageKey]);

            if ($value === '') {
                continue;
            }

            if ($default === '' || $value !== $default) {
                return $value;
            }
        }

        return '';
    }

    protected static function getLegacyLanguageKey($context, $field, $key)
    {
        if (!preg_match('/^custom([1-9]|10)$/', $field, $matches)) {
            return '';
        }

        $prefix = strtolower($context) === 'venue' ? 'COM_JEM_VENUE_CUSTOM_FIELD' : 'COM_JEM_EVENT_CUSTOM_FIELD';
        $suffix = $key === 'descriptions' ? '_DESC' : '';

        return $prefix . (int) $matches[1] . $suffix;
    }

    protected static function getLegacyLanguageFiles($language)
    {
        $app = Factory::getApplication();
        $isAdmin = method_exists($app, 'isClient') ? $app->isClient('administrator') : $app->isAdmin();

        $adminOverride = JPATH_ADMINISTRATOR . '/language/overrides/' . $language . '.override.ini';
        $siteOverride = JPATH_SITE . '/language/overrides/' . $language . '.override.ini';
        $adminIni = JPATH_ADMINISTRATOR . '/language/' . $language . '/com_jem.ini';
        $adminTaggedIni = JPATH_ADMINISTRATOR . '/language/' . $language . '/' . $language . '.com_jem.ini';
        $siteIni = JPATH_SITE . '/language/' . $language . '/com_jem.ini';
        $siteTaggedIni = JPATH_SITE . '/language/' . $language . '/' . $language . '.com_jem.ini';
        $adminComponentIni = JPATH_ADMINISTRATOR . '/components/com_jem/language/' . $language . '/' . $language . '.com_jem.ini';
        $adminComponentPlainIni = JPATH_ADMINISTRATOR . '/components/com_jem/language/' . $language . '/com_jem.ini';
        $siteComponentIni = JPATH_SITE . '/components/com_jem/language/' . $language . '/' . $language . '.com_jem.ini';
        $siteComponentPlainIni = JPATH_SITE . '/components/com_jem/language/' . $language . '/com_jem.ini';

        return $isAdmin
            ? array($adminOverride, $siteOverride, $adminIni, $adminTaggedIni, $siteIni, $siteTaggedIni, $adminComponentIni, $adminComponentPlainIni, $siteComponentIni, $siteComponentPlainIni)
            : array($siteOverride, $adminOverride, $siteIni, $siteTaggedIni, $adminIni, $adminTaggedIni, $siteComponentIni, $siteComponentPlainIni, $adminComponentIni, $adminComponentPlainIni);
    }

    protected static function getCustomLanguageFiles($language)
    {
        $app = Factory::getApplication();
        $isAdmin = method_exists($app, 'isClient') ? $app->isClient('administrator') : $app->isAdmin();

        $adminOverride = JPATH_ADMINISTRATOR . '/language/overrides/' . $language . '.override.ini';
        $siteOverride = JPATH_SITE . '/language/overrides/' . $language . '.override.ini';
        $adminIni = JPATH_ADMINISTRATOR . '/language/' . $language . '/com_jem.ini';
        $adminTaggedIni = JPATH_ADMINISTRATOR . '/language/' . $language . '/' . $language . '.com_jem.ini';
        $siteIni = JPATH_SITE . '/language/' . $language . '/com_jem.ini';
        $siteTaggedIni = JPATH_SITE . '/language/' . $language . '/' . $language . '.com_jem.ini';

        return $isAdmin
            ? array($adminOverride, $siteOverride, $adminIni, $adminTaggedIni, $siteIni, $siteTaggedIni)
            : array($siteOverride, $adminOverride, $siteIni, $siteTaggedIni, $adminIni, $adminTaggedIni);
    }

    protected static function getPackageLanguageValue($languageKey, $language)
    {
        $app = Factory::getApplication();
        $isAdmin = method_exists($app, 'isClient') ? $app->isClient('administrator') : $app->isAdmin();

        $adminComponentIni = JPATH_ADMINISTRATOR . '/components/com_jem/language/' . $language . '/' . $language . '.com_jem.ini';
        $adminComponentPlainIni = JPATH_ADMINISTRATOR . '/components/com_jem/language/' . $language . '/com_jem.ini';
        $siteComponentIni = JPATH_SITE . '/components/com_jem/language/' . $language . '/' . $language . '.com_jem.ini';
        $siteComponentPlainIni = JPATH_SITE . '/components/com_jem/language/' . $language . '/com_jem.ini';
        $files = $isAdmin
            ? array($adminComponentIni, $adminComponentPlainIni, $siteComponentIni, $siteComponentPlainIni)
            : array($siteComponentIni, $siteComponentPlainIni, $adminComponentIni, $adminComponentPlainIni);

        foreach ($files as $file) {
            $strings = self::loadLegacyLanguageFile($file);

            if (!empty($strings[$languageKey])) {
                return trim((string) $strings[$languageKey]);
            }
        }

        return '';
    }

    protected static function loadLegacyLanguageFile($file)
    {
        if (isset(self::$languageFileCache[$file])) {
            return self::$languageFileCache[$file];
        }

        if (!is_file($file) || !is_readable($file)) {
            self::$languageFileCache[$file] = array();

            return array();
        }

        $parsed = @parse_ini_file($file, false, INI_SCANNER_RAW);
        self::$languageFileCache[$file] = is_array($parsed) ? $parsed : array();

        return self::$languageFileCache[$file];
    }

    protected static function getDefaultLegacyValue($context, $field, $key)
    {
        if (!preg_match('/^custom([1-9]|10)$/', $field, $matches)) {
            return '';
        }

        $number = (int) $matches[1];
        $contextLabel = strtolower($context) === 'venue' ? 'Venue' : 'Event';

        return $contextLabel . ' Custom Field ' . $number . ($key === 'descriptions' ? ' Description' : '');
    }

    protected static function isCustomLegacyValue($context, $field, $key, $value)
    {
        $default = self::getDefaultLegacyValue($context, $field, $key);

        if ($default === '') {
            return false;
        }

        return trim((string) $value) !== $default;
    }
}
