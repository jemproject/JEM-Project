<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

$app = Factory::getApplication();
$document = $app->getDocument();
$wa = $document->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

// Create shortcut to parameters.
$params        = $this->params;
// $settings    = json_decode($this->item->attribs);
$hideEmptyManagedFields = !empty($this->jemsettings->frontend_hide_empty_managed_fields);
$typeField = $this->form->getField('type_id');
$showTypeField = !$hideEmptyManagedFields || !$typeField || !method_exists($typeField, 'hasAvailableTypes') || $typeField->hasAvailableTypes();
$contactField = $this->form->getField('contactid');
$showContactField = $contactField && (!method_exists($contactField, 'hasAvailableContacts') || $contactField->hasAvailableContacts());
$articleAutoInfo = htmlspecialchars(Text::_('COM_JEM_EVENT_ARTICLE_AUTO_INFO'), ENT_QUOTES, 'UTF-8');
$articleAutoInfoCategory = htmlspecialchars(Text::_('COM_JEM_EVENT_ARTICLE_AUTO_INFO_CATEGORY'), ENT_QUOTES, 'UTF-8');
$articleCategoryRules = array();

try {
    $db = Factory::getContainer()->get('DatabaseDriver');
    $query = $db->getQuery(true)
        ->select(array(
            $db->quoteName('jc.id'),
            $db->quoteName('jc.article_category_id'),
            $db->quoteName('jc.article_create_mode'),
            $db->quoteName('cc.title', 'article_category_title'),
        ))
        ->from($db->quoteName('#__jem_categories', 'jc'))
        ->join('LEFT', $db->quoteName('#__categories', 'cc') . ' ON ' . $db->quoteName('cc.id') . ' = ' . $db->quoteName('jc.article_category_id') . ' AND ' . $db->quoteName('cc.extension') . ' = ' . $db->quote('com_content'));
    $db->setQuery($query);

    foreach ($db->loadObjectList() ?: array() as $categoryRule) {
        $articleCategoryRules[(int) $categoryRule->id] = array(
            'categoryId'    => (int) $categoryRule->article_category_id,
            'categoryTitle' => (string) $categoryRule->article_category_title,
            'mode'          => (int) $categoryRule->article_create_mode,
        );
    }
} catch (Throwable $e) {
    $articleCategoryRules = array();
}

$uploadSizeToBytes = static function ($value) {
    $value = trim((string) $value);
    $unit  = strtolower(substr($value, -1));
    $size  = (float) $value;

    if ($unit === 'g') {
        $size *= 1024 * 1024 * 1024;
    } elseif ($unit === 'm') {
        $size *= 1024 * 1024;
    } elseif ($unit === 'k') {
        $size *= 1024;
    }

    return $size;
};
$formatUploadSize = static function ($bytes) {
    $bytes = (float) $bytes;

    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 2) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }

    return number_format($bytes, 0) . ' B';
};
$uploadMaxBytes = $uploadSizeToBytes(ini_get('upload_max_filesize'));
$postMaxBytes   = $uploadSizeToBytes(ini_get('post_max_size'));
$uploadLimit    = $formatUploadSize($postMaxBytes > 0 ? min($uploadMaxBytes, $postMaxBytes) : $uploadMaxBytes);

$document->addStyleDeclaration('
    .jem-associated-article-options {
        border: 0;
        margin: 0 !important;
        padding: 0 .5rem !important;
    }
    .jem-associated-article-options .jem-dl {
        display: grid;
        grid-template-columns: minmax(14rem, 240px) minmax(0, 1fr);
        align-items: center;
        column-gap: 0;
        margin-bottom: 0;
    }
    .jem-associated-article-options .jem-dl dt,
    .jem-associated-article-options .jem-dl dd {
        margin-bottom: .35rem;
    }
    .jem-associated-article-options .alert {
        margin-top: .35rem !important;
    }
    .jem-associated-article-options .jem-dl .alert {
        grid-column: 2;
    }
    .jem-associated-article-picker {
        display: flex;
        flex-wrap: nowrap;
        align-items: stretch;
        max-width: 100%;
    }
    .jem-associated-article-picker > * {
        flex: 1 1 auto;
        min-width: 0;
    }
    .jem-editevent-field-date .field-calendar,
    .jem-editevent-field-date .input-group,
    .jem-editevent-field-date .input-append {
        display: inline-flex;
        width: min(100%, 28rem);
        max-width: 28rem;
    }
    .jem-editevent-field-date input[type="text"],
    .jem-editevent-field-date input[type="date"] {
        width: 14rem !important;
        max-width: 14rem;
        flex: 0 0 14rem;
    }
    .jem-editevent-field-cats joomla-field-fancy-select,
    .jem-editevent-field-cats joomla-field-fancy-select .choices,
    .jem-editevent-field-cats joomla-field-fancy-select .choices__inner,
    .jem-editevent-field-cats .choices,
    .jem-editevent-field-cats select {
        width: min(100%, 36rem) !important;
        max-width: 36rem !important;
    }
    .jem-editevent-field-cats .choices__list--dropdown,
    .jem-editevent-field-cats .choices__list[aria-expanded] {
        width: 100%;
        min-width: 100%;
        max-width: 36rem;
        overflow-x: hidden;
    }
    .jem-editevent-field-cats .choices__item {
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .jem-editevent-image-fields {
        display: grid;
        gap: .9rem;
        max-width: 100%;
    }
    .jem-editevent-image-field {
        display: grid;
        grid-template-columns: minmax(220px, 260px) minmax(0, 1fr);
        gap: .75rem 1rem;
        align-items: center;
        min-width: 0;
        border: 1px solid #b8bec8;
        border-radius: .55rem;
        padding: .75rem 1rem;
        background: #fff;
        box-sizing: border-box;
    }
    .jem-editevent-image-copy strong,
    .jem-editevent-image-copy span {
        display: block;
    }
    .jem-editevent-image-copy span {
        color: #6c757d;
        font-size: .9rem;
        line-height: 1.3;
        margin-top: .15rem;
    }
    .jem-editevent-image-maxsize {
        color: #495057;
        display: block;
        font-size: .82rem;
        line-height: 1.25;
        margin-top: .75rem;
    }
    .jem-editevent-image-maxsize strong {
        white-space: nowrap;
    }
    .jem-editevent-image-control {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto auto;
        gap: .65rem .75rem;
        align-items: center;
        min-width: 0;
    }
    .jem-editevent-image-choice {
        display: grid;
        grid-column: 1;
        grid-template-columns: minmax(9rem, 10rem) minmax(18rem, 1fr);
        gap: .4rem .75rem;
        align-items: center;
        min-width: 0;
    }
    .jem-editevent-image-choice span {
        color: #6c757d;
        font-size: .82rem;
        font-weight: 600;
        line-height: 1.2;
        grid-column: 1;
    }
    .jem-editevent-image-choice select,
    .jem-editevent-image-choice input[type="file"],
    .jem-editevent-image-choice joomla-field-fancy-select,
    .jem-editevent-image-choice .choices {
        grid-column: 2;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .jem-editevent-image-choice joomla-field-fancy-select,
    .jem-editevent-image-choice joomla-field-fancy-select .choices,
    .jem-editevent-image-choice joomla-field-fancy-select .choices__inner {
        width: min(100%, 24rem) !important;
        max-width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box;
    }
    .jem-editevent-image-upload {
        align-items: start;
    }
    .jem-editevent-image-upload,
    .jem-editevent-image-upload > :not(span) {
        white-space: nowrap;
    }
    .jem-editevent-image-upload br {
        display: none;
    }
    .jem-editevent-image-upload .form-text,
    .jem-editevent-image-upload small,
    .jem-editevent-image-upload > div:not(:first-child) {
        display: none;
    }
    .jem-editevent-image-control input[type="file"] {
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }
    .jem-editevent-image-preview {
        grid-column: 2;
        grid-row: 1 / span 2;
        max-width: 140px;
    }
    .jem-editevent-image-preview img {
        max-width: 140px;
        max-height: 110px;
        object-fit: contain;
    }
    .jem-editevent-image-field--layout select {
        width: min(420px, 100%);
    }
    .jem-editevent-image-clear {
        grid-column: 3;
        grid-row: 1 / span 2;
        align-self: start;
        white-space: nowrap;
    }
    .jem-editevent-image-remove {
        grid-column: 1 / 4;
        justify-self: start;
    }
    .jem-editevent-image-layout-choice {
        grid-column: 1 / 4;
        border-top: 1px solid #d9dee7;
        margin-top: .25rem;
        padding-top: .75rem;
    }
    .jem-editevent-image-layout-choice span {
        align-self: start;
    }
    .jem-editevent-image-layout-copy {
        display: block;
    }
    .jem-editevent-image-layout-copy small {
        display: block;
        color: #6c757d;
        font-weight: 400;
        line-height: 1.3;
        margin-top: .15rem;
    }
    .jem-editevent-image-layout-choice {
        grid-template-columns: minmax(9rem, 10rem) max-content;
    }
    .jem-editevent-image-layout-choice select {
        width: auto;
        min-width: 12rem;
        max-width: 100%;
    }
    @media (max-width: 767.98px) {
        .jem-associated-article-options .jem-dl {
            grid-template-columns: 1fr;
            row-gap: .25rem;
        }
        .jem-associated-article-options .jem-dl .alert {
            grid-column: 1;
        }
        .jem-editevent-field-date .field-calendar,
        .jem-editevent-field-date .input-group,
        .jem-editevent-field-date .input-append,
        .jem-editevent-field-date input[type="text"],
        .jem-editevent-field-date input[type="date"],
        .jem-editevent-field-cats joomla-field-fancy-select,
        .jem-editevent-field-cats joomla-field-fancy-select .choices,
        .jem-editevent-field-cats joomla-field-fancy-select .choices__inner,
        .jem-editevent-field-cats .choices,
        .jem-editevent-field-cats select {
            width: 100% !important;
            max-width: 100% !important;
        }
        .jem-editevent-image-field {
            grid-template-columns: 1fr;
        }
        .jem-editevent-image-control {
            display: grid;
            grid-template-columns: 1fr;
            align-items: stretch;
        }
        .jem-editevent-image-choice,
        .jem-editevent-image-choice span,
        .jem-editevent-image-choice select,
        .jem-editevent-image-choice input[type="file"],
        .jem-editevent-image-choice joomla-field-fancy-select,
        .jem-editevent-image-choice .choices,
        .jem-editevent-image-layout-choice,
        .jem-editevent-image-clear,
        .jem-editevent-image-remove,
        .jem-editevent-image-preview {
            grid-column: 1;
            grid-row: auto;
        }
        .jem-editevent-image-choice,
        .jem-editevent-image-layout-choice,
        .jem-editevent-image-control .btn,
        .jem-editevent-image-control .button3 {
            width: 100%;
        }
        .jem-editevent-image-choice {
            grid-template-columns: 1fr;
        }
        .jem-editevent-image-layout-choice {
            grid-template-columns: 1fr;
        }
        .jem-editevent-image-choice joomla-field-fancy-select,
        .jem-editevent-image-choice joomla-field-fancy-select .choices,
        .jem-editevent-image-choice joomla-field-fancy-select .choices__inner {
            width: 100% !important;
        }
        .jem-editevent-image-upload,
        .jem-editevent-image-upload > :not(span) {
            white-space: normal;
        }
    }
');
?>

<script>
    jQuery(document).ready(function($) {
        var imageConflictMessage = <?php echo json_encode(Text::_('COM_JEM_IMAGE_UPLOAD_CONFLICT')); ?>;

        function resetSelect($select) {
            if (!$select.length) {
                return;
            }

            $select.val('').trigger('change');
            var fancy = $select.closest('joomla-field-fancy-select').get(0);

            if (fancy) {
                fancy.dispatchEvent(new Event('change', {bubbles: true}));
            }
        }

        function showImageConflictMessage() {
            if (window.Joomla && typeof Joomla.renderMessages === 'function') {
                Joomla.renderMessages({error: [imageConflictMessage]});
                return;
            }

            window.alert(imageConflictMessage);
        }

        $('.jem-editevent-image-clear').on('click', function() {
            var $button = $(this);
            resetSelect($('#' + $button.data('jemImageSelect')));
            $('#' + $button.data('jemImageFile')).val('').trigger('change');
        });

        $('.jem-editevent-image-upload').each(function() {
            $(this).contents().filter(function() {
                return this.nodeType === 3 && this.nodeValue.indexOf('Maximum upload size') !== -1;
            }).remove();

            $(this).find('strong').filter(function() {
                return /^\s*\d+([.,]\d+)?\s*(KB|MB|GB)\s*$/i.test($(this).text());
            }).remove();
        });

        $('#jform_userfile, #jform_fulluserfile').on('change', function() {
            var $file = $(this);
            var selectId = this.id === 'jform_fulluserfile' ? 'jform_fullimage' : 'jform_datimage';

            if ($file.val() && $('#' + selectId).val()) {
                $file.val('');
                showImageConflictMessage();
            }
        });
    });
</script>

<script>
    jQuery(document).ready(function($){

        function checkmaxplaces(){
            var maxplaces = $('jform_maxplaces');

            if (maxplaces != null){
                $('#jform_maxplaces').on('change', function(){
                    if ($('#event-available')) {
                        var val = parseInt($('#jform_maxplaces').val());
                        var booked = parseInt($('#event-booked').val());
                        $('event-available').val() = (val-booked);
                    }
                });

                $('#jform_maxplaces').on('keyup', function(){
                    if ($('event-available')) {
                        var val = parseInt($('jform_maxplaces').val());
                        var booked = parseInt($('event-booked').val());
                        $('event-available').val() = (val-booked);
                    }
                });
            }
        }
        checkmaxplaces();
    });
</script>

<script>
    $(document).ready(function () {
        var $registraCheckbox = $('input[name="jform[registra]"]');
        var $restOfContent = $(".jem-dl-rest").children("dd, dt");

        $registraCheckbox.on("change", function () {
            if ($(this).is(":checked")) {
                $restOfContent.show();
            } else {
                $restOfContent.hide();
            }
        });

        var $minBookedUserInput = $("#jform_minbookeduser");
        var $maxBookedUserInput = $("#jform_maxbookeduser");
        var $maxPlacesInput = $("#jform_maxplaces");
        var $reservedPlacesInput = $("#jform_reservedplaces");

        $minBookedUserInput
            .add($maxBookedUserInput)
            .add($maxPlacesInput)
            .add($reservedPlacesInput)
            .on("change", function () {
                var minBookedUserValue = parseInt($minBookedUserInput.val());
                var maxBookedUserValue = parseInt($maxBookedUserInput.val());
                var maxPlacesValue = parseInt($maxPlacesInput.val());
                var reservedPlacesValue = parseInt($reservedPlacesInput.val());
                if (minBookedUserValue > maxPlacesValue && maxPlacesValue != 0) {
                    $minBookedUserInput.val(maxPlacesValue);
                }
                if (maxBookedUserValue > maxPlacesValue && maxPlacesValue != 0) {
                    $maxBookedUserInput.val(maxPlacesValue);
                }
                if (minBookedUserValue > maxBookedUserValue) {
                    $minBookedUserInput.val(maxBookedUserValue);
                }
                if (reservedPlacesValue > maxPlacesValue && maxPlacesValue != 0) {
                    $reservedPlacesInput.val(maxPlacesValue);
                }
            });

        // Trigger the change event on page load to initialize the state
        $registraCheckbox.change();
        $minBookedUserInput.change();

        var articleCategoryRules = <?php echo json_encode($articleCategoryRules); ?>;
        var articleAutoInfoText = <?php echo json_encode($articleAutoInfo); ?>;
        var articleAutoInfoCategoryText = <?php echo json_encode($articleAutoInfoCategory); ?>;
        var $articleBlock = $('.jem-associated-article-options');
        var $articleAction = $('#jform_create_article');
        var $articleUsage = $('#jform_attribs_article_usage');
        var $articleAutoInfo = $('#jem-article-auto-info');
        var $articleTargetCategory = $('#jform_article_target_category_id');

        function getSelectedCategoryIds() {
            var values = $('#jform_cats').val() || $('select[name="jform[cats][]"]').val() || $('input[name="jform[cats][]"]:checked').map(function () {
                return this.value;
            }).get() || [];

            if (!Array.isArray(values)) {
                values = [values];
            }

            return values.map(function (value) {
                return parseInt(value, 10) || 0;
            }).filter(Boolean);
        }

        function hasAssociatedArticle() {
            return $articleBlock.data('hasArticle') === 1 || parseInt($('#jform_article_id_id, #jform_article_id').first().val(), 10) > 0;
        }

        function setArticleRowVisibility(selector, visible) {
            $articleBlock.find(selector).toggle(!!visible);
        }

        function updateArticleTargetCategoryOptions(categories) {
            if (!$articleTargetCategory.length) {
                return;
            }

            if (!$articleTargetCategory.data('originalOptions')) {
                $articleTargetCategory.data('originalOptions', $articleTargetCategory.find('option').map(function () {
                    return {
                        value: this.value,
                        text: $(this).text()
                    };
                }).get());
            }

            var currentValue = parseInt($articleTargetCategory.val(), 10) || 0;
            $articleTargetCategory.empty();

            if (!categories.length) {
                ($articleTargetCategory.data('originalOptions') || []).forEach(function (option) {
                    $('<option>')
                        .val(option.value)
                        .text(option.text)
                        .appendTo($articleTargetCategory);
                });
                $articleTargetCategory.val(currentValue);
                $articleTargetCategory.trigger('change');
                return;
            }

            categories.forEach(function (category) {
                $('<option>')
                    .val(category.id)
                    .text(category.title || ('#' + category.id))
                    .appendTo($articleTargetCategory);
            });

            if (categories.length && categories.some(function (category) { return category.id === currentValue; })) {
                $articleTargetCategory.val(currentValue);
            } else if (categories.length) {
                $articleTargetCategory.val(categories[0].id);
            }

            $articleTargetCategory.trigger('change');
        }

        function updateAssociatedArticleOptions() {
            if (!$articleBlock.length || !$articleAction.length) {
                return;
            }

            var selected = getSelectedCategoryIds();
            var rules = selected.map(function (categoryId) {
                return articleCategoryRules[categoryId] || {categoryId: 0, mode: 0};
            });
            var hasAuto = rules.some(function (rule) {
                return parseInt(rule.mode, 10) === 1;
            });
            var hasConfigured = rules.some(function (rule) {
                return parseInt(rule.mode, 10) !== 0;
            });
            var autoArticleCategoryIds = [];
            var autoArticleCategoryTitles = [];
            var autoArticleCategories = [];
            rules.forEach(function (rule) {
                var categoryId = parseInt(rule.categoryId, 10) || 0;
                var mode = parseInt(rule.mode, 10);

                if (!categoryId) {
                    return;
                }

                if (mode === 1 && autoArticleCategoryIds.indexOf(categoryId) === -1) {
                    autoArticleCategoryIds.push(categoryId);
                    autoArticleCategoryTitles.push(rule.categoryTitle || ('#' + categoryId));
                    autoArticleCategories.push({
                        id: categoryId,
                        title: rule.categoryTitle || ('#' + categoryId)
                    });
                }
            });
            var articleSelected = hasAssociatedArticle();
            var actionValue = $articleAction.val();

            if (!$articleBlock.data('articleUsageInitialized') && !articleSelected && actionValue === '0' && $articleUsage.val() === 'information' && !hasConfigured) {
                $articleUsage.val('none');
            }
            $articleBlock.data('articleUsageInitialized', 1);

            var usageValue = $articleUsage.val() || 'none';
            var usesArticle = usageValue !== 'none';

            $articleAction.find('option[value="2"]').prop('disabled', !hasAuto).toggle(hasAuto);

            if (!usesArticle) {
                $articleAction.val('0');
                actionValue = '0';
            } else if (hasAuto) {
                $articleAction.val('2');
                actionValue = '2';
            } else {
                $articleAction.val('0');
                actionValue = '0';
            }

            setArticleRowVisibility('.js-jem-article-selector', usesArticle);
            setArticleRowVisibility('.js-jem-article-target', usesArticle && hasAuto && autoArticleCategoryIds.length > 1);
            setArticleRowVisibility('.js-jem-article-usage', true);
            updateArticleTargetCategoryOptions((usesArticle && hasAuto) ? autoArticleCategories : []);
            if (hasAuto && autoArticleCategoryTitles.length) {
                $articleAutoInfo.html(articleAutoInfoCategoryText.replace('%s', $('<div>').text(autoArticleCategoryTitles.join(', ')).html()));
            } else {
                $articleAutoInfo.html(articleAutoInfoText);
            }
            $articleAutoInfo.prop('hidden', actionValue !== '2');
        }

        $articleUsage.on('change', updateAssociatedArticleOptions);
        $articleAction.on('change', updateAssociatedArticleOptions);
        $('#jform_cats, select[name="jform[cats][]"], input[name="jform[cats][]"]').on('change', updateAssociatedArticleOptions);
        $('#jform_article_id_id, #jform_article_id').on('change', updateAssociatedArticleOptions);
        $articleBlock.on('click', '.button-clear, .btn', function () {
            window.setTimeout(updateAssociatedArticleOptions, 100);
        });
        updateAssociatedArticleOptions();
    });
</script>

<script>
    Joomla.submitbutton = function(task) {
        if (task == 'event.cancel' || document.formvalidator.isValid(document.getElementById('adminForm'))) {
            <?php //echo $this->form->getField('articletext')->save(); ?>
            Joomla.submitform(task);
        } else {
            alert('<?php echo $this->escape(Text::_('JGLOBAL_VALIDATION_FORM_FAILED'));?>');
        }
    }
</script>
<script>
    // window.addEvent('domready', function(){
    jQuery(document).ready(function($){

        var showUnregistraUntil = function(){
            var unregistra = $("#jform_unregistra");

            var unregistramode = unregistra.val();

            if (unregistramode == 2) {
                document.getElementById('unregistra_until').style.display = '';
                document.getElementById('unregistra_until2').style.display = '';
                document.getElementById('jform_unregistra_until').style.display = '';
                document.getElementById('jform_unregistra_until2').style.display = '';
            } else {
                document.getElementById('unregistra_until').style.display = 'none';
                document.getElementById('unregistra_until2').style.display = 'none';
                document.getElementById('jform_unregistra_until').style.display = 'none';
                document.getElementById('jform_unregistra_until2').style.display = 'none';
            }
        }
        $("#jform_unregistra").on('change', showUnregistraUntil);
        showUnregistraUntil();
    });

    jQuery(document).ready(function($){

        var showRegistraFrom = function(){
            var registra = $("#jform_registra");

            var registramode = registra.val();

            if (registramode == 2) {
                document.getElementById('registra_from').style.display = '';
                document.getElementById('registra_from2').style.display = '';
                document.getElementById('registra_until').style.display = '';
                document.getElementById('registra_until2').style.display = '';
                document.getElementById('jform_registra_from').style.display = '';
                document.getElementById('jform_registra_from2').style.display = '';
                document.getElementById('jform_registra_until').style.display = '';
                document.getElementById('jform_registra_until2').style.display = '';
            } else {
                document.getElementById('registra_from').style.display = 'none';
                document.getElementById('registra_from2').style.display = 'none';
                document.getElementById('registra_until').style.display = 'none';
                document.getElementById('registra_until2').style.display = 'none';
                document.getElementById('jform_registra_from').style.display = 'none';
                document.getElementById('jform_registra_from2').style.display = 'none';
                document.getElementById('jform_registra_until').style.display = 'none';
                document.getElementById('jform_registra_until2').style.display = 'none';

            }
        }
        $("#jform_registra").on('change', showRegistraFrom);
        showRegistraFrom();
    });
</script>

<div id="jem" class="jem_editevent<?php echo $this->pageclass_sfx; ?>">
    <div class="edit item-page">
        <?php if ($params->get('show_page_heading')) : ?>
            <h1>
                <?php echo $this->escape($params->get('page_heading')); ?>
            </h1>
        <?php endif; ?>

        <form enctype="multipart/form-data" action="<?php echo Route::_('index.php?option=com_jem&a_id=' . (int) $this->item->id); ?>" method="post" name="adminForm" id="adminForm" class="form-validate">

            <button type="submit" class="btn btn-primary" onclick="Joomla.submitbutton('event.save')"><?php echo Text::_('JSAVE') ?></button>
            <button type="cancel" class="btn btn-secondary" onclick="Joomla.submitbutton('event.cancel')"><?php echo Text::_('JCANCEL') ?></button>
            <br>
            <?php if ($this->item->recurrence_type > 0) : ?>
                <div class="description warningrecurrence" style="clear: both;">
                    <div style="float:left;">
                        <?php echo JemOutput::recurrenceicon($this->item, false, false); ?>
                    </div>
                    <div class="floattext" style="margin-left:36px;">
                        <strong><?php echo Text::_('COM_JEM_EDITEVENT_WARN_RECURRENCE_TITLE'); ?></strong>
                        <br>
                        <?php
                        if (!empty($this->item->recurrence_type) && empty($this->item->recurrence_first_id)) {
                            echo nl2br(Text::_('COM_JEM_EDITEVENT_WARN_RECURRENCE_FIRST_TEXT'));
                        } else {
                            echo nl2br(Text::_('COM_JEM_EDITEVENT_WARN_RECURRENCE_TEXT'));
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($this->params->get('showintrotext')) : ?>
                <div class="description no_space floattext">
                    <?php echo $this->params->get('introtext'); ?>
                </div>
            <?php endif; ?>

            <?php //echo HTMLHelper::_('tabs.start', 'det-pane'); ?>

            <!-- DETAILS TAB -->
            <?php //echo HTMLHelper::_('tabs.panel', Text::_('COM_JEM_EDITEVENT_INFO_TAB'), 'editevent-infotab'); ?>
            <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'editevent-infotab', 'recall' => true, 'breakpoint' => 768]); ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'editevent-infotab', Text::_('COM_JEM_EDITEVENT_INFO_TAB')); ?>

            <fieldset class="adminform">
                <legend><?php echo Text::_('COM_JEM_EDITEVENT_DETAILS_LEGEND'); ?></legend>
                <dl class="jem-dl">
                    <dt><?php echo $this->form->getLabel('title'); ?></dt>
                    <dd><?php echo $this->form->getInput('title'); ?></dd>
                    <?php if (is_null($this->item->id)) : ?>
                        <dt><?php echo $this->form->getLabel('alias'); ?></dt>
                        <dd><?php echo $this->form->getInput('alias'); ?></dd>
                    <?php endif; ?>
                    <dt class="jem-editevent-field-date-label"><?php echo $this->form->getLabel('dates'); ?></dt>
                    <dd class="jem-editevent-field-date"><?php echo $this->form->getInput('dates'); ?></dd>
                    <dt class="jem-editevent-field-date-label"><?php echo $this->form->getLabel('enddates'); ?></dt>
                    <dd class="jem-editevent-field-date"><?php echo $this->form->getInput('enddates'); ?></dd>
                    <dt><?php echo $this->form->getLabel('times'); ?></dt>
                    <dd class="time-input"><?php echo $this->form->getInput('times'); ?></dd>
                    <dt><?php echo $this->form->getLabel('endtimes'); ?></dt>
                    <dd class="time-input"><?php echo $this->form->getInput('endtimes'); ?></dd>
                    <?php if($this->jemsettings->defaultCategory && empty($this->item->id)) {
                        $this->form->setFieldAttribute('cats', 'default', $this->jemsettings->defaultCategory);
                    } ?>
                    <dt class="jem-editevent-field-cats-label"><?php echo $this->form->getLabel('cats'); ?></dt>
                    <dd class="jem-editevent-field-cats"><?php echo $this->form->getInput('cats'); ?></dd>
                    <?php if($this->jemsettings->defaultVenue && empty($this->item->id)) {
                        $this->form->setFieldAttribute('locid', 'default', $this->jemsettings->defaultVenue);
                    } ?>
                    <dt><?php echo $this->form->getLabel('locid'); ?></dt>
                    <dd><?php echo $this->form->getInput('locid'); ?></dd>
                    <?php if ($showTypeField) : ?>
                        <dt><?php echo $this->form->getLabel('type_id'); ?></dt>
                        <dd><?php echo $this->form->getInput('type_id'); ?></dd>
                    <?php else : ?>
                        <?php echo $this->form->getInput('type_id'); ?>
                    <?php endif; ?>
                    <?php if ($showContactField) : ?>
                        <dt><?php echo $this->form->getLabel('contactid'); ?></dt>
                        <dd><?php echo $this->form->getInput('contactid'); ?></dd>
                    <?php else : ?>
                        <?php echo $this->form->getInput('contactid'); ?>
                    <?php endif; ?>
                    <dt><?php echo $this->form->getLabel('featured'); ?></dt>
                    <dd><?php echo $this->form->getInput('featured'); ?></dd>
                </dl>
            </fieldset>
            <?php if ($this->form->getField('article_id')) : ?>
                <fieldset class="adminform jem-associated-article-options" data-has-article="<?php echo !empty($this->item->article_id) ? 1 : 0; ?>">
                    <dl class="jem-dl">
                        <dt class="js-jem-article-usage"><?php echo $this->form->getLabel('article_usage', 'attribs'); ?></dt>
                        <dd class="js-jem-article-usage"><?php echo $this->form->getInput('article_usage', 'attribs'); ?></dd>
                        <dt class="js-jem-article-selector"><?php echo $this->form->getLabel('article_id'); ?></dt>
                        <dd class="js-jem-article-selector">
                            <div class="jem-associated-article-picker">
                                <?php echo $this->form->getInput('article_id'); ?>
                            </div>
                            <input type="hidden" name="jform[create_article]" id="jform_create_article" value="<?php echo (int) $this->form->getValue('create_article'); ?>">
                            <div id="jem-article-auto-info" class="alert alert-info small mt-2 mb-0" hidden>
                                <?php echo $articleAutoInfo; ?>
                            </div>
                        </dd>
                        <dt class="js-jem-article-target"><?php echo $this->form->getLabel('article_target_category_id'); ?></dt>
                        <dd class="js-jem-article-target"><?php echo $this->form->getInput('article_target_category_id'); ?></dd>
                    </dl>
                </fieldset>
            <?php endif; ?>
            <!-- EVENTDESCRIPTION -->
            <fieldset class="adminform">
                <legend><?php echo Text::_('COM_JEM_EDITEVENT_DESCRIPTION_LEGEND'); ?></legend>
                <div>
                    <?php echo $this->form->getLabel('articletext'); ?>
                    <?php echo $this->form->getInput('articletext'); ?>
                </div>
            </fieldset>

            <!-- IMAGE -->
            <?php if ($this->item->datimage || !empty($this->item->fullimage) || $this->jemsettings->imageenabled != 0) : ?>
                <fieldset class="jem_fldst_image">
                    <legend><?php echo Text::_('COM_JEM_IMAGE'); ?></legend>
                    <?php if ($this->jemsettings->imageenabled != 0) : ?>
                        <div class="jem-editevent-image-fields">
                            <div class="jem-editevent-image-field">
                                <div class="jem-editevent-image-copy">
                                    <strong><?php echo Text::_('COM_JEM_EVENT_INTRO_IMAGE'); ?></strong>
                                    <span><?php echo Text::_('COM_JEM_EVENT_INTRO_IMAGE_DESC'); ?></span>
                                    <small class="jem-editevent-image-maxsize"><?php echo Text::_('COM_JEM_MAXIMUM_UPLOAD_SIZE'); ?> <strong><?php echo $uploadLimit; ?></strong></small>
                                </div>
                                <div class="jem-editevent-image-control">
                                    <?php if ($this->item->datimage) : ?>
                                        <div class="jem-editevent-image-preview jem-editevent-image-preview--intro">
                                            <?php echo JEMOutput::flyer($this->item, $this->dimage, 'event', 'datimage'); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="jem-editevent-image-choice">
                                        <span><?php echo Text::_('COM_JEM_SERVER_IMAGE'); ?></span>
                                        <?php echo $this->form->getInput('datimage'); ?>
                                    </div>
                                    <div class="jem-editevent-image-choice jem-editevent-image-upload">
                                        <span><?php echo Text::_('COM_JEM_UPLOAD_NEW_IMAGE'); ?></span>
                                    <?php echo $this->form->getInput('userfile'); ?>
                                    </div>
                                    <button type="button" class="button3 btn btn-secondary jem-editevent-image-clear" data-jem-image-select="jform_datimage" data-jem-image-file="jform_userfile"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
                                    <?php if ($this->item->datimage) : ?>
                                        <button type="button" class="button3 btn btn-secondary jem-editevent-image-remove" onclick="document.getElementById('removeimage').value = '1'; var preview = this.closest('.jem-editevent-image-field').querySelector('.jem-editevent-image-preview'); if (preview) preview.style.display = 'none'; this.style.display = 'none';">
                                            <?php echo Text::_('COM_JEM_REMOVE_IMAGE'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <input type="hidden" name="removeimage" id="removeimage" value="0" />
                                </div>
                            </div>
                            <div class="jem-editevent-image-field">
                                <div class="jem-editevent-image-copy">
                                    <strong><?php echo Text::_('COM_JEM_EVENT_FULLIMAGE'); ?></strong>
                                    <span><?php echo Text::_('COM_JEM_EVENT_FULLIMAGE_FE_DESC'); ?></span>
                                    <small class="jem-editevent-image-maxsize"><?php echo Text::_('COM_JEM_MAXIMUM_UPLOAD_SIZE'); ?> <strong><?php echo $uploadLimit; ?></strong></small>
                                </div>
                                <div class="jem-editevent-image-control">
                                    <?php if (!empty($this->item->fullimage)) : ?>
                                        <div class="jem-editevent-image-preview jem-editevent-image-preview--detail">
                                            <?php echo JEMOutput::flyer($this->item, $this->dfullimage, 'event', 'fullimage'); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="jem-editevent-image-choice">
                                        <span><?php echo Text::_('COM_JEM_SERVER_IMAGE'); ?></span>
                                        <?php echo $this->form->getInput('fullimage'); ?>
                                    </div>
                                    <div class="jem-editevent-image-choice jem-editevent-image-upload">
                                        <span><?php echo Text::_('COM_JEM_UPLOAD_NEW_IMAGE'); ?></span>
                                    <?php echo $this->form->getInput('fulluserfile'); ?>
                                    </div>
                                    <button type="button" class="button3 btn btn-secondary jem-editevent-image-clear" data-jem-image-select="jform_fullimage" data-jem-image-file="jform_fulluserfile"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
                                    <?php if (!empty($this->item->fullimage)) : ?>
                                        <button type="button" class="button3 btn btn-secondary jem-editevent-image-remove" onclick="document.getElementById('removefullimage').value = '1'; var preview = this.closest('.jem-editevent-image-field').querySelector('.jem-editevent-image-preview'); if (preview) preview.style.display = 'none'; this.style.display = 'none';">
                                            <?php echo Text::_('COM_JEM_REMOVE_IMAGE'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <input type="hidden" name="removefullimage" id="removefullimage" value="0" />
                                    <div class="jem-editevent-image-choice jem-editevent-image-layout-choice">
                                        <span class="jem-editevent-image-layout-copy">
                                            <?php echo Text::_('COM_JEM_EVENT_FULLIMAGE_LAYOUT'); ?>
                                        </span>
                                        <?php echo $this->form->getInput('fullimage_layout'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </fieldset>
            <?php endif; ?>

            <!-- EXTENDED TAB -->
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'editevent-extendedtab', Text::_('COM_JEM_EDITEVENT_EXTENDED_TAB')); ?>
            <?php //echo HTMLHelper::_('tabs.panel', Text::_('COM_JEM_EDITEVENT_EXTENDED_TAB'), 'editevent-extendedtab'); ?>
            <?php echo $this->loadTemplate('extended'); ?>

            <!-- ADVANCED TAB -->
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'editevent-advancedtab', Text::_('COM_JEM_ADVANCED')); ?>
            <?php //echo HTMLHelper::_('tabs.panel', Text::_('COM_JEM_ADVANCED'), 'editevent-advancedtab'); ?>
            <?php echo $this->loadTemplate('publish'); ?>

            <!-- ATTACHMENTS TAB -->
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
            <?php if (!empty($this->item->attachments) || ($this->jemsettings->attachmentenabled != 0)) : ?>
                <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'event-attachments', Text::_('COM_JEM_EVENT_ATTACHMENTS_TAB')); ?>
                <?php //echo HTMLHelper::_('tabs.panel', Text::_('COM_JEM_EVENT_ATTACHMENTS_TAB'), 'event-attachments'); ?>
                <?php echo $this->loadTemplate('attachments'); ?>
                <?php echo HTMLHelper::_('uitab.endTab'); ?>
            <?php endif; ?>

            <!-- LINKS TAB -->
            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'event-links', Text::_('COM_JEM_EVENT_LINKS_TAB')); ?>
            <?php echo $this->loadTemplate('links'); ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>

            <!-- OTHER TAB -->
            <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'event-other', Text::_('COM_JEM_EVENT_OTHER_TAB')); ?>
            <?php //echo HTMLHelper::_('tabs.panel', Text::_('COM_JEM_EVENT_OTHER_TAB'), 'event-other'); ?>
            <?php echo $this->loadTemplate('other'); ?>
            <?php echo HTMLHelper::_('uitab.endTab'); ?>
            <?php //echo HTMLHelper::_('tabs.end'); ?>

            <input type="hidden" name="task" value="" />
            <input type="hidden" name="return" value="<?php echo $this->return_page; ?>" />
            <input type="hidden" name="author_ip" value="<?php echo $this->item->author_ip; ?>" />
            <?php if ($this->params->get('enable_category', 0) == 1) : ?>
                <input type="hidden" name="jform[catid]" value="<?php echo $this->params->get('catid', 1); ?>" />
            <?php endif; ?>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    </div>

        <?php if ($this->params->get('showfootertext')) : ?>
        <div class="description no_space floattext">
            <?php echo $this->params->get('footertext'); ?>
        </div>
    <?php endif; ?>
    <div class="copyright">
        <?php echo JemOutput::footer(); ?>
    </div>
</div>
