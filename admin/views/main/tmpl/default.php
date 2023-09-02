<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$options = array(
	'onActive' => 'function(title, description){
		description.setStyle("display", "block");
		title.addClass("open").removeClass("closed");
	}',
	'onBackground' => 'function(title, description){
		description.setStyle("display", "none");
		title.addClass("closed").removeClass("open");
	}',
	'startOffset' => 0,  // 0 starts on the first tab, 1 starts the second, etc...
	'useCookie' => true, // this must not be a string. Don't use quotes.
);

?>
<style>
	.jem-wei-menus .card{
		min-height: 126px;
	}
	.jem-wei-menus .card-body div:first-child{
		float:none !important;
	}
	.jem-wei-menus .icon{
		text-align:center;
	}
	.jem-wei-menus .icon a{
		display: flex;
    	flex-direction: column;
    	align-items: center;
	}
</style>
<form action="<?php echo Route::_('index.php?option=com_jem');?>" id="application-form" method="post" name="adminForm" class="form-validate">
	<div id="j-main-container" class="j-main-container">
		<table style="width:100%">
			<tr>
				<td valign="top">
					<table>
						<tr>
							<td>
								<div class="cpanel jem-wei-menus">
									<?php
										$link = 'index.php?option=com_jem&amp;view=events';
										$this->quickiconButton($link, 'icon-48-events.png', Text::_('COM_JEM_EVENTS'));

										$link = 'index.php?option=com_jem&amp;task=event.add';
										$this->quickiconButton($link, 'icon-48-eventedit.png', Text::_('COM_JEM_ADD_EVENT'));

										$link = 'index.php?option=com_jem&amp;view=venues';
										$this->quickiconButton($link, 'icon-48-venues.png', Text::_('COM_JEM_VENUES'));

										$link = 'index.php?option=com_jem&task=venue.add';
										$this->quickiconButton($link, 'icon-48-venuesedit.png', Text::_('COM_JEM_ADD_VENUE'));

										$link = 'index.php?option=com_jem&amp;view=categories';
										$this->quickiconButton($link, 'icon-48-categories.png', Text::_('COM_JEM_CATEGORIES'));

										$link = 'index.php?option=com_jem&amp;task=category.add';
										$this->quickiconButton($link, 'icon-48-categoriesedit.png', Text::_('COM_JEM_ADD_CATEGORY'));

										$link = 'index.php?option=com_jem&amp;view=groups';
										$this->quickiconButton($link, 'icon-48-groups.png', Text::_('COM_JEM_GROUPS'));

										$link = 'index.php?option=com_jem&amp;task=group.add';
										$this->quickiconButton($link, 'icon-48-groupedit.png', Text::_('COM_JEM_GROUP_ADD'));

										$link = 'index.php?option=com_jem&amp;task=plugins.plugins';
										$this->quickiconButton($link, 'icon-48-plugins.png', Text::_('COM_JEM_MANAGE_PLUGINS'));

										//only admins should be able to see these items
										if (JemFactory::getUser()->authorise('core.manage', 'com_jem')) {
											$link = 'index.php?option=com_jem&amp;view=settings';
											$this->quickiconButton($link, 'icon-48-settings.png', Text::_('COM_JEM_SETTINGS_TITLE'));

											$link = 'index.php?option=com_jem&amp;view=housekeeping';
											$this->quickiconButton($link, 'icon-48-housekeeping.png', Text::_('COM_JEM_HOUSEKEEPING'));

											$link = 'index.php?option=com_jem&amp;task=sampledata.load';
											$this->quickiconButton($link, 'icon-48-sampledata.png', Text::_('COM_JEM_MAIN_LOAD_SAMPLE_DATA'));

											$link = 'index.php?option=com_jem&amp;view=updatecheck';
											$this->quickiconButton($link, 'icon-48-update.png', Text::_('COM_JEM_UPDATECHECK_TITLE'));

											$link = 'index.php?option=com_jem&amp;view=import';
											$this->quickiconButton($link, 'icon-48-tableimport.png', Text::_('COM_JEM_IMPORT_DATA'));

											$link = 'index.php?option=com_jem&amp;view=export';
											$this->quickiconButton($link, 'icon-48-tableexport.png', Text::_('COM_JEM_EXPORT_DATA'));

											$link = 'index.php?option=com_jem&amp;view=cssmanager';
											$this->quickiconButton( $link, 'icon-48-cssmanager.png', Text::_( 'COM_JEM_CSSMANAGER_TITLE' ) );
										}

										$link = 'index.php?option=com_jem&amp;view=help';
										$this->quickiconButton($link, 'icon-48-help.png', Text::_('COM_JEM_HELP'));
									?>
								</div>
							</td>
						</tr>
					</table>
				</td>
				<td valign="top" width="320px" style="padding: 7px 0 0 18px">
					
					<div class="accordion" id="accordion_jem">
						<?php //echo HTMLHelper::_('sliders.start','stat-pane',$options); ?>
						<?php //echo HTMLHelper::_('sliders.panel', Text::_('COM_JEM_MAIN_EVENT_STATS'),'events'); ?>
						<div class="accordion-item">
							<h2 class="accordion-header" id="clsp_events_header">
								<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#clsp_events" aria-expanded="true" aria-controls="clsp_events">
									<?php  echo Text::_('COM_JEM_MAIN_EVENT_STATS'); ?>
								</button>
							</h2>
							<div id="clsp_events" class="accordion-collapse collapse show" aria-labelledby="clsp_events_header" data-bs-parent="#accordion_jem">
								<div class="accordion-body">
									<table class="adminlist">
										<tr>
											<td><?php echo Text::_('COM_JEM_MAIN_EVENTS_PUBLISHED').': '; ?></td>
											<td><b><?php echo $this->events->published; ?> </b></td>
										</tr>
										<tr>
											<td><?php echo Text::_('COM_JEM_MAIN_EVENTS_UNPUBLISHED').': '; ?></td>
											<td><b><?php echo $this->events->unpublished; ?> </b></td>
										</tr>
										<tr>
											<td><?php echo Text::_('COM_JEM_MAIN_EVENTS_ARCHIVED').': '; ?> </td>
											<td><b><?php echo $this->events->archived; ?> </b></td>
										</tr>
										<tr>
											<td><?php echo Text::_('COM_JEM_MAIN_EVENTS_TRASHED').': '; ?></td>
											<td><b><?php echo $this->events->trashed; ?> </b></td>
										</tr>
										<tr>
											<td><?php echo Text::_('COM_JEM_MAIN_EVENTS_TOTAL').': '; ?></td>
											<td><b><?php echo $this->events->total; ?> </b></td>
										</tr>
									</table>
								</div>
							</div>
						</div>
						<div class="accordion-item">
							<h2 class="accordion-header" id="clsp_venues_header">
								<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#clsp_venues" aria-expanded="true" aria-controls="clsp_venues">
									<?php  echo Text::_('COM_JEM_MAIN_VENUE_STATS'); ?>
								</button>
							</h2>
							<div id="clsp_venues" class="accordion-collapse collapse" aria-labelledby="clsp_venues_header" data-bs-parent="#accordion_jem">
								<div class="accordion-body">
									<table class="adminlist">
										<tr>
											<td><?php echo Text::_('COM_JEM_MAIN_VENUES_PUBLISHED').': '; ?></td>
											<td><b><?php echo $this->venue->published; ?> </b></td>
										</tr>
										<tr>
											<td><?php echo Text::_('COM_JEM_MAIN_VENUES_UNPUBLISHED').': '; ?></td>
											<td><b><?php echo $this->venue->unpublished; ?> </b></td>
										</tr>
										<tr>
											<td><?php echo Text::_('COM_JEM_MAIN_VENUES_TOTAL').': '; ?></td>
											<td><b><?php echo $this->venue->total; ?> </b></td>
										</tr>
									</table>
								</div>
							</div>
						</div>
						<div class="accordion-item">
							<h2 class="accordion-header" id="clsp_categories_header">
								<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#clsp_categories" aria-expanded="true" aria-controls="clsp_categories">
									<?php  echo Text::_('COM_JEM_MAIN_CATEGORY_STATS'); ?>
								</button>
							</h2>
							<div id="clsp_categories" class="accordion-collapse collapse" aria-labelledby="clsp_categories_header" data-bs-parent="#accordion_jem">
								<div class="accordion-body">
									<table class="adminlist" >
										<tr>
											<td><?php echo Text::_('COM_JEM_MAIN_CATEGORIES_PUBLISHED').': '; ?></td>
											<td><b><?php echo $this->category->published; ?> </b></td>
										</tr>
										<tr>
											<td><?php echo Text::_('COM_JEM_MAIN_CATEGORIES_UNPUBLISHED').': '; ?></td>
											<td><b><?php echo $this->category->unpublished; ?> </b></td>
										</tr>
										<tr>
											<td><?php echo Text::_('COM_JEM_MAIN_CATEGORIES_ARCHIVED').': '; ?></td>
											<td><b><?php echo $this->category->archived; ?> </b></td>
										</tr>
										<tr>
											<td><?php echo Text::_('COM_JEM_MAIN_CATEGORIES_TRASHED').': '; ?></td>
											<td><b><?php echo $this->category->trashed; ?> </b></td>
										</tr>
										<tr>
											<td><?php echo Text::_('COM_JEM_MAIN_CATEGORIES_TOTAL').': '; ?></td>
											<td><b><?php echo $this->category->total; ?> </b></td>
										</tr>
									</table>
								</div>
							</div>
						</div>
					</div>
				
					<?php //echo HTMLHelper::_('sliders.end'); ?>
					<h3 class="title mt-4"><?php echo Text::_('COM_JEM_MAIN_DONATE'); ?></h3>
					<div class="content">
						<?php echo Text::_('COM_JEM_MAIN_DONATE_TEXT'); ?> </br></br>
						<div class="center">
							<a href="https://www.joomlaeventmanager.net/project/donate" target="_blank">
								<?php echo HTMLHelper::_('image', 'com_jem/PayPal_DonateButton.png', Text::_('COM_JEM_MAIN_DONATE'), NULL, true); ?>
							</a>
						</div>
					</div>
				</td>
			</tr>
		</table>
	</div>
</form>
