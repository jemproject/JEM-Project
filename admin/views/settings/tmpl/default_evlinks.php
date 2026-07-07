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

<div class="width-100" style="padding: 10px 1vw;">
    <fieldset class="options-form">
        <legend><?php echo Text::_('COM_JEM_SETTINGS_EVENT_LINKS'); ?></legend>
        <ul class="adminformlist">
            <li><div class="label-form"><?php echo $this->form->renderfield('event_show_online_meeting',$group); ?></div></li>
            <li><div class="label-form"><?php echo $this->form->renderfield('event_online_meeting_ics',$group); ?></div></li>
            <li><div class="label-form"><?php echo $this->form->renderfield('event_online_meeting_ics_description',$group); ?></div></li>
            <li><div class="label-form"><?php echo $this->form->renderfield('event_online_meeting_default_label',$group); ?></div></li>
            <li><div class="label-form"><?php echo $this->form->renderfield('allowed_link_extensions',$group); ?></div></li>
            <li><div class="label-form"><?php echo $this->form->renderfield('allowed_link_schemes',$group); ?></div></li>
        </ul>
    </fieldset>
</div>
