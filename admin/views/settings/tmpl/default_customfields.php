<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

$languages = !empty($this->customFieldLanguages) ? $this->customFieldLanguages : array('en-GB');
$config = is_array($this->customFieldsConfig) ? $this->customFieldsConfig : array();
$legacyValues = JemCustomFields::getLegacyLanguageValues($languages);
$typeOptions = JemCustomFields::getTypeOptions();

$renderTable = function ($context) use ($languages, $config, $legacyValues, $typeOptions) {
    $contextLabel = $context === 'event' ? Text::_('COM_JEM_EVENTS') : Text::_('COM_JEM_VENUES');
    $siteLanguage = Factory::getApplication()->getLanguage()->getTag();
    $activeLanguage = in_array($siteLanguage, $languages, true) ? $siteLanguage : ($languages[0] ?? 'en-GB');
    $displayLanguages = $languages;

    if (($activeIndex = array_search($activeLanguage, $displayLanguages, true)) !== false) {
        unset($displayLanguages[$activeIndex]);
        array_unshift($displayLanguages, $activeLanguage);
        $displayLanguages = array_values($displayLanguages);
    }

    $orderedFields = array();
    for ($i = 1; $i <= 10; $i++) {
        $field = 'custom' . $i;
        $fieldConfig = isset($config[$context][$field]) && is_array($config[$context][$field]) ? $config[$context][$field] : array();
        $orderedFields[] = array(
            'field' => $field,
            'index' => $i,
            'order' => isset($fieldConfig['order']) ? (int) $fieldConfig['order'] : $i,
        );
    }

    usort($orderedFields, function ($a, $b) {
        if ($a['order'] === $b['order']) {
            return $a['index'] <=> $b['index'];
        }

        return $a['order'] <=> $b['order'];
    });
    ?>
    <div class="jem-custom-fields-grid" data-context="<?php echo htmlspecialchars($context, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="jem-custom-fields-common-pane">
            <div class="jem-custom-fields-common-toolbar mb-2" aria-hidden="true"></div>
            <table class="table table-striped table-sm align-middle jem-custom-fields-settings jem-custom-fields-common-table"
                   data-context="<?php echo htmlspecialchars($context, ENT_QUOTES, 'UTF-8'); ?>">
                <caption class="visually-hidden"><?php echo htmlspecialchars($contextLabel, ENT_QUOTES, 'UTF-8'); ?> <?php echo Text::_('COM_JEM_CUSTOM_FIELD_SLOT'); ?></caption>
                <colgroup>
                    <col class="jem-custom-fields-slot-col">
                    <col class="jem-custom-fields-type-col">
                    <?php foreach (array('enabled', 'show_backend', 'show_frontend_edit', 'show_detail', 'hide_empty') as $flag) : ?>
                        <col class="jem-custom-fields-flag-col">
                    <?php endforeach; ?>
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_ORDER'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_TYPE'); ?></th>
                        <th scope="col" class="jem-custom-fields-flag" title="<?php echo Text::_('JENABLED'); ?>"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_ENABLED_SHORT'); ?></th>
                        <th scope="col" class="jem-custom-fields-flag" title="<?php echo Text::_('COM_JEM_CUSTOM_FIELD_SHOW_BACKEND'); ?>"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_BACKEND_SHORT'); ?></th>
                        <th scope="col" class="jem-custom-fields-flag" title="<?php echo Text::_('COM_JEM_CUSTOM_FIELD_SHOW_FRONTEND_EDIT'); ?>"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_FRONTEND_SHORT'); ?></th>
                        <th scope="col" class="jem-custom-fields-flag" title="<?php echo Text::_('COM_JEM_CUSTOM_FIELD_SHOW_DETAIL'); ?>"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_DETAIL_SHORT'); ?></th>
                        <th scope="col" class="jem-custom-fields-flag" title="<?php echo Text::_('COM_JEM_CUSTOM_FIELD_HIDE_EMPTY'); ?>"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_EMPTY_SHORT'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orderedFields as $position => $orderedField) :
                        $field = $orderedField['field'];
                        $fieldConfig = isset($config[$context][$field]) && is_array($config[$context][$field]) ? $config[$context][$field] : array();
                        $fieldConfig = array_replace(array(
                            'order'              => $position + 1,
                            'enabled'            => 1,
                            'show_backend'       => 1,
                            'show_frontend_edit' => 1,
                            'show_detail'        => 1,
                            'hide_empty'         => 1,
                            'type'               => JemCustomFields::TYPE_TEXT,
                            'options'            => '',
                            'labels'             => array(),
                            'descriptions'       => array(),
                        ), $fieldConfig);
                        ?>
                        <tr draggable="true" data-field="<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>">
                            <th scope="row"
                                class="jem-custom-fields-order"
                                title="<?php echo htmlspecialchars(Text::sprintf('COM_JEM_CUSTOM_FIELD_STORED_AS', $field), ENT_QUOTES, 'UTF-8'); ?>">
                                <span class="jem-custom-fields-drag" aria-hidden="true">::</span>
                                <span class="jem-custom-fields-position"><?php echo (int) ($position + 1); ?></span>
                                <input type="hidden"
                                       class="jem-custom-fields-order-input"
                                       name="jem_custom_fields[<?php echo $context; ?>][<?php echo $field; ?>][order]"
                                       value="<?php echo (int) ($position + 1); ?>">
                            </th>
                            <td class="jem-custom-fields-type">
                                <select class="form-select form-select-sm"
                                        name="jem_custom_fields[<?php echo $context; ?>][<?php echo $field; ?>][type]">
                                    <?php foreach ($typeOptions as $value => $label) : ?>
                                        <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($fieldConfig['type'] ?? JemCustomFields::TYPE_TEXT) === $value ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <?php foreach (array('enabled', 'show_backend', 'show_frontend_edit', 'show_detail', 'hide_empty') as $flag) : ?>
                                <td class="jem-custom-fields-flag">
                                    <input type="checkbox"
                                           name="jem_custom_fields[<?php echo $context; ?>][<?php echo $field; ?>][<?php echo $flag; ?>]"
                                           value="1"
                                           <?php echo !empty($fieldConfig[$flag]) ? 'checked' : ''; ?>>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="jem-custom-fields-language-pane">
            <div class="d-flex justify-content-between align-items-end gap-2 mb-2 flex-wrap">
                <div class="nav nav-tabs jem-custom-fields-language-tabs" role="tablist" aria-label="<?php echo htmlspecialchars(Text::_('JLANGUAGE'), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php foreach ($displayLanguages as $language) : ?>
                        <button type="button"
                                class="nav-link py-1 px-3 jem-custom-fields-language-tab <?php echo $language === $activeLanguage ? 'active' : ''; ?>"
                                role="tab"
                                aria-selected="<?php echo $language === $activeLanguage ? 'true' : 'false'; ?>"
                                data-context="<?php echo htmlspecialchars($context, ENT_QUOTES, 'UTF-8'); ?>"
                                data-language="<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <button type="button"
                        class="btn btn-outline-secondary btn-sm jem-custom-fields-load-language"
                        data-context="<?php echo htmlspecialchars($context, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo Text::_('COM_JEM_CUSTOM_FIELDS_LOAD_LANGUAGE'); ?>
                </button>
            </div>
            <table class="table table-striped table-sm align-middle jem-custom-fields-settings jem-custom-fields-language-table"
                   data-context="<?php echo htmlspecialchars($context, ENT_QUOTES, 'UTF-8'); ?>">
                <caption class="visually-hidden"><?php echo htmlspecialchars($contextLabel, ENT_QUOTES, 'UTF-8'); ?> <?php echo Text::_('JLANGUAGE'); ?></caption>
            <colgroup>
                <?php foreach ($displayLanguages as $language) :
                    $languageClass = preg_replace('/[^a-z0-9_-]/i', '-', $language);
                    ?>
                    <col class="jem-custom-fields-label-col jem-custom-fields-language-col jem-custom-fields-language-<?php echo htmlspecialchars($languageClass, ENT_QUOTES, 'UTF-8'); ?>" data-language="<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>">
                    <col class="jem-custom-fields-description-col jem-custom-fields-language-col jem-custom-fields-language-<?php echo htmlspecialchars($languageClass, ENT_QUOTES, 'UTF-8'); ?>" data-language="<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endforeach; ?>
                <col class="jem-custom-fields-options-col">
                <col class="jem-custom-fields-clear-col">
            </colgroup>
            <thead>
                <tr>
                    <?php foreach ($displayLanguages as $language) :
                        $languageClass = preg_replace('/[^a-z0-9_-]/i', '-', $language);
                        ?>
                        <th scope="col" class="jem-custom-fields-label jem-custom-fields-language-col jem-custom-fields-language-<?php echo htmlspecialchars($languageClass, ENT_QUOTES, 'UTF-8'); ?>" data-language="<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?> <?php echo Text::_('COM_JEM_CUSTOM_FIELD_LABEL'); ?></th>
                        <th scope="col" class="jem-custom-fields-description jem-custom-fields-language-col jem-custom-fields-language-<?php echo htmlspecialchars($languageClass, ENT_QUOTES, 'UTF-8'); ?>" data-language="<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?> <?php echo Text::_('COM_JEM_CUSTOM_FIELD_DESCRIPTION'); ?></th>
                    <?php endforeach; ?>
                    <th scope="col" class="jem-custom-fields-options"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_OPTIONS'); ?></th>
                    <th scope="col" class="jem-custom-fields-clear"><span class="visually-hidden"><?php echo Text::_('JACTION_DELETE'); ?></span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orderedFields as $orderedField) :
                    $field = $orderedField['field'];
                    $fieldConfig = isset($config[$context][$field]) && is_array($config[$context][$field]) ? $config[$context][$field] : array();
                    $fieldConfig = array_replace(array(
                        'order'              => $orderedField['order'],
                        'enabled'            => 1,
                        'show_backend'       => 1,
                        'show_frontend_edit' => 1,
                        'show_detail'        => 1,
                        'hide_empty'         => 1,
                        'type'               => JemCustomFields::TYPE_TEXT,
                        'options'            => '',
                        'labels'             => array(),
                        'descriptions'       => array(),
                    ), $fieldConfig);
                    ?>
                    <tr data-field="<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php foreach ($displayLanguages as $language) :
                            $languageClass = preg_replace('/[^a-z0-9_-]/i', '-', $language);
                            ?>
                            <td class="jem-custom-fields-label jem-custom-fields-language-col jem-custom-fields-language-<?php echo htmlspecialchars($languageClass, ENT_QUOTES, 'UTF-8'); ?>" data-language="<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="text"
                                       class="form-control form-control-sm"
                                       data-context="<?php echo $context; ?>"
                                       data-field="<?php echo $field; ?>"
                                       data-kind="labels"
                                       data-language="<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>"
                                       name="jem_custom_fields[<?php echo $context; ?>][<?php echo $field; ?>][labels][<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>]"
                                       value="<?php echo htmlspecialchars($fieldConfig['labels'][$language] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </td>
                            <td class="jem-custom-fields-description jem-custom-fields-language-col jem-custom-fields-language-<?php echo htmlspecialchars($languageClass, ENT_QUOTES, 'UTF-8'); ?>" data-language="<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="text"
                                       class="form-control form-control-sm"
                                       data-context="<?php echo $context; ?>"
                                       data-field="<?php echo $field; ?>"
                                       data-kind="descriptions"
                                       data-language="<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>"
                                       name="jem_custom_fields[<?php echo $context; ?>][<?php echo $field; ?>][descriptions][<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>]"
                                       value="<?php echo htmlspecialchars($fieldConfig['descriptions'][$language] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </td>
                        <?php endforeach; ?>
                        <td class="jem-custom-fields-options">
                            <input type="text"
                                   class="form-control form-control-sm"
                                   name="jem_custom_fields[<?php echo $context; ?>][<?php echo $field; ?>][options]"
                                   title="<?php echo Text::_('COM_JEM_CUSTOM_FIELD_OPTIONS_DESC'); ?>"
                                   placeholder="<?php echo Text::_('COM_JEM_CUSTOM_FIELD_OPTIONS_PLACEHOLDER'); ?>"
                                   value="<?php echo htmlspecialchars($fieldConfig['options'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        </td>
                        <td class="jem-custom-fields-clear">
                            <button type="button"
                                    class="btn btn-outline-danger btn-sm jem-custom-fields-clear-field"
                                    data-context="<?php echo htmlspecialchars($context, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-field="<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>"
                                    title="<?php echo htmlspecialchars(Text::_('COM_JEM_CUSTOM_FIELD_CLEAR'), ENT_QUOTES, 'UTF-8'); ?>"
                                    aria-label="<?php echo htmlspecialchars(Text::_('COM_JEM_CUSTOM_FIELD_CLEAR'), ENT_QUOTES, 'UTF-8'); ?>">X</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    </div>
    <?php
};
?>

<style>
    .jem-custom-fields-grid {
        display: flex;
        gap: .6rem;
        align-items: start;
        width: 100%;
    }

    .jem-custom-fields-common-pane {
        order: 1;
        flex: 0 0 30rem;
        max-width: 30rem;
        overflow-x: auto;
    }

    .jem-custom-fields-common-toolbar {
        min-height: 2.45rem;
        border-bottom: 1px solid transparent;
    }

    .jem-custom-fields-language-pane {
        order: 2;
        flex: 1 1 36rem;
        min-width: 0;
        overflow-x: auto;
    }

    .jem-custom-fields-settings .jem-custom-fields-flag {
        text-align: center;
        vertical-align: middle;
        white-space: normal;
    }

    .jem-custom-fields-settings .jem-custom-fields-order {
        cursor: grab;
        text-align: center;
        white-space: nowrap;
        user-select: none;
    }

    .jem-custom-fields-settings tr.is-dragging {
        opacity: .55;
    }

    .jem-custom-fields-drag {
        color: #6c757d;
        display: inline-block;
        font-weight: 700;
        letter-spacing: 1px;
        margin-right: .35rem;
        transform: rotate(90deg);
    }

    .jem-custom-fields-position {
        display: inline-block;
        min-width: 1.35rem;
        font-weight: 700;
    }

    .jem-custom-fields-settings td.jem-custom-fields-flag {
        padding-left: .2rem;
        padding-right: .2rem;
    }

    .jem-custom-fields-common-table .jem-custom-fields-slot-col,
    .jem-custom-fields-common-table th:first-child,
    .jem-custom-fields-common-table td:first-child {
        width: 4.75rem;
        min-width: 4.75rem;
    }

    .jem-custom-fields-common-table .jem-custom-fields-type-col,
    .jem-custom-fields-common-table .jem-custom-fields-type {
        width: 10.5rem;
        min-width: 10.5rem;
    }

    .jem-custom-fields-common-table .jem-custom-fields-type .form-select-sm {
        width: 10.25rem;
        min-width: 10.25rem;
        max-width: 10.25rem;
    }

    .jem-custom-fields-common-table .jem-custom-fields-flag-col,
    .jem-custom-fields-common-table .jem-custom-fields-flag {
        width: 2.85rem;
        min-width: 2.85rem;
        max-width: 2.85rem;
    }

    .jem-custom-fields-language-table .jem-custom-fields-label-col,
    .jem-custom-fields-language-table th.jem-custom-fields-label,
    .jem-custom-fields-language-table td.jem-custom-fields-label {
        width: 20%;
        min-width: 0;
    }

    .jem-custom-fields-language-table .jem-custom-fields-description-col,
    .jem-custom-fields-language-table th.jem-custom-fields-description,
    .jem-custom-fields-language-table td.jem-custom-fields-description {
        width: 50%;
        min-width: 0;
    }

    .jem-custom-fields-language-table .jem-custom-fields-options-col,
    .jem-custom-fields-language-table .jem-custom-fields-options {
        width: 28%;
        min-width: 0;
    }

    .jem-custom-fields-language-table .jem-custom-fields-clear-col,
    .jem-custom-fields-language-table .jem-custom-fields-clear {
        width: 2.75rem;
        min-width: 2.75rem;
        text-align: center;
    }

    .jem-custom-fields-settings th,
    .jem-custom-fields-settings td {
        padding-left: .15rem;
        padding-right: .15rem;
    }

    .jem-custom-fields-settings th.jem-custom-fields-label,
    .jem-custom-fields-settings td.jem-custom-fields-label {
        padding-right: 0;
    }

    .jem-custom-fields-settings th.jem-custom-fields-description,
    .jem-custom-fields-settings td.jem-custom-fields-description {
        padding-left: 0;
        padding-right: .65rem;
    }

    .jem-custom-fields-settings td.jem-custom-fields-label .form-control-sm {
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
    }

    .jem-custom-fields-settings td.jem-custom-fields-description .form-control-sm {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }

    .jem-custom-fields-settings thead th {
        text-align: center;
        vertical-align: middle;
    }

    .jem-custom-fields-settings .form-control-sm {
        width: 100%;
        min-width: 8.5rem;
    }

    .jem-custom-fields-language-table .form-control-sm {
        min-width: 0;
    }

    .jem-custom-fields-language-tabs {
        border-bottom-color: #9bb2d0;
    }

    .jem-custom-fields-language-tabs .nav-link {
        font-weight: 600;
        color: #2f527e;
        border-bottom-width: 3px;
    }

    .jem-custom-fields-language-tabs .nav-link.active {
        background: #eef3fa;
        border-color: #d8e2ef #d8e2ef #4f78a8;
        color: #1f3d63;
    }

    .jem-custom-fields-settings .jem-custom-fields-language-col.is-hidden {
        display: none;
    }

    .jem-custom-fields-settings {
        width: 100%;
    }

    .jem-custom-fields-common-table {
        width: auto;
        min-width: 29rem;
        table-layout: fixed;
    }

    .jem-custom-fields-language-table {
        width: 100%;
        min-width: 0;
        table-layout: fixed;
    }

    @media (max-width: 1100px) {
        .jem-custom-fields-grid {
            flex-direction: column;
        }

        .jem-custom-fields-common-pane,
        .jem-custom-fields-language-pane {
            flex: 1 1 auto;
            max-width: 100%;
            width: 100%;
        }

        .jem-custom-fields-common-table {
            min-width: 58rem;
        }

        .jem-custom-fields-language-table {
            min-width: 42rem;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var legacyValues = <?php echo json_encode($legacyValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    var exampleValues = <?php echo json_encode(JemCustomFields::getExampleConfig(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    var exampleButton = document.querySelector('.jem-custom-fields-load-example');

    function setNamedValue(name, value) {
        var fields = document.getElementsByName(name);

        if (!fields.length) {
            return;
        }

        Array.prototype.forEach.call(fields, function (field) {
            if (field.type === 'checkbox') {
                field.checked = parseInt(value, 10) === 1;
            } else {
                field.value = value === null || typeof value === 'undefined' ? '' : value;
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    function loadExampleCustomFields() {
        ['event', 'venue'].forEach(function (context) {
            if (!exampleValues[context]) {
                return;
            }

            Object.keys(exampleValues[context]).forEach(function (field) {
                var config = exampleValues[context][field] || {};
                var base = 'jem_custom_fields[' + context + '][' + field + ']';

                ['enabled', 'show_backend', 'show_frontend_edit', 'show_detail', 'hide_empty'].forEach(function (flag) {
                    setNamedValue(base + '[' + flag + ']', config[flag] ? 1 : 0);
                });

                setNamedValue(base + '[order]', config.order || parseInt(field.replace('custom', ''), 10) || 1);
                setNamedValue(base + '[type]', config.type || 'text');
                setNamedValue(base + '[options]', config.options || '');

                Object.keys(config.labels || {}).forEach(function (language) {
                    setNamedValue(base + '[labels][' + language + ']', config.labels[language] || '');
                });

                Object.keys(config.descriptions || {}).forEach(function (language) {
                    setNamedValue(base + '[descriptions][' + language + ']', config.descriptions[language] || '');
                });
            });
        });

        ['event', 'venue'].forEach(function (context) {
            sortRowsByOrder(context);
            updateCustomFieldOrder(context);
        });
    }

    function getCustomFieldTable(context, kind) {
        return document.querySelector('.jem-custom-fields-' + kind + '-table[data-context="' + context + '"]');
    }

    function updateCustomFieldOrder(context) {
        var commonTable = getCustomFieldTable(context, 'common');
        var languageTable = getCustomFieldTable(context, 'language');

        if (!commonTable || !languageTable) {
            return;
        }

        Array.prototype.forEach.call(commonTable.tBodies[0].rows, function (row, index) {
            var field = row.getAttribute('data-field');
            var position = index + 1;
            var positionLabel = row.querySelector('.jem-custom-fields-position');
            var orderInput = row.querySelector('.jem-custom-fields-order-input');
            var languageRow = languageTable.tBodies[0].querySelector('tr[data-field="' + field + '"]');

            if (positionLabel) {
                positionLabel.textContent = position;
            }

            if (orderInput) {
                orderInput.value = position;
            }

            if (languageRow && languageRow.parentNode) {
                languageRow.parentNode.appendChild(languageRow);
            }
        });
    }

    function sortRowsByOrder(context) {
        var commonTable = getCustomFieldTable(context, 'common');

        if (!commonTable) {
            return;
        }

        Array.prototype.slice.call(commonTable.tBodies[0].rows)
            .sort(function (a, b) {
                var aOrder = parseInt((a.querySelector('.jem-custom-fields-order-input') || {}).value, 10) || 0;
                var bOrder = parseInt((b.querySelector('.jem-custom-fields-order-input') || {}).value, 10) || 0;

                return aOrder - bOrder;
            })
            .forEach(function (row) {
                commonTable.tBodies[0].appendChild(row);
            });
    }

    document.querySelectorAll('.jem-custom-fields-common-table tbody').forEach(function (tbody) {
        var draggedRow = null;

        tbody.addEventListener('dragstart', function (event) {
            draggedRow = event.target.closest('tr[data-field]');

            if (!draggedRow) {
                return;
            }

            draggedRow.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', draggedRow.getAttribute('data-field'));
        });

        tbody.addEventListener('dragover', function (event) {
            var targetRow = event.target.closest('tr[data-field]');

            if (!draggedRow || !targetRow || targetRow === draggedRow) {
                return;
            }

            event.preventDefault();

            var bounds = targetRow.getBoundingClientRect();
            var before = event.clientY < bounds.top + bounds.height / 2;
            targetRow.parentNode.insertBefore(draggedRow, before ? targetRow : targetRow.nextSibling);
        });

        tbody.addEventListener('drop', function (event) {
            event.preventDefault();
        });

        tbody.addEventListener('dragend', function () {
            if (!draggedRow) {
                return;
            }

            var table = draggedRow.closest('table');
            var context = table ? table.getAttribute('data-context') : '';
            draggedRow.classList.remove('is-dragging');
            draggedRow = null;

            if (context) {
                updateCustomFieldOrder(context);
            }
        });
    });

    document.querySelectorAll('.jem-custom-fields-clear-field').forEach(function (button) {
        button.addEventListener('click', function () {
            var context = button.getAttribute('data-context');
            var field = button.getAttribute('data-field');
            var base = 'jem_custom_fields[' + context + '][' + field + ']';

            setNamedValue(base + '[type]', 'text');
            setNamedValue(base + '[options]', '');

            document.querySelectorAll('input[data-context="' + context + '"][data-field="' + field + '"]').forEach(function (input) {
                if (input.getAttribute('data-kind') === 'labels' || input.getAttribute('data-kind') === 'descriptions') {
                    input.value = '';
                }
            });
        });
    });

    if (exampleButton) {
        exampleButton.addEventListener('click', function () {
            if (confirm('<?php echo addslashes(Text::_('COM_JEM_CUSTOM_FIELDS_LOAD_EXAMPLE_CONFIRM')); ?>')) {
                loadExampleCustomFields();
                exampleButton.classList.remove('btn-outline-primary');
                exampleButton.classList.add('btn-success');
            }
        });
    }

    function setActiveLanguage(context, language) {
        document.querySelectorAll('.jem-custom-fields-language-tab[data-context="' + context + '"]').forEach(function (button) {
            var active = button.getAttribute('data-language') === language;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        document.querySelectorAll('.jem-custom-fields-language-table .jem-custom-fields-language-col').forEach(function (cell) {
            var table = cell.closest('table');
            if (!table || table.getAttribute('data-context') !== context) {
                return;
            }

            cell.classList.toggle('is-hidden', cell.getAttribute('data-language') !== language);
        });
    }

    document.querySelectorAll('.jem-custom-fields-language-tabs').forEach(function (group) {
        var active = group.querySelector('.jem-custom-fields-language-tab.active') || group.querySelector('.jem-custom-fields-language-tab');
        if (active) {
            setActiveLanguage(active.getAttribute('data-context'), active.getAttribute('data-language'));
        }
    });

    document.querySelectorAll('.jem-custom-fields-language-tab').forEach(function (button) {
        button.addEventListener('click', function () {
            setActiveLanguage(button.getAttribute('data-context'), button.getAttribute('data-language'));
        });
    });

    document.querySelectorAll('.jem-custom-fields-load-language').forEach(function (button) {
        button.addEventListener('click', function () {
            var context = button.getAttribute('data-context');
            var filled = 0;

            document.querySelectorAll('.jem-custom-fields-settings input[data-context="' + context + '"]').forEach(function (input) {
                var field = input.getAttribute('data-field');
                var kind = input.getAttribute('data-kind');
                var language = input.getAttribute('data-language');
                var value = legacyValues[context]
                    && legacyValues[context][field]
                    && legacyValues[context][field][kind]
                    && legacyValues[context][field][kind][language]
                    ? legacyValues[context][field][kind][language]
                    : '';

                if (value !== '' && input.value.trim() === '') {
                    input.value = value;
                    filled++;
                }
            });

            if (filled > 0) {
                button.classList.remove('btn-outline-secondary');
                button.classList.add('btn-success');
            }
        });
    });
});
</script>

<div class="alert alert-info">
    <?php echo Text::_('COM_JEM_CUSTOM_FIELDS_SETTINGS_DESC'); ?>
    <div class="small mt-2">
        <?php echo Text::_('COM_JEM_CUSTOM_FIELDS_ABBREVIATIONS_DESC'); ?>
    </div>
    <div class="small mt-1">
        <?php echo Text::_('COM_JEM_CUSTOM_FIELD_OPTIONS_DESC'); ?>
    </div>
</div>

<div class="d-flex justify-content-end mb-3">
    <button type="button" class="btn btn-outline-primary btn-sm jem-custom-fields-load-example">
        <?php echo Text::_('COM_JEM_CUSTOM_FIELDS_LOAD_EXAMPLE'); ?>
    </button>
</div>

<?php echo HTMLHelper::_('uitab.startTabSet', 'custom-fields-pane', array('active' => 'custom-fields-events', 'recall' => true, 'breakpoint' => 768)); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'custom-fields-pane', 'custom-fields-events', Text::_('COM_JEM_EVENTS')); ?>
    <?php $renderTable('event'); ?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'custom-fields-pane', 'custom-fields-venues', Text::_('COM_JEM_VENUES')); ?>
    <?php $renderTable('venue'); ?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.endTabSet'); ?>
