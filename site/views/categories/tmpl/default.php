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
<div id="eventlist" class="el_categoriesview">
<p class="buttons">
	<?php
		echo ELOutput::submitbutton( $this->dellink, $this->params );
		echo ELOutput::archivebutton( $this->params, $this->task );
	?>
</p>

<?php if ($this->params->def( 'show_page_title', 1 )) : ?>
	<h1 class="componentheading">
		<?php echo $this->escape($this->pagetitle); ?>
	</h1>
<?php endif; ?>

<?php foreach ($this->rows as $row) : ?>

<div class="floattext">
	<h2 class="eventlist cat<?php echo $row->id; ?>">
		<?php echo JHTML::_('link', JRoute::_($row->linktarget), $this->escape($row->catname)); ?>
	</h2>

<?php if ($row->image) : ?>
	<div class="catimg">
	  	<?php  		
			 echo JHTML::_('link', JRoute::_($row->linktarget), $row->image);
		?>
		<p>
			<?php
			echo JText::_( 'COM_JEM_EVENTS' ).': ';
			echo JHTML::_('link', JRoute::_($row->linktarget), $row->assignedevents ? $row->assignedevents : '0');
			?>
		</p>

	</div>
<?php endif; ?>

	<div class="catdescription cat<?php echo $row->id; ?>"><?php echo $row->catdescription ; ?>
	<p>
		<?php
			echo JHTML::_('link', JRoute::_($row->linktarget), $row->linktext);
		?>
		(<?php echo $row->assignedevents ? $row->assignedevents : '0';?>)
	</p>
	</div>

</div>

<?php 
//only show this part if subcategries are available
if (count($row->subcats)) :
?>

<div class="subcategories">
<?php echo JText::_('COM_JEM_SUBCATEGORIES'); ?>
</div>
<?php
$n = count($row->subcats);
$i = 0;
?>
<div class="subcategorieslist">
	<?php foreach ($row->subcats as $sub) : ?>
	  <?php if ($this->params->get('showemptychilds',1) || $sub->assignedevents): ?>
			<strong><a href="<?php echo JRoute::_( 'index.php?view=categoryevents&id='. $sub->slug ); ?>"><?php echo $this->escape($sub->catname); ?></a></strong> (<?php echo $sub->assignedevents != null ? $sub->assignedevents : 0; ?>)
			<?php 
			$i++;
			if ($i != $n) :
				echo ',';
			endif;
		endif;
	endforeach; ?>
</div>

<?php endif; ?>

<?php endforeach; ?>

<!--pagination-->
<div class="pageslinks">
	<?php echo $this->pagination->getPagesLinks(); ?>
</div>

<div class="pagescounter">
	<?php 
	//echo $this->pagination->getPagesCounter(); 
	?>
</div>

<!--copyright-->

<p class="copyright">
	<?php echo ELOutput::footer( ); ?>
</p>
</div>