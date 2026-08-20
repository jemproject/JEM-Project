<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$configurationSubmitted = (int) $this->form->getValue('capacity_configuration_submitted') === 1;
$submittedConfiguration = null;
if ($configurationSubmitted) {
    $submittedConfiguration = json_decode((string) $this->form->getValue('capacity_configuration_json'), true);
    if (!is_array($submittedConfiguration) || !isset($submittedConfiguration['spaces']) || !is_array($submittedConfiguration['spaces'])) {
        $submittedConfiguration = null;
    }
}

$profileName = trim((string) ($configurationSubmitted
    ? $this->form->getValue('capacity_profile_name')
    : ($this->item->capacity_profile_name ?? '')));
if ($profileName === '') {
    $profileName = Text::_('COM_JEM_VENUE_CAPACITY_PROFILE_MAIN');
}
$initialSpaces = $submittedConfiguration !== null
    ? array_values($submittedConfiguration['spaces'])
    : array_values((array) ($this->item->capacity_spaces ?? array()));
$hasConfiguredProfile = $configurationSubmitted || (int) ($this->item->capacity_profile_id ?? 0) > 0;
$profiles = array_values((array) ($this->item->capacity_profiles ?? array()));
$selectedProfileId = (int) ($configurationSubmitted
    ? $this->form->getValue('capacity_profile_id')
    : ($this->item->capacity_profile_id ?? 0));
$isDefaultProfile = $configurationSubmitted
    ? (int) $this->form->getValue('capacity_profile_set_default') === 1
    : !empty($this->item->capacity_profile_is_default);
$isPublishedProfile = !isset($this->item->capacity_profile_published) || !empty($this->item->capacity_profile_published);
$profileRevision = (int) ($configurationSubmitted
    ? $this->form->getValue('capacity_profile_revision')
    : ($this->item->capacity_profile_revision ?? 1));
$profileCapacity = (int) ($configurationSubmitted
    ? $this->form->getValue('capacity_profile_capacity')
    : ($this->item->capacity_profile_capacity ?? 0));
$selectedProfileAvailable = false;
$initialLayoutCapacity = 0;
foreach ($initialSpaces as $space) {
    $initialLayoutCapacity += max(0, (int) ($space['layout_capacity'] ?? 0));
}
foreach ($profiles as $profile) {
    if ((int) ($profile['id'] ?? 0) === $selectedProfileId) {
        $selectedProfileAvailable = true;
        break;
    }
}

$initialConfiguration = json_encode(
    array('spaces' => $initialSpaces),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<div class="jem-venue-capacity-editor" id="jem-venue-capacity-editor" data-profile-configured="<?php echo $hasConfiguredProfile ? '1' : '0'; ?>" data-restored-unsaved="<?php echo $configurationSubmitted ? '1' : '0'; ?>">
    <?php echo $this->form->getInput('capacity_configuration_submitted'); ?>
    <?php echo $this->form->getInput('capacity_configuration_json'); ?>
    <?php echo $this->form->getInput('capacity_profile_id'); ?>
    <?php echo $this->form->getInput('capacity_profile_code'); ?>
    <?php echo $this->form->getInput('capacity_profile_action'); ?>
    <?php echo $this->form->getInput('capacity_profile_set_default'); ?>
    <?php echo $this->form->getInput('capacity_profile_ordering'); ?>

    <div class="alert alert-info jem-capacity-intro">
        <strong><?php echo Text::_('COM_JEM_VENUE_CAPACITY_CONFIGURATION'); ?>:</strong>
        <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_CONFIGURATION_INTRO'); ?></span>
    </div>

    <section class="adminform jem-capacity-empty-state" data-role="profile-empty-state"<?php echo $hasConfiguredProfile ? ' hidden' : ''; ?>>
        <strong><?php echo Text::_('COM_JEM_VENUE_CAPACITY_NO_PROFILES'); ?></strong>
        <span class="form-text"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_NO_PROFILES_DESC'); ?></span>
        <button type="button" class="btn btn-primary" data-action="create-profile">
            <span class="icon-plus" aria-hidden="true"></span>
            <?php echo Text::_('COM_JEM_VENUE_CAPACITY_CREATE_PROFILE'); ?>
        </button>
    </section>

    <div data-role="profile-configuration"<?php echo $hasConfiguredProfile ? '' : ' hidden'; ?>>
        <section class="adminform jem-capacity-venue-toolbar">
            <div class="jem-capacity-profile-actions">
            <label class="visually-hidden" for="jem-capacity-profile-selector"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_PROFILE_SELECTOR'); ?></label>
            <select class="form-select" id="jem-capacity-profile-selector">
                <?php if ($configurationSubmitted && !$selectedProfileAvailable) : ?>
                    <option value="<?php echo $selectedProfileId; ?>"
                        data-space-count="<?php echo count($initialSpaces); ?>"
                        data-layout-capacity="<?php echo $initialLayoutCapacity; ?>"
                        data-profile-capacity="<?php echo $profileCapacity; ?>"
                        data-is-default="<?php echo $isDefaultProfile ? 1 : 0; ?>"
                        data-is-published="1" selected data-role="profile-option"><?php echo htmlspecialchars(
                        Text::sprintf(
                            'COM_JEM_VENUE_CAPACITY_PROFILE_OPTION_SUMMARY',
                            $profileName,
                            count($initialSpaces),
                            $initialLayoutCapacity,
                            $profileCapacity
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?></option>
                <?php endif; ?>
                <?php foreach ($profiles as $profile) : ?>
                    <option value="<?php echo (int) $profile['id']; ?>"
                        data-space-count="<?php echo (int) ($profile['space_count'] ?? 0); ?>"
                        data-layout-capacity="<?php echo (int) ($profile['layout_capacity'] ?? 0); ?>"
                        data-profile-capacity="<?php echo (int) ($profile['capacity'] ?? 0); ?>"
                        data-is-default="<?php echo !empty($profile['is_default']) ? 1 : 0; ?>"
                        data-is-published="<?php echo !empty($profile['published']) ? 1 : 0; ?>"<?php echo (int) $profile['id'] === $selectedProfileId ? ' selected data-role="profile-option"' : ''; ?>><?php echo htmlspecialchars(
                        Text::sprintf(
                            'COM_JEM_VENUE_CAPACITY_PROFILE_OPTION_SUMMARY',
                            (string) $profile['name'],
                            (int) ($profile['space_count'] ?? 0),
                            (int) ($profile['layout_capacity'] ?? 0),
                            (int) ($profile['capacity'] ?? 0)
                        )
                        . (!empty($profile['is_default']) ? ' (' . Text::_('COM_JEM_VENUE_CAPACITY_PROFILE_DEFAULT') . ')' : '')
                        . (empty($profile['published']) ? ' (' . Text::_('COM_JEM_VENUE_CAPACITY_PROFILE_ARCHIVED') . ')' : ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?></option>
                <?php endforeach; ?>
            </select>
            <span class="d-inline-block">
                <button type="button" class="btn btn-outline-primary" data-action="add-profile">
                    <span class="icon-plus" aria-hidden="true"></span>
                    <?php echo Text::_('COM_JEM_VENUE_CAPACITY_ADD_PROFILE'); ?>
                </button>
            </span>
            <span class="d-inline-block">
                <button type="button" class="btn btn-outline-secondary" data-action="set-default"<?php echo $isDefaultProfile ? ' disabled' : ''; ?>>
                    <span class="icon-check" aria-hidden="true"></span>
                    <?php echo Text::_('COM_JEM_VENUE_CAPACITY_SET_DEFAULT'); ?>
                </button>
            </span>
            <button type="button" class="btn btn-outline-secondary" data-action="duplicate-profile"<?php echo $hasConfiguredProfile ? '' : ' disabled'; ?>><?php echo Text::_('COM_JEM_VENUE_CAPACITY_DUPLICATE_PROFILE'); ?></button>
            <button type="button" class="btn btn-outline-secondary" data-action="move-profile-up"<?php echo $hasConfiguredProfile ? '' : ' disabled'; ?> title="<?php echo Text::_('COM_JEM_MOVE_UP'); ?>"><span class="icon-arrow-up" aria-hidden="true"></span></button>
            <button type="button" class="btn btn-outline-secondary" data-action="move-profile-down"<?php echo $hasConfiguredProfile ? '' : ' disabled'; ?> title="<?php echo Text::_('COM_JEM_MOVE_DOWN'); ?>"><span class="icon-arrow-down" aria-hidden="true"></span></button>
            <button type="button" class="btn btn-outline-danger" data-action="archive-profile"<?php echo !$hasConfiguredProfile || $isDefaultProfile ? ' disabled' : ''; ?>><?php echo Text::_('COM_JEM_VENUE_CAPACITY_ARCHIVE_PROFILE'); ?></button>
            </div>
        </section>

        <section class="adminform jem-capacity-profile-card" aria-labelledby="jem-capacity-profile-title">
        <h2 class="visually-hidden" id="jem-capacity-profile-title" data-role="profile-title"><?php echo htmlspecialchars($profileName, ENT_QUOTES, 'UTF-8'); ?></h2>
        <div class="jem-capacity-profile-details">
            <div class="jem-capacity-profile-name">
                <?php echo $this->form->getLabel('capacity_profile_name'); ?>
                <?php if ($isDefaultProfile) : ?>
                    <span class="badge bg-info text-dark jem-capacity-profile-default"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_PROFILE_DEFAULT'); ?></span>
                <?php endif; ?>
                <?php if (!$isPublishedProfile) : ?>
                    <span class="badge bg-secondary"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_PROFILE_ARCHIVED'); ?></span>
                <?php endif; ?>
                <span class="jem-capacity-profile-revision">
                    <?php echo Text::_('COM_JEM_VENUE_CAPACITY_PROFILE_REVISION_COMPACT'); ?>
                    <strong><?php echo max(1, $profileRevision); ?></strong>
                </span>
                <?php echo $this->form->getInput('capacity_profile_name'); ?>
                <div id="jform_capacity_profile_name-desc" class="form-text hide-aware-inline-help d-none">
                    <?php echo Text::_('COM_JEM_VENUE_CAPACITY_PROFILE_DESC'); ?>
                </div>
            </div>
            <div class="jem-capacity-profile-capacity">
                <?php echo $this->form->getLabel('capacity_profile_capacity'); ?>
                <?php echo $this->form->getInput('capacity_profile_capacity'); ?>
                <div id="jform_capacity_profile_capacity-desc" class="form-text hide-aware-inline-help d-none">
                    <?php echo Text::_('COM_JEM_VENUE_PROFILE_CAPACITY_DESC'); ?>
                </div>
            </div>
            <div hidden><?php echo $this->form->getInput('capacity_profile_revision'); ?></div>
            <div class="jem-capacity-profile-summary" aria-live="polite">
                <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT_CAPACITY'); ?>:</span>
                <strong data-role="profile-capacity">0</strong>
                <span>/</span>
                <strong data-role="venue-capacity">0</strong>
            </div>
            <div class="form-text hide-aware-inline-help d-none jem-capacity-profile-help">
                <?php echo Text::_('COM_JEM_VENUE_CAPACITY_PROFILE_REVISION_DESC'); ?>
                <?php echo Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT_CAPACITY_SUMMARY_DESC'); ?>
            </div>
            <div class="jem-capacity-validation text-danger" data-role="capacity-validation" role="alert" hidden></div>
        </div>
        </section>

        <div class="jem-capacity-spaces" data-role="spaces"></div>
        <div>
            <button type="button" class="btn btn-outline-primary" data-action="add-space">
                <span class="icon-plus" aria-hidden="true"></span>
                <?php echo Text::_('COM_JEM_VENUE_CAPACITY_ADD_SPACE'); ?>
            </button>
        </div>
    </div>
</div>

<template id="jem-capacity-space-template">
    <article class="card jem-capacity-space-card" data-space-index="">
        <div class="card-header jem-capacity-card-header">
            <h3 class="h5 mb-0" data-role="space-title"></h3>
            <div class="btn-group btn-group-sm" role="group" aria-label="<?php echo Text::_('COM_JEM_VENUE_CAPACITY_SPACE_ACTIONS'); ?>">
                <button type="button" class="btn btn-outline-secondary" data-action="space-up" title="<?php echo Text::_('COM_JEM_MOVE_UP'); ?>">
                    <span class="icon-arrow-up" aria-hidden="true"></span>
                </button>
                <button type="button" class="btn btn-outline-secondary" data-action="space-down" title="<?php echo Text::_('COM_JEM_MOVE_DOWN'); ?>">
                    <span class="icon-arrow-down" aria-hidden="true"></span>
                </button>
                <button type="button" class="btn btn-outline-danger" data-action="remove-space">
                    <span class="icon-trash" aria-hidden="true"></span>
                    <?php echo Text::_('COM_JEM_REMOVE'); ?>
                </button>
            </div>
        </div>
        <div class="card-body">
            <input type="hidden" data-field="space_id">
            <input type="hidden" data-field="layout_id">
            <div class="jem-capacity-section jem-capacity-section-space">
                <h4 class="h6"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_SPACE'); ?></h4>
                <div class="jem-capacity-field-grid jem-capacity-space-grid">
                    <input type="hidden" data-field="space_code">
                    <label>
                        <span>
                            <?php echo Text::_('COM_JEM_VENUE_CAPACITY_SPACE_NAME'); ?>
                            <span class="star" aria-hidden="true">&nbsp;*</span>
                        </span>
                        <input type="text" class="form-control required" maxlength="255" data-field="space_name" required aria-required="true">
                        <div class="form-text hide-aware-inline-help d-none" data-help-field="space_name"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_SPACE_NAME_DESC'); ?></div>
                    </label>
                    <label class="jem-capacity-color-field">
                        <span><?php echo Text::_('COM_JEM_COLOR'); ?></span>
                        <input type="color" class="form-control form-control-color" data-field="space_color">
                        <div class="form-text hide-aware-inline-help d-none" data-help-field="space_color"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_COLOR_DESC'); ?></div>
                    </label>
                </div>
                <label class="d-block mt-3">
                    <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_SPACE_DESCRIPTION'); ?></span>
                    <textarea class="form-control" rows="2" data-field="space_description"></textarea>
                    <div class="form-text hide-aware-inline-help d-none" data-help-field="space_description"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_SPACE_DESCRIPTION_DESC'); ?></div>
                </label>
                <div class="jem-capacity-media-grid mt-3">
                    <input type="hidden" data-field="space_image">
                    <label>
                        <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_SPACE_IMAGE'); ?></span>
                        <input type="file" class="form-control" accept="image/*" data-upload="space">
                        <span class="form-text" data-role="space-image-current"></span>
                    </label>
                    <label>
                        <span><?php echo Text::_('COM_JEM_VENUE_IMAGE_ALT'); ?></span>
                        <input type="text" class="form-control" maxlength="255" data-field="space_image_alt">
                        <div class="form-text hide-aware-inline-help d-none" data-help-field="space_image_alt"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_SPACE_IMAGE_ALT_DESC'); ?></div>
                    </label>
                    <input type="hidden" value="0" data-field="space_image_remove">
                    <button type="button" class="btn btn-outline-danger jem-capacity-media-remove" data-action="remove-space-image" title="<?php echo Text::_('COM_JEM_REMOVE_IMAGE'); ?>" aria-label="<?php echo Text::_('COM_JEM_REMOVE_IMAGE'); ?>">
                        <span class="icon-times" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
            <div class="jem-capacity-section jem-capacity-section-layout">
                <h4 class="h6"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT'); ?></h4>
                <div class="jem-capacity-field-grid jem-capacity-layout-grid">
                    <label>
                        <span>
                            <?php echo Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT_NAME'); ?>
                            <span class="star" aria-hidden="true">&nbsp;*</span>
                        </span>
                        <input type="text" class="form-control required" maxlength="255" data-field="layout_name" required aria-required="true">
                        <div class="form-text hide-aware-inline-help d-none" data-help-field="layout_name"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT_NAME_DESC'); ?></div>
                    </label>
                    <label>
                        <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT_CODE'); ?></span>
                        <input type="text" class="form-control readonly" maxlength="64" data-field="layout_code" readonly>
                        <div class="form-text hide-aware-inline-help d-none" data-help-field="layout_code"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT_CODE_DESC'); ?></div>
                    </label>
                    <label>
                        <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT_CAPACITY'); ?></span>
                        <input type="number" class="form-control" min="0" step="1" data-field="layout_capacity">
                        <div class="form-text hide-aware-inline-help d-none" data-help-field="layout_capacity"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT_CAPACITY_DESC'); ?></div>
                    </label>
                    <label class="jem-capacity-color-field">
                        <span><?php echo Text::_('COM_JEM_COLOR'); ?></span>
                        <input type="color" class="form-control form-control-color" data-field="layout_color">
                        <div class="form-text hide-aware-inline-help d-none" data-help-field="layout_color"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_COLOR_DESC'); ?></div>
                    </label>
                </div>
                <p class="form-text mb-0" data-role="layout-revision"></p>
                <div class="jem-capacity-media-grid mt-3">
                    <input type="hidden" data-field="layout_image">
                    <label>
                        <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT_PLAN'); ?></span>
                        <input type="file" class="form-control" accept="image/*" data-upload="layout">
                        <span class="form-text" data-role="layout-image-current"></span>
                    </label>
                    <label>
                        <span><?php echo Text::_('COM_JEM_VENUE_IMAGE_ALT'); ?></span>
                        <input type="text" class="form-control" maxlength="255" data-field="layout_image_alt">
                        <div class="form-text hide-aware-inline-help d-none" data-help-field="layout_image_alt"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT_IMAGE_ALT_DESC'); ?></div>
                    </label>
                    <input type="hidden" value="0" data-field="layout_image_remove">
                    <button type="button" class="btn btn-outline-danger jem-capacity-media-remove" data-action="remove-layout-image" title="<?php echo Text::_('COM_JEM_REMOVE_IMAGE'); ?>" aria-label="<?php echo Text::_('COM_JEM_REMOVE_IMAGE'); ?>">
                        <span class="icon-times" aria-hidden="true"></span>
                    </button>
                </div>
                <div class="jem-capacity-section jem-capacity-section-areas">
                    <div class="jem-capacity-areas-heading">
                        <div>
                            <h5 class="h6 mb-1"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_AREAS'); ?></h5>
                            <div class="form-text hide-aware-inline-help d-none mb-0"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_AREAS_DESC'); ?></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-action="add-area">
                            <span class="icon-plus" aria-hidden="true"></span>
                            <?php echo Text::_('COM_JEM_VENUE_CAPACITY_ADD_AREA'); ?>
                        </button>
                    </div>
                    <div class="jem-capacity-area-list" data-role="areas"></div>
                </div>
            </div>
        </div>
    </article>
</template>

<template id="jem-capacity-area-template">
    <div class="jem-capacity-area-row" data-area-index="">
        <input type="hidden" data-area-field="id">
        <label>
            <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_AREA_NAME'); ?></span>
            <input type="text" class="form-control" maxlength="255" data-area-field="name">
            <div class="form-text hide-aware-inline-help d-none" data-help-area-field="name"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_AREA_NAME_DESC'); ?></div>
        </label>
        <label>
            <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_AREA_CODE'); ?></span>
            <input type="text" class="form-control" maxlength="64" data-area-field="code">
            <div class="form-text hide-aware-inline-help d-none" data-help-area-field="code"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_AREA_CODE_DESC'); ?></div>
        </label>
        <label>
            <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_AREA_CAPACITY'); ?></span>
            <input type="number" class="form-control" min="0" step="1" data-area-field="capacity">
            <div class="form-text hide-aware-inline-help d-none" data-help-area-field="capacity"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_AREA_CAPACITY_DESC'); ?></div>
        </label>
        <label class="jem-capacity-color-field">
            <span><?php echo Text::_('COM_JEM_COLOR'); ?></span>
            <input type="color" class="form-control form-control-color" data-area-field="color">
            <div class="form-text hide-aware-inline-help d-none" data-help-area-field="color"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_COLOR_DESC'); ?></div>
        </label>
        <label>
            <span><?php echo Text::_('JSTATUS'); ?></span>
            <select class="form-select" data-area-field="published">
                <option value="1"><?php echo Text::_('JPUBLISHED'); ?></option>
                <option value="0"><?php echo Text::_('JUNPUBLISHED'); ?></option>
            </select>
            <div class="form-text hide-aware-inline-help d-none" data-help-area-field="published"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_AREA_STATUS_DESC'); ?></div>
        </label>
        <label class="jem-capacity-area-description">
            <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_AREA_DESCRIPTION'); ?></span>
            <textarea class="form-control" rows="2" data-area-field="description"></textarea>
            <div class="form-text hide-aware-inline-help d-none" data-help-area-field="description"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_AREA_DESCRIPTION_DESC'); ?></div>
        </label>
        <button type="button" class="btn btn-sm btn-outline-danger jem-capacity-area-remove" data-action="remove-area">
            <span class="icon-trash" aria-hidden="true"></span>
            <?php echo Text::_('COM_JEM_REMOVE'); ?>
        </button>
    </div>
</template>

<script type="application/json" id="jem-capacity-initial-data"><?php echo $initialConfiguration; ?></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const editor = document.getElementById('jem-venue-capacity-editor');
    const spacesContainer = editor ? editor.querySelector('[data-role="spaces"]') : null;
    const hidden = document.getElementById('jform_capacity_configuration_json');
    const profileSubmittedInput = document.getElementById('jform_capacity_configuration_submitted');
    const emptyState = editor ? editor.querySelector('[data-role="profile-empty-state"]') : null;
    const profileConfiguration = editor ? editor.querySelector('[data-role="profile-configuration"]') : null;
    const spaceTemplate = document.getElementById('jem-capacity-space-template');
    const areaTemplate = document.getElementById('jem-capacity-area-template');
    const profileCapacityInput = document.getElementById('jform_capacity_profile_capacity');
    const venueCapacityInput = document.getElementById('jform_capacity');
    const venueNameInput = document.getElementById('jform_venue');
    const profileNameInput = document.getElementById('jform_capacity_profile_name');
    const profileIdInput = document.getElementById('jform_capacity_profile_id');
    const profileCodeInput = document.getElementById('jform_capacity_profile_code');
    const profileActionInput = document.getElementById('jform_capacity_profile_action');
    const profileSetDefaultInput = document.getElementById('jform_capacity_profile_set_default');
    const profileSelector = document.getElementById('jem-capacity-profile-selector');
    const labels = <?php echo json_encode(array(
        'space'          => Text::_('COM_JEM_VENUE_CAPACITY_SPACE_NUMBER'),
        'profileMain'    => Text::_('COM_JEM_VENUE_CAPACITY_PROFILE_MAIN'),
        'profileDefault' => Text::_('COM_JEM_VENUE_CAPACITY_PROFILE_DEFAULT'),
        'profileArchived'=> Text::_('COM_JEM_VENUE_CAPACITY_PROFILE_ARCHIVED'),
        'profileSummary' => Text::_('COM_JEM_VENUE_CAPACITY_PROFILE_OPTION_SUMMARY'),
        'layoutRevision' => Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT_REVISION'),
        'confirmRemove'  => Text::_('COM_JEM_VENUE_CAPACITY_CONFIRM_REMOVE_SPACE'),
        'profileLimit'   => Text::_('COM_JEM_VENUE_CAPACITY_ERROR_PROFILE_LIMIT'),
        'physicalLimit'  => Text::_('COM_JEM_VENUE_CAPACITY_ERROR_PROFILE_PHYSICAL_LIMIT'),
        'areaTotal'      => Text::_('COM_JEM_VENUE_CAPACITY_ERROR_AREA_TOTAL_DETAIL'),
        'venue'          => Text::_('COM_JEM_VENUE'),
        'profile'        => Text::_('COM_JEM_VENUE_CAPACITY_PROFILE'),
        'layout'         => Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT'),
        'area'           => Text::_('COM_JEM_VENUE_CAPACITY_AREA'),
        'newProfile'     => Text::_('COM_JEM_VENUE_CAPACITY_NEW_PROFILE'),
        'confirmSwitch'  => Text::_('COM_JEM_VENUE_CAPACITY_CONFIRM_SWITCH_PROFILE'),
        'confirmArchive' => Text::_('COM_JEM_VENUE_CAPACITY_CONFIRM_ARCHIVE_PROFILE'),
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    if (!editor || !spacesContainer || !hidden || !spaceTemplate || !areaTemplate) {
        return;
    }

    let initial = {spaces: []};
    let profileConfigured = editor.dataset.profileConfigured === '1';
    const pendingUploads = new Map();
    let nextClientKey = 1;
    try {
        initial = JSON.parse(document.getElementById('jem-capacity-initial-data').textContent || '{"spaces":[]}');
    } catch (ignore) {
        initial = {spaces: []};
    }

    function blankSpace() {
        return {
            space_id: 0,
            space_code: '',
            space_name: '',
            space_color: '#2F6F9F',
            space_description: '',
            space_image: '',
            space_image_alt: '',
            space_image_remove: 0,
            layout_id: 0,
            layout_code: '',
            layout_name: '',
            layout_color: '#B78324',
            layout_revision: 0,
            layout_capacity: 0,
            layout_image: '',
            layout_image_alt: '',
            layout_image_remove: 0,
            client_key: 'new-' + (nextClientKey++),
            areas: []
        };
    }

    function blankArea() {
        return {id: 0, code: '', name: '', color: '#8A6D3B', description: '', capacity: 0, allocation_mode: 'quantity', published: 1};
    }

    function integer(value) {
        const number = Number.parseInt(value, 10);
        return Number.isFinite(number) && number >= 0 ? number : 0;
    }

    function formatMessage(message, values) {
        values.forEach(function (value) {
            message = message.replace(/%[sd]/, String(value));
        });
        return message;
    }

    function profileOptionLabel(name, option) {
        const values = [
            name,
            integer(option ? option.dataset.spaceCount : 0),
            integer(option ? option.dataset.layoutCapacity : 0),
            integer(option ? option.dataset.profileCapacity : 0)
        ];
        let label = labels.profileSummary;
        values.forEach(function (value) {
            label = label.replace(/%[sd]/, String(value));
        });
        if (option && option.dataset.isDefault === '1') {
            label += ' (' + labels.profileDefault + ')';
        }
        if (option && option.dataset.isPublished === '0') {
            label += ' (' + labels.profileArchived + ')';
        }

        return label;
    }

    function setProfileConfigured(configured) {
        profileConfigured = Boolean(configured);
        editor.dataset.profileConfigured = profileConfigured ? '1' : '0';
        if (emptyState) {
            emptyState.hidden = profileConfigured;
        }
        if (profileConfiguration) {
            profileConfiguration.hidden = !profileConfigured;
        }
        if (profileSubmittedInput) {
            profileSubmittedInput.value = profileConfigured ? '1' : '0';
        }
    }

    function overviewNode(kind, label, name, capacity, color) {
        const node = document.createElement('div');
        const header = document.createElement('div');
        const identity = document.createElement('div');
        const type = document.createElement('span');
        const nameRow = document.createElement('div');
        const title = document.createElement('strong');
        const capacityBadge = document.createElement('span');
        const children = document.createElement('div');

        node.className = 'jem-capacity-overview-node is-' + kind;
        node.style.setProperty('--jem-capacity-node-color', color || '#7C99B5');
        header.className = 'jem-capacity-overview-node-header';
        identity.className = 'jem-capacity-overview-node-identity';
        type.className = 'jem-capacity-overview-node-type';
        nameRow.className = 'jem-capacity-overview-node-name';
        type.textContent = label;
        title.textContent = name;
        capacityBadge.className = 'badge bg-secondary';
        capacityBadge.textContent = String(integer(capacity));
        children.className = 'jem-capacity-overview-children';
        nameRow.appendChild(title);
        identity.append(type, nameRow);
        header.append(identity, capacityBadge);
        node.append(header, children);

        return {node: node, children: children};
    }

    function renderOverview(spaces, profileTotal, profileLimit, venueLimit, layoutsExceedProfile, profileExceedsVenue) {
        const overview = document.querySelector('[data-role="configuration-overview"]');
        if (!overview) {
            return;
        }

        const venueName = venueNameInput && venueNameInput.value.trim()
            ? venueNameInput.value.trim()
            : labels.venue;
        const venue = overviewNode('venue', labels.venue, venueName, venueLimit, '#59799A');
        if (!profileConfigured) {
            overview.replaceChildren(venue.node);
            return;
        }
        const profileName = profileNameInput && profileNameInput.value.trim()
            ? profileNameInput.value.trim()
            : labels.profileMain;
        const profile = overviewNode('profile', labels.profile, profileName, profileLimit, '#59799A');
        venue.node.classList.toggle('is-invalid-limit', profileExceedsVenue);
        profile.node.classList.toggle('is-invalid-limit', layoutsExceedProfile || profileExceedsVenue);
        venue.children.appendChild(profile.node);

        spaces.forEach(function (space, index) {
            const spaceName = space.space_name || (labels.space + ' ' + (index + 1));
            const spaceNode = overviewNode('space', labels.space, spaceName, space.layout_capacity, space.space_color);
            const layoutName = space.layout_name || labels.layout;
            const layoutNode = overviewNode('layout', labels.layout, layoutName, space.layout_capacity, space.layout_color);
            (Array.isArray(space.areas) ? space.areas : []).forEach(function (area, areaIndex) {
                const areaName = area.name || (labels.area + ' ' + (areaIndex + 1));
                const areaNode = overviewNode('area', labels.area, areaName, area.capacity, area.color);
                layoutNode.children.appendChild(areaNode.node);
            });
            spaceNode.children.appendChild(layoutNode.node);
            profile.children.appendChild(spaceNode.node);
        });

        const profileBadge = profile.node.querySelector('.badge');
        if (profileBadge) {
            profileBadge.textContent = String(profileTotal) + ' / ' + String(profileLimit);
        }
        overview.replaceChildren(venue.node);
    }

    function setValue(scope, selector, value) {
        const field = scope.querySelector(selector);
        if (field) {
            field.value = value === null || value === undefined ? '' : value;
        }
        return field;
    }

    function connectInlineHelp(scope, prefix) {
        scope.querySelectorAll('[data-help-field], [data-help-area-field]').forEach(function (description) {
            const fieldName = description.dataset.helpField || description.dataset.helpAreaField || '';
            const selector = description.dataset.helpField
                ? '[data-field="' + fieldName + '"]'
                : '[data-area-field="' + fieldName + '"]';
            const field = description.closest('label') ? description.closest('label').querySelector(selector) : null;
            if (!field || !fieldName) {
                return;
            }
            field.id = prefix + '-' + fieldName.replace(/_/g, '-');
            description.id = field.id + '-desc';
        });
    }

    function applyInlineHelpState(scope, visible) {
        scope.querySelectorAll('div.hide-aware-inline-help').forEach(function (description) {
            description.classList.toggle('d-none', !visible);
            if (!description.id || !description.id.endsWith('-desc')) {
                return;
            }
            const field = document.getElementById(description.id.slice(0, -5));
            if (!field) {
                return;
            }
            if (visible) {
                field.setAttribute('aria-describedby', description.id);
            } else {
                field.removeAttribute('aria-describedby');
            }
        });
    }

    function readSpaces() {
        return Array.from(spacesContainer.querySelectorAll('.jem-capacity-space-card')).map(function (card) {
            const value = function (name) {
                const field = card.querySelector('[data-field="' + name + '"]');
                return field ? field.value : '';
            };
            const areas = Array.from(card.querySelectorAll('.jem-capacity-area-row')).map(function (row) {
                const areaValue = function (name) {
                    const field = row.querySelector('[data-area-field="' + name + '"]');
                    return field ? field.value : '';
                };
                return {
                    id: integer(areaValue('id')),
                    code: areaValue('code').trim(),
                    name: areaValue('name').trim(),
                    color: areaValue('color'),
                    description: areaValue('description').trim(),
                    capacity: integer(areaValue('capacity')),
                    allocation_mode: 'quantity',
                    published: integer(areaValue('published')) === 1 ? 1 : 0
                };
            });
            card.querySelectorAll('[data-upload]').forEach(function (upload) {
                if (upload.files && upload.files[0]) {
                    const key = card.dataset.clientKey + ':' + upload.dataset.upload;
                    pendingUploads.set(key, upload.files[0]);
                }
            });
            const flagged = function (name) {
                const field = card.querySelector('[data-field="' + name + '"]');
                return field && (field.checked || integer(field.value) === 1) ? 1 : 0;
            };

            return {
                client_key: card.dataset.clientKey,
                space_id: integer(value('space_id')),
                space_code: value('space_code').trim(),
                space_name: value('space_name').trim(),
                space_color: value('space_color'),
                space_description: value('space_description').trim(),
                space_image: value('space_image').trim(),
                space_image_alt: value('space_image_alt').trim(),
                space_image_remove: flagged('space_image_remove'),
                layout_id: integer(value('layout_id')),
                layout_code: value('layout_code').trim(),
                layout_name: value('layout_name').trim(),
                layout_color: value('layout_color'),
                layout_revision: integer(card.dataset.layoutRevision),
                layout_capacity: integer(value('layout_capacity')),
                layout_image: value('layout_image').trim(),
                layout_image_alt: value('layout_image_alt').trim(),
                layout_image_remove: flagged('layout_image_remove'),
                areas: areas
            };
        });
    }

    function sync() {
        const spaces = readSpaces();
        hidden.value = JSON.stringify({spaces: spaces});
        const profileTotal = spaces.reduce(function (total, space) {
            return total + integer(space.layout_capacity);
        }, 0);
        const profileLimit = integer(profileCapacityInput ? profileCapacityInput.value : 0);
        const venueLimit = integer(venueCapacityInput ? venueCapacityInput.value : 0);
        const summary = editor.querySelector('.jem-capacity-profile-summary');
        const venueLimitCard = editor.querySelector('.jem-capacity-venue-toolbar');
        const validation = editor.querySelector('[data-role="capacity-validation"]');
        const layoutsExceedProfile = profileTotal > profileLimit;
        const profileExceedsVenue = profileLimit > venueLimit;
        let areaValidationMessage = '';
        spaces.forEach(function (space, index) {
            const card = spacesContainer.querySelector('[data-space-index="' + index + '"]');
            const layoutCapacityField = card ? card.querySelector('[data-field="layout_capacity"]') : null;
            const activeAreaTotal = (Array.isArray(space.areas) ? space.areas : []).reduce(function (total, area) {
                return total + (integer(area.published) === 1 ? integer(area.capacity) : 0);
            }, 0);
            const exceedsLayout = integer(space.layout_capacity) > 0
                && activeAreaTotal > integer(space.layout_capacity);
            const message = exceedsLayout
                ? formatMessage(labels.areaTotal, [space.layout_name || labels.layout, activeAreaTotal, integer(space.layout_capacity)])
                : '';
            if (layoutCapacityField) {
                layoutCapacityField.setCustomValidity(message);
                layoutCapacityField.classList.toggle('is-invalid', exceedsLayout);
            }
            if (!areaValidationMessage && message) {
                areaValidationMessage = message;
            }
        });
        const profileValidationMessage = profileExceedsVenue
            ? labels.physicalLimit
            : (layoutsExceedProfile ? labels.profileLimit : '');
        const validationMessage = areaValidationMessage || profileValidationMessage;
        const profileOption = editor.querySelector('[data-role="profile-option"]');
        if (profileOption) {
            const configuredSpaceCount = spaces.filter(function (space) {
                return integer(space.space_id) > 0
                    || space.space_name.trim() !== ''
                    || space.layout_name.trim() !== ''
                    || integer(space.layout_capacity) > 0;
            }).length;
            profileOption.dataset.spaceCount = String(configuredSpaceCount);
            profileOption.dataset.layoutCapacity = String(profileTotal);
            profileOption.dataset.profileCapacity = String(profileLimit);
            profileOption.textContent = profileOptionLabel(
                profileNameInput ? (profileNameInput.value.trim() || labels.profileMain) : labels.profileMain,
                profileOption
            );
        }
        editor.querySelector('[data-role="profile-capacity"]').textContent = String(profileTotal);
        editor.querySelector('[data-role="venue-capacity"]').textContent = String(profileLimit);
        if (summary) {
            summary.classList.toggle('is-over-capacity', layoutsExceedProfile);
        }
        if (venueLimitCard) {
            venueLimitCard.classList.toggle('is-over-capacity', profileExceedsVenue);
        }
        if (profileCapacityInput) {
            profileCapacityInput.setCustomValidity(profileValidationMessage);
            profileCapacityInput.classList.toggle('is-invalid', profileValidationMessage !== '');
        }
        if (validation) {
            validation.textContent = validationMessage;
            validation.hidden = validationMessage === '';
        }
        renderOverview(
            spaces,
            profileTotal,
            profileLimit,
            venueLimit,
            layoutsExceedProfile,
            profileExceedsVenue
        );
    }

    function renderArea(container, area, areaIndex, spaceIndex) {
        const row = areaTemplate.content.firstElementChild.cloneNode(true);
        row.dataset.areaIndex = String(areaIndex);
        area.color = area.color || '#8A6D3B';
        ['id', 'code', 'name', 'color', 'description', 'capacity', 'published'].forEach(function (field) {
            setValue(row, '[data-area-field="' + field + '"]', area[field]);
        });
        connectInlineHelp(row, 'jem-capacity-space-' + spaceIndex + '-area-' + areaIndex);
        container.appendChild(row);
    }

    function render(spaces) {
        const showInlineHelp = Array.from(editor.querySelectorAll('div.hide-aware-inline-help'))
            .some(function (description) { return !description.classList.contains('d-none'); });
        spacesContainer.replaceChildren();
        if (profileConfigured && !spaces.length) {
            spaces = [blankSpace()];
        }

        spaces.forEach(function (space, spaceIndex) {
            const card = spaceTemplate.content.firstElementChild.cloneNode(true);
            card.dataset.spaceIndex = String(spaceIndex);
            space.client_key = space.client_key || (integer(space.space_id) > 0 ? 'space-' + integer(space.space_id) : 'new-' + (nextClientKey++));
            card.dataset.clientKey = space.client_key;
            card.dataset.layoutRevision = String(integer(space.layout_revision));
            space.space_color = space.space_color || '#2F6F9F';
            space.layout_color = space.layout_color || '#B78324';
            ['space_id', 'space_code', 'space_name', 'space_color', 'space_description', 'space_image', 'space_image_alt', 'layout_id', 'layout_code', 'layout_name', 'layout_color', 'layout_capacity', 'layout_image', 'layout_image_alt'].forEach(function (field) {
                setValue(card, '[data-field="' + field + '"]', space[field]);
            });
            ['space_image_remove', 'layout_image_remove'].forEach(function (field) {
                const removeField = card.querySelector('[data-field="' + field + '"]');
                if (removeField) {
                    removeField.value = integer(space[field]) === 1 ? '1' : '0';
                }
            });
            const spaceUpload = card.querySelector('[data-upload="space"]');
            const layoutUpload = card.querySelector('[data-upload="layout"]');
            if (spaceUpload) {
                spaceUpload.name = 'capacity_space_image[' + spaceIndex + ']';
            }
            if (layoutUpload) {
                layoutUpload.name = 'capacity_layout_image[' + spaceIndex + ']';
            }
            const restoreUpload = function (upload, kind) {
                const file = pendingUploads.get(space.client_key + ':' + kind);
                if (!upload || !file || typeof DataTransfer === 'undefined') {
                    return;
                }
                const transfer = new DataTransfer();
                transfer.items.add(file);
                upload.files = transfer.files;
            };
            restoreUpload(spaceUpload, 'space');
            restoreUpload(layoutUpload, 'layout');
            const spaceCurrent = card.querySelector('[data-role="space-image-current"]');
            const layoutCurrent = card.querySelector('[data-role="layout-image-current"]');
            if (spaceCurrent) {
                spaceCurrent.textContent = space.space_image || '';
            }
            if (layoutCurrent) {
                layoutCurrent.textContent = space.layout_image || '';
            }
            const spaceRemove = card.querySelector('[data-action="remove-space-image"]');
            const layoutRemove = card.querySelector('[data-action="remove-layout-image"]');
            if (spaceRemove) {
                spaceRemove.disabled = !space.space_image && !pendingUploads.has(space.client_key + ':space');
            }
            if (layoutRemove) {
                layoutRemove.disabled = !space.layout_image && !pendingUploads.has(space.client_key + ':layout');
            }
            connectInlineHelp(card, 'jem-capacity-space-' + spaceIndex);

            card.querySelector('[data-role="space-title"]').textContent = space.space_name || (labels.space + ' ' + (spaceIndex + 1));
            const revision = integer(space.layout_revision);
            card.querySelector('[data-role="layout-revision"]').textContent = revision > 0
                ? labels.layoutRevision.replace('%s', String(revision))
                : '';
            const areas = card.querySelector('[data-role="areas"]');
            (Array.isArray(space.areas) ? space.areas : []).forEach(function (area, areaIndex) {
                renderArea(areas, area, areaIndex, spaceIndex);
            });
            spacesContainer.appendChild(card);
            applyInlineHelpState(card, showInlineHelp);
        });
        sync();
    }

    editor.addEventListener('input', function (event) {
        if (event.target.id === 'jform_capacity_profile_name') {
            const profileName = event.target.value.trim() || labels.profileMain;
            const profileTitle = editor.querySelector('[data-role="profile-title"]');
            const profileOption = editor.querySelector('[data-role="profile-option"]');
            if (profileTitle) {
                profileTitle.textContent = profileName;
            }
            if (profileOption) {
                profileOption.textContent = profileOptionLabel(profileName, profileOption);
            }
        }
        if (event.target.matches('[data-field="space_name"]')) {
            const card = event.target.closest('.jem-capacity-space-card');
            const title = card ? card.querySelector('[data-role="space-title"]') : null;
            if (card && title) {
                const fallbackNumber = integer(card.dataset.spaceIndex) + 1;
                title.textContent = event.target.value.trim() || (labels.space + ' ' + fallbackNumber);
            }
        }
        sync();
    });
    editor.addEventListener('change', function (event) {
        if (event.target.matches('[data-upload]') && event.target.files && event.target.files[0]) {
            const card = event.target.closest('.jem-capacity-space-card');
            const kind = event.target.dataset.upload;
            const removeField = card ? card.querySelector('[data-field="' + kind + '_image_remove"]') : null;
            if (removeField) {
                removeField.value = '0';
            }
        }
        sync();
    });
    editor.addEventListener('click', function (event) {
        const button = event.target.closest('[data-action]');
        if (!button) {
            return;
        }

        const action = button.dataset.action;
        let spaces = readSpaces();
        const card = button.closest('.jem-capacity-space-card');
        const spaceIndex = card ? integer(card.dataset.spaceIndex) : -1;
        const areaRow = button.closest('.jem-capacity-area-row');
        const areaIndex = areaRow ? integer(areaRow.dataset.areaIndex) : -1;

        if (action === 'create-profile' || action === 'add-profile') {
            setProfileConfigured(true);
            if (profileIdInput) {
                profileIdInput.value = '0';
            }
            if (profileCodeInput) {
                profileCodeInput.value = '';
            }
            if (profileActionInput) {
                profileActionInput.value = '';
            }
            if (profileSetDefaultInput) {
                profileSetDefaultInput.value = <?php echo $profiles ? "'0'" : "'1'"; ?>;
            }
            if (profileNameInput) {
                profileNameInput.value = labels.newProfile;
            }
            if (profileCapacityInput) {
                profileCapacityInput.value = String(integer(venueCapacityInput ? venueCapacityInput.value : 0));
            }
            spaces = [blankSpace()];
        } else if (action === 'set-default') {
            sync();
            profileSetDefaultInput.value = '1';
            Joomla.submitbutton('venue.apply');
            return;
        } else if (action === 'duplicate-profile') {
            sync();
            profileActionInput.value = 'duplicate';
            Joomla.submitbutton('venue.apply');
            return;
        } else if (action === 'move-profile-up' || action === 'move-profile-down') {
            sync();
            profileActionInput.value = action === 'move-profile-up' ? 'move-up' : 'move-down';
            Joomla.submitbutton('venue.apply');
            return;
        } else if (action === 'archive-profile') {
            if (!window.confirm(labels.confirmArchive)) {
                return;
            }
            sync();
            profileActionInput.value = 'archive';
            Joomla.submitbutton('venue.apply');
            return;
        } else if (action === 'add-space') {
            spaces.push(blankSpace());
        } else if (action === 'remove-space' && spaceIndex >= 0) {
            if (spaces.length > 1 && !window.confirm(labels.confirmRemove)) {
                return;
            }
            spaces.splice(spaceIndex, 1);
        } else if (action === 'space-up' && spaceIndex > 0) {
            [spaces[spaceIndex - 1], spaces[spaceIndex]] = [spaces[spaceIndex], spaces[spaceIndex - 1]];
        } else if (action === 'space-down' && spaceIndex >= 0 && spaceIndex < spaces.length - 1) {
            [spaces[spaceIndex + 1], spaces[spaceIndex]] = [spaces[spaceIndex], spaces[spaceIndex + 1]];
        } else if (action === 'add-area' && spaceIndex >= 0) {
            spaces[spaceIndex].areas.push(blankArea());
        } else if (action === 'remove-area' && spaceIndex >= 0 && areaIndex >= 0) {
            spaces[spaceIndex].areas.splice(areaIndex, 1);
        } else if (action === 'remove-space-image' && spaceIndex >= 0) {
            pendingUploads.delete(spaces[spaceIndex].client_key + ':space');
            spaces[spaceIndex].space_image = '';
            spaces[spaceIndex].space_image_alt = '';
            spaces[spaceIndex].space_image_remove = 1;
        } else if (action === 'remove-layout-image' && spaceIndex >= 0) {
            pendingUploads.delete(spaces[spaceIndex].client_key + ':layout');
            spaces[spaceIndex].layout_image = '';
            spaces[spaceIndex].layout_image_alt = '';
            spaces[spaceIndex].layout_image_remove = 1;
        } else {
            return;
        }

        if (window.jemVenueEditState) {
            window.jemVenueEditState.markDirty();
        }
        render(spaces);
        if ((action === 'create-profile' || action === 'add-profile') && profileNameInput) {
            profileNameInput.focus();
            profileNameInput.select();
        }
    });

    const form = document.getElementById('venue-form');
    if (form) {
        form.addEventListener('submit', sync);
    }
    if (venueCapacityInput) {
        venueCapacityInput.addEventListener('input', sync);
    }
    if (venueNameInput) {
        venueNameInput.addEventListener('input', sync);
    }
    if (profileSelector) {
        profileSelector.addEventListener('change', function () {
            if (!window.confirm(labels.confirmSwitch)) {
                profileSelector.value = String(<?php echo $selectedProfileId; ?>);
                return;
            }
            if (window.jemVenueEditState) {
                window.jemVenueEditState.allowNavigation();
            }
            const url = new URL(window.location.href);
            url.searchParams.set('task', 'venue.edit');
            url.searchParams.set('id', String(<?php echo (int) ($this->item->id ?? 0); ?>));
            url.searchParams.set('profile_id', profileSelector.value);
            window.location.href = url.toString();
        });
    }

    setProfileConfigured(profileConfigured);
    render(Array.isArray(initial.spaces) ? initial.spaces : []);
    if (editor.dataset.restoredUnsaved === '1' && window.jemVenueEditState) {
        window.jemVenueEditState.markDirty();
    }
});
</script>
