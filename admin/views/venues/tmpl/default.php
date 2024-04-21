<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Object\CMSObject;

$user		= JemFactory::getUser();
$userId		= $user->get('id');
$listOrder	= $this->escape($this->state->get('list.ordering'));
$listDirn	= $this->escape($this->state->get('list.direction'));
$canOrder	= $user->authorise('core.edit.state', 'com_jem.category');
$saveOrder	= $listOrder=='a.ordering';
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->useScript('table.columns');
?>

<form action="<?php echo Route::_('index.php?option=com_jem&view=venues'); ?>" method="post" name="adminForm" id="adminForm">
	<div id="j-main-container" class="j-main-container">
		<fieldset id="filter-bar" class=" mb-3">
			<div class="row mb-3">
				<div class="col-md-4">
					<div class="input-group">  
						<input type="text" name="filter_search" id="filter_search" class="form-control" aria-describedby="filter_search-desc" placeholder="<?php echo Text::_('COM_JEM_SEARCH');?>" value="<?php echo $this->escape($this->state->get('filter_search')); ?>"  inputmode="search" onChange="document.adminForm.submit();" >											
						
						<button type="submit" class="filter-search-bar__button btn btn-primary" aria-label="Search">
							<span class="filter-search-bar__button-icon icon-search" aria-hidden="true"></span>
						</button>
						<button type="button" class="btn btn-primary" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
					</div>
				</div>
				<div class="col-md-8">
					<div class="filter-select fltrt">
						<select name="filter_state" class="inputbox form-select" onchange="this.form.submit()">
							<option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED');?></option>
							<?php echo HTMLHelper::_('select.options', HTMLHelper::_('jgrid.publishedOptions',array('all' => 0, 'archived' => 0, 'trash' => 0)), 'value', 'text', $this->state->get('filter_state'), true);?>
						</select>
					</div>
				</div>
			<!-- <div class="filter-search fltlft">
				<?php echo $this->lists['filter']; ?>
				<input type="text" name="filter_search" id="filter_search" placeholder="<?php echo Text::_('COM_JEM_SEARCH');?>" value="<?php echo $this->escape($this->state->get('filter_search')); ?>" class="text_area" onChange="document.adminForm.submit();" />
				<button type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
				<button type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
			</div> -->

		</fieldset>
		<div class="clr"> </div>

		<table class="table table-striped" id="articleList">
			<thead>
				<tr>
					<th style="width:1%" class="center">
						<input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" />
					</th>
					<th class="title">
						<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_VENUE', 'a.venue', $listDirn, $listOrder ); ?>
					</th>
					<th style="width:20%">
						<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ALIAS', 'a.alias', $listDirn, $listOrder ); ?>
					</th>
					<th>
						<?php echo Text::_('COM_JEM_WEBSITE'); ?>
					</th>
					<th style="width:10%">
						<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_CITY', 'a.city', $listDirn, $listOrder ); ?>
					</th>
					<th style="width:5%" class="center">
						<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_STATE', 'a.state', $listDirn, $listOrder ); ?>
						</th>
					<th style="width:5%" class="center">
						<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_COUNTRY', 'a.country', $listDirn, $listOrder ); ?>
					</th>
					<th style="width:5%" class="center" nowrap="nowrap">
						<?php echo Text::_('JSTATUS'); ?>
					</th>
					<th>
						<?php echo Text::_('COM_JEM_CREATION'); ?>
					</th>
					<th style="width:5%" class="center" nowrap="nowrap">
						<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_EVENTS', 'assignedevents', $listDirn, $listOrder ); ?>
						</th>
					<th style="width:5%" class="center">
						<?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ORDERING', 'a.ordering', $listDirn, $listOrder ); ?>
						<?php if ($saveOrder) :?>
							<?php //echo HTMLHelper::_('grid.order',  $this->items, 'filesave.png', 'venues.saveorder'); ?>
						<?php endif; ?>
					</th>
					<th style="width:1%" class="center" nowrap="nowrap">
						<?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ID', 'a.id', $listDirn, $listOrder ); ?>
					</th>
				</tr>
			</thead>

			<tfoot>
				<tr>
					<td colspan="20">
						<?php //echo (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks(null, array('showLimitBox' => true)) : $this->pagination->getListFooter()); ?>
						<div class="row align-items-center">
                            <div class="col-md-9">
                                <?php
                                echo  (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks(null) : $this->pagination->getListFooter());
                                ?>
                            </div>
							<div class="col-md-3">
								<div class="limit float-end">
									<?php 
										echo $this->pagination->getLimitBox();	
									?>
								</div>
							</div>
						</div>
					</td>
				</tr>
			</tfoot>

			<tbody>
				<?php
                $countItems = count($this->items);
				foreach ($this->items as $i => $item) : ?>
					<?php
					$ordering	= ($listOrder == 'a.ordering');
					$canCreate	= $user->authorise('core.create');
					$canEdit	= $user->authorise('core.edit');
					$canCheckin	= $user->authorise('core.manage', 'com_checkin') || $item->checked_out == $userId || $item->checked_out == 0;
					$canEditOwn	= $user->authorise('core.edit.own') && $item->created_by == $userId;
					$canChange	= $user->authorise('core.edit.state') && $canCheckin;
					$link 		= 'index.php?option=com_jem&amp;task=venue.edit&amp;id='. $item->id;
					$published 	= HTMLHelper::_('jgrid.published', $item->published, $i, 'venues.', $canChange, 'cb', $item->publish_up, $item->publish_down);
				?>
					<tr class="row<?php echo $i % 2; ?>">
						<td class="center">
							<?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
						<td style="text-align:left" class="venue">
							<?php if ($item->checked_out) : ?>
								<?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'venues.', $canCheckin); ?>
							<?php endif; ?>
							<?php if ($canEdit || $canEditOwn) : ?>
								<a href="<?php echo Route::_('index.php?option=com_jem&task=venue.edit&id='.(int) $item->id); ?>">
									<?php echo $this->escape($item->venue); ?>
								</a>
							<?php else : ?>
								<?php echo $this->escape($item->venue); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if (\Joomla\String\StringHelper::strlen($item->alias) > 25) : ?>
								<?php echo $this->escape(\Joomla\String\StringHelper::substr($item->alias, 0 , 25)).'...'; ?>
							<?php else : ?>
								<?php echo $this->escape($item->alias); ?>
							<?php endif; ?>
						</td>
						<td style="text-align:left">
							<?php if ($item->url) : ?>
								<a href="<?php echo $this->escape($item->url); ?>" target="_blank">
									<?php if (\Joomla\String\StringHelper::strlen($item->url) > 25) : ?>
										<?php echo $this->escape(\Joomla\String\StringHelper::substr($item->url, 0 , 25)).'...'; ?>
									<?php else : ?>
										<?php echo $this->escape($item->url); ?>
									<?php endif; ?>
								</a>
							<?php else : ?>
								-
							<?php endif; ?>
						</td>
						<td style="text-align:left" class="city"><?php echo $item->city ? $this->escape($item->city) : '-'; ?></td>
						<td class="center state"><?php echo $item->state ? $this->escape($item->state) : '-'; ?></td>
						<td class="center country"><?php echo $item->country ? $this->escape($item->country) : '-'; ?></td>
						<td class="center"><?php echo $published; ?></td>
						<td>
							<?php
							$created	 	= HTMLHelper::_('date',$item->created,Text::_('DATE_FORMAT_LC5'));
							$image 			= HTMLHelper::_('image','com_jem/icon-16-info.png', NULL,NULL,true);
							$overlib 		= Text::_('COM_JEM_CREATED_AT').': '.$created.'<br />';
							$overlib 		.= Text::_('COM_JEM_AUTHOR').'</strong>: ' . $item->author.'<br />';
							$overlib 		.= Text::_('COM_JEM_EMAIL').'</strong>: ' . $item->email.'<br />';
							if ($item->author_ip != '') {
								$overlib		.= Text::_('COM_JEM_WITH_IP').': '.$item->author_ip.'<br />';
							}
							if (!empty($item->modified)) {
								$overlib 	.= '<br />'.Text::_('COM_JEM_EDITED_AT').': '. HTMLHelper::_('date',$item->modified,Text::_('DATE_FORMAT_LC5') ) .'<br />'. Text::_('COM_JEM_GLOBAL_MODIFIEDBY').': '.$item->modified_by;
							}
							?>
							<span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_EVENTS_STATS'), $overlib, 'editlinktip'); ?>
							
							<a href="<?php echo 'index.php?option=com_users&amp;task=edit&amp;hidemainmenu=1&amp;cid[]='.$item->created_by; ?>"><?php echo $item->author; ?></a></span>
							
							
						</td>
						<td class="center"><?php echo $item->assignedevents; ?></td>
						<td class="order">
						<?php if ($canChange) : ?>
                            <?php $disabled = $saveOrder ?  '' : 'disabled="disabled"'; ?>
                            <div style="display:-webkit-box">
                            	<div><input type="text" style="text-align: center; margin: auto 0; min-width: 50px;" name="order[]" size="5" value="<?php echo $item->ordering;?>" <?php echo $disabled ?> class="text-area-order" /></div>

							<?php if ($saveOrder) :
								if ($listDirn == 'asc') : ?>
									<div><?php if($i) : ?>
										<span><?php echo $this->pagination->orderUpIcon( $i, true, 'venues.orderup', 'JLIB_HTML_MOVE_UP', $ordering ); ?></span>
	                                <?php else : ?>
	                                        <div style='width:32px;'>&nbsp;</div>
	                                <?php endif; ?></div>
	                                <div><?php if($countItems != $i+1) : ?>
										<span><?php echo $this->pagination->orderDownIcon( $i,$this->pagination->total, true, 'venues.orderdown', 'JLIB_HTML_MOVE_DOWN', $ordering ); ?></span>
	                                <?php else : ?>
	                                    <div style='width:32px;'>&nbsp;</div>
	                                <?php endif; ?></div>
								<?php elseif ($listDirn == 'desc') : ?>
									<div><?php if($i) : ?>
										<span><?php echo $this->pagination->orderUpIcon( $i, true, 'venues.orderdown', 'JLIB_HTML_MOVE_UP', $ordering ); ?></span>
	                                <?php else : ?>
	                                    <div style='width:32px;'>&nbsp;</div>
	                                <?php endif; ?></div>
	                                <div><?php if($countItems != $i+1) : ?>
										<span><?php echo $this->pagination->orderDownIcon( $i,$this->pagination->total, true, 'venues.orderup', 'JLIB_HTML_MOVE_DOWN', $ordering ); ?></span>
	                                <?php else : ?>
	                                    <div style='width:32px;'>&nbsp;</div>
	                                <?php endif; ?></div>
								<?php endif; ?>
							<?php endif; ?>
                            </div>
						<?php else : ?>
							<?php echo $item->ordering; ?>
						<?php endif; ?>
						</td>
						<td class="center">
							<?php echo (int) $item->id; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
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
