<?php
/**
 * @version 2.1.6
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

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
<form action="<?php echo JRoute::_('index.php?option=com_jem');?>" id="application-form" method="post" name="adminForm" class="form-validate">
	<?php if (isset($this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo $this->sidebar; ?>
	</div>
	<div id="j-main-container" class="span10">
	<?php endif; ?>

		<table style="width:100%">
			<tr>
				<td valign="top">
					<table>
						<tr>
							<td>
								<div class="cpanel">
								<?php
									$link = 'index.php?option=com_jem&amp;view=events';
									$this->quickiconButton($link, 'icon-48-events.png', JText::_('COM_JEM_EVENTS'));

									$link = 'index.php?option=com_jem&amp;task=event.add';
									$this->quickiconButton($link, 'icon-48-eventedit.png', JText::_('COM_JEM_ADD_EVENT'));

									$link = 'index.php?option=com_jem&amp;view=venues';
									$this->quickiconButton($link, 'icon-48-venues.png', JText::_('COM_JEM_VENUES'));

									$link = 'index.php?option=com_jem&task=venue.add';
									$this->quickiconButton($link, 'icon-48-venuesedit.png', JText::_('COM_JEM_ADD_VENUE'));

									$link = 'index.php?option=com_jem&amp;view=categories';
									$this->quickiconButton($link, 'icon-48-categories.png', JText::_('COM_JEM_CATEGORIES'));

									$link = 'index.php?option=com_jem&amp;task=category.add';
									$this->quickiconButton($link, 'icon-48-categoriesedit.png', JText::_('COM_JEM_ADD_CATEGORY'));

									$link = 'index.php?option=com_jem&amp;view=groups';
									$this->quickiconButton($link, 'icon-48-groups.png', JText::_('COM_JEM_GROUPS'));

									$link = 'index.php?option=com_jem&amp;task=group.add';
									$this->quickiconButton($link, 'icon-48-groupedit.png', JText::_('COM_JEM_GROUP_ADD'));

									$link = 'index.php?option=com_jem&amp;task=plugins.plugins';
									$this->quickiconButton($link, 'icon-48-plugins.png', JText::_('COM_JEM_MANAGE_PLUGINS'));

									//only admins should be able to see these items
									if (JemFactory::getUser()->authorise('core.manage', 'com_jem')) {
										$link = 'index.php?option=com_jem&amp;view=settings';
										$this->quickiconButton($link, 'icon-48-settings.png', JText::_('COM_JEM_SETTINGS_TITLE'));

										$link = 'index.php?option=com_jem&amp;view=housekeeping';
										$this->quickiconButton($link, 'icon-48-housekeeping.png', JText::_('COM_JEM_HOUSEKEEPING'));

										$link = 'index.php?option=com_jem&amp;task=sampledata.load';
										$this->quickiconButton($link, 'icon-48-sampledata.png', JText::_('COM_JEM_MAIN_LOAD_SAMPLE_DATA'));

										$link = 'index.php?option=com_jem&amp;view=updatecheck';
										$this->quickiconButton($link, 'icon-48-update.png', JText::_('COM_JEM_UPDATECHECK_TITLE'));

										$link = 'index.php?option=com_jem&amp;view=import';
										$this->quickiconButton($link, 'icon-48-tableimport.png', JText::_('COM_JEM_IMPORT_DATA'));

										$link = 'index.php?option=com_jem&amp;view=export';
										$this->quickiconButton($link, 'icon-48-tableexport.png', JText::_('COM_JEM_EXPORT_DATA'));

										$link = 'index.php?option=com_jem&amp;view=cssmanager';
										$this->quickiconButton( $link, 'icon-48-cssmanager.png', JText::_( 'COM_JEM_CSSMANAGER_TITLE' ) );
									}

									$link = 'index.php?option=com_jem&amp;view=help';
									$this->quickiconButton($link, 'icon-48-help.png', JText::_('COM_JEM_HELP'));
								?>
								</div>
							</td>
						</tr>
					</table>
				</td>
				<td valign="top" width="320px" style="padding: 7px 0 0 5px">
					<?php echo JHtml::_('sliders.start','stat-pane',$options); ?>
					<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_MAIN_EVENT_STATS'),'events'); ?>
					<table class="adminlist">
						<tr>
							<td><?php echo JText::_('COM_JEM_MAIN_EVENTS_PUBLISHED').': '; ?></td>
							<td><b><?php echo $this->events->published; ?> </b></td>
						</tr>
						<tr>
							<td><?php echo JText::_('COM_JEM_MAIN_EVENTS_UNPUBLISHED').': '; ?></td>
							<td><b><?php echo $this->events->unpublished; ?> </b></td>
						</tr>
						<tr>
							<td><?php echo JText::_('COM_JEM_MAIN_EVENTS_ARCHIVED').': '; ?> </td>
							<td><b><?php echo $this->events->archived; ?> </b></td>
						</tr>
						<tr>
							<td><?php echo JText::_('COM_JEM_MAIN_EVENTS_TRASHED').': '; ?></td>
							<td><b><?php echo $this->events->trashed; ?> </b></td>
						</tr>
						<tr>
							<td><?php echo JText::_('COM_JEM_MAIN_EVENTS_TOTAL').': '; ?></td>
							<td><b><?php echo $this->events->total; ?> </b></td>
						</tr>
					</table>
					<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_MAIN_VENUE_STATS'), 'venues'); ?>
					<table class="adminlist">
						<tr>
							<td><?php echo JText::_('COM_JEM_MAIN_VENUES_PUBLISHED').': '; ?></td>
							<td><b><?php echo $this->venue->published; ?> </b></td>
						</tr>
						<tr>
							<td><?php echo JText::_('COM_JEM_MAIN_VENUES_UNPUBLISHED').': '; ?></td>
							<td><b><?php echo $this->venue->unpublished; ?> </b></td>
						</tr>
						<tr>
							<td><?php echo JText::_('COM_JEM_MAIN_VENUES_TOTAL').': '; ?></td>
							<td><b><?php echo $this->venue->total; ?> </b></td>
						</tr>
					</table>
					<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_MAIN_CATEGORY_STATS'), 'categories'); ?>
					<table class="adminlist">
						<tr>
							<td><?php echo JText::_('COM_JEM_MAIN_CATEGORIES_PUBLISHED').': '; ?></td>
							<td><b><?php echo $this->category->published; ?> </b></td>
						</tr>
						<tr>
							<td><?php echo JText::_('COM_JEM_MAIN_CATEGORIES_UNPUBLISHED').': '; ?></td>
							<td><b><?php echo $this->category->unpublished; ?> </b></td>
						</tr>
						<tr>
							<td><?php echo JText::_('COM_JEM_MAIN_CATEGORIES_ARCHIVED').': '; ?></td>
							<td><b><?php echo $this->category->archived; ?> </b></td>
						</tr>
						<tr>
							<td><?php echo JText::_('COM_JEM_MAIN_CATEGORIES_TRASHED').': '; ?></td>
							<td><b><?php echo $this->category->trashed; ?> </b></td>
						</tr>
						<tr>
							<td><?php echo JText::_('COM_JEM_MAIN_CATEGORIES_TOTAL').': '; ?></td>
							<td><b><?php echo $this->category->total; ?> </b></td>
						</tr>
					</table>
					<?php echo JHtml::_('tabs.end'); ?>
				</td>
			</tr>
		</table>
	<?php if (isset($this->sidebar)) : ?>
	</div>
	<?php endif; ?>
</form>