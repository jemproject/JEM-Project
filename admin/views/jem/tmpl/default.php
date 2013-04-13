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

						$link = 'index.php?option=com_jem&amp;view=events';
						JEMViewJEM::quickiconButton( $link, 'icon-48-events.png', JText::_( 'COM_JEM_EVENTS' ) );

						$link = 'index.php?option=com_jem&amp;view=event';
						JEMViewJEM::quickiconButton( $link, 'icon-48-eventedit.png', JText::_( 'COM_JEM_ADD_EVENT' ) );

						$link = 'index.php?option=com_jem&amp;view=venues';
						JEMViewJEM::quickiconButton( $link, 'icon-48-venues.png', JText::_( 'COM_JEM_VENUES' ) );

						$link = 'index.php?option=com_jem&amp;view=venue';
						JEMViewJEM::quickiconButton( $link, 'icon-48-venuesedit.png', JText::_( 'COM_JEM_ADD_VENUE' ) );

						$link = 'index.php?option=com_jem&amp;view=categories';
						JEMViewJEM::quickiconButton( $link, 'icon-48-categories.png', JText::_( 'COM_JEM_CATEGORIES' ) );

						$link = 'index.php?option=com_jem&amp;view=category';
						JEMViewJEM::quickiconButton( $link, 'icon-48-categoriesedit.png', JText::_( 'COM_JEM_ADD_CATEGORY' ) );

						$link = 'index.php?option=com_jem&amp;view=groups';
						JEMViewJEM::quickiconButton( $link, 'icon-48-groups.png', JText::_( 'COM_JEM_GROUPS' ) );

						$link = 'index.php?option=com_jem&amp;view=group';
						JEMViewJEM::quickiconButton( $link, 'icon-48-groupedit.png', JText::_( 'COM_JEM_ADD_GROUP' ) );

						$link = 'index.php?option=com_jem&amp;view=archive';
						JEMViewJEM::quickiconButton( $link, 'icon-48-archive.png', JText::_( 'COM_JEM_ARCHIVESCREEN' ) );

						/*$link = 'index.php?option=com_jem&amp;controller=plugins&amp;task=plugins';
						JEMViewJEM::quickiconButton( $link, 'icon-48-plugins.png', JText::_( 'COM_JEM_MANAGE_PLUGINS' ) );
						*/
						
						
						//only admins should be able to see this items
						 if (JFactory::getUser()->authorise('core.manage')) {
							$link = 'index.php?option=com_jem&amp;controller=settings&amp;task=edit';
							JEMViewJEM::quickiconButton( $link, 'icon-48-settings.png', JText::_( 'COM_JEM_SETTINGS' ) );

						/*	$link = 'index.php?option=com_jem&amp;view=editcss';
							JEMViewJEM::quickiconButton( $link, 'icon-48-cssedit.png', JText::_( 'COM_JEM_EDIT_CSS' ) );
                         */
							$link = 'index.php?option=com_jem&amp;view=cleanup';
							JEMViewJEM::quickiconButton( $link, 'icon-48-housekeeping.png', JText::_( 'COM_JEM_CLEANUP' ) );
							
							
						}

						$link = 'index.php?option=com_jem&amp;view=help';
						JEMViewJEM::quickiconButton( $link, 'icon-48-help.png', JText::_( 'COM_JEM_HELP' ) );

						/*
						$link = 'index.php?option=com_jem&amp;view=updatecheck';
						JEMViewJEM::quickiconButton( $link, 'icon-48-update.png', JText::_( 'COM_JEM_UPDATE_CHECK' ), 1 );
						
						
						$link = 'index.php?option=com_jem&amp;controller=sampledata&amp;task=load';
						JEMViewJEM::quickiconButton( $link, 'icon-48-sampledata.png', JText::_( 'COM_JEM_LOAD_SAMPLE_DATA' ) );
						*/
						
						if (JFactory::getUser()->authorise('core.manage')) {
						$link = 'index.php?option=com_jem&amp;view=import';
						JEMViewJEM::quickiconButton( $link, 'icon-48-sampledata.png', JText::_( 'COM_JEM_IMPORT_DATA' ) );
						
						$link = 'index.php?option=com_jem&amp;view=export';
						JEMViewJEM::quickiconButton( $link, 'icon-48-sampledata.png', JText::_( 'COM_JEM_EXPORT_DATA' ) );
						}
						
						?>
						</div>
					
				</tr>
			</table>
			</td>
			<td valign="top" width="320px" style="padding: 7px 0 0 5px">
			<?php
			$title = JText::_( 'COM_JEM_EVENT_STATS' );
			echo JHtml::_('sliders.start','stat-pane',$options);
			echo JHtml::_('sliders.panel',$title,'events');

				?>
				<table class="adminlist">
					<tr>
						<td>
							<?php echo JText::_( 'COM_JEM_EVENTS_PUBLISHED' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->events[0]; ?></b>
						</td>
					</tr>
					<tr>
						<td>
							<?php echo JText::_( 'COM_JEM_EVENTS_UNPUBLISHED' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->events[1]; ?></b>
						</td>
					</tr>
					<tr>
						<td>
							<?php echo JText::_( 'COM_JEM_EVENTS_ARCHIVED' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->events[2]; ?></b>
						</td>
					</tr>
					<tr>
						<td>
							<?php echo JText::_( 'COM_JEM_EVENTS_TOTAL' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->events[3]; ?></b>
						</td>
					</tr>
				</table>
				<?php

				$title2 = JText::_( 'COM_JEM_VENUE_STATS' );
				
				echo JHtml::_('sliders.panel', $title2, 'venues' );

				?>
				<table class="adminlist">
					<tr>
						<td>
							<?php echo JText::_( 'COM_JEM_VENUES_PUBLISHED' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->venue[0]; ?></b>
						</td>
					</tr>
					<tr>
						<td>
							<?php echo JText::_( 'COM_JEM_VENUES_UNPUBLISHED' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->venue[1]; ?></b>
						</td>
					</tr>
					<tr>
						<td>
							<?php echo JText::_( 'COM_JEM_VENUES_TOTAL' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->venue[2]; ?></b>
						</td>
					</tr>
				</table>
				<?php

				$title3 = JText::_( 'COM_JEM_CATEGORY_STATS' );
				echo JHtml::_('sliders.panel',$title3, 'categories' );
				?>
				<table class="adminlist">
					<tr>
						<td>
							<?php echo JText::_( 'COM_JEM_CATEGORIES_PUBLISHED' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->category[0]; ?></b>
						</td>
					</tr>
					<tr>
						<td>
							<?php echo JText::_( 'COM_JEM_CATEGORIES_UNPUBLISHED' ).': '; ?>
						</td>
						<td>
							<b><?php echo $this->category[1]; ?></b>
						</td>
					</tr>
					<tr>
						<td>
							<?php echo JText::_( 'COM_JEM_CATEGORIES_TOTAL' ).': '; ?>
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