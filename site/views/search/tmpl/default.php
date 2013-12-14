<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;


?>
<div id="jem" class="jem_jem">
<div class="buttons">
	<?php
		echo JEMOutput::submitbutton( $this->dellink, $this->params );
		echo JEMOutput::archivebutton( $this->params, $this->task );
		echo JEMOutput::printbutton( $this->print_link, $this->params );
	?>
</div>

<?php if ($this->params->def( 'show_page_title', 1 )) : ?>

    <h1 class="componentheading">
		<?php echo $this->escape($this->pagetitle); ?>
	</h1>

<?php endif; ?>


<?php if ($this->params->get('showintrotext')) : ?>
	<div class="description no_space floattext">
		<?php echo $this->params->get('introtext'); ?>
	</div>
<?php endif; ?>

<form action="<?php echo $this->action; ?>" method="post" name="adminForm" id="adminForm">

<!--table-->
<?php if ($this->params->get('template_suffix')):?>
<?php echo $this->loadTemplate('table_'. $this->params->get('template_suffix')); ?>
<?php else: ?>
<?php echo $this->loadTemplate('table'); ?>
<?php endif;?>

<p>
<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="" />
<input type="hidden" name="task" value="<?php echo $this->task; ?>" />
<input type="hidden" name="view" value="search" />
</p>
</form>

<!--footer-->

<div class="pagination">
	<?php echo $this->pagination->getPagesLinks(); ?>
</div>


<div class="copyright">
	<?php echo JEMOutput::footer( ); ?>
</div>

</div>