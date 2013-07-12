<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>
<div id="jem"
	class="jem_venues_view">
	<p class="buttons">
		<?php
		echo JEMOutput::submitbutton( $this->dellink, $this->params );
		echo JEMOutput::archivebutton( $this->params, $this->task );
		echo JEMOutput::printbutton( $this->print_link, $this->params );
		?>
	</p>

	<?php if ($this->params->def('show_page_title', 1)) : ?>
	<h1 class='componentheading'>
		<?php echo $this->escape($this->pagetitle); ?>
	</h1>
	<?php endif; ?>

	<!--Venue-->

	<?php foreach($this->rows as $row) : ?>

	<h2 class="jem">
		<a href="<?php echo $row->targetlink; ?>"><?php echo $this->escape($row->venue); ?>
		</a>
	</h2>

	<?php
	echo JEMOutput::flyer( $row, $row->limage, 'venue' );
	?>

	<dl class="location floattext">
		<?php if (($this->jemsettings->showdetlinkvenue == 1) && (!empty($row->url))) : ?>
		<dt class="venue_website">
			<?php echo JText::_( 'COM_JEM_WEBSITE' ).':'; ?>
		</dt>
		<dd class="venue_website">
			<a href="<?php echo $row->url; ?>" target="_blank"> <?php echo $row->urlclean; ?>
			</a>
		</dd>
		<?php endif; ?>

		<?php
		if ( $this->jemsettings->showdetailsadress == 1 ) :
		?>

		<?php if ( $row->street ) : ?>
		<dt class="venue_street">
			<?php echo JText::_( 'COM_JEM_STREET' ).':'; ?>
		</dt>
		<dd class="venue_street">
			<?php echo $this->escape($row->street); ?>
		</dd>
		<?php endif; ?>

		<?php if ( $row->plz ) : ?>
		<dt class="venue_plz">
			<?php echo JText::_( 'COM_JEM_ZIP' ).':'; ?>
		</dt>
		<dd class="venue_plz">
			<?php echo $this->escape($row->plz); ?>
		</dd>
		<?php endif; ?>

		<?php if ( $row->city ) : ?>
		<dt class="venue_city">
			<?php echo JText::_( 'COM_JEM_CITY' ).':'; ?>
		</dt>
		<dd class="venue_city">
			<?php echo $this->escape($row->city); ?>
		</dd>
		<?php endif; ?>

		<?php if ( $row->state ) : ?>
		<dt class="venue_state">
			<?php echo JText::_( 'COM_JEM_STATE' ).':'; ?>
		</dt>
		<dd class="venue_state">
			<?php echo $this->escape($row->state); ?>
		</dd>
		<?php endif; ?>

		<?php if ( $row->country ) : ?>
		<dt class="venue_country">
			<?php echo JText::_( 'COM_JEM_COUNTRY' ).':'; ?>
		</dt>
		<dd class="venue_country">
			<?php echo $row->countryimg ? $row->countryimg : $row->country; ?>
		</dd>
		<?php endif; ?>

		<dt class="venue_assignedevents">
			<?php echo JText::_( 'COM_JEM_EVENTS' ).':'; ?>
		</dt>
		<dd class="venue_assignedevents">
			<a href="<?php echo $row->targetlink; ?>"><?php echo $row->assignedevents; ?>
			</a>
		</dd>
		<?php 
		if ($this->jemsettings->showmapserv == 1)
		 		{ 
			echo JEMOutput::mapicon($row);
				}  
		endif;
		?>

	</dl>
	<p>
		<?php 
		if ($this->jemsettings->showmapserv == 2)
		{
			echo JEMOutput::mapicon($row);
		}
		?>
	</p>

	<?php if ($this->jemsettings->showlocdescription == 1) :	?>
	<h2 class="description">
		<?php echo JText::_( 'COM_JEM_DESCRIPTION' ).':'; ?>
	</h2>
	<div class="description">
		<?php echo $row->locdescription; ?>
	</div>
	<?php endif; ?>
	<?php endforeach; ?>

	<!--pagination-->
	<div class="pagination">
		<?php echo $this->pagination->getPagesLinks(); ?>
	</div>


	<!--copyright-->
	<p class="copyright">
		<?php echo JEMOutput::footer( ); ?>
	</p>
</div>
