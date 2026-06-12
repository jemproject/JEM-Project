<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$languages = !empty($this->customFieldLanguages) ? $this->customFieldLanguages : array('en-GB');
$config = is_array($this->customFieldsConfig) ? $this->customFieldsConfig : array();
$legacyValues = JemCustomFields::getLegacyLanguageValues($languages);

$renderTable = function ($context) use ($languages, $config, $legacyValues) {
    $contextLabel = $context === 'event' ? Text::_('COM_JEM_EVENTS') : Text::_('COM_JEM_VENUES');
    ?>
    <div class="d-flex justify-content-end mb-2">
        <button type="button"
                class="btn btn-outline-secondary btn-sm jem-custom-fields-load-language"
                data-context="<?php echo htmlspecialchars($context, ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo Text::_('COM_JEM_CUSTOM_FIELDS_LOAD_LANGUAGE'); ?>
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle jem-custom-fields-settings"
               style="--jem-custom-field-language-count: <?php echo max(1, count($languages)); ?>;">
            <caption class="visually-hidden"><?php echo htmlspecialchars($contextLabel, ENT_QUOTES, 'UTF-8'); ?></caption>
            <colgroup>
                <col class="jem-custom-fields-slot-col">
                <?php foreach (array('enabled', 'show_backend', 'show_frontend_edit', 'show_detail', 'hide_empty') as $flag) : ?>
                    <col class="jem-custom-fields-flag-col">
                <?php endforeach; ?>
                <?php foreach ($languages as $language) : ?>
                    <col class="jem-custom-fields-label-col">
                    <col class="jem-custom-fields-description-col">
                <?php endforeach; ?>
            </colgroup>
            <thead>
                <tr>
                    <th scope="col"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_SLOT'); ?></th>
                    <th scope="col" class="jem-custom-fields-flag" title="<?php echo Text::_('JENABLED'); ?>"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_ENABLED_SHORT'); ?></th>
                    <th scope="col" class="jem-custom-fields-flag" title="<?php echo Text::_('COM_JEM_CUSTOM_FIELD_SHOW_BACKEND'); ?>"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_BACKEND_SHORT'); ?></th>
                    <th scope="col" class="jem-custom-fields-flag" title="<?php echo Text::_('COM_JEM_CUSTOM_FIELD_SHOW_FRONTEND_EDIT'); ?>"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_FRONTEND_SHORT'); ?></th>
                    <th scope="col" class="jem-custom-fields-flag" title="<?php echo Text::_('COM_JEM_CUSTOM_FIELD_SHOW_DETAIL'); ?>"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_DETAIL_SHORT'); ?></th>
                    <th scope="col" class="jem-custom-fields-flag" title="<?php echo Text::_('COM_JEM_CUSTOM_FIELD_HIDE_EMPTY'); ?>"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_EMPTY_SHORT'); ?></th>
                    <?php foreach ($languages as $language) : ?>
                        <th scope="col" class="jem-custom-fields-label"><?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?> <?php echo Text::_('COM_JEM_CUSTOM_FIELD_LABEL'); ?></th>
                        <th scope="col" class="jem-custom-fields-description"><?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?> <?php echo Text::_('COM_JEM_CUSTOM_FIELD_DESCRIPTION'); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 1; $i <= 10; $i++) :
                    $field = 'custom' . $i;
                    $fieldConfig = isset($config[$context][$field]) && is_array($config[$context][$field]) ? $config[$context][$field] : array();
                    $fieldConfig = array_replace(array(
                        'enabled'            => 1,
                        'show_backend'       => 1,
                        'show_frontend_edit' => 1,
                        'show_detail'        => 1,
                        'hide_empty'         => 1,
                        'labels'             => array(),
                        'descriptions'       => array(),
                    ), $fieldConfig);
                    ?>
                    <tr>
                        <th scope="row"><?php echo htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?></th>
                        <?php foreach (array('enabled', 'show_backend', 'show_frontend_edit', 'show_detail', 'hide_empty') as $flag) : ?>
                            <td class="jem-custom-fields-flag">
                                <input type="checkbox"
                                       name="jem_custom_fields[<?php echo $context; ?>][<?php echo $field; ?>][<?php echo $flag; ?>]"
                                       value="1"
                                       <?php echo !empty($fieldConfig[$flag]) ? 'checked' : ''; ?>>
                            </td>
                        <?php endforeach; ?>
                        <?php foreach ($languages as $language) : ?>
                            <td class="jem-custom-fields-label">
                                <input type="text"
                                       class="form-control form-control-sm"
                                       data-context="<?php echo $context; ?>"
                                       data-field="<?php echo $field; ?>"
                                       data-kind="labels"
                                       data-language="<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>"
                                       name="jem_custom_fields[<?php echo $context; ?>][<?php echo $field; ?>][labels][<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>]"
                                       value="<?php echo htmlspecialchars($fieldConfig['labels'][$language] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </td>
                            <td class="jem-custom-fields-description">
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
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <?php
};
?>

<style>
    .jem-custom-fields-settings .jem-custom-fields-flag {
        text-align: center;
        vertical-align: middle;
        white-space: normal;
    }

    .jem-custom-fields-settings td.jem-custom-fields-flag {
        padding-left: .2rem;
        padding-right: .2rem;
    }

    .jem-custom-fields-settings .jem-custom-fields-slot-col,
    .jem-custom-fields-settings th:first-child,
    .jem-custom-fields-settings td:first-child {
        width: 4.75rem;
        min-width: 4.75rem;
    }

    .jem-custom-fields-settings .jem-custom-fields-flag-col,
    .jem-custom-fields-settings .jem-custom-fields-flag {
        width: 2.85rem;
        min-width: 2.85rem;
        max-width: 2.85rem;
    }

    .jem-custom-fields-settings .jem-custom-fields-label-col,
    .jem-custom-fields-settings th.jem-custom-fields-label,
    .jem-custom-fields-settings td.jem-custom-fields-label {
        width: 8rem;
        min-width: 8rem;
    }

    .jem-custom-fields-settings .jem-custom-fields-description-col,
    .jem-custom-fields-settings th.jem-custom-fields-description,
    .jem-custom-fields-settings td.jem-custom-fields-description {
        width: 16rem;
        min-width: 16rem;
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

    .jem-custom-fields-settings {
        width: 100%;
        min-width: max(100%, calc(19rem + (var(--jem-custom-field-language-count) * 24rem)));
        table-layout: fixed;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var legacyValues = <?php echo json_encode($legacyValues, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

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
</div>

<?php echo HTMLHelper::_('uitab.startTabSet', 'custom-fields-pane', array('active' => 'custom-fields-events', 'recall' => true, 'breakpoint' => 768)); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'custom-fields-pane', 'custom-fields-events', Text::_('COM_JEM_EVENTS')); ?>
    <?php $renderTable('event'); ?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.addTab', 'custom-fields-pane', 'custom-fields-venues', Text::_('COM_JEM_VENUES')); ?>
    <?php $renderTable('venue'); ?>
<?php echo HTMLHelper::_('uitab.endTab'); ?>

<?php echo HTMLHelper::_('uitab.endTabSet'); ?>
