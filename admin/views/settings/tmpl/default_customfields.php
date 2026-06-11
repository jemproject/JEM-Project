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

$renderTable = function ($context) use ($languages, $config) {
    $contextLabel = $context === 'event' ? Text::_('COM_JEM_EVENTS') : Text::_('COM_JEM_VENUES');
    ?>
    <div class="table-responsive">
        <table class="table table-striped table-sm align-middle jem-custom-fields-settings">
            <caption class="visually-hidden"><?php echo htmlspecialchars($contextLabel, ENT_QUOTES, 'UTF-8'); ?></caption>
            <thead>
                <tr>
                    <th scope="col"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_SLOT'); ?></th>
                    <th scope="col" class="jem-custom-fields-flag" title="<?php echo Text::_('JENABLED'); ?>"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_ENABLED_SHORT'); ?></th>
                    <th scope="col" class="jem-custom-fields-flag" title="<?php echo Text::_('COM_JEM_CUSTOM_FIELD_SHOW_BACKEND'); ?>"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_BACKEND_SHORT'); ?></th>
                    <th scope="col" class="jem-custom-fields-flag" title="<?php echo Text::_('COM_JEM_CUSTOM_FIELD_SHOW_FRONTEND_EDIT'); ?>"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_FRONTEND_SHORT'); ?></th>
                    <th scope="col" class="jem-custom-fields-flag" title="<?php echo Text::_('COM_JEM_CUSTOM_FIELD_SHOW_DETAIL'); ?>"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_DETAIL_SHORT'); ?></th>
                    <th scope="col" class="jem-custom-fields-flag" title="<?php echo Text::_('COM_JEM_CUSTOM_FIELD_HIDE_EMPTY'); ?>"><?php echo Text::_('COM_JEM_CUSTOM_FIELD_EMPTY_SHORT'); ?></th>
                    <?php foreach ($languages as $language) : ?>
                        <th scope="col"><?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?> <?php echo Text::_('COM_JEM_CUSTOM_FIELD_LABEL'); ?></th>
                        <th scope="col"><?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?> <?php echo Text::_('COM_JEM_CUSTOM_FIELD_DESCRIPTION'); ?></th>
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
                            <td>
                                <input type="text"
                                       class="form-control form-control-sm"
                                       name="jem_custom_fields[<?php echo $context; ?>][<?php echo $field; ?>][labels][<?php echo htmlspecialchars($language, ENT_QUOTES, 'UTF-8'); ?>]"
                                       value="<?php echo htmlspecialchars($fieldConfig['labels'][$language] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                            </td>
                            <td>
                                <input type="text"
                                       class="form-control form-control-sm"
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
        width: 2.85rem;
        min-width: 2.85rem;
        max-width: 2.85rem;
        text-align: center;
        vertical-align: middle;
        white-space: normal;
    }

    .jem-custom-fields-settings td.jem-custom-fields-flag {
        padding-left: .2rem;
        padding-right: .2rem;
    }

    .jem-custom-fields-settings th:first-child,
    .jem-custom-fields-settings td:first-child {
        width: 4.75rem;
        min-width: 4.75rem;
    }

    .jem-custom-fields-settings th:not(.jem-custom-fields-flag):not(:first-child),
    .jem-custom-fields-settings td:not(.jem-custom-fields-flag):not(:first-child) {
        min-width: 11.5rem;
    }

    .jem-custom-fields-settings th,
    .jem-custom-fields-settings td {
        padding-left: .25rem;
        padding-right: .25rem;
    }

    .jem-custom-fields-settings thead th {
        text-align: center;
        vertical-align: middle;
    }

    .jem-custom-fields-settings .form-control-sm {
        min-width: 10.25rem;
        max-width: 10.75rem;
    }

    .jem-custom-fields-settings {
        width: max-content;
        min-width: 100%;
    }
</style>

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
