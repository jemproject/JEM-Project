<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$max_custom_fields = $this->settings->get('global_editvenue_maxnumcustomfields', -1); // default to All
?>

<!-- CUSTOM FIELDS -->
<?php if ($max_custom_fields != 0) : ?>
<fieldset class="panelform">
	<legend><?php echo Text::_('COM_JEM_EDITVENUE_CUSTOMFIELDS'); ?></legend>
	<dl class="adminformlist jem-dl-long">
		<?php
			$fields = $this->form->getFieldset('custom');
			if ($max_custom_fields < 0) :
				$max_custom_fields = count($fields);
			endif;
			$cnt = 0;
			foreach ($fields as $field) :
				if (++$cnt <= $max_custom_fields) :
		?>
		<dt><?php echo $field->label; ?></dt>
		<dd><?php echo $field->input; ?></dd>
		<?php
				endif;
			endforeach;
		?>
	</dl>
</fieldset>
<?php endif; ?>
