<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

JHtml::_('behavior.tooltip');
$colspan = ($this->event->waitinglist ? 10 : 9);

$detaillink = JRoute::_(JEMHelperRoute::getEventRoute($this->event->id.':'.$this->event->alias));

?>
<script type="text/javascript">
	function tableOrdering(order, dir, view)
	{
		var form = document.getElementById("adminForm");

		form.filter_order.value 	= order;
		form.filter_order_Dir.value	= dir;
		form.submit(view);
	}
</script>

<div id="jem" class="jem_jem">
<div class="buttons">
	<?php
		echo JEMOutput::printbutton( $this->print_link, $this->params );
		echo JEMOutput::exportbutton( $this->event->id);
		echo JEMOutput::backbutton( $this->backlink, $this->view );
	?>
</div>

<?php if ($this->params->def( 'show_page_title', 1 )) : ?>

    <h1 class="componentheading">
		<?php echo $this->escape($this->pagetitle); ?>
	</h1>

<?php endif; ?>


<?php if ($this->params->get('showintrotext')) : ?>
	<div class="description no_space floattext">
		<?php echo $this->params->get('introtext'); ?>
	</div>
<?php endif; ?>

<h2><?php echo htmlspecialchars($this->event->title, ENT_QUOTES, 'UTF-8'); ?></h2>

<form action="<?php echo $this->action; ?>"  method="post" name="adminForm" id="adminForm">

	<table class="adminlist">
		<tr>
		  	<td width="80%">

				<b><?php echo JText::_( 'COM_JEM_TITLE' ).':'; ?></b>&nbsp;<a href="<?php echo $detaillink ; ?>"><?php echo htmlspecialchars($this->event->title, ENT_QUOTES, 'UTF-8'); ?></a>
				<br />
				<b><?php echo JText::_( 'COM_JEM_DATE' ).':'; ?></b>&nbsp;

				<?php echo JEMOutput::formatLongDateTime($this->event->dates, $this->event->times,
				$this->event->enddates, $this->event->endtimes); ?>
			</td>

		  </tr>
	</table>
	<br />
	<div id="jem_filter" class="floattext">
		<div class="jem_fleft">
			 	<?php echo JText::_( 'COM_JEM_SEARCH' ).' '.$this->lists['filter']; ?>
				<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search']; ?>" class="inputbox" onChange="document.adminForm.submit();" />
				<button class="buttonfilter" type="submit"><?php echo JText::_('COM_JEM_GO'); ?></button>
			<button class="buttonfilter" type="button" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
			</div>
			<?php if ($this->event->waitinglist): ?>
			 <div style="text-align:right; white-space:nowrap;">
			 	<?php echo JText::_( 'COM_JEM_STATUS' ).' '.$this->lists['waiting'];
			 	echo '&nbsp;';
			 	?>
			</div>
			<?php endif; ?>
			<div class="jem_fright">
			<?php
			echo '<label for="limit">'.JText::_('COM_JEM_DISPLAY_NUM').'</label>&nbsp;';
			echo $this->pagination->getLimitBox();
			?>
		</div></div>
	<table class="eventtable" style="width:100%" id="articleList">
		<thead>
			<tr>
				<th width="1%" class="center"><?php echo JText::_( 'COM_JEM_NUM' ); ?></th>
				<th width="1%" class="center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
				<th class="title"><?php echo JHtml::_('grid.sort', 'COM_JEM_USERNAME', 'u.username', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>

				<?php if ($this->enableemailaddress == 1) {?>
				<th class="title"><?php echo JText::_( 'COM_JEM_EMAIL' ); ?></th>
				<?php } ?>


				<th class="title"><?php echo JHtml::_('grid.sort', 'COM_JEM_REGDATE', 'r.uregdate', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php if ($this->event->waitinglist): ?>
				<th class="center"><?php echo JHtml::_('grid.sort', 'COM_JEM_HEADER_WAITINGLIST_STATUS', 'r.waiting', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
				<?php endif;?>
				<th class="center"><?php echo JText::_( 'COM_JEM_REMOVE_USER' ); ?></th>
			</tr>
		</thead>

		<tbody>
			<?php
   		foreach ($this->rows as $i => $row) :
			?>
			<tr class="row<?php echo $i % 2; ?>">
				<td class="center"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
				<td class="center"><?php echo JHtml::_('grid.id', $i, $row->id); ?></td>
				<td>
					<?php echo $row->username; ?>
				</td>


				<?php if ($this->enableemailaddress == 1) {?>
				<td><a href="mailto:<?php echo $row->email; ?>"><?php echo $row->email; ?></a></td>
				<?php } ?>

				<td><?php echo JHtml::_('date',$row->uregdate,JText::_('DATE_FORMAT_LC2')); ?></td>
				<?php if ($this->event->waitinglist): ?>
				<td class="hasTip center" title="<?php echo ($row->waiting ? JText::_('COM_JEM_ON_WAITINGLIST') : JText::_('COM_JEM_ATTENDEES_ATTENDING')).'::'; ?>">
					<?php if ($row->waiting):?>
						<?php echo JHtml::_('link',JRoute::_('index.php?option=com_jem&view=attendees&amp;task=attendees.attendeetoggle&id='.$row->id),JHtml::_('image','com_jem/publish_y.png',JText::_('COM_JEM_ON_WAITINGLIST'),NULL,true)); ?>
					<?php else: ?>
						<?php echo JHtml::_('link',JRoute::_('index.php?option=com_jem&view=attendees&amp;task=attendees.attendeetoggle&id='.$row->id),JHtml::_('image','com_jem/tick.png', JText::_('COM_JEM_ATTENDEES_ATTENDING'),NULL,true)); ?>
					<?php endif;?>
				</td>
				<?php endif;?>
				<td class="center"><a href="javascript: void(0);" onclick="return listItemTask('cb<?php echo $i;?>','attendees.attendeeremove')"><?php echo
						                        JHtml::_('image','com_jem/publish_x.png',JText::_('COM_JEM_ATTENDEES_DELETE'),NULL,true); ?></a></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
		<?php echo JHtml::_( 'form.token' ); ?>
		<input type="hidden" name="option" value="com_jem" />
		<input type="hidden" name="boxchecked" value="0" />
		<input type="hidden" name="task" value="" />
		<input type="hidden" name="view" value="attendees" />
		<input type="hidden" name="id" value="<?php echo $this->event->id; ?>" />
		<input type="hidden" name="Itemid" value="<?php echo $this->item->id;?>" />
		<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
		<input type="hidden" name="enableemailaddress" value="<?php echo $this->enableemailaddress; ?>" />
</form>
<div class="pagination">
	<?php echo $this->pagination->getPagesLinks(); ?>
</div>

<div class="copyright">
<?php echo JEMOutput::footer(); ?>
</div>

</div>