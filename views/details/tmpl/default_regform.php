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

// no direct access
defined( '_JEXEC' ) or die;

//the user is not registered allready -> display registration form
?>
<?php if ($this->row->maxplaces && count($this->registers) >= $this->row->maxplaces && !$this->row->waitinglist): ?>
	<p class="el-event-full">
		<?php echo JText::_( 'COM_EVENTLIST_EVENT_FULL_NOTICE' ); ?>
	</p>
<?php else: ?>
<form id="Eventlist" action="<?php echo JRoute::_('index.php'); ?>" method="post">
	<p>
		<?php if ($this->row->maxplaces && count($this->registers) >= $this->row->maxplaces): // full event ?>
			<?php echo JText::_( 'COM_EVENTLIST_EVENT_FULL_REGISTER_TO_WAITING_LIST' ).': '; ?>
		<?php else: ?>
			<?php echo JText::_( 'COM_EVENTLIST_I_WILL_GO' ).': '; ?>
		<?php endif; ?>
		<input type="checkbox" name="reg_check" onclick="check(this, document.getElementById('el_send_attend'))" />
	</p>
<p>
	<input type="submit" id="el_send_attend" name="el_send_attend" value="<?php echo JText::_( 'COM_EVENTLIST_REGISTER' ); ?>" disabled="disabled" />
</p>
<p>
	<input type="hidden" name="rdid" value="<?php echo $this->row->did; ?>" />
	<?php echo JHTML::_( 'form.token' ); ?>
	<input type="hidden" name="task" value="userregister" />
</p>
</form>
<?php endif;