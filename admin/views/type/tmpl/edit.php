<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

HTMLHelper::_('behavior.formvalidator');

$typeAttribs = json_decode((string) ($this->item->attribs ?? ''), true);
if (!is_array($typeAttribs)) {
    $typeAttribs = array();
}

$showDatesDefault = (int) ($typeAttribs['show_dates_default'] ?? 1);
$blockEvents = (int) ($typeAttribs['block_events'] ?? 0);
?>

<form action="<?php echo Route::_('index.php?option=com_jem&layout=edit&id=' . (int) $this->item->id); ?>"
      method="post" name="adminForm" id="adminForm" class="form-validate">

    <div class="row">
        <div class="col-md-9">
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h3 mb-3">
                        <?php echo empty($this->item->id) ? Text::_('COM_JEM_ADD_TYPE') : Text::_('COM_JEM_TYPE_EDIT'); ?>
                    </h2>

                    <div class="mb-3">
                        <?php echo $this->form->getLabel('name'); ?>
                        <?php echo $this->form->getInput('name'); ?>
                    </div>

                    <div class="mb-3">
                        <?php echo $this->form->getLabel('description'); ?>
                        <?php echo $this->form->getInput('description'); ?>
                    </div>

                    <?php echo $this->form->getInput('base_language'); ?>
                    <?php echo $this->form->getInput('translation_languages'); ?>
                    <?php echo $this->form->getInput('translations'); ?>

                    <?php if (!empty($this->typeLanguages)) : ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                <?php echo Text::_('COM_JEM_TYPE_TRANSLATIONS'); ?>
                            </div>
                            <div class="card-body">
                                <p class="form-text">
                                    <?php echo Text::_('COM_JEM_TYPE_TRANSLATIONS_DESC'); ?>
                                </p>
                                <?php foreach ($this->typeLanguages as $language) : ?>
                                    <?php
                                    $languageCode = (string) $language->code;
                                    $translation = isset($this->typeTranslations[$languageCode]) && is_array($this->typeTranslations[$languageCode])
                                        ? $this->typeTranslations[$languageCode]
                                        : array();
                                    $translatedName = isset($translation['name']) ? (string) $translation['name'] : '';
                                    $translatedDescription = isset($translation['description']) ? (string) $translation['description'] : '';
                                    ?>
                                    <div class="jem-type-translation border rounded p-3 mb-3"<?php echo $language->is_default ? '' : ' data-language="' . htmlspecialchars($languageCode, ENT_QUOTES, 'UTF-8') . '"'; ?>>
                                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                            <strong>
                                                <?php echo htmlspecialchars($language->title, ENT_QUOTES, 'UTF-8'); ?>
                                                <span class="text-muted">(<?php echo htmlspecialchars($languageCode, ENT_QUOTES, 'UTF-8'); ?>)</span>
                                            </strong>
                                            <?php if ($language->is_default) : ?>
                                                <span class="badge bg-primary"><?php echo Text::_('COM_JEM_TYPE_TRANSLATION_BASE_LANGUAGE'); ?></span>
                                            <?php elseif (!$language->is_active) : ?>
                                                <span class="badge bg-secondary"><?php echo Text::_('COM_JEM_TYPE_TRANSLATION_INACTIVE_LANGUAGE'); ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($language->is_default) : ?>
                                            <p class="form-text mb-0">
                                                <?php echo Text::_('COM_JEM_TYPE_TRANSLATION_BASE_LANGUAGE_DESC'); ?>
                                            </p>
                                        <?php else : ?>
                                            <div class="mb-2">
                                                <label class="form-label" for="jem-type-translation-name-<?php echo htmlspecialchars($languageCode, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo Text::_('COM_JEM_TYPE_TRANSLATION_NAME'); ?>
                                                </label>
                                                <input type="text"
                                                       id="jem-type-translation-name-<?php echo htmlspecialchars($languageCode, ENT_QUOTES, 'UTF-8'); ?>"
                                                       class="form-control jem-type-translation-name"
                                                       value="<?php echo htmlspecialchars($translatedName, ENT_QUOTES, 'UTF-8'); ?>"
                                                       maxlength="100">
                                            </div>
                                            <div>
                                                <label class="form-label" for="jem-type-translation-description-<?php echo htmlspecialchars($languageCode, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <?php echo Text::_('COM_JEM_TYPE_TRANSLATION_DESCRIPTION'); ?>
                                                </label>
                                                <textarea id="jem-type-translation-description-<?php echo htmlspecialchars($languageCode, ENT_QUOTES, 'UTF-8'); ?>"
                                                          class="form-control jem-type-translation-description"
                                                          rows="3"><?php echo htmlspecialchars($translatedDescription, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="jem-type-visual-fields">
                        <div class="mb-3 jem-type-icon-field">
                            <?php echo $this->form->getLabel('icon'); ?>
                            <div class="input-group">
                                <select id="jem-icon-style" class="form-select flex-grow-0" style="width:auto" title="<?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY'); ?>">
                                    <option value="all"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY_ALL'); ?></option>
                                    <option value="events"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY_EVENTS'); ?></option>
                                    <option value="places"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY_PLACES'); ?></option>
                                    <option value="people"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY_PEOPLE'); ?></option>
                                    <option value="media"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY_MEDIA'); ?></option>
                                    <option value="commerce"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY_COMMERCE'); ?></option>
                                    <option value="transport"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY_TRANSPORT'); ?></option>
                                    <option value="nature"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY_NATURE'); ?></option>
                                    <option value="food"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY_FOOD'); ?></option>
                                    <option value="health"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY_HEALTH'); ?></option>
                                    <option value="education"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY_EDUCATION'); ?></option>
                                    <option value="sports"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY_SPORTS'); ?></option>
                                    <option value="technology"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY_TECHNOLOGY'); ?></option>
                                    <option value="animals"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY_ANIMALS'); ?></option>
                                    <option value="interface"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY_INTERFACE'); ?></option>
                                    <option value="social"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY_SOCIAL'); ?></option>
                                    <option value="other"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_CATEGORY_OTHER'); ?></option>
                                    <option value="fa-solid"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_STYLE_SOLID'); ?></option>
                                    <option value="fa-regular"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_STYLE_REGULAR'); ?></option>
                                    <option value="fa-brands"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_STYLE_BRANDS'); ?></option>
                                </select>
                                <?php echo $this->form->getInput('icon'); ?>
                                <button type="button" id="jem-icon-search" class="btn btn-primary" title="<?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_SEARCH'); ?>">
                                    <span class="icon-search" aria-hidden="true"></span>
                                    <span class="visually-hidden"><?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_SEARCH'); ?></span>
                                </button>
                            </div>
                            <datalist id="jem-icon-list-all"></datalist>
                            <datalist id="jem-icon-list-fa-solid">
                                <option value="fa-solid fa-address-book">
                                <option value="fa-solid fa-address-card">
                                <option value="fa-solid fa-arrow-down">
                                <option value="fa-solid fa-arrow-left">
                                <option value="fa-solid fa-arrow-right">
                                <option value="fa-solid fa-arrow-up">
                                <option value="fa-solid fa-asterisk">
                                <option value="fa-solid fa-ban">
                                <option value="fa-solid fa-bars">
                                <option value="fa-solid fa-bell">
                                <option value="fa-solid fa-bolt">
                                <option value="fa-solid fa-bookmark">
                                <option value="fa-solid fa-briefcase">
                                <option value="fa-solid fa-building">
                                <option value="fa-solid fa-bullhorn">
                                <option value="fa-solid fa-calendar">
                                <option value="fa-solid fa-calendar-check">
                                <option value="fa-solid fa-calendar-days">
                                <option value="fa-solid fa-calendar-plus">
                                <option value="fa-solid fa-camera">
                                <option value="fa-solid fa-cart-shopping">
                                <option value="fa-solid fa-certificate">
                                <option value="fa-solid fa-chart-bar">
                                <option value="fa-solid fa-chart-pie">
                                <option value="fa-solid fa-check">
                                <option value="fa-solid fa-circle-check">
                                <option value="fa-solid fa-circle-info">
                                <option value="fa-solid fa-circle-minus">
                                <option value="fa-solid fa-circle-plus">
                                <option value="fa-solid fa-circle-question">
                                <option value="fa-solid fa-circle-xmark">
                                <option value="fa-solid fa-clock">
                                <option value="fa-solid fa-cloud">
                                <option value="fa-solid fa-code">
                                <option value="fa-solid fa-comment">
                                <option value="fa-solid fa-compass">
                                <option value="fa-solid fa-copy">
                                <option value="fa-solid fa-database">
                                <option value="fa-solid fa-download">
                                <option value="fa-solid fa-earth-americas">
                                <option value="fa-solid fa-envelope">
                                <option value="fa-solid fa-eye">
                                <option value="fa-solid fa-eye-slash">
                                <option value="fa-solid fa-file">
                                <option value="fa-solid fa-file-lines">
                                <option value="fa-solid fa-filter">
                                <option value="fa-solid fa-flag">
                                <option value="fa-solid fa-floppy-disk">
                                <option value="fa-solid fa-folder">
                                <option value="fa-solid fa-folder-open">
                                <option value="fa-solid fa-forward">
                                <option value="fa-solid fa-gear">
                                <option value="fa-solid fa-gears">
                                <option value="fa-solid fa-gift">
                                <option value="fa-solid fa-globe">
                                <option value="fa-solid fa-graduation-cap">
                                <option value="fa-solid fa-heart">
                                <option value="fa-solid fa-house">
                                <option value="fa-solid fa-image">
                                <option value="fa-solid fa-inbox">
                                <option value="fa-solid fa-info">
                                <option value="fa-solid fa-key">
                                <option value="fa-solid fa-laptop">
                                <option value="fa-solid fa-layer-group">
                                <option value="fa-solid fa-leaf">
                                <option value="fa-solid fa-link">
                                <option value="fa-solid fa-list">
                                <option value="fa-solid fa-location-dot">
                                <option value="fa-solid fa-location-pin">
                                <option value="fa-solid fa-lock">
                                <option value="fa-solid fa-magnifying-glass">
                                <option value="fa-solid fa-map">
                                <option value="fa-solid fa-map-pin">
                                <option value="fa-solid fa-minus">
                                <option value="fa-solid fa-mobile">
                                <option value="fa-solid fa-music">
                                <option value="fa-solid fa-paperclip">
                                <option value="fa-solid fa-pen">
                                <option value="fa-solid fa-pen-to-square">
                                <option value="fa-solid fa-phone">
                                <option value="fa-solid fa-play">
                                <option value="fa-solid fa-plus">
                                <option value="fa-solid fa-power-off">
                                <option value="fa-solid fa-print">
                                <option value="fa-solid fa-puzzle-piece">
                                <option value="fa-solid fa-recycle">
                                <option value="fa-solid fa-rotate">
                                <option value="fa-solid fa-share">
                                <option value="fa-solid fa-shield">
                                <option value="fa-solid fa-signal">
                                <option value="fa-solid fa-sitemap">
                                <option value="fa-solid fa-sliders">
                                <option value="fa-solid fa-square-check">
                                <option value="fa-solid fa-star">
                                <option value="fa-solid fa-stop">
                                <option value="fa-solid fa-tag">
                                <option value="fa-solid fa-tags">
                                <option value="fa-solid fa-thumbs-down">
                                <option value="fa-solid fa-thumbs-up">
                                <option value="fa-solid fa-ticket">
                                <option value="fa-solid fa-trash">
                                <option value="fa-solid fa-trophy">
                                <option value="fa-solid fa-triangle-exclamation">
                                <option value="fa-solid fa-unlock">
                                <option value="fa-solid fa-upload">
                                <option value="fa-solid fa-user">
                                <option value="fa-solid fa-user-group">
                                <option value="fa-solid fa-user-plus">
                                <option value="fa-solid fa-users">
                                <option value="fa-solid fa-video">
                                <option value="fa-solid fa-wand-magic-sparkles">
                                <option value="fa-solid fa-wrench">
                                <option value="fa-solid fa-xmark">
                            </datalist>
                            <datalist id="jem-icon-list-fa-regular">
                                <option value="fa-regular fa-address-book">
                                <option value="fa-regular fa-address-card">
                                <option value="fa-regular fa-bell">
                                <option value="fa-regular fa-bookmark">
                                <option value="fa-regular fa-calendar">
                                <option value="fa-regular fa-calendar-check">
                                <option value="fa-regular fa-calendar-days">
                                <option value="fa-regular fa-calendar-plus">
                                <option value="fa-regular fa-chart-bar">
                                <option value="fa-regular fa-circle-check">
                                <option value="fa-regular fa-circle-dot">
                                <option value="fa-regular fa-circle-question">
                                <option value="fa-regular fa-circle-xmark">
                                <option value="fa-regular fa-clock">
                                <option value="fa-regular fa-comment">
                                <option value="fa-regular fa-copy">
                                <option value="fa-regular fa-envelope">
                                <option value="fa-regular fa-eye">
                                <option value="fa-regular fa-eye-slash">
                                <option value="fa-regular fa-face-smile">
                                <option value="fa-regular fa-file">
                                <option value="fa-regular fa-file-lines">
                                <option value="fa-regular fa-flag">
                                <option value="fa-regular fa-floppy-disk">
                                <option value="fa-regular fa-folder">
                                <option value="fa-regular fa-folder-open">
                                <option value="fa-regular fa-heart">
                                <option value="fa-regular fa-image">
                                <option value="fa-regular fa-images">
                                <option value="fa-regular fa-lightbulb">
                                <option value="fa-regular fa-map">
                                <option value="fa-regular fa-pen-to-square">
                                <option value="fa-regular fa-square-check">
                                <option value="fa-regular fa-star">
                                <option value="fa-regular fa-thumbs-down">
                                <option value="fa-regular fa-thumbs-up">
                                <option value="fa-regular fa-trash-can">
                                <option value="fa-regular fa-user">
                            </datalist>
                            <datalist id="jem-icon-list-fa-brands">
                                <option value="fa-brands fa-facebook">
                                <option value="fa-brands fa-instagram">
                                <option value="fa-brands fa-linkedin">
                                <option value="fa-brands fa-tiktok">
                                <option value="fa-brands fa-twitter">
                                <option value="fa-brands fa-whatsapp">
                                <option value="fa-brands fa-x-twitter">
                                <option value="fa-brands fa-youtube">
                            </datalist>
                            <div id="jem-icon-picker" class="jem-icon-picker mt-2" aria-label="<?php echo Text::_('COM_JEM_TYPE_FIELD_ICON_PICKER'); ?>"></div>
                            <div id="jem-icon-preview" class="mt-2" style="min-height:2rem">
                                <?php if ($this->item->icon) : ?>
                                    <span id="jem-icon-glyph"
                                          class="<?php echo htmlspecialchars($this->item->icon, ENT_QUOTES, 'UTF-8'); ?>"
                                          ></span>
                                    <small id="jem-icon-label" class="text-muted ms-2"><?php echo htmlspecialchars($this->item->icon, ENT_QUOTES, 'UTF-8'); ?></small>
                                <?php else : ?>
                                    <span id="jem-icon-glyph"></span>
                                    <small id="jem-icon-label" class="text-muted ms-2"></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3 jem-type-color-field">
                            <?php echo $this->form->getLabel('color'); ?>
                            <?php echo $this->form->getInput('color'); ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">

                    <div class="mb-3">
                        <?php echo $this->form->getLabel('entity'); ?>
                        <?php echo $this->form->getInput('entity'); ?>
                    </div>

                    <div class="mb-3 jem-type-day-settings" data-day-settings>
                        <h3 class="h5 mb-2"><?php echo Text::_('COM_JEM_TYPE_DAY_SETTINGS'); ?></h3>
                        <div class="mb-3">
                            <label for="jem_type_show_dates_default" class="form-label">
                                <?php echo Text::_('COM_JEM_TYPE_FIELD_SHOW_DATES_DEFAULT'); ?>
                            </label>
                            <select id="jem_type_show_dates_default" class="form-select" data-attrib-key="show_dates_default">
                                <option value="1"<?php echo $showDatesDefault ? ' selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
                                <option value="0"<?php echo !$showDatesDefault ? ' selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
                            </select>
                            <div class="form-text"><?php echo Text::_('COM_JEM_TYPE_FIELD_SHOW_DATES_DEFAULT_DESC'); ?></div>
                        </div>
                        <div>
                            <label for="jem_type_block_events" class="form-label">
                                <?php echo Text::_('COM_JEM_TYPE_FIELD_BLOCK_EVENTS'); ?>
                            </label>
                            <select id="jem_type_block_events" class="form-select" data-attrib-key="block_events">
                                <option value="0"<?php echo !$blockEvents ? ' selected' : ''; ?>><?php echo Text::_('JNO'); ?></option>
                                <option value="1"<?php echo $blockEvents ? ' selected' : ''; ?>><?php echo Text::_('JYES'); ?></option>
                            </select>
                            <div class="form-text"><?php echo Text::_('COM_JEM_TYPE_FIELD_BLOCK_EVENTS_DESC'); ?></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <?php echo $this->form->getLabel('published'); ?>
                        <?php echo $this->form->getInput('published'); ?>
                    </div>

                    <div class="mb-3">
                        <?php echo $this->form->getLabel('access'); ?>
                        <?php echo $this->form->getInput('access'); ?>
                    </div>

                    <div class="mb-3">
                        <?php echo $this->form->getLabel('language'); ?>
                        <?php echo $this->form->getInput('language'); ?>
                    </div>

                    <div class="mb-3">
                        <?php echo $this->form->getLabel('alias'); ?>
                        <?php echo $this->form->getInput('alias'); ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <?php echo $this->form->getInput('id'); ?>
    <?php echo $this->form->getInput('ordering'); ?>
    <?php echo $this->form->getInput('attribs'); ?>

    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
<style>
    .jem-icon-picker {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(3.75rem, 1fr));
        gap: .5rem;
        max-height: 21rem;
        overflow: auto;
        padding: .5rem;
        border: 1px solid var(--border-color, #dfe3e7);
        border-radius: .25rem;
        background: var(--body-bg, #fff);
    }

    .jem-icon-option {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 3.75rem;
        height: 3.75rem;
        padding: 0;
        border: 1px solid var(--border-color, #dfe3e7);
        border-radius: .25rem;
        background: var(--body-bg, #fff);
        color: inherit;
    }

    .jem-icon-option > span {
        font-size: 1.5rem;
    }

    #jem-icon-preview {
        display: flex;
        align-items: center;
        min-height: 3rem !important;
    }

    #jem-icon-glyph {
        font-size: 2.25rem;
        min-width: 2.25rem;
        text-align: center;
    }

    .jem-type-color-field .field-media-wrapper,
    .jem-type-color-field .minicolors,
    .jem-type-color-field .minicolors-input {
        width: 100%;
    }

    .jem-icon-option:hover,
    .jem-icon-option:focus {
        border-color: var(--template-link-color, #2a69b8);
        color: var(--template-link-color, #2a69b8);
    }

    .jem-icon-option.is-selected {
        border-color: var(--template-link-color, #2a69b8);
        background: var(--template-link-color, #2a69b8);
        color: #fff;
    }

    .jem-type-day-settings[hidden] {
        display: none !important;
    }
</style>
<script>
(function () {
    var form = document.getElementById('adminForm');
    var translationsInput = document.getElementById('jform_translations');
    var languagesInput = document.getElementById('jform_translation_languages');

    if (!form || !translationsInput || !languagesInput) { return; }

    function collectTypeTranslations() {
        var translations = {};
        var languages = [];

        document.querySelectorAll('.jem-type-translation[data-language]').forEach(function (container) {
            var language = container.getAttribute('data-language');
            var nameInput = container.querySelector('.jem-type-translation-name');
            var descriptionInput = container.querySelector('.jem-type-translation-description');
            var name = nameInput ? nameInput.value.trim() : '';
            var description = descriptionInput ? descriptionInput.value.trim() : '';

            if (!language || (name === '' && description === '')) {
                return;
            }

            translations[language] = {
                name: name,
                description: description
            };
            languages.push(language);
        });

        translationsInput.value = JSON.stringify(translations);
        languagesInput.value = languages.join(',');
    }

    form.addEventListener('submit', collectTypeTranslations);
    document.querySelectorAll('.jem-type-translation-name, .jem-type-translation-description').forEach(function (field) {
        field.addEventListener('input', collectTypeTranslations);
        field.addEventListener('change', collectTypeTranslations);
    });
    collectTypeTranslations();
})();

(function () {
    var form = document.getElementById('adminForm');
    var entity = document.getElementById('jform_entity');
    var attribsInput = document.getElementById('jform_attribs');
    var daySettings = document.querySelector('[data-day-settings]');
    var fields = document.querySelectorAll('[data-attrib-key]');

    if (!form || !entity || !attribsInput || !daySettings) { return; }

    function parseAttribs() {
        try {
            var parsed = JSON.parse(attribsInput.value || '{}');
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function collectDayAttribs() {
        var attribs = parseAttribs();

        delete attribs.show_dates_default;
        delete attribs.block_events;

        if (String(entity.value) === '4') {
            fields.forEach(function (field) {
                attribs[field.getAttribute('data-attrib-key')] = field.value === '1' ? 1 : 0;
            });
        }

        attribsInput.value = Object.keys(attribs).length ? JSON.stringify(attribs) : '';
    }

    function toggleDaySettings() {
        daySettings.hidden = String(entity.value) !== '4';
        collectDayAttribs();
    }

    entity.addEventListener('change', toggleDaySettings);
    fields.forEach(function (field) {
        field.addEventListener('change', collectDayAttribs);
    });
    form.addEventListener('submit', collectDayAttribs);
    toggleDaySettings();
})();

(function () {
    var input   = document.querySelector('input[name="jform[icon]"]');
    var select  = document.getElementById('jem-icon-style');
    var picker  = document.getElementById('jem-icon-picker');
    var search  = document.getElementById('jem-icon-search');
    var allList = document.getElementById('jem-icon-list-all');
    var glyph   = document.getElementById('jem-icon-glyph');
    var label   = document.getElementById('jem-icon-label');
    var styles  = ['fa-solid', 'fa-regular', 'fa-brands'];
    var catalog = {
        events: [
            'fa-solid fa-calendar', 'fa-solid fa-calendar-day', 'fa-solid fa-calendar-days', 'fa-solid fa-calendar-week',
            'fa-solid fa-calendar-check', 'fa-solid fa-calendar-plus', 'fa-solid fa-calendar-minus', 'fa-solid fa-clock',
            'fa-solid fa-hourglass', 'fa-solid fa-stopwatch', 'fa-solid fa-ticket', 'fa-solid fa-ticket-simple',
            'fa-solid fa-music', 'fa-solid fa-microphone', 'fa-solid fa-guitar', 'fa-solid fa-drum',
            'fa-solid fa-champagne-glasses', 'fa-solid fa-masks-theater', 'fa-solid fa-film', 'fa-solid fa-clapperboard',
            'fa-solid fa-palette', 'fa-solid fa-paintbrush', 'fa-solid fa-camera', 'fa-solid fa-image',
            'fa-solid fa-book-open', 'fa-solid fa-graduation-cap', 'fa-solid fa-trophy', 'fa-solid fa-award',
            'fa-solid fa-medal', 'fa-solid fa-star', 'fa-solid fa-heart', 'fa-solid fa-cake-candles',
            'fa-solid fa-gift', 'fa-solid fa-utensils', 'fa-solid fa-mug-saucer', 'fa-solid fa-person-running',
            'fa-solid fa-person-swimming', 'fa-solid fa-futbol', 'fa-solid fa-basketball', 'fa-solid fa-volleyball',
            'fa-solid fa-dumbbell', 'fa-solid fa-gamepad', 'fa-solid fa-chess', 'fa-solid fa-dice'
        ],
        places: [
            'fa-solid fa-location-dot', 'fa-solid fa-location-pin', 'fa-solid fa-map-pin', 'fa-solid fa-map-location-dot',
            'fa-solid fa-map', 'fa-solid fa-compass', 'fa-solid fa-globe', 'fa-solid fa-earth-europe',
            'fa-solid fa-earth-americas', 'fa-solid fa-city', 'fa-solid fa-building', 'fa-solid fa-building-columns',
            'fa-solid fa-museum', 'fa-solid fa-landmark', 'fa-solid fa-monument', 'fa-solid fa-archway',
            'fa-solid fa-house', 'fa-solid fa-hotel', 'fa-solid fa-school', 'fa-solid fa-university',
            'fa-solid fa-church', 'fa-solid fa-mosque', 'fa-solid fa-synagogue', 'fa-solid fa-place-of-worship',
            'fa-solid fa-hospital', 'fa-solid fa-store', 'fa-solid fa-shop', 'fa-solid fa-warehouse',
            'fa-solid fa-tree', 'fa-solid fa-mountain-sun', 'fa-solid fa-umbrella-beach', 'fa-solid fa-campground',
            'fa-solid fa-road', 'fa-solid fa-bridge', 'fa-solid fa-door-open', 'fa-solid fa-signs-post',
            'fa-solid fa-faucet', 'fa-solid fa-faucet-drip', 'fa-solid fa-droplet', 'fa-solid fa-water'
        ],
        people: [
            'fa-solid fa-user', 'fa-solid fa-user-group', 'fa-solid fa-users', 'fa-solid fa-user-plus',
            'fa-solid fa-user-check', 'fa-solid fa-user-clock', 'fa-solid fa-user-gear', 'fa-solid fa-user-tie',
            'fa-solid fa-person', 'fa-solid fa-person-dress', 'fa-solid fa-children', 'fa-solid fa-child',
            'fa-solid fa-person-chalkboard', 'fa-solid fa-person-running', 'fa-solid fa-person-walking',
            'fa-solid fa-person-hiking', 'fa-solid fa-person-biking', 'fa-solid fa-person-skating',
            'fa-solid fa-hands-holding', 'fa-solid fa-handshake', 'fa-solid fa-hand-holding-heart',
            'fa-solid fa-hand-holding-medical', 'fa-solid fa-address-book', 'fa-solid fa-address-card',
            'fa-regular fa-user', 'fa-regular fa-address-book', 'fa-regular fa-address-card', 'fa-regular fa-face-smile'
        ],
        media: [
            'fa-solid fa-camera', 'fa-solid fa-camera-retro', 'fa-solid fa-video', 'fa-solid fa-film',
            'fa-solid fa-clapperboard', 'fa-solid fa-photo-film', 'fa-solid fa-image', 'fa-solid fa-images',
            'fa-solid fa-music', 'fa-solid fa-microphone', 'fa-solid fa-microphone-lines', 'fa-solid fa-headphones',
            'fa-solid fa-headset', 'fa-solid fa-volume-high', 'fa-solid fa-radio', 'fa-solid fa-podcast',
            'fa-solid fa-play', 'fa-solid fa-pause', 'fa-solid fa-stop', 'fa-solid fa-forward',
            'fa-solid fa-backward', 'fa-solid fa-record-vinyl', 'fa-solid fa-compact-disc', 'fa-solid fa-guitar',
            'fa-solid fa-drum', 'fa-regular fa-image', 'fa-regular fa-images', 'fa-regular fa-circle-play'
        ],
        commerce: [
            'fa-solid fa-cart-shopping', 'fa-solid fa-cart-plus', 'fa-solid fa-bag-shopping', 'fa-solid fa-basket-shopping',
            'fa-solid fa-store', 'fa-solid fa-shop', 'fa-solid fa-tags', 'fa-solid fa-tag',
            'fa-solid fa-receipt', 'fa-solid fa-credit-card', 'fa-solid fa-money-bill', 'fa-solid fa-money-bill-wave',
            'fa-solid fa-money-bills', 'fa-solid fa-coins', 'fa-solid fa-sack-dollar', 'fa-solid fa-dollar-sign',
            'fa-solid fa-euro-sign', 'fa-solid fa-sterling-sign', 'fa-solid fa-percent', 'fa-solid fa-barcode',
            'fa-solid fa-qrcode', 'fa-solid fa-box', 'fa-solid fa-box-open', 'fa-solid fa-truck-fast',
            'fa-regular fa-credit-card'
        ],
        transport: [
            'fa-solid fa-car', 'fa-solid fa-car-side', 'fa-solid fa-car-rear', 'fa-solid fa-taxi',
            'fa-solid fa-bus', 'fa-solid fa-train', 'fa-solid fa-train-subway', 'fa-solid fa-tram',
            'fa-solid fa-plane', 'fa-solid fa-plane-departure', 'fa-solid fa-plane-arrival', 'fa-solid fa-helicopter',
            'fa-solid fa-ship', 'fa-solid fa-ferry', 'fa-solid fa-bicycle', 'fa-solid fa-motorcycle',
            'fa-solid fa-truck', 'fa-solid fa-truck-fast', 'fa-solid fa-van-shuttle', 'fa-solid fa-route',
            'fa-solid fa-road', 'fa-solid fa-road-circle-check', 'fa-solid fa-traffic-light', 'fa-solid fa-gas-pump',
            'fa-solid fa-charging-station'
        ],
        nature: [
            'fa-solid fa-leaf', 'fa-solid fa-seedling', 'fa-solid fa-tree', 'fa-solid fa-tree-city',
            'fa-solid fa-mountain', 'fa-solid fa-mountain-sun', 'fa-solid fa-sun', 'fa-solid fa-moon',
            'fa-solid fa-cloud', 'fa-solid fa-cloud-rain', 'fa-solid fa-cloud-sun', 'fa-solid fa-snowflake',
            'fa-solid fa-water', 'fa-solid fa-droplet', 'fa-solid fa-fire', 'fa-solid fa-wind',
            'fa-solid fa-earth-europe', 'fa-solid fa-rainbow', 'fa-solid fa-umbrella-beach'
        ],
        food: [
            'fa-solid fa-utensils', 'fa-solid fa-burger', 'fa-solid fa-pizza-slice', 'fa-solid fa-hotdog',
            'fa-solid fa-ice-cream', 'fa-solid fa-cookie', 'fa-solid fa-cake-candles', 'fa-solid fa-apple-whole',
            'fa-solid fa-carrot', 'fa-solid fa-fish', 'fa-solid fa-mug-saucer', 'fa-solid fa-wine-glass',
            'fa-solid fa-wine-bottle', 'fa-solid fa-beer-mug-empty', 'fa-solid fa-champagne-glasses'
        ],
        health: [
            'fa-solid fa-hospital', 'fa-solid fa-house-medical', 'fa-solid fa-stethoscope', 'fa-solid fa-kit-medical',
            'fa-solid fa-user-doctor', 'fa-solid fa-user-nurse', 'fa-solid fa-pills', 'fa-solid fa-capsules',
            'fa-solid fa-syringe', 'fa-solid fa-heart-pulse', 'fa-solid fa-notes-medical', 'fa-solid fa-wheelchair',
            'fa-solid fa-prescription-bottle-medical', 'fa-solid fa-star-of-life'
        ],
        education: [
            'fa-solid fa-school', 'fa-solid fa-graduation-cap', 'fa-solid fa-book', 'fa-solid fa-book-open',
            'fa-solid fa-book-open-reader', 'fa-solid fa-building-columns', 'fa-solid fa-chalkboard-user',
            'fa-solid fa-person-chalkboard', 'fa-solid fa-pen', 'fa-solid fa-pencil', 'fa-solid fa-language',
            'fa-solid fa-flask', 'fa-solid fa-microscope'
        ],
        sports: [
            'fa-solid fa-futbol', 'fa-solid fa-basketball', 'fa-solid fa-volleyball', 'fa-solid fa-football',
            'fa-solid fa-baseball', 'fa-solid fa-bowling-ball', 'fa-solid fa-table-tennis-paddle-ball',
            'fa-solid fa-person-running', 'fa-solid fa-person-swimming', 'fa-solid fa-person-biking',
            'fa-solid fa-person-hiking', 'fa-solid fa-dumbbell', 'fa-solid fa-medal', 'fa-solid fa-trophy'
        ],
        technology: [
            'fa-solid fa-laptop', 'fa-solid fa-computer', 'fa-solid fa-display', 'fa-solid fa-mobile-screen',
            'fa-solid fa-tablet-screen-button', 'fa-solid fa-server', 'fa-solid fa-database', 'fa-solid fa-code',
            'fa-solid fa-terminal', 'fa-solid fa-wifi', 'fa-solid fa-microchip', 'fa-solid fa-robot',
            'fa-solid fa-satellite-dish', 'fa-solid fa-plug', 'fa-solid fa-power-off'
        ],
        animals: [
            'fa-solid fa-paw', 'fa-solid fa-dog', 'fa-solid fa-cat', 'fa-solid fa-horse',
            'fa-solid fa-fish', 'fa-solid fa-dove', 'fa-solid fa-kiwi-bird', 'fa-solid fa-hippo',
            'fa-solid fa-dragon', 'fa-solid fa-bug', 'fa-solid fa-worm'
        ],
        interface: [
            'fa-solid fa-check', 'fa-solid fa-xmark', 'fa-solid fa-plus', 'fa-solid fa-minus',
            'fa-solid fa-circle-check', 'fa-solid fa-circle-xmark', 'fa-solid fa-circle-info', 'fa-solid fa-circle-question',
            'fa-solid fa-triangle-exclamation', 'fa-solid fa-ban', 'fa-solid fa-lock', 'fa-solid fa-unlock',
            'fa-solid fa-eye', 'fa-solid fa-eye-slash', 'fa-solid fa-gear', 'fa-solid fa-gears',
            'fa-solid fa-sliders', 'fa-solid fa-filter', 'fa-solid fa-magnifying-glass', 'fa-solid fa-arrow-up',
            'fa-solid fa-arrow-down', 'fa-solid fa-arrow-left', 'fa-solid fa-arrow-right', 'fa-solid fa-rotate',
            'fa-solid fa-download', 'fa-solid fa-upload', 'fa-solid fa-print', 'fa-solid fa-share',
            'fa-solid fa-link', 'fa-solid fa-copy', 'fa-solid fa-trash', 'fa-solid fa-pen-to-square',
            'fa-solid fa-floppy-disk', 'fa-solid fa-bars', 'fa-regular fa-circle-check', 'fa-regular fa-circle-xmark',
            'fa-regular fa-circle-question', 'fa-regular fa-eye', 'fa-regular fa-eye-slash', 'fa-regular fa-pen-to-square'
        ],
        social: [
            'fa-brands fa-facebook', 'fa-brands fa-instagram', 'fa-brands fa-linkedin', 'fa-brands fa-tiktok',
            'fa-brands fa-twitter', 'fa-brands fa-x-twitter', 'fa-brands fa-whatsapp', 'fa-brands fa-youtube',
            'fa-brands fa-vimeo', 'fa-brands fa-soundcloud', 'fa-brands fa-spotify', 'fa-brands fa-flickr',
            'fa-brands fa-pinterest', 'fa-brands fa-reddit', 'fa-brands fa-telegram', 'fa-brands fa-discord',
            'fa-brands fa-github', 'fa-brands fa-gitlab', 'fa-brands fa-dribbble', 'fa-brands fa-behance',
            'fa-brands fa-wordpress', 'fa-brands fa-joomla'
        ]
    };

    var searchKeywords = {
        'fa-solid fa-faucet': 'fountain fuente water agua tap grifo',
        'fa-solid fa-faucet-drip': 'fountain fuente water agua tap grifo drip',
        'fa-solid fa-droplet': 'fountain fuente water agua drop gota',
        'fa-solid fa-water': 'fountain fuente water agua'
    };

    if (!input || !select || !picker || !search) { return; }

    function activeDatalist() {
        return 'jem-icon-list-' + (styles.indexOf(select.value) !== -1 ? select.value : 'all');
    }

    function detectStyle(val) {
        if (!val) { return ''; }
        var prefix = val.split(' ')[0];
        return styles.indexOf(prefix) !== -1 ? prefix : '';
    }

    function updatePreview(val) {
        glyph.className = val ? val : '';
        label.textContent = val;
    }

    function getListValues(style) {
        var list = document.getElementById('jem-icon-list-' + style);
        if (!list) { return []; }

        return Array.prototype.slice.call(list.querySelectorAll('option'))
            .map(function (option) { return option.value; })
            .filter(Boolean);
    }

    function getIconValues() {
        if (select.value === 'other') {
            var categorised = unique(Object.keys(catalog).reduce(function (values, category) {
                return values.concat(catalog[category]);
            }, []));
            var available = unique(styles.reduce(function (values, style) {
                return values.concat(getListValues(style));
            }, []));

            return available.filter(function (value) {
                return categorised.indexOf(value) === -1;
            });
        }

        if (catalog[select.value]) {
            return unique(catalog[select.value]);
        }

        if (select.value !== 'all') {
            return getListValues(select.value);
        }

        return unique(Object.keys(catalog).reduce(function (values, category) {
            return values.concat(catalog[category]);
        }, []).concat(styles.reduce(function (values, style) {
            return values.concat(getListValues(style));
        }, [])));
    }

    function unique(values) {
        return values.filter(function (value, index, list) {
            return value && list.indexOf(value) === index;
        });
    }

    function buildAllDatalist() {
        if (!allList) { return; }

        allList.innerHTML = '';
        getIconValues().forEach(function (value) {
            var option = document.createElement('option');
            option.value = value;
            allList.appendChild(option);
        });
    }

    function markSelected(val) {
        picker.querySelectorAll('.jem-icon-option').forEach(function (button) {
            button.classList.toggle('is-selected', button.getAttribute('data-icon') === val);
        });
    }

    function setIcon(val) {
        input.value = val;
        updatePreview(val);
        markSelected(val);
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function normaliseSearch(value) {
        return String(value || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/fa-(solid|regular|brands)|fa-/g, ' ')
            .replace(/[^a-z0-9]+/g, ' ')
            .trim();
    }

    function renderPicker(searchTerm) {
        var current = input.value.trim();
        var query = normaliseSearch(searchTerm);
        picker.innerHTML = '';

        getIconValues().filter(function (value) {
            if (!query) {
                return true;
            }

            return normaliseSearch(value + ' ' + (searchKeywords[value] || '')).indexOf(query) !== -1;
        }).forEach(function (value) {
            var button = document.createElement('button');
            var icon = document.createElement('span');

            button.type = 'button';
            button.className = 'jem-icon-option';
            button.setAttribute('data-icon', value);
            button.setAttribute('title', value);
            button.setAttribute('aria-label', value);
            icon.className = value;

            button.appendChild(icon);
            button.addEventListener('click', function () {
                setIcon(value);
            });

            picker.appendChild(button);
        });

        markSelected(current);
    }

    // Init: keep the manual field searchable and render a visual picker.
    select.value = 'all';
    buildAllDatalist();
    input.setAttribute('list', activeDatalist());
    updatePreview(input.value.trim());
    renderPicker();

    select.addEventListener('change', function () {
        input.setAttribute('list', activeDatalist());
        renderPicker();
    });

    search.addEventListener('click', function () {
        renderPicker(input.value.trim());
    });

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            renderPicker(input.value.trim());
        }
    });

    input.addEventListener('input', function () {
        var val = this.value.trim();
        var style = detectStyle(val);

        if (style && select.value !== 'all' && select.value !== style) {
            select.value = style;
            input.setAttribute('list', activeDatalist());
            renderPicker();
        }

        updatePreview(val);
        markSelected(val);
    });
})();
</script>
