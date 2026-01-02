<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$group = 'globalattribs';

?>
<div class="width-50 fltlft">
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_GLOBAL_PARAMETERS'); ?></legend>
            <ul class="adminformlist">
                <?php foreach ($this->form->getFieldset('globalparam') as $field): ?>
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
</div>
<div class="width-50 fltrt">
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_GLOBAL_PARAMETERS_ADVANCED'); ?></legend>
            <ul class="adminformlist">
                <?php foreach ($this->form->getFieldset('globalparam2') as $field): ?>
                    <li><div class="label-form"><?php echo $this->form->renderfield($field->fieldname); ?></div></li>
                <?php endforeach; ?>
            </ul>
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
