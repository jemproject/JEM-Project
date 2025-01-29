<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
?>

<div id="jem" class="jem_venueslist<?php echo $this->pageclass_sfx;?>">
	<div class="buttons">
		<?php
		//$btn_params = array('task' => $this->task, 'print_link' => $this->print_link);
		$btn_params = array('task' => $this->task, 'print_link' => Route::_('index.php?option=com_jem&view=venueslist&layout=print&task=print&tmpl=component&print=1'));
		echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
		?>
	</div>
	
		
	
	<?php if ($this->params->get('show_page_heading', 1)) : ?>
		<h1 class="componentheading">
			<?php echo $this->escape($this->params->get('page_heading')); ?>
		</h1>
	<?php endif; ?>

	<div class="clr"></div>

	<?php if ($this->params->get('showintrotext')) : ?>
		<div class="description no_space floattext">
			<?php echo $this->params->get('introtext'); ?>
		</div>
	<?php endif; ?>
	<!--table-->
	<form action="<?php echo htmlspecialchars($this->action); ?>" method="post" name="adminForm" id="adminForm">
	<?php echo $this->loadTemplate('venues');?>

																															 
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="option" value="com_jem" />
	<?php echo HTMLHelper::_('form.token'); ?>
	</form>
																					  
	<?php if ($this->params->get('showfootertext')) : ?>
		<div class="description no_space floattext">
			<?php echo $this->params->get('footertext'); ?>
		</div>
	<?php endif; ?>											  
	<!--footer-->
	<div class="copyright">
		<?php echo JEMOutput::footer( ); ?>
	</div>
</div>