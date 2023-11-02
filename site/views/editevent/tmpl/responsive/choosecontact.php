<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

$function = Factory::getApplication()->input->getCmd('function', 'jSelectContact');
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

<div id="jem" class="jem_select_contact">
	<h1 class='componentheading'>
		<?php echo Text::_('COM_JEM_SELECT_CONTACT'); ?>
	</h1>

	<div class="clr"></div>

	<form action="<?php echo JRoute::_('index.php?option=com_jem&view=editevent&layout=choosecontact&tmpl=component&function='.$this->escape($function).'&'.JSession::getFormToken().'=1'); ?>" method="post" name="adminForm" id="adminForm">
		<div class="jem-row valign-baseline">
      <div id="jem_filter" class="jem-form jem-row jem-justify-start">
        <div>
          <?php
          echo '<label for="filter_type">'.Text::_('COM_JEM_FILTER').'</label>';
          ?>
        </div>
        <div class="jem-row jem-justify-start jem-nowrap">
          <?php echo $this->searchfilter; ?>
          <input type="text" name="filter_search" id="filter_search" value="<?php echo $this->lists['search']; ?>" class="inputbox" onChange="document.adminForm.submit();" />
        </div>
        <div class="jem-row jem-justify-start jem-nowrap">
          <button type="submit" class="pointer btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
          <button type="button" class="pointer btn btn-secondary" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>
          <button type="button" class="pointer btn btn-primary" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('', '<?php echo Text::_('COM_JEM_SELECT_CONTACT') ?>');"><?php echo Text::_('COM_JEM_NOCONTACT')?></button>
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

    <hr class="jem-hr"/>
    
    <div class="jem-sort jem-sort-small">
      <div class="jem-list-row jem-small-list">
        <div class="sectiontableheader jem-contact-number"><?php echo Text::_('COM_JEM_NUM'); ?></div>
        <div class="sectiontableheader jem-contact-name"><?php echo JHtml::_('grid.sort', 'COM_JEM_NAME', 'con.name', $this->lists['order_Dir'], $this->lists['order'] ); ?></div>
        <div class="sectiontableheader jem-contact-city"><?php echo JHtml::_('grid.sort', 'COM_JEM_CITY', 'con.suburb', $this->lists['order_Dir'], $this->lists['order'] ); ?></div>
        <div class="sectiontableheader jem-contact-state"><?php echo JHtml::_('grid.sort', 'COM_JEM_STATE', 'con.state', $this->lists['order_Dir'], $this->lists['order'] ); ?></div>
      </div>
    </div>
    
    <ul class="eventlist eventtable">
      <?php if (empty($this->rows)) : ?>
        <li class="jem-event jem-list-row jem-small-list"><?php echo Text::_('COM_JEM_NOCONTACTS'); ?></li>
      <?php else :?>
        <?php foreach ($this->rows as $i => $row) : ?>
          <li class="jem-event jem-list-row jem-small-list row<?php echo $i % 2; ?>">
            <div class="jem-event-info-small jem-contact-number">
              <?php echo $this->pagination->getRowOffset( $i ); ?>
            </div>
            
            <div class="jem-event-info-small jem-contact-name">
              <span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_SELECT'), $row->name, 'editlinktip selectcontact'); ?>>
								 <a class="pointer" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('<?php echo $row->id; ?>', '<?php echo $this->escape(addslashes($row->name)); ?>');"><?php echo $this->escape($row->name); ?></a>
							</span>
            </div>
            
            <div class="jem-event-info-small jem-contact-city">
              <?php echo $this->escape($row->suburb); ?>
            </div>
            
            <div class="jem-event-info-small jem-contact-state">
              <?php echo $this->escape($row->state); ?>
            </div>
          </li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>
    
		<input type="hidden" name="task" value="selectcontact" />
		<input type="hidden" name="option" value="com_jem" />
		<input type="hidden" name="tmpl" value="component" />
		<input type="hidden" name="function" value="<?php echo $this->escape($function); ?>" />
		<input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
	</form>

	<div class="pagination">
		<?php echo $this->pagination->getPagesLinks(); ?>
	</div>
</div>
