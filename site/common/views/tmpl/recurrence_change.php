<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

Factory::getApplication()->getDocument()->getWebAssetManager()->registerAndUseScript(
    'com_jem.recurrence-change', 'media/com_jem/js/recurrence-change.js', ['version' => 'auto'], ['defer' => true]
);
Text::script('COM_JEM_RECURRENCE_CHANGE_REVIEW');
?>
<input type="hidden" name="jem_recurrence_event_id" value="<?php echo (int) $this->item->id; ?>">
<input type="hidden" name="jem_recurrence_choice" value="">
<input type="hidden" name="jem_recurrence_fingerprint" value="">
<dialog class="jem-recurrence-change" aria-labelledby="jem-recurrence-change-title">
    <h2 id="jem-recurrence-change-title"><?php echo Text::_('COM_JEM_RECURRENCE_CHANGE_TITLE'); ?></h2>
    <p data-recurrence-message></p>
    <p data-recurrence-blocked role="status"></p>
    <fieldset>
        <legend><?php echo Text::_('COM_JEM_RECURRENCE_CHANGE_CHOOSE'); ?></legend>
        <p><label><input type="radio" name="jem_recurrence_decision" value="keep">
            <span data-recurrence-keep-label><?php echo Text::_('COM_JEM_RECURRENCE_CHANGE_KEEP'); ?></span></label></p>
        <p data-recurrence-delete><label><input type="radio" name="jem_recurrence_decision" value="delete">
            <?php echo Text::_('COM_JEM_RECURRENCE_CHANGE_DELETE'); ?></label></p>
    </fieldset>
    <button type="button" class="btn btn-primary" data-recurrence-continue disabled><?php echo Text::_('COM_JEM_RECURRENCE_CHANGE_CONFIRM'); ?></button>
    <button type="button" class="btn btn-secondary" data-recurrence-cancel><?php echo Text::_('JCANCEL'); ?></button>
</dialog>
