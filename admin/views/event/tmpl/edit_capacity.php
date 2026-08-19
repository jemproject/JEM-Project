<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;

$requirements = (array) ($this->item->pricing_requirements ?? array());
$venueExists = !empty($requirements['venue_exists']);
$capacityReady = !empty($requirements['capacity_ready']);
$isSaved = !empty($this->item->id);
$configurationOptions = array_values((array) ($requirements['configuration_options'] ?? array()));
$configurationAssignments = array_values((array) ($requirements['configuration_assignments'] ?? array()));
$configurationProfiles = array_values((array) ($requirements['configuration_profiles'] ?? array()));
$configurationCustomRequired = !empty($requirements['configuration_custom_required']);
$selectedConfigurationKey = (string) ($this->item->venue_configuration_key ?? '');
$selectedAssignmentIds = json_decode((string) ($this->item->venue_assignment_ids ?? '[]'), true);
if (!is_array($selectedAssignmentIds)) {
    $selectedAssignmentIds = array();
}
$allocationMode = (string) ($this->item->venue_allocation_mode ?? 'none');
$existingCommerce = in_array((string) ($this->item->pricing_mode ?? 'classic'), array('priced', 'single', 'multiple'), true);
$canOverrideConflict = Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_jem');
?>

<div class="jem-event-capacity" data-capacity-ready="<?php echo $capacityReady ? 1 : 0; ?>">
    <fieldset class="adminform">
        <legend><?php echo Text::_('COM_JEM_EVENT_VENUE_CAPACITY_TAB'); ?></legend>
        <p class="text-muted"><?php echo Text::_('COM_JEM_EVENT_VENUE_CAPACITY_INTRO'); ?></p>
        <div class="jem-event-pricing-grid">
            <?php echo $this->form->renderField('venue_allocation_mode'); ?>
            <?php echo $this->form->renderField('capacity_mode'); ?>
        </div>
    </fieldset>

    <?php if ($existingCommerce) : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_JEM_EVENT_VENUE_CAPACITY_EXISTING_COMMERCE'); ?></div>
    <?php else : ?>
        <div class="alert alert-info"><?php echo Text::_('COM_JEM_EVENT_VENUE_CAPACITY_COMMERCE_DEFERRED'); ?></div>
    <?php endif; ?>

    <div class="jem-event-capacity-details" <?php echo $allocationMode === 'none' ? 'hidden' : ''; ?>>
        <div class="jem-event-pricing-readiness" aria-label="<?php echo htmlspecialchars(Text::_('COM_JEM_EVENT_VENUE_CAPACITY_TAB'), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="jem-event-pricing-check <?php echo $venueExists ? 'is-ready' : 'is-missing'; ?>" data-requirement="venue">
                <strong><?php echo Text::_('COM_JEM_EVENT_VENUE_CAPACITY_REQUIREMENT_VENUE'); ?></strong>
                <span><?php echo $venueExists ? Text::_('JYES') : Text::_('JNO'); ?></span>
            </div>
            <div class="jem-event-pricing-check <?php echo $capacityReady ? 'is-ready' : 'is-missing'; ?>" data-requirement="configuration">
                <strong><?php echo Text::_('COM_JEM_EVENT_VENUE_CAPACITY_REQUIREMENT_CONFIGURATION'); ?></strong>
                <span><?php echo $capacityReady
                    ? Text::sprintf(
                        'COM_JEM_EVENT_PRICING_CONFIGURATION_SUMMARY',
                        (int) ($requirements['space_count'] ?? 0),
                        (int) ($requirements['configured_capacity'] ?? 0),
                        (int) ($requirements['profile_revision'] ?? 0)
                    )
                    : Text::_('JNO'); ?></span>
            </div>
        </div>

        <div class="alert alert-warning" data-capacity-notice="missing" <?php echo $capacityReady ? 'hidden' : ''; ?>>
            <?php echo Text::_('COM_JEM_EVENT_VENUE_CAPACITY_MISSING'); ?>
        </div>

        <fieldset class="adminform jem-event-venue-configuration" <?php echo $capacityReady ? '' : 'hidden'; ?>>
            <legend><?php echo Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION'); ?></legend>
            <p class="text-muted"><?php echo Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION_DESC'); ?></p>
            <label for="jem-event-venue-configuration-select" class="form-label"><?php echo Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION_SELECT'); ?></label>
            <select id="jem-event-venue-configuration-select" class="form-select">
                <?php if ($selectedConfigurationKey === 'saved') : ?>
                    <option value="saved" selected><?php echo Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION_SAVED'); ?></option>
                <?php endif; ?>
                <?php foreach ($configurationOptions as $option) : ?>
                    <option value="<?php echo htmlspecialchars((string) $option['key'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $selectedConfigurationKey === (string) $option['key'] ? ' selected' : ''; ?>>
                        <?php echo htmlspecialchars((string) $option['label'], ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
                <?php if ($configurationCustomRequired || $selectedConfigurationKey === 'custom') : ?>
                    <option value="custom"<?php echo $selectedConfigurationKey === 'custom' ? ' selected' : ''; ?>><?php echo Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION_CUSTOM'); ?></option>
                <?php endif; ?>
            </select>
            <div class="jem-event-venue-custom mt-3" <?php echo $selectedConfigurationKey === 'custom' ? '' : 'hidden'; ?>>
                <p class="form-text"><?php echo Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION_CUSTOM_DESC'); ?></p>
                <label class="form-label" for="jem-event-venue-custom-profile"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_PROFILE'); ?></label>
                <select class="form-select mb-3" id="jem-event-venue-custom-profile">
                    <?php foreach ($configurationProfiles as $profile) : ?>
                        <option value="<?php echo (int) $profile['profile_id']; ?>"><?php echo htmlspecialchars((string) $profile['profile_name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="jem-event-venue-custom-grid">
                    <?php foreach ($configurationAssignments as $assignment) : ?>
                        <?php $assignmentId = (int) $assignment['id']; ?>
                        <label class="jem-event-venue-custom-option" data-profile-id="<?php echo (int) ($assignment['profile_id'] ?? 0); ?>">
                            <input type="checkbox" value="<?php echo $assignmentId; ?>" data-profile-id="<?php echo (int) ($assignment['profile_id'] ?? 0); ?>"<?php echo in_array($assignmentId, array_map('intval', $selectedAssignmentIds), true) ? ' checked' : ''; ?>>
                            <span><strong><?php echo htmlspecialchars((string) $assignment['space_name'], ENT_QUOTES, 'UTF-8'); ?></strong> <?php echo htmlspecialchars((string) $assignment['layout_name'], ENT_QUOTES, 'UTF-8'); ?> &middot; <?php echo (int) $assignment['capacity']; ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="jem-event-venue-configuration-preview mt-3" aria-live="polite"></div>
            <?php if ($isSaved) : ?>
                <div class="mt-3"><?php echo $this->form->renderField('reload_venue_capacity'); ?></div>
            <?php endif; ?>
        </fieldset>

        <div class="alert alert-info" data-capacity-notice="first-save" <?php echo $capacityReady && !$isSaved ? '' : 'hidden'; ?>><?php echo Text::_('COM_JEM_EVENT_VENUE_CAPACITY_FIRST_SAVE_NOTICE'); ?></div>
        <div class="alert alert-info" data-capacity-notice="snapshot" <?php echo $capacityReady && $isSaved ? '' : 'hidden'; ?>><?php echo Text::_('COM_JEM_EVENT_VENUE_CAPACITY_SAVED_NOTICE'); ?></div>
        <?php if ($canOverrideConflict) : ?>
            <fieldset class="adminform jem-event-space-conflict-override">
                <legend><?php echo Text::_('COM_JEM_EVENT_SPACE_CONFLICT_OVERRIDE'); ?></legend>
                <?php echo $this->form->renderField('space_conflict_override'); ?>
                <?php echo $this->form->renderField('space_conflict_reason'); ?>
            </fieldset>
        <?php endif; ?>
    </div>

    <?php echo $this->form->getInput('venue_profile_id'); ?>
    <?php echo $this->form->getInput('venue_profile_revision'); ?>
    <?php echo $this->form->getInput('venue_snapshot'); ?>
    <?php echo $this->form->getInput('venue_configuration_key'); ?>
    <?php echo $this->form->getInput('venue_assignment_ids'); ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('.jem-event-capacity');
    const allocationMode = document.getElementById('jform_venue_allocation_mode');
    const capacityMode = document.getElementById('jform_capacity_mode');
    if (!root || !allocationMode || !capacityMode) {
        return;
    }

    const details = root.querySelector('.jem-event-capacity-details');
    const configurationFieldset = root.querySelector('.jem-event-venue-configuration');
    const configurationSelect = document.getElementById('jem-event-venue-configuration-select');
    const configurationCustom = root.querySelector('.jem-event-venue-custom');
    const configurationCustomGrid = root.querySelector('.jem-event-venue-custom-grid');
    const configurationCustomProfile = document.getElementById('jem-event-venue-custom-profile');
    const configurationPreview = root.querySelector('.jem-event-venue-configuration-preview');
    const configurationKeyInput = document.getElementById('jform_venue_configuration_key');
    const assignmentIdsInput = document.getElementById('jform_venue_assignment_ids');
    const venueInput = document.getElementById('jform_locid_id');
    const reloadInput = document.getElementById('jform_reload_venue_capacity');
    const ajaxToken = <?php echo json_encode(Session::getFormToken()); ?>;
    const eventId = <?php echo (int) ($this->item->id ?? 0); ?>;
    const savedAssignmentIds = <?php echo json_encode(array_values(array_map('intval', $selectedAssignmentIds))); ?>;
    const savedSnapshotLabel = <?php echo json_encode(Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION_SAVED')); ?>;
    let configurationState = {
        options: <?php echo json_encode($configurationOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        profiles: <?php echo json_encode($configurationProfiles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        assignments: <?php echo json_encode($configurationAssignments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        customRequired: <?php echo $configurationCustomRequired ? 'true' : 'false'; ?>
    };

    const selectedIds = function () {
        if (!configurationSelect) {
            return [];
        }
        if (configurationSelect.value === 'saved') {
            return savedAssignmentIds.slice();
        }
        if (configurationSelect.value === 'custom') {
            const profileId = Number(configurationCustomProfile ? configurationCustomProfile.value : 0);
            return Array.from(configurationCustomGrid.querySelectorAll('input:checked'))
                .filter(function (input) { return Number(input.dataset.profileId || 0) === profileId; })
                .map(function (input) { return Number(input.value); });
        }
        const option = configurationState.options.find(function (item) { return item.key === configurationSelect.value; });
        return option ? option.assignment_ids.map(Number) : [];
    };

    const updateSelection = function (changed) {
        if (!configurationSelect) {
            return;
        }
        const ids = selectedIds();
        configurationKeyInput.value = configurationSelect.value;
        assignmentIdsInput.value = JSON.stringify(ids);
        configurationCustom.hidden = configurationSelect.value !== 'custom';
        if (configurationCustomProfile) {
            configurationCustomGrid.querySelectorAll('[data-profile-id]').forEach(function (element) {
                element.hidden = Number(element.dataset.profileId || 0) !== Number(configurationCustomProfile.value || 0);
            });
        }
        allocationMode.value = configurationSelect.value === 'custom' ? 'custom' : allocationMode.value;
        const assignments = configurationState.assignments.filter(function (assignment) { return ids.indexOf(Number(assignment.id)) !== -1; });
        const capacity = assignments.reduce(function (sum, assignment) { return sum + Number(assignment.capacity || 0); }, 0);
        const savedSnapshot = configurationSelect.value === 'saved';
        configurationPreview.className = 'jem-event-venue-configuration-preview mt-3 alert ' + (assignments.length || savedSnapshot ? 'alert-success' : 'alert-warning');
        configurationPreview.textContent = assignments.length
            ? assignments.map(function (assignment) { return assignment.space_name + ' / ' + assignment.layout_name; }).join(' · ') + ' — ' + capacity
            : (savedSnapshot
                ? savedSnapshotLabel
                : <?php echo json_encode(Text::_('COM_JEM_EVENT_VENUE_CAPACITY_MISSING')); ?>);
        if (changed && reloadInput) {
            reloadInput.checked = true;
            reloadInput.value = '1';
        }
    };

    const renderCustomAssignments = function () {
        configurationCustomGrid.replaceChildren();
        configurationState.assignments.forEach(function (assignment) {
            const label = document.createElement('label');
            label.className = 'jem-event-venue-custom-option';
            label.dataset.profileId = assignment.profile_id;
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.value = assignment.id;
            input.dataset.profileId = assignment.profile_id;
            const span = document.createElement('span');
            span.textContent = assignment.space_name + ' / ' + assignment.layout_name + ' · ' + assignment.capacity;
            label.append(input, span);
            configurationCustomGrid.appendChild(label);
        });
    };

    const applyVenuePayload = function (payload) {
        configurationState = {
            options: Array.isArray(payload.configuration_options) ? payload.configuration_options : [],
            profiles: Array.isArray(payload.configuration_profiles) ? payload.configuration_profiles : [],
            assignments: Array.isArray(payload.configuration_assignments) ? payload.configuration_assignments : [],
            customRequired: Boolean(payload.configuration_custom_required)
        };
        root.dataset.capacityReady = payload.capacity_ready ? '1' : '0';
        root.querySelector('[data-requirement="venue"]').classList.toggle('is-ready', Boolean(payload.venue_exists));
        root.querySelector('[data-requirement="venue"]').classList.toggle('is-missing', !payload.venue_exists);
        root.querySelector('[data-requirement="configuration"]').classList.toggle('is-ready', Boolean(payload.capacity_ready));
        root.querySelector('[data-requirement="configuration"]').classList.toggle('is-missing', !payload.capacity_ready);
        root.querySelector('[data-capacity-notice="missing"]').hidden = Boolean(payload.capacity_ready);
        configurationFieldset.hidden = !payload.capacity_ready;
        configurationSelect.replaceChildren();
        configurationState.options.forEach(function (configuration) {
            const option = document.createElement('option');
            option.value = configuration.key;
            option.textContent = configuration.label;
            configurationSelect.appendChild(option);
        });
        if (configurationState.customRequired) {
            const custom = document.createElement('option');
            custom.value = 'custom';
            custom.textContent = <?php echo json_encode(Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION_CUSTOM')); ?>;
            configurationSelect.appendChild(custom);
        }
        renderCustomAssignments();
        if (configurationCustomProfile) {
            configurationCustomProfile.replaceChildren();
            configurationState.profiles.forEach(function (profile) {
                const option = document.createElement('option');
                option.value = profile.profile_id;
                option.textContent = profile.profile_name;
                configurationCustomProfile.appendChild(option);
            });
        }
        updateSelection(true);
    };

    const loadVenueConfigurations = function () {
        const venueId = Number(venueInput ? venueInput.value : 0);
        const query = new URLSearchParams({option: 'com_jem', task: 'event.venueConfigurations', format: 'json', venue_id: String(venueId), id: String(eventId)});
        query.set(ajaxToken, '1');
        fetch('index.php?' + query.toString(), {headers: {'Accept': 'application/json'}})
            .then(function (response) { if (!response.ok) { throw new Error('HTTP ' + response.status); } return response.json(); })
            .then(function (response) { if (!response.success || !response.data) { throw new Error(response.message || 'Invalid response'); } applyVenuePayload(response.data); })
            .catch(function (error) {
                configurationFieldset.hidden = false;
                configurationPreview.className = 'jem-event-venue-configuration-preview mt-3 alert alert-warning';
                configurationPreview.textContent = <?php echo json_encode(Text::_('COM_JEM_EVENT_VENUE_CONFIGURATION_LOAD_ERROR')); ?> + ' ' + error.message;
            });
    };

    const updateMode = function () {
        const enabled = allocationMode.value !== 'none';
        details.hidden = !enabled;
        capacityMode.disabled = !enabled;
        if (!enabled) {
            configurationKeyInput.value = '';
            assignmentIdsInput.value = '[]';
        }
    };

    allocationMode.addEventListener('change', function () {
        if (allocationMode.value === 'custom' && configurationSelect) {
            configurationSelect.value = 'custom';
        } else if (allocationMode.value === 'profile' && configurationSelect && configurationSelect.value === 'custom') {
            configurationSelect.selectedIndex = 0;
        }
        updateMode();
        updateSelection(true);
    });
    capacityMode.addEventListener('change', function () { if (reloadInput) { reloadInput.checked = true; reloadInput.value = '1'; } });
    if (configurationSelect) {
        configurationSelect.addEventListener('change', function () {
            allocationMode.value = configurationSelect.value === 'custom' ? 'custom' : 'profile';
            updateMode();
            updateSelection(true);
        });
    }
    configurationCustomGrid.addEventListener('change', function () { updateSelection(true); });
    if (configurationCustomProfile) {
        const selectedAssignment = configurationState.assignments.find(function (assignment) {
            return savedAssignmentIds.indexOf(Number(assignment.id)) !== -1;
        });
        if (selectedAssignment) {
            configurationCustomProfile.value = String(selectedAssignment.profile_id);
        }
        configurationCustomProfile.addEventListener('change', function () {
            configurationCustomGrid.querySelectorAll('input:checked').forEach(function (input) {
                if (Number(input.dataset.profileId || 0) !== Number(configurationCustomProfile.value || 0)) {
                    input.checked = false;
                }
            });
            updateSelection(true);
        });
    }
    if (venueInput) {
        venueInput.addEventListener('change', loadVenueConfigurations);
    }

    updateMode();
    updateSelection(false);
});
</script>
