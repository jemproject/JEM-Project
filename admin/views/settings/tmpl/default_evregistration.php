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

<div class="width-100" style="padding: 10px 1vw;">
    <fieldset class="options-form">
		<legend><?php echo Text::_('COM_JEM_REGISTRATION'); ?></legend>
		<ul class="adminformlist">
			<li><div class="label-form"><?php echo $this->form->renderfield('event_show_attendeenames',$group); ?></div></li>
			<li><div class="label-form"><?php echo $this->form->renderfield('event_show_more_attendeedetails',$group); ?></div></li>
			<li><div class="label-form"><?php echo $this->form->renderfield('event_comunsolution',$group); ?></div></li>
			<li><div class="label-form"><?php echo $this->form->renderfield('event_comunoption',$group); ?></div></li>
		</ul>
	</fieldset>
</div>
