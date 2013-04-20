<?php
/**
 * @version 1.1 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

// no direct access
defined( '_JEXEC' ) or die;
JHTML::_('behavior.modal');
?>
<div id="jem" class="el_categoryevents">
<p class="buttons">
	<?php
		if ( !$this->params->get( 'popup' ) ) : //don't show in printpopup
			echo JEMOutput::submitbutton( $this->dellink, $this->params );
			echo JEMOutput::archivebutton( $this->params, $this->task, $this->category->slug );
		endif;
		echo JEMOutput::mailbutton( $this->category->slug, 'categoryevents', $this->params );
		echo JEMOutput::printbutton( $this->print_link, $this->params );
	?>
</p>

<?php if ($this->params->def( 'show_page_title', 1 )) : ?>

    <h1 class='componentheading'>
		<?php echo $this->task == 'archive' ? $this->escape($this->category->catname.' - '.JText::_('COM_JEM_ARCHIVE')) : $this->escape($this->category->catname); ?>
	</h1>

<?php endif; ?>

<div class="floattext">
 
 <div class="catimg">
  <?php //flyer
	echo JEMOutput::flyer( $this->category, $this->cimage, 'category' );
	?>
	</div>
	
	<div class="catdescription">
		<p><?php echo $this->catdescription; ?></p>
	</div>
</div>
<!--subcategories-->
<?php 
//only show this part if subcategries are available
if (count($this->categories) && $this->category->id > 0) :
?>

<?php echo $this->loadTemplate('subcategories'); ?>

<?php endif; ?>

<?php echo $this->loadTemplate('attachments'); ?>

<form action="<?php echo $this->action; ?>" method="post" id="adminForm">
<!--table-->
<?php echo $this->loadTemplate('table'); ?>
<p>
<input type="hidden" name="option" value="com_jem" />
<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
<input type="hidden" name="view" value="categoryevents" />
<input type="hidden" name="task" value="<?php echo $this->task; ?>" />
<input type="hidden" name="id" value="<?php echo $this->category->id; ?>" />
<input type="hidden" name="Itemid" value="<?php echo $this->item->id;?>" />
</p>
</form>

<!--pagination-->

<?php if (!$this->params->get( 'popup' ) ) : ?>
<div class="pagination">
	<?php echo $this->pagination->getPagesLinks(); ?>
</div>

<p class="pagescounter">
	<?php 
	//echo $this->pagination->getPagesCounter(); 
	?>
</p>
<?php endif; ?>


<?php
echo JEMOutput::icalbutton($this->category->id, 'categoryevents');
?>


<!--copyright-->

<p class="copyright">
	<?php echo JEMOutput::footer( ); ?>
</p>
</div>