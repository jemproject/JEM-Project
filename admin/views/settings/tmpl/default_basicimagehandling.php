<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$gdv = JEMImage::gdVersion();
$profiles = array(
    array('prefix' => 'image_event_intro', 'title' => 'COM_JEM_IMAGE_PROFILE_EVENT_INTRO'),
    array('prefix' => 'image_event_full', 'title' => 'COM_JEM_IMAGE_PROFILE_EVENT_FULL'),
    array('prefix' => 'image_venue', 'title' => 'COM_JEM_IMAGE_PROFILE_VENUE'),
    array('prefix' => 'image_category', 'title' => 'COM_JEM_IMAGE_PROFILE_CATEGORY'),
);
$storageProfiles = array(
    array('prefix' => 'event_image_subfolder', 'title' => 'COM_JEM_EVENT_IMAGE_STORAGE'),
    array('prefix' => 'venue_image_subfolder', 'title' => 'COM_JEM_VENUE_IMAGE_STORAGE'),
    array('prefix' => 'category_image_subfolder', 'title' => 'COM_JEM_CATEGORY_IMAGE_STORAGE'),
);
$globalGroup = 'globalattribs';
?>

<style>
    .jem-image-settings-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        gap: 1rem;
        padding: 10px 1vw;
    }
    .jem-image-settings-column {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        min-width: 0;
    }
    .jem-image-settings-grid .options-form {
        min-width: 0;
        margin: 0;
    }
    .jem-image-settings-profile select {
        width: auto;
        max-width: 100%;
    }
    .jem-image-settings-profile input[id$="_custom_ratio"] {
        width: 8rem;
        max-width: 100%;
    }
    .jem-image-settings-profile input[id$="_default_dimension"] {
        width: 8rem;
        max-width: 100%;
    }
    .jem-image-settings-general input[id="jform_image_filetypes"] {
        width: 18rem;
        max-width: 100%;
    }
    .jem-image-settings-general input[id="jform_sizelimit"],
    .jem-image-settings-general input[id="jform_image_min_dimension"],
    .jem-image-settings-general input[id="jform_image_max_dimension"],
    .jem-image-settings-general input[id="jform_imagewidth"],
    .jem-image-settings-general input[id="jform_imagehight"] {
        width: 8rem;
        max-width: 100%;
    }
    .jem-image-settings-storage select,
    .jem-image-settings-profile select {
        width: auto;
        max-width: 100%;
    }
    .jem-image-settings-storage input[id$="_pattern"] {
        width: 100%;
    }
    .jem-image-settings-profile [hidden],
    .jem-image-settings-storage [hidden] {
        display: none !important;
    }
    .jem-image-settings-storage code {
        overflow-wrap: anywhere;
    }
    @media (min-width: 1200px) {
        .jem-image-settings-grid {
            grid-template-columns: minmax(22rem, 0.9fr) minmax(28rem, 1.1fr);
            align-items: start;
        }
    }
</style>

<script>
(function () {
    'use strict';

    function initialiseImageSettingsVisibility() {
        document.querySelectorAll('.jem-image-settings-profile[data-jem-image-profile]').forEach(function (profile) {
            var prefix = profile.dataset.jemImageProfile;
            var ratio = document.getElementById('jform_' + prefix + '_ratio');
            var customRatioRow = profile.querySelector('[data-jem-image-custom-ratio]');

            if (!ratio || !customRatioRow) {
                return;
            }

            var updateVisibility = function () {
                customRatioRow.hidden = ratio.value !== 'custom';
            };

            ratio.addEventListener('change', updateVisibility);
            updateVisibility();
        });

        document.querySelectorAll('.jem-image-settings-storage[data-jem-image-storage]').forEach(function (storage) {
            var preset = storage.querySelector('select[id$="_preset"]');
            var customPatternRow = storage.querySelector('[data-jem-image-custom-pattern]');

            if (!preset || !customPatternRow) {
                return;
            }

            var updateVisibility = function () {
                customPatternRow.hidden = preset.value !== 'custom';
            };

            preset.addEventListener('change', updateVisibility);
            updateVisibility();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiseImageSettingsVisibility);
    } else {
        initialiseImageSettingsVisibility();
    }
}());
</script>

<div class="width-100 jem-image-settings-grid">
    <div class="jem-image-settings-column jem-image-settings-left">
        <fieldset class="options-form jem-image-settings-general">
            <legend><?php echo Text::_('COM_JEM_IMAGE_GENERAL_SETTINGS'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('image_filetypes'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('sizelimit'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('image_min_dimension'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('image_max_dimension'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('imagewidth'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('imagehight'); ?></div></li>
                <?php if ($gdv && $gdv >= 2) : //is the gd library installed on the server and its version > 2? ?>
                    <li><div class="label-form"><?php echo $this->form->renderfield('gddisabled'); ?></div></li>
                <?php endif; ?>
                <li><div class="label-form"><?php echo $this->form->renderfield('lightbox'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('flyer'); ?></div></li>
            </ul>
        </fieldset>

        <?php foreach ($storageProfiles as $storageProfile) : ?>
            <?php
                $storagePreset = (string) $this->form->getValue(
                    $storageProfile['prefix'] . '_preset',
                    $globalGroup
                );
            ?>
            <fieldset class="options-form jem-image-settings-storage" data-jem-image-storage="<?php echo $this->escape($storageProfile['prefix']); ?>">
                <legend><?php echo Text::_($storageProfile['title']); ?></legend>
                <ul class="adminformlist">
                    <li><div class="label-form"><?php echo $this->form->renderfield($storageProfile['prefix'] . '_enabled', $globalGroup); ?></div></li>
                    <li><div class="label-form"><?php echo $this->form->renderfield($storageProfile['prefix'] . '_preset', $globalGroup); ?></div></li>
                    <li data-jem-image-custom-pattern<?php echo $storagePreset === 'custom' ? '' : ' hidden'; ?>><div class="label-form"><?php echo $this->form->renderfield($storageProfile['prefix'] . '_pattern', $globalGroup); ?></div></li>
                </ul>
            </fieldset>
        <?php endforeach; ?>
    </div>

    <div class="jem-image-settings-column jem-image-settings-right">
        <?php foreach ($profiles as $profile) : ?>
            <?php
                $ratioValue = (string) $this->form->getValue($profile['prefix'] . '_ratio');
            ?>
            <fieldset class="options-form jem-image-settings-profile" data-jem-image-profile="<?php echo $this->escape($profile['prefix']); ?>">
                <legend><?php echo Text::_($profile['title']); ?></legend>
                <ul class="adminformlist">
                    <li><div class="label-form"><?php echo $this->form->renderfield($profile['prefix'] . '_required'); ?></div></li>
                    <li><div class="label-form"><?php echo $this->form->renderfield($profile['prefix'] . '_default_dimension'); ?></div></li>
                    <li><div class="label-form"><?php echo $this->form->renderfield($profile['prefix'] . '_dimension_mandatory'); ?></div></li>
                    <li><div class="label-form"><?php echo $this->form->renderfield($profile['prefix'] . '_mode'); ?></div></li>
                    <li data-jem-image-ratio><div class="label-form"><?php echo $this->form->renderfield($profile['prefix'] . '_ratio'); ?></div></li>
                    <li data-jem-image-ratio><div class="label-form"><?php echo $this->form->renderfield($profile['prefix'] . '_ratio_mandatory'); ?></div></li>
                    <li data-jem-image-custom-ratio<?php echo $ratioValue === 'custom' ? '' : ' hidden'; ?>><div class="label-form"><?php echo $this->form->renderfield($profile['prefix'] . '_custom_ratio'); ?></div></li>
                </ul>
            </fieldset>
        <?php endforeach; ?>
    </div>
</div>
