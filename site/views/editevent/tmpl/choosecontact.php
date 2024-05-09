<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$function = Factory::getApplication()->input->getCmd('function', 'jSelectContact');
?>

<script type="text/javascript">
	function tableOrdering( order, dir, view )
	{
		var form = document.getElementById("adminForm");

		form.filter_order.value 	= order;
		form.filter_order_Dir.value	= dir;
		form.submit( view );
	}
</script>

<div id="jem" class="jem_select_contact">
	<h1 class='componentheading'>
		<?php echo Text::_('COM_JEM_SELECT_CONTACT'); ?>
	</h1>

	<div class="clr"></div>

	<form action="<?php echo Route::_('index.php?option=com_jem&view=editevent&layout=choosecontact&tmpl=component&function='.$this->escape($function).'&'.Session::getFormToken().'=1'); ?>" method="post" name="adminForm" id="adminForm">
		<div id="jem_filter" class="floattext">
			<div class="jem_fleft">
				<?php
				echo '<label for="filter_type">'.Text::_('COM_JEM_FILTER').'</label>&nbsp;';
				echo $this->searchfilter.'&nbsp;';
				?>
				<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search']; ?>" class="inputbox" onChange="document.adminForm.submit();" />
				<button type="submit" class="pointer btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
				<button type="button" class="pointer btn btn-secondary" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
				<button type="button" class="pointer btn btn-primary" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('', '<?php echo Text::_('COM_JEM_SELECT_CONTACT') ?>');"><?php echo Text::_('COM_JEM_NOCONTACT')?></button>
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
					<th width="7" class="sectiontableheader"><?php echo Text::_('COM_JEM_NUM'); ?></th>
					<th align="left" class="sectiontableheader"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_NAME', 'con.name', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
					<?php if (0) : /* removed because it maybe forbidden to show */ ?>
						<th align="left" class="sectiontableheader"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ADDRESS', 'con.address', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
					<?php endif; ?>
					<th align="left" class="sectiontableheader"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_CITY', 'con.suburb', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
					<th align="left" class="sectiontableheader"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_STATE', 'con.state', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
					<?php if (0) : /* removed because it maybe forbidden to show */ ?>
						<th align="left" class="sectiontableheader"><?php echo Text::_('COM_JEM_EMAIL'); ?></th>
						<th align="left" class="sectiontableheader"><?php echo Text::_('COM_JEM_TELEPHONE'); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($this->rows)) : ?>
					<tr align="center"><td colspan="0"><?php echo Text::_('COM_JEM_NOCONTACTS'); ?></td></tr>
				<?php else :?>
					<?php foreach ($this->rows as $i => $row) : ?>
					<tr class="row<?php echo $i % 2; ?>">
						<td class="center"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
						<td align="left">
							<span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_SELECT'), $row->name, 'editlinktip'); ?>>
								<a class="pointer" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('<?php echo $row->id; ?>', '<?php echo $this->escape(addslashes($row->name)); ?>');"><?php echo $this->escape($row->name); ?></a>
							</span>
						</td>
						<?php if (0) : /* removed because it maybe forbidden to show */ ?>
							<td align="left"><?php echo $this->escape($row->address); ?></td>
						<?php endif; ?>
						<td align="left"><?php echo $this->escape($row->suburb); ?></td>
						<td align="left"><?php echo $this->escape($row->state); ?></td>
						<?php if (0) : /* removed because it maybe forbidden to show */ ?>
							<td align="left"><?php echo $this->escape($row->email_to); ?></td>
							<td align="left"><?php echo $this->escape($row->telephone); ?></td>
						<?php endif; ?>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<input type="hidden" name="task" value="selectcontact" />
		<input type="hidden" name="option" value="com_jem" />
		<input type="hidden" name="tmpl" value="component" />
		<input type="hidden" name="function" value="<?php echo $this->escape($function); ?>" />
		<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	</form>

	<div class="pagination">
		<?php echo $this->pagination->getPagesLinks(); ?>
	</div>
</div>
