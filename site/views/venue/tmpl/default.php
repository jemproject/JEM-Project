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
<div id="jem" class="jem_venue" itemscope="itemscope" itemtype="http://schema.org/Place">
	<div class="buttons">
		<?php
			echo JEMOutput::addvenuebutton($this->addvenuelink, $this->params, $this->jemsettings);
			echo JEMOutput::submitbutton($this->addeventlink, $this->params);
			echo JEMOutput::archivebutton($this->params, $this->task, $this->venue->slug);
			echo JEMOutput::mailbutton($this->venue->slug, 'venue', $this->params);
			echo JEMOutput::printbutton($this->print_link, $this->params);
		?>
	</div>
	<?php if ($this->params->def('show_page_title', 1)) : ?>
		<h1 class='componentheading'>
			<span itemprop="name"><?php echo $this->escape($this->pagetitle); ?></span>
		</h1>
	<?php endif; ?>

	<!--Venue-->
	<h2 class="jem">
			<?php echo JText::_('COM_JEM_VENUE'); ?>
			<?php echo JEMOutput::editbutton($this->venue, $this->params, NULL, $this->allowedtoeditvenue, 'venue' ); ?>
	</h2>
	<?php echo JEMOutput::flyer( $this->venue, $this->limage, 'venue' ); ?>

	<?php if (($this->settings->get('global_show_detlinkvenue',1)) && (!empty($this->venue->url))) : ?>
		<dl class="location">
			<dt class="venue"><?php echo JText::_('COM_JEM_WEBSITE').':'; ?></dt>
			<dd class="venue">
				<a href="<?php echo $this->venue->url; ?>" target="_blank"><?php echo $this->venue->urlclean; ?></a>
			</dd>
		</dl>
	<?php endif; ?>

	<?php if ($this->settings->get('global_show_detailsadress',1)) : ?>
		<dl class="location floattext" itemprop="address" itemscope itemtype="http://schema.org/PostalAddress">
			<?php if ( $this->venue->street ) : ?>
			<dt class="venue_street"><?php echo JText::_('COM_JEM_STREET').':'; ?></dt>
			<dd class="venue_street" itemprop="streetAddress">
				<?php echo $this->escape($this->venue->street); ?>
			</dd>
			<?php endif; ?>

			<?php if ( $this->venue->postalCode ) : ?>
			<dt class="venue_postalCode"><?php echo JText::_('COM_JEM_ZIP').':'; ?></dt>
			<dd class="venue_postalCode" itemprop="postalCode">
				<?php echo $this->escape($this->venue->postalCode); ?>
			</dd>
			<?php endif; ?>

			<?php if ( $this->venue->city ) : ?>
			<dt class="venue_city"><?php echo JText::_('COM_JEM_CITY').':'; ?></dt>
			<dd class="venue_city" itemprop="addressLocality">
				<?php echo $this->escape($this->venue->city); ?>
			</dd>
			<?php endif; ?>

			<?php if ( $this->venue->state ) : ?>
			<dt class="venue_state"><?php echo JText::_('COM_JEM_STATE').':'; ?></dt>
			<dd class="venue_state" itemprop="addressRegion">
				<?php echo $this->escape($this->venue->state); ?>
			</dd>
			<?php endif; ?>

			<?php if ( $this->venue->country ) : ?>
			<dt class="venue_country"><?php echo JText::_('COM_JEM_COUNTRY').':'; ?></dt>
			<dd class="venue_country">
				<?php echo $this->venue->countryimg ? $this->venue->countryimg : $this->venue->country; ?>
				<meta itemprop="addressCountry" content="<?php echo $this->venue->country; ?>" />
			</dd>
			<?php endif; ?>


			<?php
		for($cr = 1; $cr <= 10; $cr++) {
			$currentRow = $this->venue->{'custom'.$cr};
			if(substr($currentRow, 0, 7) == "http://") {
				$currentRow = '<a href="'.$this->escape($currentRow).'" target="_blank">'.$this->escape($currentRow).'</a>';
 			}
			if($currentRow) {
		?>
				<dt class="custom<?php echo $cr; ?>"><?php echo JText::_('COM_JEM_VENUE_CUSTOM_FIELD'.$cr).':'; ?></dt>
				<dd class="custom<?php echo $cr; ?>"><?php echo $currentRow; ?></dd>
		<?php
			}
		}
		?>

			<?php if ($this->settings->get('global_showmapser')== 1) : ?>
				<?php echo JEMOutput::mapicon($this->venue); ?>
			<?php endif; ?>
		</dl>
		<?php if ($this->settings->get('global_show_mapserv')== 2) : ?>
			<?php echo JEMOutput::mapicon($this->venue); ?>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ($this->settings->get('global_show_locdescription',1) && $this->venuedescription != ''
 		&& $this->venuedescription != '<br />') : ?>

		<h2 class="description"><?php echo JText::_('COM_JEM_VENUE_DESCRIPTION'); ?></h2>
		<div class="description no_space floattext" itemprop="description">
			<?php echo $this->venuedescription; ?>
		</div>
	<?php endif; ?>

	<?php $this->attachments = $this->venue->attachments; ?>
	<?php echo $this->loadTemplate('attachments'); ?>

	<!--table-->
	<form action="<?php echo $this->action; ?>" method="post" id="adminForm">
		<?php echo $this->loadTemplate('table'); ?>

		<p>
		<input type="hidden" name="option" value="com_jem" />
		<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
		<input type="hidden" name="filter_order_Dir" value="" />
		<input type="hidden" name="view" value="venue" />
		<input type="hidden" name="id" value="<?php echo $this->venue->id; ?>" />
		</p>
	</form>

	<!--pagination-->
	<div class="pagination">
		<?php echo $this->pagination->getPagesLinks(); ?>
	</div>
	<?php echo JEMOutput::icalbutton($this->venue->id, 'venue'); ?>
	<!--copyright-->
	<div class="copyright">
		<?php echo JEMOutput::footer( ); ?>
	</div>
</div>