<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;

$function = Factory::getApplication()->input->getCmd('function', 'jSelectVenue');
?>

<script>
	function tableOrdering( order, dir, view )
	{
		var form = document.getElementById("adminForm");

		form.filter_order.value 	= order;
		form.filter_order_Dir.value	= dir;
		form.submit( view );
	}
</script>

<div id="jem" class="jem_select_venue">
	<h1 class='componentheading'>
		<?php echo Text::_('COM_JEM_SELECT_VENUE'); ?>
	</h1>

	<div class="clr"></div>

	<form action="<?php echo Route::_('index.php?option=com_jem&view=editevent&layout=choosevenue&tmpl=component&function='.$this->escape($function).'&'.Session::getFormToken().'=1'); ?>" method="post" name="adminForm" id="adminForm">
		<div id="jem_filter" class="floattext">
			<div class="jem_fleft">
				<?php
				echo '<label for="filter_type">'.Text::_('COM_JEM_FILTER').'</label>&nbsp;';
				echo $this->searchfilter.'&nbsp;';
				?>
				<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->filter;?>" class="inputbox" onchange="document.adminForm.submit();" />
				<button type="submit" class="pointer btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
				<button type="button" class="pointer btn btn-secondary" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
				<button type="button" class="pointer btn btn-primary" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('', '<?php echo Text::_('COM_JEM_SELECT_VENUE') ?>');"><?php echo Text::_('COM_JEM_NOVENUE')?></button>
			</div>
			<div class="jem_fright">
				<?php
				echo '<label for="limit">'.Text::_('COM_JEM_DISPLAY_NUM').'</label>&nbsp;';
				echo $this->pagination->getLimitBox();
				?>
			</div>
		</div>

		<table class="eventtable table table-striped" style="width:100%" summary="jem">
			<thead>
				<tr>
                    <th style="width: 7px; text-align: left;" class="sectiontableheader"><?php echo Text::_('COM_JEM_NUM'); ?></th>
					<th style="text-align: left;" class="sectiontableheader"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_VENUE', 'l.venue', $this->lists['order_Dir'], $this->lists['order'], 'selectvenue' ); ?></th>
					<th style="text-align: left;" class="sectiontableheader"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_CITY', 'l.city', $this->lists['order_Dir'], $this->lists['order'], 'selectvenue' ); ?></th>
					<th style="text-align: left;" class="sectiontableheader"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_STATE', 'l.state', $this->lists['order_Dir'], $this->lists['order']); ?></th>
					<th style="text-align: left;" class="sectiontableheader"><?php echo Text::_('COM_JEM_COUNTRY'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($this->rows)) : ?>
					<tr style="text-align: center"><td colspan="0"><?php echo Text::_('COM_JEM_NOVENUES'); ?></td></tr>
				<?php else :?>
					<?php foreach ($this->rows as $i => $row) : ?>
					<tr class="row<?php echo $i % 2; ?>">
						<td><?php echo $this->pagination->getRowOffset( $i ); ?></td>
						<td style="text-align: left;">
							<a class="pointer" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('<?php echo $row->id; ?>', '<?php echo $this->escape(addslashes($row->venue)); ?>');"><?php echo $this->escape($row->venue); ?></a>
						</td>
						<td style="text-align: left;"><?php echo $this->escape($row->city); ?></td>
						<td style="text-align: left;"><?php echo $this->escape($row->state); ?></td>
						<td style="text-align: left;"><?php echo !empty($row->country) ? $this->escape($row->country) : ''; ?></td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<p>
		<input type="hidden" name="task" value="selectvenue" />
		<input type="hidden" name="option" value="com_jem" />
		<input type="hidden" name="tmpl" value="component" />
		<input type="hidden" name="function" value="<?php echo $this->escape($function); ?>" />
		<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
		</p>
	</form>

	<div class="pagination">
		<?php echo $this->pagination->getPagesLinks(); ?>
	</div>
</div>
