<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$buildProfilePayloads = function (array $options) {
    $payloads = array();

    foreach ($options as $option) {
        $id = (int) ($option->value ?? 0);

        if ($id <= 0) {
            continue;
        }

        $payloads[$id] = array(
            'title' => (string) ($option->text ?? ''),
            'format' => strtoupper((string) ($option->source_format ?? '')),
            'mapping' => (array) ($option->profile_mapping ?? array()),
            'config' => (array) ($option->profile_config ?? array()),
        );
    }

    return $payloads;
};

$renderMigrationCsvBlock = function ($id, $title, $description, $showColumnsText, array $fields, $replaceName, $fileName, $task) {
    ?>
    <section class="jem-import-card">
        <div class="jem-import-card-header">
            <div>
                <h3><?php echo Text::_($title); ?></h3>
                <p><?php echo Text::_($description); ?></p>
            </div>
        </div>
        <details class="jem-import-columns">
            <summary><?php echo Text::_($showColumnsText); ?></summary>
            <div class="jem-import-columns-list"><?php echo implode(', ', $fields); ?></div>
        </details>
        <div class="jem-import-row jem-import-row-compact">
            <div class="jem-import-field jem-import-field-replace">
                <label for="<?php echo $id; ?>-replace">
                    <?php echo Text::_('COM_JEM_IMPORT_REPLACEIFEXISTS_LABEL'); ?>
                    <span><?php echo Text::_('COM_JEM_IMPORT_REPLACEIFEXISTS_HELP'); ?></span>
                </label>
                <?php echo HTMLHelper::_('select.booleanlist', $replaceName, 'class="inputbox form-select" id="' . $id . '-replace"', 0); ?>
            </div>
            <div class="jem-import-field jem-import-field-file">
                <label for="<?php echo $id; ?>-file-upload"><?php echo Text::_('COM_JEM_IMPORT_SELECTCSV'); ?></label>
                <input type="file" id="<?php echo $id; ?>-file-upload" accept=".csv,text/csv,text/plain,text/*" name="<?php echo $fileName; ?>" class="form-control" />
            </div>
            <div class="jem-import-actions">
                <button type="submit" class="btn btn-primary" onclick="document.getElementById('task1').value='<?php echo $task; ?>';">
                    <span class="icon-upload" aria-hidden="true"></span>
                    <?php echo Text::_('COM_JEM_IMPORT_START'); ?>
                </button>
            </div>
        </div>
    </section>
    <?php
};

$renderCreateModalButton = function ($id, $task, $buttonText, $titleText, $targetSelectId, $nameFieldId, $saveTask, $entity = 0) {
    $modalId = 'jem-import-create-' . preg_replace('/[^a-z0-9_-]/i', '-', $id);
    $url = Route::_('index.php?option=com_jem&task=' . $task . '&tmpl=component' . ($entity ? '&entity=' . (int) $entity : ''), false);
    $footer = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
        . '<button type="button" class="btn btn-primary" onclick="JemImportModalSaveAndSelect(\''
        . htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') . '\', \''
        . htmlspecialchars($targetSelectId, ENT_QUOTES, 'UTF-8') . '\', \''
        . htmlspecialchars($nameFieldId, ENT_QUOTES, 'UTF-8') . '\', \''
        . htmlspecialchars($saveTask, ENT_QUOTES, 'UTF-8') . '\');">'
        . Text::_('COM_JEM_IMPORT_SAVE_AND_SELECT') . '</button>';

    echo HTMLHelper::_(
        'bootstrap.renderModal',
        $modalId,
        array(
            'url'    => $url,
            'title'  => Text::_($titleText),
            'width'  => '100%',
            'height' => '100%',
            'footer' => $footer,
        )
    );
    ?>
    <button type="button" class="btn btn-secondary jem-import-create-btn" data-bs-toggle="modal" data-bs-target="#<?php echo $modalId; ?>">
        <span class="icon-plus" aria-hidden="true"></span>
        <?php echo Text::_($buttonText); ?>
    </button>
    <?php
};

$renderFancySelect = function (array $options, $name, $id, $selected = null) {
    $select = HTMLHelper::_(
        'select.genericlist',
        $options,
        $name,
        array(
            'class' => 'form-select',
            'id' => $id,
        ),
        'value',
        'text',
        $selected
    );

    return '<joomla-field-fancy-select class="jem-import-fancy-select" style="width: min(100%, 36rem); max-width: 36rem;" placeholder="'
        . htmlspecialchars(Text::_('JGLOBAL_TYPE_OR_SELECT_SOME_OPTIONS'), ENT_QUOTES, 'UTF-8')
        . '">' . $select . '</joomla-field-fancy-select>';
};

$renderPreviewActions = function ($commitTask, $clearTask, $tabId, $validCount) {
    ?>
    <div class="jem-import-actions jem-import-preview-actions">
        <button type="button" class="btn btn-primary" data-import-task="<?php echo htmlspecialchars($commitTask, ENT_QUOTES, 'UTF-8'); ?>"<?php echo empty($validCount) ? ' disabled' : ''; ?> onclick="JemImportSubmit('<?php echo $commitTask; ?>', '<?php echo $tabId; ?>');">
            <span class="icon-upload" aria-hidden="true"></span>
            <?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_IMPORT_VALID_ROWS'); ?>
        </button>
        <button type="button" class="btn btn-secondary" onclick="JemImportSubmit('<?php echo $clearTask; ?>', '<?php echo $tabId; ?>');">
            <?php echo Text::_('JTOOLBAR_CANCEL'); ?>
        </button>
    </div>
    <?php
};

$renderImportMappingBlock = function (array $preview, $inputName, array $jemFields, $profileName) {
    $sourceFields = array_values(array_filter((array) ($preview['source_fields'] ?? array()), 'strlen'));
    $mapping = (array) ($preview['mapping'] ?? array());
    $staticValues = array_values((array) ($preview['static_values'] ?? array()));
    $profileId = (int) ($preview['profile_id'] ?? 0);
    $profileTitle = (string) ($preview['profile_title'] ?? '');
    $staticName = preg_replace('/_mapping$/', '_static_values', $inputName);

    if (!$sourceFields) {
        return;
    }
    ?>
    <div class="jem-import-row jem-import-row-compact jem-import-profile-row">
        <input type="hidden" name="<?php echo $profileName; ?>_id" value="<?php echo $profileId; ?>">
        <div class="jem-import-field jem-import-profile-save">
            <label>
                <input type="checkbox" name="<?php echo $profileName; ?>_save" value="1">
                <?php echo Text::_('COM_JEM_IMPORT_SAVE_PROFILE'); ?>
            </label>
        </div>
        <div class="jem-import-field jem-import-profile-name">
            <label for="<?php echo $profileName; ?>_title"><?php echo Text::_('COM_JEM_IMPORT_PROFILE_NAME'); ?></label>
            <input type="text" id="<?php echo $profileName; ?>_title" name="<?php echo $profileName; ?>_title" class="form-control" value="<?php echo htmlspecialchars($profileTitle, ENT_QUOTES, 'UTF-8'); ?>">
        </div>
    </div>
    <div class="jem-import-mapping-layout">
        <details class="jem-import-columns jem-import-mapping-panel" open data-profile-id="<?php echo $profileId; ?>" data-original-mapping="<?php echo htmlspecialchars(json_encode($mapping), ENT_QUOTES, 'UTF-8'); ?>">
            <summary><?php echo Text::_('COM_JEM_IMPORT_MAPPING_TITLE'); ?></summary>
            <p class="small text-muted mt-2 mb-2"><?php echo Text::_('COM_JEM_IMPORT_MAPPING_DESC'); ?></p>
            <div class="table-responsive mt-2">
                <table class="adminlist table jem-import-mapping-table">
                    <thead>
                        <tr>
                            <th><?php echo Text::_('COM_JEM_IMPORT_SOURCE_FIELD'); ?></th>
                            <th><?php echo Text::_('COM_JEM_IMPORT_JEM_FIELD'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sourceFields as $sourceField) : ?>
                            <?php $selected = $mapping[$sourceField] ?? ''; ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($sourceField, ENT_QUOTES, 'UTF-8'); ?></code></td>
                                <td>
                                    <select name="<?php echo $inputName; ?>[<?php echo htmlspecialchars($sourceField, ENT_QUOTES, 'UTF-8'); ?>]" class="form-select jem-import-mapping-select" data-source-field="<?php echo htmlspecialchars($sourceField, ENT_QUOTES, 'UTF-8'); ?>">
                                        <option value=""><?php echo Text::_('JNONE'); ?></option>
                                        <?php foreach ($jemFields as $value => $field) : ?>
                                            <?php
                                            $label = is_array($field) ? ($field['label'] ?? $value) : $field;
                                            $kind = is_array($field) ? ($field['kind'] ?? 'jem') : 'jem';
                                            ?>
                                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" class="jem-import-mapping-option-<?php echo htmlspecialchars($kind, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $selected === $value ? ' selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($inputName === 'external_venue_import_mapping') : ?>
                <div class="jem-import-actions jem-import-actions-row mt-3">
                    <button type="button" class="btn btn-secondary" onclick="JemImportSubmit('import.previewExternalVenueImport', 'venue-import');">
                        <span class="icon-refresh" aria-hidden="true"></span>
                        <?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_RELOAD_PREVIEW'); ?>
                    </button>
                    <span class="small text-muted"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_REFRESH_PREVIEW_DESC'); ?></span>
                </div>
            <?php endif; ?>
        </details>
        <details class="jem-import-columns jem-import-static-panel" open>
            <summary><?php echo Text::_('COM_JEM_IMPORT_STATIC_VALUES_TITLE'); ?></summary>
            <p class="small text-muted mt-2 mb-2"><?php echo Text::_('COM_JEM_IMPORT_STATIC_VALUES_DESC'); ?></p>
            <div class="table-responsive">
                <table class="adminlist table jem-import-static-table" data-static-name="<?php echo htmlspecialchars($staticName, ENT_QUOTES, 'UTF-8'); ?>">
                    <thead>
                        <tr>
                            <th><?php echo Text::_('COM_JEM_IMPORT_JEM_FIELD'); ?></th>
                            <th><?php echo Text::_('COM_JEM_IMPORT_STATIC_VALUE'); ?></th>
                            <th><?php echo Text::_('COM_JEM_IMPORT_STATIC_MODE'); ?></th>
                            <th class="center"><?php echo Text::_('JACTION_DELETE'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rows = $staticValues ?: array(array('field' => '', 'value' => '', 'mode' => 'if_empty')); ?>
                        <?php foreach ($rows as $index => $staticValue) : ?>
                            <?php
                            $selectedField = (string) ($staticValue['field'] ?? '');
                            $value = (string) ($staticValue['value'] ?? '');
                            $mode = (string) ($staticValue['mode'] ?? 'if_empty');
                            ?>
                            <tr class="jem-import-static-row">
                                <td>
                                    <select name="<?php echo $staticName; ?>[<?php echo (int) $index; ?>][field]" class="form-select jem-import-static-field">
                                        <option value=""><?php echo Text::_('JNONE'); ?></option>
                                        <?php foreach ($jemFields as $fieldValue => $field) : ?>
                                            <?php $label = is_array($field) ? ($field['label'] ?? $fieldValue) : $field; ?>
                                            <option value="<?php echo htmlspecialchars($fieldValue, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $selectedField === $fieldValue ? ' selected' : ''; ?>>
                                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="text" name="<?php echo $staticName; ?>[<?php echo (int) $index; ?>][value]" class="form-control jem-import-static-value" value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"></td>
                                <td>
                                    <select name="<?php echo $staticName; ?>[<?php echo (int) $index; ?>][mode]" class="form-select jem-import-static-mode">
                                        <option value="if_empty"<?php echo $mode !== 'always' ? ' selected' : ''; ?>><?php echo Text::_('COM_JEM_IMPORT_STATIC_MODE_IF_EMPTY'); ?></option>
                                        <option value="always"<?php echo $mode === 'always' ? ' selected' : ''; ?>><?php echo Text::_('COM_JEM_IMPORT_STATIC_MODE_ALWAYS'); ?></option>
                                    </select>
                                </td>
                                <td class="center"><button type="button" class="btn btn-danger btn-sm jem-import-static-remove" aria-label="<?php echo Text::_('JACTION_DELETE'); ?>"><span class="icon-trash" aria-hidden="true"></span></button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-secondary btn-sm jem-import-static-add">
                <span class="icon-plus" aria-hidden="true"></span>
                <?php echo Text::_('COM_JEM_IMPORT_STATIC_ADD'); ?>
            </button>
        </details>
    </div>
    <?php
};

$renderImportStatus = function ($status) {
    $statusText = (string) $status;
    $statusOk = Text::_('COM_JEM_IMPORT_EXTERNAL_STATUS_OK');
    $statusError = Text::_('COM_JEM_IMPORT_EXTERNAL_STATUS_ERROR');
    $statusClass = strcasecmp($statusText, $statusOk) === 0 ? 'is-ok' : (strcasecmp($statusText, $statusError) === 0 ? 'is-error' : '');

    return '<span class="jem-import-status ' . $statusClass . '">' . htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') . '</span>';
};

$renderImportFieldValue = function ($value, $field, array $row) {
    $fieldStatus = (array) ($row['field_status'][$field] ?? array());
    $state = (string) ($fieldStatus['state'] ?? '');
    $value = (string) $value;

    if ($field !== 'day_type' && $field !== 'day_type_id') {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    if ($state === 'ok') {
        return '<span class="jem-import-field-badge jem-import-field-badge-ok"><span aria-hidden="true">&#10003;</span> '
            . htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
            . '</span>';
    }

    if ($state === 'error') {
        $source = (string) ($fieldStatus['source'] ?? '');
        $fallback = (string) ($fieldStatus['fallback'] ?? $value);

        return '<span class="jem-import-field-badge jem-import-field-badge-error" title="'
            . htmlspecialchars(Text::sprintf('COM_JEM_IMPORT_SPECIAL_DAYS_TYPE_FALLBACK_TITLE', $source, $fallback), ENT_QUOTES, 'UTF-8')
            . '"><span aria-hidden="true">&#10007;</span> '
            . htmlspecialchars($source !== '' ? $source : $value, ENT_QUOTES, 'UTF-8')
            . '</span>'
            . '<span class="jem-import-field-fallback"> &rarr; ' . htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$renderCatalogSelectionNotice = function ($context) {
    $entry = (array) ($this->selectedImportCatalogEntry ?? array());

    if (!$entry || JemImportCatalogHelper::getContext($entry['type'] ?? '') !== $context) {
        return;
    }
    ?>
    <div class="alert alert-info jem-import-catalog-selected">
        <strong><?php echo htmlspecialchars($entry['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
        <?php if (!empty($entry['source'])) : ?>
            <span><?php echo Text::_('COM_JEM_IMPORT_CATALOG_SELECTED_SOURCE'); ?>:</span>
            <a href="<?php echo htmlspecialchars($entry['source'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo htmlspecialchars($entry['source'], ENT_QUOTES, 'UTF-8'); ?>
            </a>
        <?php endif; ?>
        <?php if (!empty($entry['profile'])) : ?>
            <span><?php echo Text::sprintf('COM_JEM_IMPORT_CATALOG_SELECTED_PROFILE', htmlspecialchars($entry['profile'], ENT_QUOTES, 'UTF-8')); ?></span>
        <?php endif; ?>
    </div>
    <?php
};

$renderDynamicPreviewTable = function (array $preview, array $fallbackFields = array()) use ($renderImportStatus, $renderImportFieldValue) {
    $recordFields = array_values(array_filter((array) ($preview['record_fields'] ?? $fallbackFields), 'strlen'));
    $rows = (array) ($preview['rows'] ?? array());
    ?>
    <div class="jem-import-data-heading">
        <hr>
        <h4><?php echo Text::_('COM_JEM_IMPORT_MAPPED_DATA_TITLE'); ?></h4>
    </div>
    <div class="table-responsive jem-import-preview-table-wrap">
        <table class="adminlist table jem-import-paged-table jem-import-dynamic-preview" data-page-size="100" data-server-paginated="<?php echo !empty($preview['server_paginated']) ? '1' : '0'; ?>" data-preview-offset="<?php echo (int) ($preview['preview_offset'] ?? 0); ?>" data-source-records="<?php echo htmlspecialchars(json_encode($preview['source_records'] ?? array()), ENT_QUOTES, 'UTF-8'); ?>">
            <thead>
                <tr>
                    <th class="center">#</th>
                    <th><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_STATUS'); ?></th>
                    <th><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_NOTES'); ?></th>
                    <?php foreach ($recordFields as $field) : ?>
                        <th data-field="<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $index => $row) : ?>
                    <?php
                    $importData = (array) ($row['import_data'] ?? array());
                    $statusText = (string) ($row['status'] ?? '');
                    ?>
                    <tr>
                        <td class="center" data-fixed="row-number"><?php echo (int) ($preview['preview_offset'] ?? 0) + (int) $index + 1; ?></td>
                        <td data-fixed="status"><?php echo $renderImportStatus($statusText); ?></td>
                        <td data-fixed="notes"><?php echo htmlspecialchars(implode('; ', (array) ($row['notes'] ?? array())), ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php foreach ($recordFields as $field) : ?>
                            <td data-field="<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $renderImportFieldValue($importData[$field] ?? '', $field, (array) $row); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($preview['server_paginated'])) : ?>
        <?php
        $page = (int) ($preview['preview_page'] ?? 1);
        $pages = (int) ($preview['preview_pages'] ?? 1);
        $total = (int) ($preview['total_count'] ?? 0);
        $first = (int) ($preview['preview_offset'] ?? 0) + 1;
        $last = min($total, $first + max(0, (int) ($preview['displayed_count'] ?? 0) - 1));
        $pageUrl = function ($targetPage) {
            return 'index.php?option=com_jem&amp;view=import&amp;preview=venues&amp;venue_preview_page=' . (int) $targetPage . '#venue-import';
        };
        ?>
        <nav class="jem-import-pagination d-flex flex-wrap align-items-center gap-2 mt-2" aria-label="<?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_PAGINATION'); ?>">
            <a class="btn btn-secondary btn-sm<?php echo $page <= 1 ? ' disabled' : ''; ?>" href="<?php echo $page <= 1 ? '#' : $pageUrl($page - 1); ?>"<?php echo $page <= 1 ? ' aria-disabled="true"' : ''; ?>><?php echo Text::_('JPREV'); ?></a>
            <?php for ($number = max(1, $page - 2); $number <= min($pages, $page + 2); $number++) : ?>
                <a class="btn btn-sm <?php echo $number === $page ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo $pageUrl($number); ?>"<?php echo $number === $page ? ' aria-current="page"' : ''; ?>><?php echo $number; ?></a>
            <?php endfor; ?>
            <a class="btn btn-secondary btn-sm<?php echo $page >= $pages ? ' disabled' : ''; ?>" href="<?php echo $page >= $pages ? '#' : $pageUrl($page + 1); ?>"<?php echo $page >= $pages ? ' aria-disabled="true"' : ''; ?>><?php echo Text::_('JNEXT'); ?></a>
            <span><?php echo Text::sprintf('COM_JEM_IMPORT_EXTERNAL_PREVIEW_PAGE_STATUS', $page, $pages, $first, $last, $total); ?></span>
        </nav>
    <?php endif; ?>
    <?php
};

$buildImportMappingFields = function (array $jemFields, $customContext) {
    $fields = array();

    foreach ($jemFields as $field) {
        $fields[$field] = array(
            'label' => $field . ' (JEM)',
            'kind' => 'jem',
        );
    }

    for ($i = 1; $i <= 10; $i++) {
        $field = 'custom' . $i;
        $fields[$field] = array(
            'label' => $field . ' (custom)',
            'kind' => 'custom',
        );
    }

    return $fields;
};

$eventMappingFields = $buildImportMappingFields(array(
    'title',
    'alias',
    'dates',
    'enddates',
    'times',
    'endtimes',
    'start_datetime',
    'end_datetime',
    'introtext',
    'fulltext',
    'metadata',
    'published',
    'publish_up',
    'publish_down',
    'type_id',
    'locid',
    'language',
    'categories',
    'online_meeting_url',
    'online_meeting_label',
    'meta_keywords',
    'meta_description',
    'event_status',
    'ticket_availability',
), 'event');
$venueMappingFields = $buildImportMappingFields(array(
    'venue',
    'alias',
    'color',
    'url',
    'street',
    'postalCode',
    'city',
    'district',
    'level',
    'capacity',
    'state',
    'country',
    'email',
    'phone',
    'mobile',
    'latitude',
    'longitude',
    'coordinates',
    'locdescription',
    'meta_keywords',
    'meta_description',
    'locimage',
    'map',
    'published',
    'publish_up',
    'publish_down',
    'access',
    'attribs',
    'language',
    'type_id',
), 'venue');
$venueMappingFields['coordinates']['label'] = Text::_('COM_JEM_IMPORT_COORDINATES_SPLIT_FIELD');
$specialDaysMappingFields = array();
foreach (array(
    'id',
    'title',
    'alias',
    'day_type_id',
    'day_type',
    'start_date',
    'end_date',
    'weekdays',
    'country',
    'region',
    'city',
    'description',
    'article_id',
    'url',
    'show_dates',
    'published',
    'access',
    'ordering',
) as $field) {
    $specialDaysMappingFields[$field] = array(
        'label' => $field . ' (JEM)',
        'kind' => 'jem',
    );
}

$eventCatalogEntry = (array) ($this->selectedImportCatalogEntry ?? array());
if (!$eventCatalogEntry || JemImportCatalogHelper::getContext($eventCatalogEntry['type'] ?? '') !== 'events') {
    $eventCatalogEntry = array();
}

$venueCatalogEntry = (array) ($this->selectedImportCatalogEntry ?? array());
if (!$venueCatalogEntry || JemImportCatalogHelper::getContext($venueCatalogEntry['type'] ?? '') !== 'venues') {
    $venueCatalogEntry = array();
}

?>
<style>
    .modal[id^="jem-import-create-"] .modal-dialog {
        width: 90vw;
        max-width: 90vw;
        height: 90vh;
        margin-top: 5vh;
        margin-bottom: 5vh;
    }

    .modal[id^="jem-import-create-"] .modal-content {
        height: 90vh;
    }

    .modal[id^="jem-import-create-"] .modal-body {
        height: calc(90vh - 8.5rem);
        padding: 0;
    }

    .modal[id^="jem-import-create-"] iframe {
        width: 100% !important;
        height: 100% !important;
    }

    .jem-import-mapping-layout {
        display: grid;
        grid-template-columns: max-content minmax(38rem, 1fr);
        gap: 1rem;
        align-items: start;
        max-width: 100%;
    }

    .jem-import-mapping-panel,
    .jem-import-static-panel {
        min-width: 0;
    }

    .jem-import-static-panel {
        width: min(100%, 68rem);
        max-width: 100%;
    }

    .jem-import-static-table th,
    .jem-import-static-table td {
        padding: .35rem .5rem;
        vertical-align: middle;
        width: auto;
    }

    .jem-import-static-table tbody tr:nth-child(odd) {
        background: rgba(0, 0, 0, .035);
    }

    .jem-import-static-table .form-control,
    .jem-import-static-table .form-select {
        min-height: 2rem;
        padding-top: .2rem;
        padding-bottom: .2rem;
    }

    .jem-import-static-table .jem-import-static-field {
        width: max-content;
        min-width: 14rem;
        max-width: min(28rem, 100%);
    }

    .jem-import-static-table .jem-import-static-value {
        width: 18rem;
        min-width: 12rem;
        max-width: 100%;
    }

    .jem-import-static-table .jem-import-static-mode {
        width: max-content;
        min-width: 9rem;
    }

    @media (max-width: 1100px) {
        .jem-import-mapping-layout {
            grid-template-columns: 1fr;
        }

        .jem-import-static-table .jem-import-static-field {
            max-width: none;
        }
    }
</style>
<?php if($this->progress->step > 1) : ?>
    <meta http-equiv="refresh" content="1; url=index.php?option=com_jem&amp;view=import&amp;task=import.eventlistimport&amp;step=<?php
    echo $this->progress->step; ?>&amp;table=<?php echo $this->progress->table; ?>&amp;current=<?php
    echo $this->progress->current; ?>&amp;total=<?php echo $this->progress->total; ?>" />
<?php endif; ?>

<?php if (isset($this->sidebar)) : ?>
<div id="j-sidebar-container" class="span2">
    <?php echo $this->sidebar; ?>
</div>
<div id="j-main-container" class="span10">
    <?php endif; ?>

    <div id="j-main-container" class="j-main-container jem-import-page">
        <form action="<?php echo Route::_('index.php?option=com_jem&view=import'); ?>" method="post" name="adminForm" enctype="multipart/form-data" id="adminForm">
            <?php echo HTMLHelper::_('uitab.startTabSet', 'jem-import-tabs', array('active' => 'event-import', 'recall' => false, 'breakpoint' => 768)); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'jem-import-tabs', 'event-import', Text::_('COM_JEM_IMPORT_TAB_EVENT_IMPORT')); ?>
                <?php
                $eventSelectedProfileId = (int) ($this->selectedExternalImportProfileId ?? 0);
                $eventSelectedMode = (string) ($this->externalImportPreview['mode'] ?? 'standard');
                $eventSelectedPublished = (int) ($this->externalImportPreview['published'] ?? 1);
                $eventSelectedPublishUp = (string) ($this->externalImportPreview['publish_up'] ?? $this->externalPublishUpDefault);
                $eventSelectedLanguage = (string) ($this->externalImportPreview['language'] ?? '*');
                ?>
                <div class="jem-import-tab-intro">
                    <h2><?php echo Text::_('COM_JEM_IMPORT_EVENT_IMPORT_TITLE'); ?></h2>
                    <p><?php echo Text::_('COM_JEM_IMPORT_EVENT_IMPORT_DESC'); ?></p>
                </div>
                <?php $renderCatalogSelectionNotice('events'); ?>
                <div class="jem-import-grid">
                    <section class="jem-import-card">
                        <div class="jem-import-card-header">
                            <div>
                                <h3><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_EVENTS_TITLE'); ?></h3>
                                <p><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_EVENTS_DESC'); ?></p>
                            </div>
                        </div>
                        <div class="jem-import-profile-first">
                            <label for="external_import_profile_id"><?php echo Text::_('COM_JEM_IMPORT_PROFILE_LABEL'); ?></label>
                            <?php echo HTMLHelper::_('select.genericlist', $this->externalImportProfileOptions, 'external_import_profile_id', 'class="form-select" id="external_import_profile_id"', 'value', 'text', $eventSelectedProfileId); ?>
                        </div>
                        <div class="jem-import-profile-summary" data-profile-summary="events" hidden>
                            <strong><?php echo Text::_('COM_JEM_IMPORT_PROFILE_CONFIGURATION'); ?></strong>
                            <div class="jem-import-profile-summary-content"></div>
                        </div>
                        <hr class="jem-import-profile-separator">
                        <div class="jem-import-row">
                            <div class="jem-import-field jem-import-field-file">
                                <label><?php echo Text::_('COM_JEM_IMPORT_SOURCE'); ?></label>
                                <?php $eventSourceUrl = (string) ($eventCatalogEntry['source'] ?? ($this->externalImportPreview['source_url'] ?? '')); ?>
                                <div class="jem-import-source-choice" data-source-choice="external-import">
                                    <label>
                                        <input type="radio" name="external_import_source_mode" value="url"<?php echo $eventSourceUrl !== '' ? ' checked' : ''; ?>>
                                        <?php echo Text::_('COM_JEM_IMPORT_SOURCE_URL'); ?>
                                    </label>
                                    <label>
                                        <input type="radio" name="external_import_source_mode" value="file"<?php echo $eventSourceUrl === '' ? ' checked' : ''; ?>>
                                        <?php echo Text::_('COM_JEM_IMPORT_SOURCE_FILE'); ?>
                                    </label>
                                </div>
                                <div class="jem-import-source-url" data-source-panel="external-import-url"<?php echo $eventSourceUrl === '' ? ' hidden' : ''; ?>>
                                    <label for="external-import-source-url"><?php echo Text::_('COM_JEM_IMPORT_SOURCE_URL'); ?></label>
                                    <input type="url" id="external-import-source-url" name="external_import_source_url" class="form-control" value="<?php echo htmlspecialchars($eventSourceUrl, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Event source URL">
                                </div>
                                <div class="jem-import-source-file" data-source-panel="external-import-file"<?php echo $eventSourceUrl !== '' ? ' hidden' : ''; ?>>
                                    <label for="external-import-file-upload"><?php echo Text::_('COM_JEM_IMPORT_SELECT_CSV_JSON_XML_OR_ICS'); ?></label>
                                    <input type="file" id="external-import-file-upload" accept=".csv,.json,.xml,.ics,text/csv,application/json,text/xml,application/xml,text/calendar,text/plain" name="FileExternalImport" class="form-control" />
                                </div>
                                <?php if (!empty($this->externalImportPreview['source_name'])) : ?>
                                    <div class="form-text jem-import-source-note">
                                        <?php echo htmlspecialchars(Text::sprintf('COM_JEM_IMPORT_PREVIEW_SOURCE_FILE', $this->externalImportPreview['source_name']), ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                            <div class="jem-import-field">
                                <label for="external_import_catid"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_DESTINATION_CATEGORY'); ?></label>
                                <div class="jem-import-select-actions">
                                    <?php echo $renderFancySelect($this->externalCategoryOptions, 'external_import_catid', 'external_import_catid', $this->externalImportPreview['catid'] ?? 0); ?>
                                    <?php $renderCreateModalButton('external-category', 'category.add', 'COM_JEM_IMPORT_CREATE_CATEGORY', 'COM_JEM_IMPORT_CREATE_CATEGORY_TITLE', 'external_import_catid', 'jform_catname', 'category.save'); ?>
                                </div>
                            </div>
                            <div class="jem-import-field">
                                <label for="external_import_mode"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_IMPORT_AS'); ?></label>
                                <select name="external_import_mode" id="external_import_mode" class="form-select">
                                    <option value="standard" <?php echo $eventSelectedMode === 'standard' ? 'selected' : ''; ?>><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_STANDARD_EVENTS'); ?></option>
                                    <option value="openday" <?php echo $eventSelectedMode === 'openday' ? 'selected' : ''; ?>><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_OPEN_DAY_EVENTS'); ?></option>
                                </select>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                        </div>
                        <div class="jem-import-row">
                            <div class="jem-import-field">
                                <label for="external_import_type_id"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_DEFAULT_TYPE'); ?></label>
                                <div class="jem-import-select-actions">
                                    <?php echo $renderFancySelect($this->externalTypeOptions, 'external_import_type_id', 'external_import_type_id', $this->externalImportPreview['type_id'] ?? 0); ?>
                                    <?php $renderCreateModalButton('external-type', 'type.add', 'COM_JEM_IMPORT_CREATE_TYPE', 'COM_JEM_IMPORT_CREATE_TYPE_TITLE', 'external_import_type_id', 'jform_name', 'type.save', 1); ?>
                                </div>
                            </div>
                            <div class="jem-import-field">
                                <label for="external_import_locid"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_DEFAULT_VENUE'); ?></label>
                                <div class="jem-import-select-actions">
                                    <?php echo $renderFancySelect($this->externalVenueOptions, 'external_import_locid', 'external_import_locid', $this->externalImportPreview['locid'] ?? 0); ?>
                                    <?php $renderCreateModalButton('external-venue', 'venue.add', 'COM_JEM_IMPORT_CREATE_VENUE', 'COM_JEM_IMPORT_CREATE_VENUE_TITLE', 'external_import_locid', 'jform_venue', 'venue.save'); ?>
                                </div>
                            </div>
                            <div class="jem-import-field">
                                <label for="external_import_published"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PUBLISHED_STATE'); ?></label>
                                <select name="external_import_published" id="external_import_published" class="form-select">
                                    <option value="1" <?php echo $eventSelectedPublished === 1 ? 'selected' : ''; ?>><?php echo Text::_('JPUBLISHED'); ?></option>
                                    <option value="0" <?php echo $eventSelectedPublished === 0 ? 'selected' : ''; ?>><?php echo Text::_('JUNPUBLISHED'); ?></option>
                                </select>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                            <div class="jem-import-field">
                                <label for="external_import_publish_up"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PUBLISH_UP'); ?></label>
                                <?php echo HTMLHelper::_('calendar', $eventSelectedPublishUp, 'external_import_publish_up', 'external_import_publish_up', '%Y-%m-%d %H:%M:%S', array('class' => 'form-control', 'showTime' => true, 'timeFormat' => '24')); ?>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                            <div class="jem-import-field">
                                <label for="external_import_language"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_LANGUAGE'); ?></label>
                                <?php echo HTMLHelper::_('select.genericlist', $this->externalLanguageOptions, 'external_import_language', 'class="form-select" id="external_import_language"', 'value', 'text', $eventSelectedLanguage); ?>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                        </div>
                        <div class="jem-import-actions jem-import-actions-row">
                            <button type="button" class="btn btn-primary" onclick="JemImportSubmit('import.previewExternalImport', 'event-import');">
                                <span class="icon-search" aria-hidden="true"></span>
                                <?php echo empty($this->externalImportPreview) ? Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW') : Text::_('COM_JEM_IMPORT_EXTERNAL_REFRESH_PREVIEW'); ?>
                            </button>
                        </div>
                        <details class="jem-import-columns">
                            <summary><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_EVENTS_CSV_HELP_TITLE'); ?></summary>
                            <div class="mt-2">
                                <p><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_EVENTS_CSV_HELP_DESC'); ?></p>
                                <pre class="jem-import-example"><code><?php echo $this->escape(Text::_('COM_JEM_IMPORT_EXTERNAL_EVENTS_CSV_HEADER')); ?></code></pre>
                                <pre class="jem-import-example"><code><?php echo $this->escape(Text::_('COM_JEM_IMPORT_EXTERNAL_EVENTS_CSV_ALT_HEADER')); ?></code></pre>
                                <p class="jem-import-help-note"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_EVENTS_CSV_COMPLETE_EXAMPLE_LABEL'); ?></p>
                                <pre class="jem-import-example"><code><?php echo $this->escape(str_replace('\n', "\n", Text::_('COM_JEM_IMPORT_EXTERNAL_EVENTS_CSV_COMPLETE_EXAMPLE'))); ?></code></pre>
                                <p class="jem-import-help-note"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_EVENTS_CSV_SIMPLE_EXAMPLE_LABEL'); ?></p>
                                <pre class="jem-import-example"><code><?php echo $this->escape(str_replace('\n', "\n", Text::_('COM_JEM_IMPORT_EXTERNAL_EVENTS_CSV_SIMPLE_EXAMPLE'))); ?></code></pre>
                                <p class="jem-import-help-note"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_EVENTS_CSV_ALIASES'); ?></p>
                            </div>
                        </details>
                    </section>
                </div>
                <?php if (!empty($this->externalCsvPreview)) : ?>
                    <section class="jem-import-card jem-import-preview-card">
                        <h3><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_EVENTS_PREVIEW_TITLE'); ?></h3>
                        <p><?php echo htmlspecialchars($this->externalCsvPreview['summary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><?php echo Text::sprintf('COM_JEM_IMPORT_DETECTED_FORMAT', strtoupper($this->externalCsvPreview['format'] ?? 'csv')); ?></p>
                        <?php $renderImportMappingBlock((array) $this->externalCsvPreview, 'external_import_mapping', $eventMappingFields, 'external_import_profile'); ?>
                        <?php $renderDynamicPreviewTable((array) $this->externalCsvPreview); ?>
                        <?php $renderPreviewActions('import.commitExternalImport', 'import.clearExternalImportPreview', 'event-import', $this->externalCsvPreview['valid_count'] ?? 0); ?>
                    </section>
                <?php endif; ?>
                <?php if (!empty($this->externalIcsPreview)) : ?>
                    <section class="jem-import-card jem-import-preview-card">
                        <h3><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_ICS_PREVIEW_TITLE'); ?></h3>
                        <p><?php echo htmlspecialchars($this->externalIcsPreview['summary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <div class="table-responsive">
                            <table class="adminlist table jem-import-paged-table" data-page-size="50">
                                <thead>
                                    <tr>
                                        <th class="center">#</th>
                                        <th><?php echo Text::_('COM_JEM_TITLE'); ?></th>
                                        <th><?php echo Text::_('COM_JEM_DATE'); ?></th>
                                        <th><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_TIME'); ?></th>
                                        <th><?php echo Text::_('COM_JEM_CATEGORY'); ?></th>
                                        <th><?php echo Text::_('COM_JEM_TYPE'); ?></th>
                                        <th><?php echo Text::_('COM_JEM_VENUE'); ?></th>
                                        <th><?php echo Text::_('JFIELD_LANGUAGE_LABEL'); ?></th>
                                        <th><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PUBLISH_UP'); ?></th>
                                        <th><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_NOTES'); ?></th>
                                        <th><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_STATUS'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($this->externalIcsPreview['rows'] ?? array()) as $index => $row) : ?>
                                        <tr>
                                            <td class="center"><?php echo (int) $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($row['date_label'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($row['time_label'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($this->externalIcsPreview['category_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($this->externalIcsPreview['type_label'] ?? Text::_('JNONE'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($this->externalIcsPreview['venue_label'] ?? Text::_('JNONE'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($this->externalIcsPreview['language_label'] ?? '*', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($this->externalIcsPreview['publish_up_label'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars(implode('; ', $row['notes']), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo $renderImportStatus($row['status'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php $renderPreviewActions('import.commitExternalImport', 'import.clearExternalImportPreview', 'event-import', $this->externalIcsPreview['valid_count'] ?? 0); ?>
                    </section>
                <?php endif; ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'jem-import-tabs', 'venue-import', Text::_('COM_JEM_IMPORT_TAB_VENUE_IMPORT')); ?>
                <?php
                $venueSelectedProfileId = (int) ($this->selectedExternalVenueImportProfileId ?? 0);
                $venueSelectedTypeId = (int) ($this->externalVenueImportPreview['type_id'] ?? 0);
                $venueSelectedPublished = (int) ($this->externalVenueImportPreview['published'] ?? 1);
                $venueSelectedLanguage = (string) ($this->externalVenueImportPreview['language'] ?? '*');
                ?>
                <div class="jem-import-tab-intro">
                    <h2><?php echo Text::_('COM_JEM_IMPORT_VENUE_IMPORT_TITLE'); ?></h2>
                    <p><?php echo Text::_('COM_JEM_IMPORT_VENUE_IMPORT_DESC'); ?></p>
                </div>
                <?php $renderCatalogSelectionNotice('venues'); ?>
                <div class="jem-import-grid">
                    <section class="jem-import-card">
                        <div class="jem-import-card-header">
                            <div>
                                <h3><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_TITLE'); ?></h3>
                                <p><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_DESC'); ?></p>
                            </div>
                        </div>
                        <div class="jem-import-profile-first">
                            <label for="external_venue_import_profile_id"><?php echo Text::_('COM_JEM_IMPORT_PROFILE_LABEL'); ?></label>
                            <?php echo HTMLHelper::_('select.genericlist', $this->externalVenueImportProfileOptions, 'external_venue_import_profile_id', 'class="form-select" id="external_venue_import_profile_id"', 'value', 'text', $venueSelectedProfileId); ?>
                        </div>
                        <div class="jem-import-profile-summary" data-profile-summary="venues" hidden>
                            <strong><?php echo Text::_('COM_JEM_IMPORT_PROFILE_CONFIGURATION'); ?></strong>
                            <div class="jem-import-profile-summary-content"></div>
                        </div>
                        <hr class="jem-import-profile-separator">
                        <div class="jem-import-row">
                            <div class="jem-import-field jem-import-field-file">
                                <label><?php echo Text::_('COM_JEM_IMPORT_SOURCE'); ?></label>
                                <?php $venueSourceUrl = (string) ($venueCatalogEntry['source'] ?? ($this->externalVenueImportPreview['source_url'] ?? '')); ?>
                                <div class="jem-import-source-choice" data-source-choice="external-venue-import">
                                    <label>
                                        <input type="radio" name="external_venue_import_source_mode" value="url"<?php echo $venueSourceUrl !== '' ? ' checked' : ''; ?>>
                                        <?php echo Text::_('COM_JEM_IMPORT_SOURCE_URL'); ?>
                                    </label>
                                    <label>
                                        <input type="radio" name="external_venue_import_source_mode" value="file"<?php echo $venueSourceUrl === '' ? ' checked' : ''; ?>>
                                        <?php echo Text::_('COM_JEM_IMPORT_SOURCE_FILE'); ?>
                                    </label>
                                </div>
                                <div class="jem-import-source-url" data-source-panel="external-venue-import-url"<?php echo $venueSourceUrl === '' ? ' hidden' : ''; ?>>
                                    <label for="external-venue-import-source-url"><?php echo Text::_('COM_JEM_IMPORT_SOURCE_URL'); ?></label>
                                    <input type="url" id="external-venue-import-source-url" name="external_venue_import_source_url" class="form-control" value="<?php echo htmlspecialchars($venueSourceUrl, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Venue source URL">
                                </div>
                                <div class="jem-import-source-file" data-source-panel="external-venue-import-file"<?php echo $venueSourceUrl !== '' ? ' hidden' : ''; ?>>
                                    <label for="external-venue-import-file-upload"><?php echo Text::_('COM_JEM_IMPORT_SELECT_CSV_JSON_XML_OR_XLSX'); ?></label>
                                    <input type="file" id="external-venue-import-file-upload" accept=".csv,.json,.xml,.xlsx,text/csv,application/json,text/xml,application/xml,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain" name="FileExternalVenueImport" class="form-control" />
                                </div>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                            <div class="jem-import-field">
                                <label for="external_venue_import_type_id"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_DEFAULT_TYPE'); ?></label>
                                <div class="jem-import-select-actions">
                                    <?php echo $renderFancySelect($this->externalVenueTypeOptions, 'external_venue_import_type_id', 'external_venue_import_type_id', $venueSelectedTypeId); ?>
                                    <?php $renderCreateModalButton('external-venue-type', 'type.add', 'COM_JEM_IMPORT_CREATE_TYPE', 'COM_JEM_IMPORT_CREATE_TYPE_TITLE', 'external_venue_import_type_id', 'jform_name', 'type.save', 3); ?>
                                </div>
                            </div>
                            <div class="jem-import-field">
                                <label for="external_venue_import_published"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PUBLISHED_STATE'); ?></label>
                                <select name="external_venue_import_published" id="external_venue_import_published" class="form-select">
                                    <option value="1"<?php echo $venueSelectedPublished === 1 ? ' selected' : ''; ?>><?php echo Text::_('JPUBLISHED'); ?></option>
                                    <option value="0"<?php echo $venueSelectedPublished === 0 ? ' selected' : ''; ?>><?php echo Text::_('JUNPUBLISHED'); ?></option>
                                </select>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                            <div class="jem-import-field">
                                <label for="external_venue_import_language"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_LANGUAGE'); ?></label>
                                <?php echo HTMLHelper::_('select.genericlist', $this->externalLanguageOptions, 'external_venue_import_language', 'class="form-select" id="external_venue_import_language"', 'value', 'text', $venueSelectedLanguage); ?>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                        </div>
                        <div class="jem-import-actions jem-import-actions-row">
                            <button type="button" class="btn btn-primary" onclick="JemImportSubmit('import.previewExternalVenueImport', 'venue-import');">
                                <span class="icon-search" aria-hidden="true"></span>
                                <?php echo empty($this->externalVenueImportPreview) ? Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW') : Text::_('COM_JEM_IMPORT_EXTERNAL_REFRESH_PREVIEW'); ?>
                            </button>
                        </div>
                        <details class="jem-import-columns">
                            <summary><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_CSV_HELP_TITLE'); ?></summary>
                            <div class="mt-2">
                                <p><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_CSV_HELP_DESC'); ?></p>
                                <pre class="jem-import-example"><code><?php echo $this->escape(Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_CSV_HEADER')); ?></code></pre>
                                <pre class="jem-import-example"><code><?php echo $this->escape(Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_CSV_ALT_HEADER')); ?></code></pre>
                                <p class="jem-import-help-note"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_CSV_COMPLETE_EXAMPLE_LABEL'); ?></p>
                                <pre class="jem-import-example"><code><?php echo $this->escape(str_replace('\n', "\n", Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_CSV_COMPLETE_EXAMPLE'))); ?></code></pre>
                                <p class="jem-import-help-note"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_CSV_SIMPLE_EXAMPLE_LABEL'); ?></p>
                                <pre class="jem-import-example"><code><?php echo $this->escape(str_replace('\n', "\n", Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_CSV_SIMPLE_EXAMPLE'))); ?></code></pre>
                                <p class="jem-import-help-note"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_CSV_ALIASES'); ?></p>
                            </div>
                        </details>
                    </section>
                </div>
                <?php if (!empty($this->externalVenueImportPreview)) : ?>
                    <section class="jem-import-card jem-import-preview-card" data-import-preview-context="venues">
                        <h3><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_PREVIEW_TITLE'); ?></h3>
                        <p><?php echo htmlspecialchars($this->externalVenueImportPreview['summary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if (($this->externalVenueImportPreview['displayed_count'] ?? 0) < ($this->externalVenueImportPreview['valid_count'] ?? 0) + ($this->externalVenueImportPreview['error_count'] ?? 0)) : ?>
                            <p class="alert alert-info"><?php echo Text::sprintf('COM_JEM_IMPORT_EXTERNAL_PREVIEW_PAGED_SAMPLE', (int) ($this->externalVenueImportPreview['preview_page_size'] ?? 100), (int) (($this->externalVenueImportPreview['valid_count'] ?? 0) + ($this->externalVenueImportPreview['error_count'] ?? 0))); ?></p>
                        <?php endif; ?>
                        <p><?php echo Text::sprintf('COM_JEM_IMPORT_DETECTED_FORMAT', strtoupper($this->externalVenueImportPreview['format'] ?? 'csv')); ?></p>
                        <?php if (!empty($this->externalVenueImportPreview['profile_title'])) : ?>
                            <p><?php echo Text::sprintf('COM_JEM_IMPORT_PROFILE_APPLIED', htmlspecialchars($this->externalVenueImportPreview['profile_title'], ENT_QUOTES, 'UTF-8')); ?></p>
                        <?php endif; ?>
                        <?php $renderImportMappingBlock((array) $this->externalVenueImportPreview, 'external_venue_import_mapping', $venueMappingFields, 'external_venue_import_profile'); ?>
                        <div class="alert alert-warning d-none mt-3" data-venue-preview-dirty role="status">
                            <?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_MAPPING_CHANGED'); ?>
                        </div>
                        <?php $renderDynamicPreviewTable((array) $this->externalVenueImportPreview); ?>
                        <?php $renderPreviewActions('import.commitExternalVenueImport', 'import.clearExternalVenueImportPreview', 'venue-import', $this->externalVenueImportPreview['valid_count'] ?? 0); ?>
                    </section>
                <?php endif; ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'jem-import-tabs', 'jem-migration', Text::_('COM_JEM_IMPORT_TAB_JEM_MIGRATION')); ?>
                <div class="jem-import-tab-intro">
                    <h2><?php echo Text::_('COM_JEM_IMPORT_JEM_MIGRATION_TITLE'); ?></h2>
                    <p><?php echo Text::_('COM_JEM_IMPORT_JEM_MIGRATION_DESC'); ?></p>
                </div>
                <div class="alert alert-info">
                    <strong><?php echo Text::_('COM_JEM_IMPORT_INSTRUCTIONS'); ?></strong>
                    <p><?php echo Text::_('COM_JEM_IMPORT_INSTRUCTIONS_DESC'); ?></p>
                    <p><?php echo Text::_('COM_JEM_IMPORT_FIRSTROW'); ?></p>
                </div>
                <div class="jem-import-grid">
                    <?php
                    $renderMigrationCsvBlock('venues', 'COM_JEM_IMPORT_VENUES', 'COM_JEM_IMPORT_VENUES_DESC', 'COM_JEM_IMPORT_SHOW_VENUE_COLUMNS', $this->venuefields, 'replace_venues', 'Filevenues', 'import.csvvenuesimport');
                    $renderMigrationCsvBlock('categories', 'COM_JEM_IMPORT_CATEGORIES', 'COM_JEM_IMPORT_CATEGORIES_DESC', 'COM_JEM_IMPORT_SHOW_CATEGORY_COLUMNS', $this->catfields, 'replace_categories', 'Filecategories', 'import.csvcategoriesimport');
                    $renderMigrationCsvBlock('events', 'COM_JEM_IMPORT_EVENTS', 'COM_JEM_IMPORT_EVENTS_DESC', 'COM_JEM_IMPORT_SHOW_EVENT_COLUMNS', array_merge($this->eventfields, array('categories')), 'replace_events', 'Fileevents', 'import.csveventimport');
                    $renderMigrationCsvBlock('catevents', 'COM_JEM_IMPORT_CAT_EVENTS', 'COM_JEM_IMPORT_CAT_EVENTS_DESC', 'COM_JEM_IMPORT_SHOW_CATEVENT_COLUMNS', $this->cateventsfields, 'replace_catevents', 'Filecatevents', 'import.csvcateventsimport');
                    $renderMigrationCsvBlock('attachments', 'COM_JEM_IMPORT_ATTACHMENTS', 'COM_JEM_IMPORT_ATTACHMENTS_DESC', 'COM_JEM_IMPORT_SHOW_ATTACHMENT_COLUMNS', $this->attachmentfields, 'replace_attachments', 'Fileattachments', 'import.csvattachmentsimport');
                    $renderMigrationCsvBlock('types', 'COM_JEM_IMPORT_TYPES', 'COM_JEM_IMPORT_TYPES_DESC', 'COM_JEM_IMPORT_SHOW_TYPE_COLUMNS', $this->typefields, 'replace_types', 'Filetypes', 'import.csvtypesimport');
                    ?>
                </div>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'jem-import-tabs', 'special-days', Text::_('COM_JEM_IMPORT_TAB_SPECIAL_DAYS')); ?>
                <?php
                $specialDaysFormState = (array) ($this->specialDaysImportFormState ?? array());
                $specialDaysSelectedProfileId = (int) (($this->specialDaysImportPreview['profile_id'] ?? 0) ?: ($specialDaysFormState['profile_id'] ?? ($this->selectedSpecialDaysImportProfileId ?? 0)));
                $specialDaysSelectedType = (string) (($this->specialDaysImportPreview['day_type_id'] ?? '') ?: ($specialDaysFormState['day_type'] ?? ($this->specialDaysImportPreview['day_type'] ?? '')));
                $specialDaysReplace = (int) ($this->specialDaysImportPreview['replace'] ?? ($specialDaysFormState['replace'] ?? 0));
                $specialDaysShowDates = (int) ($this->specialDaysImportPreview['show_dates'] ?? ($specialDaysFormState['show_dates'] ?? 1));
                ?>
                <div class="jem-import-tab-intro">
                    <h2><?php echo Text::_('COM_JEM_SPECIAL_DAYS'); ?></h2>
                    <p><?php echo Text::_('COM_JEM_IMPORT_SPECIAL_DAYS_DESC'); ?></p>
                </div>
                <?php $renderCatalogSelectionNotice('specialdays'); ?>
                <div class="jem-import-grid">
                    <section class="jem-import-card jem-import-specialdays-card">
                        <div class="jem-import-card-header">
                            <div>
                                <h3><?php echo Text::_('COM_JEM_SPECIAL_DAYS_IMPORT_TITLE'); ?></h3>
                                <p><?php echo Text::_('COM_JEM_SPECIAL_DAYS_IMPORT_DESC'); ?></p>
                            </div>
                        </div>
                        <div class="jem-import-row">
                            <div class="jem-import-field jem-import-field-file">
                                <label for="specialdays-import-file-upload"><?php echo Text::_('COM_JEM_IMPORT_SELECT_FILE'); ?></label>
                                <input type="file" id="specialdays-import-file-upload" accept=".csv,.clm,.json,.xml,.ics,text/csv,application/json,application/xml,text/xml,text/calendar,text/plain" name="FileSpecialDaysImport" class="form-control" />
                                <?php if (!empty($this->specialDaysImportPreview['source_name'])) : ?>
                                    <div class="form-text jem-import-source-note">
                                        <?php echo htmlspecialchars(Text::sprintf('COM_JEM_IMPORT_PREVIEW_SOURCE_FILE', $this->specialDaysImportPreview['source_name']), ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                                <label for="specialdays_import_profile_id" class="mt-2"><?php echo Text::_('COM_JEM_IMPORT_PROFILE_LABEL'); ?></label>
                                <?php echo HTMLHelper::_('select.genericlist', $this->specialDaysImportProfileOptions, 'specialdays_import_profile_id', 'class="form-select" id="specialdays_import_profile_id"', 'value', 'text', $specialDaysSelectedProfileId); ?>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                            <div class="jem-import-field">
                                <label for="specialdays_import_day_type">
                                    <?php echo Text::_('COM_JEM_SPECIAL_DAY_FIELD_TYPE'); ?>
                                    <span><?php echo Text::_('COM_JEM_IMPORT_SPECIAL_DAYS_TYPE_FALLBACK_DESC'); ?></span>
                                </label>
                                <?php echo HTMLHelper::_('select.genericlist', $this->specialDayTypeOptions, 'specialdays_import_day_type', 'class="form-select" id="specialdays_import_day_type"', 'value', 'text', $specialDaysSelectedType); ?>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                            <div class="jem-import-field jem-import-field-replace">
                                <label for="replace_specialdays_import">
                                    <?php echo Text::_('COM_JEM_IMPORT_REPLACEIFEXISTS_LABEL'); ?>
                                    <span><?php echo Text::_('COM_JEM_IMPORT_REPLACEIFEXISTS_HELP'); ?></span>
                                </label>
                                <select name="replace_specialdays_import" id="replace_specialdays_import" class="form-select">
                                    <option value="0" <?php echo $specialDaysReplace === 0 ? 'selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
                                    <option value="1" <?php echo $specialDaysReplace === 1 ? 'selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
                                </select>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                            <div class="jem-import-field jem-import-field-show-dates">
                                <label for="specialdays_import_show_dates">
                                    <?php echo Text::_('COM_JEM_SPECIAL_DAY_FIELD_SHOW_DATES'); ?>
                                    <span><?php echo Text::_('COM_JEM_IMPORT_SPECIAL_DAYS_SHOW_DATES_FALLBACK_DESC'); ?></span>
                                </label>
                                <select name="specialdays_import_show_dates" id="specialdays_import_show_dates" class="form-select">
                                    <option value="1" <?php echo $specialDaysShowDates === 1 ? 'selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
                                    <option value="0" <?php echo $specialDaysShowDates === 0 ? 'selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
                                </select>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                        </div>
                        <div class="jem-import-actions jem-import-actions-row">
                            <button type="button" class="btn btn-primary" onclick="JemImportSubmit('import.previewSpecialDaysImport', 'special-days');">
                                <span class="icon-search" aria-hidden="true"></span>
                                <?php echo empty($this->specialDaysImportPreview) ? Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW') : Text::_('COM_JEM_IMPORT_EXTERNAL_REFRESH_PREVIEW'); ?>
                            </button>
                        </div>
                        <details class="jem-import-columns">
                            <summary><?php echo Text::_('COM_JEM_SPECIAL_DAYS_IMPORT_CSV_HELP_TITLE'); ?></summary>
                            <div class="mt-2">
                                <p><?php echo Text::_('COM_JEM_SPECIAL_DAYS_IMPORT_CSV_FIELDS'); ?></p>
                                <pre class="jem-import-example"><code><?php echo $this->escape(str_replace('\n', "\n", Text::_('COM_JEM_SPECIAL_DAYS_IMPORT_CSV_EXAMPLE'))); ?></code></pre>
                            </div>
                        </details>
                    </section>
                </div>
                <?php $specialDaysPreview = $this->specialDaysImportPreview ?? null; ?>
                <?php if (!empty($specialDaysPreview)) : ?>
                        <section class="jem-import-card jem-import-preview-card">
                            <h3><?php echo htmlspecialchars($specialDaysPreview['title'] ?? Text::_('COM_JEM_SPECIAL_DAYS_IMPORT_PREVIEW_TITLE'), ENT_QUOTES, 'UTF-8'); ?></h3>
                            <p><?php echo htmlspecialchars($specialDaysPreview['summary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p><?php echo Text::sprintf('COM_JEM_IMPORT_DETECTED_FORMAT', strtoupper($specialDaysPreview['format'] ?? 'csv')); ?></p>
                            <?php if (!empty($specialDaysPreview['source_fields'])) : ?>
                                <?php if (!empty($specialDaysPreview['profile_title'])) : ?>
                                    <p><?php echo Text::sprintf('COM_JEM_IMPORT_PROFILE_APPLIED', htmlspecialchars($specialDaysPreview['profile_title'], ENT_QUOTES, 'UTF-8')); ?></p>
                                <?php endif; ?>
                                <?php $renderImportMappingBlock((array) $specialDaysPreview, 'specialdays_import_mapping', $specialDaysMappingFields, 'specialdays_import_profile'); ?>
                                <?php $renderDynamicPreviewTable((array) $specialDaysPreview); ?>
                                <?php $renderPreviewActions('import.commitSpecialDaysImport', 'import.clearSpecialDaysImportPreview', 'special-days', $specialDaysPreview['valid_count'] ?? 0); ?>
                            <?php else : ?>
                                <div class="table-responsive">
                                    <table class="adminlist table jem-import-paged-table" data-page-size="50">
                                        <thead>
                                            <tr>
                                                <th class="center">#</th>
                                                <th><?php echo Text::_('COM_JEM_TITLE'); ?></th>
                                                <th><?php echo Text::_('COM_JEM_DATE'); ?></th>
                                                <th><?php echo Text::_('COM_JEM_SPECIAL_DAY_FIELD_TYPE'); ?></th>
                                                <th><?php echo Text::_('JGLOBAL_DESCRIPTION'); ?></th>
                                                <th><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_NOTES'); ?></th>
                                                <th><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_STATUS'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (($specialDaysPreview['rows'] ?? array()) as $index => $row) : ?>
                                                <tr>
                                                    <td class="center"><?php echo (int) $index + 1; ?></td>
                                                    <td><?php echo htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($row['date_label'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo $renderImportFieldValue($row['day_type'] ?? ($specialDaysPreview['day_type'] ?? ''), 'day_type', (array) $row); ?></td>
                                                    <td><?php echo htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars(implode('; ', $row['notes']), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo $renderImportStatus($row['status'] ?? ''); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php $renderPreviewActions('import.commitSpecialDaysImport', 'import.clearSpecialDaysImportPreview', 'special-days', $specialDaysPreview['valid_count'] ?? 0); ?>
                            <?php endif; ?>
                        </section>
                <?php endif; ?>
                <p class="jem-import-secondary-action">
                    <a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_jem&view=specialdays'); ?>">
                        <?php echo Text::_('COM_JEM_IMPORT_OPEN_SPECIAL_DAYS'); ?>
                    </a>
                    <a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_jem&view=settings#calendar_special_days'); ?>">
                        <?php echo Text::_('COM_JEM_IMPORT_OPEN_SPECIAL_DAYS_SETTINGS'); ?>
                    </a>
                </p>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'jem-import-tabs', 'advanced-tools', Text::_('COM_JEM_IMPORT_TAB_ADVANCED_TOOLS')); ?>
                <div class="jem-import-tab-intro">
                    <h2><?php echo Text::_('COM_JEM_IMPORT_ADVANCED_TOOLS_TITLE'); ?></h2>
                    <p><?php echo Text::_('COM_JEM_IMPORT_ADVANCED_TOOLS_DESC'); ?></p>
                </div>
                <section class="jem-import-card">
                    <h3><?php echo Text::_('COM_JEM_IMPORT_ADVANCED_TOOLS_REPORTS_TITLE'); ?></h3>
                    <p><?php echo Text::_('COM_JEM_IMPORT_ADVANCED_TOOLS_REPORTS_DESC'); ?></p>
                </section>
                <section class="jem-import-card">
                    <h3><?php echo Text::_('COM_JEM_IMPORT_LOGS_TITLE'); ?></h3>
                    <p><?php echo Text::sprintf('COM_JEM_IMPORT_LOGS_PATH', htmlspecialchars($this->importLogPath, ENT_QUOTES, 'UTF-8')); ?></p>
                    <div class="table-responsive">
                        <table class="adminlist table jem-import-logs-table">
                            <thead>
                                <tr>
                                    <th><?php echo Text::_('COM_JEM_IMPORT_LOGS_IMPORT_TYPE'); ?></th>
                                    <th><?php echo Text::_('COM_JEM_IMPORT_LOGS_FILE'); ?></th>
                                    <th><?php echo Text::_('COM_JEM_IMPORT_LOGS_ACTIONS'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->importLogs as $logFile) : ?>
                                    <?php
                                    $logFilePath = $this->importLogPath . DIRECTORY_SEPARATOR . $logFile['file'];
                                    $logExists = is_file($logFilePath) && is_readable($logFilePath);
                                    $viewUrl = Route::_('index.php?option=com_jem&task=import.viewLog&log=' . $logFile['key'] . '&' . Session::getFormToken() . '=1', false);
                                    $downloadUrl = Route::_('index.php?option=com_jem&task=import.downloadLog&log=' . $logFile['key'] . '&' . Session::getFormToken() . '=1', false);
                                    $modalId = 'jem-import-log-modal-' . preg_replace('/[^a-z0-9_-]/i', '-', $logFile['key']);
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($logFile['label'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><code><?php echo htmlspecialchars($logFilePath, ENT_QUOTES, 'UTF-8'); ?></code></td>
                                        <td>
                                            <?php if ($logExists) : ?>
                                                <?php
                                                echo HTMLHelper::_(
                                                    'bootstrap.renderModal',
                                                    $modalId,
                                                    array(
                                                        'url'    => $viewUrl,
                                                        'title'  => htmlspecialchars($logFile['file'], ENT_QUOTES, 'UTF-8'),
                                                        'width'  => '100%',
                                                        'height' => '600px',
                                                        'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
                                                    )
                                                );
                                                ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?php echo $modalId; ?>">
                                                    <?php echo Text::_('COM_JEM_IMPORT_LOGS_VIEW'); ?>
                                                </button>
                                                <a class="btn btn-sm btn-outline-primary" href="<?php echo $downloadUrl; ?>">
                                                    <span class="icon-download" aria-hidden="true"></span>
                                                    <?php echo Text::_('COM_JEM_IMPORT_LOGS_DOWNLOAD'); ?>
                                                </a>
                                            <?php else : ?>
                                                <span class="text-muted"><?php echo Text::_('COM_JEM_IMPORT_LOGS_NOT_CREATED'); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'jem-import-tabs', 'download-lists', Text::_('COM_JEM_IMPORT_TAB_DOWNLOAD_LISTS')); ?>
                <div class="jem-import-tab-intro">
                    <h2><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_TITLE'); ?></h2>
                    <p><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_DESC'); ?></p>
                </div>
                <section class="jem-import-card jem-import-card-planned">
                    <div class="jem-import-catalog-controls">
                        <div class="jem-import-catalog-filters">
                        <div class="jem-import-field">
                            <label for="jem-import-catalog-country"><?php echo Text::_('COM_JEM_IMPORT_CATALOG_COUNTRY'); ?></label>
                            <select id="jem-import-catalog-country" class="form-select">
                                <option value=""><?php echo Text::_('JALL'); ?></option>
                                <?php foreach (($this->importCatalogCountries ?? array()) as $code => $label) : ?>
                                    <option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label ?: $code, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="jem-import-field">
                            <label for="jem-import-catalog-county"><?php echo Text::_('COM_JEM_IMPORT_CATALOG_COUNTY'); ?></label>
                            <select id="jem-import-catalog-county" class="form-select">
                                <option value=""><?php echo Text::_('JALL'); ?></option>
                                <?php foreach (($this->importCatalogCounties ?? array()) as $value => $label) : ?>
                                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="jem-import-field">
                            <label for="jem-import-catalog-city"><?php echo Text::_('COM_JEM_IMPORT_CATALOG_CITY'); ?></label>
                            <select id="jem-import-catalog-city" class="form-select">
                                <option value=""><?php echo Text::_('JALL'); ?></option>
                                <?php foreach (($this->importCatalogCities ?? array()) as $value => $label) : ?>
                                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="jem-import-field">
                            <label for="jem-import-catalog-type"><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_TYPE'); ?></label>
                            <select id="jem-import-catalog-type" class="form-select">
                                <option value=""><?php echo Text::_('JALL'); ?></option>
                                <?php foreach (($this->importCatalogTypes ?? array()) as $value) : ?>
                                    <?php
                                    $typeKey = $value === 'venues'
                                        ? 'COM_JEM_IMPORT_CATALOG_TYPE_VENUES'
                                        : ($value === 'specialdays' ? 'COM_JEM_IMPORT_CATALOG_TYPE_SPECIAL_DAYS' : 'COM_JEM_IMPORT_CATALOG_TYPE_EVENTS');
                                    ?>
                                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo Text::_($typeKey); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="jem-import-field">
                            <label for="jem-import-catalog-format"><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_FORMAT'); ?></label>
                            <select id="jem-import-catalog-format" class="form-select">
                                <option value=""><?php echo Text::_('JALL'); ?></option>
                                <?php foreach (($this->importCatalogFormats ?? array()) as $value => $label) : ?>
                                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        </div>
                        <?php
                        $catalogStatus = (array) ($this->importCatalogStatus ?? array());
                        $catalogAvailable = !empty($catalogStatus['available']);
                        $catalogStatusClass = $catalogAvailable ? 'text-success' : 'text-danger';
                        $catalogStatusIcon = $catalogAvailable ? 'icon-check' : 'icon-times';
                        $catalogStatusText = $catalogAvailable ? Text::_('COM_JEM_IMPORT_CATALOG_AVAILABLE') : Text::_('COM_JEM_IMPORT_CATALOG_UNAVAILABLE');
                        ?>
                        <div class="jem-import-catalog-meta">
                        <div class="jem-import-field jem-import-catalog-source-field">
                            <label><?php echo Text::_('COM_JEM_IMPORT_CATALOG_SOURCE'); ?></label>
                            <span class="jem-import-catalog-source-line">
                                <code><?php echo htmlspecialchars($this->importCatalogSource ?? '', ENT_QUOTES, 'UTF-8'); ?></code>
                                <span class="jem-import-catalog-source-status <?php echo $catalogStatusClass; ?>" title="<?php echo htmlspecialchars($catalogStatusText, ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="<?php echo $catalogStatusIcon; ?>" aria-hidden="true"></span>
                                    <span class="visually-hidden"><?php echo $catalogStatusText; ?></span>
                                </span>
                            </span>
                            <?php if ($catalogAvailable && (!empty($catalogStatus['version']) || !empty($catalogStatus['published']))) : ?>
                                <span class="d-block text-muted">
                                    <?php echo Text::sprintf('COM_JEM_IMPORT_CATALOG_STATUS_DESC', htmlspecialchars((string) ($catalogStatus['version'] ?? ''), ENT_QUOTES, 'UTF-8'), htmlspecialchars((string) ($catalogStatus['published'] ?? ''), ENT_QUOTES, 'UTF-8')); ?>
                                </span>
                            <?php elseif (!$catalogAvailable) : ?>
                                <span class="d-block text-muted"><?php echo Text::_('COM_JEM_IMPORT_CATALOG_UNAVAILABLE_DESC'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="jem-import-actions">
                            <button type="button" class="btn btn-secondary" onclick="JemImportRefreshCatalog();">
                                <?php echo Text::_('COM_JEM_IMPORT_CATALOG_REFRESH'); ?>
                            </button>
                        </div>
                        </div>
                    </div>
                </section>
                <section class="jem-import-card jem-import-card-planned">
                    <h3><?php echo Text::_('COM_JEM_IMPORT_CATALOG_AVAILABLE_TITLE'); ?></h3>
                    <div class="table-responsive">
                        <table class="adminlist table jem-import-catalog-table" id="jem-import-catalog-table">
                            <thead>
                                <tr>
                                    <th><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_COUNTRY'); ?></th>
                                    <th><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_COUNTY'); ?></th>
                                    <th><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_CITY'); ?></th>
                                    <th><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_LIST'); ?></th>
                                    <th><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_TYPE'); ?></th>
                                    <th><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_FORMAT'); ?></th>
                                    <th class="text-end"><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_ITEMS'); ?></th>
                                    <th><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_CATEGORY'); ?></th>
                                    <th><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_SOURCE'); ?></th>
                                    <th><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_ACTION'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($this->importCatalogEntries ?? array()) as $entry) : ?>
                                    <?php
                                    $context = JemImportCatalogHelper::getContext($entry['type'] ?? '');
                                    $typeKey = $context === 'venues'
                                        ? 'COM_JEM_IMPORT_CATALOG_TYPE_VENUES'
                                        : ($context === 'specialdays' ? 'COM_JEM_IMPORT_CATALOG_TYPE_SPECIAL_DAYS' : 'COM_JEM_IMPORT_CATALOG_TYPE_EVENTS');
                                    ?>
                                    <tr data-country="<?php echo htmlspecialchars($entry['country'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-county="<?php echo htmlspecialchars($entry['county'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-city="<?php echo htmlspecialchars($entry['city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-type="<?php echo htmlspecialchars($context, ENT_QUOTES, 'UTF-8'); ?>" data-format="<?php echo htmlspecialchars(strtolower((string) ($entry['format'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
                                        <td><?php echo htmlspecialchars($entry['country'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($entry['county'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($entry['city'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($entry['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <?php if (!empty($entry['description'])) : ?>
                                                <span class="d-block text-muted"><?php echo htmlspecialchars($entry['description'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($entry['profile'])) : ?>
                                                <span class="d-block text-muted"><?php echo Text::sprintf('COM_JEM_IMPORT_CATALOG_PROFILE_HINT', htmlspecialchars($entry['profile'], ENT_QUOTES, 'UTF-8')); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo Text::_($typeKey); ?></td>
                                        <td><?php echo strtoupper(htmlspecialchars($entry['format'] ?? '', ENT_QUOTES, 'UTF-8')); ?></td>
                                        <td class="text-end">
                                            <?php if (($entry['item_count'] ?? null) !== null) : ?>
                                                <span<?php echo !empty($entry['item_count_checked']) ? ' title="' . htmlspecialchars(Text::sprintf('COM_JEM_IMPORT_CATALOG_ITEMS_CHECKED', $entry['item_count_checked']), ENT_QUOTES, 'UTF-8') . '"' : ''; ?>><?php echo number_format((int) $entry['item_count'], 0, '.', ','); ?></span>
                                            <?php else : ?>
                                                <span aria-label="<?php echo htmlspecialchars(Text::_('COM_JEM_IMPORT_CATALOG_ITEMS_UNKNOWN'), ENT_QUOTES, 'UTF-8'); ?>">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($entry['category_rule'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if (!empty($entry['source'])) : ?>
                                                <a href="<?php echo htmlspecialchars($entry['source'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                                                    <?php echo htmlspecialchars($entry['source'], ENT_QUOTES, 'UTF-8'); ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary" onclick="JemImportLoadCatalogItem('<?php echo htmlspecialchars($entry['id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>');">
                                                <?php echo Text::_('COM_JEM_IMPORT_CATALOG_LOAD'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="jem-import-catalog-empty"<?php echo !empty($this->importCatalogEntries) ? ' hidden' : ''; ?>>
                                    <td colspan="10"><?php echo Text::_('COM_JEM_IMPORT_CATALOG_EMPTY'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <div class="jem-import-grid">
                    <section class="jem-import-card jem-import-card-planned">
                        <h3><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_CATALOG_TITLE'); ?></h3>
                        <p><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_CATALOG_DESC'); ?></p>
                        <?php if (!empty($catalogStatus['is_custom'])) : ?>
                            <div class="alert alert-info d-flex align-items-center justify-content-between gap-2">
                                <span>
                                    <strong><?php echo Text::_('COM_JEM_IMPORT_CATALOG_CUSTOM_ACTIVE'); ?></strong>
                                    <span class="d-block"><code><?php echo htmlspecialchars($this->importCatalogSource ?? '', ENT_QUOTES, 'UTF-8'); ?></code></span>
                                </span>
                                <?php if (!empty($this->canManageImportCatalog)) : ?>
                                    <button type="button" class="btn btn-sm btn-danger" title="<?php echo htmlspecialchars(Text::_('COM_JEM_IMPORT_CATALOG_REMOVE_CUSTOM'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(Text::_('COM_JEM_IMPORT_CATALOG_REMOVE_CUSTOM'), ENT_QUOTES, 'UTF-8'); ?>" onclick="if (confirm(<?php echo htmlspecialchars(json_encode(Text::_('COM_JEM_IMPORT_CATALOG_REMOVE_CONFIRM')), ENT_QUOTES, 'UTF-8'); ?>)) { JemImportSubmit('import.removeCustomCatalog', 'download-lists'); }">
                                        <span class="icon-times" aria-hidden="true"></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php else : ?>
                            <div class="alert alert-light">
                                <strong><?php echo Text::_('COM_JEM_IMPORT_CATALOG_OFFICIAL_ACTIVE'); ?></strong>
                                <span class="d-block"><code><?php echo htmlspecialchars($this->importCatalogSource ?? '', ENT_QUOTES, 'UTF-8'); ?></code></span>
                            </div>
                        <?php endif; ?>
                        <ul class="jem-import-feature-list">
                            <li><?php echo !empty($catalogStatus['is_custom']) ? Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_CUSTOM_XML_SOURCE') : Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_XML_SOURCE'); ?></li>
                            <li><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_COUNTRY_FILTER'); ?></li>
                            <li><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_YEAR_FILTER'); ?></li>
                            <?php if ($catalogAvailable) : ?>
                                <li><?php echo Text::sprintf('COM_JEM_IMPORT_CATALOG_STATUS_DESC', htmlspecialchars((string) ($catalogStatus['version'] ?? ''), ENT_QUOTES, 'UTF-8'), htmlspecialchars((string) ($catalogStatus['published'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></li>
                            <?php endif; ?>
                        </ul>
                        <?php if (!empty($this->canManageImportCatalog)) : ?>
                            <input type="file" class="visually-hidden" id="jem-import-catalog-file" name="FileImportCatalog" accept=".xml,application/xml,text/xml" />
                            <button type="button" class="btn btn-primary" onclick="document.getElementById('jem-import-catalog-file').click();">
                                <span class="icon-upload" aria-hidden="true"></span>
                                <?php echo Text::_('COM_JEM_IMPORT_CATALOG_LOAD_XML'); ?>
                            </button>
                            <div class="form-text mt-2"><?php echo Text::_('COM_JEM_IMPORT_CATALOG_LOAD_XML_DESC'); ?></div>
                        <?php endif; ?>
                    </section>
                    <section class="jem-import-card jem-import-card-planned">
                        <h3><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_TYPES_TITLE'); ?></h3>
                        <p><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_TYPES_DESC'); ?></p>
                        <ul class="jem-import-feature-list">
                            <li><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_EVENTS'); ?></li>
                            <li><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_SPECIAL_DAYS'); ?></li>
                            <li><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_CATEGORIES_OPTION'); ?></li>
                        </ul>
                    </section>
                    <section class="jem-import-card jem-import-card-planned">
                        <h3><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_COMMUNITY_TITLE'); ?></h3>
                        <p><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_COMMUNITY_DESC'); ?></p>
                    </section>
                </div>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.addTab', 'jem-import-tabs', 'import-security', Text::_('COM_JEM_SETTINGS_SECURITY')); ?>
                <div class="jem-import-tab-intro">
                    <h2><?php echo Text::_('COM_JEM_SETTINGS_SECURITY_IMPORT'); ?></h2>
                    <p><?php echo Text::_('COM_JEM_SETTINGS_SECURITY_IMPORT_DESC'); ?></p>
                </div>
                <section class="jem-import-card">
                    <div class="mb-4">
                        <label class="form-label"><strong><?php echo Text::_('COM_JEM_SETTINGS_SECURITY_CORE_BLOCKED_TAGS'); ?></strong></label>
                        <div><code><?php echo htmlspecialchars(implode(', ', JemImportSecurityHelper::getCoreBlockedTags()), ENT_QUOTES, 'UTF-8'); ?></code></div>
                        <div class="form-text"><?php echo Text::_('COM_JEM_SETTINGS_SECURITY_CORE_BLOCKED_TAGS_DESC'); ?></div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="jem-import-security-additional-tags"><strong><?php echo Text::_('COM_JEM_SETTINGS_SECURITY_ADDITIONAL_BLOCKED_TAGS'); ?></strong></label>
                        <textarea class="form-control" id="jem-import-security-additional-tags" name="import_security[additional_blocked_tags]" rows="4"<?php echo empty($this->canConfigureImportSecurity) ? ' disabled' : ''; ?>><?php echo htmlspecialchars($this->importSecuritySettings['additional_blocked_tags'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <div class="form-text"><?php echo Text::_('COM_JEM_SETTINGS_SECURITY_ADDITIONAL_BLOCKED_TAGS_DESC'); ?></div>
                    </div>

                    <fieldset class="mb-4">
                        <legend class="form-label fs-6"><strong><?php echo Text::_('COM_JEM_SETTINGS_SECURITY_ALLOW_TRUSTED_IFRAMES'); ?></strong></legend>
                        <div class="btn-group" role="group" aria-label="<?php echo htmlspecialchars(Text::_('COM_JEM_SETTINGS_SECURITY_ALLOW_TRUSTED_IFRAMES'), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="radio" class="btn-check" name="import_security[allow_trusted_iframes]" id="jem-import-security-iframes-yes" value="1"<?php echo !empty($this->importSecuritySettings['allow_trusted_iframes']) ? ' checked' : ''; ?><?php echo empty($this->canConfigureImportSecurity) ? ' disabled' : ''; ?>>
                            <label class="btn btn-outline-success" for="jem-import-security-iframes-yes"><?php echo Text::_('JYES'); ?></label>
                            <input type="radio" class="btn-check" name="import_security[allow_trusted_iframes]" id="jem-import-security-iframes-no" value="0"<?php echo empty($this->importSecuritySettings['allow_trusted_iframes']) ? ' checked' : ''; ?><?php echo empty($this->canConfigureImportSecurity) ? ' disabled' : ''; ?>>
                            <label class="btn btn-outline-danger" for="jem-import-security-iframes-no"><?php echo Text::_('JNO'); ?></label>
                        </div>
                        <div class="form-text"><?php echo Text::_('COM_JEM_SETTINGS_SECURITY_ALLOW_TRUSTED_IFRAMES_DESC'); ?></div>
                    </fieldset>

                    <div class="mb-4" id="jem-import-security-hosts-group">
                        <label class="form-label" for="jem-import-security-hosts"><strong><?php echo Text::_('COM_JEM_SETTINGS_SECURITY_TRUSTED_IFRAME_HOSTS'); ?></strong></label>
                        <textarea class="form-control" id="jem-import-security-hosts" name="import_security[trusted_iframe_hosts]" rows="5"<?php echo empty($this->canConfigureImportSecurity) ? ' disabled' : ''; ?>><?php echo htmlspecialchars($this->importSecuritySettings['trusted_iframe_hosts'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <div class="form-text"><?php echo Text::_('COM_JEM_SETTINGS_SECURITY_TRUSTED_IFRAME_HOSTS_DESC'); ?></div>
                    </div>

                    <?php if (!empty($this->canConfigureImportSecurity)) : ?>
                        <div class="jem-import-actions">
                            <button type="button" class="btn btn-primary" onclick="JemImportSubmit('import.saveSecuritySettings', 'import-security');">
                                <span class="icon-save" aria-hidden="true"></span>
                                <?php echo Text::_('JSAVE'); ?>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="jem-import-security-restore-defaults">
                                <span class="icon-refresh" aria-hidden="true"></span>
                                <?php echo Text::_('COM_JEM_SETTINGS_SECURITY_RESTORE_DEFAULTS'); ?>
                            </button>
                        </div>
                    <?php else : ?>
                        <div class="alert alert-warning"><?php echo Text::_('COM_JEM_SETTINGS_SECURITY_ADMIN_ONLY'); ?></div>
                    <?php endif; ?>
                </section>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.endTabSet'); ?>

            <?php echo HTMLHelper::_('form.token'); ?>
            <input type="hidden" name="option" value="com_jem" />
            <input type="hidden" name="view" value="import" />
            <input type="hidden" name="controller" value="import" />
            <input type="hidden" name="task" id="task1" value="" />
            <input type="hidden" name="catalog_id" id="jem-import-catalog-id" value="" />
        </form>
    </div>
</div>

<script>
function JemImportSubmit(task, tab) {
    var form = document.getElementById('adminForm');
    var taskField = document.getElementById('task1');

    if (!form || !taskField) {
        return;
    }

    taskField.value = task;

    if (tab) {
        form.action = form.action.replace(/#.*$/, '') + '#' + tab;
    }

    if (tab === 'event-import') {
        var venueFile = document.getElementById('external-venue-import-file-upload');
        if (venueFile) {
            venueFile.value = '';
        }
    } else if (tab === 'venue-import') {
        var eventFile = document.getElementById('external-import-file-upload');
        if (eventFile) {
            eventFile.value = '';
        }
    }

    form.submit();
}

function JemImportRefreshCatalog() {
    var url = new URL(window.location.href);
    sessionStorage.setItem('jemImportResetCatalogFilters', '1');
    url.searchParams.set('catalog_refresh', Date.now().toString());
    url.hash = 'download-lists';
    window.location.assign(url.toString());
}

function JemImportToggleSecurityHosts() {
    var enabled = document.getElementById('jem-import-security-iframes-yes');
    var group = document.getElementById('jem-import-security-hosts-group');

    if (enabled && group) {
        group.hidden = !enabled.checked;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var iframeOptions = document.querySelectorAll('input[name="import_security[allow_trusted_iframes]"]');
    var restore = document.getElementById('jem-import-security-restore-defaults');

    iframeOptions.forEach(function (option) {
        option.addEventListener('change', JemImportToggleSecurityHosts);
    });

    if (restore) {
        restore.addEventListener('click', function () {
            document.getElementById('jem-import-security-additional-tags').value = '';
            document.getElementById('jem-import-security-hosts').value = '';
            document.getElementById('jem-import-security-iframes-no').checked = true;
            JemImportToggleSecurityHosts();
        });
    }

    JemImportToggleSecurityHosts();

    var catalogFile = document.getElementById('jem-import-catalog-file');
    if (catalogFile) {
        catalogFile.addEventListener('change', function () {
            if (catalogFile.files && catalogFile.files.length) {
                JemImportSubmit('import.uploadCatalog', 'download-lists');
            }
        });
    }
});

function JemImportLoadCatalogItem(id) {
    var field = document.getElementById('jem-import-catalog-id');

    if (!field) {
        return;
    }

    field.value = id || '';
    JemImportSubmit('import.loadCatalogItem', 'download-lists');
}

function JemImportShowHashTab() {
    var storageKey = 'jemImportActiveTab';
    var maxAge = 60 * 60 * 1000;
    var validTabs = ['event-import', 'venue-import', 'jem-migration', 'special-days', 'advanced-tools', 'download-lists', 'import-security'];
    var hash = window.location.hash ? window.location.hash.substring(1) : '';
    var target = validTabs.indexOf(hash) !== -1 ? hash : '';

    if (!target) {
        try {
            var stored = JSON.parse(localStorage.getItem(storageKey) || 'null');
            if (stored && validTabs.indexOf(stored.tab) !== -1 && Date.now() - Number(stored.saved || 0) <= maxAge) {
                target = stored.tab;
            } else {
                localStorage.removeItem(storageKey);
            }
        } catch (error) {
            localStorage.removeItem(storageKey);
        }
    }

    target = target || 'event-import';
    var trigger = document.querySelector('[data-bs-target="#' + target + '"], [href="#' + target + '"], [aria-controls="' + target + '"]');

    if (trigger && window.bootstrap && window.bootstrap.Tab) {
        window.bootstrap.Tab.getOrCreateInstance(trigger).show();
    } else if (trigger) {
        trigger.click();
    }

    document.querySelectorAll('[data-bs-toggle="tab"], [role="tab"]').forEach(function (tabTrigger) {
        var tab = (tabTrigger.getAttribute('data-bs-target') || tabTrigger.getAttribute('href') || '').replace(/^#/, '')
            || tabTrigger.getAttribute('aria-controls') || '';

        if (validTabs.indexOf(tab) === -1) {
            return;
        }

        tabTrigger.addEventListener('click', function () {
            localStorage.setItem(storageKey, JSON.stringify({ tab: tab, saved: Date.now() }));
            var url = new URL(window.location.href);
            url.hash = tab;
            window.history.replaceState(null, '', url.toString());
        });
    });
}

function JemImportRenderStatus(statusText) {
    var status = String(statusText || '');
    var span = document.createElement('span');
    span.className = 'jem-import-status';
    span.textContent = status;

    if (status.toLowerCase() === '<?php echo strtolower(htmlspecialchars(Text::_('COM_JEM_IMPORT_EXTERNAL_STATUS_OK'), ENT_QUOTES, 'UTF-8')); ?>') {
        span.className += ' is-ok';
    } else if (status.toLowerCase() === '<?php echo strtolower(htmlspecialchars(Text::_('COM_JEM_IMPORT_EXTERNAL_STATUS_ERROR'), ENT_QUOTES, 'UTF-8')); ?>') {
        span.className += ' is-error';
    }

    return span;
}

function JemImportUpdateDynamicPreview(select) {
    var card = select.closest('.jem-import-preview-card');
    var table = card ? card.querySelector('.jem-import-dynamic-preview') : null;

    if (!table) {
        return;
    }

    if (card.getAttribute('data-import-preview-context') === 'venues') {
        var dirtyNotice = card.querySelector('[data-venue-preview-dirty]');
        var importButton = card.querySelector('[data-import-task="import.commitExternalVenueImport"]');

        if (dirtyNotice) {
            dirtyNotice.classList.remove('d-none');
        }

        if (importButton) {
            importButton.disabled = true;
            importButton.setAttribute('aria-disabled', 'true');
        }
    }

    var sourceRecords = [];
    var previewOffset = parseInt(table.getAttribute('data-preview-offset'), 10) || 0;

    try {
        sourceRecords = JSON.parse(table.getAttribute('data-source-records') || '[]');
    } catch (error) {
        sourceRecords = [];
    }

    var selects = Array.prototype.slice.call(card.querySelectorAll('.jem-import-mapping-select'));
    var targetFields = [];

    selects.forEach(function (mappingSelect) {
        var target = mappingSelect.value || '';

        if (target && targetFields.indexOf(target) === -1) {
            targetFields.push(target);
        }
    });

    var staticRows = Array.prototype.slice.call(card.querySelectorAll('.jem-import-static-row')).map(function (row) {
        var field = row.querySelector('.jem-import-static-field');
        var value = row.querySelector('.jem-import-static-value');
        var mode = row.querySelector('.jem-import-static-mode');

        return {
            field: field ? field.value : '',
            value: value ? value.value : '',
            mode: mode ? mode.value : 'if_empty'
        };
    }).filter(function (item) {
        return item.field && item.value !== '';
    });

    staticRows.forEach(function (item) {
        if (targetFields.indexOf(item.field) === -1) {
            targetFields.push(item.field);
        }
    });

    var fixedRows = Array.prototype.slice.call(table.tBodies[0] ? table.tBodies[0].rows : []).map(function (row) {
        return {
            status: row.querySelector('[data-fixed="status"]') ? row.querySelector('[data-fixed="status"]').textContent : '',
            notes: row.querySelector('[data-fixed="notes"]') ? row.querySelector('[data-fixed="notes"]').textContent : ''
        };
    });

    var theadRow = table.tHead && table.tHead.rows[0] ? table.tHead.rows[0] : table.createTHead().insertRow();
    theadRow.innerHTML = '';
    ['#', '<?php echo htmlspecialchars(Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_STATUS'), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars(Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_NOTES'), ENT_QUOTES, 'UTF-8'); ?>'].forEach(function (label) {
        var th = document.createElement('th');
        th.textContent = label;
        if (label === '#') {
            th.className = 'center';
        }
        theadRow.appendChild(th);
    });
    targetFields.forEach(function (field) {
        var th = document.createElement('th');
        th.setAttribute('data-field', field);
        th.textContent = field;
        theadRow.appendChild(th);
    });

    var tbody = table.tBodies[0] || table.createTBody();
    tbody.innerHTML = '';

    sourceRecords.forEach(function (record, index) {
        var values = {};

        selects.forEach(function (mappingSelect) {
            var source = mappingSelect.getAttribute('data-source-field') || '';
            var target = mappingSelect.value || '';
            var value = source && Object.prototype.hasOwnProperty.call(record, source) ? String(record[source]).trim() : '';

            if (!target || !value) {
                return;
            }

            values[target] = values[target] ? values[target] + ', ' + value : value;
        });

        staticRows.forEach(function (item) {
            if (item.mode === 'always' || !values[item.field]) {
                values[item.field] = item.value;
            }
        });

        var tr = document.createElement('tr');
        var rowNumber = document.createElement('td');
        var status = document.createElement('td');
        var notes = document.createElement('td');
        rowNumber.className = 'center';
        rowNumber.setAttribute('data-fixed', 'row-number');
        rowNumber.textContent = String(previewOffset + index + 1);
        status.setAttribute('data-fixed', 'status');
        notes.setAttribute('data-fixed', 'notes');
        status.appendChild(JemImportRenderStatus(fixedRows[index] ? fixedRows[index].status : ''));
        notes.textContent = fixedRows[index] ? fixedRows[index].notes : '';
        tr.appendChild(rowNumber);
        tr.appendChild(status);
        tr.appendChild(notes);

        targetFields.forEach(function (field) {
            var td = document.createElement('td');
            td.setAttribute('data-field', field);
            td.textContent = values[field] || '';
            tr.appendChild(td);
        });

        tbody.appendChild(tr);
    });
}

function JemImportRenumberStaticRows(table) {
    var staticName = table.getAttribute('data-static-name') || '';

    Array.prototype.slice.call(table.querySelectorAll('.jem-import-static-row')).forEach(function (row, index) {
        var field = row.querySelector('.jem-import-static-field');
        var value = row.querySelector('.jem-import-static-value');
        var mode = row.querySelector('.jem-import-static-mode');

        if (field) {
            field.name = staticName + '[' + index + '][field]';
        }

        if (value) {
            value.name = staticName + '[' + index + '][value]';
        }

        if (mode) {
            mode.name = staticName + '[' + index + '][mode]';
        }
    });
}

function JemImportBindStaticRow(row) {
    row.querySelectorAll('.jem-import-static-field, .jem-import-static-value, .jem-import-static-mode').forEach(function (input) {
        input.addEventListener('change', function () {
            JemImportUpdateDynamicPreview(input);
        });
        input.addEventListener('input', function () {
            JemImportUpdateDynamicPreview(input);
        });
    });

    var remove = row.querySelector('.jem-import-static-remove');

    if (remove) {
        remove.addEventListener('click', function () {
            var table = row.closest('.jem-import-static-table');
            row.remove();

            if (table) {
                JemImportRenumberStaticRows(table);
                JemImportUpdateDynamicPreview(table);
            }
        });
    }
}

function JemImportModalSaveAndSelect(modalId, selectId, nameFieldId, saveTask) {
    var modal = document.getElementById(modalId);
    var frame = modal ? modal.querySelector('iframe') : null;

    if (!frame || !frame.contentWindow || !frame.contentWindow.document) {
        return;
    }

    var frameWindow = frame.contentWindow;
    var frameDocument = frameWindow.document;
    var nameField = frameDocument.getElementById(nameFieldId);
    var label = nameField ? nameField.value.trim() : '';

    if (!label) {
        if (frameWindow.Joomla && typeof frameWindow.Joomla.submitbutton === 'function') {
            frameWindow.Joomla.submitbutton(saveTask);
        }
        return;
    }

    sessionStorage.setItem('jemImportCreatedOption', JSON.stringify({
        selectId: selectId,
        label: label
    }));

    var reloaded = false;
    var reloadImport = function () {
        if (reloaded) {
            return;
        }

        reloaded = true;
        window.setTimeout(function () {
            window.location.reload();
        }, 250);
    };

    frame.addEventListener('load', reloadImport, { once: true });

    if (frameWindow.Joomla && typeof frameWindow.Joomla.submitbutton === 'function') {
        frameWindow.Joomla.submitbutton(saveTask);
    } else if (frameWindow.Joomla && typeof frameWindow.Joomla.submitform === 'function') {
        frameWindow.Joomla.submitform(saveTask, frameDocument.getElementById('item-form'));
    } else {
        var form = frameDocument.getElementById('item-form');
        if (form) {
            var task = form.querySelector('input[name="task"]');
            if (task) {
                task.value = saveTask;
            }
            form.submit();
        }
    }

    window.setTimeout(function () {
        reloadImport();
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function () {
    JemImportShowHashTab();

    var importProfiles = {
        events: <?php echo json_encode($buildProfilePayloads($this->externalImportProfileOptions), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>,
        venues: <?php echo json_encode($buildProfilePayloads($this->externalVenueImportProfileOptions), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>
    };
    var profileControlIds = {
        events: {
            catid: 'external_import_catid',
            mode: 'external_import_mode',
            type_id: 'external_import_type_id',
            locid: 'external_import_locid',
            published: 'external_import_published',
            publish_up: 'external_import_publish_up',
            language: 'external_import_language'
        },
        venues: {
            type_id: 'external_venue_import_type_id',
            published: 'external_venue_import_published',
            language: 'external_venue_import_language'
        }
    };

    var addProfileSummaryLine = function (container, label, values) {
        if (!values.length) {
            return;
        }

        var line = document.createElement('div');
        var strong = document.createElement('strong');
        var code = document.createElement('code');
        strong.textContent = label + ': ';
        code.textContent = values.join(', ');
        line.appendChild(strong);
        line.appendChild(code);
        container.appendChild(line);
    };

    var applyImportProfile = function (context, select, updateControls) {
        var profile = importProfiles[context] ? importProfiles[context][select.value] : null;
        var summary = document.querySelector('[data-profile-summary="' + context + '"]');
        var content = summary ? summary.querySelector('.jem-import-profile-summary-content') : null;

        if (!profile) {
            if (summary) {
                summary.hidden = true;
            }
            return;
        }

        var config = profile.config || {};
        var staticValues = Array.isArray(config.static_values) ? config.static_values : [];
        var mappingValues = Object.keys(profile.mapping || {}).map(function (source) {
            return source + ' → ' + profile.mapping[source];
        });
        var importFieldValues = [];
        var configuredImportFields = {};
        var sourceType = config.source_mode || (config.source_url ? 'url' : 'file');
        var sourceValue = config.source_url || config.source_name || <?php echo json_encode(Text::_('COM_JEM_IMPORT_PROFILE_SOURCE_FILE_REQUIRED')); ?>;

        Object.keys(profileControlIds[context] || {}).forEach(function (key) {
            if (!Object.prototype.hasOwnProperty.call(config, key) || config[key] === '' || config[key] === null) {
                return;
            }

            var control = document.getElementById(profileControlIds[context][key]);
            var label = control ? document.querySelector('label[for="' + control.id + '"]') : null;
            var value = String(config[key]);

            if (control && control.tagName === 'SELECT' && control.options[control.selectedIndex]) {
                var matchingOption = Array.prototype.find.call(control.options, function (option) {
                    return String(option.value) === value;
                });
                value = matchingOption ? matchingOption.text.trim() : value;
            }

            importFieldValues.push((label ? label.textContent.trim() : key) + '=' + value);
            configuredImportFields[key] = true;
        });
        staticValues.forEach(function (item) {
            if (item && item.field && !configuredImportFields[item.field]) {
                importFieldValues.push(item.field + '=' + (item.value === undefined ? '' : item.value));
            }
        });

        if (content) {
            content.textContent = '';
            addProfileSummaryLine(content, <?php echo json_encode(Text::_('COM_JEM_IMPORT_PROFILE_SOURCE_TYPE')); ?>, [sourceType === 'url' ? <?php echo json_encode(Text::_('COM_JEM_IMPORT_SOURCE_URL')); ?> : <?php echo json_encode(Text::_('COM_JEM_IMPORT_SOURCE_FILE')); ?>]);
            addProfileSummaryLine(content, <?php echo json_encode(Text::_('COM_JEM_IMPORT_PROFILE_SOURCE')); ?>, [sourceValue]);
            addProfileSummaryLine(content, <?php echo json_encode(Text::_('COM_JEM_IMPORT_PROFILE_FORMAT')); ?>.replace(': %s', ''), profile.format ? [profile.format] : []);
            addProfileSummaryLine(content, <?php echo json_encode(Text::_('COM_JEM_IMPORT_PROFILE_IMPORT_FIELDS')); ?>, importFieldValues);
            addProfileSummaryLine(content, <?php echo json_encode(Text::_('COM_JEM_IMPORT_PROFILE_MAPPING_SUMMARY')); ?>.replace(': %s', ''), mappingValues);
            summary.hidden = false;
        }

        if (!updateControls) {
            return;
        }

        Object.keys(profileControlIds[context] || {}).forEach(function (key) {
            if (!Object.prototype.hasOwnProperty.call(config, key)) {
                return;
            }

            var control = document.getElementById(profileControlIds[context][key]);
            if (control) {
                control.value = String(config[key]);
                control.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        var prefix = context === 'venues' ? 'external_venue_import' : 'external_import';
        if (config.source_mode) {
            var sourceRadio = document.querySelector('input[name="' + prefix + '_source_mode"][value="' + config.source_mode + '"]');
            if (sourceRadio) {
                sourceRadio.checked = true;
                sourceRadio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
        if (config.source_url) {
            var sourceInput = document.querySelector('input[name="' + prefix + '_source_url"]');
            if (sourceInput) {
                sourceInput.value = config.source_url;
            }
        }
    };

    [
        ['events', document.getElementById('external_import_profile_id')],
        ['venues', document.getElementById('external_venue_import_profile_id')]
    ].forEach(function (item) {
        if (!item[1]) {
            return;
        }

        item[1].addEventListener('change', function () {
            applyImportProfile(item[0], item[1], true);
        });
        applyImportProfile(item[0], item[1], true);
    });

    var createdOption = sessionStorage.getItem('jemImportCreatedOption');

    if (createdOption) {
        sessionStorage.removeItem('jemImportCreatedOption');

        try {
            createdOption = JSON.parse(createdOption);
            var select = document.getElementById(createdOption.selectId);
            var label = (createdOption.label || '').trim().toLowerCase();

            if (select && label) {
                Array.prototype.some.call(select.options, function (option) {
                    var text = option.text.replace(/^[-\s]+/, '').trim().toLowerCase();

                    if (text === label) {
                        select.value = option.value;
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                        JemImportLogCreatedOption(createdOption.selectId, createdOption.label, option.value);
                        return true;
                    }

                    return false;
                });
            }
        } catch (error) {
        }
    }

    document.querySelectorAll('.jem-import-paged-table').forEach(function (table) {
        var tbody = table.tBodies[0];

        if (!tbody) {
            return;
        }

        var rows = Array.prototype.slice.call(tbody.rows);
        var serverPaginated = table.getAttribute('data-server-paginated') === '1';
        var pageSize = parseInt(table.getAttribute('data-page-size'), 10) || 50;

        if (!rows.length || serverPaginated) {
            return;
        }

        var page = 0;
        var pages = Math.ceil(rows.length / pageSize);
        var nav = document.createElement('div');
        var prev = document.createElement('button');
        var next = document.createElement('button');
        var status = document.createElement('span');

        nav.className = 'jem-import-pagination d-flex align-items-center gap-2 mt-2';
        prev.type = 'button';
        prev.className = 'btn btn-secondary btn-sm';
        prev.textContent = <?php echo json_encode(Text::_('JPREV')); ?>;
        next.type = 'button';
        next.className = 'btn btn-secondary btn-sm';
        next.textContent = <?php echo json_encode(Text::_('JNEXT')); ?>;

        function renderPage() {
            var start = page * pageSize;
            var end = Math.min(start + pageSize, rows.length);

            rows.forEach(function (row, index) {
                row.hidden = index < start || index >= end;
            });

            prev.disabled = page === 0;
            next.disabled = page >= pages - 1;
            status.textContent = (start + 1) + '-' + end + ' / ' + rows.length;
        }

        prev.addEventListener('click', function () {
            if (page > 0) {
                page--;
                renderPage();
            }
        });

        next.addEventListener('click', function () {
            if (page < pages - 1) {
                page++;
                renderPage();
            }
        });

        nav.appendChild(prev);
        nav.appendChild(status);
        nav.appendChild(next);
        table.parentNode.insertAdjacentElement('afterend', nav);
        renderPage();
    });

    document.querySelectorAll('.jem-import-mapping-select').forEach(function (select) {
        select.addEventListener('change', function () {
            JemImportUpdateDynamicPreview(select);
        });
    });

    document.querySelectorAll('.jem-import-static-row').forEach(function (row) {
        JemImportBindStaticRow(row);
    });

    document.querySelectorAll('.jem-import-static-add').forEach(function (button) {
        button.addEventListener('click', function () {
            var panel = button.closest('.jem-import-static-panel');
            var table = panel ? panel.querySelector('.jem-import-static-table') : null;
            var tbody = table && table.tBodies[0] ? table.tBodies[0] : null;
            var firstRow = tbody && tbody.querySelector('.jem-import-static-row') ? tbody.querySelector('.jem-import-static-row') : null;

            if (!table || !tbody || !firstRow) {
                return;
            }

            var row = firstRow.cloneNode(true);

            row.querySelectorAll('select').forEach(function (select) {
                select.selectedIndex = 0;
            });
            row.querySelectorAll('input').forEach(function (input) {
                input.value = '';
            });

            tbody.appendChild(row);
            JemImportRenumberStaticRows(table);
            JemImportBindStaticRow(row);
            JemImportUpdateDynamicPreview(table);
        });
    });

    document.querySelectorAll('[data-source-choice]').forEach(function (choice) {
        var key = choice.getAttribute('data-source-choice');
        var urlPanel = document.querySelector('[data-source-panel="' + key + '-url"]');
        var filePanel = document.querySelector('[data-source-panel="' + key + '-file"]');
        var fileInput = filePanel ? filePanel.querySelector('input[type="file"]') : null;

        var updateSourcePanels = function () {
            var selected = choice.querySelector('input[type="radio"]:checked');
            var useUrl = selected && selected.value === 'url';

            if (urlPanel) {
                urlPanel.hidden = !useUrl;
            }

            if (filePanel) {
                filePanel.hidden = useUrl;
            }

            if (fileInput && useUrl) {
                fileInput.value = '';
            }
        };

        choice.querySelectorAll('input[type="radio"]').forEach(function (radio) {
            radio.addEventListener('change', updateSourcePanels);
        });

        updateSourcePanels();
    });

    var filter = document.getElementById('jem-import-catalog-country');
    var countyFilter = document.getElementById('jem-import-catalog-county');
    var cityFilter = document.getElementById('jem-import-catalog-city');
    var typeFilter = document.getElementById('jem-import-catalog-type');
    var formatFilter = document.getElementById('jem-import-catalog-format');
    var table = document.getElementById('jem-import-catalog-table');

    if (!filter || !table) {
        return;
    }

    var filterCatalog = function () {
        var selected = filter.value;
        var county = countyFilter ? countyFilter.value : '';
        var city = cityFilter ? cityFilter.value : '';
        var type = typeFilter ? typeFilter.value : '';
        var format = formatFilter ? formatFilter.value : '';
        var visible = 0;
        var rows = table.querySelectorAll('tbody tr[data-country]');

        rows.forEach(function (row) {
            var show = (!selected || row.getAttribute('data-country') === selected)
                && (!county || row.getAttribute('data-county') === county)
                && (!city || row.getAttribute('data-city') === city)
                && (!type || row.getAttribute('data-type') === type)
                && (!format || row.getAttribute('data-format') === format);
            row.hidden = !show;
            if (show) {
                visible++;
            }
        });

        var empty = table.querySelector('.jem-import-catalog-empty');
        if (empty) {
            empty.hidden = visible > 0;
        }
    };

    if (sessionStorage.getItem('jemImportResetCatalogFilters') === '1') {
        sessionStorage.removeItem('jemImportResetCatalogFilters');
        [filter, countyFilter, cityFilter, typeFilter, formatFilter].forEach(function (catalogFilter) {
            if (catalogFilter) {
                catalogFilter.value = '';
            }
        });
    }

    filterCatalog();

    filter.addEventListener('change', filterCatalog);

    if (countyFilter) {
        countyFilter.addEventListener('change', filterCatalog);
    }

    if (cityFilter) {
        cityFilter.addEventListener('change', filterCatalog);
    }

    if (typeFilter) {
        typeFilter.addEventListener('change', filterCatalog);
    }

    if (formatFilter) {
        formatFilter.addEventListener('change', filterCatalog);
    }
});

function JemImportLogCreatedOption(selectId, label, value) {
    var source = selectId.indexOf('_ics_') !== -1 ? 'ics' : 'csv';
    var object = 'option';

    if (selectId.indexOf('catid') !== -1) {
        object = 'category';
    } else if (selectId.indexOf('type_id') !== -1) {
        object = 'type';
    } else if (selectId.indexOf('locid') !== -1) {
        object = 'venue';
    }

    var params = new URLSearchParams({
        option: 'com_jem',
        task: 'import.logCreatedImportOption',
        source: source,
        object: object,
        select: selectId,
        label: label || '',
        value: value || '0',
        '<?php echo Session::getFormToken(); ?>': '1'
    });

    fetch('index.php?' + params.toString(), {
        credentials: 'same-origin'
    }).then(function () {
        window.location.reload();
    }).catch(function () {
    });
}
</script>
