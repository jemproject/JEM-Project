<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$initialSpaces = array_values((array) ($this->item->capacity_spaces ?? array()));
if (!$initialSpaces) {
    $initialSpaces[] = array(
        'space_id'          => 0,
        'space_code'        => '',
        'space_name'        => '',
        'space_description' => '',
        'layout_id'         => 0,
        'layout_code'       => '',
        'layout_name'       => '',
        'layout_revision'   => 0,
        'layout_capacity'   => (int) ($this->item->capacity ?? 0),
        'areas'             => array(),
    );
}

$initialConfiguration = json_encode(
    array('spaces' => $initialSpaces),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<div class="jem-venue-capacity-editor" id="jem-venue-capacity-editor">
    <?php echo $this->form->getInput('capacity_configuration_submitted'); ?>
    <?php echo $this->form->getInput('capacity_configuration_json'); ?>
    <?php echo $this->form->getInput('capacity_profile_id'); ?>

    <div class="alert alert-info">
        <strong><?php echo Text::_('COM_JEM_VENUE_CAPACITY_CONFIGURATION'); ?></strong><br>
        <?php echo Text::_('COM_JEM_VENUE_CAPACITY_CONFIGURATION_INTRO'); ?>
    </div>

    <fieldset class="adminform">
        <legend><?php echo Text::_('COM_JEM_VENUE_CAPACITY_PROFILE'); ?></legend>
        <div class="jem-capacity-field-grid">
            <?php echo $this->form->renderField('capacity_profile_name'); ?>
            <?php echo $this->form->renderField('capacity_profile_revision'); ?>
        </div>
        <div class="jem-capacity-profile-summary" aria-live="polite">
            <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_PROFILE_TOTAL'); ?>:</span>
            <strong data-role="profile-capacity">0</strong>
            <span>/ <?php echo Text::_('COM_JEM_VENUE_CAPACITY_PHYSICAL_LIMIT'); ?>:</span>
            <strong data-role="venue-capacity">0</strong>
        </div>
    </fieldset>

    <div class="jem-capacity-spaces" data-role="spaces"></div>
    <div>
        <button type="button" class="btn btn-outline-primary" data-action="add-space">
            <span class="icon-plus" aria-hidden="true"></span>
            <?php echo Text::_('COM_JEM_VENUE_CAPACITY_ADD_SPACE'); ?>
        </button>
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
                <div class="jem-capacity-field-grid">
                    <label>
                        <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_SPACE_NAME'); ?></span>
                        <input type="text" class="form-control" maxlength="255" data-field="space_name">
                    </label>
                    <label>
                        <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_SPACE_CODE'); ?></span>
                        <input type="text" class="form-control" maxlength="64" data-field="space_code">
                        <small class="form-text"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_SPACE_CODE_DESC'); ?></small>
                    </label>
                </div>
                <label class="d-block mt-3">
                    <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_SPACE_DESCRIPTION'); ?></span>
                    <textarea class="form-control" rows="2" data-field="space_description"></textarea>
                </label>
            </div>
            <div class="jem-capacity-section jem-capacity-section-layout">
                <h4 class="h6"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT'); ?></h4>
                <div class="jem-capacity-field-grid jem-capacity-layout-grid">
                    <label>
                        <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT_NAME'); ?></span>
                        <input type="text" class="form-control" maxlength="255" data-field="layout_name">
                    </label>
                    <label>
                        <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT_CODE'); ?></span>
                        <input type="text" class="form-control" maxlength="64" data-field="layout_code">
                    </label>
                    <label>
                        <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT_CAPACITY'); ?></span>
                        <input type="number" class="form-control" min="0" step="1" data-field="layout_capacity">
                    </label>
                </div>
                <p class="form-text mb-0" data-role="layout-revision"></p>
            </div>
            <div class="jem-capacity-section jem-capacity-section-areas">
                <div class="jem-capacity-areas-heading">
                    <div>
                        <h4 class="h6 mb-1"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_AREAS'); ?></h4>
                        <p class="form-text mb-0"><?php echo Text::_('COM_JEM_VENUE_CAPACITY_AREAS_DESC'); ?></p>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-action="add-area">
                        <span class="icon-plus" aria-hidden="true"></span>
                        <?php echo Text::_('COM_JEM_VENUE_CAPACITY_ADD_AREA'); ?>
                    </button>
                </div>
                <div class="jem-capacity-area-list" data-role="areas"></div>
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
        </label>
        <label>
            <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_AREA_CODE'); ?></span>
            <input type="text" class="form-control" maxlength="64" data-area-field="code">
        </label>
        <label>
            <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_AREA_CAPACITY'); ?></span>
            <input type="number" class="form-control" min="0" step="1" data-area-field="capacity">
        </label>
        <label>
            <span><?php echo Text::_('JSTATUS'); ?></span>
            <select class="form-select" data-area-field="published">
                <option value="1"><?php echo Text::_('JPUBLISHED'); ?></option>
                <option value="0"><?php echo Text::_('JUNPUBLISHED'); ?></option>
            </select>
        </label>
        <label class="jem-capacity-area-description">
            <span><?php echo Text::_('COM_JEM_VENUE_CAPACITY_AREA_DESCRIPTION'); ?></span>
            <textarea class="form-control" rows="2" data-area-field="description"></textarea>
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
    const spaceTemplate = document.getElementById('jem-capacity-space-template');
    const areaTemplate = document.getElementById('jem-capacity-area-template');
    const venueCapacityInput = document.getElementById('jform_capacity');
    const labels = <?php echo json_encode(array(
        'space'          => Text::_('COM_JEM_VENUE_CAPACITY_SPACE_NUMBER'),
        'layoutRevision' => Text::_('COM_JEM_VENUE_CAPACITY_LAYOUT_REVISION'),
        'confirmRemove'  => Text::_('COM_JEM_VENUE_CAPACITY_CONFIRM_REMOVE_SPACE'),
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    if (!editor || !spacesContainer || !hidden || !spaceTemplate || !areaTemplate) {
        return;
    }

    let initial = {spaces: []};
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
            space_description: '',
            layout_id: 0,
            layout_code: '',
            layout_name: '',
            layout_revision: 0,
            layout_capacity: 0,
            areas: []
        };
    }

    function blankArea() {
        return {id: 0, code: '', name: '', description: '', capacity: 0, allocation_mode: 'quantity', published: 1};
    }

    function integer(value) {
        const number = Number.parseInt(value, 10);
        return Number.isFinite(number) && number >= 0 ? number : 0;
    }

    function setValue(scope, selector, value) {
        const field = scope.querySelector(selector);
        if (field) {
            field.value = value === null || value === undefined ? '' : value;
        }
        return field;
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
                    description: areaValue('description').trim(),
                    capacity: integer(areaValue('capacity')),
                    allocation_mode: 'quantity',
                    published: integer(areaValue('published')) === 1 ? 1 : 0
                };
            });

            return {
                space_id: integer(value('space_id')),
                space_code: value('space_code').trim(),
                space_name: value('space_name').trim(),
                space_description: value('space_description').trim(),
                layout_id: integer(value('layout_id')),
                layout_code: value('layout_code').trim(),
                layout_name: value('layout_name').trim(),
                layout_revision: integer(card.dataset.layoutRevision),
                layout_capacity: integer(value('layout_capacity')),
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
        editor.querySelector('[data-role="profile-capacity"]').textContent = String(profileTotal);
        editor.querySelector('[data-role="venue-capacity"]').textContent = String(integer(venueCapacityInput ? venueCapacityInput.value : 0));
    }

    function renderArea(container, area, areaIndex) {
        const row = areaTemplate.content.firstElementChild.cloneNode(true);
        row.dataset.areaIndex = String(areaIndex);
        ['id', 'code', 'name', 'description', 'capacity', 'published'].forEach(function (field) {
            setValue(row, '[data-area-field="' + field + '"]', area[field]);
        });
        container.appendChild(row);
    }

    function render(spaces) {
        spacesContainer.replaceChildren();
        if (!spaces.length) {
            spaces = [blankSpace()];
        }

        spaces.forEach(function (space, spaceIndex) {
            const card = spaceTemplate.content.firstElementChild.cloneNode(true);
            card.dataset.spaceIndex = String(spaceIndex);
            card.dataset.layoutRevision = String(integer(space.layout_revision));
            ['space_id', 'space_code', 'space_name', 'space_description', 'layout_id', 'layout_code', 'layout_name', 'layout_capacity'].forEach(function (field) {
                setValue(card, '[data-field="' + field + '"]', space[field]);
            });

            const stableSpaceCode = card.querySelector('[data-field="space_code"]');
            if (stableSpaceCode && integer(space.space_id) > 0) {
                stableSpaceCode.readOnly = true;
                stableSpaceCode.classList.add('readonly');
            }

            card.querySelector('[data-role="space-title"]').textContent = space.space_name || (labels.space + ' ' + (spaceIndex + 1));
            const revision = integer(space.layout_revision);
            card.querySelector('[data-role="layout-revision"]').textContent = revision > 0
                ? labels.layoutRevision.replace('%s', String(revision))
                : '';
            const areas = card.querySelector('[data-role="areas"]');
            (Array.isArray(space.areas) ? space.areas : []).forEach(function (area, areaIndex) {
                renderArea(areas, area, areaIndex);
            });
            spacesContainer.appendChild(card);
        });
        sync();
    }

    editor.addEventListener('input', sync);
    editor.addEventListener('change', sync);
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

        if (action === 'add-space') {
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
        } else {
            return;
        }

        render(spaces);
    });

    const form = document.getElementById('venue-form');
    if (form) {
        form.addEventListener('submit', sync);
    }
    if (venueCapacityInput) {
        venueCapacityInput.addEventListener('input', sync);
    }

    render(Array.isArray(initial.spaces) ? initial.spaces : []);
});
</script>
