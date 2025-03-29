<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Button\FeaturedButton;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Button\PublishedButton;

HTMLHelper::addIncludePath(JPATH_COMPONENT.'/helpers/html');
$user		= JemFactory::getUser();
$userId		= $user->get('id');
$listOrder	= $this->escape($this->state->get('list.ordering'));
$listDirn	= $this->escape($this->state->get('list.direction'));
$canOrder	= $user->authorise('core.edit.state', 'com_jem.category');
$saveOrder	= $listOrder=='a.ordering';

$params		= (isset($this->state->params)) ? $this->state->params : new CMSObject();
$settings	= $this->settings;
$wa = $this->document->getWebAssetManager();
$wa->useScript('table.columns');
?>
<script>
    $(document).ready(function() {
        var h = <?php echo $settings->get('highlight','0'); ?>;

        switch(h)
        {
            case 0:
                break;
            case 1:
                highlightevents();
                break;
        }
    });
</script>

<form action="<?php echo Route::_('index.php?option=com_jem&view=events'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <fieldset id="filter-bar" class=" mb-3">
            <div class="row">
				<div class="col-md-1">				
                    <div class="row">
	                    <div class="wauto-minwmax">
                            <div class="input-group">
                                <?php echo $this->lists['filter']; ?>
                            </div>
                        </div>
                    </div>
                </div>
				<div class="col-md-10">		
                    <div class="row mb-12">    
                        <div class="col-md-3">
                            <div class="input-group">
                                <input type="text" name="filter_search" id="filter_search" class="form-control" aria-describedby="filter_search-desc" placeholder="<?php echo Text::_('COM_JEM_SEARCH');?>" value="<?php echo $this->escape($this->state->get('filter_search')); ?>"  inputmode="search" onChange="document.adminForm.submit();" >

                                <button type="submit" class="filter-search-bar__button btn btn-primary" aria-label="Search">
                                    <span class="filter-search-bar__button-icon icon-search" aria-hidden="true"></span>
                                </button>
                                <button type="button" class="btn btn-primary" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <?php echo HTMLHelper::_('calendar', $this->state->get('filter_begin'), 'filter_begin', 'filter_begin', '%Y-%m-%d' , array('size'=>10, 'onchange'=>"this.form.fireEvent('submit');this.form.submit()",'placeholder'=>Text::_('COM_JEM_EVENTS_FILTER_STARTDATE')));?>
                        </div>
                        <div class="col-md-2">

                            <?php echo HTMLHelper::_('calendar', $this->state->get('filter_end'), 'filter_end', 'filter_end', '%Y-%m-%d' , array('size'=>10, 'onchange'=>"this.form.fireEvent('submit');this.form.submit()",'placeholder'=>Text::_('COM_JEM_EVENTS_FILTER_ENDDATE') ));?>
                        </div>
                        <div class="col-md-1 wauto-minwmax">
                            <select name="filter_state" class="inputbox form-select wauto-minwmax" onchange="this.form.submit()">
                                <option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED');?></option>
                                <?php echo HTMLHelper::_('select.options', HTMLHelper::_('jgrid.publishedOptions'), 'value', 'text', $this->state->get('filter_state'), true);?>
                            </select>
                        </div>
                        <div class="col-md-2 wauto-minwmax">
                            <select name="filter_access" class="inputbox form-select wauto-minwmax" onchange="this.form.submit()">
                                <option value=""><?php echo Text::_('JOPTION_SELECT_ACCESS');?></option>
                                <?php echo HTMLHelper::_('select.options', HTMLHelper::_('access.assetgroups'), 'value', 'text', $this->state->get('filter.access'));?>
                            </select>
                        </div>
                    </div>
                </div>
				<div class="col-md-1">				
                    <div class="row">
                        <div class="wauto-minwmax">
                            <div class=" float-end">
                                <?php echo $this->pagination->getLimitBox(); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </fieldset>
        <div class="clr"> </div>
        <div class="table">
            <table class="table table-striped itemList" id="eventList">
                <thead>
                <tr>
                    <th style="width: 1%" class="center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
                    <th class="nowrap"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_DATE', 'a.dates', $listDirn, $listOrder ); ?></th>
                    <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_STARTTIME_SHORT', 'a.times', $listDirn, $listOrder ); ?></th>
                    <th class="nowrap"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_EVENT_TITLE', 'a.title', $listDirn, $listOrder ); ?></th>
                    <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_VENUE', 'loc.venue', $listDirn, $listOrder ); ?></th>
                    <th><?php echo Text::_('COM_JEM_CATEGORIES'); ?></th>
                    <th style="width: 1%"><?php echo HTMLHelper::_('grid.sort', 'JFEATURED', 'a.featured', $listDirn, $listOrder, NULL, 'desc'); ?></th>
                    <th style="width: 1%" class="center nowrap"><?php echo Text::_('JSTATUS'); ?></th>
                    <th class="nowrap"><?php echo Text::_('COM_JEM_AUTHOR'); ?></th>
                    <th class="center"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_HITS', 'a.hits', $listDirn, $listOrder ); ?></th>
                    <th style="width: 1%" class="center nowrap"><?php echo Text::_('COM_JEM_REGISTERED_USERS_SHORT'); ?></th>
                    <th style="width: 9%" class="center"><?php echo HTMLHelper::_('grid.sort',  'JGRID_HEADING_ACCESS', 'a.access', $listDirn, $listOrder); ?></th>
                    <th style="width: 1%" class="center nowrap"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ID', 'a.id', $listDirn, $listOrder ); ?></th>
                </tr>
                </thead>

                <tbody id="search_in_here">
                <?php
                foreach ($this->items as $i => $row) :
                    //Prepare date
                    $displaydate = JemOutput::formatShortDateTime($row->dates, null, $row->enddates, null, $this->jemsettings->showtime);
                    // Insert a break between date and enddate if possible
                    $displaydate = str_replace(" - ", " -<br />", $displaydate);

                    //Prepare time
                    if (!$row->times) {
                        $displaytime = '-';
                    } else {
                        $displaytime = JemOutput::formattime($row->times);
                    }

                    $ordering	= ($listOrder == 'ordering');
                    $canCreate	= $user->authorise('core.create');
                    $canEdit	= $user->authorise('core.edit');
                    $canCheckin	= $user->authorise('core.manage', 'com_checkin') || $row->checked_out == $userId || $row->checked_out == 0;
                    $canChange	= $user->authorise('core.edit.state') && $canCheckin;

                    $venuelink 		= 'index.php?option=com_jem&amp;task=venue.edit&amp;id='.$row->locid;
                    $published 		= HTMLHelper::_('jgrid.published', $row->published, $i, 'events.');
                    ?>
                    <tr class="row<?php echo $i % 2; ?>">
                        <td class="center"><?php echo HTMLHelper::_('grid.id', $i, $row->id); ?></td>
                        <td class="startdate">
                            <?php if ($row->checked_out) : ?>
                                <?php echo HTMLHelper::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'events.', $canCheckin); ?>
                            <?php endif; ?>
                            <?php if ($canEdit) : ?>
                                <a href="<?php echo Route::_('index.php?option=com_jem&task=event.edit&id='.(int) $row->id); ?>">
                                    <?php echo $displaydate; ?>
                                </a>
                            <?php else : ?>
                                <?php echo $displaydate; ?>
                            <?php endif; ?>
                        </td>
                        <td class="starttime"><?php echo $displaytime; ?></td>
                        <td class="eventtitle">
                            <?php if ($canEdit) : ?>
                                <a href="<?php echo Route::_('index.php?option=com_jem&task=event.edit&id='.(int) $row->id); ?>">
                                    <?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row); ?>
                                </a>
                            <?php else : ?>
                                <?php echo $this->escape($row->title) . JemOutput::recurrenceicon($row); ?>
                            <?php endif; ?>
                            <br />
                            <?php if (\Joomla\String\StringHelper::strlen($row->alias) > 25) : ?>
                                <?php echo \Joomla\String\StringHelper::substr( $this->escape($row->alias), 0 , 25).'...'; ?>
                            <?php else : ?>
                                <?php echo $this->escape($row->alias); ?>
                            <?php endif; ?>
                        </td>
                        <td class="venue">
                            <?php if ($row->venue) : ?>
                                <?php if ( $row->vchecked_out && ( $row->vchecked_out != $this->user->get('id') ) ) : ?>
                                    <?php echo $this->escape($row->venue); ?>
                                <?php else : ?>
                                    <span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_EDIT_VENUE'), $row->venue, 'editlinktip'); ?>>
										<a href="<?php echo $venuelink; ?>">
											<?php echo $this->escape($row->venue); ?>
										</a>
									</span>
                                <?php endif; ?>
                            <?php else : ?>
                                <?php echo '-'; ?>
                            <?php endif; ?>
                        </td>
                        <td class="category">
                            <?php echo implode(", ", JemOutput::getCategoryList($row->categories, $this->jemsettings->catlinklist,true)); ?>
                        </td>
                        <td class="center">
                            <?php //echo HTMLHelper::_('jemhtml.featured', $i, $row->featured, $canChange);
                            $options = [
                                'task_prefix' => 'events.',
                                'disabled' => !$canChange,
                                'id' => 'featured-' . $row->id
                            ];
                            echo (new FeaturedButton())->render((int) $row->featured, $i, $options);
                            ?>
                        </td>
                        <td class="center">
                            <?php
                            $options = [
                            'task_prefix' => 'events.',
                            'disabled' => !$canChange,
                            'id' => 'state-' . $row->id
                            ];
                            echo (new PublishedButton())->render((int) $row->published, $i, $options, $row->publish_up, $row->publish_down);
                            ?>
                        </td>
                        <td>
                            <?php
                            $created	 	= HTMLHelper::_('date',$row->created,Text::_('DATE_FORMAT_LC5'));
                            $image 			= HTMLHelper::_('image','com_jem/icon-16-info.png',NULL,NULL,true );
                            $overlib 		= Text::_('COM_JEM_CREATED_AT').': '.$created.'<br />';
                            $overlib 		.= Text::_('COM_JEM_AUTHOR').'</strong>: ' . $row->author.'<br />';
                            $overlib 		.= Text::_('COM_JEM_EMAIL').'</strong>: ' . $row->email.'<br />';
                            if ($row->author_ip != '') {
                                $overlib		.= Text::_('COM_JEM_WITH_IP').': '.$row->author_ip.'<br />';
                            }
                            if (!empty($row->modified)) {
                                $overlib 	.= '<br />'.Text::_('COM_JEM_EDITED_AT').': '. HTMLHelper::_('date',$row->modified,Text::_('DATE_FORMAT_LC5') ) .'<br />'. Text::_('COM_JEM_GLOBAL_MODIFIEDBY').': '.$row->modified_by;
                            }
                            ?>
                            <span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_EVENTS_STATS'), $overlib, 'editlinktip'); ?>

                            <a href="<?php echo 'index.php?option=com_users&amp;task=edit&amp;hidemainmenu=1&amp;cid[]='.$row->created_by; ?>"><?php echo $row->author; ?></a></span>


                        </td>
                        <td class="center"><?php echo $row->hits; ?></td>

                        <td class="center">
                            <?php
                            if ($this->jemsettings->showfroregistra || ($row->registra & 1)) {
                                $linkreg 	= 'index.php?option=com_jem&amp;view=attendees&amp;eventid='.$row->id;
                                $count = $row->regCount+$row->reserved;
                                if ($row->maxplaces)
                                {
                                    $count .= '/'.$row->maxplaces;
                                    if ($row->waitinglist && $row->waiting) {
                                        $count .= '+'.$row->waiting;
                                    }
                                }
                                if (!empty($row->unregCount)) {
                                    $count .= '-'.(int)$row->unregCount;
                                }
                                if (!empty($row->invited)) {
                                    $count .= ','.(int)$row->invited .'?';
                                }
                                ?>
                                <a href="<?php echo $linkreg; ?>" title="<?php echo Text::_('COM_JEM_EVENTS_MANAGEATTENDEES'); ?>">
                                    <?php echo $count; ?>
                                </a>
                            <?php } else { ?>
                                <?php echo HTMLHelper::_('image', 'com_jem/publish_r.png', NULL, NULL, true); ?>
                            <?php } ?>
                        </td>
                        <td class="center">
                            <?php echo $this->escape($row->access_level); ?>
                        </td>
                        <td class="center">
							<?php echo (int) $row->id; ?>
						</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
			
			<div class="ms-auto mb-4 me-0">
                <?php echo  (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks(null) : $this->pagination->getListFooter()); ?>           
            </div>
        </div>
    </div>
    <?php //if (isset($this->sidebar)) : ?>
    <?php //endif; ?>

    <div>
	    <input type="hidden" name="task" value="" />
	    <input type="hidden" name="boxchecked" value="0" />
	    <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
	    <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />

	    <?php echo HTMLHelper::_('form.token'); ?>
	</div>
</form>
