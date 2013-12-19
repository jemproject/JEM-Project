<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>
<div id="jem" class="jem_venues_view">
	<div class="buttons">
		<?php
		echo JEMOutput::addvenuebutton($this->addvenuelink, $this->params, $this->jemsettings);
		echo JEMOutput::archivebutton($this->params, $this->task);
		echo JEMOutput::submitbutton($this->addeventlink, $this->params);
		echo JEMOutput::printbutton($this->print_link, $this->params);
		?>
	</div>

	<?php if ($this->params->def('show_page_title', 1)) : ?>
	<h1 class='componentheading'>
		<?php echo $this->escape($this->pagetitle); ?>
	</h1>
	<?php endif; ?>

	<!--Venue-->

	<?php foreach($this->rows as $row) : ?>
	<div itemscope itemtype="http://schema.org/Place">
		<h2 class="jem">
			<a href="<?php echo $row->targetlink; ?>" itemprop="url"><span itemprop="name"><?php echo $this->escape($row->venue); ?></span></a>
		</h2>

		<!-- FLYER -->
		<?php echo JEMOutput::flyer( $row, $row->limage, 'venue' ); ?>

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

			<dt class="venue_assignedevents">
				<?php echo JText::_('COM_JEM_EVENTS').':'; ?>
			</dt>
			<dd class="venue_assignedevents">
				<a href="<?php echo $row->targetlink; ?>"><?php echo $row->assignedevents; ?></a>
			</dd>
		</dl>
		<?php if ( $this->settings->get('global_show_detailsadress',1)) : ?>
			<dl class="location floattext" itemprop="address" itemscope itemtype="http://schema.org/PostalAddress">
				<?php if ( $row->street ) : ?>
				<dt class="venue_street">
					<?php echo JText::_('COM_JEM_STREET').':'; ?>
				</dt>
				<dd class="venue_street" itemprop="streetAddress">
					<?php echo $this->escape($row->street); ?>
				</dd>
				<?php endif; ?>

				<?php if ( $row->postalCode ) : ?>
				<dt class="venue_postalCode">
					<?php echo JText::_('COM_JEM_ZIP').':'; ?>
				</dt>
				<dd class="venue_postalCode" itemprop="postalCode">
					<?php echo $this->escape($row->postalCode); ?>
				</dd>
				<?php endif; ?>

				<?php if ( $row->city ) : ?>
				<dt class="venue_city">
					<?php echo JText::_('COM_JEM_CITY').':'; ?>
				</dt>
				<dd class="venue_city" itemprop="addressLocality">
					<?php echo $this->escape($row->city); ?>
				</dd>
				<?php endif; ?>

				<?php if ( $row->state ) : ?>
				<dt class="venue_state">
					<?php echo JText::_('COM_JEM_STATE').':'; ?>
				</dt>
				<dd class="venue_state" itemprop="addressRegion">
					<?php echo $this->escape($row->state); ?>
				</dd>
				<?php endif; ?>

				<?php if ( $row->country ) : ?>
				<dt class="venue_country">
					<?php echo JText::_('COM_JEM_COUNTRY').':'; ?>
				</dt>
				<dd class="venue_country">
					<?php echo $row->countryimg ? $row->countryimg : $row->country; ?>
					<meta itemprop="addressCountry" content="<?php echo $row->country; ?>" />
				</dd>
				<?php endif; ?>

				<?php if ($this->settings->get('global_show_mapserv') == 1) : ?>
					<?php echo JEMOutput::mapicon($row); ?>
				<?php endif; ?>
			</dl>
			<?php if ($this->settings->get('global_show_mapserv') == 2) : ?>
				<?php echo JEMOutput::mapicon($row); ?>
			<?php endif; ?>
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
		<?php echo JEMOutput::footer( ); ?>
	</div>
</div>