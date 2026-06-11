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
        $languages = \Joomla\CMS\Language\LanguageHelper::getContentLanguages(array(0, 1), false);
        $tags = array();

        foreach ($languages as $language) {
            if (!empty($language->lang_code)) {
                $tags[] = $language->lang_code;
            }
        }

        if (!$tags) {
            $tags[] = Factory::getApplication()->getLanguage()->getTag();
        }

        return array_values(array_unique($tags));
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

        return $fallback;
    }

    protected static function getDefaultLanguage()
    {
        $languages = self::getLanguageTags();

        return $languages[0] ?? Factory::getApplication()->getLanguage()->getTag();
    }
}
