<?php
/**
 * @version 1.9.6
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
		echo JEMOutput::publishbutton();
		echo JEMOutput::unpublishbutton();
		echo JEMOutput::trashbutton();
	?>
</div>
<?php if ($this->params->get('show_page_heading', 1)) : ?>
	<h1 class="componentheading">
		<?php echo $this->escape($this->params->get('page_heading')); ?>
	</h1>
<?php endif; ?>

<!--table-->

<?php
	echo $this->loadTemplate('events');
?>

<!--footer-->

<div class="copyright">
	<?php echo JEMOutput::footer( ); ?>
</div>

</div>