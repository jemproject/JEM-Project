<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
JHTML::_('behavior.modal', 'a.flyermodal');
?>
<div id="jem" class="jem_venue_events">
<div class="buttons">
	<?php
	
		/* @todo this button is disabled as we have to check the addvenue code 
		/* echo JEMOutput::addvenuebutton( $this->addvenuelink, $this->params, $this->jemsettings ); */
		echo JEMOutput::archivebutton( $this->params, $this->task, $this->venue->slug );
		echo JEMOutput::mailbutton( $this->venue->slug, 'venueevents', $this->params );
		echo JEMOutput::printbutton( $this->print_link, $this->params );
	?>
</div>
<?php if ($this->params->def('show_page_title', 1)) : ?>
	<h1 class='componentheading'>
		<?php 
		echo '&nbsp';
		?>
    
	</h1>
<?php endif; ?>

	<!--Venue-->
	
		<h2 class="jem">
			<?php echo $this->escape($this->pagetitle); ?>
			<?php echo JEMOutput::editbutton($this->item->id, $this->venue->id, $this->params, $this->allowedtoeditvenue, 'editvenue' ); ?>
		</h2>
	<?php //flyer
	echo JEMOutput::flyer( $this->venue, $this->limage, 'venue' );
	?>

	<dl class="location floattext">
		<?php if (($this->jemsettings->showdetlinkvenue == 1) && (!empty($this->venue->url))) : ?>
		<dt class="venue"><?php echo JText::_( 'COM_JEM_WEBSITE' ).':'; ?></dt>
			<dd class="venue">
					<a href="<?php echo $this->venue->url; ?>" target="_blank"> <?php echo $this->venue->urlclean; ?></a>
			</dd>
		<?php endif; ?>

		<?php if ( $this->jemsettings->showdetailsadress == 1 ) : ?>

  			<?php if ( $this->venue->street ) : ?>
  			<dt class="venue_street"><?php echo JText::_( 'COM_JEM_STREET' ).':'; ?></dt>
			<dd class="venue_street">
    			<?php echo $this->escape($this->venue->street); ?>
			</dd>
			<?php endif; ?>

			<?php if ( $this->venue->plz ) : ?>
  			<dt class="venue_plz"><?php echo JText::_( 'COM_JEM_ZIP' ).':'; ?></dt>
			<dd class="venue_plz">
    			<?php echo $this->escape($this->venue->plz); ?>
			</dd>
			<?php endif; ?>

			<?php if ( $this->venue->city ) : ?>
    		<dt class="venue_city"><?php echo JText::_( 'COM_JEM_CITY' ).':'; ?></dt>
    		<dd class="venue_city">
    			<?php echo $this->escape($this->venue->city); ?>
    		</dd>
    		<?php endif; ?>

    		<?php if ( $this->venue->state ) : ?>
			<dt class="venue_state"><?php echo JText::_( 'COM_JEM_STATE' ).':'; ?></dt>
			<dd class="venue_state">
    			<?php echo $this->escape($this->venue->state); ?>
			</dd>
			<?php endif; ?>

			<?php if ( $this->venue->country ) : ?>
			<dt class="venue_country"><?php echo JText::_( 'COM_JEM_COUNTRY' ).':'; ?></dt>
    		<dd class="venue_country">
    			<?php echo $this->venue->countryimg ? $this->venue->countryimg : $this->venue->country; ?>
    		</dd>
    		<?php endif; ?>
    		<?php 
    		if ($this->jemsettings->showmapserv == 1) 
    					{ 
					echo JEMOutput::mapicon($this->venue);
						}  
			endif; 
			?>
	</dl>

	<p>
	<?php 
		if ($this->jemsettings->showmapserv == 2)
		{
		echo JEMOutput::mapicon($this->venue);
		}
	?>
	</p>
	<?php
  	if ($this->jemsettings->showlocdescription == 1 && $this->venuedescription != '' && $this->venuedescription != '<br />') :
	?>

		<h2 class="description"><?php echo JText::_( 'COM_JEM_DESCRIPTION' ); ?></h2>
	  	<div class="description no_space floattext">
	  		<?php echo $this->venuedescription;	?>
		</div>

	<?php endif; ?>

	<?php echo $this->loadTemplate('attachments'); ?>

	<!--table-->
	<form action="<?php echo $this->action; ?>" method="post" id="adminForm">
	<?php echo $this->loadTemplate('table'); ?>

	<p>
	<input type="hidden" name="option" value="com_jem" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="" />
	<input type="hidden" name="view" value="venueevents" />
	<input type="hidden" name="id" value="<?php echo $this->venue->id; ?>" />
	<input type="hidden" name="Itemid" value="<?php echo $this->item->id;?>" />
	</p>
	</form>

<!--pagination-->

<div class="pagination">
	<?php echo $this->pagination->getPagesLinks(); ?>
</div>


<?php
echo JEMOutput::icalbutton($this->venue->id, 'venueevents');
?>



<!--copyright-->

<div class="copyright">
	<?php echo JEMOutput::footer( ); ?>
</div>
</div>
