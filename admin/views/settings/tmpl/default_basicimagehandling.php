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
?>

<style>
    .jem-image-settings-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr);
        row-gap: 1rem;
        padding: 10px 1vw;
    }
    .jem-image-settings-grid .options-form {
        min-width: 0;
        margin: 0;
    }
    .jem-image-settings-general {
        grid-column: auto;
    }
    .jem-image-settings-profile select {
        width: auto;
        max-width: 100%;
    }
    .jem-image-settings-profile input[id$="_custom_ratio"] {
        width: 8rem;
        max-width: 100%;
    }
    .jem-image-settings-profile [hidden] {
        display: none !important;
    }
</style>

<script>
(function () {
    'use strict';

    function initialiseImageProfileVisibility() {
        document.querySelectorAll('.jem-image-settings-profile[data-jem-image-profile]').forEach(function (profile) {
            var prefix = profile.dataset.jemImageProfile;
            var mode = document.getElementById('jform_' + prefix + '_mode');
            var ratio = document.getElementById('jform_' + prefix + '_ratio');
            var ratioRow = profile.querySelector('[data-jem-image-ratio]');
            var customRatioRow = profile.querySelector('[data-jem-image-custom-ratio]');

            if (!mode || !ratio || !ratioRow || !customRatioRow) {
                return;
            }

            var updateVisibility = function () {
                var usesRatio = mode.value !== 'none';
                ratioRow.hidden = !usesRatio;
                customRatioRow.hidden = !(usesRatio && ratio.value === 'custom');
            };

            mode.addEventListener('change', updateVisibility);
            ratio.addEventListener('change', updateVisibility);
            updateVisibility();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialiseImageProfileVisibility);
    } else {
        initialiseImageProfileVisibility();
    }
}());
</script>

<div class="width-100 jem-image-settings-grid">
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

    <?php foreach ($profiles as $profile) : ?>
        <?php
            $modeValue = (string) $this->form->getValue($profile['prefix'] . '_mode');
            $ratioValue = (string) $this->form->getValue($profile['prefix'] . '_ratio');
        ?>
        <fieldset class="options-form jem-image-settings-profile" data-jem-image-profile="<?php echo $this->escape($profile['prefix']); ?>">
            <legend><?php echo Text::_($profile['title']); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield($profile['prefix'] . '_required'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield($profile['prefix'] . '_default_dimension'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield($profile['prefix'] . '_mode'); ?></div></li>
                <li data-jem-image-ratio<?php echo $modeValue === 'none' ? ' hidden' : ''; ?>><div class="label-form"><?php echo $this->form->renderfield($profile['prefix'] . '_ratio'); ?></div></li>
                <li data-jem-image-custom-ratio<?php echo $modeValue !== 'none' && $ratioValue === 'custom' ? '' : ' hidden'; ?>><div class="label-form"><?php echo $this->form->renderfield($profile['prefix'] . '_custom_ratio'); ?></div></li>
            </ul>
        </fieldset>
    <?php endforeach; ?>
</div>
