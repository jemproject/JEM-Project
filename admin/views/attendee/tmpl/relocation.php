<?php
/** @package JEM @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL */
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;

if (empty($this->row->id) || (int) $this->row->status !== 1 || !empty($this->row->waiting)
    || empty($this->capacity->enabled) || !empty($this->commerceReadOnly)) {
    return;
}
$canFinance = Joomla\CMS\Factory::getApplication()->getIdentity()->authorise('jem.tickets.pay', 'com_jem');
?>
<div class="card my-3" id="jem-relocation">
    <div class="card-body">
        <button type="button" class="btn btn-outline-primary" id="jem-relocation-open"><?php echo Text::_('COM_JEM_RELOCATION_TITLE'); ?></button>
        <div id="jem-relocation-panel" hidden>
            <p class="mt-3"><?php echo Text::_('COM_JEM_RELOCATION_HELP'); ?></p>
            <label for="jem-relocation-reason" class="form-label"><?php echo Text::_('COM_JEM_RELOCATION_REASON'); ?></label>
            <textarea id="jem-relocation-reason" name="relocation_reason" class="form-control mb-3" maxlength="1000" rows="2"></textarea>
            <label for="jem-relocation-choice" class="form-label"><?php echo Text::_('COM_JEM_RELOCATION_FINANCIAL'); ?></label>
            <select id="jem-relocation-choice" name="relocation_choice" class="form-select mb-3">
                <option value="standard"><?php echo Text::_('COM_JEM_RELOCATION_STANDARD'); ?></option>
                <?php if ($canFinance) : ?>
                    <?php foreach (['promotion', 'consolidation_supplement', 'agreement', 'consolidation_refund'] as $choice) : ?>
                    <option value="<?php echo $choice; ?>" hidden><?php echo Text::_('COM_JEM_RELOCATION_' . strtoupper($choice)); ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <label id="jem-relocation-accept-label" class="form-check mb-3" hidden>
                <input type="checkbox" class="form-check-input" name="relocation_accepted" value="1" id="jem-relocation-accepted">
                <span class="form-check-label"><?php echo Text::_('COM_JEM_RELOCATION_ACCEPT'); ?></span>
            </label>
            <button type="button" class="btn btn-secondary" id="jem-relocation-preview"><?php echo Text::_('COM_JEM_RELOCATION_REVIEW'); ?></button>
            <div id="jem-relocation-error" class="alert alert-danger mt-3" role="alert" hidden></div>
            <div id="jem-relocation-review" class="mt-3" aria-live="polite" hidden></div>
            <button type="button" class="btn btn-primary mt-3" id="jem-relocation-confirm" disabled><?php echo Text::_('COM_JEM_RELOCATION_CONFIRM'); ?></button>
        </div>
    </div>
</div>

<?php if (JemHelperBackend::canManage('jem.notifications.resend')) : ?>
    <?php $latestRelocation = Joomla\CMS\Factory::getContainer()->get('DatabaseDriver')
        ->setQuery('SELECT COUNT(*) FROM #__jem_register_history WHERE registration_id=' . (int) $this->row->id
            . ' AND revision=' . (int) $this->row->revision . " AND action='relocated'")->loadResult(); ?>
    <?php if ($latestRelocation) : ?>
    <button type="button" id="jem-relocation-notify" class="btn btn-outline-secondary"><?php echo Text::_('COM_JEM_RELOCATION_RETRY'); ?></button>
    <?php endif; ?>
<?php endif; ?>
