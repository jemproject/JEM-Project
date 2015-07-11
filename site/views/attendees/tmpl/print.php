<?php
/**
 * @version 2.1.5
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die; ?>

<table style="width: 100%">
	<tr>
		<td class="sectionname" width="100%"><span
			style="color: #C24733; font-size: 18px; font-weight: bold;"><?php echo JText::_( 'COM_JEM_REGISTERED_USER' ); ?>
			</span>
		</td>
		<td><div class="button2-left">
				<div class="blank">
					<a href="#" onclick="window.print();return false;"><?php echo JHtml::_('image','system/printButton.png', JText::_('JGLOBAL_PRINT'), JText::_('JGLOBAL_PRINT'), true); ?>
					</a>
				</div>
			</div>
		</td>
	</tr>
</table>
<br />
<table class="adminlist">
	<tr>
		<td align="left"><b><?php echo JText::_( 'COM_JEM_TITLE' ).':'; ?> </b>&nbsp;<?php echo $this->escape($this->event->title); ?><br />
			<b><?php echo JText::_( 'COM_JEM_DATE' ).':'; ?> </b>&nbsp;<?php echo JEMOutput::formatLongDateTime($this->event->dates, $this->event->times,
					$this->event->enddates, $this->event->endtimes); ?></td>
	</tr>
</table>
<br />
<table class="table table-striped" id="articleList">
	<thead>
		<tr>
			<th class="title"><?php echo JText::_( 'COM_JEM_USERNAME' ); ?></th>
			<th class="title"><?php echo JText::_( 'COM_JEM_REGDATE' ); ?></th>
			<?php if ($this->enableemailaddress == 1) : ?>
			<th class="title"><?php echo JText::_( 'COM_JEM_EMAIL' ); ?></th>
			<?php endif; ?>
			<?php if ($this->event->waitinglist): ?>
			<th class="title"><?php echo JText::_('COM_JEM_HEADER_WAITINGLIST_STATUS' ); ?></th>
			<?php endif; ?>
		</tr>
	</thead>

	<tbody>
		<?php
		$regname = $this->settings->get('global_regname', '1');
		$k = 0;
		foreach ($this->rows as $row) :
		?>
		<tr class="<?php echo "row$k"; ?>">
			<td><?php echo $regname ? $row->name : $row->username; ?></td>
			<td><?php echo JHtml::_('date', $row->uregdate, JText::_('DATE_FORMAT_LC2')); ?></td>
			<?php if ($this->enableemailaddress == 1) : ?>
			<td><?php echo $row->email; ?></td>
			<?php endif; ?>
			<?php if ($this->event->waitinglist): ?>
			<td><?php echo JText::_($row->waiting ? 'COM_JEM_ATTENDEES_ON_WAITINGLIST' : 'COM_JEM_ATTENDEES_ATTENDING'); ?></td>
			<?php endif; ?>
		</tr>
		<?php $k = 1 - $k;
		endforeach; ?>
	</tbody>
</table>

<div class="copyright">
	<?php echo JEMOutput::footer( ); ?>
</div>
