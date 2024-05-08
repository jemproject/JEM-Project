<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

$function = Factory::getApplication()->input->getCmd('function', 'jSelectUsers');
$checked = 0;

HTMLHelper::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.'/helpers/html');
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
		<?php echo Text::_('COM_JEM_SELECT_USERS_TO_INVITE'); ?>
	</h1>

	<div class="clr"></div>

	<form action="<?php echo Route::_('index.php?option=com_jem&view=editevent&layout=chooseusers&tmpl=component&function='.$this->escape($function).'&'.Session::getFormToken().'=1'); ?>" method="post" name="adminForm" id="adminForm">
		<?php if(0) : ?>
		<div class="jem-row valign-baseline">
			<div id="jem_filter" class="jem-form jem-row jem-justify-start">
				<div>
					<?php
					echo '<label for="filter_type">'.Text::_('COM_JEM_FILTER').'</label>&nbsp;';
					?>
				</div>
				<div class="jem-row jem-justify-start jem-nowrap">
          <?php echo $this->searchfilter.'&nbsp;'; ?>
          <input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search']; ?>" class="inputbox" onChange="document.adminForm.submit();" />
        </div>
				<div class="jem-row jem-justify-start jem-nowrap">
          <button type="submit" class="pointer btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
					<button type="button" class="pointer btn btn-secondary" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
					<?php /*<button type="button" class="pointer btn btn-primary" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('', '0');"><?php echo Text::_('COM_JEM_NOUSERS')?></button>*/ ?>
        </div>
      </div>
			<div class="jem-row jem-justify-start jem-nowrap">
        <div>
          <?php echo '<label for="limit">'.Text::_('COM_JEM_DISPLAY_NUM').'</label>&nbsp;'; ?>
        </div>
        <div>&nbsp;</div>
        <div>
          <?php echo $this->pagination->getLimitBox(); ?>
        </div>
      </div>
    </div>
		<?php endif;?>

		<hr class="jem-hr"/>

    <div class="jem-sort jem-sort-small">
      <div class="jem-list-row jem-small-list">
        <div class="sectiontableheader jem-users-number"><?php echo Text::_('COM_JEM_NUM'); ?></div>
        <div class="sectiontableheader jem-users-checkall"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></div>
        <div class="sectiontableheader jem-users-name"><?php echo Text::_('COM_JEM_NAME'); ?></div>
        <div class="sectiontableheader jem-users-state"><?php echo Text::_('COM_JEM_STATUS'); ?></div>
        <div class="sectiontableheader jem-users-state"><?php echo Text::_('COM_JEM_PLACES'); ?></div>
      </div>
    </div>

		<ul class="eventlist eventtable">
      <?php if (empty($this->rows)) : ?>
        <li class="jem-event jem-list-row jem-small-list"><?php echo Text::_('COM_JEM_NOUSERS'); ?></li>
      <?php else :?>
        <?php foreach ($this->rows as $i => $row) : ?>
          <li class="jem-event jem-list-row jem-small-list row<?php echo $i % 2; ?>">
            <div class="jem-event-info-small jem-users-number">
              <?php echo $this->pagination->getRowOffset( $i ); ?>
            </div>

            <div class="jem-event-info-small jem-users-checkall">
              <?php
							//echo HTMLHelper::_('grid.id', $i, $row->id);
							$cb = HTMLHelper::_('grid.id', $i, $row->id);
							if ($row->status == 0) {
							//	JemHelper::addLogEntry('before: '.$cb, __METHOD__);
								$cb = preg_replace('/(onclick=)/', 'checked $1', $cb);
								++$checked;
							//	JemHelper::addLogEntry('after:  '.$cb, __METHOD__);
							}
							echo $cb;
							?>
            </div>

            <div class="jem-event-info-small jem-users-name">
              <?php echo $this->escape($row->name); ?>
            </div>

            <div class="jem-event-info-small jem-users-state">
              <?php echo jemhtml::toggleAttendanceStatus( 0, $row->status, false); ?>
            </div>

            <div class="jem-event-info-small jem-users-places">
				<?php echo $this->escape($row->places); ?>
            </div>
          </li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>

        <hr class="jem-hr"/>

        <div class="jem-row jem-justify-start valign-baseline">
           <div style="padding-right:5px;">
				<?php echo Text::_('COM_JEM_SELECT');?>
            </div>
            <div style="padding-right:10px;">
				<?php echo Text::_('COM_JEM_PLACES'); ?>
            </div>
            <div style="padding-right:10px;">
                <input id="places" name="places" type="number" style="text-align: center; width:auto;"  value="0" max="1" min="0">
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

	<div class="pagination">
		<?php echo $this->pagination->getPagesLinks(); ?>
	</div>

	<hr class="jem-hr"/>

	<div class="jem-row jem-justify-end">
    <button type="button" class="pointer btn btn-primary" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>(checkList(document.adminForm), document.adminForm.boxchecked.value);"><?php echo Text::_('COM_JEM_SAVE'); ?></button>
  </div>
</div>
