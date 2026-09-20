<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Event\CustomFields\PrepareDomEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Event\DispatcherInterface;

$customFieldsFile = defined('JPATH_SITE')
    ? JPATH_SITE . '/components/com_jem/classes/customfields.class.php'
    : __DIR__ . '/customfields.class.php';
require_once $customFieldsFile;

/**
 * Resolves category-controlled event fields and Joomla venue fields.
 *
 * Category configuration never owns field values. Legacy values remain in the
 * JEM tables and Joomla field values remain managed by com_fields.
 */
class JemCategoryCustomFields
{
    public const CONTEXT_EVENT = 'com_jem.event';
    public const CONTEXT_VENUE = 'com_jem.venue';
    public const MODE_GLOBAL = 'global';
    public const MODE_SELECTION = 'selection';
    public const MODE_CUSTOM = 'custom';
    public const MODE_JOOMLA = 'joomla';
    public const MODE_NONE = 'none';

    /**
     * Cached non-default category configurations.
     *
     * @var array|null
     */
    protected static $categoryConfigurations;

    /**
     * Cached published Joomla event fields.
     *
     * @var array|null
     */
    protected static $joomlaFields;

    /**
     * Whether Joomla's field service was reached successfully in this request.
     *
     * @var boolean|null
     */
    protected static $joomlaFieldsAvailable;

    /**
     * Cached published Joomla event field groups.
     *
     * @var array|null
     */
    protected static $joomlaEventFieldGroups;

    /**
     * Cached published Joomla venue fields.
     *
     * @var array|null
     */
    protected static $joomlaVenueFields;

    /**
     * Whether Joomla's venue field service was reached successfully.
     *
     * @var boolean|null
     */
    protected static $joomlaVenueFieldsAvailable;

    /**
     * Cached published Joomla venue field groups.
     *
     * @var array|null
     */
    protected static $joomlaVenueFieldGroups;

    /**
     * Return a canonical category configuration.
     *
     * Empty and legacy rows intentionally resolve to the global JEM fields.
     *
     * @param   mixed    $raw               JSON string or decoded value.
     * @param   boolean  $validateFieldIds  Validate ids against their provider.
     *
     * @return array
     */
    public static function normaliseConfiguration($raw, $validateFieldIds = false)
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : array();
        } elseif (is_object($raw)) {
            $raw = (array) $raw;
        }

        $raw = is_array($raw) ? $raw : array();
        $mode = strtolower(trim((string) ($raw['mode'] ?? self::MODE_GLOBAL)));

        if (!in_array(
            $mode,
            array(self::MODE_GLOBAL, self::MODE_SELECTION, self::MODE_CUSTOM, self::MODE_JOOMLA, self::MODE_NONE),
            true
        )) {
            $mode = self::MODE_GLOBAL;
        }

        $legacyFieldIds = self::normalisePositiveIds($raw['jem_field_ids'] ?? array());
        $joomlaFieldIds = self::normalisePositiveIds($raw['joomla_field_ids'] ?? array());
        $joomlaGroupIds = self::normalisePositiveIds($raw['joomla_group_ids'] ?? array());
        $legacyIds = self::normalisePositiveIds($raw['field_ids'] ?? array());

        if ($mode === self::MODE_CUSTOM) {
            $mode = self::MODE_SELECTION;
            $legacyFieldIds = $legacyIds;
        } elseif ($mode === self::MODE_JOOMLA) {
            $mode = self::MODE_SELECTION;
            $joomlaFieldIds = $legacyIds;
        }

        if ($mode === self::MODE_SELECTION) {
            $legacyFieldIds = array_values(array_filter($legacyFieldIds, static function ($fieldId) {
                return $fieldId >= 1 && $fieldId <= 10;
            }));

            if ($validateFieldIds) {
                $legacyFieldIds = array_values(array_intersect(
                    $legacyFieldIds,
                    self::getEnabledLegacyFieldIds()
                ));
                $validFields = self::getJoomlaEventFieldsById();

                if (self::$joomlaFieldsAvailable !== false) {
                    $joomlaFieldIds = array_values(array_intersect(
                        $joomlaFieldIds,
                        array_map('intval', array_keys($validFields))
                    ));
                    $joomlaGroupIds = array_values(array_intersect(
                        $joomlaGroupIds,
                        array_map('intval', array_keys(self::getJoomlaEventFieldGroupsById()))
                    ));
                }
            }
        } else {
            $legacyFieldIds = array();
            $joomlaFieldIds = array();
            $joomlaGroupIds = array();
        }

        sort($legacyFieldIds, SORT_NUMERIC);
        sort($joomlaFieldIds, SORT_NUMERIC);
        sort($joomlaGroupIds, SORT_NUMERIC);

        return array(
            'mode'             => $mode,
            'jem_field_ids'    => $legacyFieldIds,
            'joomla_field_ids' => $joomlaFieldIds,
            'joomla_group_ids' => $joomlaGroupIds,
        );
    }

    /**
     * Encode submitted configuration after applying the server-side allowlist.
     *
     * @param   mixed  $raw  Submitted JSON value.
     *
     * @return string
     */
    public static function encodeConfiguration($raw)
    {
        return json_encode(self::normaliseConfiguration($raw, true));
    }

    /**
     * Forget request-local configuration caches after a category is saved.
     *
     * @return void
     */
    public static function clearCache()
    {
        self::$categoryConfigurations = null;
        self::$joomlaFields = null;
        self::$joomlaFieldsAvailable = null;
        self::$joomlaEventFieldGroups = null;
        self::$joomlaVenueFields = null;
        self::$joomlaVenueFieldsAvailable = null;
        self::$joomlaVenueFieldGroups = null;
    }

    /**
     * Load explicit category configurations. Missing rows use MODE_GLOBAL.
     *
     * @return array  Configuration indexed by category id.
     */
    public static function getCategoryConfigurations()
    {
        if (self::$categoryConfigurations !== null) {
            return self::$categoryConfigurations;
        }

        self::$categoryConfigurations = array();

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select(array($db->quoteName('id'), $db->quoteName('custom_fields')))
                ->from($db->quoteName('#__jem_categories'))
                ->where($db->quoteName('custom_fields') . ' IS NOT NULL')
                ->where($db->quoteName('custom_fields') . ' <> ' . $db->quote(''));
            $db->setQuery($query);

            foreach ((array) $db->loadObjectList() as $category) {
                self::$categoryConfigurations[(int) $category->id] = self::normaliseConfiguration($category->custom_fields);
            }
        } catch (Throwable $error) {
            // Upgrade-safe fallback: existing installations continue to use
            // the global JEM fields until the additive column is available.
        }

        return self::$categoryConfigurations;
    }

    /**
     * Resolve selected category ids from a form item, category list or request.
     *
     * @param   mixed  $source  Form data or category objects.
     * @param   mixed  $form    Optional Joomla form.
     *
     * @return array
     */
    public static function getCategoryIds($source = null, $form = null)
    {
        $categoryIds = array();

        if (is_object($source)) {
            if (isset($source->cats)) {
                $categoryIds = self::normaliseIds($source->cats);
            } elseif (isset($source->categories)) {
                $categoryIds = self::normaliseIds($source->categories);
            } elseif (isset($source->catid)) {
                $categoryIds = self::normaliseIds($source->catid);
            }
        } elseif (is_array($source)) {
            if (array_key_exists('cats', $source)) {
                $categoryIds = self::normaliseIds($source['cats']);
            } elseif (array_key_exists('categories', $source)) {
                $categoryIds = self::normaliseIds($source['categories']);
            } elseif (array_key_exists('catid', $source)) {
                $categoryIds = self::normaliseIds($source['catid']);
            } else {
                $categoryIds = self::normaliseIds($source);
            }
        }

        if (!$categoryIds && $form) {
            $categoryIds = self::normaliseIds($form->getValue('cats'));
        }

        if (!$categoryIds) {
            $posted = Factory::getApplication()->input->post->get('jform', array(), 'array');
            $categoryIds = self::normaliseIds($posted['cats'] ?? array());
        }

        if (!$categoryIds) {
            $categoryId = Factory::getApplication()->input->getInt('catid', 0);
            $categoryIds = $categoryId > 0 ? array($categoryId) : array();
        }

        return $categoryIds;
    }

    /**
     * Return the legacy JEM field indexes enabled by the selected categories.
     *
     * @param   array  $categoryIds  Selected category ids.
     *
     * @return array
     */
    public static function getLegacyFieldIdsForCategories(array $categoryIds)
    {
        $globalIds = self::getEnabledLegacyFieldIds();

        if (!$categoryIds) {
            return $globalIds;
        }

        $configurations = self::getCategoryConfigurations();
        $fieldIds = array();

        foreach ($categoryIds as $categoryId) {
            $configuration = $configurations[(int) $categoryId] ?? self::normaliseConfiguration(null);

            if ($configuration['mode'] === self::MODE_GLOBAL) {
                $fieldIds = array_merge($fieldIds, $globalIds);
            } elseif ($configuration['mode'] === self::MODE_SELECTION) {
                $fieldIds = array_merge($fieldIds, $configuration['jem_field_ids']);
            }
        }

        $fieldIds = array_values(array_unique(array_intersect(array_map('intval', $fieldIds), $globalIds)));
        sort($fieldIds, SORT_NUMERIC);

        return $fieldIds;
    }

    /**
     * Return ordered legacy field names enabled by the selected categories.
     *
     * @param   array        $categoryIds  Selected category ids.
     * @param   string|null  $scope        Visibility scope.
     *
     * @return array
     */
    public static function getLegacyFieldNamesForCategories(array $categoryIds, $scope = null)
    {
        $allowed = array_flip(self::getLegacyFieldIdsForCategories($categoryIds));

        return array_values(array_filter(
            JemCustomFields::getOrderedFields('event', $scope),
            static function ($fieldName) use ($allowed) {
                return isset($allowed[(int) substr($fieldName, 6)]);
            }
        ));
    }

    /**
     * Return Joomla field ids enabled by the selected categories.
     *
     * @param   array  $categoryIds  Selected category ids.
     *
     * @return array
     */
    public static function getJoomlaFieldIdsForCategories(array $categoryIds)
    {
        if (!$categoryIds) {
            return array();
        }

        $configurations = self::getCategoryConfigurations();
        $fieldIds = array();

        foreach ($categoryIds as $categoryId) {
            $configuration = $configurations[(int) $categoryId] ?? self::normaliseConfiguration(null);

            if ($configuration['mode'] === self::MODE_SELECTION) {
                $fieldIds = array_merge($fieldIds, $configuration['joomla_field_ids']);

                if ($configuration['joomla_group_ids']) {
                    $groupLookup = array_flip($configuration['joomla_group_ids']);

                    foreach (self::getJoomlaEventFieldsById() as $fieldId => $field) {
                        if (isset($groupLookup[(int) ($field->group_id ?? 0)])) {
                            $fieldIds[] = (int) $fieldId;
                        }
                    }
                }
            }
        }

        $validIds = array_map('intval', array_keys(self::getJoomlaEventFieldsById()));
        $fieldIds = array_values(array_unique(array_intersect(array_map('intval', $fieldIds), $validIds)));
        sort($fieldIds, SORT_NUMERIC);

        return $fieldIds;
    }

    /**
     * Return published Joomla custom fields for com_jem.event.
     *
     * @param   mixed    $item          Optional event item.
     * @param   boolean  $prepareValue  Prepare values for display.
     *
     * @return array
     */
    public static function getJoomlaEventFields($item = null, $prepareValue = false)
    {
        try {
            Factory::getApplication()->bootComponent('com_fields');

            if (!class_exists(FieldsHelper::class)) {
                self::$joomlaFieldsAvailable = false;

                return array();
            }

            if (is_array($item)) {
                $item = (object) $item;
            } elseif (!is_object($item)) {
                $item = null;
            } else {
                $item = clone $item;
            }

            // JEM category selection is stored on the JEM category, not in
            // Joomla's #__fields_categories relation.
            if ($item) {
                unset($item->catid, $item->fieldscatid);
            }

            $fields = (array) FieldsHelper::getFields(self::CONTEXT_EVENT, $item, $prepareValue);
            self::$joomlaFieldsAvailable = true;

            return $fields;
        } catch (Throwable $error) {
            self::$joomlaFieldsAvailable = false;

            return array();
        }
    }

    /**
     * Return available Joomla fields indexed by their database id.
     *
     * @return array
     */
    public static function getJoomlaEventFieldsById()
    {
        if (self::$joomlaFields !== null) {
            return self::$joomlaFields;
        }

        self::$joomlaFields = array();

        foreach (self::getJoomlaEventFields() as $field) {
            $fieldId = (int) ($field->id ?? 0);

            if ($fieldId > 0 && (string) ($field->context ?? '') === self::CONTEXT_EVENT) {
                self::$joomlaFields[$fieldId] = $field;
            }
        }

        return self::$joomlaFields;
    }

    /**
     * Return published Joomla event field groups indexed by id and ordered by Joomla.
     *
     * @return array
     */
    public static function getJoomlaEventFieldGroupsById()
    {
        if (self::$joomlaEventFieldGroups === null) {
            self::$joomlaEventFieldGroups = self::getJoomlaFieldGroupsByContext(self::CONTEXT_EVENT);
        }

        return self::$joomlaEventFieldGroups;
    }

    /**
     * Return published Joomla custom fields for com_jem.venue.
     *
     * @param   mixed    $item          Optional venue item.
     * @param   boolean  $prepareValue  Prepare values for display.
     *
     * @return array
     */
    public static function getJoomlaVenueFields($item = null, $prepareValue = false)
    {
        try {
            Factory::getApplication()->bootComponent('com_fields');

            if (!class_exists(FieldsHelper::class)) {
                self::$joomlaVenueFieldsAvailable = false;

                return array();
            }

            if (is_array($item)) {
                $item = (object) $item;
            } elseif (!is_object($item)) {
                $item = null;
            } else {
                $item = clone $item;
            }

            if ($item) {
                unset($item->catid, $item->fieldscatid);
            }

            $fields = (array) FieldsHelper::getFields(self::CONTEXT_VENUE, $item, $prepareValue);
            self::$joomlaVenueFieldsAvailable = true;

            return $fields;
        } catch (Throwable $error) {
            self::$joomlaVenueFieldsAvailable = false;

            return array();
        }
    }

    /**
     * Return available Joomla venue fields indexed by database id.
     *
     * @return array
     */
    public static function getJoomlaVenueFieldsById()
    {
        if (self::$joomlaVenueFields !== null) {
            return self::$joomlaVenueFields;
        }

        self::$joomlaVenueFields = array();

        foreach (self::getJoomlaVenueFields() as $field) {
            $fieldId = (int) ($field->id ?? 0);

            if ($fieldId > 0 && (string) ($field->context ?? '') === self::CONTEXT_VENUE) {
                self::$joomlaVenueFields[$fieldId] = $field;
            }
        }

        return self::$joomlaVenueFields;
    }

    /**
     * Return published Joomla venue field groups indexed by id and ordered by Joomla.
     *
     * @return array
     */
    public static function getJoomlaVenueFieldGroupsById()
    {
        if (self::$joomlaVenueFieldGroups === null) {
            self::$joomlaVenueFieldGroups = self::getJoomlaFieldGroupsByContext(self::CONTEXT_VENUE);
        }

        return self::$joomlaVenueFieldGroups;
    }

    /**
     * Load published Joomla field groups through the component model.
     *
     * @param   string  $context  Joomla custom-field context.
     *
     * @return array
     */
    protected static function getJoomlaFieldGroupsByContext($context)
    {
        $groups = array();

        try {
            $model = Factory::getApplication()->bootComponent('com_fields')
                ->getMVCFactory()->createModel('Groups', 'Administrator', array('ignore_request' => true));
            $model->setState('filter.context', $context);
            $model->setState('filter.state', 1);
            $model->setState('list.limit', 0);

            foreach ((array) $model->getItems() as $group) {
                $groupId = (int) ($group->id ?? 0);

                if ($groupId > 0) {
                    $groups[$groupId] = $group;
                }
            }
        } catch (Throwable $error) {
            return array();
        }

        return $groups;
    }

    /**
     * Add only the fields enabled by the event's selected categories.
     * Inactive stored values never enter the edit-form DOM.
     *
     * @param   mixed  $form  Joomla form.
     * @param   mixed  $data  Event form data.
     *
     * @return void
     */
    public static function prepareEventForm($form, $data = array())
    {
        if (!$form) {
            return;
        }

        $eventData = self::normaliseEventData($data, $form);
        $categoryIds = self::getCategoryIds($eventData, $form);
        $activeJoomlaIds = self::getJoomlaFieldIdsForCategories($categoryIds);
        $scope = Factory::getApplication()->isClient('administrator') ? 'backend' : 'frontend_edit';
        $activeLegacyNames = self::getLegacyFieldNamesForCategories($categoryIds, $scope);
        $activeLegacyLookup = array_flip($activeLegacyNames);

        for ($i = 1; $i <= 10; $i++) {
            $fieldName = 'custom' . $i;

            if (!isset($activeLegacyLookup[$fieldName])) {
                if (!$form->removeField($fieldName, 'custom')) {
                    $form->removeField($fieldName);
                }
            }
        }

        self::restoreMissingLegacyValues($form, $eventData, $activeLegacyNames);
        $fieldsById = array();

        foreach (self::getJoomlaEventFields($eventData) as $field) {
            $fieldId = (int) ($field->id ?? 0);

            if ($fieldId > 0 && (string) ($field->context ?? '') === self::CONTEXT_EVENT) {
                $fieldsById[$fieldId] = $field;
            }
        }

        $fieldsByName = array();

        foreach ($fieldsById as $fieldId => $field) {
            if (in_array((int) $fieldId, $activeJoomlaIds, true)) {
                $fieldsByName[(string) $field->name] = $field;
            }
        }

        foreach ((array) $form->getGroup('com_fields') as $formField) {
            if (!isset($fieldsByName[$formField->fieldname])) {
                $form->removeField($formField->fieldname, 'com_fields');
            }
        }

        $missingFields = array();
        foreach ($fieldsByName as $field) {
            if (!$form->getField($field->name, 'com_fields')) {
                $missingFields[] = $field;
            }
        }

        self::appendJoomlaFieldsToForm($form, $missingFields, self::CONTEXT_EVENT);

        $submittedFields = isset($eventData->com_fields) && is_array($eventData->com_fields)
            ? $eventData->com_fields
            : array();

        foreach ($fieldsByName as $field) {
            $formField = $form->getField($field->name, 'com_fields');

            if (!$formField) {
                continue;
            }

            $fieldId = (int) $field->id;

            $form->setFieldAttribute($field->name, 'data-jem-field-id', (string) $fieldId, 'com_fields');

            if (array_key_exists($field->name, $submittedFields)) {
                $form->setValue($field->name, 'com_fields', $submittedFields[$field->name]);
            } elseif ($form->getValue($field->name, 'com_fields') !== null) {
                // Keep data already bound by Joomla, including values restored
                // after validation fails.
            } elseif (isset($eventData->id) && (int) $eventData->id > 0 && property_exists($field, 'rawvalue')) {
                $form->setValue($field->name, 'com_fields', $field->rawvalue);
            }
        }
    }

    /**
     * Add published Joomla venue fields to a venue edit form.
     *
     * Venue fields are global to the venue context. JEM category field
     * selection is intentionally limited to events.
     *
     * @param   mixed  $form  Joomla form.
     * @param   mixed  $data  Venue form data.
     *
     * @return void
     */
    public static function prepareVenueForm($form, $data = array())
    {
        if (!$form) {
            return;
        }

        $venueData = self::normaliseItemData($data, $form);
        $fieldsByName = array();

        foreach (self::getJoomlaVenueFields($venueData) as $field) {
            $fieldId = (int) ($field->id ?? 0);

            if ($fieldId > 0 && (string) ($field->context ?? '') === self::CONTEXT_VENUE) {
                $fieldsByName[(string) $field->name] = $field;
            }
        }

        foreach ((array) $form->getGroup('com_fields') as $formField) {
            if (!isset($fieldsByName[$formField->fieldname])) {
                $form->removeField($formField->fieldname, 'com_fields');
            }
        }

        $missingFields = array();

        foreach ($fieldsByName as $field) {
            if (!$form->getField($field->name, 'com_fields')) {
                $missingFields[] = $field;
            }
        }

        self::appendJoomlaFieldsToForm($form, $missingFields, self::CONTEXT_VENUE);

        $submittedFields = isset($venueData->com_fields) && is_array($venueData->com_fields)
            ? $venueData->com_fields
            : array();

        foreach ($fieldsByName as $field) {
            $formField = $form->getField($field->name, 'com_fields');

            if (!$formField) {
                continue;
            }

            $fieldId = (int) $field->id;

            $form->setFieldAttribute($field->name, 'data-jem-field-id', (string) $fieldId, 'com_fields');

            if (array_key_exists($field->name, $submittedFields)) {
                $form->setValue($field->name, 'com_fields', $submittedFields[$field->name]);
            } elseif ($form->getValue($field->name, 'com_fields') !== null) {
                // Keep values already bound by Joomla after validation errors.
            } elseif (isset($venueData->id) && (int) $venueData->id > 0 && property_exists($field, 'rawvalue')) {
                $form->setValue($field->name, 'com_fields', $field->rawvalue);
            }
        }
    }

    /**
     * Remove submitted values which are not enabled by the selected categories.
     * Stored inactive values are deliberately left untouched.
     *
     * @param   array  $data         Event data passed by reference.
     * @param   array  $categoryIds  Selected categories.
     *
     * @return void
     */
    public static function filterEventData(array &$data, array $categoryIds)
    {
        $legacyFields = array_flip(self::getLegacyFieldNamesForCategories($categoryIds));

        for ($i = 1; $i <= 10; $i++) {
            $fieldName = 'custom' . $i;

            if (!isset($legacyFields[$fieldName])) {
                unset($data[$fieldName]);
            }
        }

        if (!isset($data['com_fields']) || !is_array($data['com_fields'])) {
            return;
        }

        $activeIds = self::getJoomlaFieldIdsForCategories($categoryIds);
        $activeNames = array();
        $fieldsById = self::getJoomlaEventFieldsById();

        foreach ($activeIds as $fieldId) {
            if (isset($fieldsById[$fieldId])) {
                $activeNames[(string) $fieldsById[$fieldId]->name] = true;
            }
        }

        foreach (array_keys($data['com_fields']) as $fieldName) {
            if (!isset($activeNames[$fieldName])) {
                unset($data['com_fields'][$fieldName]);
            }
        }
    }

    /**
     * Remove submitted values which do not belong to the venue context.
     *
     * @param   array  $data  Venue data passed by reference.
     *
     * @return void
     */
    public static function filterVenueData(array &$data)
    {
        if (!isset($data['com_fields']) || !is_array($data['com_fields'])) {
            return;
        }

        $activeNames = array();

        foreach (self::getJoomlaVenueFieldsById() as $field) {
            $activeNames[(string) $field->name] = true;
        }

        foreach (array_keys($data['com_fields']) as $fieldName) {
            if (!isset($activeNames[$fieldName])) {
                unset($data['com_fields'][$fieldName]);
            }
        }
    }

    /**
     * Render selected Joomla fields as rows compatible with JEM event details.
     *
     * @param   object  $item         Event item.
     * @param   array   $categoryIds  Selected categories.
     * @param   string  $classPrefix  CSS class prefix.
     * @param   boolean $tooltip      Add the legacy tooltip attributes.
     *
     * @return string
     */
    public static function renderJoomlaDetailRows($item, array $categoryIds, $classPrefix = 'joomla-custom-', $tooltip = false)
    {
        return self::getJoomlaEventDetailPresentation(
            $item,
            $categoryIds,
            $classPrefix,
            $tooltip
        )['rows'];
    }

    /**
     * Build ungrouped rows and grouped cards for Joomla event fields.
     *
     * @param   object   $item         Event item.
     * @param   array    $categoryIds  Selected categories.
     * @param   string   $classPrefix  CSS class prefix.
     * @param   boolean  $tooltip      Add the legacy tooltip attributes.
     *
     * @return array
     */
    public static function getJoomlaEventDetailPresentation(
        $item,
        array $categoryIds,
        $classPrefix = 'joomla-custom-',
        $tooltip = false
    ) {
        $allowedIds = array_flip(self::getJoomlaFieldIdsForCategories($categoryIds));

        if (!$allowedIds) {
            return array('rows' => '', 'cards' => '');
        }

        return self::buildJoomlaDetailPresentation(
            self::getJoomlaEventFields($item, true),
            $allowedIds,
            $classPrefix,
            $tooltip,
            'event'
        );
    }

    /**
     * Render Joomla venue fields as rows compatible with JEM venue details.
     *
     * @param   object   $item         Venue item.
     * @param   string   $classPrefix  CSS class prefix.
     * @param   boolean  $tooltip      Add the legacy tooltip attributes.
     *
     * @return string
     */
    public static function renderJoomlaVenueDetailRows($item, $classPrefix = 'joomla-custom-', $tooltip = false)
    {
        return self::getJoomlaVenueDetailPresentation($item, $classPrefix, $tooltip)['rows'];
    }

    /**
     * Build ungrouped rows and grouped cards for Joomla venue fields.
     *
     * @param   object   $item         Venue item.
     * @param   string   $classPrefix  CSS class prefix.
     * @param   boolean  $tooltip      Add the legacy tooltip attributes.
     *
     * @return array
     */
    public static function getJoomlaVenueDetailPresentation(
        $item,
        $classPrefix = 'joomla-custom-',
        $tooltip = false
    ) {
        return self::buildJoomlaDetailPresentation(
            self::getJoomlaVenueFields($item, true),
            null,
            $classPrefix,
            $tooltip,
            'venue'
        );
    }

    /**
     * Return a safe custom-field presentation sequence.
     *
     * @param   string  $order  Stored presentation-order value.
     *
     * @return array
     */
    public static function normalisePresentationOrder($order)
    {
        $orders = array(
            'jem_joomla_groups',
            'jem_groups_joomla',
            'joomla_jem_groups',
            'joomla_groups_jem',
            'groups_jem_joomla',
            'groups_joomla_jem',
        );

        if (!in_array($order, $orders, true)) {
            $order = 'jem_joomla_groups';
        }

        return explode('_', $order);
    }

    /**
     * Render JEM rows, ungrouped Joomla rows and Joomla Field Group cards in
     * the configured order.
     *
     * @param   array   $parts      HTML indexed by jem, joomla and groups.
     * @param   string  $order      Stored presentation-order value.
     * @param   string  $listClass  Definition-list CSS class.
     *
     * @return string
     */
    public static function renderOrderedDetailSections(array $parts, $order, $listClass)
    {
        $rows = self::renderOrderedDetailRows($parts, $order);

        if ($rows === '') {
            return '';
        }

        $listClass = htmlspecialchars($listClass, ENT_QUOTES, 'UTF-8');

        return '<dl class="' . $listClass . '">' . $rows . '</dl>';
    }

    /**
     * Return custom-field rows in the configured provider order.
     *
     * @param   array   $parts  HTML indexed by jem, joomla and groups.
     * @param   string  $order  Stored presentation-order value.
     *
     * @return string
     */
    public static function renderOrderedDetailRows(array $parts, $order)
    {
        $html = '';

        foreach (self::normalisePresentationOrder((string) $order) as $section) {
            $content = (string) ($parts[$section] ?? '');

            if ($content !== '') {
                $html .= $content;
            }
        }

        return $html;
    }

    /**
     * Mark the first custom-field pair so a detail list can add one subtle
     * separator without changing its shared label/value columns.
     *
     * @param   string  $rows  Definition-list rows.
     *
     * @return string
     */
    public static function addDetailSeparator($rows)
    {
        $rows = (string) $rows;

        if ($rows === '') {
            return '';
        }

        $rows = preg_replace('/<dt class="/', '<dt class="jem-custom-fields-start ', $rows, 1);
        $rows = preg_replace('/<dd class="/', '<dd class="jem-custom-fields-start ', $rows, 1);

        return $rows;
    }

    /**
     * Split Joomla fields into normal rows and cards keyed by Field Group.
     *
     * @param   array       $fields        Prepared Joomla fields.
     * @param   array|null  $allowedIds    Optional field-id allowlist.
     * @param   string      $classPrefix   CSS class prefix.
     * @param   boolean     $tooltip       Add legacy tooltip attributes.
     * @param   string      $entity        Event or venue.
     *
     * @return array
     */
    protected static function buildJoomlaDetailPresentation(
        array $fields,
        $allowedIds,
        $classPrefix,
        $tooltip,
        $entity
    ) {
        $rows = '';
        $groups = array();

        foreach ($fields as $field) {
            $fieldId = (int) ($field->id ?? 0);
            $value = trim((string) ($field->value ?? ''));

            if ($fieldId < 1 || $value === '' || ($allowedIds !== null && !isset($allowedIds[$fieldId]))) {
                continue;
            }

            $groupId = max(0, (int) ($field->group_id ?? 0));

            if ($groupId === 0) {
                $rows .= self::renderJoomlaDetailRow($field, $classPrefix, $tooltip);
                continue;
            }

            if (!isset($groups[$groupId])) {
                $groups[$groupId] = array(
                    'title' => Text::_((string) ($field->group_title ?? 'COM_JEM_CUSTOMFIELDS')),
                    'rows'  => '',
                );
            }

            $groups[$groupId]['rows'] .= self::renderJoomlaGroupedDetailRow(
                $field,
                $classPrefix,
                $entity,
                $groupId
            );
        }

        if (!$groups) {
            return array(
                'rows'  => $rows,
                'cards' => '',
            );
        }

        $groupDefinitions = $entity === 'event'
            ? self::getJoomlaEventFieldGroupsById()
            : self::getJoomlaVenueFieldGroupsById();
        $orderedGroupIds = array_keys($groupDefinitions);

        foreach (array_keys($groups) as $groupId) {
            if (!in_array($groupId, $orderedGroupIds, true)) {
                $orderedGroupIds[] = $groupId;
            }
        }

        $cards = '';

        foreach ($orderedGroupIds as $groupId) {
            if (!isset($groups[$groupId])) {
                continue;
            }

            $group = $groups[$groupId];

            if ($group['rows'] === '') {
                continue;
            }

            if (isset($groupDefinitions[$groupId]) && trim((string) $groupDefinitions[$groupId]->title) !== '') {
                $group['title'] = Text::_((string) $groupDefinitions[$groupId]->title);
            }

            $entityClass = htmlspecialchars($entity, ENT_QUOTES, 'UTF-8');
            $groupTitle = htmlspecialchars($group['title'], ENT_QUOTES, 'UTF-8');
            $groupDomId = 'jem-custom-field-group-' . $entityClass . '-' . (int) $groupId;
            $cards .= '<dt id="' . $groupDomId . '-label" class="jem-custom-field-group-label jem-custom-field-group-label--'
                . $entityClass . '">' . $groupTitle . ':</dt>'
                . '<dd id="' . $groupDomId . '" class="jem-custom-field-group-value jem-custom-field-group-value--'
                . $entityClass . '" data-jem-field-group-id="' . (int) $groupId
                . '" aria-labelledby="' . $groupDomId . '-label">'
                . '<div class="jem-custom-field-group-card jem-custom-field-group-card--' . $entityClass . '">'
                . '<div id="' . $groupDomId . '-fields" class="jem-custom-field-group__list">'
                . $group['rows'] . '</div>'
                . '</div>'
                . '</dd>';
        }

        return array(
            'rows'  => $rows,
            'cards' => $cards,
        );
    }

    /**
     * Render one prepared Joomla field as a definition-list row.
     *
     * @param   object   $field        Prepared Joomla field.
     * @param   string   $classPrefix  CSS class prefix.
     * @param   boolean  $tooltip      Add legacy tooltip attributes.
     *
     * @return string
     */
    protected static function renderJoomlaDetailRow($field, $classPrefix, $tooltip)
    {
        $fieldId = (int) $field->id;
        $value = trim((string) $field->value);
        $label = Text::_((string) ($field->label ?? $field->name));
        $prefix = Text::plural((string) $field->params->get('prefix', ''), $value);
        $suffix = Text::plural((string) $field->params->get('suffix', ''), $value);
        $class = htmlspecialchars($classPrefix . $fieldId, ENT_QUOTES, 'UTF-8');
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $tooltipAttribute = $tooltip ? ' hasTooltip" data-original-title="' . $escapedLabel : '';
        $html = '<dt class="' . $class . $tooltipAttribute . '">' . $escapedLabel . ':</dt>';
        $html .= '<dd class="' . $class . '">';

        if ($prefix !== '') {
            $html .= '<span class="field-prefix">' . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        $html .= '<span class="field-value">' . $value . '</span>';

        if ($suffix !== '') {
            $html .= '<span class="field-suffix">' . htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        return $html . '</dd>';
    }

    /**
     * Render one populated Joomla field inside a compact Field Group card.
     *
     * @param   object   $field        Prepared Joomla field.
     * @param   string   $classPrefix  CSS class prefix.
     * @param   string   $entity       Event or venue.
     * @param   integer  $groupId      Joomla Field Group id.
     *
     * @return string
     */
    protected static function renderJoomlaGroupedDetailRow($field, $classPrefix, $entity, $groupId)
    {
        $fieldId = (int) $field->id;
        $value = trim((string) $field->value);
        $label = Text::_((string) ($field->label ?? $field->name));
        $prefix = Text::plural((string) $field->params->get('prefix', ''), $value);
        $suffix = Text::plural((string) $field->params->get('suffix', ''), $value);
        $class = htmlspecialchars($classPrefix . $fieldId, ENT_QUOTES, 'UTF-8');
        $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
        $domId = 'jem-custom-field-group-' . preg_replace('/[^a-z0-9_-]+/i', '-', (string) $entity)
            . '-' . (int) $groupId . '-field-' . $fieldId;
        $html = '<div id="' . $domId . '" class="jem-custom-field-group__field ' . $class . '">'
            . '<span id="' . $domId . '-label" class="jem-custom-field-group__field-label">'
            . $escapedLabel . ':</span> '
            . '<span id="' . $domId . '-value" class="jem-custom-field-group__field-value"'
            . ' aria-labelledby="' . $domId . '-label">';

        if ($prefix !== '') {
            $html .= '<span class="field-prefix">' . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        $html .= '<span class="field-value">' . $value . '</span>';

        if ($suffix !== '') {
            $html .= '<span class="field-suffix">' . htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8') . '</span>';
        }

        return $html . '</span></div>';
    }

    /**
     * Render Joomla field groups as tabs in an existing JEM tab set.
     *
     * @param   mixed   $form       Joomla form.
     * @param   string  $tabSet     Parent tab-set id.
     * @param   string  $tabPrefix  Unique tab id prefix.
     * @param   string  $entity     Event or venue, used by DOM hooks.
     *
     * @return string
     */
    public static function renderJoomlaFormTabs($form, $tabSet, $tabPrefix, $entity)
    {
        if (!$form || !in_array($entity, array('event', 'venue'), true)) {
            return '';
        }

        $html = '';

        foreach ((array) $form->getFieldsets('com_fields') as $fieldsetName => $fieldset) {
            $fields = array();

            foreach ((array) $form->getFieldset($fieldsetName) as $field) {
                if ((int) $field->getAttribute('data-jem-field-id') > 0) {
                    $fields[] = $field;
                }
            }

            if (!$fields) {
                continue;
            }

            $label = !empty($fieldset->label) ? Text::_($fieldset->label) : Text::_('COM_JEM_CUSTOMFIELDS');
            $description = !empty($fieldset->description) ? Text::_($fieldset->description) : '';
            $tabId = preg_replace('/[^a-z0-9_-]+/i', '-', $tabPrefix . '-' . $fieldsetName);
            $escapedLabel = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

            $html .= HTMLHelper::_('uitab.addTab', $tabSet, $tabId, $escapedLabel);
            $html .= '<fieldset class="panelform jem-joomla-custom-fields jem-joomla-custom-fields--'
                . $entity . '">';

            if ($description !== '') {
                $html .= '<div class="alert alert-info">'
                    . htmlspecialchars(strip_tags($description), ENT_QUOTES, 'UTF-8')
                    . '</div>';
            }

            $html .= '<ul class="adminformlist jem-customfields-edit">';

            foreach ($fields as $field) {
                $fieldId = (int) $field->getAttribute('data-jem-field-id');
                $html .= '<li data-jem-' . $entity . '-joomla-field-id="' . $fieldId . '">'
                    . '<span class="jem-customfield-label">' . $field->label . '</span>'
                    . '<span class="jem-customfield-input">' . $field->input . '</span>'
                    . '</li>';
            }

            $html .= '</ul></fieldset>';
            $html .= HTMLHelper::_('uitab.endTab');
        }

        return $html;
    }

    /**
     * Return globally enabled legacy event field indexes.
     *
     * @return array
     */
    public static function getEnabledLegacyFieldIds()
    {
        $fieldIds = array();

        foreach (JemCustomFields::getOrderedFields('event') as $fieldName) {
            if (!empty(JemCustomFields::getFieldConfig('event', $fieldName)['enabled'])) {
                $fieldIds[] = (int) substr($fieldName, 6);
            }
        }

        return array_values(array_unique($fieldIds));
    }

    /**
     * Append missing Joomla fields using the same public field-plugin event as
     * com_fields. This includes plugin-specific form types without direct SQL.
     *
     * @param   mixed   $form     Joomla form.
     * @param   array   $fields   Joomla field definitions.
     * @param   string  $context  Joomla field context.
     *
     * @return void
     */
    protected static function appendJoomlaFieldsToForm($form, array $fields, $context)
    {
        if (!$fields) {
            return;
        }

        try {
            PluginHelper::importPlugin('fields');
            $fieldTypes = FieldsHelper::getFieldTypes();
            $xml = new DOMDocument('1.0', 'UTF-8');
            $fieldsNode = $xml->appendChild(new DOMElement('form'))->appendChild(new DOMElement('fields'));
            $fieldsNode->setAttribute('name', 'com_fields');
            $dispatcher = Factory::getContainer()->get(DispatcherInterface::class);
            $fieldsPerGroup = array();

            foreach ($fields as $field) {
                if (!isset($fieldTypes[$field->type])) {
                    continue;
                }

                $groupId = max(0, (int) ($field->group_id ?? 0));

                if (!isset($fieldsPerGroup[$groupId])) {
                    $fieldsPerGroup[$groupId] = array();
                }

                $fieldsPerGroup[$groupId][] = $field;

                if (!empty($fieldTypes[$field->type]['path'])) {
                    FormHelper::addFieldPath($fieldTypes[$field->type]['path']);
                }

                if (!empty($fieldTypes[$field->type]['rules'])) {
                    FormHelper::addRulePath($fieldTypes[$field->type]['rules']);
                }
            }

            $groups = array(
                0 => (object) array(
                    'id'          => 0,
                    'title'       => 'COM_JEM_CUSTOMFIELDS',
                    'description' => '',
                ),
            );
            $groupsModel = Factory::getApplication()->bootComponent('com_fields')
                ->getMVCFactory()->createModel('Groups', 'Administrator', array('ignore_request' => true));
            $groupsModel->setState('filter.context', $context);

            foreach ((array) $groupsModel->getItems() as $group) {
                $groups[(int) $group->id] = $group;
            }

            $orderedGroupIds = array_keys($groups);

            foreach (array_keys($fieldsPerGroup) as $groupId) {
                if (!in_array($groupId, $orderedGroupIds, true)) {
                    $orderedGroupIds[] = $groupId;
                }
            }

            foreach ($orderedGroupIds as $groupId) {
                if (!isset($fieldsPerGroup[$groupId], $groups[$groupId])) {
                    continue;
                }

                $groupFields = $fieldsPerGroup[$groupId];

                if (!isset($groups[$groupId])) {
                    continue;
                }

                $group = $groups[$groupId];
                $fieldset = $fieldsNode->appendChild(new DOMElement('fieldset'));
                $fieldset->setAttribute('name', 'fields-' . $groupId);
                $fieldset->setAttribute('label', (string) ($group->title ?: 'COM_JEM_CUSTOMFIELDS'));
                $fieldset->setAttribute('description', strip_tags((string) ($group->description ?? '')));

                foreach ($groupFields as $field) {
                    $dispatcher->dispatch('onCustomFieldsPrepareDom', new PrepareDomEvent('onCustomFieldsPrepareDom', array(
                        'subject'  => $field,
                        'fieldset' => $fieldset,
                        'form'     => $form,
                    )));
                }

                if (!$fieldset->hasChildNodes()) {
                    $fieldsNode->removeChild($fieldset);
                }
            }

            if ($fieldsNode->hasChildNodes()) {
                $form->load($xml->saveXML());
            }
        } catch (Throwable $error) {
            // An unavailable third-party field type must not break item edit.
        }
    }

    /**
     * Restore stored JEM values for fields which become active after a form
     * reload. Submitted values, including intentional empty values, win.
     *
     * @param   mixed   $form        Joomla form.
     * @param   object  $eventData   Current submitted or stored event data.
     * @param   array   $fieldNames  Effective legacy field names.
     *
     * @return void
     */
    protected static function restoreMissingLegacyValues($form, $eventData, array $fieldNames)
    {
        $eventId = (int) ($eventData->id ?? 0);
        $missing = array_values(array_filter($fieldNames, static function ($fieldName) use ($eventData) {
            return !property_exists($eventData, $fieldName);
        }));

        if ($eventId < 1 || !$missing) {
            return;
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $columns = array_map(static function ($fieldName) use ($db) {
                return $db->quoteName($fieldName);
            }, $missing);
            $query = $db->getQuery(true)
                ->select($columns)
                ->from($db->quoteName('#__jem_events'))
                ->where($db->quoteName('id') . ' = ' . $eventId);
            $db->setQuery($query);
            $values = (array) $db->loadAssoc();

            foreach ($missing as $fieldName) {
                if (array_key_exists($fieldName, $values)) {
                    $form->setValue($fieldName, null, $values[$fieldName]);
                }
            }
        } catch (Throwable $error) {
            // A missing event during a concurrent delete leaves empty fields;
            // normal permission and save checks still protect the request.
        }
    }

    /**
     * Convert the supplied event data to an object and supplement form values.
     *
     * @param   mixed  $data  Event form data.
     * @param   mixed  $form  Joomla form.
     *
     * @return object
     */
    protected static function normaliseEventData($data, $form)
    {
        $data = self::normaliseItemData($data, $form);

        if (!isset($data->cats)) {
            $data->cats = $form->getValue('cats');
        }

        return $data;
    }

    /**
     * Convert supplied item data to an object and supplement common values.
     *
     * @param   mixed  $data  Form data.
     * @param   mixed  $form  Joomla form.
     *
     * @return object
     */
    protected static function normaliseItemData($data, $form)
    {
        if (is_array($data)) {
            $data = (object) $data;
        } elseif (!is_object($data)) {
            $data = new stdClass();
        }

        if (!isset($data->id)) {
            $data->id = (int) $form->getValue('id');
        }

        if (!isset($data->language)) {
            $data->language = $form->getValue('language');
        }

        return $data;
    }

    /**
     * Return sorted unique positive integer ids from a submitted array.
     *
     * @param   mixed  $values  Submitted ids.
     *
     * @return array
     */
    protected static function normalisePositiveIds($values)
    {
        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            is_array($values) ? $values : array()
        ), static function ($value) {
            return $value > 0;
        })));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    /**
     * Convert ids, comma-separated strings or category objects to integers.
     *
     * @param   mixed  $values  Values to normalise.
     *
     * @return array
     */
    protected static function normaliseIds($values)
    {
        if (is_string($values)) {
            $values = explode(',', $values);
        } elseif (is_object($values)) {
            $values = array($values);
        }

        $ids = array();

        foreach ((array) $values as $value) {
            if (is_object($value)) {
                $value = $value->id ?? $value->catid ?? 0;
            } elseif (is_array($value)) {
                $value = $value['id'] ?? $value['catid'] ?? 0;
            }

            $value = (int) $value;

            if ($value > 0) {
                $ids[] = $value;
            }
        }

        return array_values(array_unique($ids));
    }
}
