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

HTMLHelper::addIncludePath(JPATH_COMPONENT.'/helpers/html');

$user		= JemFactory::getUser();
$userId		= $user->get('id');
$listOrder	= $this->escape($this->state->get('list.ordering'));
$listDirn	= $this->escape($this->state->get('list.direction'));
$document   = Factory::getApplication()->getDocument();
$wa         = $document->getWebAssetManager();

$wa->addInlineScript('
	Joomla.submitbutton = function(task)
	{
		document.adminForm.task.value=task;
		if (task == "attendees.export") {
			Joomla.submitform(task, document.getElementById("adminForm"));
			document.adminForm.task.value="";
		} else {
      		Joomla.submitform(task, document.getElementById("adminForm"));
		}
	};
');
$wa->addInlineScript('
    function submitName(node) {
      node.parentNode.previousElementSibling.childNodes[0].checked = true;
      Joomla.submitbutton("attendees.edit");
    }
');
?>
<form action="<?php echo Route::_('index.php?option=com_jem&view=attendees&eventid='.$this->event->id); ?>"  method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <fieldset id="filter-bar" class="mb-3">
            <div class="row">
                <div class="col-md-11">
					 <div class="row mb-12">
	                        <div class="col-md-2">
				   				<strong><?php echo Text::_('COM_JEM_DATE').':'; ?></strong>&nbsp;<?php echo $this->event->dates; ?><br />
							</div>
			                <div class="col-md-2">
								<strong><?php echo Text::_('COM_JEM_EVENT_TITLE').':'; ?></strong>&nbsp;<?php echo $this->escape($this->event->title); ?>
							</div>
					 </div>
				</div>
                <div class="col-md-1">
                    <div class="row">
                        <div class="wauto-minwmax">
                            <div class="float-end">
                                <?php echo $this->pagination->getLimitBox(); ?>
                            </div>
                        </div>
                    </div>
                </div>
			</div>
		</fieldset>
		<table class="adminform">
			<tr>
				<td style="width: 100%;">
					<?php echo Text::_('COM_JEM_SEARCH').' '.$this->lists['filter']; ?>
					<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
					<button class="buttonfilter" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
					<button class="buttonfilter" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
				</td>
				<td style="text-align:right; white-space:nowrap;">
					<?php echo Text::_('COM_JEM_STATUS').' '.$this->lists['status']; ?>
				</td>
			</tr>
		</table>
		<table class="table table-striped" id="attendeeList">
			<thead>
				<tr>
                    <th style="width: 1%" class="center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
					<th class="title"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_NAME', 'u.name', $listDirn, $listOrder); ?></th>
					<th class="title"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_USERNAME', 'u.username', $listDirn, $listOrder); ?></th>
					<th class="title"><?php echo Text::_('COM_JEM_EMAIL'); ?></th>
					<th class="title"><?php echo Text::_('COM_JEM_IP_ADDRESS'); ?></th>
					<th class="title"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_REGDATE', 'r.uregdate', $listDirn, $listOrder); ?></th>
					<th class="title center"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_USER_ID', 'r.uid', $listDirn, $listOrder); ?></th>
					<th class="title center"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_HEADER_WAITINGLIST_STATUS', 'r.waiting',$listDirn, $listOrder); ?></th>
                    <th class="title center"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ATTENDEES_PLACES', 'r.waiting',$listDirn, $listOrder); ?></th>
					<?php if (!empty($this->jemsettings->regallowcomments)) : ?>
					<th class="title"><?php echo Text::_('COM_JEM_COMMENT'); ?></th>
					<?php endif;?>
					<th class="title center"><?php echo Text::_('COM_JEM_REMOVE_USER'); ?></th>
                    <th style="width: 1%" class="center nowrap"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ATTENDEES_REGID', 'r.id', $listDirn, $listOrder ); ?></th>
				</tr>
			</thead>

			<tbody>
				<?php
				$canChange = $user->authorise('core.edit.state');

				foreach ($this->items as $i => $row) :
				?>
				<tr class="row<?php echo $i % 2; ?>">
					<td class="center"><?php echo HTMLHelper::_('grid.id', $i, $row->id); ?></td> <?php // Die ID kann man doch auch als Parameter für "submitName()" nehmen. Dann muss ich nicht erst den Baum entlang hangeln ?>
					<td><a href="<?php echo Route::_('index.php?option=com_jem&view=attendee&event='.$row->event . '&id='.$row->id);?>"><?php echo $row->name; ?></a></td>
					<td><?php echo $row->username; ?></td>
					<td class="email"><a href="mailto:<?php echo $row->email; ?>"><?php echo $row->email; ?></a></td>
					<td><?php echo $row->uip == 'DISABLED' ? Text::_('COM_JEM_DISABLED') : $row->uip; ?></td>
					<td><?php if (!empty($row->uregdate)) { echo HTMLHelper::_('date', $row->uregdate, Text::_('DATE_FORMAT_LC2')); } ?></td>
					<td class="center">
					<a href="<?php echo Route::_('index.php?option=com_users&task=user.edit&id='.$row->uid); ?>"><?php echo $row->uid; ?></a>
					</td>
					<td class="center">
						<?php
						$status = (int)$row->status;
                        if($this->event->waitinglist) {
                            if ($status === 1 && $row->waiting == 1) {
                                $status = 2;
                            }
                            echo jemhtml::toggleAttendanceStatus($i, $status, $canChange);
                        } else {
                            echo jemhtml::toggleAttendanceStatus($i, $status, false);
                        }
						?>
					</td>
                    <td class="center">
						<?php echo $row->places; ?>
                    </td>
					<?php if (!empty($this->jemsettings->regallowcomments)) : ?>
					<?php $cmnt = (\Joomla\String\StringHelper::strlen($row->comment) > 16) ? (rtrim(\Joomla\String\StringHelper::substr($row->comment, 0, 14)).'&hellip;') : $row->comment; ?>
					<td><?php if (!empty($cmnt)) { echo HTMLHelper::_('tooltip', $row->comment, null, null, $cmnt, null, null); } ?></td>
					<?php endif; ?>
					<td class="center">
						<a href="javascript: void(0);" onclick="return Joomla.listItemTask('cb<?php echo $i;?>','attendees.remove')">
							<?php echo HTMLHelper::_('image','com_jem/publish_r.png',Text::_('COM_JEM_REMOVE'),NULL,true); ?>
						</a>
					</td>
					<td class="center">
					<?php echo $this->escape($row->id); ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

	    <div class="ms-auto mb-4 me-0">
	        <?php echo (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks(null) : $this->pagination->getListFooter()); ?>
	    </div>
    </div>

    <div>
        <input type="hidden" name="task" value=""/>
        <input type="hidden" name="boxchecked" value="0"/>
        <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>"/>
        <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>"/>

        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
