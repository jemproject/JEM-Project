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
use Joomla\CMS\HTML\HTMLHelper;
?>

<fieldset class="adminform">
	<legend><?php echo Text::_('COM_JEM_EDITEVENT_PUBLISH_TAB'); ?></legend>
	<dl class="jem-dl">
		<dt><?php echo $this->form->getLabel('featured'); ?></dt>
		<dd><?php echo $this->form->getInput('featured'); ?></dd>
		<dt><?php echo $this->form->getLabel('published'); ?></dt>
		<dd><?php echo $this->form->getInput('published'); ?></dd>
		<dt><?php echo $this->form->getLabel('access'); ?></dt>
		<dd><?php
			echo HTMLHelper::_(
				'select.genericlist',
				$this->access,
				'jform[access]',
				array('list.attr' => ' class="form-select inputbox" size="1"', 'list.select' => $this->item->access, 'option.attr' => 'disabled', 'id' => 'access')
			);
			?>
		</dd>

		<p>&nbsp;</p>
</fieldset>
		<!-- START META FIELDSET -->
<fieldset class="adminform">
		<legend><?php echo Text::_('COM_JEM_METADATA'); ?></legend>
		<div class="formelm-area">
			<input class="inputbox btn btn-secondary" type="button" onclick="insert_keyword('[title]')" value="<?php echo Text::_('COM_JEM_TITLE');	?>" />
			<input class="inputbox btn btn-secondary" type="button" onclick="insert_keyword('[a_name]')" value="<?php echo Text::_('COM_JEM_VENUE'); ?>" />
			<input class="inputbox btn btn-secondary" type="button" onclick="insert_keyword('[categories]')" value="<?php echo Text::_('COM_JEM_CATEGORIES'); ?>" />
			<input class="inputbox btn btn-secondary" type="button" onclick="insert_keyword('[dates]')" value="<?php echo Text::_('COM_JEM_DATE'); ?>" />
			<input class="inputbox btn btn-secondary" type="button" onclick="insert_keyword('[times]')" value="<?php echo Text::_('COM_JEM_TIME'); ?>" />
			<input class="inputbox btn btn-secondary" type="button" onclick="insert_keyword('[enddates]')" value="<?php echo Text::_('COM_JEM_ENDDATE'); ?>" />
			<input class="inputbox btn btn-secondary" type="button" onclick="insert_keyword('[endtimes]')" value="<?php echo Text::_('COM_JEM_ENDTIME'); ?>" />
			<br />
			<br />
			<?php
			if (!empty($this->item->meta_keywords)) {
				$meta_keywords = $this->item->meta_keywords;
			} else {
				$meta_keywords = $this->jemsettings->meta_keywords;
			}
			?>
			<dl class="jem-dl">
				<dt>
					<label for="meta_keywords">
						<?php echo Text::_('COM_JEM_META_KEYWORDS') . ':'; ?>
					</label>
				</dt>
				<dd><textarea class="inputbox" name="meta_keywords" id="meta_keywords" rows="5" cols="40" maxlength="150" onfocus="get_inputbox('meta_keywords')" onblur="change_metatags()"><?php echo $meta_keywords; ?></textarea></dd>
			</dl>
		</div>
		<div class="formelm-area">
			<?php
			if (!empty($this->item->meta_description)) {
				$meta_description = $this->item->meta_description;
			} else {
				$meta_description = $this->jemsettings->meta_description;
			}
			?>
			<dl class="jem-dl">
				<dt>
					<label for="meta_description">
						<?php echo Text::_('COM_JEM_META_DESCRIPTION') . ':'; ?>
					</label>
				</dt>
				<dd><textarea class="inputbox" name="meta_description" id="meta_description" rows="5" cols="40" maxlength="200" onfocus="get_inputbox('meta_description')" onblur="change_metatags()"><?php echo $meta_description; ?></textarea></dd>
			</dl>
		</div>
		<!-- include the metatags end-->

		<script type="text/javascript">
			<!--
			starter("<?php
						echo Text::_('COM_JEM_META_ERROR');
						?>"); // window.onload is already in use, call the function manualy instead
			-->
		</script>

</fieldset>
