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

<div class="table-responsive">
    <table class="table" id="jem-age-levels-table">
        <thead>
            <tr>
                <th><?php echo Text::_('COM_JEM_AGE_LEVEL_TITLE'); ?></th>
                <th><?php echo Text::_('COM_JEM_AGE_LEVEL_MINIMUM'); ?></th>
                <th><?php echo Text::_('COM_JEM_AGE_LEVEL_BACKGROUND'); ?></th>
                <th><?php echo Text::_('COM_JEM_AGE_LEVEL_TEXT_COLOR'); ?></th>
                <th><?php echo Text::_('JPUBLISHED'); ?></th>
                <th class="text-end"><?php echo Text::_('JACTIONS'); ?></th>
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

    function escapeAttribute(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function rowHtml(level) {
        return '<tr data-id="' + Number(level.id || 0) + '">' +
            '<td><input type="text" class="form-control jem-age-title" maxlength="100" value="' + escapeAttribute(level.title) + '" required></td>' +
            '<td><input type="number" class="form-control jem-age-minimum" min="0" max="120" value="' + Number(level.min_age || 0) + '" required></td>' +
            '<td><input type="color" class="form-control form-control-color jem-age-background" value="' + escapeAttribute(level.badge_background || '#1F2937') + '"></td>' +
            '<td><input type="color" class="form-control form-control-color jem-age-text" value="' + escapeAttribute(level.badge_text || '#FFFFFF') + '"></td>' +
            '<td><input type="checkbox" class="form-check-input jem-age-published"' + (Number(level.published) ? ' checked' : '') + '></td>' +
            '<td class="text-end text-nowrap">' +
                '<button type="button" class="btn btn-sm btn-secondary jem-age-up" title="' + escapeAttribute(moveUpLabel) + '"><span class="icon-arrow-up" aria-hidden="true"></span></button> ' +
                '<button type="button" class="btn btn-sm btn-secondary jem-age-down" title="' + escapeAttribute(moveDownLabel) + '"><span class="icon-arrow-down" aria-hidden="true"></span></button> ' +
                '<button type="button" class="btn btn-sm btn-danger jem-age-remove" title="' + escapeAttribute(deleteLabel) + '"><span class="icon-trash" aria-hidden="true"></span></button>' +
            '</td></tr>';
    }

    function updatePayload() {
        payload.value = JSON.stringify(Array.prototype.map.call(body.querySelectorAll('tr'), function(row) {
            return {
                id: Number(row.getAttribute('data-id') || 0),
                title: row.querySelector('.jem-age-title').value,
                min_age: Number(row.querySelector('.jem-age-minimum').value),
                badge_background: row.querySelector('.jem-age-background').value,
                badge_text: row.querySelector('.jem-age-text').value,
                published: row.querySelector('.jem-age-published').checked ? 1 : 0
            };
        }));
    }

    function append(level) {
        body.insertAdjacentHTML('beforeend', rowHtml(level));
        updatePayload();
    }

    initialLevels.forEach(append);

    addButton.addEventListener('click', function() {
        append({id: 0, title: '', min_age: 0, badge_background: '#1F2937', badge_text: '#FFFFFF', published: 1});
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
    body.addEventListener('input', updatePayload);
    document.getElementById('settings-form').addEventListener('submit', updatePayload);
})();
</script>
