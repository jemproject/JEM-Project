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
    const TYPE_TEXT = 'text';
    const TYPE_LINK = 'link';
    const TYPE_TEXTAREA = 'textarea';
    const TYPE_SAFEHTML = 'safehtml';
    const TYPE_LIST = 'list';

    /**
     * Cached legacy language files read from INI and Joomla overrides.
     *
     * @var array
     */
    protected static $languageFileCache = array();

    public static function getTypeOptions()
    {
        return array(
            self::TYPE_TEXT     => Text::_('COM_JEM_CUSTOM_FIELD_TYPE_TEXT'),
            self::TYPE_LINK     => Text::_('COM_JEM_CUSTOM_FIELD_TYPE_LINK'),
            self::TYPE_TEXTAREA => Text::_('COM_JEM_CUSTOM_FIELD_TYPE_TEXTAREA'),
            self::TYPE_SAFEHTML => Text::_('COM_JEM_CUSTOM_FIELD_TYPE_SAFEHTML'),
            self::TYPE_LIST     => Text::_('COM_JEM_CUSTOM_FIELD_TYPE_LIST'),
        );
    }

    /**
     * Return a ready-to-use example configuration for events and venues.
     *
     * @return array
     */
    public static function getExampleConfig()
    {
        $event = array(
            'custom1' => array(self::TYPE_LIST, 'All ages;Children;Teens;Adults;Seniors;Family', 'Audience', 'Recommended audience or age range.'),
            'custom2' => array(self::TYPE_LINK, '', 'External info', 'External event information link.'),
            'custom3' => array(self::TYPE_TEXTAREA, '', 'Preparation notes', 'Short notes to prepare before attending.'),
            'custom4' => array(self::TYPE_LIST, 'Beginner;Intermediate;Advanced;Professional', 'Event level', 'Skill or knowledge level.'),
            'custom5' => array(self::TYPE_SAFEHTML, '', 'Accessibility note', 'Short safe HTML accessibility note.'),
            'custom6' => array(self::TYPE_TEXT, '', 'Equipment', 'Equipment or material needed.'),
            'custom7' => array(self::TYPE_LIST, 'English;Spanish;German;French;Multilingual', 'Language', 'Main language used during the event.'),
            'custom8' => array(self::TYPE_LINK, '', 'Online link', 'Streaming or online meeting link.'),
            'custom9' => array(self::TYPE_TEXT, '', 'Partner', 'Sponsor or partner name.'),
            'custom10' => array(self::TYPE_TEXTAREA, '', 'Cancellation policy', 'Short cancellation or refund note.'),
        );

        $venue = array(
            'custom1' => array(self::TYPE_TEXT, '', 'Room capacity', 'Short venue capacity note.'),
            'custom2' => array(self::TYPE_LINK, '', 'Venue guide', 'External venue information link.'),
            'custom3' => array(self::TYPE_LIST, 'Indoor;Outdoor;Hybrid', 'Venue format', 'Indoor, outdoor or mixed venue format.'),
            'custom4' => array(self::TYPE_LIST, 'None;Street parking;Paid parking;Free parking', 'Parking', 'Parking availability.'),
            'custom5' => array(self::TYPE_TEXTAREA, '', 'Accessibility', 'Accessibility facilities at the venue.'),
            'custom6' => array(self::TYPE_TEXTAREA, '', 'Public transport', 'Public transport instructions.'),
            'custom7' => array(self::TYPE_TEXT, '', 'Room', 'Room, floor or area name.'),
            'custom8' => array(self::TYPE_LIST, 'Projector;Sound system;Stage;Wifi;Kitchen;None', 'Equipment', 'Main equipment available.'),
            'custom9' => array(self::TYPE_TEXT, '', 'Contact desk', 'On-site contact or desk.'),
            'custom10' => array(self::TYPE_TEXTAREA, '', 'Venue rules', 'Short practical venue rules.'),
        );

        return array(
            'event' => self::buildExampleContextConfig($event),
            'venue' => self::buildExampleContextConfig($venue),
        );
    }

    protected static function buildExampleContextConfig($fields)
    {
        $config = array();
        $order = 1;

        foreach ($fields as $field => $definition) {
            $config[$field] = array(
                'order'              => $order++,
                'enabled'            => 1,
                'show_backend'       => 1,
                'show_frontend_edit' => 1,
                'show_detail'        => 1,
                'hide_empty'         => 1,
                'type'               => $definition[0],
                'options'            => $definition[1],
                'labels'             => array('en-GB' => $definition[2]),
                'descriptions'       => array('en-GB' => $definition[3]),
            );
        }

        return $config;
    }

    protected static function normaliseType($type)
    {
        $type = strtolower(trim((string) $type));
        $allowed = array_keys(self::getTypeOptions());

        return in_array($type, $allowed, true) ? $type : self::TYPE_TEXT;
    }

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
                    'order'              => self::normaliseOrder($source['order'] ?? $i, $i),
                    'enabled'            => !empty($source['enabled']) ? 1 : 0,
                    'show_backend'       => !empty($source['show_backend']) ? 1 : 0,
                    'show_frontend_edit' => !empty($source['show_frontend_edit']) ? 1 : 0,
                    'show_detail'        => !empty($source['show_detail']) ? 1 : 0,
                    'hide_empty'         => !empty($source['hide_empty']) ? 1 : 0,
                    'type'               => self::normaliseType($source['type'] ?? self::TYPE_TEXT),
                    'options'            => trim((string) ($source['options'] ?? '')),
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

    protected static function normaliseOrder($order, $fallback)
    {
        $order = (int) $order;

        return $order >= 1 && $order <= 10 ? $order : (int) $fallback;
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
            'order'              => (int) substr($field, 6),
            'enabled'            => 1,
            'show_backend'       => 1,
            'show_frontend_edit' => 1,
            'show_detail'        => 1,
            'hide_empty'         => 1,
            'type'               => self::TYPE_TEXT,
            'options'            => '',
            'labels'             => array(),
            'descriptions'       => array(),
        ), $fieldConfig);
    }

    /**
     * Return custom field names ordered by their configured visual position.
     *
     * Values remain stored in custom1..custom10; this only controls display/edit order.
     *
     * @param   string       $context  event or venue.
     * @param   string|null  $scope    Optional visibility scope.
     *
     * @return array
     */
    public static function getOrderedFields($context, $scope = null)
    {
        $fields = array();

        for ($i = 1; $i <= 10; $i++) {
            $field = 'custom' . $i;

            if ($scope !== null && !self::isVisible($context, $field, $scope)) {
                continue;
            }

            $config = self::getFieldConfig($context, $field);
            $fields[] = array(
                'field' => $field,
                'index' => $i,
                'order' => self::normaliseOrder($config['order'] ?? $i, $i),
            );
        }

        usort($fields, function ($a, $b) {
            if ($a['order'] === $b['order']) {
                return $a['index'] <=> $b['index'];
            }

            return $a['order'] <=> $b['order'];
        });

        return array_column($fields, 'field');
    }

    public static function parseOptions($options)
    {
        $items = array();
        $parts = explode(';', (string) $options);

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (strpos($part, '=') !== false) {
                [$value, $label] = array_map('trim', explode('=', $part, 2));
            } else {
                $value = $part;
                $label = $part;
            }

            if ($value === '') {
                continue;
            }

            $items[$value] = $label !== '' ? $label : $value;
        }

        return $items;
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
        self::applyFormConfig($form, $context, $scope);
    }

    public static function applyFormConfig($form, $context, $scope)
    {
        if (!$form) {
            return;
        }

        foreach (self::getOrderedFields($context) as $field) {
            $i = (int) substr($field, 6);

            if (!self::isVisible($context, $field, $scope)) {
                if (!$form->removeField($field, 'custom')) {
                    $form->removeField($field);
                }
                continue;
            }

            $config = self::getFieldConfig($context, $field);
            $prefix = strtoupper($context) === 'EVENT' ? 'COM_JEM_EVENT_CUSTOM_FIELD' : 'COM_JEM_VENUE_CUSTOM_FIELD';
            $label = self::getLabel($context, $field, Text::_($prefix . $i));
            $description = self::getDescription($context, $field, Text::_($prefix . $i . '_DESC'));

            self::replaceFormField($form, $field, $label, $description, $config);
        }
    }

    protected static function replaceFormField($form, $field, $label, $description, array $config)
    {
        $type = self::normaliseType($config['type'] ?? self::TYPE_TEXT);
        $attributes = array(
            'name'        => $field,
            'type'        => $type === self::TYPE_LINK ? 'url' : ($type === self::TYPE_LIST ? 'list' : ($type === self::TYPE_TEXTAREA || $type === self::TYPE_SAFEHTML ? 'textarea' : 'text')),
            'class'       => 'inputbox',
            'label'       => $label,
            'description' => $description,
        );

        if ($type === self::TYPE_TEXTAREA || $type === self::TYPE_SAFEHTML) {
            $attributes['rows'] = '4';
            $attributes['cols'] = '40';
        } elseif ($type !== self::TYPE_LIST) {
            $attributes['size'] = $type === self::TYPE_LINK ? '40' : '20';
        }

        if ($type === self::TYPE_SAFEHTML) {
            $attributes['filter'] = 'safehtml';
        }

        $xml = '<field';
        foreach ($attributes as $name => $value) {
            $xml .= ' ' . $name . '="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"';
        }

        if ($type === self::TYPE_LIST) {
            $xml .= '>';
            $xml .= '<option value="">' . htmlspecialchars(Text::_('COM_JEM_CUSTOM_FIELD_SELECT_OPTION'), ENT_QUOTES, 'UTF-8') . '</option>';

            foreach (self::parseOptions($config['options'] ?? '') as $value => $optionLabel) {
                $xml .= '<option value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '">'
                    . htmlspecialchars((string) $optionLabel, ENT_QUOTES, 'UTF-8') . '</option>';
            }

            $xml .= '</field>';
        } else {
            $xml .= ' />';
        }

        try {
            $element = new SimpleXMLElement($xml);
            if (!$form->setField($element, null, true, 'custom')) {
                $form->setField($element, null, true);
            }
        } catch (Exception $e) {
            if (!$form->setFieldAttribute($field, 'label', $label, 'custom')) {
                $form->setFieldAttribute($field, 'label', $label);
            }

            if (!$form->setFieldAttribute($field, 'description', $description, 'custom')) {
                $form->setFieldAttribute($field, 'description', $description);
            }
        }
    }

    public static function validateAndSanitizeData($context, array &$data, array &$errors = array())
    {
        $valid = true;

        for ($i = 1; $i <= 10; $i++) {
            $field = 'custom' . $i;

            if (!array_key_exists($field, $data)) {
                continue;
            }

            $config = self::getFieldConfig($context, $field);
            $fieldErrors = array();
            $value = self::sanitizeValue($context, $field, $data[$field], $config, $fieldErrors);
            $data[$field] = $value;

            if (!empty($fieldErrors)) {
                $valid = false;
                $errors = array_merge($errors, $fieldErrors);
            }
        }

        return $valid;
    }

    public static function sanitizeValue($context, $field, $value, array $config = array(), array &$errors = array())
    {
        $config = $config ?: self::getFieldConfig($context, $field);
        $type = self::normaliseType($config['type'] ?? self::TYPE_TEXT);
        $label = self::getLabel($context, $field, $field);
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        switch ($type) {
            case self::TYPE_LINK:
                $value = filter_var($value, FILTER_SANITIZE_URL);
                $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
                $isValidLink = in_array($scheme, array('http', 'https'), true) && filter_var($value, FILTER_VALIDATE_URL);
                $isValidMailto = $scheme === 'mailto' && filter_var(substr($value, 7), FILTER_VALIDATE_EMAIL);

                if (!$isValidLink && !$isValidMailto) {
                    $errors[] = Text::sprintf('COM_JEM_CUSTOM_FIELD_ERROR_INVALID_URL', $label);
                    return '';
                }
                break;

            case self::TYPE_TEXTAREA:
                $value = strip_tags($value);
                break;

            case self::TYPE_SAFEHTML:
                $value = \Joomla\CMS\Filter\InputFilter::getInstance(array(), array(), 1, 1)->clean($value, 'html');
                break;

            case self::TYPE_LIST:
                $options = self::parseOptions($config['options'] ?? '');
                if ($options && !array_key_exists($value, $options)) {
                    $errors[] = Text::sprintf('COM_JEM_CUSTOM_FIELD_ERROR_INVALID_OPTION', $label);
                    return '';
                }
                $value = strip_tags($value);
                break;

            case self::TYPE_TEXT:
            default:
                $value = strip_tags($value);
                break;
        }

        $maxLength = self::getStorageLength($field);
        if (strlen($value) > $maxLength) {
            $errors[] = Text::sprintf('COM_JEM_CUSTOM_FIELD_ERROR_TOO_LONG', $label, $maxLength);
            return substr($value, 0, $maxLength);
        }

        return $value;
    }

    public static function renderValue($context, $field, $value)
    {
        $value = trim((string) $value);

        if ($value === '' || !self::isVisible($context, $field, 'detail')) {
            return '';
        }

        $config = self::getFieldConfig($context, $field);
        $type = self::normaliseType($config['type'] ?? self::TYPE_TEXT);

        if ($type === self::TYPE_LINK) {
            $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
            $isValidLink = in_array($scheme, array('http', 'https'), true) && filter_var($value, FILTER_VALIDATE_URL);
            $isValidMailto = $scheme === 'mailto' && filter_var(substr($value, 7), FILTER_VALIDATE_EMAIL);

            if ($isValidLink || $isValidMailto) {
                return '<a href="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">'
                    . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</a>';
            }
        }

        if ($type === self::TYPE_TEXTAREA) {
            return nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
        }

        if ($type === self::TYPE_SAFEHTML) {
            return \Joomla\CMS\Filter\InputFilter::getInstance(array(), array(), 1, 1)->clean($value, 'html');
        }

        if ($type === self::TYPE_LIST) {
            $options = self::parseOptions($config['options'] ?? '');
            $label = $options[$value] ?? $value;
            return htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        }

        if (preg_match('%^https?://%', $value)) {
            return '<a href="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">'
                . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</a>';
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public static function renderDetailRows($context, $source, $labelPrefix, $classPrefix = 'custom', $tooltip = false, $valuePrefix = 'custom')
    {
        $html = '';

        foreach (self::getOrderedFields($context, 'detail') as $fieldName) {
            $cr = (int) substr($fieldName, 6);
            $property = $valuePrefix . $cr;
            $currentRow = self::renderValue($context, $fieldName, $source->{$property} ?? '');

            if ($currentRow === '') {
                continue;
            }

            $fieldLabel = self::getLabel($context, $fieldName, Text::_($labelPrefix . $cr));
            $class = htmlspecialchars($classPrefix . $cr, ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8');
            $tooltipAttr = $tooltip ? ' hasTooltip" data-original-title="' . $label : '';

            $html .= '<dt class="' . $class . $tooltipAttr . '">' . $label . ':</dt>';
            $html .= '<dd class="' . $class . '">' . $currentRow . '</dd>';
        }

        return $html;
    }

    protected static function getStorageLength($field)
    {
        return in_array($field, array('custom1', 'custom2'), true) ? 200 : 100;
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
