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


<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/nl_NL/all.js#xfbml=1";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>


<div id="jem" class="event_id<?php echo $this->row->did; ?> jem_details">
	<p class="buttons">
			<?php echo JEMOutput::mailbutton( $this->row->slug, 'details', $this->params ); ?>
			<?php echo JEMOutput::printbutton( $this->print_link, $this->params ); ?>
			<?php echo JEMOutput::icalbutton($this->row->slug, 'details'); ?>
	</p>

<?php if ($this->params->def( 'show_page_title', 1 )) : ?>
	<h1 class="componentheading">
		<?php echo $this->escape($this->row->title); ?>
	</h1>
<?php endif; ?>

<!-- Details EVENT -->
	<h2 class="jem">
		<?php
    	echo JText::_( 'COM_JEM_EVENT' );
    	echo '&nbsp;'.JEMOutput::editbutton($this->item->id, $this->row->did, $this->params, $this->allowedtoeditevent, 'editevent' );
    	?>
	</h2>

	<?php //flyer
	echo JEMOutput::flyer( $this->row, $this->dimage, 'event' );
	?>

	<dl class="event_info floattext">

		<?php if ($this->jemsettings->showdetailstitle == 1) : ?>
			<dt class="title"><?php echo JText::_( 'COM_JEM_TITLE' ).':'; ?></dt>
    		<dd class="title"><?php echo $this->escape($this->row->title); ?></dd>
		<?php
  		endif;
  		?>
  		<dt class="when"><?php echo JText::_( 'COM_JEM_WHEN' ).':'; ?></dt>
		<dd class="when">
			<?php
			if (JEMHelper::isValidDate($this->row->dates))
			{
				echo JEMOutput::formatdate($this->row->dates, $this->row->times);
    					
    		if (JEMHelper::isValidDate($this->row->enddates) && $this->row->enddates != $this->row->dates) :
    			echo ' - '.JEMOutput::formatdate($this->row->enddates, $this->row->endtimes);
    		endif;
			}
			else {
				echo JText::_('COM_JEM_OPEN_DATE');
			}
    		
    		if ($this->jemsettings->showtimedetails == 1) :
    	
				echo '&nbsp;'.JEMOutput::formattime($this->row->dates, $this->row->times);
						
				if ($this->row->endtimes) :
					echo ' - '.JEMOutput::formattime($this->row->enddates, $this->row->endtimes);
				endif;
			endif;
			?>
		</dd>
  		<?php
  		if ($this->row->locid != 0) :
  		?>
		    <dt class="where"><?php echo JText::_( 'COM_JEM_WHERE' ).':'; ?></dt>
		    <dd class="where">
    		<?php if (($this->jemsettings->showdetlinkvenue == 1) && (!empty($this->row->url))) : ?>

			    <a target="_blank" href="<?php echo $this->row->url; ?>"><?php echo $this->escape($this->row->venue); ?></a> -

			<?php elseif ($this->jemsettings->showdetlinkvenue == 2) : ?>

			    <a href="<?php echo JRoute::_( 'index.php?view=venueevents&id='.$this->row->venueslug ); ?>"><?php echo $this->row->venue; ?></a> -

			<?php elseif ($this->jemsettings->showdetlinkvenue == 0) :

				echo $this->escape($this->row->venue).' - ';

			endif;

			echo $this->escape($this->row->city); ?>

			</dd>

		<?php endif; 
		$n = count($this->categories);
		?>

		<dt class="category"><?php echo $n < 2 ? JText::_( 'COM_JEM_CATEGORY' ) : JText::_( 'COM_JEM_CATEGORIES' ); ?>:</dt>
    		<dd class="category">
    			<?php
				$i = 0;
    			foreach ($this->categories as $category) :
    			?>
					<a href="<?php echo JRoute::_( 'index.php?view=categoryevents&id='. $category->slug ); ?>"><?php echo $this->escape($category->catname); ?></a>
				<?php 
					$i++;
					if ($i != $n) :
						echo ',';
					endif;
				endforeach;
    			?>
			</dd><br />
	</dl>

  	<?php if ($this->jemsettings->showevdescription == 1 && $this->row->datdescription != '' && $this->row->datdescription != '<br />') : ?>

  	    <h2 class="description"><?php echo JText::_( 'COM_JEM_DESCRIPTION' ); ?></h2>
  		<div class="description event_desc">
  			<?php echo $this->row->datdescription; ?>
  		</div>

  	<?php endif; ?>
  	
  	<?php if ($this->row->attachments && count($this->row->attachments)):?>
  	    <h2 class="description"><?php echo JText::_( 'COM_JEM_EVENT_FILES' ); ?></h2>
  		<div>
  			<table class="event-file">
  				<tbody>
  			<?php foreach ($this->row->attachments as $file): ?>
	  				<tr>
	  					<td>
		  					<span class="event-file-dl-icon hasTip" title="<?php echo JText::_('COM_JEM_DOWNLOAD').' '.$this->escape($file->file).'::'.$this->escape($file->description);?>">
		  					<?php echo JHTML::link('index.php?option=com_jem&task=getfile&format=raw&file='.$file->id, 
		  					                       JHTML::image('media/com_jem/images/download_16.png', JText::_('COM_JEM_DOWNLOAD'))); ?></span>  			
	  					</td>
	  					<td class="event-file-name"><?php echo $this->escape($file->name ? $file->name : $file->file); ?></td>
	  				</tr>
  				</tbody>
  			<?php endforeach; ?>
  			</table>
  		</div>
  	<?php endif; ?>

  	
  	<!--  	Contact  --> 	
  <?php if ($this->row->conid != 0) : ?>
  
  <h2 class="contact">
			<?php echo JText::_( 'COM_JEM_CONTACT' ) ; ?>
		</h2>
  
  
  	<dl class="location floattext">
  	<?php if ( $this->row->conname ) : ?>
  				<dt class="con_name"><?php echo JText::_( 'COM_JEM_NAME' ).':'; ?></dt>
				<dd class="con_name">
    				<?php echo $this->escape($this->row->conname); ?>
				</dd>
				<?php endif; ?>
				
				<?php if ( $this->row->conemail ) : ?>
  				<dt class="con_email"><?php echo JText::_( 'COM_JEM_EMAIL' ).':'; ?></dt>
				<dd class="con_email">
    				<?php echo $this->escape($this->row->conemail); ?>
				</dd>
				<?php endif; ?>
				
				<?php if ( $this->row->contelephone ) : ?>
  				<dt class="con_telephone"><?php echo JText::_( 'COM_JEM_TELEPHONE' ).':'; ?></dt>
				<dd class="con_telephone">
    				<?php echo $this->escape($this->row->contelephone); ?>
				</dd>
				<?php endif; ?>
  	</dl>
  
  	<?php endif ?>
  	
  	
<!--  	Venue  -->

	<?php if ($this->row->locid != 0) : ?>

		<h2 class="location">
			<?php echo JText::_( 'COM_JEM_VENUE' ) ; ?>
  			<?php echo JEMOutput::editbutton($this->item->id, $this->row->locid, $this->params, $this->allowedtoeditvenue, 'editvenue' ); ?>
		</h2>

		<?php //flyer
		echo JEMOutput::flyer( $this->row, $this->limage, 'venue' );
		
		?>

		<dl class="location floattext">
			 <dt class="venue"><?php echo JText::_( 'COM_JEM_LOCATION' ).':'; ?></dt>
				<dd class="venue">
				<?php echo "<a href='".JRoute::_( 'index.php?view=venueevents&id='.$this->row->venueslug )."'>".$this->escape($this->row->venue)."</a>"; ?>

				<?php if (!empty($this->row->url)) : ?>
					&nbsp; - &nbsp;
					<a target="_blank" href="<?php echo $this->row->url; ?>"> <?php echo JText::_( 'COM_JEM_WEBSITE' ); ?></a>
				<?php
				endif;
				?>
				</dd>

			<?php
  			if ( $this->jemsettings->showdetailsadress == 1 ) :
  			?>

  				<?php if ( $this->row->street ) : ?>
  				<dt class="venue_street"><?php echo JText::_( 'COM_JEM_STREET' ).':'; ?></dt>
				<dd class="venue_street">
    				<?php echo $this->escape($this->row->street); ?>
				</dd>
				<?php endif; ?>

				<?php if ( $this->row->plz ) : ?>
  				<dt class="venue_plz"><?php echo JText::_( 'COM_JEM_ZIP' ).':'; ?></dt>
				<dd class="venue_plz">
    				<?php echo $this->escape($this->row->plz); ?>
				</dd>
				<?php endif; ?>

				<?php if ( $this->row->city ) : ?>
    			<dt class="venue_city"><?php echo JText::_( 'COM_JEM_CITY' ).':'; ?></dt>
    			<dd class="venue_city">
    				<?php echo $this->escape($this->row->city); ?>
    			</dd>
    			<?php endif; ?>

    			<?php if ( $this->row->state ) : ?>
    			<dt class="venue_state"><?php echo JText::_( 'COM_JEM_STATE' ).':'; ?></dt>
    			<dd class="venue_state">
    				<?php echo $this->escape($this->row->state); ?>
    			</dd>
				<?php endif; ?>

				<?php if ( $this->row->country ) : ?>
				<dt class="venue_country"><?php echo JText::_( 'COM_JEM_COUNTRY' ).':'; ?></dt>
    			<dd class="venue_country">
    				<?php echo $this->row->countryimg ? $this->row->countryimg : $this->row->country; ?>
    			</dd>
    			<?php endif; ?>
			<?php
			endif;
			?>
		</dl>
		<p><?php echo JEMOutput::mapicon( $this->row ); ?>&nbsp;</p>
		<?php if ($this->jemsettings->showlocdescription == 1 && $this->row->locdescription != '' && $this->row->locdescription != '<br />') :	?>

			<h2 class="location_desc"><?php echo JText::_( 'COM_JEM_DESCRIPTION' ); ?></h2>
  			<div class="description location_desc">
  				<?php echo $this->row->locdescription;	?>
  			</div>

		<?php endif; ?>

	<?php
	//row->locid !=0 end
	endif;
	?>

	<?php if ($this->row->registra == 1) : ?>

		<!-- Registration -->
		<?php echo $this->loadTemplate('attendees'); ?>

	<?php endif; ?>

	<?php echo $this->row->pluginevent->onEventDetailsEnd; ?>


<p class="copyright">
	<?php echo JEMOutput::footer( ); ?>
</p>

<?php if ($this->params->get('facebook', 1) == 1) {  
$currenturl = JURI::current(); ?>

<div class="fb-like" data-href="<?php echo $currenturl ?>" data-send="true" data-layout="button_count" data-width="450" data-show-faces="true" data-font="segoe ui"></div>
<?php   }   ?>

</div>