<?php
/**
 * @version 2.1.5
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;
?>

<div id="jem" class="jem_myvenues<?php echo $this->pageclass_sfx;?>">
	<div class="buttons">
		<?php if (!empty($this->canPublishVenue)) :
			echo JemOutput::publishbutton('myvenues');
			echo JemOutput::unpublishbutton('myvenues');
		//	echo JemOutput::trashbutton('myvenues');
		endif; ?>
	</div>

	<?php if ($this->params->get('show_page_heading', 1)) : ?>
		<h1 class="componentheading">
			<?php echo $this->escape($this->params->get('page_heading')); ?>
		</h1>
	<?php endif; ?>

	<!--table-->
	<?php echo $this->loadTemplate('venues');?>

	<!--footer-->
	<div class="copyright">
		<?php echo JEMOutput::footer( ); ?>
	</div>
</div>
