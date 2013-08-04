<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>

<form action="<?php echo JRoute::_('index.php?option=com_jem&view=updatecheck'); ?>" method="post" name="adminForm" id="adminForm">

<?php 
if ($this->updatedata->failed == 0) {
		?>
		<table style="width:100%" class="adminlist">
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
		  				echo '<b><font color="green">'.JText::_( 'COM_JEM_LATEST_VERSION' ).'</font></b>';
		  			} elseif( $this->updatedata->current == -1 ) {
		  				echo '<b><font color="red">'.JText::_( 'COM_JEM_OLD_VERSION' ).'</font></b>';
		  			} else {
		  				echo '<b><font color="orange">'.JText::_( 'COM_JEM_NEWER_VERSION' ).'</font></b>';
		  			}
		  		?>
		  		</td>
			</tr>
		</table>

		<br />

		<table style="width:100%" class="adminlist">
			<tr>
		  		<td><b><?php echo JText::_( 'COM_JEM_VERSION' ).':'; ?></b></td>
		  		<td><?php
					echo $this->updatedata->versiondetail;
					?>
		  		</td>
			</tr>
			<tr>
		  		<td><b><?php echo JText::_( 'COM_JEM_RELEASE_DATE' ).':'; ?></b></td>
		  		<td><?php
					echo $this->updatedata->date;
					?>
		  		</td>
			</tr>
			<tr>
		  		<td><b><?php echo JText::_( 'COM_JEM_CHANGES' ).':'; ?></b></td>
		  		<td><ul>
		  			<?php
					foreach ($this->updatedata->changes as $change) {
   						echo '<li>'.$change.'</li>';
					}
					?>
					</ul>
		  		</td>
			</tr>
			<tr>
		  		<td><b><?php echo JText::_( 'COM_JEM_INFORMATION' ).':'; ?></b></td>
		  		<td>
					<a href="<?php echo $this->updatedata->info; ?>" target="_blank">Click for more information</a>
		  		</td>
			</tr>
			<tr>
		  		<td><b><?php echo JText::_( 'COM_JEM_FILES' ).':'; ?></b></td>
		  		<td>
					<a href="<?php echo $this->updatedata->download; ?>" target="_blank">Click to Download</a>
		  		</td>
			</tr>
			<tr>
		  		<td><b><?php echo JText::_( 'COM_JEM_NOTES' ).':'; ?></b></td>
		  		<td><?php
					echo $this->updatedata->notes;
					?>
		  		</td>
			</tr>
		</table>

<?php
} else {
?>

		<table style="width:100%" class="adminlist">
			<tr>
		  		<td>
		  		<?php
		  			echo JHTML::_('image', 'administrator/templates/'. $this->template .'/images/header/icon-48-help_header.png', NULL);
		  		?>
		  		</td>
		  		<td>
		  		<?php
		  			echo '<b><font color="red">'.JText::_( 'COM_JEM_CONNECTION_FAILED' ).'</font></b>';
		  		?>
		  		</td>
			</tr>
		</table>
<?php
}
?>
<input type="hidden" name="task" value="" />
<?php echo JHtml::_('form.token'); ?>
</form>