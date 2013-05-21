<?php
/**
 * @version 1.9 $Id$
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

defined('_JEXEC') or die; ?>

<form action="<?php echo JRoute::_('index.php?option=com_jem&view=archive'); ?>" method="post" name="adminForm" id="adminForm">

	<table class="adminform">
		<tr>
			<td width="100%">
				<?php echo JText::_( 'COM_JEM_SEARCH' ).' '.$this->lists['filter']; ?>
				<input type="text" name="search" id="search" value="<?php echo $this->lists['search']; ?>" class="text_area" onchange="document.adminForm.submit();" />
				<button onclick="this.form.submit();"><?php echo JText::_( 'COM_JEM_GO' ); ?></button>
				<button onclick="this.form.getElementById('search').value='';this.form.submit();"><?php echo JText::_( 'COM_JEM_RESET' ); ?></button>
			</td>
		</tr>
	</table>

	<table class="table table-striped" id="articleList">
		<thead>
			<tr>
				<th width="5" class="center"><?php echo JText::_( 'COM_JEM_NUM' ); ?></th>
				<th width="5" class="center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_DATE', 'a.dates', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_START', 'a.times', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_EVENT_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_VENUE', 'loc.venue', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<th class="title"><?php echo JText::_( 'COM_JEM_CATEGORY' ); ?></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_CITY', 'loc.city', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<th class="title"><?php echo JText::_( 'COM_JEM_CREATION' ); ?></th>
			</tr>
		</thead>

		<tfoot>
			<tr>
				<td colspan="9">
					<?php echo $this->pagination->getListFooter(); ?>
				</td>
			</tr>
		</tfoot>

		<tbody>
			<?php
			foreach ($this->rows as $i => $row) :
				if (JEMHelper::isValidDate($row->dates)) {
					$date		= JEMOutput::formatdate($row->dates);
				}
				else {
					$date		= JText::_('COM_JEM_OPEN_DATE');
				}

				if (!JEMHelper::isValidDate($row->enddates)) {
					$displaydate = $date;
				} else {
					$enddate 	= JEMOutput::formatdate($row->enddates);
					$displaydate = $date.' - <br />'.$enddate;
				}

				//Don't display 0 time
				if (!$row->times) {
					$time = '';
				} else {
					$time = strftime( $this->jemsettings->formattime, strtotime( $row->times ));
					$time = $time.' '.$this->jemsettings->timename;
				}
   			?>
			<tr class="row<?php echo $i % 2; ?>">
				<td class="center"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
				<td class="center"><input type="checkbox" id="cb<?php echo $i;?>" name="cid[]" value="<?php echo $row->id; ?>" onclick="isChecked(this.checked);" /></td>
				<td>
					<?php echo $displaydate; ?>
				</td>
				<td><?php echo $time; ?></td>
				<td><?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?></td>
				<td><?php echo $row->venue ? htmlspecialchars($row->venue, ENT_QUOTES, 'UTF-8') : '-'; ?></td>
				<td>
					<?php
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
						<span class="editlinktip hasTip" title="<?php echo JText::_('COM_JEM_EDIT_CATEGORY');?>::<?php echo $path; ?>">
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
				<td><?php echo $row->city ? htmlspecialchars($row->city, ENT_QUOTES, 'UTF-8') : '-'; ?></td>
				<td>
					<?php echo JText::_( 'COM_JEM_AUTHOR' ).': '; ?><a href="<?php echo 'index.php?option=com_users&amp;task=edit&amp;hidemainmenu=1&amp;cid[]='.$row->created_by; ?>"><?php echo $row->author; ?></a><br />
					<?php echo JText::_( 'COM_JEM_EMAIL' ).': '; ?><a href="mailto:<?php echo $row->email; ?>"><?php echo $row->email; ?></a><br />
					<?php
					$created = JHTML::Date( $row->created, JText::_( 'DATE_FORMAT_LC2' ) );
					$edited	 = JHTML::Date( $row->modified, JText::_( 'DATE_FORMAT_LC2' ) );
					$image 			= JHTML::_('image', 'administrator/templates/'. $this->template .'/images/menu/icon-16-info.png', JText::_('COM_JEM_NOTES') );
					$overlib 	= JText::_( 'COM_JEM_CREATED_AT' ).': '.$created.'<br />';
					$overlib	.= JText::_( 'COM_JEM_WITH_IP' ).': '.$row->author_ip.'<br />';
					if ($row->modified != '0000-00-00 00:00:00') {
						$overlib 	.= JText::_( 'COM_JEM_EDITED_AT' ).': '.$edited.'<br />';
						$overlib 	.= JText::_( 'COM_JEM_EDITED_FROM' ).': '.$row->editor.'<br />';
					}
					?>
					<span class="editlinktip hasTip" title="<?php echo JText::_('COM_JEM_EVENT_STATS'); ?>::<?php echo $overlib; ?>">
						<?php echo $image; ?>
					</span>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>

	</table>

	<p class="copyright">
		<?php echo JEMAdmin::footer( ); ?>
	</p>

	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="controller" value="archive" />
	<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>