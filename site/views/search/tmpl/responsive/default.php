<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>

<div id="jem" class="jem_search<?php echo $this->pageclass_sfx;?>">
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
  
  <h2>
    <?php echo Text::_('COM_JEM_SEARCH_SUBMIT');?>
  </h2>
	<!--table-->
	<form action="<?php echo htmlspecialchars($this->action); ?>" method="post" name="adminForm" id="adminForm">
		<?php
		/*if ($this->params->get('template_suffix')) {
			echo $this->loadTemplate('table_'. $this->params->get('template_suffix'));
		} else {*/
			echo $this->loadTemplate('table');
		//}
		?>

		<p>
			<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
			<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
			<input type="hidden" name="task" value="<?php echo $this->task; ?>" />
			<input type="hidden" name="view" value="search" />
		</p>
	</form>

	<!--footer-->
	<div class="pagination">
		<?php echo $this->pagination->getPagesLinks(); ?>
	</div>

	<div class="copyright">
		<?php echo JemOutput::footer( ); ?>
	</div>
</div>
