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

$document->addStyleDeclaration('
    .jem-associated-article-options {
        border: 0;
        margin: 0 !important;
        padding: 0 .5rem !important;
    }
    .jem-associated-article-options .adminformlist {
        margin-bottom: 0;
    }
    .jem-associated-article-options .adminformlist li {
        display: grid;
        grid-template-columns: minmax(14rem, 240px) minmax(0, 1fr);
        align-items: center;
        column-gap: 0;
        margin: .35rem 0;
    }
    .jem-associated-article-options .adminformlist label {
        margin-bottom: 0;
    }
    .jem-associated-article-options .alert {
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
    @media (max-width: 767.98px) {
        .jem-associated-article-options .adminformlist li {
            grid-template-columns: 1fr;
            row-gap: .25rem;
        }
        .jem-associated-article-options .alert {
            grid-column: 1;
        }
    }
');
?>

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
                document.getElementById('jform_unregistra_until').style.display = '';
                document.getElementById('jform_unregistra_until2').style.display = '';
            } else {
                document.getElementById('unregistra_until').style.display = 'none';
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
                document.getElementById('registra_until').style.display = '';
                document.getElementById('jform_registra_from').style.display = '';
                document.getElementById('jform_registra_from2').style.display = '';
                document.getElementById('jform_registra_until').style.display = '';
                document.getElementById('jform_registra_until2').style.display = '';
            } else {
                document.getElementById('registra_from').style.display = 'none';
                document.getElementById('registra_until').style.display = 'none';
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

            <fieldset>
                <legend><?php echo Text::_('COM_JEM_EDITEVENT_DETAILS_LEGEND'); ?></legend>
                <ul class="adminformlist">
                    <li><?php echo $this->form->getLabel('title'); ?><?php echo $this->form->getInput('title'); ?></li>
                    <?php if (is_null($this->item->id)):?>
                        <li><?php echo $this->form->getLabel('alias'); ?><?php echo $this->form->getInput('alias'); ?></li>
                    <?php endif; ?>
                    <li><?php echo $this->form->getLabel('dates'); ?><?php echo $this->form->getInput('dates'); ?></li>
                    <li><?php echo $this->form->getLabel('enddates'); ?><?php echo $this->form->getInput('enddates'); ?></li>
                    <li><?php echo $this->form->getLabel('times'); ?><?php echo $this->form->getInput('times'); ?></li>
                    <li><?php echo $this->form->getLabel('endtimes'); ?><?php echo $this->form->getInput('endtimes'); ?></li>
                    <?php if($this->jemsettings->defaultCategory && empty($this->item->id)) {
                        $this->form->setFieldAttribute('cats', 'default', $this->jemsettings->defaultCategory);
                    } ?>
                    <li><?php echo $this->form->getLabel('cats'); ?><?php echo $this->form->getInput('cats'); ?></li>
                    <?php if($this->jemsettings->defaultVenue && empty($this->item->id)) {
                        $this->form->setFieldAttribute('locid', 'default', $this->jemsettings->defaultVenue);
                    } ?>
                    <li><?php echo $this->form->getLabel('locid'); ?> <?php echo $this->form->getInput('locid'); ?></li>
                    <?php if ($showTypeField) : ?>
                        <li><?php echo $this->form->getLabel('type_id'); ?><?php echo $this->form->getInput('type_id'); ?></li>
                    <?php else : ?>
                        <?php echo $this->form->getInput('type_id'); ?>
                    <?php endif; ?>
                    <?php if ($showContactField) : ?>
                        <li><?php echo $this->form->getLabel('contactid'); ?> <?php echo $this->form->getInput('contactid'); ?></li>
                    <?php else : ?>
                        <?php echo $this->form->getInput('contactid'); ?>
                    <?php endif; ?>
                    <li><?php echo $this->form->getLabel('featured'); ?><?php echo $this->form->getInput('featured'); ?></li>
                </ul>
            </fieldset>
            <?php if ($this->form->getField('article_id')) : ?>
                <fieldset class="jem-associated-article-options" data-has-article="<?php echo !empty($this->item->article_id) ? 1 : 0; ?>">
                    <ul class="adminformlist">
                        <li class="js-jem-article-usage"><?php echo $this->form->getLabel('article_usage', 'attribs'); ?><?php echo $this->form->getInput('article_usage', 'attribs'); ?></li>
                        <li class="js-jem-article-selector">
                            <?php echo $this->form->getLabel('article_id'); ?>
                            <div class="jem-associated-article-picker">
                                <?php echo $this->form->getInput('article_id'); ?>
                            </div>
                            <input type="hidden" name="jform[create_article]" id="jform_create_article" value="<?php echo (int) $this->form->getValue('create_article'); ?>">
                            <div id="jem-article-auto-info" class="alert alert-info small mt-2 mb-0" hidden>
                                <?php echo $articleAutoInfo; ?>
                            </div>
                        </li>
                        <li class="js-jem-article-target"><?php echo $this->form->getLabel('article_target_category_id'); ?><?php echo $this->form->getInput('article_target_category_id'); ?></li>
                    </ul>
                </fieldset>
            <?php endif; ?>
            <!-- EVENTDESCRIPTION -->
            <fieldset>
                <legend><?php echo Text::_('COM_JEM_EVENT_DESCRIPTION'); ?></legend>
                <div class="clr"></div>
                <?php echo $this->form->getLabel('articletext'); ?>
                <div class="clr"><br></div>
                <?php echo $this->form->getInput('articletext'); ?>
            </fieldset>

            <!-- IMAGE -->
            <?php if ($this->item->datimage || $this->jemsettings->imageenabled != 0) : ?>
                <fieldset class="jem_fldst_image">
                    <legend><?php echo Text::_('COM_JEM_IMAGE'); ?></legend>
                    <?php
                    if ($this->item->datimage) :
                        echo JemOutput::flyer($this->item, $this->dimage, 'event', 'datimage');
                        ?><input type="hidden" name="datimage" id="datimage" value="<?php echo $this->item->datimage; ?>" /><?php
                    endif;
                    ?>
                    <?php if ($this->jemsettings->imageenabled != 0) : ?>
                        <ul class="adminformlist">
                            <li>
                                <?php /* We get field with id 'jform_userfile' and name 'jform[userfile]' */ ?>
                                <?php echo $this->form->getLabel('userfile'); ?> <?php echo $this->form->getInput('userfile'); ?>
                            </li>
                            <li>
                                <button type="button" class="button3" onclick="document.getElementById('jform_userfile').val() = ''"><?php echo Text::_('JSEARCH_FILTER_CLEAR') ?></button>
                            </li>
                            <?php
                            if ($this->item->datimage) :
                                echo HTMLHelper::image('media/com_jem/images/publish_r.webp', null, array('id' => 'userfile-remove', 'data-id' => $this->item->id, 'data-type' => 'events', 'title' => Text::_('COM_JEM_REMOVE_IMAGE')));

                            endif;
                            ?>
                            <input type="hidden" name="removeimage" id="removeimage" value="0" />

                        </ul>
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
