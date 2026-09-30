<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$levels = array_map(static function ($level) {
    return array(
        'id' => (int) $level->id,
        'title' => (string) $level->title,
        'min_age' => (int) $level->min_age,
        'max_age' => (int) $level->max_age,
        'badge_label' => (string) $level->badge_label,
        'badge_background' => (string) $level->badge_background,
        'badge_text' => (string) $level->badge_text,
        'published' => (int) $level->published,
    );
}, $this->ageLevels ?? array());
?>

<div class="alert alert-info">
    <?php echo Text::_('COM_JEM_AGE_ACCESS_SETTINGS_DESC'); ?>
</div>
<div class="alert alert-warning">
    <?php echo Text::_('COM_JEM_AGE_ACCESS_PROFILE_DOB_NOTICE'); ?>
</div>

<input type="hidden" name="jem_age_levels" id="jem-age-levels-payload" value="">

<style>
#jem-age-levels-table {
    table-layout: fixed;
    width: 100%;
}

#jem-age-levels-table th,
#jem-age-levels-table td {
    padding: .45rem .35rem;
    vertical-align: middle;
}

#jem-age-levels-table .form-control,
#jem-age-levels-table .input-group {
    min-width: 0;
}

#jem-age-levels-table .jem-age-color-control {
    display: grid;
    grid-template-columns: 2.25rem minmax(5.25rem, 1fr);
}

#jem-age-levels-table .jem-age-color-control .form-control-color {
    width: 2.25rem;
    min-width: 2.25rem;
    height: 2.25rem;
    padding: 0;
}

#jem-age-levels-table .jem-age-color-control input[type="text"] {
    width: 100%;
    min-width: 0;
    font-family: var(--font-monospace, monospace);
}

#jem-age-levels-table .jem-age-color-control .form-control-color::-webkit-color-swatch-wrapper {
    padding: 0;
}

#jem-age-levels-table .jem-age-color-control .form-control-color::-webkit-color-swatch {
    border: 0;
    border-radius: .2rem;
}

#jem-age-levels-table .jem-age-color-control .form-control-color::-moz-color-swatch {
    border: 0;
    border-radius: .2rem;
}

#jem-age-levels-table .jem-age-cell--published {
    text-align: center;
}

#jem-age-levels-table .jem-age-preview {
    white-space: nowrap;
}

@media (max-width: 1100px) {
    #jem-age-levels-table th,
    #jem-age-levels-table td {
        padding-inline: .2rem;
    }

    #jem-age-levels-table .btn-sm {
        padding-inline: .4rem;
    }
}

@media (max-width: 767.98px) {
    .jem-age-levels-responsive {
        overflow: visible;
    }

    #jem-age-levels-table,
    #jem-age-levels-table tbody {
        display: block;
    }

    #jem-age-levels-table thead {
        display: none;
    }

    #jem-age-levels-table tbody tr {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: .65rem .75rem;
        margin-bottom: .75rem;
        padding: .75rem;
        border: 1px solid var(--border-color, #dfe3e7);
        border-radius: .35rem;
    }

    #jem-age-levels-table tbody td {
        display: block;
        padding: 0;
        border: 0;
        text-align: start;
    }

    #jem-age-levels-table tbody td::before {
        display: block;
        margin-bottom: .2rem;
        font-size: .8rem;
        font-weight: 600;
        content: attr(data-label);
    }

    #jem-age-levels-table .jem-age-cell--title,
    #jem-age-levels-table .jem-age-cell--background,
    #jem-age-levels-table .jem-age-cell--text {
        grid-column: 1 / -1;
    }

    #jem-age-levels-table .jem-age-cell--published {
        text-align: start;
    }

    #jem-age-levels-table .jem-age-cell--delete::before {
        content: '';
    }
}
</style>

<div class="table-responsive jem-age-levels-responsive">
    <table class="table table-sm" id="jem-age-levels-table">
        <colgroup>
            <col style="width:18%">
            <col style="width:9%">
            <col style="width:9%">
            <col style="width:8%">
            <col style="width:15%">
            <col style="width:15%">
            <col style="width:7%">
            <col style="width:6%">
            <col style="width:9%">
            <col style="width:4%">
        </colgroup>
        <thead>
            <tr>
                <th><?php echo Text::_('COM_JEM_AGE_LEVEL_TITLE'); ?></th>
                <th><?php echo Text::_('COM_JEM_AGE_LEVEL_MINIMUM'); ?></th>
                <th><?php echo Text::_('COM_JEM_AGE_LEVEL_MAXIMUM'); ?></th>
                <th><?php echo Text::_('COM_JEM_AGE_LEVEL_BADGE_LABEL'); ?></th>
                <th><?php echo Text::_('COM_JEM_AGE_LEVEL_BACKGROUND'); ?></th>
                <th><?php echo Text::_('COM_JEM_AGE_LEVEL_TEXT_COLOR'); ?></th>
                <th><?php echo Text::_('COM_JEM_AGE_LEVEL_PREVIEW'); ?></th>
                <th class="text-center"><?php echo Text::_('JPUBLISHED'); ?></th>
                <th class="text-end"><?php echo Text::_('COM_JEM_AGE_LEVEL_ORDER'); ?></th>
                <th class="text-end"></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<button type="button" class="btn btn-secondary" id="jem-add-age-level">
    <span class="icon-plus" aria-hidden="true"></span>
    <?php echo Text::_('COM_JEM_AGE_LEVEL_ADD'); ?>
</button>

<script>
(function() {
    var initialLevels = <?php echo json_encode($levels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var body = document.querySelector('#jem-age-levels-table tbody');
    var payload = document.getElementById('jem-age-levels-payload');
    var addButton = document.getElementById('jem-add-age-level');
    var moveUpLabel = <?php echo json_encode(Text::_('JLIB_HTML_MOVE_UP'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var moveDownLabel = <?php echo json_encode(Text::_('JLIB_HTML_MOVE_DOWN'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var deleteLabel = <?php echo json_encode(Text::_('JACTION_DELETE'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var columnLabels = <?php echo json_encode(array(
        'title' => Text::_('COM_JEM_AGE_LEVEL_TITLE'),
        'minimum' => Text::_('COM_JEM_AGE_LEVEL_MINIMUM'),
        'maximum' => Text::_('COM_JEM_AGE_LEVEL_MAXIMUM'),
        'badge' => Text::_('COM_JEM_AGE_LEVEL_BADGE_LABEL'),
        'background' => Text::_('COM_JEM_AGE_LEVEL_BACKGROUND'),
        'text' => Text::_('COM_JEM_AGE_LEVEL_TEXT_COLOR'),
        'preview' => Text::_('COM_JEM_AGE_LEVEL_PREVIEW'),
        'published' => Text::_('JPUBLISHED'),
        'order' => Text::_('COM_JEM_AGE_LEVEL_ORDER'),
    ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function escapeAttribute(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function rowHtml(level) {
        var background = level.badge_background || '#1F2937';
        var text = level.badge_text || '#FFFFFF';

        return '<tr data-id="' + Number(level.id || 0) + '">' +
            '<td class="jem-age-cell--title" data-label="' + escapeAttribute(columnLabels.title) + '"><input type="text" class="form-control form-control-sm jem-age-title" maxlength="100" value="' + escapeAttribute(level.title) + '" required></td>' +
            '<td data-label="' + escapeAttribute(columnLabels.minimum) + '"><input type="number" class="form-control form-control-sm jem-age-minimum" min="0" max="99" value="' + Number(level.min_age || 0) + '" required></td>' +
            '<td data-label="' + escapeAttribute(columnLabels.maximum) + '"><input type="number" class="form-control form-control-sm jem-age-maximum" min="0" max="99" value="' + Number(level.max_age ?? 99) + '" required></td>' +
            '<td data-label="' + escapeAttribute(columnLabels.badge) + '"><input type="text" class="form-control form-control-sm jem-age-label" maxlength="32" value="' + escapeAttribute(level.badge_label) + '" placeholder="18+"></td>' +
            colorControl('background', background, columnLabels.background) +
            colorControl('text', text, columnLabels.text) +
            '<td data-label="' + escapeAttribute(columnLabels.preview) + '"><span class="badge jem-age-preview" style="background:' + escapeAttribute(background) + ';color:' + escapeAttribute(text) + '"></span></td>' +
            '<td class="jem-age-cell--published" data-label="' + escapeAttribute(columnLabels.published) + '"><input type="checkbox" class="form-check-input jem-age-published"' + (Number(level.published) ? ' checked' : '') + '></td>' +
            '<td class="text-end text-nowrap" data-label="' + escapeAttribute(columnLabels.order) + '">' +
                '<button type="button" class="btn btn-sm btn-secondary jem-age-up" title="' + escapeAttribute(moveUpLabel) + '"><span class="icon-arrow-up" aria-hidden="true"></span></button> ' +
                '<button type="button" class="btn btn-sm btn-secondary jem-age-down" title="' + escapeAttribute(moveDownLabel) + '"><span class="icon-arrow-down" aria-hidden="true"></span></button>' +
            '</td>' +
            '<td class="text-end text-nowrap jem-age-cell--delete">' +
                '<button type="button" class="btn btn-sm btn-danger jem-age-remove" title="' + escapeAttribute(deleteLabel) + '"><span class="icon-trash" aria-hidden="true"></span></button>' +
            '</td></tr>';
    }

    function colorControl(name, value, label) {
        return '<td class="jem-age-cell--' + name + '" data-label="' + escapeAttribute(label) + '"><div class="input-group flex-nowrap jem-age-color-control">' +
            '<input type="color" class="form-control form-control-color jem-age-' + name + '" value="' + escapeAttribute(value) + '">' +
            '<input type="text" class="form-control form-control-sm jem-age-' + name + '-hex" maxlength="7" value="' + escapeAttribute(value) + '" aria-label="#RRGGBB">' +
            '</div></td>';
    }

    function automaticLabel(row) {
        var minimum = Number(row.querySelector('.jem-age-minimum').value || 0);
        var maximum = Number(row.querySelector('.jem-age-maximum').value || 99);

        return maximum < 99 ? minimum + '-' + maximum : minimum + '+';
    }

    function updatePreview(row) {
        var label = row.querySelector('.jem-age-label').value.trim() || automaticLabel(row);
        var preview = row.querySelector('.jem-age-preview');
        preview.textContent = label;
        preview.style.backgroundColor = row.querySelector('.jem-age-background').value;
        preview.style.color = row.querySelector('.jem-age-text').value;
    }

    function updatePayload() {
        payload.value = JSON.stringify(Array.prototype.map.call(body.querySelectorAll('tr'), function(row) {
            return {
                id: Number(row.getAttribute('data-id') || 0),
                title: row.querySelector('.jem-age-title').value,
                min_age: Number(row.querySelector('.jem-age-minimum').value),
                max_age: Number(row.querySelector('.jem-age-maximum').value),
                badge_label: row.querySelector('.jem-age-label').value,
                badge_background: row.querySelector('.jem-age-background-hex').value,
                badge_text: row.querySelector('.jem-age-text-hex').value,
                published: row.querySelector('.jem-age-published').checked ? 1 : 0
            };
        }));
    }

    function append(level) {
        body.insertAdjacentHTML('beforeend', rowHtml(level));
        updatePreview(body.lastElementChild);
        updatePayload();
    }

    initialLevels.forEach(append);

    addButton.addEventListener('click', function() {
        append({id: 0, title: '', min_age: 0, max_age: 99, badge_label: '', badge_background: '#1F2937', badge_text: '#FFFFFF', published: 1});
        body.lastElementChild.querySelector('.jem-age-title').focus();
    });

    body.addEventListener('click', function(event) {
        var row = event.target.closest('tr');
        if (!row) {
            return;
        }
        if (event.target.closest('.jem-age-remove')) {
            row.remove();
        } else if (event.target.closest('.jem-age-up') && row.previousElementSibling) {
            body.insertBefore(row, row.previousElementSibling);
        } else if (event.target.closest('.jem-age-down') && row.nextElementSibling) {
            body.insertBefore(row.nextElementSibling, row);
        }
        updatePayload();
    });
    body.addEventListener('input', function(event) {
        var row = event.target.closest('tr');
        if (!row) {
            return;
        }

        if (event.target.matches('.jem-age-background, .jem-age-text')) {
            var hexClass = event.target.classList.contains('jem-age-background')
                ? '.jem-age-background-hex'
                : '.jem-age-text-hex';
            row.querySelector(hexClass).value = event.target.value.toUpperCase();
        } else if (event.target.matches('.jem-age-background-hex, .jem-age-text-hex')
            && /^#[0-9a-f]{6}$/i.test(event.target.value)) {
            var colorClass = event.target.classList.contains('jem-age-background-hex')
                ? '.jem-age-background'
                : '.jem-age-text';
            row.querySelector(colorClass).value = event.target.value;
        }

        updatePreview(row);
        updatePayload();
    });
    document.getElementById('settings-form').addEventListener('submit', updatePayload);
})();
</script>
