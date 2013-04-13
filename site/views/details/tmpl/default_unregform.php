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

// no direct access
defined( '_JEXEC' ) or die;
?>
<?php 
//the user is allready registered. Let's check if he can unregister from the event
if ($this->row->unregistra == 0) :

	//no he is not allowed to unregister
	echo JText::_( 'COM_JEM_ALLREADY_REGISTERED' );

else:

	//he is allowed to unregister -> display form
	?>
	<form id="JEM" action="<?php echo JRoute::_('index.php'); ?>" method="post">
		<p>
			<?php if ($this->isregistered == 2): ?>
				<?php echo JText::_( 'COM_JEM_WAITINGLIST_UNREGISTER_BOX' ).': '; ?>
			<?php else: ?>
				<?php echo JText::_( 'COM_JEM_UNREGISTER_BOX' ).': '; ?>
			<?php endif;?>
			<input type="checkbox" name="reg_check" onclick="check(this, document.getElementById('el_send_attend'))" />
		</p>
		<p>
			<input type="submit" id="el_send_attend" name="el_send_attend" value="<?php echo JText::_( 'COM_JEM_UNREGISTER' ); ?>" disabled="disabled" />
		</p>
		<p>
			<input type="hidden" name="rdid" value="<?php echo $this->row->did; ?>" />
			<?php echo JHTML::_( 'form.token' ); ?>
			<input type="hidden" name="task" value="delreguser" />
		</p>
	</form>
	<?php
endif;