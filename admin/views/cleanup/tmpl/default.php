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
<table cellspacing="0" cellpadding="4" border="0" width="100%">
	<tr>
		<td width="10%">
			<div class="linkicon">
				<a href="index.php?option=com_eventlist&amp;controller=cleanup&amp;task=cleaneventimg">
					<?php echo JHTML::_('image', 'administrator/components/com_eventlist/assets/images/icon-48-cleaneventimg.png',  JText::_( 'COM_EVENTLIST_CLEANUP_EVENT_IMG' ) ); ?>
					<span><?php echo JText::_( 'COM_EVENTLIST_CLEANUP_EVENT_IMG' ); ?></span>
				</a>
			</div>
		</td>
		<td width="40%" valign="middle">
			<?php echo JText::_( 'COM_EVENTLIST_CLEANUP_EVENT_IMG_DESC' ); ?>
		</td>
		<td width="10%">
			<div class="linkicon">
				<a href="index.php?option=com_eventlist&amp;controller=cleanup&amp;task=cleanvenueimg">
					<?php echo JHTML::_('image', 'administrator/components/com_eventlist/assets/images/icon-48-cleanvenueimg.png',  JText::_( 'COM_EVENTLIST_CLEANUP_VENUE_IMG' ) ); ?>
					<span><?php echo JText::_( 'COM_EVENTLIST_CLEANUP_VENUE_IMG' ); ?></span>
				</a>
			</div>
		</td>
		<td width="40%" valign="middle">
			<?php echo JText::_( 'COM_EVENTLIST_CLEANUP_VENUE_IMG_DESC' ); ?>
		</td>
	<tr>
    <td width="10%">
      <div class="linkicon">
        <a href="index.php?option=com_eventlist&amp;controller=cleanup&amp;task=triggerarchive">
          <?php echo JHTML::_('image', 'administrator/components/com_eventlist/assets/images/icon-48-archive.png',  JText::_( 'COM_EVENTLIST_CLEANUP_TRIGGER_AUTOARCHIVE' ) ); ?>
          <span><?php echo JText::_( 'COM_EVENTLIST_CLEANUP_TRIGGER_AUTOARCHIVE' ); ?></span>
        </a>
      </div>
    </td>
    <td width="40%" valign="middle">
      <?php echo JText::_( 'COM_EVENTLIST_CLEANUP_TRIGGER_AUTOARCHIVE_DESC' ); ?>
    </td>
    <td width="10%"></td>
    <td width="40%"></td>
  </tr>
</table>