<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>
	<table style="width:100%" class="adminlist">
		<tr>
			<td class="sectionname" width="100%"><font style="color: #C24733; font-size : 18px; font-weight: bold;"><?php echo Text::_('COM_JEM_REGISTERED_USER'); ?></font></td>
			<td><div class="button2-left"><div class="blank"><a href="#" onclick="window.print();return false;"><?php echo Text::_('COM_JEM_PRINT'); ?></a></div></div></td>
		</tr>
	</table>
	<br />
	<table class="adminlist" style="width:100%">
		<tr>
			<td align="left">
				<b><?php echo Text::_('COM_JEM_DATE').':'; ?></b>&nbsp;<?php echo $this->event->dates; ?><br />
				<b><?php echo Text::_('COM_JEM_EVENT_TITLE').':'; ?></b>&nbsp;<?php echo $this->escape($this->event->title); ?>
			</td>
		</tr>
	</table>
	<br />
	<table class="table table-striped" id="attendeesList">
		<thead>
			<tr>
				<th class="title"><?php echo Text::_('COM_JEM_NAME'); ?></th>
				<th class="title"><?php echo Text::_('COM_JEM_USERNAME'); ?></th>
				<th class="title"><?php echo Text::_('COM_JEM_EMAIL'); ?></th>
				<th class="title"><?php echo Text::_('COM_JEM_REGDATE'); ?></th>
				<?php if ($this->event->waitinglist): ?>
				<th class="title"><?php echo Text::_('COM_JEM_HEADER_WAITINGLIST_STATUS' ); ?></th>
				<?php endif; ?>
				<?php if (!empty($this->jemsettings->regallowcomments)) : ?>
				<th class="title"><?php echo Text::_('COM_JEM_COMMENT'); ?></th>
				<?php endif; ?>
				<th class="title center"><?php echo Text::_('COM_JEM_USER_ID'); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ($this->rows as $i => $row) :
			?>
			<tr class="row<?php echo $i % 2; ?>">
				<td><?php echo $row->name; ?></td>
				<td><?php echo $row->username; ?></td>
				<td><?php echo $row->email; ?></td>
				<td><?php if (!empty($row->uregdate)) { echo JHtml::_('date', $row->uregdate, Text::_('DATE_FORMAT_LC2')); } ?></td>
				<?php
				switch ($row->status) {
				case -1: // explicitely unregistered
					$text = 'COM_JEM_ATTENDEES_NOT_ATTENDING';
					break;
				case  0: // invited, not answered yet
					$text = 'COM_JEM_ATTENDEES_INVITED';
					break;
				case  1: // registered
					$text = $row->waiting ? 'COM_JEM_ATTENDEES_ON_WAITINGLIST' : 'COM_JEM_ATTENDEES_ATTENDING';
					break;
				default: // oops...
					$text = 'COM_JEM_ATTENDEES_STATUS_UNKNOWN';
					break;
				} ?>
				<td><?php echo Text::_($text); ?></td>
				<?php if (!empty($this->jemsettings->regallowcomments)) : ?>
				<td><?php echo $row->comment; ?></td>
				<?php endif; ?>
				<td class="center"><?php echo $row->uid; ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
