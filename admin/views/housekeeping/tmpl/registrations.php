<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$total = array_sum($this->registrationOrphans);
?>
<div class="container-fluid jem-housekeeping-tool">
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <?php echo HTMLHelper::_('image', 'com_jem/icon-48-cleancatseventrels.svg', '', array('width' => 48, 'height' => 48), true); ?>
                <div>
                    <h2 class="h4 mb-1"><?php echo Text::_('COM_JEM_HOUSEKEEPING_REGISTRATIONS'); ?></h2>
                    <p class="mb-0 text-muted"><?php echo Text::_('COM_JEM_HOUSEKEEPING_REGISTRATIONS_DESC'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$this->registrationOrphans) : ?>
        <div class="alert alert-success">
            <span class="icon-check" aria-hidden="true"></span> <?php echo Text::_('COM_JEM_HOUSEKEEPING_REGISTRATIONS_NONE'); ?>
        </div>
    <?php else : ?>
        <form method="post" action="<?php echo Route::_('index.php?option=com_jem&task=housekeeping.purgeOrphanRegistrations'); ?>"
              class="card mb-4" id="jem-registrations-form">
            <div class="card-body">
                <h3 class="h5"><?php echo Text::sprintf('COM_JEM_HOUSEKEEPING_REGISTRATIONS_FOUND', $total, count($this->registrationOrphans)); ?></h3>
                <p><?php echo Text::_('COM_JEM_HOUSEKEEPING_REGISTRATIONS_EXPLAIN'); ?></p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle w-auto">
                        <caption class="visually-hidden"><?php echo Text::_('COM_JEM_HOUSEKEEPING_REGISTRATIONS'); ?></caption>
                        <thead><tr>
                            <th scope="col" class="text-center" style="width:1%">
                                <input type="checkbox" class="form-check-input" id="jem-registrations-all" checked
                                       aria-label="<?php echo $escape(Text::_('JGLOBAL_CHECK_ALL')); ?>">
                            </th>
                            <th scope="col"><?php echo Text::_('COM_JEM_HOUSEKEEPING_REGISTRATIONS_EVENT_ID'); ?></th>
                            <th scope="col" class="text-end"><?php echo Text::_('COM_JEM_HOUSEKEEPING_REGISTRATIONS_COUNT'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($this->registrationOrphans as $eventId => $count) : ?>
                            <?php $id = 'jem-registrations-event-' . (int) $eventId; ?>
                            <tr>
                                <td class="text-center">
                                    <input class="form-check-input jem-registrations-box" type="checkbox" name="events[]" id="<?php echo $id; ?>"
                                           value="<?php echo (int) $eventId; ?>" checked>
                                </td>
                                <td><label for="<?php echo $id; ?>">#<?php echo (int) $eventId; ?></label></td>
                                <td class="text-end"><?php echo (int) $count; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($this->registrationPurgeAllowed) : ?>
                    <input type="hidden" name="registrations_nonce" value="<?php echo $escape($this->registrationNonce); ?>">
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <button type="submit" class="btn btn-danger mt-2" id="jem-registrations-submit">
                        <?php echo Text::_('COM_JEM_HOUSEKEEPING_REGISTRATIONS_DELETE'); ?>
                    </button>
                    <p class="small text-muted mt-2 mb-0"><?php echo Text::_('COM_JEM_HOUSEKEEPING_REGISTRATIONS_AFTER'); ?></p>
                <?php else : ?>
                    <div class="alert alert-info mb-0"><?php echo Text::_('COM_JEM_HOUSEKEEPING_REGISTRATIONS_ADMIN_ONLY'); ?></div>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>

    <?php if (array_filter($this->registrationDetails)) : ?>
        <div class="card mb-4"><div class="card-body">
            <h3 class="h5"><?php echo Text::_('COM_JEM_HOUSEKEEPING_REGISTRATIONS_DETAILS'); ?></h3>
            <ul class="mb-2">
                <?php foreach ($this->registrationDetails as $table => $count) : ?>
                    <?php if ($count) : ?>
                        <li><?php echo Text::sprintf('COM_JEM_TOOLS_REGISTRATION_DETAILS_ORPHANED', (int) $count, $escape($table)); ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <p class="small text-muted mb-0"><?php echo Text::_('COM_JEM_TOOLS_RECOMMEND_REGISTRATION_DETAILS_ORPHANED'); ?></p>
        </div></div>
    <?php endif; ?>
</div>
<?php if ($this->registrationOrphans && $this->registrationPurgeAllowed) : ?>
<script>
(function () {
    const form = document.getElementById('jem-registrations-form');
    const all = document.getElementById('jem-registrations-all');
    const submit = document.getElementById('jem-registrations-submit');
    const boxes = Array.from(form.querySelectorAll('.jem-registrations-box'));
    const confirmText = <?php echo json_encode(Text::_('COM_JEM_HOUSEKEEPING_REGISTRATIONS_CONFIRM')); ?>;
    const update = function () {
        submit.disabled = !boxes.some(function (box) { return box.checked; });
    };
    all.addEventListener('change', function () {
        boxes.forEach(function (box) { box.checked = all.checked; });
        update();
    });
    boxes.forEach(function (box) { box.addEventListener('change', update); });
    form.addEventListener('submit', function (event) {
        const selected = boxes.filter(function (box) { return box.checked; }).length;
        if (!window.confirm(confirmText.replace('%d', String(selected)))) {
            event.preventDefault();
        }
    });
    update();
})();
</script>
<?php endif; ?>
