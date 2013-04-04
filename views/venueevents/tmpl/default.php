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
<div id="eventlist" class="el_venueevents">
<p class="buttons">
	<?php
		if ( !$this->params->get( 'popup' ) ) : //don't show in printpopup
			echo ELOutput::submitbutton( $this->dellink, $this->params );
			echo ELOutput::archivebutton( $this->params, $this->task, $this->venue->slug );
		endif;
		echo ELOutput::mailbutton( $this->venue->slug, 'venueevents', $this->params );
		echo ELOutput::printbutton( $this->print_link, $this->params );
	?>
</p>
<?php if ($this->params->def('show_page_title', 1)) : ?>
	<h1 class='componentheading'>
		<?php echo $this->escape($this->pagetitle); ?>
    <?php echo ELOutput::editbutton($this->item->id, $this->venue->id, $this->params, $this->allowedtoeditvenue, 'editvenue' ); ?>
	</h1>
<?php endif; ?>

	<!--Venue-->
	<?php //flyer
	echo ELOutput::flyer( $this->venue, $this->limage, 'venue' );
	?>

	<dl class="location floattext">
		<?php if (($this->elsettings->showdetlinkvenue == 1) && (!empty($this->venue->url))) : ?>
		<dt class="venue"><?php echo JText::_( 'COM_EVENTLIST_WEBSITE' ).':'; ?></dt>
			<dd class="venue">
					<a href="<?php echo $this->venue->url; ?>" target="_blank"> <?php echo $this->venue->urlclean; ?></a>
			</dd>
		<?php endif; ?>

		<?php if ( $this->elsettings->showdetailsadress == 1 ) : ?>

  			<?php if ( $this->venue->street ) : ?>
  			<dt class="venue_street"><?php echo JText::_( 'COM_EVENTLIST_STREET' ).':'; ?></dt>
			<dd class="venue_street">
    			<?php echo $this->escape($this->venue->street); ?>
			</dd>
			<?php endif; ?>

			<?php if ( $this->venue->plz ) : ?>
  			<dt class="venue_plz"><?php echo JText::_( 'COM_EVENTLIST_ZIP' ).':'; ?></dt>
			<dd class="venue_plz">
    			<?php echo $this->escape($this->venue->plz); ?>
			</dd>
			<?php endif; ?>

			<?php if ( $this->venue->city ) : ?>
    		<dt class="venue_city"><?php echo JText::_( 'COM_EVENTLIST_CITY' ).':'; ?></dt>
    		<dd class="venue_city">
    			<?php echo $this->escape($this->venue->city); ?>
    		</dd>
    		<?php endif; ?>

    		<?php if ( $this->venue->state ) : ?>
			<dt class="venue_state"><?php echo JText::_( 'COM_EVENTLIST_STATE' ).':'; ?></dt>
			<dd class="venue_state">
    			<?php echo $this->escape($this->venue->state); ?>
			</dd>
			<?php endif; ?>

			<?php if ( $this->venue->country ) : ?>
			<dt class="venue_country"><?php echo JText::_( 'COM_EVENTLIST_COUNTRY' ).':'; ?></dt>
    		<dd class="venue_country">
    			<?php echo $this->venue->countryimg ? $this->venue->countryimg : $this->venue->country; ?>
    		</dd>
    		<?php endif; ?>
		<?php
		endif; //showdetails ende
		?>
	</dl>

	<?php echo ELOutput::mapicon( $this->venue ); ?>
	<?php
  	if ($this->elsettings->showlocdescription == 1 && $this->venuedescription != '' && $this->venuedescription != '<br />') :
	?>

		<h2 class="description"><?php echo JText::_( 'COM_EVENTLIST_DESCRIPTION' ); ?></h2>
	  	<div class="description no_space floattext">
	  		<?php echo $this->venuedescription;	?>
		</div>

	<?php endif; ?>

	<?php echo $this->loadTemplate('attachments'); ?>

	<!--table-->
	<form action="<?php echo $this->action; ?>" method="post" id="adminForm">
	<?php echo $this->loadTemplate('table'); ?>

	<p>
	<input type="hidden" name="option" value="com_eventlist" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="" />
	<input type="hidden" name="view" value="venueevents" />
	<input type="hidden" name="id" value="<?php echo $this->venue->id; ?>" />
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
	<?php echo JHTML::link( JRoute::_('index.php?option=com_eventlist&view=venueevents&id='. $this->venue->id.'&format=raw&layout=ics'),
                          JHTML::image('components/com_eventlist/assets/images/iCal2.0.png', JText::_('COM_EVENTLIST_EXPORT_ICS'))
	                        ); ?>
</span>
<?php endif; ?>

<!--copyright-->

<p class="copyright">
	<?php echo ELOutput::footer( ); ?>
</p>
</div>
