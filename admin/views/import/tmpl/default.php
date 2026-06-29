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

$renderCreateModalButton = function ($id, $task, $buttonText, $titleText, $targetSelectId, $nameFieldId, $saveTask) {
    $modalId = 'jem-import-create-' . preg_replace('/[^a-z0-9_-]/i', '-', $id);
    $url = Route::_('index.php?option=com_jem&task=' . $task . '&tmpl=component', false);
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
        <button type="button" class="btn btn-primary"<?php echo empty($validCount) ? ' disabled' : ''; ?> onclick="JemImportSubmit('<?php echo $commitTask; ?>', '<?php echo $tabId; ?>');">
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
    $profileId = (int) ($preview['profile_id'] ?? 0);
    $profileTitle = (string) ($preview['profile_title'] ?? '');

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
    <details class="jem-import-columns jem-import-mapping-panel" open data-profile-id="<?php echo $profileId; ?>" data-original-mapping="<?php echo htmlspecialchars(json_encode($mapping), ENT_QUOTES, 'UTF-8'); ?>">
        <summary><?php echo Text::_('COM_JEM_IMPORT_MAPPING_TITLE'); ?></summary>
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
    </details>
    <?php
};

$renderImportStatus = function ($status) {
    $statusText = (string) $status;
    $statusOk = Text::_('COM_JEM_IMPORT_EXTERNAL_STATUS_OK');
    $statusError = Text::_('COM_JEM_IMPORT_EXTERNAL_STATUS_ERROR');
    $statusClass = strcasecmp($statusText, $statusOk) === 0 ? 'is-ok' : (strcasecmp($statusText, $statusError) === 0 ? 'is-error' : '');

    return '<span class="jem-import-status ' . $statusClass . '">' . htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') . '</span>';
};

$renderDynamicPreviewTable = function (array $preview, array $fallbackFields = array()) use ($renderImportStatus) {
    $recordFields = array_values(array_filter((array) ($preview['record_fields'] ?? $fallbackFields), 'strlen'));
    $rows = (array) ($preview['rows'] ?? array());
    ?>
    <div class="jem-import-data-heading">
        <hr>
        <h4><?php echo Text::_('COM_JEM_IMPORT_MAPPED_DATA_TITLE'); ?></h4>
    </div>
    <div class="table-responsive jem-import-preview-table-wrap">
        <table class="adminlist table jem-import-paged-table jem-import-dynamic-preview" data-page-size="50" data-source-records="<?php echo htmlspecialchars(json_encode($preview['source_records'] ?? array()), ENT_QUOTES, 'UTF-8'); ?>">
            <thead>
                <tr>
                    <th><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_STATUS'); ?></th>
                    <th><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_NOTES'); ?></th>
                    <?php foreach ($recordFields as $field) : ?>
                        <th data-field="<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <?php $importData = (array) ($row['import_data'] ?? array()); ?>
                    <tr>
                        <td data-fixed="status"><?php echo $renderImportStatus($row['status'] ?? ''); ?></td>
                        <td data-fixed="notes"><?php echo htmlspecialchars(implode('; ', (array) ($row['notes'] ?? array())), ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php foreach ($recordFields as $field) : ?>
                            <td data-field="<?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($importData[$field] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
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
    'state',
    'country',
    'latitude',
    'longitude',
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
$specialDaysMappingFields = array();
foreach (array(
    'id',
    'title',
    'alias',
    'day_type',
    'start_date',
    'end_date',
    'weekdays',
    'country',
    'region',
    'city',
    'description',
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
                <div class="jem-import-tab-intro">
                    <h2><?php echo Text::_('COM_JEM_IMPORT_EVENT_IMPORT_TITLE'); ?></h2>
                    <p><?php echo Text::_('COM_JEM_IMPORT_EVENT_IMPORT_DESC'); ?></p>
                </div>
                <div class="jem-import-grid">
                    <section class="jem-import-card">
                        <div class="jem-import-card-header">
                            <div>
                                <h3><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_EVENTS_TITLE'); ?></h3>
                                <p><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_EVENTS_DESC'); ?></p>
                            </div>
                        </div>
                        <div class="jem-import-row">
                            <div class="jem-import-field jem-import-field-file">
                                <label for="external-import-file-upload"><?php echo Text::_('COM_JEM_IMPORT_SELECT_CSV_JSON_XML_OR_ICS'); ?></label>
                                <input type="file" id="external-import-file-upload" accept=".csv,.json,.xml,.ics,text/csv,application/json,text/xml,application/xml,text/calendar,text/plain" name="FileExternalImport" class="form-control" />
                                <label for="external_import_profile_id" class="mt-2"><?php echo Text::_('COM_JEM_IMPORT_PROFILE_LABEL'); ?></label>
                                <?php echo HTMLHelper::_('select.genericlist', $this->externalImportProfileOptions, 'external_import_profile_id', 'class="form-select" id="external_import_profile_id"', 'value', 'text'); ?>
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
                                    <option value="standard"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_STANDARD_EVENTS'); ?></option>
                                    <option value="openday"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_OPEN_DAY_EVENTS'); ?></option>
                                </select>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                        </div>
                        <div class="jem-import-row">
                            <div class="jem-import-field">
                                <label for="external_import_type_id"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_DEFAULT_TYPE'); ?></label>
                                <div class="jem-import-select-actions">
                                    <?php echo $renderFancySelect($this->externalTypeOptions, 'external_import_type_id', 'external_import_type_id', $this->externalImportPreview['type_id'] ?? 0); ?>
                                    <?php $renderCreateModalButton('external-type', 'type.add', 'COM_JEM_IMPORT_CREATE_TYPE', 'COM_JEM_IMPORT_CREATE_TYPE_TITLE', 'external_import_type_id', 'jform_name', 'type.save'); ?>
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
                                    <option value="1"><?php echo Text::_('JPUBLISHED'); ?></option>
                                    <option value="0"><?php echo Text::_('JUNPUBLISHED'); ?></option>
                                </select>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                            <div class="jem-import-field">
                                <label for="external_import_publish_up"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PUBLISH_UP'); ?></label>
                                <?php echo HTMLHelper::_('calendar', $this->externalPublishUpDefault, 'external_import_publish_up', 'external_import_publish_up', '%Y-%m-%d %H:%M:%S', array('class' => 'form-control', 'showTime' => true, 'timeFormat' => '24')); ?>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                            <div class="jem-import-field">
                                <label for="external_import_language"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_LANGUAGE'); ?></label>
                                <?php echo HTMLHelper::_('select.genericlist', $this->externalLanguageOptions, 'external_import_language', 'class="form-select" id="external_import_language"', 'value', 'text', '*'); ?>
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
                                    <?php foreach (($this->externalIcsPreview['rows'] ?? array()) as $row) : ?>
                                        <tr>
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
                <div class="jem-import-tab-intro">
                    <h2><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_TITLE'); ?></h2>
                    <p><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_DESC'); ?></p>
                </div>
                <div class="jem-import-grid">
                    <section class="jem-import-card">
                        <div class="jem-import-card-header">
                            <div>
                                <h3><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_TITLE'); ?></h3>
                                <p><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_DESC'); ?></p>
                            </div>
                        </div>
                        <div class="jem-import-row">
                            <div class="jem-import-field jem-import-field-file">
                                <label for="external-venue-import-file-upload"><?php echo Text::_('COM_JEM_IMPORT_SELECT_CSV_JSON_OR_XML'); ?></label>
                                <input type="file" id="external-venue-import-file-upload" accept=".csv,.json,.xml,text/csv,application/json,text/xml,application/xml,text/plain" name="FileExternalVenueImport" class="form-control" />
                                <label for="external_venue_import_profile_id" class="mt-2"><?php echo Text::_('COM_JEM_IMPORT_PROFILE_LABEL'); ?></label>
                                <?php echo HTMLHelper::_('select.genericlist', $this->externalVenueImportProfileOptions, 'external_venue_import_profile_id', 'class="form-select" id="external_venue_import_profile_id"', 'value', 'text'); ?>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                            <div class="jem-import-field">
                                <label for="external_venue_import_type_id"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_DEFAULT_TYPE'); ?></label>
                                <div class="jem-import-select-actions">
                                    <?php echo $renderFancySelect($this->externalTypeOptions, 'external_venue_import_type_id', 'external_venue_import_type_id', $this->externalVenueImportPreview['type_id'] ?? 0); ?>
                                    <?php $renderCreateModalButton('external-venue-type', 'type.add', 'COM_JEM_IMPORT_CREATE_TYPE', 'COM_JEM_IMPORT_CREATE_TYPE_TITLE', 'external_venue_import_type_id', 'jform_name', 'type.save'); ?>
                                </div>
                            </div>
                            <div class="jem-import-field">
                                <label for="external_venue_import_published"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PUBLISHED_STATE'); ?></label>
                                <select name="external_venue_import_published" id="external_venue_import_published" class="form-select">
                                    <option value="1"><?php echo Text::_('JPUBLISHED'); ?></option>
                                    <option value="0"><?php echo Text::_('JUNPUBLISHED'); ?></option>
                                </select>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                            <div class="jem-import-field">
                                <label for="external_venue_import_language"><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_LANGUAGE'); ?></label>
                                <?php echo HTMLHelper::_('select.genericlist', $this->externalLanguageOptions, 'external_venue_import_language', 'class="form-select" id="external_venue_import_language"', 'value', 'text', '*'); ?>
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
                    <section class="jem-import-card jem-import-preview-card">
                        <h3><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_VENUES_PREVIEW_TITLE'); ?></h3>
                        <p><?php echo htmlspecialchars($this->externalVenueImportPreview['summary'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p><?php echo Text::sprintf('COM_JEM_IMPORT_DETECTED_FORMAT', strtoupper($this->externalVenueImportPreview['format'] ?? 'csv')); ?></p>
                        <?php if (!empty($this->externalVenueImportPreview['profile_title'])) : ?>
                            <p><?php echo Text::sprintf('COM_JEM_IMPORT_PROFILE_APPLIED', htmlspecialchars($this->externalVenueImportPreview['profile_title'], ENT_QUOTES, 'UTF-8')); ?></p>
                        <?php endif; ?>
                        <?php $renderImportMappingBlock((array) $this->externalVenueImportPreview, 'external_venue_import_mapping', $venueMappingFields, 'external_venue_import_profile'); ?>
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
                <div class="jem-import-tab-intro">
                    <h2><?php echo Text::_('COM_JEM_SPECIAL_DAYS'); ?></h2>
                    <p><?php echo Text::_('COM_JEM_IMPORT_SPECIAL_DAYS_DESC'); ?></p>
                </div>
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
                                <label for="specialdays-import-file-upload"><?php echo Text::_('COM_JEM_IMPORT_SELECT_CSV_OR_ICS'); ?></label>
                                <input type="file" id="specialdays-import-file-upload" accept=".csv,.ics,text/csv,text/calendar,text/plain" name="FileSpecialDaysImport" class="form-control" />
                                <label for="specialdays_import_profile_id" class="mt-2"><?php echo Text::_('COM_JEM_IMPORT_PROFILE_LABEL'); ?></label>
                                <?php echo HTMLHelper::_('select.genericlist', $this->specialDaysImportProfileOptions, 'specialdays_import_profile_id', 'class="form-select" id="specialdays_import_profile_id"', 'value', 'text'); ?>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                            <div class="jem-import-field">
                                <label for="specialdays_import_day_type">
                                    <?php echo Text::_('COM_JEM_SPECIAL_DAY_FIELD_TYPE'); ?>
                                    <span><?php echo Text::_('COM_JEM_IMPORT_SPECIAL_DAYS_TYPE_FALLBACK_DESC'); ?></span>
                                </label>
                                <?php echo HTMLHelper::_('select.genericlist', $this->specialDayTypeOptions, 'specialdays_import_day_type', 'class="form-select" id="specialdays_import_day_type"', 'value', 'text'); ?>
                                <span class="jem-import-field-spacer" aria-hidden="true"></span>
                            </div>
                            <div class="jem-import-field jem-import-field-replace">
                                <label for="replace_specialdays_import">
                                    <?php echo Text::_('COM_JEM_IMPORT_REPLACEIFEXISTS_LABEL'); ?>
                                    <span><?php echo Text::_('COM_JEM_IMPORT_REPLACEIFEXISTS_HELP'); ?></span>
                                </label>
                                <select name="replace_specialdays_import" id="replace_specialdays_import" class="form-select">
                                    <option value="0"><?php echo Text::_('JNO'); ?></option>
                                    <option value="1"><?php echo Text::_('JYES'); ?></option>
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
                            <?php if (($specialDaysPreview['format'] ?? 'csv') === 'csv' && !empty($specialDaysPreview['source_fields'])) : ?>
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
                                                <th><?php echo Text::_('COM_JEM_TITLE'); ?></th>
                                                <th><?php echo Text::_('COM_JEM_DATE'); ?></th>
                                                <th><?php echo Text::_('COM_JEM_SPECIAL_DAY_FIELD_TYPE'); ?></th>
                                                <th><?php echo Text::_('JGLOBAL_DESCRIPTION'); ?></th>
                                                <th><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_NOTES'); ?></th>
                                                <th><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_STATUS'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (($specialDaysPreview['rows'] ?? array()) as $row) : ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($row['date_label'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($row['day_type'] ?? ($specialDaysPreview['day_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
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
                    <div class="jem-import-row jem-import-catalog-controls">
                        <div class="jem-import-field">
                            <label for="jem-import-catalog-country"><?php echo Text::_('COM_JEM_IMPORT_CATALOG_COUNTRY'); ?></label>
                            <select id="jem-import-catalog-country" class="form-select">
                                <option value=""><?php echo Text::_('JALL'); ?></option>
                                <option value="ES"><?php echo Text::_('COM_JEM_IMPORT_CATALOG_COUNTRY_ES'); ?></option>
                                <option value="DE"><?php echo Text::_('COM_JEM_IMPORT_CATALOG_COUNTRY_DE'); ?></option>
                            </select>
                        </div>
                        <div class="jem-import-field">
                            <label><?php echo Text::_('COM_JEM_IMPORT_CATALOG_SOURCE'); ?></label>
                            <code><?php echo Text::_('COM_JEM_IMPORT_CATALOG_SOURCE_EXAMPLE'); ?></code>
                        </div>
                        <div class="jem-import-actions">
                            <button type="button" class="btn btn-secondary" disabled>
                                <?php echo Text::_('COM_JEM_IMPORT_CATALOG_REFRESH'); ?>
                            </button>
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
                                    <th><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_LIST'); ?></th>
                                    <th><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_TYPE'); ?></th>
                                    <th><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_FORMAT'); ?></th>
                                    <th><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_CATEGORY'); ?></th>
                                    <th><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_SOURCE'); ?></th>
                                    <th><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TABLE_ACTION'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr data-country="ES">
                                    <td>ES</td>
                                    <td><?php echo Text::_('COM_JEM_IMPORT_CATALOG_SAMPLE_ES_LABOUR'); ?></td>
                                    <td><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TYPE_SPECIAL_DAYS'); ?></td>
                                    <td>CSV</td>
                                    <td><?php echo Text::_('COM_JEM_IMPORT_CATALOG_CATEGORY_SPECIAL_DAYS'); ?></td>
                                    <td><code>imports/ES/specialdays/labour-calendar-2026.csv</code></td>
                                    <td><button type="button" class="btn btn-sm btn-secondary" disabled><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_NEXT_PHASE'); ?></button></td>
                                </tr>
                                <tr data-country="ES">
                                    <td>ES</td>
                                    <td><?php echo Text::_('COM_JEM_IMPORT_CATALOG_SAMPLE_ES_SCHOOL'); ?></td>
                                    <td><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TYPE_EVENTS'); ?></td>
                                    <td>CSV</td>
                                    <td><?php echo Text::_('COM_JEM_IMPORT_CATALOG_CATEGORY_SELECTED_SUBCATEGORIES'); ?></td>
                                    <td><code>imports/ES/events/madrid-school-year-2026.csv</code></td>
                                    <td><button type="button" class="btn btn-sm btn-secondary" disabled><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_NEXT_PHASE'); ?></button></td>
                                </tr>
                                <tr data-country="DE">
                                    <td>DE</td>
                                    <td><?php echo Text::_('COM_JEM_IMPORT_CATALOG_SAMPLE_DE_BAVARIA'); ?></td>
                                    <td><?php echo Text::_('COM_JEM_IMPORT_CATALOG_TYPE_EVENTS'); ?></td>
                                    <td>ICS</td>
                                    <td><?php echo Text::_('COM_JEM_IMPORT_CATALOG_CATEGORY_SELECTED'); ?></td>
                                    <td><code>imports/DE/events/bavaria-school-holidays-2026.ics</code></td>
                                    <td><button type="button" class="btn btn-sm btn-secondary" disabled><?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_NEXT_PHASE'); ?></button></td>
                                </tr>
                                <tr class="jem-import-catalog-empty" hidden>
                                    <td colspan="7"><?php echo Text::_('COM_JEM_IMPORT_CATALOG_EMPTY'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <div class="jem-import-grid">
                    <section class="jem-import-card jem-import-card-planned">
                        <h3><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_CATALOG_TITLE'); ?></h3>
                        <p><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_CATALOG_DESC'); ?></p>
                        <ul class="jem-import-feature-list">
                            <li><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_XML_SOURCE'); ?></li>
                            <li><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_COUNTRY_FILTER'); ?></li>
                            <li><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_YEAR_FILTER'); ?></li>
                            <li><?php echo Text::sprintf('COM_JEM_IMPORT_CATALOG_STATUS_DESC', '1.0', '2026-06-28'); ?></li>
                        </ul>
                        <button type="button" class="btn btn-secondary" disabled>
                            <?php echo Text::_('COM_JEM_IMPORT_EXTERNAL_NEXT_PHASE'); ?>
                        </button>
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
                    <section class="jem-import-card jem-import-card-planned">
                        <h3><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_EXPORT_TITLE'); ?></h3>
                        <p><?php echo Text::_('COM_JEM_IMPORT_DOWNLOAD_LISTS_EXPORT_DESC'); ?></p>
                    </section>
                </div>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <?php echo HTMLHelper::_('uitab.endTabSet'); ?>

            <?php echo HTMLHelper::_('form.token'); ?>
            <input type="hidden" name="option" value="com_jem" />
            <input type="hidden" name="view" value="import" />
            <input type="hidden" name="controller" value="import" />
            <input type="hidden" name="task" id="task1" value="" />
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

function JemImportShowHashTab() {
    var hash = window.location.hash ? window.location.hash.substring(1) : '';

    if (!hash) {
        return;
    }

    var trigger = document.querySelector('[data-bs-target="#' + hash + '"], [href="#' + hash + '"], [aria-controls="' + hash + '"]');

    if (trigger && window.bootstrap && window.bootstrap.Tab) {
        window.bootstrap.Tab.getOrCreateInstance(trigger).show();
    } else if (trigger) {
        trigger.click();
    }
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

    var sourceRecords = [];

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

    var fixedRows = Array.prototype.slice.call(table.tBodies[0] ? table.tBodies[0].rows : []).map(function (row) {
        return {
            status: row.querySelector('[data-fixed="status"]') ? row.querySelector('[data-fixed="status"]').textContent : '',
            notes: row.querySelector('[data-fixed="notes"]') ? row.querySelector('[data-fixed="notes"]').textContent : ''
        };
    });

    var theadRow = table.tHead && table.tHead.rows[0] ? table.tHead.rows[0] : table.createTHead().insertRow();
    theadRow.innerHTML = '';
    ['<?php echo htmlspecialchars(Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_STATUS'), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars(Text::_('COM_JEM_IMPORT_EXTERNAL_PREVIEW_NOTES'), ENT_QUOTES, 'UTF-8'); ?>'].forEach(function (label) {
        var th = document.createElement('th');
        th.textContent = label;
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

        var tr = document.createElement('tr');
        var status = document.createElement('td');
        var notes = document.createElement('td');
        status.setAttribute('data-fixed', 'status');
        notes.setAttribute('data-fixed', 'notes');
        status.appendChild(JemImportRenderStatus(fixedRows[index] ? fixedRows[index].status : ''));
        notes.textContent = fixedRows[index] ? fixedRows[index].notes : '';
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
        var pageSize = parseInt(table.getAttribute('data-page-size'), 10) || 50;

        if (!rows.length) {
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

    var filter = document.getElementById('jem-import-catalog-country');
    var table = document.getElementById('jem-import-catalog-table');

    if (!filter || !table) {
        return;
    }

    filter.addEventListener('change', function () {
        var selected = filter.value;
        var visible = 0;
        var rows = table.querySelectorAll('tbody tr[data-country]');

        rows.forEach(function (row) {
            var show = !selected || row.getAttribute('data-country') === selected;
            row.hidden = !show;
            if (show) {
                visible++;
            }
        });

        var empty = table.querySelector('.jem-import-catalog-empty');
        if (empty) {
            empty.hidden = visible > 0;
        }
    });
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
