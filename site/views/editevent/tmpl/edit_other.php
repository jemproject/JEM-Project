<?php
/**
 * @version 2.3.1
 * @package JEM
 * @copyright (C) 2013-2021 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

$max_custom_fields = $this->settings->get('global_editevent_maxnumcustomfields', -1); // default to All
?>

	<!-- CUSTOM FIELDS -->
	<?php if ($max_custom_fields != 0) : ?>
	<fieldset class="panelform">
		<legend><?php echo JText::_('COM_JEM_EVENT_CUSTOMFIELDS_LEGEND'); ?></legend>
		<ul class="adminformlist">
			<?php
				$fields = $this->form->getFieldset('custom');
				if ($max_custom_fields < 0) :
					$max_custom_fields = count($fields);
				endif;
				$cnt = 0;
				foreach($fields as $field) :
					if (++$cnt <= $max_custom_fields) :
					?><li><?php echo $field->label; ?><?php echo $field->input; ?></li><?php
					endif;
				endforeach;
			?>
		</ul>
	</fieldset>
	<?php endif; ?>

