<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

require_once JPATH_SITE . '/components/com_jem/classes/eventfilterconfig.class.php';
require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
require_once JPATH_SITE . '/components/com_jem/classes/customfields.class.php';

/**
 * Ordered event filter editor used by menu views.
 */
class JFormFieldEventfilters extends FormField
{
    protected $type = 'Eventfilters';

    /**
     * Render the first shared filter editor used by Eventslist.
     */
    protected function getInput()
    {
        $this->loadAssets();

        $configuration = JemEventFilterConfig::normalise($this->value);
        $customFields = $this->getAvailableCustomFields();
        $rows = array();

        foreach ($configuration['rows'] as $row) {
            if (JemEventFilterConfig::isCustomKey($row['key']) && !isset($customFields[$row['key']])) {
                continue;
            }

            $rows[$row['key']] = $row;
        }

        $configuration['rows'] = array_values($rows);

        $contactForm = $this->buildContactForm($rows);
        $categoryInput = $this->renderNestedField($contactForm, 'contact_category');
        $contactInput = $this->renderNestedField($contactForm, 'contacts');
        $html = array();
        $html[] = '<div class="jem-event-filter-editor" data-jem-event-filter-editor'
            . ' data-jem-event-filter-max-custom="' . JemEventFilterConfig::MAX_CUSTOM_FIELDS . '">';
        $html[] = '  <input type="hidden" id="' . $this->escape($this->id) . '" name="'
            . $this->escape($this->name) . '" value="' . $this->escape(JemEventFilterConfig::encode($configuration)) . '">';
        $html[] = '  <p class="text-muted">' . $this->escape(Text::_('COM_JEM_EVENT_FILTERS_EDITOR_DESC')) . '</p>';
        $html[] = '  <div class="table-responsive">';
        $html[] = '    <table class="table table-striped align-middle jem-event-filter-table">';
        $html[] = '      <thead><tr>';
        $html[] = '        <th scope="col" class="jem-event-filter-order"><span class="visually-hidden">'
            . $this->escape(Text::_('JGRID_HEADING_ORDERING')) . '</span></th>';
        $html[] = '        <th scope="col">' . $this->escape(Text::_('COM_JEM_EVENT_FILTER_COLUMN_FILTER')) . '</th>';
        $html[] = '        <th scope="col">' . $this->escape(Text::_('COM_JEM_EVENT_FILTER_COLUMN_CONDITION')) . '</th>';
        $html[] = '        <th scope="col">' . $this->escape(Text::_('COM_JEM_EVENT_FILTER_COLUMN_PRESELECTION')) . '</th>';
        $html[] = '        <th scope="col" class="text-center">' . $this->escape(Text::_('COM_JEM_EVENT_FILTER_COLUMN_ACTIVE')) . '</th>';
        $html[] = '        <th scope="col" class="text-center">' . $this->escape(Text::_('COM_JEM_EVENT_FILTER_COLUMN_VISIBLE')) . '</th>';
        $html[] = '        <th scope="col" class="text-center">' . $this->escape(Text::_('COM_JEM_EVENT_FILTER_COLUMN_EDITABLE')) . '</th>';
        $html[] = '        <th scope="col" class="text-center jem-event-filter-actions"><span class="visually-hidden">'
            . $this->escape(Text::_('COM_JEM_REMOVE')) . '</span></th>';
        $html[] = '      </tr></thead>';
        $html[] = '      <tbody>';

        foreach ($rows as $row) {
            if ($row['key'] === JemEventFilterConfig::CONTACT_CATEGORY) {
                $valueInput = $categoryInput;
            } elseif ($row['key'] === JemEventFilterConfig::CONTACT) {
                $valueInput = $contactInput;
            } else {
                $valueInput = $this->renderCustomValueInput($row, $customFields[$row['key']]);
            }

            $html[] = $this->renderRow($row, $valueInput, $customFields[$row['key']] ?? array());
        }

        $html[] = '      </tbody>';
        $html[] = '    </table>';
        $html[] = '  </div>';
        $html[] = $this->renderCustomFieldToolbar($customFields);

        foreach ($customFields as $key => $customField) {
            $row = JemEventFilterConfig::row(array('rows' => array(array('key' => $key))), $key);

            if ($customField['type'] === JemCustomFields::TYPE_LIST) {
                $row['condition'] = 'exact';
            }

            $html[] = '<template data-filter-template="' . $this->escape($key) . '">';
            $html[] = $this->renderRow(
                $row,
                $this->renderCustomValueInput($row, $customField),
                $customField
            );
            $html[] = '</template>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Return the enabled event custom fields available to the menu filter.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getAvailableCustomFields(): array
    {
        $fields = array();

        foreach (JemCustomFields::getOrderedFields('event') as $key) {
            $config = JemCustomFields::getFieldConfig('event', $key);

            if (empty($config['enabled'])) {
                continue;
            }

            $index = (int) substr($key, 6);
            $fields[$key] = array(
                'label' => JemCustomFields::getLabel(
                    'event',
                    $key,
                    Text::_('COM_JEM_EVENT_CUSTOM_FIELD' . $index)
                ),
                'type' => (string) ($config['type'] ?? JemCustomFields::TYPE_TEXT),
                'options' => JemCustomFields::parseOptions($config['options'] ?? ''),
            );
        }

        return $fields;
    }

    /**
     * Build native Joomla/JEM selectors for the two related contact filters.
     *
     * @param   array<string, array<string, mixed>>  $rows  Rows keyed by filter name.
     */
    private function buildContactForm(array $rows): Form
    {
        $control = preg_replace('/[^A-Za-z0-9_]/', '_', $this->id) . '_editor';
        $form = new Form($control, array('control' => $control));
        $form->addFieldPath(JPATH_ADMINISTRATOR . '/components/com_jem/models/fields');
        $form->load(
            '<form>'
            . '<field name="contact_category" type="category" extension="com_contact" published="1" default="0"'
            . ' class="form-select w-auto jem-options-width-auto" label="COM_JEM_EVENT_FILTER_CONTACT_CATEGORY">'
            . '<option value="0">JOPTION_SELECT_CATEGORY</option>'
            . '</field>'
            . '<field name="contacts" type="modal_contact" categoryfield="contact_category" default=""'
            . ' label="COM_JEM_EVENT_FILTER_CONTACT" />'
            . '</form>'
        );
        $form->bind(array(
            'contact_category' => (int) $rows[JemEventFilterConfig::CONTACT_CATEGORY]['value'],
            'contacts' => implode(',', $rows[JemEventFilterConfig::CONTACT]['value']),
        ));

        return $form;
    }

    /**
     * Render a nested Joomla field through its public API.
     *
     * Reading the protected input property from another FormField subclass
     * bypasses Joomla's magic accessor and returns null before first render.
     */
    private function renderNestedField(Form $form, string $name): string
    {
        $field = $form->getField($name);

        if (!$field) {
            throw new UnexpectedValueException('Unable to load event filter field: ' . $name);
        }

        return $field->renderField(array(
            'hiddenLabel' => true,
            'hiddenDescription' => true,
        ));
    }

    /**
     * Render the preselection control for an event custom field.
     *
     * @param   array<string, mixed>  $row         Normalised filter row.
     * @param   array<string, mixed>  $definition  Custom-field definition.
     */
    private function renderCustomValueInput(array $row, array $definition): string
    {
        $id = $this->id . '_' . $row['key'] . '_value';
        $value = (string) $row['value'];

        if ($definition['type'] === JemCustomFields::TYPE_LIST) {
            $html = array();
            $html[] = '<select id="' . $this->escape($id) . '" class="form-select form-select-sm"'
                . ' data-filter-custom-value>';
            $html[] = '  <option value="">' . $this->escape(Text::_('JOPTION_SELECT')) . '</option>';

            foreach ($definition['options'] as $optionValue => $optionLabel) {
                $selected = $value === (string) $optionValue ? ' selected' : '';
                $html[] = '  <option value="' . $this->escape((string) $optionValue) . '"' . $selected . '>'
                    . $this->escape((string) $optionLabel) . '</option>';
            }

            $html[] = '</select>';

            return implode("\n", $html);
        }

        return '<input type="text" id="' . $this->escape($id) . '" class="form-control form-control-sm"'
            . ' maxlength="200" value="' . $this->escape($value) . '" data-filter-custom-value>';
    }

    /**
     * Render the control used to append unique optional custom-field rows.
     *
     * @param   array<string, array<string, mixed>>  $customFields  Available fields.
     */
    private function renderCustomFieldToolbar(array $customFields): string
    {
        $pickerId = $this->id . '_custom_picker';
        $html = array();
        $html[] = '<div class="jem-event-filter-toolbar" data-jem-event-filter-toolbar>';
        $html[] = '  <label for="' . $this->escape($pickerId) . '" class="visually-hidden">'
            . $this->escape(Text::_('COM_JEM_EVENT_FILTER_CUSTOM_SELECT')) . '</label>';
        $html[] = '  <select id="' . $this->escape($pickerId) . '" class="form-select form-select-sm"'
            . ' data-jem-custom-field-picker>';
        $html[] = '    <option value="">' . $this->escape(Text::_('COM_JEM_EVENT_FILTER_CUSTOM_SELECT')) . '</option>';

        foreach ($customFields as $key => $customField) {
            $html[] = '    <option value="' . $this->escape($key) . '">'
                . $this->escape($customField['label'] . ' (' . $key . ')') . '</option>';
        }

        $html[] = '  </select>';
        $html[] = '  <button type="button" class="btn btn-outline-primary btn-sm" data-jem-event-filter-add>'
            . '<span class="icon-plus" aria-hidden="true"></span> '
            . $this->escape(Text::_('COM_JEM_EVENT_FILTER_CUSTOM_ADD')) . '</button>';
        $html[] = '  <span class="text-muted" data-jem-event-filter-count aria-live="polite"></span>';
        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * Render one ordered filter row.
     *
     * @param   array<string, mixed>  $row         Normalised row.
     * @param   string                $valueInput  Native preselection control.
     * @param   array<string, mixed>  $customField Optional custom-field definition.
     */
    private function renderRow(array $row, string $valueInput, array $customField = array()): string
    {
        $key = $row['key'];
        $isCustom = JemEventFilterConfig::isCustomKey($key);

        if ($key === JemEventFilterConfig::CONTACT_CATEGORY) {
            $label = Text::_('COM_JEM_EVENT_FILTER_CONTACT_CATEGORY');
            $conditionOptions = array(
                'exact' => Text::_('COM_JEM_EVENT_FILTER_CONDITION_EXACT'),
                'descendants' => Text::_('COM_JEM_EVENT_FILTER_CONDITION_DESCENDANTS'),
            );
        } elseif ($key === JemEventFilterConfig::CONTACT) {
            $label = Text::_('COM_JEM_EVENT_FILTER_CONTACT');
            $conditionOptions = array('in' => Text::_('COM_JEM_EVENT_FILTER_CONDITION_IN'));
        } else {
            $label = (string) $customField['label'];
            $conditionOptions = $customField['type'] === JemCustomFields::TYPE_LIST
                ? array('exact' => Text::_('COM_JEM_EVENT_FILTER_CONDITION_EXACT_VALUE'))
                : array(
                    'contains' => Text::_('COM_JEM_EVENT_FILTER_CONDITION_CONTAINS'),
                    'exact' => Text::_('COM_JEM_EVENT_FILTER_CONDITION_EXACT_VALUE'),
                );
        }

        $html = array();
        $html[] = '<tr draggable="true" data-filter-key="' . $this->escape($key) . '"'
            . ($isCustom ? ' data-filter-optional="1"' : '') . '>';
        $html[] = '  <td class="jem-event-filter-order"><button type="button" class="btn btn-sm btn-link jem-event-filter-drag"'
            . ' aria-label="' . $this->escape(Text::sprintf('COM_JEM_EVENT_FILTER_MOVE', $label)) . '">'
            . '<span class="icon-menu" aria-hidden="true"></span></button></td>';
        $html[] = '  <th scope="row"><span>' . $this->escape($label) . '</span></th>';
        $html[] = '  <td><select class="form-select form-select-sm" data-filter-property="condition"'
            . ($key === JemEventFilterConfig::CONTACT || count($conditionOptions) === 1 ? ' disabled' : '') . '>';

        foreach ($conditionOptions as $value => $text) {
            $selected = $row['condition'] === $value ? ' selected' : '';
            $html[] = '    <option value="' . $this->escape($value) . '"' . $selected . '>'
                . $this->escape($text) . '</option>';
        }

        $html[] = '  </select></td>';
        $html[] = '  <td data-filter-value="' . $this->escape($key) . '">' . $valueInput . '</td>';
        $html[] = '  <td class="text-center">' . $this->renderSwitch($key, 'active', (bool) $row['active'], $label) . '</td>';
        $html[] = '  <td class="text-center">' . $this->renderSwitch($key, 'visible', (bool) $row['visible'], $label) . '</td>';
        $html[] = '  <td class="text-center">' . $this->renderSwitch($key, 'editable', (bool) $row['editable'], $label) . '</td>';

        if ($isCustom) {
            $html[] = '  <td class="text-center jem-event-filter-actions">'
                . '<button type="button" class="btn btn-sm btn-outline-danger jem-event-filter-remove"'
                . ' data-jem-event-filter-remove aria-label="'
                . $this->escape(Text::sprintf('COM_JEM_EVENT_FILTER_CUSTOM_REMOVE', $label)) . '">'
                . '<span class="icon-trash" aria-hidden="true"></span></button></td>';
        } else {
            $html[] = '  <td class="jem-event-filter-actions" aria-hidden="true"></td>';
        }

        $html[] = '</tr>';

        return implode("\n", $html);
    }

    /**
     * Render an accessible status switch.
     */
    private function renderSwitch(string $key, string $property, bool $checked, string $filterLabel): string
    {
        $id = $this->id . '_' . $key . '_' . $property;
        $statusLabel = Text::_('COM_JEM_EVENT_FILTER_COLUMN_' . strtoupper($property));

        return '<div class="form-check form-switch d-inline-flex">'
            . '<input class="form-check-input" type="checkbox" id="' . $this->escape($id) . '"'
            . ' data-filter-property="' . $this->escape($property) . '" value="1"'
            . ($checked ? ' checked' : '')
            . ' aria-label="' . $this->escape($filterLabel . ': ' . $statusLabel) . '">'
            . '</div>';
    }

    /**
     * Load the isolated editor assets in Joomla's menu form.
     */
    private function loadAssets(): void
    {
        $document = Factory::getApplication()->getDocument();

        if (!is_object($document) || !method_exists($document, 'getWebAssetManager')) {
            return;
        }

        $wa = $document->getWebAssetManager();

        if (!$wa->assetExists('script', 'com_jem.event-filters-admin')) {
            $wa->registerScript(
                'com_jem.event-filters-admin',
                'media/com_jem/js/event-filters-admin.js',
                array(),
                array('defer' => true)
            );
        }

        if (!$wa->assetExists('style', 'com_jem.event-filters-admin')) {
            $wa->registerStyle(
                'com_jem.event-filters-admin',
                'media/com_jem/css/event-filters-admin.css'
            );
        }

        $wa->useScript('com_jem.event-filters-admin');
        $wa->useStyle('com_jem.event-filters-admin');
    }

    /**
     * Escape text for HTML attributes and content.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
