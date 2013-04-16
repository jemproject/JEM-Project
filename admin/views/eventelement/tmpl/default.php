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
?>

<form action="index.php?option=com_jem&amp;view=eventelement&amp;tmpl=component" method="post" name="adminForm" id="adminForm">

<table class="adminform">
	<tr>
		<td width="100%">
			<?php echo JText::_( 'COM_JEM_SEARCH' ).' '.$this->lists['filter']; ?>
			<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
			<button onclick="this.form.submit();"><?php echo JText::_( 'COM_JEM_GO' ); ?></button>
			<button onclick="this.form.getElementById('search').value='';this.form.submit();"><?php echo JText::_( 'COM_JEM_RESET' ); ?></button>
		</td>
		<td nowrap="nowrap">
			<?php echo $this->lists['state'];	?>
		</td>
	</tr>
</table>

<table class="adminlist" cellspacing="1">
	<thead>
		<tr>
			<th width="5"><?php echo JText::_( 'COM_JEM_NUM' ); ?></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_EVENT_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order'], 'eventelement' ); ?></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_DATE', 'a.dates', $this->lists['order_Dir'], $this->lists['order'], 'eventelement' ); ?></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_START', 'a.times', $this->lists['order_Dir'], $this->lists['order'], 'eventelement' ); ?></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_VENUE', 'loc.venue', $this->lists['order_Dir'], $this->lists['order'], 'eventelement' ); ?></th>
			<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_CITY', 'loc.city', $this->lists['order_Dir'], $this->lists['order'], 'eventelement' ); ?></th>
			<th class="title"><?php echo JText::_('COM_JEM_CATEGORY'); ?></th>
		    <th width="1%" nowrap="nowrap"><?php echo JText::_( 'COM_JEM_PUBLISHED' ); ?></th>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="8">
				<?php echo $this->pagination->getListFooter(); ?>
			</td>
		</tr>
	</tfoot>

	<tbody>
		<?php
			$k = 0;
			for ($i=0, $n=count( $this->rows ); $i < $n; $i++) {
				$row = $this->rows[$i];
		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td>
				<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SELECT' );?>::<?php echo $row->title; ?>">
				<a style="cursor:pointer" onclick="window.parent.elSelectEvent('<?php echo $row->id; ?>', '<?php echo str_replace( array("'", "\""), array("\\'", ""), $row->title ); ?>');">
					<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
				</a></span>
			</td>
			<td>
				<?php
					//Format date
					if (ELHelper::isValidDate($row->dates)) {
						$date = strftime( $this->elsettings->formatdate, strtotime( $row->dates ));
					} 
					else {
						$date		= JText::_('COM_JEM_OPEN_DATE');
					}
					if ( !ELHelper::isValidDate($row->enddates) ) {
						$displaydate = $date;
					} else {
						$enddate 	= strftime( $this->elsettings->formatdate, strtotime( $row->enddates ));
						$displaydate = $date.' - '.$enddate;
					}

					echo $displaydate;
				?>
			</td>
			<td>
				<?php
					//Prepare time
					if (!$row->times) {
						$displaytime = '-';
					} else {
						$time = strftime( $this->elsettings->formattime, strtotime( $row->times ));
						$displaytime = $time.' '.$this->elsettings->timename;
					}
					echo $displaytime;
				?>
			</td>
			<td><?php echo $row->venue ? htmlspecialchars($row->venue, ENT_QUOTES, 'UTF-8') : '-'; ?></td>
			<td><?php echo $row->city ? htmlspecialchars($row->city, ENT_QUOTES, 'UTF-8') : '-'; ?></td>
			<td><?php
				$nr = count($row->categories);
				$ix = 0;
				foreach ($row->categories as $key => $category) :				
					$catlink	= 'index.php?option=com_jem&amp;controller=categories&amp;task=edit&amp;cid[]='. $category->id;
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
						
	
							<?php echo $title; ?>
						
				
					<?php
					}
					$ix++;
					if ($ix != $nr) :
						echo ', ';
					endif;
				endforeach;
				?></td>
			<td align="center">
				<?php $img = $row->published ? 'tick.png' : 'publish_x.png'; ?>
				<img src="../media/com_jem/images/<?php echo $img;?>" width="16" height="16" border="0" alt="" />
			</td>
		</tr>
			<?php $k = 1 - $k; } ?>
	</tbody>

</table>

<p class="copyright">
	<?php echo ELAdmin::footer( ); ?>
</p>

<input type="hidden" name="task" value="" />
<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>