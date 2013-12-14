<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>

<form action="index.php?option=com_jem&amp;view=eventelement&amp;tmpl=component" method="post" name="adminForm" id="adminForm">

<table class="adminform">
	<tr>
		<td width="100%">
			<?php echo JText::_( 'COM_JEM_SEARCH' ).' '.$this->lists['filter']; ?>
			<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search']; ?>" class="text_area" onChange="document.adminForm.submit();" />
			<button class="buttonfilter" type="submit"><?php echo JText::_('COM_JEM_GO'); ?></button>
			<button class="buttonfilter" type="button" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
		</td>
		<td nowrap="nowrap">
			<?php echo $this->lists['state'];	?>
		</td>
	</tr>
</table>

<table class="table table-striped" id="articleList">
	<thead>
		<tr>
			<th class="center" width="5"><?php echo JText::_( 'COM_JEM_NUM' ); ?></th>
			<th class="title"><?php echo JHtml::_('grid.sort', 'COM_JEM_EVENT_TITLE', 'a.title', $this->lists['order_Dir'], $this->lists['order'], 'eventelement' ); ?></th>
			<th class="title"><?php echo JHtml::_('grid.sort', 'COM_JEM_DATE', 'a.dates', $this->lists['order_Dir'], $this->lists['order'], 'eventelement' ); ?></th>
			<th class="title"><?php echo JHtml::_('grid.sort', 'COM_JEM_START', 'a.times', $this->lists['order_Dir'], $this->lists['order'], 'eventelement' ); ?></th>
			<th class="title"><?php echo JHtml::_('grid.sort', 'COM_JEM_VENUE', 'loc.venue', $this->lists['order_Dir'], $this->lists['order'], 'eventelement' ); ?></th>
			<th class="title"><?php echo JHtml::_('grid.sort', 'COM_JEM_CITY', 'loc.city', $this->lists['order_Dir'], $this->lists['order'], 'eventelement' ); ?></th>
			<th class="title"><?php echo JText::_('COM_JEM_CATEGORY'); ?></th>
		    <th class="center" width="1%" nowrap="nowrap"><?php echo JText::_( 'COM_JEM_PUBLISHED' ); ?></th>
		</tr>
	</thead>

	<tfoot>
		<tr>
			<td colspan="8">
				<?php echo $this->pagination->getListFooter(); ?>
			</td>
		</tr>
	</tfoot>

	<tbody>
			<?php foreach ($this->rows as $i => $row) : ?>
		<tr class="row<?php echo $i % 2; ?>">
			<td class="center"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
			<td>
				<span class="editlinktip hasTip" title="<?php echo JText::_( 'COM_JEM_SELECT' );?>::<?php echo $row->title; ?>">
				<a style="cursor:pointer" onclick="window.parent.elSelectEvent('<?php echo $row->id; ?>', '<?php echo str_replace( array("'", "\""), array("\\'", ""), $row->title ); ?>');">
					<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
				</a></span>
			</td>
			<td>
				<?php
					//Format date
					echo JEMOutput::formatLongDateTime($row->dates, null, $row->enddates, null);
				?>
			</td>
			<td>
				<?php
					//Prepare time
					if (!$row->times) {
						$displaytime = '-';
					} else {
						$time = strftime( $this->jemsettings->formattime, strtotime( $row->times ));
						$displaytime = $time.' '.$this->jemsettings->timename;
					}
					echo $displaytime;
				?>
			</td>
			<td><?php echo $row->venue ? htmlspecialchars($row->venue, ENT_QUOTES, 'UTF-8') : '-'; ?></td>
			<td><?php echo $row->city ? htmlspecialchars($row->city, ENT_QUOTES, 'UTF-8') : '-'; ?></td>
			<td><?php
				$nr = count($row->categories);
				$ix = 0;
				foreach ($row->categories as $key => $category) :
					$catlink	= 'index.php?option=com_jem&amp;controller=categories&amp;task=edit&amp;cid[]='. $category->id;
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


							<?php echo $title; ?>


					<?php
					}
					$ix++;
					if ($ix != $nr) :
						echo ', ';
					endif;
				endforeach;
				?></td>
			<td class="center">
				<?php 
				$img = $row->published ? 'tick.png' : 'publish_x.png';
				echo JHtml::_('image','com_jem/'.$img,NULL,NULL,true); 
				?>
			</td>
		</tr>
			<?php endforeach; ?>
	</tbody>

</table>

<p class="copyright">
	<?php echo JEMAdmin::footer( ); ?>
</p>

<input type="hidden" name="task" value="" />
<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>