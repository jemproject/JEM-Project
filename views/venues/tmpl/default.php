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
<div id="eventlist" class="el_venuesview">
	<p class="buttons">
		<?php
			if ( !$this->params->get( 'popup' ) ) : //don't show in printpopup
				echo ELOutput::submitbutton( $this->dellink, $this->params );
				echo ELOutput::archivebutton( $this->params, $this->task );
			endif;

			echo ELOutput::printbutton( $this->print_link, $this->params );
		?>
	</p>

	<?php if ($this->params->def('show_page_title', 1)) : ?>
		<h1 class='componentheading'>
			<?php echo $this->escape($this->pagetitle); ?>
		</h1>
	<?php endif; ?>

	<!--Venue-->

	<?php foreach($this->rows as $row) : ?>
		
		<h2 class="eventlist">
			<a href="<?php echo $row->targetlink; ?>"><?php echo $this->escape($row->venue); ?></a>
		</h2>

			<?php
				echo ELOutput::flyer( $row, $row->limage, 'venue' );
			?>

			<dl class="location floattext">
				<?php if (($this->elsettings->showdetlinkvenue == 1) && (!empty($row->url))) : ?>
				<dt class="venue_website"><?php echo JText::_( 'COM_JEM_WEBSITE' ).':'; ?></dt>
	   			<dd class="venue_website">
					<a href="<?php echo $row->url; ?>" target="_blank"> <?php echo $row->urlclean; ?></a>
				</dd>
				<?php endif; ?>

				<?php
	  			if ( $this->elsettings->showdetailsadress == 1 ) :
	  			?>

	  			<?php if ( $row->street ) : ?>
	  			<dt class="venue_street"><?php echo JText::_( 'COM_JEM_STREET' ).':'; ?></dt>
				<dd class="venue_street">
	    			<?php echo $this->escape($row->street); ?>
				</dd>
				<?php endif; ?>

				<?php if ( $row->plz ) : ?>
	  			<dt class="venue_plz"><?php echo JText::_( 'COM_JEM_ZIP' ).':'; ?></dt>
				<dd class="venue_plz">
	    			<?php echo $this->escape($row->plz); ?>
				</dd>
				<?php endif; ?>

				<?php if ( $row->city ) : ?>
	    		<dt class="venue_city"><?php echo JText::_( 'COM_JEM_CITY' ).':'; ?></dt>
	    		<dd class="venue_city">
	    			<?php echo $this->escape($row->city); ?>
	    		</dd>
	    		<?php endif; ?>

	    		<?php if ( $row->state ) : ?>
				<dt class="venue_state"><?php echo JText::_( 'COM_JEM_STATE' ).':'; ?></dt>
				<dd class="venue_state">
	    			<?php echo $this->escape($row->state); ?>
				</dd>
				<?php endif; ?>

				<?php if ( $row->country ) : ?>
				<dt class="venue_country"><?php echo JText::_( 'COM_JEM_COUNTRY' ).':'; ?></dt>
	    		<dd class="venue_country">
	    			<?php echo $row->countryimg ? $row->countryimg : $row->country; ?>
	    		</dd>
	    		<?php endif; ?>

	    		<dt class="venue_assignedevents"><?php echo JText::_( 'COM_JEM_EVENTS' ).':'; ?></dt>
	    		<dd class="venue_assignedevents">
	    			<a href="<?php echo $row->targetlink; ?>"><?php echo $row->assignedevents; ?></a>
	    		</dd>
			<?php
			endif;
			?>

		</dl>
<p><?php echo ELOutput::mapicon( $row ); ?></p>
		
	    <?php if ($this->elsettings->showlocdescription == 1) :	?>
		<h2 class="description"><?php echo JText::_( 'COM_JEM_DESCRIPTION' ).':'; ?></h2>
		<div class="description">
	    	<?php echo $row->locdescription; ?>
		</div>
		<?php endif; ?>
	<?php endforeach; ?>

	<!--pagination-->
	<p class="pageslinks">
		<?php echo $this->pageNav->getPagesLinks(); ?>
	</p>

	<p class="pagescounter">
		<?php echo $this->pageNav->getPagesCounter(); ?>
	</p>

	<!--copyright-->
	<p class="copyright">
		<?php echo ELOutput::footer( ); ?>
	</p>
</div>