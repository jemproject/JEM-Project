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

// Create shortcut to parameters.
$params = $this->state->get('params');

$params = $params->toArray();

// This checks if the config options have ever been saved. If they haven't they will fall back to the original settings.
$editoroptions = isset($params['show_publishing_options']);

if (!$editoroptions):
$params['show_publishing_options'] = '1';
$params['show_article_options'] = '1';
$params['show_urls_images_backend'] = '0';
$params['show_urls_images_frontend'] = '0';
endif;

$group = 'attribs';

?>

<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo Text::_('COM_JEM_EVENT'); ?></legend>
		<ul class="adminformlist">
			<?php foreach ($this->form->getFieldset('basic') as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
		<ul class="adminformlist">
			<?php foreach ($this->form->getFieldset('evevents',$group) as $field): ?>
				<li><?php echo $field->label; ?> <?php echo $field->input; ?></li>
			<?php endforeach; ?>
		</ul>
	</fieldset>
	<fieldset class="adminform">
		<legend><?php echo Text::_('COM_JEM_VENUE'); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('event_show_locdescription',$group); ?> <?php echo $this->form->getInput('event_show_locdescription',$group); ?></li>
			<li><?php echo $this->form->getLabel('event_show_detailsadress',$group); ?> <?php echo $this->form->getInput('event_show_detailsadress',$group); ?></li>
			<li><?php echo $this->form->getLabel('event_show_detlinkvenue',$group); ?> <?php echo $this->form->getInput('event_show_detlinkvenue',$group); ?></li>
			<li><?php echo $this->form->getLabel('event_show_mapserv',$group); ?> <?php echo $this->form->getInput('event_show_mapserv',$group); ?></li>
			<li id="eventmap1" style="display:none"><?php echo $this->form->getLabel('event_tld',$group); ?> <?php echo $this->form->getInput('event_tld',$group); ?></li>
			<li id="eventmap2" style="display:none"><?php echo $this->form->getLabel('event_lg',$group); ?> <?php echo $this->form->getInput('event_lg',$group); ?></li>
		</ul>
	</fieldset>
	<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo Text::_('COM_JEM_REGISTRATION'); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('event_comunsolution',$group); ?> <?php echo $this->form->getInput('event_comunsolution',$group); ?></li>
			<li id="comm1" style="display:none"><?php echo $this->form->getLabel('event_comunoption',$group); ?> <?php echo $this->form->getInput('event_comunoption',$group); ?></li>
		</ul>
	</fieldset>
</div>
</div>
