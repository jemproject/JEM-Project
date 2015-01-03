<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>
<form action="index.php" method="post" name="adminForm" id="adminForm">
	<div class="imghead">
		<?php echo JText::_('COM_JEM_SEARCH').' '; ?>
		<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->search; ?>" class="text_area" onChange="document.adminForm.submit();" />
		<button class="buttonfilter" type="submit"><?php echo JText::_('JSEARCH_FILTER_SUBMIT'); ?></button>
		<button class="buttonfilter" type="button" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
	</div>

	<div class="imglist">
		<?php
		for ($i = 0; $i < count($this->images); $i++) :
			$this->setImage($i);
			echo $this->loadTemplate('image');
		endfor;
		?>
	</div>

	<div class="clear"></div>

	<div class="pnav">
		<?php echo (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks() : $this->pagination->getListFooter()); ?>
	</div>

	<input type="hidden" name="option" value="com_jem" />
	<input type="hidden" name="view" value="imagehandler" />
	<input type="hidden" name="tmpl" value="component" />
	<input type="hidden" name="task" value="<?php echo $this->task; ?>" />
</form>