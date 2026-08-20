<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$group = 'globalattribs';
$advancedFieldsets = array(
    'globalparam_access'         => 'COM_JEM_SETTINGS_ACCESS_VISIBILITY',
    'globalparam_lists_calendar' => 'COM_JEM_SETTINGS_LISTS_CALENDAR',
    'globalparam_time_regional'  => 'COM_JEM_SETTINGS_TIME_REGIONAL',
    'globalparam_attachments'    => 'COM_JEM_ATTACHMENTS',
    'globalparam_defaults'       => 'COM_JEM_SETTINGS_DEFAULT_CONTENT_VALUES',
);

?>
<div class="width-100 jem-global-parameters-profile">
    <?php echo $this->loadTemplate('profile'); ?>
</div>
<div class="width-50 fltlft">
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_SETTINGS_DISPLAY_NAVIGATION'); ?></legend>
            <ul class="adminformlist">
                <?php foreach ($this->form->getFieldset('globalparam_display') as $field): ?>
                    <li><div class="label-form"><?php echo $this->form->renderfield($field->fieldname,$group); ?></div></li>
                <?php endforeach; ?>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_SETTINGS_LEGEND_VIEW_EDITEVENT'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('global_show_ownedvenuesonly',$group); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('global_editevent_starttime_limit',$group); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('global_editevent_endtime_limit',$group); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('global_editevent_minutes_block',$group); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('global_editevent_maxnumcustomfields',$group); ?></div></li>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_SETTINGS_LEGEND_VIEW_EDITVENUE'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('global_editvenue_maxnumcustomfields',$group); ?></div></li>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_SETTINGS_CALENDAR_SPECIAL_DAYS'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('calendar_special_days_enabled', $group); ?></div></li>
            </ul>
            <?php echo $this->form->getInput('calendar_special_day_types', $group); ?>
            <div class="clr"></div>
            <p class="form-text"><?php echo Text::_('COM_JEM_SETTINGS_CALENDAR_SPECIAL_DAYS_MANAGE_DESC'); ?></p>
            <a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_jem&view=types&filter_entity=4'); ?>">
                <span class="icon-tags" aria-hidden="true"></span>
                <?php echo Text::_('COM_JEM_SETTINGS_CALENDAR_SPECIAL_DAYS_MANAGE'); ?>
            </a>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_SETTINGS_GLOBAL_RECURRENCE'); ?></legend>
            <ul class="adminformlist">
                <?php foreach ($this->form->getFieldset('globalparam_recurrence') as $field): ?>
                    <li><div class="label-form"><?php echo $this->form->renderfield($field->fieldname); ?></div></li>
                <?php endforeach; ?>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_SETTINGS_CSV_IMPORT_EXPORT'); ?></legend>
            <ul class="adminformlist">
                <?php foreach ($this->form->getFieldset('globalparam_csv') as $field): ?>
                    <li><div class="label-form"><?php echo $this->form->renderfield($field->fieldname); ?></div></li>
                <?php endforeach; ?>
            </ul>
        </fieldset>
    </div>
</div>
<div class="width-50 fltrt">
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_SETTINGS_SYSTEM_INTEGRATIONS'); ?></legend>
            <ul class="adminformlist">
                <?php foreach ($this->form->getFieldset('globalparam_system') as $field): ?>
                    <li><div class="label-form"><?php echo $this->form->renderfield($field->fieldname, $group); ?></div></li>
                <?php endforeach; ?>
            </ul>
        </fieldset>
    </div>
    <?php foreach ($advancedFieldsets as $fieldsetName => $fieldsetLabel) : ?>
        <div class="width-100" style="padding: 10px 1vw;">
            <fieldset class="options-form">
                <legend><?php echo Text::_($fieldsetLabel); ?></legend>
                <ul class="adminformlist">
                    <?php foreach ($this->form->getFieldset($fieldsetName) as $field): ?>
                        <li><div class="label-form"><?php echo $this->form->renderfield($field->fieldname); ?></div></li>
                    <?php endforeach; ?>
                </ul>
            </fieldset>
        </div>
    <?php endforeach; ?>
</div>
<div class="clr"></div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const select = document.getElementById('jform_defaultCategory');
        if (select) {
            const selectedOption = select.querySelector('option[selected]');
            if (selectedOption) {
                const optionIndex = Array.from(select.options).indexOf(selectedOption);
                const optionHeight = selectedOption.offsetHeight;
                select.scrollTop = optionIndex * optionHeight - (select.offsetHeight / 2);
            }
        }
    });
</script>
