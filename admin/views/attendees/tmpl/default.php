<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\String\StringHelper;

HTMLHelper::addIncludePath(JPATH_COMPONENT.'/helpers/html');

$user        = JemFactory::getUser();
$userId        = $user->get('id');
$listOrder    = $this->escape($this->state->get('list.ordering'));
$listDirn    = $this->escape($this->state->get('list.direction'));
$document   = Factory::getApplication()->getDocument();
$wa         = $document->getWebAssetManager();
$waitingListPlaces = array();
$waitingListIds = array();
$isPriced = !empty($this->isPriced);
$isCommerceReadOnly = !empty($this->commerceReadOnly);
$isAreaCapacity = !empty($this->isAreaCapacity);

foreach ($this->items as $item) {
    $waitingListPlaces[(int) $item->id] = max(1, (int) $item->places);

    if ((int) $item->status === JemRegistrationTransition::ATTENDING && !empty($item->waiting)) {
        $waitingListIds[(int) $item->id] = true;
    }
}

$wa->addInlineScript('
    const jemWaitingListPlaces = ' . json_encode($waitingListPlaces) . ';
    const jemWaitingListIds = ' . json_encode($waitingListIds) . ';
    Joomla.submitbutton = function(task)
    {
        if (task === "attendees.promoteWaitingList" || task === "attendees.setAttending") {
            let requested = 0;
            let waitingSelected = false;
            document.querySelectorAll("#adminForm input[name=\"cid[]\"]:checked").forEach(function(checkbox) {
                requested += jemWaitingListPlaces[checkbox.value] || 0;
                waitingSelected = waitingSelected || !!jemWaitingListIds[checkbox.value];
            });

            if (task === "attendees.setAttending" && !waitingSelected) {
                Joomla.submitform(task, document.getElementById("adminForm"));
                return;
            }
            const message = ' . json_encode(Text::_('COM_JEM_WAITINGLIST_PROMOTION_CONFIRM')) . '
                .replace("%1$s", ' . (int) $this->waitingListStatus->availableBefore . ')
                .replace("%2$s", requested);

            if (!window.confirm(message)) {
                return false;
            }

            const force = document.querySelector("#adminForm input[name=\"waitinglist_force\"]:checked");
            if (force && !window.confirm(' . json_encode(Text::_('COM_JEM_WAITINGLIST_FORCE_PROMOTION_CONFIRM')) . ')) {
                return false;
            }
        }

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
        <?php if ($isCommerceReadOnly) : ?>
            <div class="alert alert-info" role="status">
                <?php echo Text::_('COM_JEM_PRICED_REGISTRATION_COMMERCE_READ_ONLY'); ?>
            </div>
        <?php endif; ?>
        <fieldset id="filter-bar" class="mb-3">
            <div class="row">
                <div class="col-md-11">
                     <div class="row mb-12">
                            <div class="col-md-2">
                                   <strong><?php echo Text::_('COM_JEM_DATE').':'; ?></strong>&nbsp;<?php echo $this->event->dates; ?><br>
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
        <?php if ($this->event->waitinglist || $isPriced || $isAreaCapacity) : ?>
            <div class="alert alert-info d-flex flex-wrap gap-4 align-items-center" role="status">
                <span><?php echo Text::sprintf('COM_JEM_WAITINGLIST_CAPACITY_SUMMARY', (int) $this->waitingListStatus->availableBefore, (int) $this->waitingListStatus->waitingBefore); ?></span>
                <?php if ($isPriced) : ?>
                    <?php foreach ($this->poolAvailability as $pool) : ?>
                        <span class="badge bg-light text-dark border">
                            <?php echo $this->escape($pool->name); ?>:
                            <?php echo (int) $pool->remaining; ?> / <?php echo (int) $pool->capacity; ?>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($isAreaCapacity) : ?>
                    <?php foreach ((array) $this->capacityAvailability->options as $area) : ?>
                        <span class="badge bg-light text-dark border">
                            <?php echo $this->escape((string) $area['space_name'] . ' · ' . (string) $area['area_name']); ?>:
                            <?php echo (int) $area['remaining']; ?> / <?php echo (int) $area['capacity']; ?>
                        </span>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($this->event->waitinglist) : ?>
                <span>
                    <?php if ((int) ($this->jemsettings->waitinglist_automatic ?? 1)) : ?>
                        <?php echo ($this->jemsettings->waitinglist_strategy ?? 'strict') === 'fill'
                            ? Text::_('COM_JEM_WAITINGLIST_MODE_AUTOMATIC_FILL')
                            : Text::_('COM_JEM_WAITINGLIST_MODE_AUTOMATIC_STRICT'); ?>
                    <?php else : ?>
                        <?php echo Text::_('COM_JEM_WAITINGLIST_MODE_MANUAL'); ?>
                    <?php endif; ?>
                </span>
                <label class="mb-0">
                    <input type="hidden" name="waitinglist_notify" value="0">
                    <input type="checkbox" name="waitinglist_notify" value="1" checked="checked">
                    <?php echo Text::_('COM_JEM_WAITINGLIST_NOTIFY_PROMOTED'); ?>
                </label>
                <?php if ($this->canForcePromotion) : ?>
                    <label class="mb-0 text-danger">
                        <input type="checkbox" name="waitinglist_force" value="1">
                        <?php echo Text::_('COM_JEM_WAITINGLIST_FORCE_PROMOTION'); ?>
                    </label>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <table class="adminform">
            <tr>
                <td style="width: 100%;">
                    <?php echo Text::_('COM_JEM_SEARCH').' '.$this->lists['filter']; ?>
                    <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="text_area" onChange="document.adminForm.submit();" />
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
                    <th style="width: 1%" class="center"><?php if (!$isCommerceReadOnly) : ?><input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /><?php endif; ?></th>
                    <th class="title"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_NAME', 'u.name', $listDirn, $listOrder); ?></th>
                    <th class="title"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_USERNAME', 'u.username', $listDirn, $listOrder); ?></th>
                    <th class="title"><?php echo Text::_('COM_JEM_EMAIL'); ?></th>
                    <th class="title"><?php echo Text::_('COM_JEM_IP_ADDRESS'); ?></th>
                    <th class="title"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_REGDATE', 'r.uregdate', $listDirn, $listOrder); ?></th>
                    <th class="title center"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_USER_ID', 'r.uid', $listDirn, $listOrder); ?></th>
                    <th class="title center"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_HEADER_WAITINGLIST_STATUS', 'r.waiting',$listDirn, $listOrder); ?></th>
                    <th class="title center"><?php echo $isPriced
                        ? Text::_('COM_JEM_PRICED_REGISTRATION_ORDER')
                        : HTMLHelper::_('grid.sort', 'COM_JEM_ATTENDEES_PLACES', 'r.waiting', $listDirn, $listOrder); ?></th>
                    <?php if (!empty($this->jemsettings->regallowcomments)) : ?>
                    <th class="title"><?php echo Text::_('COM_JEM_COMMENT'); ?></th>
                    <?php endif;?>
                    <th class="title center"><?php echo Text::_('COM_JEM_REMOVE_USER'); ?></th>
                    <th style="width: 1%" class="center nowrap"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ATTENDEES_REGID', 'r.id', $listDirn, $listOrder ); ?></th>
                    <th style="width: 1%" class="center nowrap"><?php echo Text::_('COM_JEM_ATTENDEE_REGISTRATION_RENOTIFY'); ?></th>
                </tr>
            </thead>

            <tbody>
                <?php
                $canChange = JemHelperBackend::canManage('jem.attendees.manage');

                foreach ($this->items as $i => $row) :
                ?>
                <tr class="row<?php echo $i % 2; ?>">
                    <td class="center"><?php if (!$isCommerceReadOnly) { echo HTMLHelper::_('grid.id', $i, $row->id); } ?></td> <?php // The ID could also be passed to submitName(), avoiding DOM traversal. ?>
                    <td><a href="<?php echo Route::_('index.php?option=com_jem&view=attendee&event='.(int) $row->event . '&id='.(int) $row->id);?>"><?php echo $this->escape($row->name); ?></a></td>
                    <td><?php echo $this->escape($row->username); ?></td>
                    <td class="email"><a href="mailto:<?php echo htmlspecialchars($row->email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $this->escape($row->email); ?></a></td>
                    <td><?php echo $row->uip == 'DISABLED' ? Text::_('COM_JEM_DISABLED') : $this->escape($row->uip); ?></td>
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
                            echo jemhtml::toggleAttendanceStatus($i, $status, $canChange && !$isPriced);
                        } else {
                            echo jemhtml::toggleAttendanceStatus($i, $status, false);
                        }
                        ?>
                    </td>
                    <td class="center">
                        <?php if (!$isPriced) : ?>
                            <strong><?php echo (int) $row->places; ?></strong>
                            <?php if ($isAreaCapacity) : ?>
                                <?php $capacityLines = $this->capacityBreakdowns[(int) $row->id] ?? array(); ?>
                                <ul class="list-unstyled text-start mb-0 small">
                                    <?php foreach ($capacityLines as $line) : ?>
                                        <li><?php echo (int) $line->quantity; ?>&times; <?php echo $this->escape($line->space_name . ' · ' . $line->area_name); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        <?php else : ?>
                            <?php $orderLines = $this->commercialBreakdowns[(int) $row->id] ?? array(); ?>
                            <?php if (!$orderLines) : ?>
                                <span class="text-muted"><?php echo Text::_('COM_JEM_PRICED_REGISTRATION_NO_ORDER'); ?></span>
                            <?php else : ?>
                                <ul class="list-unstyled text-start mb-1">
                                    <?php foreach ($orderLines as $line) : ?>
                                        <li>
                                            <strong><?php echo (int) $line->quantity; ?>&times;</strong>
                                            <?php echo $this->escape($line->item_name); ?>
                                            <?php if (!empty($line->pool_name)) : ?>
                                                <small class="text-muted">&middot; <?php echo $this->escape($line->pool_name); ?></small>
                                            <?php endif; ?>
                                            <span class="text-nowrap">&mdash; <?php echo $this->escape($line->currency . ' ' . $line->line_gross); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <small class="d-block text-start fw-semibold">
                                    <?php echo (int) $row->places; ?> &middot;
                                    <?php echo $this->escape((string) $row->currency . ' ' . (string) $row->grand_total); ?>
                                </small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <?php if (!empty($this->jemsettings->regallowcomments)) : ?>
                    <?php $cmnt = (StringHelper::strlen($row->comment) > 16) ? (rtrim(StringHelper::substr($row->comment, 0, 14)).'&hellip;') : $row->comment; ?>
                    <td><?php if (!empty($cmnt)) { echo HTMLHelper::_('tooltip', $this->escape($row->comment), null, null, $this->escape($cmnt), null, null); } ?></td>
                    <?php endif; ?>
                    <td class="center">
                        <?php if (!$isCommerceReadOnly) : ?>
                            <a href="javascript: void(0);" onclick="return Joomla.listItemTask('cb<?php echo $i;?>','attendees.remove')">
                                <?php echo HTMLHelper::_('image','com_jem/publish_r.webp',Text::_('COM_JEM_REMOVE'),NULL,true); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                    <td class="center">
                    <?php echo $this->escape($row->id); ?>
                    </td>
                    <td class="center">
                        <a class="btn btn-sm btn-outline-primary hasTooltip" href="javascript: void(0);" onclick="return Joomla.listItemTask('cb<?php echo $i;?>','attendees.renotify')" title="<?php echo Text::_('COM_JEM_ATTENDEE_REGISTRATION_RENOTIFY_DESC'); ?>">
                            <span class="fa fa-envelope" aria-hidden="true"></span><span class="fa fa-share" aria-hidden="true"></span>
                            <span class="visually-hidden"><?php echo Text::_('COM_JEM_ATTENDEE_REGISTRATION_RENOTIFY'); ?></span>
                        </a>
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
