<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

// no direct access
defined( '_JEXEC' ) or die;
?>
<div id="eventlist" class="el_categoryevents">
<p class="buttons">
	<?php
		if ( !$this->params->get( 'popup' ) ) : //don't show in printpopup
			echo ELOutput::submitbutton( $this->dellink, $this->params );
			echo ELOutput::archivebutton( $this->params, $this->task, $this->category->slug );
		endif;
		echo ELOutput::mailbutton( $this->category->slug, 'categoryevents', $this->params );
		echo ELOutput::printbutton( $this->print_link, $this->params );
	?>
</p>

<?php if ($this->params->def( 'show_page_title', 1 )) : ?>

    <h1 class='componentheading'>
		<?php echo $this->task == 'archive' ? $this->escape($this->category->catname.' - '.JText::_('COM_JEM_ARCHIVE')) : $this->escape($this->category->catname); ?>
	</h1>

<?php endif; ?>

<div class="floattext">
  <?php if ($this->category->image) : ?>
	<div class="catimg">
		<?php echo $this->category->image; ?>
		
		
	</div>
  <?php endif; ?>
	<div class="catdescription">
		<?php echo $this->catdescription; ?>
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
<input type="hidden" name="filter_order_Dir" value="" />
<input type="hidden" name="view" value="categoryevents" />
<input type="hidden" name="task" value="<?php echo $this->task; ?>" />
<input type="hidden" name="id" value="<?php echo $this->category->id; ?>" />
<input type="hidden" name="Itemid" value="<?php echo $this->item->id;?>" />
</p>
</form>

<!--pagination-->

<?php if (!$this->params->get( 'popup' ) ) : ?>
<div class="pageslinks">
	<?php echo $this->pageNav->getPagesLinks(); ?>
</div>

<p class="pagescounter">
	<?php 
	//echo $this->pageNav->getPagesCounter(); 
	?>
</p>
<?php endif; ?>

<?php if ($this->params->get('events_ical', 1)): ?>
<span class="events-ical">
	<?php echo JHTML::link( JRoute::_('index.php?option=com_jem&view=categoryevents&id='. $this->category->id.'&format=raw&layout=ics'),
                          JHTML::image('media/com_jem/images/iCal2.0.png', JText::_('COM_JEM_EXPORT_ICS'))
	                        ); ?>
</span>
<?php endif; ?>

<!--copyright-->

<p class="copyright">
	<?php echo ELOutput::footer( ); ?>
</p>
</div>