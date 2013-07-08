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

<table style="width: 100%">
	<tr>
		<td class="sectionname" width="100%"><font
			style="color: #C24733; font-size: 18px; font-weight: bold;"><?php echo JText::_( 'COM_JEM_REGISTERED_USER' ); ?>
		</font></td>
		<td><div class="button2-left">
				<div class="blank">
					<a href="#" onclick="window.print();return false;"><?php echo JHTML::_('image','system/printButton.png', JText::_('JGLOBAL_PRINT'), JText::_('JGLOBAL_PRINT'), true); ?>
					</a>
				</div>
			</div></td>
	</tr>
</table>

<br />

<table class="adminlist">
	<tr>
		<td align="left"><b><?php echo JText::_( 'COM_JEM_TITLE' ).':'; ?> </b>&nbsp;<?php echo htmlspecialchars($this->event->title, ENT_QUOTES, 'UTF-8'); ?><br />
			<b><?php echo JText::_( 'COM_JEM_DATE' ).':'; ?> </b>&nbsp;<?php echo JEMOutput::formatLongDateTime($this->event->dates, $this->event->times,
					$this->event->enddates, $this->event->endtimes); ?></td>
	</tr>
</table>

<br />

<table class="table table-striped" id="articleList">
	<thead>
		<tr>
			<th class="title"><?php echo JText::_( 'COM_JEM_NAME' ); ?></th>
			<th class="title"><?php echo JText::_( 'COM_JEM_USERNAME' ); ?></th>
			<th class="title"><?php echo JText::_( 'COM_JEM_EMAIL' ); ?></th>
			<th class="title"><?php echo JText::_( 'COM_JEM_REGDATE' ); ?></th>
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
			<td><?php echo JHTML::Date( $row->uregdate, JText::_( 'DATE_FORMAT_LC2' ) ); ?>
			</td>
		</tr>
		<?php $k = 1 - $k;  
} ?>
	</tbody>

</table>

<p class="copyright">
	<?php echo JEMOutput::footer( ); ?>
</p>
