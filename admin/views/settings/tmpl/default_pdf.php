<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
?>

<div class="width-100" style="padding: 10px 1vw;">
    <?php echo HTMLHelper::_('uitab.startTabSet', 'pdf-settings-pane', array('active' => 'pdf-settings-general', 'recall' => true, 'breakpoint' => 768)); ?>

    <?php echo HTMLHelper::_('uitab.addTab', 'pdf-settings-pane', 'pdf-settings-general', Text::_('COM_JEM_PDF_GENERAL')); ?>
        <fieldset class="options-form">
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_enabled_views'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_margin_profile'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_margin_top'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_margin_right'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_margin_bottom'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_margin_left'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_background_color'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_imageheight'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_imagewidth'); ?></div></li>
            </ul>
        </fieldset>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>

    <?php echo HTMLHelper::_('uitab.addTab', 'pdf-settings-pane', 'pdf-settings-details', Text::_('COM_JEM_PDF_DETAILS')); ?>
        <fieldset class="options-form">
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_event_layout'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_event_description_mode'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_venue_description_mode'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_event_show_images'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_event_imageheight'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_event_imagewidth'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_event_image_position'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_venue_imageheight'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_venue_imagewidth'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_venue_image_position'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_event_venue_mode'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_event_include_venue_map'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_event_include_links'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_event_include_attachments'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_event_include_registration'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_event_include_contacts'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_event_include_online_meeting'); ?></div></li>
            </ul>
        </fieldset>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>

    <?php echo HTMLHelper::_('uitab.addTab', 'pdf-settings-pane', 'pdf-settings-calendars', Text::_('COM_JEM_PDF_CALENDARS')); ?>
        <fieldset class="options-form">
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_orientation'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_paper_size'); ?></div></li>
            </ul>
        </fieldset>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>

    <?php echo HTMLHelper::_('uitab.addTab', 'pdf-settings-pane', 'pdf-settings-lists', Text::_('COM_JEM_PDF_LISTS')); ?>
        <fieldset class="options-form">
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_base_font_size'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_heading_font_size'); ?></div></li>
            </ul>
        </fieldset>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>

    <?php echo HTMLHelper::_('uitab.addTab', 'pdf-settings-pane', 'pdf-settings-maps', Text::_('COM_JEM_PDF_MAPS')); ?>
        <fieldset class="options-form">
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_accent_color'); ?></div></li>
            </ul>
        </fieldset>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>

    <?php echo HTMLHelper::_('uitab.addTab', 'pdf-settings-pane', 'pdf-settings-annual', Text::_('COM_JEM_PDF_ANNUAL')); ?>
        <fieldset class="options-form">
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_annual_paper_size'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_annual_orientation'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_annual_show_day_types_legend'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_annual_show_categories_legend'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_annual_event_titles'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_annual_event_limit'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_annual_column_gap'); ?></div></li>
                <li><div class="label-form"><?php echo $this->form->renderfield('pdf_annual_row_gap'); ?></div></li>
            </ul>
        </fieldset>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>

    <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
</div>
