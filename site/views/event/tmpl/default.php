<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

HTMLHelper::addIncludePath(JPATH_COMPONENT . '/helpers');

// Create shortcuts to some parameters.
$params      = $this->item->params;
$images      = json_decode($this->item->datimage);
$attribs     = json_decode($this->item->attribs);
$user        = JemFactory::getUser();
$jemsettings = JemHelper::config();
$app         = Factory::getApplication();
$document    = $app->getDocument();
$uri         = Uri::getInstance();

// Add expiration date, if old events will be archived or removed
if ($jemsettings->oldevent > 0) {
	$enddate = strtotime($this->item->enddates?:($this->item->dates?:date("Y-m-d")));
	$expDate = date("D, d M Y H:i:s", strtotime('+1 day', $enddate));
	$document->addCustomTag('<meta http-equiv="expires" content="' . $expDate . '"/>');
}

// HTMLHelper::_('behavior.modal', 'a.flyermodal');
?>
<?php if ($params->get('access-view')) { /* This will show nothings otherwise - ??? */ ?>
<div id="jem" class="event_id<?php echo $this->item->did; ?> jem_event<?php echo $this->pageclass_sfx;?>"
	itemscope="itemscope" itemtype="https://schema.org/Event">
  
  <meta itemprop="url" content="<?php echo rtrim($uri->base(), '/').JRoute::_(JemHelperRoute::getEventRoute($this->item->slug)); ?>" />
  <meta itemprop="identifier" content="<?php echo rtrim($uri->base(), '/').JRoute::_(JemHelperRoute::getEventRoute($this->item->slug)); ?>" />
  
	<div class="buttons">
		<?php
		$btn_params = array('slug' => $this->item->slug, 'print_link' => $this->print_link);
		echo JemOutput::createButtonBar($this->getName(), $this->permissions, $btn_params);
		?>
	</div>

	<div class="clr"> </div>

	<?php if ($this->params->get('show_page_heading', 1)) : ?>
		<h1 class="componentheading">
        	<?php echo $this->escape($this->params->get('page_heading')); ?>
        </h1>
	<?php endif; ?>

	<div class="clr"> </div>

	<!-- Event -->
	<h2 class="jem">
        <span style="white-space: nowrap;">
            <?php
            echo Text::_('COM_JEM_EVENT') . JemOutput::recurrenceicon($this->item) .' ';
            echo JemOutput::editbutton($this->item, $params, $attribs, $this->permissions->canEditEvent, 'editevent') .' ';
            echo JemOutput::copybutton($this->item, $params, $attribs, $this->permissions->canAddEvent, 'editevent');
            ?>
        </span>
	</h2>

	<?php echo JemOutput::flyer($this->item, $this->dimage, 'event'); ?>

	<dl class="event_info floattext">
		<?php if ($params->get('event_show_detailstitle',1)) : ?>
		<dt class="title"><?php echo Text::_('COM_JEM_TITLE'); ?>:</dt>
		<dd class="title" itemprop="name"><?php echo $this->escape($this->item->title); ?></dd>
		<?php
		endif;
		?>
		<dt class="when"><?php echo Text::_('COM_JEM_WHEN'); ?>:</dt>
		<dd class="when">
			<?php
			echo JemOutput::formatLongDateTime($this->item->dates, $this->item->times,$this->item->enddates, $this->item->endtimes);
			echo JemOutput::formatSchemaOrgDateTime($this->item->dates, $this->item->times,$this->item->enddates, $this->item->endtimes);
			?>
		</dd>
		<?php if ($this->item->locid != 0) : ?>
		<dt class="where"><?php echo Text::_('COM_JEM_WHERE'); ?>:</dt>
		<dd class="where"><?php
			if (($params->get('event_show_detlinkvenue') == 1) && (!empty($this->item->url))) :
				?><a target="_blank" href="<?php echo $this->item->url; ?>"><?php echo $this->escape($this->item->venue); ?></a><?php
			elseif (($params->get('event_show_detlinkvenue') == 2) && (!empty($this->item->venueslug))) :
				?><a href="<?php echo JRoute::_(JemHelperRoute::getVenueRoute($this->item->venueslug)); ?>"><?php echo $this->item->venue; ?></a><?php
			else/*if ($params->get('event_show_detlinkvenue') == 0)*/ :
				echo $this->escape($this->item->venue);
			endif;

			# will show "venue" or "venue - city" or "venue - city, state" or "venue, state"
			$city  = $this->escape($this->item->city);
			$state = $this->escape($this->item->state);
			if ($city)  { echo ' - ' . $city; }
			if ($state) { echo ', ' . $state; }
			?>
		</dd>
		<?php
		endif;
		$n = is_array($this->categories) ? count($this->categories) : 0;
		?>

		<dt class="category"><?php echo $n < 2 ? Text::_('COM_JEM_CATEGORY') : Text::_('COM_JEM_CATEGORIES'); ?>:</dt>
		<dd class="category">
		<?php
		$i = 0;
		foreach ((array)$this->categories as $category) :
			?><a href="<?php echo JRoute::_(JemHelperRoute::getCategoryRoute($category->catslug)); ?>"><?php echo $this->escape($category->catname); ?></a><?php
			$i++;
			if ($i != $n) :
				echo ', ';
			endif;
		endforeach;
		?>
		</dd>

		<?php
		for ($cr = 1; $cr <= 10; $cr++) {
			$currentRow = $this->item->{'custom'.$cr};
			if (preg_match('%^http(s)?://%', $currentRow)) {
				$currentRow = '<a href="'.$this->escape($currentRow).'" target="_blank">'.$this->escape($currentRow).'</a>';
 			}
			if ($currentRow) {
			?>
				<dt class="custom<?php echo $cr; ?>"><?php echo Text::_('COM_JEM_EVENT_CUSTOM_FIELD'.$cr); ?>:</dt>
				<dd class="custom<?php echo $cr; ?>"><?php echo $currentRow; ?></dd>
			<?php
			}
		}
		?>

		<?php if ($params->get('event_show_hits')) : ?>
		<dt class="hits"><?php echo Text::_('COM_JEM_EVENT_HITS_LABEL'); ?>:</dt>
		<dd class="hits"><?php echo Text::sprintf('COM_JEM_EVENT_HITS', $this->item->hits); ?></dd>
		<?php endif; ?>


	<!-- AUTHOR -->
		<?php if ($params->get('event_show_author') && !empty($this->item->author)) : ?>
		<dt class="createdby"><?php echo Text::_('COM_JEM_EVENT_CREATED_BY_LABEL'); ?>:</dt>
		<dd class="createdby">
			<?php $author = $this->item->created_by_alias ? $this->item->created_by_alias : $this->item->author; ?>
			<?php if (!empty($this->item->contactid2) && $params->get('event_link_author') == true) :
				$needle = 'index.php?option=com_contact&view=contact&id=' . $this->item->contactid2;
				$menu = Factory::getApplication()->getMenu();
				$item = $menu->getItems('link', $needle, true);
				$cntlink = !empty($item) ? $needle . '&Itemid=' . $item->id : $needle;
				echo Text::sprintf('COM_JEM_EVENT_CREATED_BY', HTMLHelper::_('link', JRoute::_($cntlink), $author));
			else :
				echo Text::sprintf('COM_JEM_EVENT_CREATED_BY', $author);
			endif;
			?>
		</dd>
		<?php endif; ?>

	<!-- PUBLISHING STATE -->
		<?php if (!empty($this->showeventstate) && isset($this->item->published)) : ?>
		<dt class="published"><?php echo Text::_('JSTATUS'); ?>:</dt>
		<dd class="published">
			<?php switch ($this->item->published) {
			case  1: echo Text::_('JPUBLISHED');   break;
			case  0: echo Text::_('JUNPUBLISHED'); break;
			case  2: echo Text::_('JARCHIVED');    break;
			case -2: echo Text::_('JTRASHED');     break;
			} ?>
		</dd>
		<?php endif; ?>
	</dl>

	<!-- DESCRIPTION -->
	<?php if ($params->get('event_show_description','1') && ($this->item->fulltext != '' && $this->item->fulltext != '<br />' || $this->item->introtext != '' && $this->item->introtext != '<br />')) { ?>
	<h2 class="description"><?php echo Text::_('COM_JEM_EVENT_DESCRIPTION'); ?></h2>
	<div class="description event_desc" itemprop="description">

		<?php
		if ($params->get('access-view')) {
			echo $this->item->text;
		}
		/* optional teaser intro text for guests - NOT SUPPORTED YET */
		elseif (0 /*$params->get('event_show_noauth') == true and  $user->get('guest')*/ ) {
			echo $this->item->introtext;
			// Optional link to let them register to see the whole event.
			if ($params->get('event_show_readmore') && $this->item->fulltext != null) {
				$link1 = JRoute::_('index.php?option=com_users&view=login');
				$link = new JUri($link1);
				echo '<p class="readmore">';
					echo '<a href="'.$link.'">';
					if ($params->get('event_alternative_readmore') == false) {
						echo Text::_('COM_JEM_EVENT_REGISTER_TO_READ_MORE');
					} elseif ($readmore = $params->get('alternative_readmore')) {
						echo $readmore;
					}

					if ($params->get('event_show_readmore_title', 0) != 0) {
					    echo HTMLHelper::_('string.truncate', ($this->item->title), $params->get('event_readmore_limit'));
					} elseif ($params->get('event_show_readmore_title', 0) == 0) {
					} else {
						echo HTMLHelper::_('string.truncate', ($this->item->title), $params->get('event_readmore_limit'));
					} ?>
					</a>
				</p>
			<?php
			}
		} /* access_view / show_noauth */
		?>
	</div>
	<?php } ?>

	<!--  Contact -->
	<?php if ($params->get('event_show_contact') && !empty($this->item->conid )) : ?>

	<h2 class="contact"><?php echo Text::_('COM_JEM_CONTACT') ; ?></h2>

	<dl class="location floattext">
		<dt class="con_name"><?php echo Text::_('COM_JEM_NAME'); ?>:</dt>
		<dd class="con_name">
		<?php
		$contact = $this->item->conname;
		if ($params->get('event_link_contact') == true) :
			$needle = 'index.php?option=com_contact&view=contact&id=' . $this->item->conid;
			$menu = Factory::getApplication()->getMenu();
			$item = $menu->getItems('link', $needle, true);
			$cntlink2 = !empty($item) ? $needle . '&Itemid=' . $item->id : $needle;
			echo Text::sprintf('COM_JEM_EVENT_CONTACT', HTMLHelper::_('link', JRoute::_($cntlink2), $contact));
		else :
			echo Text::sprintf('COM_JEM_EVENT_CONTACT', $contact);
		endif;
		?>
		</dd>

		<?php if ($this->item->contelephone) : ?>
		<dt class="con_telephone"><?php echo Text::_('COM_JEM_TELEPHONE'); ?>:</dt>
		<dd class="con_telephone">
			<?php echo $this->escape($this->item->contelephone); ?>
		</dd>
		<?php endif; ?>
	</dl>
	<?php endif ?>

	<?php $this->attachments = $this->item->attachments; ?>
	<?php echo $this->loadTemplate('attachments'); ?>

	<!--  	Venue  -->
	<?php if (($this->item->locid != 0) && !empty($this->item->venue) && $params->get('event_show_venue', '1')) : ?>
	<p></p>
	<hr />

	<div itemprop="location" itemscope="itemscope" itemtype="https://schema.org/Place">
    <meta itemprop="name" content="<?php echo $this->escape($this->item->venue); ?>" />
		<?php $itemid = $this->item ? $this->item->id : 0 ; ?>
		<h2 class="location">
			<?php
			echo Text::_('COM_JEM_VENUE') ;
			$itemid = $this->item ? $this->item->id : 0 ;
			echo JemOutput::editbutton($this->item, $params, $attribs, $this->permissions->canEditVenue, 'editvenue');
			echo JemOutput::copybutton($this->item, $params, $attribs, $this->permissions->canAddVenue, 'editvenue');
			?>
		</h2>
		<?php echo JemOutput::flyer($this->item, $this->limage, 'venue'); ?>

		<dl class="location">
			<dt class="venue"><?php echo Text::_('COM_JEM_LOCATION'); ?>:</dt>
			<dd class="venue">
				<?php
				if (!empty($this->item->venueslug)) :
					echo '<a href="' . JRoute::_(JemHelperRoute::getVenueRoute($this->item->venueslug)) . '">' . $this->escape($this->item->venue) . '</a>';
				else :
					echo $this->escape($this->item->venue);
				endif;
				if (!empty($this->item->url)) :
					echo '&nbsp;-&nbsp;<a target="_blank" href="' . $this->item->url . '">' . Text::_('COM_JEM_WEBSITE') . '</a>';
				endif;
				?>
			</dd>
		</dl>
		<?php if ($params->get('event_show_detailsadress', '1')) : ?>
		<dl class="location floattext" itemprop="address" itemscope
		    itemtype="https://schema.org/PostalAddress">
			<?php if ($this->item->street) : ?>
			<dt class="venue_street"><?php echo Text::_('COM_JEM_STREET'); ?>:</dt>
			<dd class="venue_street" itemprop="streetAddress">
				<?php echo $this->escape($this->item->street); ?>
			</dd>
			<?php endif; ?>

			<?php if ($this->item->postalCode) : ?>
			<dt class="venue_postalCode"><?php echo Text::_('COM_JEM_ZIP'); ?>:</dt>
			<dd class="venue_postalCode" itemprop="postalCode">
				<?php echo $this->escape($this->item->postalCode); ?>
			</dd>
			<?php endif; ?>

			<?php if ($this->item->city) : ?>
			<dt class="venue_city"><?php echo Text::_('COM_JEM_CITY'); ?>:</dt>
			<dd class="venue_city" itemprop="addressLocality">
				<?php echo $this->escape($this->item->city); ?>
			</dd>
			<?php endif; ?>

			<?php if ($this->item->state) : ?>
			<dt class="venue_state"><?php echo Text::_('COM_JEM_STATE'); ?>:</dt>
			<dd class="venue_state" itemprop="addressRegion">
				<?php echo $this->escape($this->item->state); ?>
			</dd>
			<?php endif; ?>

			<?php if ($this->item->country) : ?>
			<dt class="venue_country"><?php echo Text::_('COM_JEM_COUNTRY'); ?>:</dt>
			<dd class="venue_country">
				<?php echo $this->item->countryimg ? $this->item->countryimg : $this->item->country; ?>
				<meta itemprop="addressCountry" content="<?php echo $this->item->country; ?>" />
			</dd>
			<?php endif; ?>

			<!-- PUBLISHING STATE -->
			<?php if (!empty($this->showvenuestate) && isset($this->item->locpublished)) : ?>
			<dt class="venue_published"><?php echo Text::_('JSTATUS'); ?>:</dt>
			<dd class="venue_published">
				<?php switch ($this->item->locpublished) {
				case  1: echo Text::_('JPUBLISHED');   break;
				case  0: echo Text::_('JUNPUBLISHED'); break;
				case  2: echo Text::_('JARCHIVED');    break;
				case -2: echo Text::_('JTRASHED');     break;
				} ?>
			</dd>
			<?php endif; ?>

			<?php
			for ($cr = 1; $cr <= 10; $cr++) {
				$currentRow = $this->item->{'venue'.$cr};
				if (preg_match('%^http(s)?://%', $currentRow)) {
					$currentRow = '<a href="' . $this->escape($currentRow) . '" target="_blank">' . $this->escape($currentRow) . '</a>';
				}
				if ($currentRow) {
					?>
					<dt class="custom<?php echo $cr; ?>"><?php echo Text::_('COM_JEM_VENUE_CUSTOM_FIELD'.$cr); ?>:</dt>
					<dd class="custom<?php echo $cr; ?>"><?php echo $currentRow; ?></dd>
					<?php
				}
			}
			?>

			<?php if ($params->get('event_show_mapserv') == 1 || $params->get('event_show_mapserv') == 4) : ?>
				<?php echo JemOutput::mapicon($this->item, 'event', $params); ?>
			<?php endif; ?>
		</dl>

			<?php if ($params->get('event_show_mapserv') == 2 || $params->get('event_show_mapserv') == 5) : ?>
			<div class="jem-map">
				<?php echo JemOutput::mapicon($this->item, 'event', $params); ?>
			</div>
			<?php endif; ?>

			<?php if ($params->get('event_show_mapserv') == 3) : ?>
				<input type="hidden" id="latitude" value="<?php echo $this->item->latitude; ?>">
				<input type="hidden" id="longitude" value="<?php echo $this->item->longitude; ?>">
				<input type="hidden" id="venue" value="<?php echo $this->item->venue; ?>">
				<input type="hidden" id="street" value="<?php echo $this->item->street; ?>">
				<input type="hidden" id="city" value="<?php echo $this->item->city; ?>">
				<input type="hidden" id="state" value="<?php echo $this->item->state; ?>">
				<input type="hidden" id="postalCode" value="<?php echo $this->item->postalCode; ?>">

				<?php echo JemOutput::mapicon($this->item, 'event', $params); ?>
			<?php endif; ?>
		<?php endif; /* event_show_detailsadress */ ?>

		<?php if ($params->get('event_show_locdescription', '1') && $this->item->locdescription != ''
		       && $this->item->locdescription != '<br />') : ?>
		<h2 class="location_desc"><?php echo Text::_('COM_JEM_VENUE_DESCRIPTION'); ?></h2>
		<div class="description location_desc" itemprop="description">
			<?php echo $this->item->locdescription; ?>
		</div>
		<?php endif; ?>

		<?php $this->attachments = $this->item->vattachments; ?>
		<?php echo $this->loadTemplate('attachments'); ?>

	</div>
	<?php endif; ?>

	<!-- Registration -->
	<?php if ($this->showAttendees && $params->get('event_show_registration', '1')) : ?>
		<p></p>
		<hr />
		<h2 class="register"><?php echo Text::_('COM_JEM_REGISTRATION'); ?>:</h2>
		<?php echo $this->loadTemplate('attendees'); ?>
	<?php endif; ?>

	<?php if (!empty($this->item->pluginevent->onEventEnd)) : ?>
		<p></p>
		<hr />
		<?php echo $this->item->pluginevent->onEventEnd; ?>
	<?php endif; ?>

	<div class="copyright">
		<?php echo JemOutput::footer(); ?>
	</div>
</div>

<?php }

echo JemOutput::lightbox();
?>

