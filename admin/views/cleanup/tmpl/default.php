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

defined('_JEXEC') or die;
?>
<table style="width:100%">
	<tr>
		<td width="10%">
			<div class="linkicon">
				<a href="index.php?option=com_jem&amp;controller=cleanup&amp;task=cleaneventimg">
					<?php echo JHTML::_('image', 'media/com_jem/images/icon-48-cleaneventimg.png',  JText::_( 'COM_JEM_CLEANUP_EVENT_IMG' ) ); ?>
					<span><?php echo JText::_( 'COM_JEM_CLEANUP_EVENT_IMG' ); ?></span>
				</a>
			</div>
		</td>
		<td width="40%" valign="middle">
			<?php echo JText::_( 'COM_JEM_CLEANUP_EVENT_IMG_DESC' ); ?>
		</td>
		<td width="10%">
			<div class="linkicon">
				<a href="index.php?option=com_jem&amp;controller=cleanup&amp;task=cleanvenueimg">
					<?php echo JHTML::_('image', 'media/com_jem/images/icon-48-cleanvenueimg.png',  JText::_( 'COM_JEM_CLEANUP_VENUE_IMG' ) ); ?>
					<span><?php echo JText::_( 'COM_JEM_CLEANUP_VENUE_IMG' ); ?></span>
				</a>
			</div>
		</td>
		<td width="40%" valign="middle">
			<?php echo JText::_( 'COM_JEM_CLEANUP_VENUE_IMG_DESC' ); ?>
		</td>
		</tr>
		<tr>
		<td width="10%">
			<div class="linkicon">
				<a href="index.php?option=com_jem&amp;controller=cleanup&amp;task=cleancategoryimg">
					<?php echo JHTML::_('image', 'media/com_jem/images/icon-48-cleancategoryimg.png',  JText::_( 'COM_JEM_CLEANUP_CATEGORY_IMG' ) ); ?>
					<span><?php echo JText::_( 'COM_JEM_CLEANUP_CATEGORY_IMG' ); ?></span>
				</a>
			</div>
		</td>
		<td width="40%" valign="middle">
			<?php echo JText::_( 'COM_JEM_CLEANUP_CATEGORY_IMG_DESC' ); ?>
		</td>
		
		
		
		
	
    <td width="10%">
      <div class="linkicon">
        <a href="index.php?option=com_jem&amp;controller=cleanup&amp;task=triggerarchive">
          <?php echo JHTML::_('image', 'media/com_jem/images/icon-48-archive.png',  JText::_( 'COM_JEM_CLEANUP_TRIGGER_AUTOARCHIVE' ) ); ?>
          <span><?php echo JText::_( 'COM_JEM_CLEANUP_TRIGGER_AUTOARCHIVE' ); ?></span>
        </a>
      </div>
    </td>
    <td width="40%" valign="middle">
      <?php echo JText::_( 'COM_JEM_CLEANUP_TRIGGER_AUTOARCHIVE_DESC' ); ?>
    </td>
    <td width="10%"></td>
    <td width="40%"></td>
  </tr>
</table>