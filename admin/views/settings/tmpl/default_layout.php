<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
<div class="width-50 fltlft">
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_GENERAL_LAYOUT_SETTINGS'); ?></legend>
            <ul class="adminformlist">
                <?php foreach ($this->form->getFieldset('layoutgenerallayoutsetting') as $field): ?>
                    <li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
                <?php endforeach; ?>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_CITY_COLUMN'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('showcity'); ?></div></li>

                <li id="city1" style="display:none"><div class="label-form"><?php echo $this->form->renderfield('citywidth'); ?></div></li>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_ATTENDEE_COLUMN'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('showatte'); ?></div></li>

                <li id="atte1" style="display:none"><div class="label-form"><?php echo $this->form->renderfield('attewidth'); ?></div></li>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_TITLE_COLUMN'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('showtitle'); ?></div></li>
                <li id="title1" style="display:none"><div class="label-form"><?php echo $this->form->renderfield('titlewidth'); ?></div>
                </li>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_VENUE_COLUMN'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('showlocate'); ?></div></li>

                <li id="loc1" style="display:none"><div class="label-form"><?php echo $this->form->renderfield('locationwidth'); ?></div></li>

                <li id="loc2" style="display:none"><div class="label-form"><?php echo $this->form->renderfield('showlinkvenue'); ?></div></li>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_STATE_COLUMN'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('showstate'); ?></div></li>
                <li id="state1" style="display:none"><div class="label-form"><?php echo $this->form->renderfield('statewidth'); ?></div></li>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_CATEGORY_COLUMN'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('showcat'); ?></div></li>

                <li id="cat1" style="display:none"><div class="label-form"><?php echo $this->form->renderfield('catfrowidth'); ?></div></li>

                <li id="cat2" style="display:none"><div class="label-form"><?php echo $this->form->renderfield('catlinklist'); ?></div></li>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_LAYOUT_TABLE_EVENTIMAGE'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('showeventimage'); ?></div></li>

                <li id="evimage1" style="display:none"><div class="label-form"><?php echo $this->form->renderfield('tableeventimagewidth'); ?></div></li>
            </ul>
        </fieldset>
    </div>
</div>

<div class="width-50 fltrt">
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
		<legend><?php echo Text::_('COM_JEM_SETTINGS_LEGEND_CSS'); ?></legend>
		<ul class="adminformlist">
			<?php foreach ($this->form->getFieldset('stylesheet') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
</div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
		<legend><?php echo Text::_('COM_JEM_SETTINGS_LEGEND_CSS_COLOR_BACKGROUND'); ?></legend>
		<ul class="adminformlist label-button-line">
			<?php foreach ($this->form->getFieldset('css_color') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
</div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
		<legend><?php echo Text::_('COM_JEM_SETTINGS_LEGEND_CSS_COLOR_BORDER'); ?></legend>
		<ul class="adminformlist label-button-line">
			<?php foreach ($this->form->getFieldset('css_color_border') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
</div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
		<legend><?php echo Text::_('COM_JEM_SETTINGS_LEGEND_CSS_COLOR_FONT'); ?></legend>
		<ul class="adminformlist label-button-line">
			<?php foreach ($this->form->getFieldset('css_color_font') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
</div>
</div><div class="clr"></div>
