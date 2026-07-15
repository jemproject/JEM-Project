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

// JEMHelper::headerDeclarations();
$truncateExportPreview = function ($value) {
    $value = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

    return mb_strlen($value, 'UTF-8') > 30 ? mb_substr($value, 0, 30, 'UTF-8') . '…' : $value;
};
?>
<script>
    function selectAll()
    {
        selectBox = document.getElementById("cid");

        for (var i = 0; i < selectBox.options.length; i++){
            selectBox.options[i].selected = true;
        }
    }

    function unselectAll()
    {
        selectBox = document.getElementById("cid");

        for (var i = 0; i < selectBox.options.length; i++){
            selectBox.options[i].selected = false;
        }
    }

    function jemExportTimestamp()
    {
        var now = new Date();
        var pad = function(value) {
            return String(value).padStart(2, '0');
        };

        return now.getFullYear()
            + pad(now.getMonth() + 1)
            + pad(now.getDate())
            + '-'
            + pad(now.getHours())
            + pad(now.getMinutes())
            + pad(now.getSeconds());
    }

    function jemSubmitExport(task, label, filename)
    {
        if (filename.indexOf('{timestamp}') !== -1) {
            filename = filename.replace('{timestamp}', jemExportTimestamp());
        }

        document.getElementsByName('task')[0].value = task;
        document.getElementsByName('export_filename')[0].value = filename;

        var message = <?php echo json_encode(Text::_('COM_JEM_EXPORT_DOWNLOAD_STARTED')); ?>;
        message = message.replace('%1$s', label).replace('%2$s', filename);

        if (window.Joomla && Joomla.renderMessages) {
            Joomla.renderMessages({'message': [message]});
        }

        return true;
    }

    function jemSubmitExportTask(task)
    {
        document.getElementsByName('task')[0].value = task;
        return true;
    }
</script>

<div id="jem" class="jem_jem">
    <form action="index.php" method="post" name="adminForm" enctype="multipart/form-data" id="adminForm">
        <?php if (isset($this->sidebar)) : ?>
            <!-- <div id="j-sidebar-container" class="span2">
            <?php //echo $this->sidebar; ?>
        </div> -->
        <?php endif; ?>
        <div id="j-main-container" class="j-main-container">
            <div class="row">
                <div class="col-md-9">
                    <fieldset class="options-form">
                        <legend><?php echo Text::_('COM_JEM_EXPORT_EVENTS_LEGEND');?></legend>
                        <div class="width-50 fltlft" style="padding: 0 1vw;">
                            <ul class="adminformlist">
                                <li>
                                    <label class="top" <?php echo JEMOutput::tooltip(Text::_('COM_JEM_EXPORT_ADD_CATEGORYCOLUMN'), Text::_('COM_JEM_EXPORT_ADD_CATEGORYCOLUMN'), 'editlinktip'); ?>>
                                        <?php echo Text::_('COM_JEM_EXPORT_ADD_CATEGORYCOLUMN'); ?></label>
                                    <?php
                                    $categorycolumn = array();
                                    $categorycolumn[] = HTMLHelper::_('select.option', '0', Text::_('JNO'));
                                    $categorycolumn[] = HTMLHelper::_('select.option', '1', Text::_('JYES'));
                                    $categorycolumn = HTMLHelper::_('select.genericlist', $categorycolumn, 'categorycolumn', array('size'=>'1','class'=>'inputbox form-select'), 'value', 'text', '1');
                                    echo $categorycolumn;?>
                                </li>
                                <li>
                                    <label for="dates"><?php echo Text::_('COM_JEM_EXPORT_CATALOG_START_DATE').':'; ?></label>
                                    <?php echo HTMLHelper::_('calendar', $this->catalogFilters['dates'], 'dates', 'dates', '%Y-%m-%d', array('class' => 'inputbox validate-date', 'showTime' => false)); ?>
                                </li>
                                <li>
                                    <label for="enddates"><?php echo Text::_('COM_JEM_EXPORT_CATALOG_END_DATE').':'; ?></label>
                                    <?php echo HTMLHelper::_('calendar', $this->catalogFilters['enddates'], 'enddates', 'enddates', '%Y-%m-%d', array('class' => 'inputbox validate-date', 'showTime' => false)); ?>
                                </li>
                            </ul>
                        </div>
                        <div class="width-50 fltrt" style="padding: 0 1vw;">
                            <div>
                                <label for="cid"><?php echo Text::_('COM_JEM_CATEGORY').':'; ?></label>
                                <?php echo $this->categories; ?>
                                <label for="catalog_venue_ids" class="mt-3"><?php echo Text::_('COM_JEM_EXPORT_CATALOG_VENUES'); ?>:</label>
                                <?php echo $this->catalogVenues; ?>
                                <label for="catalog_type_ids" class="mt-3"><?php echo Text::_('COM_JEM_EXPORT_CATALOG_TYPES'); ?>:</label>
                                <?php echo $this->catalogTypes; ?>
                                <div style="clear: both"></div>
                                <input class="btn btn-success csvexport" type="submit" value="<?php echo Text::_('COM_JEM_EXPORT_FILE'); ?>" onclick="return jemSubmitExport('export.export', <?php echo htmlspecialchars(json_encode(Text::_('COM_JEM_EXPORT_EVENTS_LEGEND')), ENT_QUOTES, 'UTF-8'); ?>, 'exportEvents-{timestamp}.csv');">
                            </div>
                    </fieldset>

                    <div class="clr"></div>
                </div>

                <div class="col-md-3">
                    <fieldset class="options-form">
                        <legend><?php echo Text::_('COM_JEM_EXPORT_OTHER_LEGEND');?></legend>

                        <ul class="adminformlist">
                            <li>
                                <label class="labelexport"><?php echo Text::_('COM_JEM_EXPORT_CATEGORIES'); ?></label>
                                <input type="submit" class="btn btn-success csvexport" value="<?php echo Text::_('COM_JEM_EXPORT_FILE'); ?>" onclick="return jemSubmitExport('export.exportcats', <?php echo htmlspecialchars(json_encode(Text::_('COM_JEM_EXPORT_CATEGORIES')), ENT_QUOTES, 'UTF-8'); ?>, 'exportCategories-{timestamp}.csv');">
                            </li>
                            <li>
                                <label class="labelexport"><?php echo Text::_('COM_JEM_EXPORT_VENUES'); ?></label>
                                <input type="submit" class="btn btn-success csvexport" value="<?php echo Text::_('COM_JEM_EXPORT_FILE'); ?>" onclick="return jemSubmitExport('export.exportvenues', <?php echo htmlspecialchars(json_encode(Text::_('COM_JEM_EXPORT_VENUES')), ENT_QUOTES, 'UTF-8'); ?>, 'exportVenues-{timestamp}.csv');">
                            </li>
                            <li>
                                <label class="labelexport"><?php echo Text::_('COM_JEM_EXPORT_CAT_EVENTS'); ?></label>
                                <input type="submit" class="btn btn-success csvexport" value="<?php echo Text::_('COM_JEM_EXPORT_FILE'); ?>" onclick="return jemSubmitExport('export.exportcatevents', <?php echo htmlspecialchars(json_encode(Text::_('COM_JEM_EXPORT_CAT_EVENTS')), ENT_QUOTES, 'UTF-8'); ?>, 'exportCatEvents-{timestamp}.csv');">
                            </li>
                            <li>
                                <label class="labelexport"><?php echo Text::_('COM_JEM_EXPORT_ATTACHMENTS'); ?></label>
                                <input type="submit" class="btn btn-success csvexport" value="<?php echo Text::_('COM_JEM_EXPORT_FILE'); ?>" onclick="return jemSubmitExport('export.exportattachments', <?php echo htmlspecialchars(json_encode(Text::_('COM_JEM_EXPORT_ATTACHMENTS')), ENT_QUOTES, 'UTF-8'); ?>, 'exportAttachments-{timestamp}.csv');">
                            </li>
                            <li>
                                <label class="labelexport"><?php echo Text::_('COM_JEM_EXPORT_TYPES'); ?></label>
                                <input type="submit" class="btn btn-success csvexport" value="<?php echo Text::_('COM_JEM_EXPORT_FILE'); ?>" onclick="return jemSubmitExport('export.exporttypes', <?php echo htmlspecialchars(json_encode(Text::_('COM_JEM_EXPORT_TYPES')), ENT_QUOTES, 'UTF-8'); ?>, 'exportTypes-{timestamp}.csv');">
                            </li>
                        </ul>
                    </fieldset>
                    <div class="clr"></div>
                </div>
            </div>

            <section class="card mt-4" id="catalog-event-export">
                <div class="card-body">
                    <h2 class="h3"><?php echo Text::_('COM_JEM_EXPORT_CATALOG_TITLE'); ?></h2>
                    <p><?php echo Text::_('COM_JEM_EXPORT_CATALOG_DESC'); ?></p>

                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-5">
                            <label class="form-label" for="catalog_search"><?php echo Text::_('COM_JEM_EXPORT_CATALOG_SEARCH'); ?></label>
                            <input type="search" class="form-control" id="catalog_search" name="catalog_search" value="<?php echo htmlspecialchars($this->catalogFilters['search'], ENT_QUOTES, 'UTF-8'); ?>" />
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label" for="catalog_published"><?php echo Text::_('COM_JEM_EXPORT_CATALOG_STATE'); ?></label>
                            <select class="form-select" id="catalog_published" name="catalog_published">
                                <option value=""><?php echo Text::_('JALL'); ?></option>
                                <option value="1"<?php echo (string) $this->catalogFilters['published'] === '1' ? ' selected' : ''; ?>><?php echo Text::_('JPUBLISHED'); ?></option>
                                <option value="0"<?php echo (string) $this->catalogFilters['published'] === '0' ? ' selected' : ''; ?>><?php echo Text::_('JUNPUBLISHED'); ?></option>
                                <option value="2"<?php echo (string) $this->catalogFilters['published'] === '2' ? ' selected' : ''; ?>><?php echo Text::_('JARCHIVED'); ?></option>
                                <option value="-2"<?php echo (string) $this->catalogFilters['published'] === '-2' ? ' selected' : ''; ?>><?php echo Text::_('JTRASHED'); ?></option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label" for="catalog_include_categories"><?php echo Text::_('COM_JEM_EXPORT_CATALOG_INCLUDE_CATEGORIES'); ?></label>
                            <select class="form-select" id="catalog_include_categories" name="catalog_include_categories">
                                <option value="1"<?php echo $this->catalogIncludeCategories ? ' selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
                                <option value="0"<?php echo !$this->catalogIncludeCategories ? ' selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label" for="catalog_fields"><?php echo Text::_('COM_JEM_EXPORT_CATALOG_FIELDS'); ?></label>
                        <?php echo $this->catalogFieldOptions; ?>
                        <div class="form-text"><?php echo Text::_('COM_JEM_EXPORT_CATALOG_FIELDS_DESC'); ?></div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 align-items-end mt-3">
                        <button type="submit" class="btn btn-primary" onclick="return jemSubmitExportTask('export.previewCatalogEvents');">
                            <span class="icon-search" aria-hidden="true"></span>
                            <?php echo Text::_('COM_JEM_EXPORT_CATALOG_PREVIEW'); ?>
                        </button>
                        <div>
                            <label class="form-label" for="catalog_export_format"><?php echo Text::_('COM_JEM_EXPORT_CATALOG_FORMAT'); ?></label>
                            <select class="form-select" id="catalog_export_format" name="catalog_export_format">
                                <option value="csv">CSV</option>
                                <option value="json">JSON</option>
                                <option value="xml">XML</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success" onclick="return jemSubmitExportTask('export.exportCatalogEvents');">
                            <span class="icon-download" aria-hidden="true"></span>
                            <?php echo Text::_('COM_JEM_EXPORT_CATALOG_DOWNLOAD'); ?>
                        </button>
                    </div>

                    <?php if (!empty($this->catalogPreview['requested'])) : ?>
                        <div class="alert alert-info mt-4">
                            <?php echo Text::sprintf('COM_JEM_EXPORT_CATALOG_PREVIEW_SUMMARY', (int) $this->catalogPreview['total']); ?>
                        </div>
                        <?php if (!empty($this->catalogPreview['items'])) : ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <?php foreach ((array) ($this->catalogPreview['fields'] ?? array()) as $field) : ?>
                                                <th><?php echo htmlspecialchars((string) ($this->catalogPreview['labels'][$field] ?? $field), ENT_QUOTES, 'UTF-8'); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($this->catalogPreview['items'] as $item) : ?>
                                            <tr>
                                                <?php foreach ((array) ($this->catalogPreview['fields'] ?? array()) as $field) : ?>
                                                    <?php $fullValue = (string) ($item[$field] ?? ''); ?>
                                                    <td title="<?php echo htmlspecialchars($fullValue, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($truncateExportPreview($fullValue), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ((int) $this->catalogPreview['total'] > 100) : ?>
                                <p class="text-muted"><?php echo Text::_('COM_JEM_EXPORT_CATALOG_PREVIEW_LIMIT'); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <?php //if (isset($this->sidebar)) : ?>
        <?php //endif; ?>

        <?php echo HTMLHelper::_( 'form.token' ); ?>
        <input type="hidden" name="option" value="com_jem" />
        <input type="hidden" name="view" value="export" />
        <input type="hidden" name="controller" value="export" />
        <input type="hidden" name="task" value="" />
        <input type="hidden" name="export_filename" value="" />
    </form>
</div>
