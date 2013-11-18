<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die; 
?>
	<table style="width:100%" class="adminlist">
		<tr>
		  	<td class="sectionname" width="100%"><font style="color: #C24733; font-size : 18px; font-weight: bold;"><?php echo JText::_('COM_JEM_REGISTERED_USER'); ?></font></td>
		  	<td><div class="button2-left"><div class="blank"><a href="#" onclick="window.print();return false;"><?php echo JText::_('COM_JEM_PRINT'); ?></a></div></div></td>
		</tr>
	</table>
	<br />
	<table class="adminlist">
		<tr>
		  	<td align="left">
				<b><?php echo JText::_('COM_JEM_DATE').':'; ?></b>&nbsp;<?php echo $this->event->dates; ?><br />
				<b><?php echo JText::_('COM_JEM_EVENT_TITLE').':'; ?></b>&nbsp;<?php echo htmlspecialchars($this->event->title, ENT_QUOTES, 'UTF-8'); ?>
			</td>
		  </tr>
	</table>
	<br />
	<table class="table table-striped" id="articleList">
		<thead>
			<tr>
				<th class="title"><?php echo JText::_('COM_JEM_NAME'); ?></th>
				<th class="title"><?php echo JText::_('COM_JEM_USERNAME'); ?></th>
				<th class="title"><?php echo JText::_('COM_JEM_EMAIL'); ?></th>
				<th class="title"><?php echo JText::_('COM_JEM_IP_ADDRESS'); ?></th>
				<th class="title"><?php echo JText::_('COM_JEM_REGDATE'); ?></th>
				<th class="title center"><?php echo JText::_('COM_JEM_USER_ID'); ?></th>
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
				<td><?php echo JHtml::_('date',$row->uregdate,JText::_('DATE_FORMAT_LC2')); ?></td>
				<td class="center"><?php echo $row->uid; ?></td>
			</tr>
			<?php $k = 1 - $k;  } ?>
		</tbody>
	</table>