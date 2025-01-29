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
use Joomla\CMS\Object\CMSObject;

$user		= JemFactory::getUser();
$userId		= $user->get('id');
$listOrder	= $this->escape($this->state->get('list.ordering'));
$listDirn	= $this->escape($this->state->get('list.direction'));
$canOrder	= $user->authorise('core.edit.state', 'com_jem.category');
$saveOrder	= $listOrder=='ordering';

$params		= (isset($this->state->params)) ? $this->state->params : new CMSObject();
?>

<form action="<?php echo Route::_('index.php?option=com_jem&view=groups'); ?>" method="post" name="adminForm" id="adminForm">
	<div id="j-main-container" class="j-main-container">
	    <fieldset id="filter-bar" class=" mb-3">
            <div class="row">
				<div class="col-md-11">		
                    <div class="row mb-12">
						<div class="col-md-4">
							<div class="input-group">  
								<input type="text" name="filter_search" id="filter_search" class="form-control" aria-describedby="filter_search-desc" placeholder="<?php echo Text::_('COM_JEM_SEARCH');?>" value="<?php echo $this->escape($this->state->get('filter_search')); ?>"  inputmode="search" onChange="document.adminForm.submit();" >											
						
								<button type="submit" class="filter-search-bar__button btn btn-primary" aria-label="Search">
									<span class="filter-search-bar__button-icon icon-search" aria-hidden="true"></span>
								</button>
								<button type="button" class="btn btn-primary" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
							</div>
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
        <div class="clr"> </div>

        <table class="table table-striped" id="articleList">
            <thead>
            <tr>
                <th style="width: 5px" class="center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
                <th style="width: 30%" class="title"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_GROUP_NAME', 'name', $listDirn, $listOrder ); ?></th>
				<th><?php echo Text::_( 'COM_JEM_DESCRIPTION' ); ?></th>
                <th style="width: 1%" class="title"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ID', 'id', $listDirn, $listOrder ); ?></th>
				</tr>
			</thead>

			<tbody>
				<?php foreach ($this->items as $i => $row) :
					$ordering	= ($listOrder == 'ordering');
					$canCreate	= $user->authorise('core.create');
					$canEdit	= $user->authorise('core.edit');
					$canCheckin	= $user->authorise('core.manage',		'com_checkin') || $row->checked_out == $userId || $row->checked_out == 0;
					$canChange	= $user->authorise('core.edit.state') && $canCheckin;

					$link 		= 'index.php?option=com_jem&amp;task=group.edit&amp;id='.$row->id;
				?>
				<tr class="row<?php echo $i % 2; ?>">
					<td class="center"><?php echo HTMLHelper::_('grid.id', $i, $row->id); ?></td>
					<td>
						<?php if ($row->checked_out) : ?>
							<?php echo HTMLHelper::_('jgrid.checkedout', $i, $row->editor, $row->checked_out_time, 'groups.', $canCheckin); ?>
						<?php endif; ?>
						<?php if ($canEdit) : ?>
							<a href="<?php echo $link; ?>">
								<?php echo $this->escape($row->name); ?>
							</a>
						<?php else : ?>
								<?php echo $this->escape($row->name); ?>
						<?php endif; ?>
					</td>
					<td>
						<?php
							$desc = $row->description;
							$descoutput = strip_tags($desc);
							echo $this->escape($descoutput);
						?>
					</td>
                    <td class="center"><?php echo $row->id; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
			
		<div class="ms-auto mb-4 me-0">
            <?php echo  (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks(null) : $this->pagination->getListFooter()); ?>           
        </div>
	</div>

    <div>
	    <input type="hidden" name="task" value="" />
	    <input type="hidden" name="boxchecked" value="0" />
	    <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
	    <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />

	    <?php echo HTMLHelper::_('form.token'); ?>
	</div>
</form>
