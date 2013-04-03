<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
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
	<table cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td valign="top">
			<table class="adminlist">
				<tr>
					
						<div id="cpanel">
						<?php

						$link = 'index.php?option=com_eventlist&amp;view=events';
						EventListViewEventList::quickiconButton( $link, 'icon-48-events.png', JText::_( 'COM_EVENTLIST_EVENTS' ) );

						$link = 'index.php?option=com_eventlist&amp;view=event';
						EventListViewEventList::quickiconButton( $link, 'icon-48-eventedit.png', JText::_( 'COM_EVENTLIST_ADD_EVENT' ) );

						$link = 'index.php?option=com_eventlist&amp;view=venues';
						EventListViewEventList::quickiconButton( $link, 'icon-48-venues.png', JText::_( 'COM_EVENTLIST_VENUES' ) );

						$link = 'index.php?option=com_eventlist&amp;view=venue';
						EventListViewEventList::quickiconButton( $link, 'icon-48-venuesedit.png', JText::_( 'COM_EVENTLIST_ADD_VENUE' ) );

						$link = 'index.php?option=com_eventlist&amp;view=categories';
						EventListViewEventList::quickiconButton( $link, 'icon-48-categories.png', JText::_( 'COM_EVENTLIST_CATEGORIES' ) );

						$link = 'index.php?option=com_eventlist&amp;view=category';
						EventListViewEventList::quickiconButton( $link, 'icon-48-categoriesedit.png', JText::_( 'COM_EVENTLIST_ADD_CATEGORY' ) );

						$link = 'index.php?option=com_eventlist&amp;view=groups';
						EventListViewEventList::quickiconButton( $link, 'icon-48-groups.png', JText::_( 'COM_EVENTLIST_GROUPS' ) );

						$link = 'index.php?option=com_eventlist&amp;view=group';
						EventListViewEventList::quickiconButton( $link, 'icon-48-groupedit.png', JText::_( 'COM_EVENTLIST_ADD_GROUP' ) );

						$link = 'index.php?option=com_eventlist&amp;view=archive';
						EventListViewEventList::quickiconButton( $link, 'icon-48-archive.png', JText::_( 'COM_EVENTLIST_ARCHIVESCREEN' ) );

						/*$link = 'index.php?option=com_eventlist&amp;controller=plugins&amp;task=plugins';
						EventListViewEventList::quickiconButton( $link, 'icon-48-plugins.png', JText::_( 'COM_EVENTLIST_MANAGE_PLUGINS' ) );
						*/
						
						
						//only admins should be able to see this items
						 if (JFactory::getUser()->authorise('core.manage')) {
							$link = 'index.php?option=com_eventlist&amp;controller=settings&amp;task=edit';
							EventListViewEventList::quickiconButton( $link, 'icon-48-settings.png', JText::_( 'COM_EVENTLIST_SETTINGS' ) );

						/*	$link = 'index.php?option=com_eventlist&amp;view=editcss';
							EventListViewEventList::quickiconButton( $link, 'icon-48-cssedit.png', JText::_( 'COM_EVENTLIST_EDIT_CSS' ) );
                         */
							$link = 'index.php?option=com_eventlist&amp;view=cleanup';
							EventListViewEventList::quickiconButton( $link, 'icon-48-housekeeping.png', JText::_( 'COM_EVENTLIST_CLEANUP' ) );
							
							
						}

						$link = 'index.php?option=com_eventlist&amp;view=help';
						EventListViewEventList::quickiconButton( $link, 'icon-48-help.png', JText::_( 'COM_EVENTLIST_HELP' ) );

						/*
						$link = 'index.php?option=com_eventlist&amp;view=updatecheck';
						EventListViewEventList::quickiconButton( $link, 'icon-48-update.png', JText::_( 'COM_EVENTLIST_UPDATE_CHECK' ), 1 );
						
						
						$link = 'index.php?option=com_eventlist&amp;controller=sampledata&amp;task=load';
						EventListViewEventList::quickiconButton( $link, 'icon-48-sampledata.png', JText::_( 'COM_EVENTLIST_LOAD_SAMPLE_DATA' ) );
						*/
						
						if (JFactory::getUser()->authorise('core.manage')) {
						$link = 'index.php?option=com_eventlist&amp;view=import';
						EventListViewEventList::quickiconButton( $link, 'icon-48-sampledata.png', JText::_( 'COM_EVENTLIST_IMPORT_DATA' ) );
						
						$link = 'index.php?option=com_eventlist&amp;view=export';
						EventListViewEventList::quickiconButton( $link, 'icon-48-sampledata.png', JText::_( 'COM_EVENTLIST_EXPORT_DATA' ) );
						}
						
						?>
						</div>
					
				</tr>
			</table>
			</td>
			<td valign="top" width="320px" style="padding: 7px 0 0 5px">
			<?php
			$title = JText::_( 'COM_EVENTLIST_EVENT_STATS' );
			echo JHtml::_('sliders.start','stat-pane',$options);
			echo JHtml::_('sliders.panel',$title,'events');

				?>
				<table class="adminlist">
					<tr>
						<td>
							<?php echo JText::_( 'COM_EVENTLIST_EVENTS_PUBLISHED' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->events[0]; ?></b>
						</td>
					</tr>
					<tr>
						<td>
							<?php echo JText::_( 'COM_EVENTLIST_EVENTS_UNPUBLISHED' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->events[1]; ?></b>
						</td>
					</tr>
					<tr>
						<td>
							<?php echo JText::_( 'COM_EVENTLIST_EVENTS_ARCHIVED' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->events[2]; ?></b>
						</td>
					</tr>
					<tr>
						<td>
							<?php echo JText::_( 'COM_EVENTLIST_EVENTS_TOTAL' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->events[3]; ?></b>
						</td>
					</tr>
				</table>
				<?php

				$title2 = JText::_( 'COM_EVENTLIST_VENUE_STATS' );
				
				echo JHtml::_('sliders.panel', $title2, 'venues' );

				?>
				<table class="adminlist">
					<tr>
						<td>
							<?php echo JText::_( 'COM_EVENTLIST_VENUES_PUBLISHED' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->venue[0]; ?></b>
						</td>
					</tr>
					<tr>
						<td>
							<?php echo JText::_( 'COM_EVENTLIST_VENUES_UNPUBLISHED' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->venue[1]; ?></b>
						</td>
					</tr>
					<tr>
						<td>
							<?php echo JText::_( 'COM_EVENTLIST_VENUES_TOTAL' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->venue[2]; ?></b>
						</td>
					</tr>
				</table>
				<?php

				$title3 = JText::_( 'COM_EVENTLIST_CATEGORY_STATS' );
				echo JHtml::_('sliders.panel',$title3, 'categories' );
				?>
				<table class="adminlist">
					<tr>
						<td>
							<?php echo JText::_( 'COM_EVENTLIST_CATEGORIES_PUBLISHED' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->category[0]; ?></b>
						</td>
					</tr>
					<tr>
						<td>
							<?php echo JText::_( 'COM_EVENTLIST_CATEGORIES_UNPUBLISHED' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->category[1]; ?></b>
						</td>
					</tr>
					<tr>
						<td>
							<?php echo JText::_( 'COM_EVENTLIST_CATEGORIES_TOTAL' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->category[2]; ?></b>
						</td>
					</tr>
				</table>
				<?php
				echo JHtml::_('tabs.end');
				?>
			</td>
		</tr>
		</table>

	<p class="copyright">
		<?php echo ELAdmin::footer( ); ?>
	</p>