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
<section class="jem-operating-profile mb-4<?php echo $configured ? '' : ' is-setup-required'; ?>" aria-labelledby="jem-operating-profile-title">
    <div class="jem-operating-profile-heading">
        <div>
            <h2 class="h4 mb-1" id="jem-operating-profile-title"><?php echo Text::_('COM_JEM_OPERATING_PROFILE_QUESTION'); ?></h2>
            <p class="mb-0"><?php echo Text::_('COM_JEM_OPERATING_PROFILE_INTRO'); ?></p>
        </div>
        <?php if (!$configured) : ?>
            <span class="badge bg-warning text-dark"><?php echo Text::_('COM_JEM_OPERATING_PROFILE_SETUP_REQUIRED'); ?></span>
        <?php endif; ?>
    </div>

    <div class="jem-operating-profile-grid">
        <label class="jem-operating-profile-card<?php echo $profile === JemFeaturePolicy::PROFILE_ESSENTIAL ? ' is-selected' : ''; ?>">
            <input type="radio" name="jform[operating_profile]" value="essential"<?php echo $profile === JemFeaturePolicy::PROFILE_ESSENTIAL ? ' checked' : ''; ?>>
            <span class="jem-operating-profile-card-copy">
                <strong><?php echo Text::_('COM_JEM_OPERATING_PROFILE_ESSENTIAL'); ?></strong>
                <small><?php echo Text::_('COM_JEM_OPERATING_PROFILE_ESSENTIAL_VERSION'); ?></small>
                <span><?php echo Text::_('COM_JEM_OPERATING_PROFILE_ESSENTIAL_DESC'); ?></span>
            </span>
        </label>

        <label class="jem-operating-profile-card<?php echo $profile === JemFeaturePolicy::PROFILE_ADVANCED ? ' is-selected' : ''; ?>">
            <input type="radio" name="jform[operating_profile]" value="advanced"<?php echo $profile === JemFeaturePolicy::PROFILE_ADVANCED ? ' checked' : ''; ?>>
            <span class="jem-operating-profile-card-copy">
                <strong><?php echo Text::_('COM_JEM_OPERATING_PROFILE_ADVANCED'); ?></strong>
                <small><?php echo Text::_('COM_JEM_OPERATING_PROFILE_ADVANCED_VERSION'); ?></small>
                <span><?php echo Text::_('COM_JEM_OPERATING_PROFILE_ADVANCED_DESC'); ?></span>
            </span>
        </label>

        <div class="jem-operating-profile-card is-disabled" aria-disabled="true">
            <input type="radio" disabled>
            <span class="jem-operating-profile-card-copy">
                <strong><?php echo Text::_('COM_JEM_OPERATING_PROFILE_COMMERCE'); ?></strong>
                <small><?php echo Text::_('COM_JEM_OPERATING_PROFILE_COMMERCE_VERSION'); ?></small>
                <span><?php echo Text::_('COM_JEM_OPERATING_PROFILE_COMMERCE_DESC'); ?></span>
                <em><?php echo Text::_('COM_JEM_OPERATING_PROFILE_COMING_SOON'); ?></em>
            </span>
        </div>
    </div>
    <input type="hidden" name="jform[operating_profile_configured]" value="1">
    <p class="form-text mb-0"><?php echo Text::_('COM_JEM_OPERATING_PROFILE_SAFE_CHANGE'); ?></p>
</section>

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
