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
?>

<form action="index.php" method="post" name="adminForm" id="adminForm">

	<table class="adminform">
		<tr>
			<td width="100%">
				<?php
				echo JText::_( 'COM_EVENTLIST_SEARCH' );
				echo $this->lists['filter'];
				?>
				<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
				<button onclick="document.adminForm.submit();"><?php echo JText::_( 'COM_EVENTLIST_GO' ); ?></button>
				<button onclick="$('search').value='';document.adminForm.submit();"><?php echo JText::_( 'COM_EVENTLIST_RESET' ); ?></button>
			</td>
			<td nowrap="nowrap">
				<?php echo $this->lists['state'];	?>
			</td>
		</tr>
	</table>

	<table class="adminlist" cellspacing="1">
		<thead>
			<tr>
				<th width="5"><?php echo JText::_( 'COM_EVENTLIST_NUM' ); ?></th>
				<th width="5"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_EVENTLIST_DATE', 'a.dates', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<th><?php echo JHTML::_('grid.sort', 'COM_EVENTLIST_EVENT_TIME', 'a.times', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_EVENTLIST_EVENT_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<th><?php echo JHTML::_('grid.sort', 'COM_EVENTLIST_VENUE', 'loc.venue', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<th><?php echo JHTML::_('grid.sort', 'COM_EVENTLIST_CITY', 'loc.city', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<th><?php echo JText::_( 'COM_EVENTLIST_CATEGORIES' ); ?></th>
			    <th width="1%" nowrap="nowrap"><?php echo JText::_( 'COM_EVENTLIST_PUBLISHED' ); ?></th>
				<th class="title"><?php echo JText::_( 'COM_EVENTLIST_CREATION' ); ?></th>
				<th width="1%" nowrap="nowrap"><?php echo JText::_( 'COM_EVENTLIST_REGISTERED_USERS' ); ?></th>
				<th width="1%" nowrap="nowrap"><?php echo JHTML::_('grid.sort', 'COM_EVENTLIST_ID', 'a.id', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
			</tr>
		</thead>

		<tfoot>
			<tr>
				<td colspan="12">
					<?php echo $this->pageNav->getListFooter(); ?>
				</td>
			</tr>
		</tfoot>

		<tbody>
			<?php
			$k = 0;
			for($i=0, $n=count( $this->rows ); $i < $n; $i++) {
				$row = &$this->rows[$i];

				//Prepare date
				if (ELHelper::isValidDate($row->dates)) {
					$date = strftime( $this->elsettings->formatdate, strtotime( $row->dates ));
				} 
				else {
					$date		= JText::_('COM_EVENTLIST_OPEN_DATE');
				}

				if (!ELHelper::isValidDate($row->enddates)) {
					$displaydate = $date;
				} else {
					$enddate 	= strftime( $this->elsettings->formatdate, strtotime( $row->enddates ));
					$displaydate = $date.' - <br />'.$enddate;
				}

				//Prepare time
				if (!$row->times) {
					$displaytime = '-';
				} else {
					$time = strftime( $this->elsettings->formattime, strtotime( $row->times ));
					$displaytime = $time.' '.$this->elsettings->timename;
				}

				$link 			= 'index.php?option=com_eventlist&amp;controller=events&amp;task=edit&amp;cid[]='.$row->id;
				$venuelink 		= 'index.php?option=com_eventlist&amp;controller=venues&amp;task=edit&amp;cid[]='.$row->locid;

				//$checked 	= JHTML::_('grid.checkedout', $row, $i );
				$published 	= JHTML::_('grid.published', $row, $i );
   			?>
			<tr class="<?php echo "row$k"; ?>">
				<td><?php echo $this->pageNav->getRowOffset( $i ); ?></td>
				<td><?php echo JHtml::_('grid.id', $i, $row->id); ?></td>
				<td>
					<?php
					if ( $row->checked_out && ( $row->checked_out != $this->user->get('id') ) ) {
						echo $displaydate;
					} else {
						?>
						<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_EDIT_EVENT' );?>::<?php echo $row->title; ?>">
						<a href="<?php echo $link; ?>">
						<?php echo $displaydate; ?>
						</a></span>
						<?php
					}
					?>
				</td>
				<td><?php echo $displaytime; ?></td>
				<td>
					<?php
					if ( $row->checked_out && ( $row->checked_out != $this->user->get('id') ) ) {
						echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8');
					} else {
						?>
						<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_EDIT_EVENT' );?>::<?php echo $row->title; ?>">
						<a href="<?php echo $link; ?>">
							<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
						</a></span>
						<?php
					}
					?>

					<br />

					<?php
					if (JString::strlen($row->alias) > 25) {
						echo JString::substr( htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8'), 0 , 25).'...';
					} else {
						echo htmlspecialchars($row->alias, ENT_QUOTES, 'UTF-8');
					}
					?>
				</td>
				<td>
					<?php
					if ($row->venue) {
						if ( $row->vchecked_out && ( $row->vchecked_out != $this->user->get('id') ) ) {
							echo htmlspecialchars($row->venue, ENT_QUOTES, 'UTF-8');
						} else {
					?>
						<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_EDIT_VENUE' );?>::<?php echo $row->venue; ?>">
						<a href="<?php echo $venuelink; ?>">
							<?php echo htmlspecialchars($row->venue, ENT_QUOTES, 'UTF-8'); ?>
						</a></span>
					<?php
						}
					} else {
						echo '-';
					}
					?>
				</td>
				<td><?php echo $row->city ? htmlspecialchars($row->city, ENT_QUOTES, 'UTF-8') : '-'; ?></td>
				<td>
				<?php
				$nr = count($row->categories);
				$ix = 0;
				foreach ($row->categories as $key => $category) :				
					$catlink	= 'index.php?option=com_eventlist&amp;controller=categories&amp;task=edit&amp;cid[]='. $category->id;
					$title = htmlspecialchars($category->catname, ENT_QUOTES, 'UTF-8');
					if (JString::strlen($title) > 20) {
						$title = JString::substr( $title , 0 , 20).'...';
					}
					
					$path = '';
					$pnr = count($category->parentcats);
					$pix = 0;
					foreach ($category->parentcats as $key => $parentcats) :
					
						$path .= $parentcats->catname;
						
						$pix++;
						if ($pix != $pnr) :
							$path .= ' » ';
						endif;	
					endforeach;
					
					if ( $category->cchecked_out && ( $category->cchecked_out != $this->user->get('id') ) ) {
							echo $title;
					} else { 
					?>
						<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_EVENTLIST_EDIT_CATEGORY' );?>::<?php echo $path; ?>">
						<a href="<?php echo $catlink; ?>">
							<?php echo $title; ?>
						</a>
						</span>
					<?php
					}
					$ix++;
					if ($ix != $nr) :
						echo ', ';
					endif;
				endforeach;
				?>
				</td>
				<td align="center"><?php echo $published; ?></td>
				<td>
					<?php echo JText::_( 'COM_EVENTLIST_AUTHOR' ).': '; ?><a href="<?php echo 'index.php?option=com_users&amp;task=edit&amp;hidemainmenu=1&amp;cid[]='.$row->created_by; ?>"><?php echo $row->author; ?></a><br />
					<?php echo JText::_( 'COM_EVENTLIST_EMAIL' ).': '; ?><a href="mailto:<?php echo $row->email; ?>"><?php echo $row->email; ?></a><br />
					<?php
					$created	 	= JHTML::Date( $row->created, JText::_( 'DATE_FORMAT_LC2' ) );
					$edited 		= JHTML::Date( $row->modified, JText::_( 'DATE_FORMAT_LC2' ) );
					$ip				= $row->author_ip == 'COM_EVENTLIST_DISABLED' ? JText::_( 'COM_EVENTLIST_DISABLED' ) : $row->author_ip;
					$image 			= JHTML::_('image', 'administrator/templates/'. $this->template .'/images/menu/icon-16-info.png', JText::_('COM_EVENTLIST_NOTES') );
					$overlib 		= JText::_( 'COM_EVENTLIST_CREATED_AT' ).': '.$created.'<br />';
					$overlib		.= JText::_( 'COM_EVENTLIST_WITH_IP' ).': '.$ip.'<br />';
					if ($row->modified != '0000-00-00 00:00:00') {
						$overlib 	.= JText::_( 'COM_EVENTLIST_EDITED_AT' ).': '.$edited.'<br />';
						$overlib 	.= JText::_( 'COM_EVENTLIST_EDITED_FROM' ).': '.$row->editor.'<br />';
					}
					?>
					<span class="editlinktip hasTip" title="<?php echo JText::_('COM_EVENTLIST_EVENT_STATS'); ?>::<?php echo $overlib; ?>">
						<?php echo $image; ?>
					</span>
				</td>
				<td align="center">
					<?php
					if ($row->registra == 1) {
						$linkreg 	= 'index.php?option=com_eventlist&amp;view=attendees&amp;id='.$row->id;
						$count = $row->regCount;
						if ($row->maxplaces) 
						{
							$count .= '/'.$row->maxplaces;
							if ($row->waitinglist && $row->waiting) {
								$count .= ' +'.$row->waiting;
							}
						}
					?>
						<a href="<?php echo $linkreg; ?>" title="<?php echo JText::_('COM_EVENTLIST_MANAGE_ATTENDEES'); ?>">
						<?php echo $count; ?>
						</a>
					<?php
					}else {
					?>
					<?php echo JHTML::_('image', 'administrator/components/com_eventlist/assets/images/publish_r.png',JText::_('COM_EVENTLIST_NOTES')); ?>
					
					
					<?php
					}
					?>
				</td>
				<td align="center"><?php echo $row->id; ?></td>
			</tr>
			<?php $k = 1 - $k;  } ?>

		</tbody>
	</table>

	<p class="copyright">
		<?php echo ELAdmin::footer( ); ?>
	</p>

	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="option" value="com_eventlist" />
	<input type="hidden" name="view" value="events" />
	<input type="hidden" name="controller" value="events" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>