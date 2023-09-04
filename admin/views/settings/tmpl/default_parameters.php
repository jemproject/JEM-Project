<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
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
					<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
				<?php endforeach; ?>
			</ul>
		</fieldset>
	</div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
			<legend><?php echo Text::_('COM_JEM_SETTINGS_LEGEND_VIEW_EDITEVENT'); ?></legend>
			<ul class="adminformlist">
				<li><div class="label-form"><?php echo $this->form->renderfield('global_show_ownedvenuesonly',$group); ?></div></li>
				<li><div class="label-form"><?php echo $this->form->renderfield('global_editevent_maxnumcustomfields',$group); ?></div></li>
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
					<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
				<?php endforeach; ?>
			</ul>
		</fieldset>
	</div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
			<legend><?php echo Text::_('COM_JEM_VENUES'); ?></legend>
			<ul class="adminformlist">
				<li><div class="label-form"><?php echo $this->form->renderfield('global_show_locdescription',$group); ?></div></li>
				<li><div class="label-form"><?php echo $this->form->renderfield('global_show_detailsadress',$group); ?></div></li>
				<li><div class="label-form"><?php echo $this->form->renderfield('global_show_detlinkvenue',$group); ?></div></li>
				<li><div class="label-form"><?php echo $this->form->renderfield('global_show_mapserv',$group); ?></div></li>
				<li><div class="label-form"><?php echo $this->form->renderfield('global_tld',$group); ?></div></li>
				<li><div class="label-form"><?php echo $this->form->renderfield('global_lg',$group); ?></div></li>
			</ul>
		</fieldset>
	</div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
			<legend><?php echo Text::_('COM_JEM_SETTINGS_LEGEND_VIEW_EDITVENUE'); ?></legend>
			<ul class="adminformlist">
				<li><?php echo $this->form->getLabel('global_editvenue_maxnumcustomfields',$group); ?> <?php echo $this->form->getInput('global_editvenue_maxnumcustomfields',$group); ?></li>
			</ul>
		</fieldset>
	</div>
</div>
<div class="clr"></div>
