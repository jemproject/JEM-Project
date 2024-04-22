<?php
/**
 * @version    4.2.1
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

$function = Factory::getApplication()->input->getCmd('function', 'jSelectUsers');
$checked = 0;

HTMLHelper::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.'/helpers/html');

// Get the form.
JForm::addFormPath(JPATH_COMPONENT . '/models/forms');
$form = JForm::getInstance('com_jem.addusers', 'addusers');

if (empty($form)) {
	return false;
}
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
<script type="text/javascript">
	function checkList(form)
	{
		var r='', i, n, e;
		for (i=0, n=form.elements.length; i<n; i++)
		{
			e = form.elements[i];
			if (e.type == 'checkbox' && e.id.indexOf('cb') === 0 && e.checked)
			{
				if (r) { r += ','; }
				r += e.value;
			}
		}
		return r;
	}
</script>

<div id="jem" class="jem_select_users">
	<h1 class='componentheading'>
		<?php echo Text::_('COM_JEM_SELECT_USERS_AND_STATUS'); ?>
	</h1>



	<div class="clr"></div>

	<form action="<?php echo Route::_('index.php?option=com_jem&view=attendees&layout=addusers&tmpl=component&function='.$this->escape($function).'&id='.$this->event->id.'&'.JSession::getFormToken().'=1'); ?>" method="post" name="adminForm" id="adminForm">

		<?php if(1) : ?>
		<div id="jem_filter" class="floattext">
			<div class="jem_fleft">
				<?php
				echo '<label for="filter_type">'.Text::_('COM_JEM_FILTER').'</label>&nbsp;';
				echo $this->searchfilter.'&nbsp;';
				?>
				<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search']; ?>" class="inputbox" onChange="document.adminForm.submit();" />
				<button type="submit" class="pointer btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
				<button type="button" class="pointer btn btn-secondary" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
			</div>
			<div class="jem_fright">
				<?php
				echo '<label for="limit">'.Text::_('COM_JEM_DISPLAY_NUM').'</label>&nbsp;';
				echo $this->pagination->getLimitBox();
				?>
			</div>
		</div>
		<?php endif; ?>

		<table class="eventtable table table-striped" style="width:100%" summary="jem">
			<thead>
				<tr>
					<th width="1%" class="sectiontableheader"><?php echo Text::_('COM_JEM_NUM'); ?></th>
					<th width="1%" class="center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
					<th align="left" class="sectiontableheader"><?php echo Text::_('COM_JEM_NAME'); ?></th>
					<th width="10%" class="center"><?php echo Text::_('COM_JEM_STATUS'); ?></th>
					<th width="10%" class="center"><?php echo Text::_('COM_JEM_PLACES'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($this->rows)) : ?>
					<tr align="center"><td colspan="0"><?php echo Text::_('COM_JEM_NOUSERS'); ?></td></tr>
				<?php else :?>
					<?php foreach ($this->rows as $i => $row) : ?>
					<tr class="row<?php echo $i % 2; ?>">
						<td class="center"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
						<td class="center"><?php echo HTMLHelper::_('grid.id', $i, $row->id); ?></td>
						<td align="left"><?php echo $this->escape($row->name); ?></td>
						<td class="center"><?php echo jemhtml::toggleAttendanceStatus(0, $row->status, false); ?></td>
						<td class="center"><?php echo $this->escape($row->places); ?></td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php
		if($this->event->maxbookeduser != 0)
		{
			$placesavailableuser = $this->event->maxbookeduser;
		}else{
			$placesavailableuser= null;
		}
		?>

        <div class="jem-row jem-justify-start valign-baseline">
            <div class="choose-status">
				<?php echo Text::_('COM_JEM_SELECT');?> <?php echo $form->getLabel('status'); ?> <?php echo $form->getInput('status'); ?>
            </div>
            <div class="choose-places">
				<?php echo Text::_('COM_JEM_SELECT');?> <?php echo Text::_('COM_JEM_PLACES'); ?>
            </div>
            <div style="padding-right:10px;">
                <input id="places" name="places" type="number" style="text-align: center; width:auto;" value="<?php echo $this->event->minbookeduser; ?>" max="<?php echo ($placesavailableuser > 0 ?  $placesavailableuser : ($placesavailableuser ?? '')); ?>" min="<?php echo $this->event->minbookeduser; ?>">
            </div>
        </div>

		<input type="hidden" name="task" value="selectusers" />
		<input type="hidden" name="option" value="com_jem" />
		<input type="hidden" name="tmpl" value="component" />
		<input type="hidden" name="function" value="<?php echo $this->escape($function); ?>" />
		<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
		<input type="hidden" name="boxchecked" value="<?php echo $checked; ?>" />
	</form>
	<div class="jem_fright">
		<button type="button" class="pointer btn btn-primary" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>_newusers(checkList(document.adminForm), document.adminForm.boxchecked.value, document.adminForm.status.value, document.adminForm.places.value, <?php echo $this->event->id; ?>, '<?php echo JSession::getFormToken(); ?>');">
			<?php echo Text::_('COM_JEM_SAVE'); ?>
		</button>
	</div>
	<div class="pagination">
		<?php echo $this->pagination->getPagesLinks(); ?>
	</div>
</div>
