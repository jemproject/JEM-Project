<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

$user		= JFactory::getUser();
$userId		= $user->get('id');
$listOrder	= $this->escape($this->state->get('list.ordering'));
$listDirn	= $this->escape($this->state->get('list.direction'));
$canOrder	= $user->authorise('core.edit.state', 'com_jem.category');
$saveOrder	= $listOrder=='ordering';

$params		= (isset($this->state->params)) ? $this->state->params : new JObject();


/*
 Call the highlight function with the text to highlight.
http://johannburkard.de/blog/programming/javascript/highlight-javascript-text-higlighting-jquery-plugin.html

To highlight all occurrances of �bla� (case insensitive) in all li elements, use the following code:
$('li').highlight('bla');

Remove highlighting
The highlight can be removed from any element with the removeHighlight function.
In this example, all highlights under the element with the ID highlight-plugin are removed.

$('#highlight-plugin').removeHighlight();
*/
?>

<script>
window.addEvent('domready', function(){
	var h = <?php echo $params->get('highlight','0'); ?>;

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


<form action="<?php echo JRoute::_('index.php?option=com_jem&view=archive'); ?>" method="post" name="adminForm" id="adminForm">

	<fieldset id="filter-bar">
	<div class="filter-search fltlft">
				<?php echo $this->lists['filter']; ?>
				<input type="text" name="filter_search" id="filter_search" placeholder="<?php echo JText::_( 'COM_JEM_SEARCH' );?>" value="<?php echo $this->escape($this->state->get('filter_search')); ?>" class="text_area" onChange="this.form.submit()" />
				<button type="submit"><?php echo JText::_( 'COM_JEM_GO' ); ?></button>
				<button type="button" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
			</div>

</fieldset>
<div class="clr"> </div>

	<table class="table table-striped" id="articleList">
		<thead>
			<tr>
				<th width="5" class="center"><?php echo JText::_( 'COM_JEM_NUM' ); ?></th>
				<th width="5" class="center"><input type="checkbox" name="checkall-toggle" value="" title="<?php echo JText::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)" /></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_DATE', 'a.dates', $listDirn, $listOrder); ?></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_START', 'a.times', $listDirn, $listOrder); ?></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_EVENT_TITLE', 'a.title', $listDirn, $listOrder); ?></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_VENUE', 'loc.venue', $listDirn, $listOrder); ?></th>
				<th class="title"><?php echo JText::_( 'COM_JEM_CATEGORY' ); ?></th>
				<th class="title"><?php echo JHTML::_('grid.sort', 'COM_JEM_CITY', 'loc.city', $listDirn, $listOrder); ?></th>
				<th class="title"><?php echo JText::_( 'COM_JEM_CREATION' ); ?></th>
			</tr>
		</thead>

		<tfoot>
			<tr>
				<td colspan="20">
					<?php echo $this->pagination->getListFooter(); ?>
				</td>
			</tr>
		</tfoot>

		<tbody id="seach_in_here">
			<?php
			foreach ($this->items as $i => $row) :
				//Prepare date
				$displaydate = JEMOutput::formatLongDateTime($row->dates, null, $row->enddates, null);
				// Insert a break between date and enddate if possible
				$displaydate = str_replace(" - ", " -<br />", $displaydate);

				//Don't display 0 time
				if (!$row->times) {
					$time = '-';
				} else {
					$time = strftime( $this->jemsettings->formattime, strtotime( $row->times ));
					$time = $time.' '.$this->jemsettings->timename;
				}


				$ordering	= ($listOrder == 'ordering');
				/*	$row->cat_link = JRoute::_('index.php?option=com_categories&extension=com_jem&task=edit&type=other&cid[]='. $row->catid);*/
				$canCreate	= $user->authorise('core.create');
				$canEdit	= $user->authorise('core.edit');
				$canCheckin	= $user->authorise('core.manage',		'com_checkin') || $row->checked_out == $userId || $row->checked_out == 0;
				$canChange	= $user->authorise('core.edit.state') && $canCheckin;



				$link 			= 'index.php?option=com_jem&amp;task=events.edit&amp;cid[]='.$row->id;
				$venuelink 		= 'index.php?option=com_jem&amp;task=venue.edit&amp;id='.$row->locid;
				$published 	= JHTML::_('jgrid.published', $row->published, $i, 'archive.');







			?>
			<tr class="row<?php echo $i % 2; ?>">
				<td class="center"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
				<td class="center"><?php echo JHtml::_('grid.id', $i, $row->id); ?></td>
				<td>
					<?php echo $displaydate; ?>
				</td>
				<td><?php echo $time; ?></td>
				<td><?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?></td>
				<td><?php echo $row->venue ? htmlspecialchars($row->venue, ENT_QUOTES, 'UTF-8') : '-'; ?></td>
				<td>
					<?php
				$nr = count($row->categories);
				$ix = 0;
				foreach ($row->categories as $key => $category) :
					$catlink	= 'index.php?option=com_jem&amp;task=categories.edit&amp;cid[]='. $category->id;
					$title = htmlspecialchars($category->catname, ENT_QUOTES, 'UTF-8');
					if (JString::strlen($title) > 20) {
						$title = JString::substr( $title , 0 , 20).'...';
					}

					$path = '';
					$pnr = count($category->parentcats);
					$pix = 0;
					foreach ($category->parentcats as $key => $parentcats) :

						$path .= $parentcats->catname;

						$pix++;
						if ($pix != $pnr) :
							$path .= ' » ';
						endif;
					endforeach;

					if ( $category->cchecked_out && ( $category->cchecked_out != $this->user->get('id') ) ) {
							echo $title;
					} else {
					?>
						<span class="editlinktip hasTip" title="<?php echo JText::_('COM_JEM_EDIT_CATEGORY');?>::<?php echo $path; ?>">
						<a href="<?php echo $catlink; ?>">
							<?php echo $title; ?>
						</a>
						</span>
					<?php
					}
					$ix++;
					if ($ix != $nr) :
						echo ', ';
					endif;
				endforeach;
				?>
				</td>
				<td><?php echo $row->city ? htmlspecialchars($row->city, ENT_QUOTES, 'UTF-8') : '-'; ?></td>
				<td>
					<?php echo JText::_( 'COM_JEM_AUTHOR' ).': '; ?><a href="<?php echo 'index.php?option=com_users&amp;task=edit&amp;hidemainmenu=1&amp;cid[]='.$row->created_by; ?>"><?php echo $row->author; ?></a><br />
					<?php echo JText::_( 'COM_JEM_EMAIL' ).': '; ?><a href="mailto:<?php echo $row->email; ?>"><?php echo $row->email; ?></a><br />
					<?php
					$created	 	= JHTML::Date( $row->created, JText::_( 'DATE_FORMAT_LC2' ) );
					$edited 		= JHTML::Date( $row->modified, JText::_( 'DATE_FORMAT_LC2' ) );
					$ip				= $row->author_ip == 'COM_JEM_DISABLED' ? JText::_( 'COM_JEM_DISABLED' ) : $row->author_ip;
					$image 			= JHTML::image('media/com_jem/images/icon-16-info.png', JText::_('COM_JEM_NOTES') );
					$overlib 		= JText::_( 'COM_JEM_CREATED_AT' ).': '.$created.'<br />';
					$overlib		.= JText::_( 'COM_JEM_WITH_IP' ).': '.$ip.'<br />';
					if ($row->modified != '0000-00-00 00:00:00') {
						$overlib 	.= JText::_( 'COM_JEM_EDITED_AT' ).': '.$edited.'<br />';
						$overlib 	.= JText::_( 'COM_JEM_EDITED_FROM' ).': '.$row->editor.'<br />';
					}
					?>
					<span class="editlinktip hasTip" title="<?php echo JText::_('COM_JEM_EVENT_STATS'); ?>::<?php echo $overlib; ?>">
						<?php echo $image; ?>
					</span>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>

	</table>


	<?php echo JHTML::_( 'form.token' ); ?>
	<input type="hidden" name="boxchecked" value="0" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
	<input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
</form>