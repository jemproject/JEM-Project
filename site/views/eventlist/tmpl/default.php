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

defined( '_JEXEC' ) or die;
?>
<div id="eventlist" class="el_eventlist">
<p class="buttons">
	<?php
		if ( !$this->params->get( 'popup' ) ) : //don't show in printpopup
			echo ELOutput::submitbutton( $this->dellink, $this->params );
			echo ELOutput::archivebutton( $this->params, $this->task );
		endif;

		echo ELOutput::printbutton( $this->print_link, $this->params );
		
	?>
</p>

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

<!--table-->

<form action="<?php echo $this->action; ?>" method="post" name="adminForm" id="adminForm">

<?php echo $this->loadTemplate('table'); ?>

<p>
<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />

<input type="hidden" name="task" value="<?php echo $this->task; ?>" />
<input type="hidden" name="view" value="eventlist" />
</p>
</form>

<!--footer-->

<div class="pageslinks">
	<?php echo $this->pageNav->getPagesLinks(); ?>
</div>

<?php
echo ELOutput::icalbutton('', 'eventlist');

?>


<p class="copyright">
	<?php echo ELOutput::footer( ); ?>
</p>

</div>