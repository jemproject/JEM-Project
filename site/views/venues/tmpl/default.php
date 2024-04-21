<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

// HTMLHelper::_('behavior.modal', 'a.flyermodal');
?>
<div id="jem" class="jem_venues<?php echo $this->pageclass_sfx;?>">
	<div class="buttons">
		<?php
		$btn_params = array('task' => $this->task, 'print_link' => $this->print_link);
		echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
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
		<div itemscope itemtype="https://schema.org/Place">
			<h2 class="jem">
				<a href="<?php echo $row->linkEventsPublished; ?>" itemprop="url"><span itemprop="name"><?php echo $this->escape($row->venue); ?></span></a>
			</h2>

			<!-- FLYER -->
			<?php echo JemOutput::flyer( $row, $row->limage, 'venue' ); ?>

			<!--  -->
			<dl class="location">
				<?php if (($this->settings->get('global_show_detlinkvenue',1)) && (!empty($row->url))) : ?>
				<dt class="venue_website">
					<?php echo Text::_('COM_JEM_WEBSITE').':'; ?>
				</dt>
				<dd class="venue_website">
					<a href="<?php echo $this->escape($row->url); ?>" target="_blank">
					<?php
						if (\Joomla\String\StringHelper::strlen($row->url) > 35) {
							$urlclean = htmlspecialchars(\Joomla\String\StringHelper::substr($row->url, 0 , 35)) . '...';
						} else {
							$urlclean = htmlspecialchars($row->url);
						}
						echo $urlclean;
					?>
					</a>
				</dd>
				<?php endif; ?>
			</dl>

			<?php if ( $this->settings->get('global_show_detailsadress',1)) : ?>
			<dl class="location floattext" itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
				<?php if ($row->street) : ?>
				<dt class="venue_street">
					<?php echo Text::_('COM_JEM_STREET').':'; ?>
				</dt>
				<dd class="venue_street" itemprop="streetAddress">
					<?php echo $this->escape($row->street); ?>
				</dd>
				<?php endif; ?>

				<?php if ($row->postalCode) : ?>
				<dt class="venue_postalCode">
					<?php echo Text::_('COM_JEM_ZIP').':'; ?>
				</dt>
				<dd class="venue_postalCode" itemprop="postalCode">
					<?php echo $this->escape($row->postalCode); ?>
				</dd>
				<?php endif; ?>

				<?php if ($row->city) : ?>
				<dt class="venue_city">
					<?php echo Text::_('COM_JEM_CITY').':'; ?>
				</dt>
				<dd class="venue_city" itemprop="addressLocality">
					<?php echo $this->escape($row->city); ?>
				</dd>
				<?php endif; ?>

				<?php if ($row->state) : ?>
				<dt class="venue_state">
					<?php echo Text::_('COM_JEM_STATE').':'; ?>
				</dt>
				<dd class="venue_state" itemprop="addressRegion">
					<?php echo $this->escape($row->state); ?>
				</dd>
				<?php endif; ?>

				<?php if ($row->country) : ?>
				<dt class="venue_country">
					<?php echo Text::_('COM_JEM_COUNTRY').':'; ?>
				</dt>
				<dd class="venue_country">
					<?php if ($row->country) :
						$countryimg = JemHelperCountries::getCountryFlag($row->country);
						echo $countryimg ? $countryimg : $row->country;
					endif; ?>
					<meta itemprop="addressCountry" content="<?php echo $row->country; ?>" />
				</dd>
				<?php endif; ?>

				<!-- PUBLISHING STATE -->
				<?php if (isset($row->published) && (!empty($this->show_status) || $row->published == 0)) : ?>
				<dt class="published"><?php echo Text::_('JSTATUS'); ?>:</dt>
				<dd class="published">
					<?php switch ($row->published) {
					case  1: echo Text::_('JPUBLISHED');   break;
					case  0: echo Text::_('JUNPUBLISHED'); break;
					case  2: echo Text::_('JARCHIVED');    break;
					case -2: echo Text::_('JTRASHED');     break;
					} ?>
				</dd>
				<?php endif; ?>

				<?php if ($this->settings->get('global_show_mapserv') == 1 || $this->settings->get('global_show_mapserv') == 4) : ?>
					<?php echo JemOutput::mapicon($row,null,$this->settings); ?>
				<?php endif; ?>
			</dl>
			<?php elseif (isset($row->published) && (!empty($this->show_status) || $row->published == 0)) : ?>
			<!-- PUBLISHING STATE -->
			<dl class="floattext">
				<dt class="published"><?php echo Text::_('JSTATUS'); ?>:</dt>
				<dd class="published">
					<?php switch ($row->published) {
					case  1: echo Text::_('JPUBLISHED');   break;
					case  0: echo Text::_('JUNPUBLISHED'); break;
					case  2: echo Text::_('JARCHIVED');    break;
					case -2: echo Text::_('JTRASHED');     break;
					} ?>
				</dd>
			</dl>
			<?php endif; ?>

			<dl class="floattext">
				<dt class="venue_eventspublished">
					<?php echo Text::_('COM_JEM_VENUES_EVENTS_PUBLISHED').':'; ?>
				</dt>
				<dd class="venue_eventspublished">
					<a href="<?php echo $row->linkEventsPublished; ?>"><?php echo $row->EventsPublished; ?></a>
				</dd>
			</dl>

			<dl class="floattext">
				<dt class="venue_archivedevents">
					<?php echo Text::_('COM_JEM_VENUES_EVENTS_ARCHIVED').':'; ?>
				</dt>
				<dd class="venue_archivedevents">
					<a href="<?php echo $row->linkEventsArchived; ?>"><?php echo $row->EventsArchived; ?></a>
				</dd>
			</dl>

			<?php if ( $this->settings->get('global_show_detailsadress',1)) : ?>
				<?php if ($this->settings->get('global_show_mapserv') == 2 || $this->settings->get('global_show_mapserv') == 5) : ?>
				<div class="jem-map">
					<?php echo JemOutput::mapicon($row,null,$this->settings); ?>
				</div>
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
				<?php echo Text::_('COM_JEM_VENUE_DESCRIPTION').':'; ?>
			</h2>
			<div class="description" itemprop="description">
				<?php echo $row->locdescription; ?>
			</div>
			<?php else : ?>
			<div class="clr"> </div>
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
<?php echo JemOutput::lightbox(); ?>