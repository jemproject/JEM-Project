<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$behaviourFields = array(
    'module_status_ribbons',
    'module_status_ribbon_position',
    'module_status_ribbon_side_margin',
    'module_status_last_places_threshold',
    'module_status_new_days',
);
$statusRows = array(
    'cancelled'    => array('label' => 'COM_JEM_EVENT_STATUS_CANCELLED', 'background' => '#b3261ee6'),
    'postponed'    => array('label' => 'COM_JEM_EVENT_STATUS_POSTPONED', 'background' => '#b55b00e6'),
    'rescheduled'  => array('label' => 'COM_JEM_EVENT_STATUS_RESCHEDULED', 'background' => '#2456a5e6'),
    'moved_online' => array('label' => 'COM_JEM_EVENT_STATUS_MOVED_ONLINE', 'background' => '#247a3de6'),
    'preorder'     => array('label' => 'COM_JEM_EVENT_AVAILABILITY_PREORDER', 'background' => '#b55b00e6'),
    'soldout'      => array('label' => 'COM_JEM_EVENT_AVAILABILITY_SOLDOUT', 'background' => '#b3261ee6'),
    'waitinglist'  => array('label' => 'COM_JEM_EVENT_AVAILABILITY_WAITINGLIST', 'background' => '#b55b00e6'),
    'last_places'  => array('label' => 'COM_JEM_EVENT_AVAILABILITY_LAST_PLACES', 'background' => '#b55b00e6'),
    'new'          => array('label' => 'COM_JEM_EVENT_STATUS_NEW', 'background' => '#2456a5e6'),
    'open'         => array('label' => 'COM_JEM_EVENT_AVAILABILITY_OPEN', 'background' => '#247a3de6'),
);
?>

<style>
    .jem-module-status-settings {
        padding: 10px 1vw;
    }
    .jem-module-status-settings .options-form {
        margin: 0;
    }
    .jem-module-status-settings input[type="number"] {
        width: 8rem;
        max-width: 100%;
    }
    .jem-module-status-settings select {
        width: auto;
        max-width: 100%;
    }
    .jem-module-status-colors {
        margin-top: 1rem;
    }
    .jem-module-status-colors td,
    .jem-module-status-colors th {
        vertical-align: middle;
    }
    .jem-module-status-colors input[type="text"] {
        width: 8.5rem;
        max-width: 100%;
        font-family: var(--font-monospace, monospace);
    }
    .jem-module-status-preview {
        display: inline-block;
        min-width: 8rem;
        padding: .3rem .75rem;
        border-radius: .2rem;
        color: var(--jem-preview-color);
        background: var(--jem-preview-background);
        font-size: .75rem;
        font-weight: 700;
        line-height: 1.2;
        text-align: center;
        text-transform: uppercase;
    }
    @media (max-width: 767.98px) {
        .jem-module-status-colors th:nth-child(4),
        .jem-module-status-colors td:nth-child(4) {
            display: none;
        }
    }
</style>

<script>
(function () {
    'use strict';

    function initialiseModuleStatusPreviews() {
        document.querySelectorAll('[data-jem-module-status-preview]').forEach(function (preview) {
            var status = preview.dataset.jemModuleStatusPreview;
            var background = document.getElementById('jform_module_status_color_' + status + '_bg');
            var text = document.getElementById('jform_module_status_color_' + status + '_text');

            if (!background || !text) {
                return;
            }

            var update = function () {
                if (/^#[0-9a-f]{8}$/i.test(background.value)) {
                    preview.style.setProperty('--jem-preview-background', background.value);
                }
                if (/^#[0-9a-f]{6}$/i.test(text.value)) {
                    preview.style.setProperty('--jem-preview-color', text.value);
                }
            };

            background.addEventListener('input', update);
            text.addEventListener('input', update);
            update();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiseModuleStatusPreviews);
    } else {
        initialiseModuleStatusPreviews();
    }
}());
</script>

<div class="width-100 jem-module-status-settings">
    <fieldset class="options-form">
        <legend><?php echo Text::_('COM_JEM_MODULE_STATUS_SETTINGS'); ?></legend>
        <p class="text-muted"><?php echo Text::_('COM_JEM_MODULE_STATUS_PRIORITY_DESC'); ?></p>
        <ul class="adminformlist">
            <?php foreach ($behaviourFields as $fieldName) : ?>
                <?php $field = $this->form->getField($fieldName); ?>
                <?php if ($field) : ?>
                    <li><div class="label-form"><?php echo $field->renderField(); ?></div></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>

        <div class="table-responsive jem-module-status-colors">
            <h4><?php echo Text::_('COM_JEM_MODULE_STATUS_COLORS'); ?></h4>
            <p class="text-muted"><?php echo Text::_('COM_JEM_MODULE_STATUS_COLORS_DESC'); ?></p>
            <table class="table table-striped table-sm align-middle">
                <thead>
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_JEM_MODULE_STATUS_COLOR_STATUS'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_JEM_MODULE_STATUS_COLOR_BACKGROUND'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_JEM_MODULE_STATUS_COLOR_TEXT'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_JEM_MODULE_STATUS_COLOR_PREVIEW'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_JEM_MODULE_STATUS_ACTIVE'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statusRows as $status => $row) : ?>
                        <?php
                        $backgroundField = 'module_status_color_' . $status . '_bg';
                        $textField = 'module_status_color_' . $status . '_text';
                        $activeField = 'module_status_active_' . $status;
                        $background = trim((string) $this->form->getValue($backgroundField));
                        $textColor = trim((string) $this->form->getValue($textField));
                        $background = preg_match('/^#[0-9a-f]{8}$/i', $background) ? $background : $row['background'];
                        $textColor = preg_match('/^#[0-9a-f]{6}$/i', $textColor) ? $textColor : '#ffffff';
                        ?>
                        <tr>
                            <th scope="row"><?php echo Text::_($row['label']); ?></th>
                            <td>
                                <label class="visually-hidden" for="jform_<?php echo $this->escape($backgroundField); ?>">
                                    <?php echo Text::_('COM_JEM_MODULE_STATUS_COLOR_BACKGROUND') . ': ' . Text::_($row['label']); ?>
                                </label>
                                <?php echo $this->form->getInput($backgroundField); ?>
                            </td>
                            <td>
                                <label class="visually-hidden" for="jform_<?php echo $this->escape($textField); ?>">
                                    <?php echo Text::_('COM_JEM_MODULE_STATUS_COLOR_TEXT') . ': ' . Text::_($row['label']); ?>
                                </label>
                                <?php echo $this->form->getInput($textField); ?>
                            </td>
                            <td>
                                <span class="jem-module-status-preview"
                                      data-jem-module-status-preview="<?php echo $this->escape($status); ?>"
                                      style="--jem-preview-background: <?php echo $this->escape($background); ?>; --jem-preview-color: <?php echo $this->escape($textColor); ?>;">
                                    <?php echo Text::_($row['label']); ?>
                                </span>
                            </td>
                            <td><?php echo $this->form->getInput($activeField); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </fieldset>
</div>
