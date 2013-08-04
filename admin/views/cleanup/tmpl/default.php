<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>
<table style="width:100%">
	<tr>
		<td width="10%">
			<div class="linkicon">
				<a href="index.php?option=com_jem&amp;task=cleanup.cleaneventimg">
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
				<a href="index.php?option=com_jem&amp;task=cleanup.cleanvenueimg">
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
				<a href="index.php?option=com_jem&amp;task=cleanup.cleancategoryimg">
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
        <a href="index.php?option=com_jem&amp;task=cleanup.triggerarchive">
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