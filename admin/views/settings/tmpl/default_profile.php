<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$profile = (string) $this->form->getValue('operating_profile', null, JemFeaturePolicy::PROFILE_ESSENTIAL);
$profile = JemFeaturePolicy::normaliseStoredProfile($profile);
$configured = (int) $this->form->getValue('operating_profile_configured', null, 0);
?>
<fieldset class="options-form jem-operating-profile<?php echo $configured ? '' : ' is-setup-required'; ?>" aria-describedby="jem-operating-profile-desc">
    <legend><?php echo Text::_('COM_JEM_OPERATING_PROFILE'); ?></legend>
    <div class="jem-operating-profile-heading">
        <div>
            <strong><?php echo Text::_('COM_JEM_OPERATING_PROFILE_QUESTION'); ?></strong>
            <div id="jem-operating-profile-desc" class="form-text hide-aware-inline-help d-none">
                <?php echo Text::_('COM_JEM_OPERATING_PROFILE_DESC'); ?>
            </div>
        </div>
        <?php if (!$configured) : ?>
            <span class="badge bg-warning text-dark"><?php echo Text::_('COM_JEM_OPERATING_PROFILE_SETUP_REQUIRED'); ?></span>
        <?php endif; ?>
    </div>

    <div class="jem-operating-profile-grid">
        <label class="jem-operating-profile-card<?php echo $profile === JemFeaturePolicy::PROFILE_ESSENTIAL ? ' is-selected' : ''; ?>">
            <input type="radio" name="jform[operating_profile]" value="essential" aria-describedby="jem-operating-profile-essential-desc"<?php echo $profile === JemFeaturePolicy::PROFILE_ESSENTIAL ? ' checked' : ''; ?>>
            <span class="jem-operating-profile-card-copy">
                <strong><?php echo Text::_('COM_JEM_OPERATING_PROFILE_ESSENTIAL'); ?></strong>
                <small><?php echo Text::_('COM_JEM_OPERATING_PROFILE_ESSENTIAL_VERSION'); ?></small>
                <span id="jem-operating-profile-essential-desc" class="form-text hide-aware-inline-help d-none"><?php echo Text::_('COM_JEM_OPERATING_PROFILE_ESSENTIAL_DESC'); ?></span>
            </span>
        </label>

        <label class="jem-operating-profile-card<?php echo $profile === JemFeaturePolicy::PROFILE_ADVANCED ? ' is-selected' : ''; ?>">
            <input type="radio" name="jform[operating_profile]" value="advanced" aria-describedby="jem-operating-profile-advanced-desc"<?php echo $profile === JemFeaturePolicy::PROFILE_ADVANCED ? ' checked' : ''; ?>>
            <span class="jem-operating-profile-card-copy">
                <strong><?php echo Text::_('COM_JEM_OPERATING_PROFILE_ADVANCED'); ?></strong>
                <small><?php echo Text::_('COM_JEM_OPERATING_PROFILE_ADVANCED_VERSION'); ?></small>
                <span id="jem-operating-profile-advanced-desc" class="form-text hide-aware-inline-help d-none"><?php echo Text::_('COM_JEM_OPERATING_PROFILE_ADVANCED_DESC'); ?></span>
            </span>
        </label>

        <div class="jem-operating-profile-card is-disabled" aria-disabled="true">
            <input type="radio" disabled aria-describedby="jem-operating-profile-commerce-desc">
            <span class="jem-operating-profile-card-copy">
                <strong><?php echo Text::_('COM_JEM_OPERATING_PROFILE_COMMERCE'); ?></strong>
                <small><?php echo Text::_('COM_JEM_OPERATING_PROFILE_COMMERCE_VERSION'); ?></small>
                <span id="jem-operating-profile-commerce-desc" class="form-text hide-aware-inline-help d-none"><?php echo Text::_('COM_JEM_OPERATING_PROFILE_COMMERCE_DESC'); ?></span>
                <em><?php echo Text::_('COM_JEM_OPERATING_PROFILE_COMING_SOON'); ?></em>
            </span>
        </div>
    </div>
    <input type="hidden" name="jform[operating_profile_configured]" value="1">
</fieldset>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const selector = document.querySelector('.jem-operating-profile');
    if (!selector) {
        return;
    }
    selector.addEventListener('change', function (event) {
        if (!event.target.matches('input[name="jform[operating_profile]"]')) {
            return;
        }
        selector.querySelectorAll('.jem-operating-profile-card').forEach(function (card) {
            const input = card.querySelector('input[name="jform[operating_profile]"]');
            card.classList.toggle('is-selected', Boolean(input && input.checked));
        });
    });
});
</script>
