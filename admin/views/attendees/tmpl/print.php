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

defined('_JEXEC') or die; ?>

	<table border="0" width="100%" class="adminlist">
		<tr>
		  	<td class="sectionname" width="100%"><font style="color: #C24733; font-size : 18px; font-weight: bold;"><?php echo JText::_( 'COM_JEM_REGISTERED_USER' ); ?></font></td>
		  	<td><div class="button2-left"><div class="blank"><a href="#" onclick="window.print();return false;"><?php echo JText::_('COM_JEM_PRINT'); ?></a></div></div></td>
		</tr>
	</table>

	<br />

	<table class="adminlist">
		<tr>
		  	<td align="left">
				<b><?php echo JText::_( 'COM_JEM_DATE' ).':'; ?></b>&nbsp;<?php echo $this->event->dates; ?><br />
				<b><?php echo JText::_( 'COM_JEM_EVENT_TITLE' ).':'; ?></b>&nbsp;<?php echo htmlspecialchars($this->event->title, ENT_QUOTES, 'UTF-8'); ?>
			</td>
		  </tr>
	</table>

	<br />

	<table class="adminlist">
		<thead>
			<tr>
				<th class="title"><?php echo JText::_( 'COM_JEM_NAME' ); ?></th>
				<th class="title"><?php echo JText::_( 'COM_JEM_USERNAME' ); ?></th>
				<th class="title"><?php echo JText::_( 'COM_JEM_EMAIL' ); ?></th>
				<th class="title"><?php echo JText::_( 'COM_JEM_IP_ADDRESS' ); ?></th>
				<th class="title"><?php echo JText::_( 'COM_JEM_REGDATE' ); ?></th>
				<th class="title"><?php echo JText::_( 'COM_JEM_USER_ID'); ?></th>
			</tr>
		</thead>

		<tbody>
			<?php
			$k = 0;
			for($i=0, $n=count( $this->rows ); $i < $n; $i++) {
				$row = $this->rows[$i];
   			?>
			<tr class="<?php echo "row$k"; ?>">
				<td><?php echo $row->name; ?></td>
				<td><?php echo $row->username; ?></td>
				<td><?php echo $row->email; ?></td>
				<td><?php echo $row->uip; ?></td>
				<td><?php echo JHTML::Date( $row->uregdate, JText::_( 'DATE_FORMAT_LC2' ) ); ?></td>
				<td><?php echo $row->uid; ?></td>
			</tr>
			<?php $k = 1 - $k;  } ?>
		</tbody>

	</table>

	<p class="copyright">
		<?php echo ELAdmin::footer( ); ?>
	</p>