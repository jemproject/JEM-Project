<?php
/**
 * @version 1.9.7
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;


?>
<div id="jem" class="jem_venues<?php echo $this->pageclass_sfx;?>">
	<div class="buttons">
		<?php
		echo JemOutput::addvenuebutton($this->addvenuelink, $this->params, $this->jemsettings);
		/*echo JemOutput::archivebutton($this->params, $this->task);*/
		echo JemOutput::submitbutton($this->addeventlink, $this->params);
		echo JemOutput::printbutton($this->print_link, $this->params);
		?>
	</div>

	<?php if ($this->params->get('show_page_heading', 1)) : ?>
	<h1 class='componentheading'>
		<?php echo $this->escape($this->params->get('page_heading')); ?>
	</h1>
	<?php endif; ?>

	<div class="clr"> </div>

	<!--Venue-->

	<?php foreach($this->rows as $row) : ?>
	<div itemscope itemtype="http://schema.org/Place">
		<h2 class="jem">
			<a href="<?php echo $row->linkEventsPublished; ?>" itemprop="url"><span itemprop="name"><?php echo $this->escape($row->venue); ?></span></a>
		</h2>

		<!-- FLYER -->
		<?php echo JemOutput::flyer( $row, $row->limage, 'venue' ); ?>

		<!--  -->
		<dl class="location">
			<?php if (($this->settings->get('global_show_detlinkvenue',1)) && (!empty($row->url))) : ?>
			<dt class="venue_website">
				<?php echo JText::_('COM_JEM_WEBSITE').':'; ?>
			</dt>
			<dd class="venue_website">
				<a href="<?php echo $row->url; ?>" target="_blank"> <?php echo $row->urlclean; ?></a>
			</dd>
			<?php endif; ?>
		</dl>

		<?php if ( $this->settings->get('global_show_detailsadress',1)) : ?>
			<dl class="location floattext" itemprop="address" itemscope itemtype="http://schema.org/PostalAddress">
				<?php if ($row->street) : ?>
				<dt class="venue_street">
					<?php echo JText::_('COM_JEM_STREET').':'; ?>
				</dt>
				<dd class="venue_street" itemprop="streetAddress">
					<?php echo $this->escape($row->street); ?>
				</dd>
				<?php endif; ?>

				<?php if ($row->postalCode) : ?>
				<dt class="venue_postalCode">
					<?php echo JText::_('COM_JEM_ZIP').':'; ?>
				</dt>
				<dd class="venue_postalCode" itemprop="postalCode">
					<?php echo $this->escape($row->postalCode); ?>
				</dd>
				<?php endif; ?>

				<?php if ($row->city) : ?>
				<dt class="venue_city">
					<?php echo JText::_('COM_JEM_CITY').':'; ?>
				</dt>
				<dd class="venue_city" itemprop="addressLocality">
					<?php echo $this->escape($row->city); ?>
				</dd>
				<?php endif; ?>

				<?php if ($row->state) : ?>
				<dt class="venue_state">
					<?php echo JText::_('COM_JEM_STATE').':'; ?>
				</dt>
				<dd class="venue_state" itemprop="addressRegion">
					<?php echo $this->escape($row->state); ?>
				</dd>
				<?php endif; ?>

				<?php if ($row->country) : ?>
				<dt class="venue_country">
					<?php echo JText::_('COM_JEM_COUNTRY').':'; ?>
				</dt>
				<dd class="venue_country">
					<?php echo $row->countryimg ? $row->countryimg : $row->country; ?>
					<meta itemprop="addressCountry" content="<?php echo $row->country; ?>" />
				</dd>
				<?php endif; ?>

				<?php if ($this->settings->get('global_show_mapserv') == 1) : ?>
					<?php echo JemOutput::mapicon($row,null,$this->settings); ?>
				<?php endif; ?>
			</dl>

			<dl>
				<dt class="venue_eventspublished">
					<?php echo JText::_('COM_JEM_VENUES_EVENTS_PUBLISHED').':'; ?>
				</dt>
				<dd class="venue_eventspublished">
					<a href="<?php echo $row->linkEventsPublished; ?>"><?php echo $row->EventsPublished; ?></a>
				</dd>
				<dt class="venue_archivedevents">
					<?php echo JText::_('COM_JEM_VENUES_EVENTS_ARCHIVED').':'; ?>
				</dt>
				<dd class="venue_archivedevents">
					<a href="<?php echo $row->linkEventsArchived; ?>"><?php echo $row->EventsArchived; ?></a>
				</dd>
			</dl>

			<?php if ($this->settings->get('global_show_mapserv') == 2) : ?>
				<?php echo JemOutput::mapicon($row,null,$this->settings); ?>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ($this->settings->get('global_show_mapserv')== 3) : ?>
			<input type="hidden" id="latitude" value="<?php echo $row->latitude;?>">
			<input type="hidden" id="longitude" value="<?php echo $row->longitude;?>">

			<input type="hidden" id="venue" value="<?php echo $row->venue;?>">
			<input type="hidden" id="street" value="<?php echo $row->street;?>">
			<input type="hidden" id="city" value="<?php echo $row->city;?>">
			<input type="hidden" id="state" value="<?php echo $row->state;?>">
			<input type="hidden" id="postalCode" value="<?php echo $row->postalCode;?>">
			<?php echo JemOutput::mapicon($row,'venues',$this->settings); ?>
		<?php endif; ?>

		<?php if ($this->settings->get('global_show_locdescription',1) && $row->locdescription != '' && $row->locdescription != '<br />') : ?>
			<h2 class="description">
				<?php echo JText::_('COM_JEM_VENUE_DESCRIPTION').':'; ?>
			</h2>
			<div class="description" itemprop="description">
				<?php echo $row->locdescription; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php endforeach; ?>

	<!--pagination-->
	<div class="pagination">
		<?php echo $this->pagination->getPagesLinks(); ?>
	</div>

	<!--copyright-->
	<div class="copyright">
		<?php echo JemOutput::footer( ); ?>
	</div>
</div>