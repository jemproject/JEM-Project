<?php
/**
 * $Id$
 * @package Joomla
 * @subpackage Eventlist
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
 * Eventlist is maintained by the community located at
 * http://www.joomlaeventmanager.net
 *
 * Eventlist is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * Eventlist is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

defined('_JEXEC') or die;

if ($this->updatedata->failed == 0) {
		?>
		<table cellpadding="4" cellspacing="0" border="0" width="100%" class="adminlist">
			<tr>
		  		<td>
		  		<?php
		  			if ($this->updatedata->current == 0 ) {
		  				echo JHTML::_('image', 'administrator/templates/'. $this->template .'/images/header/icon-48-checkin.png', NULL);
		  			} elseif( $this->updatedata->current == -1 ) {
		  				echo JHTML::_('image', 'administrator/templates/'. $this->template .'/images/header/icon-48-help_header.png', NULL);
		  			} else {
		  				echo JHTML::_('image', 'administrator/templates/'. $this->template .'/images/header/icon-48-help_header.png', NULL);
		  			}
		  		?>
		  		</td>
		  		<td>
		  		<?php
		  			if ($this->updatedata->current == 0) {
		  				echo '<b><font color="green">'.JText::_( 'COM_EVENTLIST_LATEST_VERSION' ).'</font></b>';
		  			} elseif( $this->updatedata->current == -1 ) {
		  				echo '<b><font color="red">'.JText::_( 'COM_EVENTLIST_OLD_VERSION' ).'</font></b>';
		  			} else {
		  				echo '<b><font color="orange">'.JText::_( 'COM_EVENTLIST_NEWER_VERSION' ).'</font></b>';
		  			}
		  		?>
		  		</td>
			</tr>
		</table>

		<br />

		<table  cellspacing="0" cellpadding="0" border="0" width="100%" class="adminlist">
			<tr>
		  		<td><b><?php echo JText::_( 'COM_EVENTLIST_VERSION' ).':'; ?></b></td>
		  		<td><?php
					echo $this->updatedata->versiondetail;
					?>
		  		</td>
			</tr>
			<tr>
		  		<td><b><?php echo JText::_( 'COM_EVENTLIST_RELEASE_DATE' ).':'; ?></b></td>
		  		<td><?php
					echo $this->updatedata->date;
					?>
		  		</td>
			</tr>
			<tr>
		  		<td><b><?php echo JText::_( 'COM_EVENTLIST_CHANGES' ).':'; ?></b></td>
		  		<td><ul>
		  			<?php
					foreach ($this->updatedata->changes as $change) {
   						echo '<li>'.$change.'</li>';
					}
					?>
					<ul>
		  		</td>
			</tr>
			<tr>
		  		<td><b><?php echo JText::_( 'COM_EVENTLIST_INFORMATION' ).':'; ?></b></td>
		  		<td>
					<a href="<?php echo $this->updatedata->info; ?>" target="_blank">Click for more information</a>
		  		</td>
			</tr>
			<tr>
		  		<td><b><?php echo JText::_( 'COM_EVENTLIST_FILES' ).':'; ?></b></td>
		  		<td>
					<a href="<?php echo $this->updatedata->download; ?>" target="_blank">Download upgradepack</a>
		  		</td>
			</tr>
			<tr>
		  		<td><b><?php echo JText::_( 'COM_EVENTLIST_NOTES' ).':'; ?></b></td>
		  		<td><?php
					echo $this->updatedata->notes;
					?>
		  		</td>
			</tr>
		</table>

<?php
} else {
?>

		<table cellpadding="4" cellspacing="0" border="0" width="100%" class="adminlist">
			<tr>
		  		<td>
		  		<?php
		  			echo JHTML::_('image', 'administrator/templates/'. $this->template .'/images/header/icon-48-help_header.png', NULL);
		  		?>
		  		</td>
		  		<td>
		  		<?php
		  			echo '<b><font color="red">'.JText::_( 'COM_EVENTLIST_CONNECTION_FAILED' ).'</font></b>';
		  		?>
		  		</td>
			</tr>
		</table>
<?php
}
?>

<p class="copyright">
	<?php echo ELAdmin::footer( ); ?>
</p>